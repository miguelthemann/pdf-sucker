// Desenvolvido pelo Sr. Engenheiro João

import { formatBytes, randomId, fileLooksLikePdf } from './util.js';
import { uploadPdfs, compressPdfs, deleteServerFiles, downloadZip } from './api.js';

/** @typedef {{ localId: string, serverId: string|null, name: string, originalSize: number, compressedSize: number|null, reduction: number|null, status: string, error: string|null }} FileItem */

const cfg = window.__APP__ || {
    gsOk: true,
    maxFileBytes: 50 * 1024 * 1024,
    maxFiles: 20,
    maxParallelCompression: 4,
};

/** @type {FileItem[]} */
const items = [];

const el = {
    drop: document.getElementById('drop-zone'),
    input: document.getElementById('file-input'),
    browse: document.getElementById('browse-btn'),
    list: document.getElementById('file-list'),
    empty: document.getElementById('empty-hint'),
    count: document.getElementById('file-count'),
    tpl: document.getElementById('row-template'),
    dlAll: document.getElementById('download-all-btn'),
    progWrap: document.getElementById('progress-wrap'),
    progFill: document.getElementById('progress-fill'),
    progBar: document.getElementById('progress-bar'),
    progLabel: document.getElementById('progress-label'),
};

function syncEmpty() {
    const n = items.length;
    el.empty.hidden = n > 0;
    el.count.textContent = String(n);
    const anyDone = items.some((i) => i.status === 'done');
    el.dlAll.disabled = !anyDone;
}

/**
 * @param {FileItem} item
 */
async function downloadSingle(item) {
    if (!item.serverId) return;
    try {
        const res = await fetch(
            `download.php?id=${encodeURIComponent(item.serverId)}&type=compressed`,
            { credentials: 'same-origin' }
        );
        if (!res.ok) {
            const t = await res.text();
            throw new Error(t.trim() || 'Não foi possível descarregar.');
        }
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const base = item.name.replace(/\.pdf$/i, '');
        a.download = `${base}_comprimido.pdf`;
        a.click();
        URL.revokeObjectURL(url);

        const idx = items.indexOf(item);
        if (idx >= 0) {
            items.splice(idx, 1);
        }
        renderList();
    } catch (e) {
        alert(e instanceof Error ? e.message : 'Erro ao descarregar.');
    }
}

/**
 * @param {FileItem} item
 */
function rowFor(item) {
    const tplRoot = el.tpl.content.querySelector('li.file-row');
    if (!tplRoot) {
        throw new Error('Modelo de linha em falta.');
    }
    const node = /** @type {HTMLElement & { update?: () => void }} */ (tplRoot.cloneNode(true));
    node.dataset.localId = item.localId;
    const nameEl = node.querySelector('.file-name');
    const metaEl = node.querySelector('.file-meta');
    const btnRm = node.querySelector('.btn-rm');
    const btnDl = node.querySelector('.btn-dl');
    nameEl.textContent = item.name;

    function renderMeta() {
        const o = formatBytes(item.originalSize);
        if (item.status === 'done' && item.compressedSize != null) {
            const c = formatBytes(item.compressedSize);
            const r =
                item.reduction != null
                    ? ` · ${item.reduction >= 0 ? '−' : '+'}${Math.abs(item.reduction).toLocaleString('pt-PT', { maximumFractionDigits: 1 })} %`
                    : '';
            metaEl.textContent = `Original: ${o} · Comprimido: ${c}${r}`;
        } else if (item.status === 'error' && item.error) {
            metaEl.textContent = item.error;
        } else if (item.status === 'ready' && item.serverId) {
            metaEl.textContent = `Original: ${o} · Pronto a comprimir`;
        } else if (item.status === 'compressing') {
            metaEl.textContent = `Original: ${o} · A comprimir…`;
        } else {
            metaEl.textContent = `Original: ${o}`;
        }
    }

    function applyState() {
        node.dataset.state = item.status;
        btnDl.hidden = item.status !== 'done' || !item.serverId;
        renderMeta();
    }

    btnRm.addEventListener('click', () => removeItem(item.localId));
    btnDl.addEventListener('click', () => void downloadSingle(item));

    applyState();
    node.update = () => applyState();
    return node;
}

/** @type {Map<string, HTMLElement & { update?: () => void }>} */
const rowMap = new Map();

function renderList() {
    el.list.innerHTML = '';
    rowMap.clear();
    for (const it of items) {
        const row = rowFor(it);
        rowMap.set(it.localId, row);
        el.list.appendChild(row);
    }
    syncEmpty();
}

function refreshRow(localId) {
    const row = rowMap.get(localId);
    if (row && typeof row.update === 'function') row.update();
}

/**
 * @param {string} localId
 */
async function removeItem(localId) {
    const idx = items.findIndex((i) => i.localId === localId);
    if (idx === -1) return;
    const it = items[idx];
    if (it.serverId) {
        try {
            await deleteServerFiles([it.serverId]);
        } catch {
            /* remove da mesma da UI */
        }
    }
    items.splice(idx, 1);
    renderList();
}

function setProgress(visible, label, pct) {
    el.progWrap.hidden = !visible;
    if (label != null) el.progLabel.textContent = label;
    const p = Math.max(0, Math.min(100, pct));
    el.progFill.style.width = `${p}%`;
    el.progBar.setAttribute('aria-valuenow', String(p));
}

function selectedQuality() {
    const r = document.querySelector('input[name="quality"]:checked');
    const v = r && r.value;
    if (v === 'low' || v === 'medium' || v === 'high') return v;
    return 'medium';
}

/**
 * @param {File[]} fileArr
 */
async function handleIncomingFiles(fileArr) {
    const pdfs = fileArr.filter((f) => f.type === 'application/pdf' || /\.pdf$/i.test(f.name));
    if (pdfs.length === 0) {
        alert('Só são aceites ficheiros PDF.');
        return;
    }

    const valid = [];
    for (const f of pdfs) {
        if (f.size > cfg.maxFileBytes) {
            alert(`O ficheiro «${f.name}» excede o limite de tamanho.`);
            continue;
        }
        /* eslint-disable no-await-in-loop */
        const okMagic = await fileLooksLikePdf(f);
        if (!okMagic) {
            alert(`O ficheiro «${f.name}» não parece ser um PDF válido.`);
            continue;
        }
        valid.push(f);
    }

    if (valid.length === 0) return;

    const room = cfg.maxFiles - items.length;
    if (room <= 0) {
        alert('Atingiu o número máximo de ficheiros na lista.');
        return;
    }
    const batch = valid.slice(0, room);
    if (valid.length > room) {
        alert(`Só pode adicionar mais ${room} ficheiro(s). O restante foi ignorado.`);
    }

    setProgress(true, 'A enviar ficheiros…', 0);

    try {
        const res = await uploadPdfs(batch, (pct) => {
            setProgress(true, 'A enviar ficheiros…', pct);
        });

        let i = 0;
        const successfulUploads = [];
        for (const part of res.files || []) {
            const f = batch[i++];
            if (!f) break;
            if (part.ok && part.id) {
                const item = {
                    localId: randomId(),
                    serverId: part.id,
                    name: part.name || f.name,
                    originalSize: part.size ?? f.size,
                    compressedSize: null,
                    reduction: null,
                    status: 'ready',
                    error: null,
                };
                items.push(item);
                successfulUploads.push(item);
            } else {
                items.push({
                    localId: randomId(),
                    serverId: null,
                    name: f.name,
                    originalSize: f.size,
                    compressedSize: null,
                    reduction: null,
                    status: 'error',
                    error: part.error || 'Erro no envio.',
                });
            }
        }
        renderList();
        
        // Iniciar compressão automática após upload bem-sucedido
        if (successfulUploads.length > 0) {
            setProgress(true, 'Envio concluído. A comprimir…', 100);
            await new Promise(resolve => setTimeout(resolve, 500));
            await autoCompressFiles(successfulUploads);
        } else {
            setProgress(true, 'Envio concluído.', 100);
            setTimeout(() => setProgress(false, '', 0), 700);
        }
    } catch (e) {
        setProgress(false, '', 0);
        alert(e instanceof Error ? e.message : 'Erro desconhecido.');
    } finally {
        syncEmpty();
    }
}

/**
 * @param {FileItem} item
 * @param {{ ok?: boolean, compressed_size?: number, reduction_percent?: number, error?: string }|undefined} result
 */
function applyCompressResult(item, result) {
    if (result && result.ok) {
        item.status = 'done';
        item.compressedSize = result.compressed_size ?? null;
        item.reduction = result.reduction_percent ?? null;
        item.error = null;
    } else {
        item.status = 'error';
        item.compressedSize = null;
        item.reduction = null;
        item.error =
            result && typeof result.error === 'string' && result.error
                ? result.error
                : 'Falha na compressão.';
    }
    refreshRow(item.localId);
}

/**
 * Comprime PDFs: até 4 em paralelo quando há mais de 4 ficheiros;
 * com 4 ou menos, um de cada vez.
 * @param {FileItem[]} targets
 */
async function compressFilesInParallel(targets) {
    const level = selectedQuality();
    const maxParallel = Math.max(1, cfg.maxParallelCompression ?? 4);
    const queue = targets.filter((i) => i.serverId);
    if (queue.length === 0) return;

    let completed = 0;
    const total = queue.length;

    async function worker() {
        while (queue.length > 0) {
            const item = queue.shift();
            if (!item || !item.serverId) continue;

            item.status = 'compressing';
            item.error = null;
            refreshRow(item.localId);

            try {
                const data = await compressPdfs([item.serverId], level);
                const result = (data.results || []).find((r) => r.id === item.serverId) ?? data.results?.[0];
                applyCompressResult(item, result);
            } catch (e) {
                item.status = 'error';
                item.error = e instanceof Error ? e.message : 'Erro desconhecido.';
                refreshRow(item.localId);
            }

            completed += 1;
            const pct = Math.min(99, Math.floor((completed / total) * 100));
            setProgress(true, `A comprimir (${completed}/${total})…`, pct);
        }
    }

    const workers = total > maxParallel ? maxParallel : 1;
    await Promise.all(Array.from({ length: workers }, () => worker()));
}

async function runCompress() {
    const targets = items.filter(
        (i) => i.serverId && (i.status === 'ready' || i.status === 'error' || i.status === 'done')
    );
    if (targets.length === 0) {
        alert('Não há PDFs prontos a comprimir na lista.');
        return;
    }

    el.dlAll.disabled = true;
    setProgress(true, 'A comprimir no servidor…', 8);

    try {
        await compressFilesInParallel(targets);
        setProgress(true, 'Compressão concluída.', 100);
        setTimeout(() => setProgress(false, '', 0), 700);
    } catch (e) {
        setProgress(false, '', 0);
        alert(e instanceof Error ? e.message : 'Erro desconhecido.');
    } finally {
        syncEmpty();
    }
}

/**
 * Comprime ficheiros automaticamente (após upload).
 * @param {FileItem[]} targets
 */
async function autoCompressFiles(targets) {
    if (targets.length === 0) return;

    el.dlAll.disabled = true;
    setProgress(true, 'A comprimir no servidor…', 8);

    try {
        await compressFilesInParallel(targets);
        setProgress(true, 'Compressão concluída.', 100);
        setTimeout(() => setProgress(false, '', 0), 700);
    } catch (e) {
        setProgress(false, '', 0);
        for (const it of targets) {
            if (it.status === 'compressing') {
                it.status = 'error';
                it.error = e instanceof Error ? e.message : 'Erro desconhecido.';
                refreshRow(it.localId);
            }
        }
    } finally {
        syncEmpty();
    }
}

async function runDownloadAll() {
    const ids = items.filter((i) => i.status === 'done' && i.serverId).map((i) => /** @type {string} */ (i.serverId));
    if (ids.length === 0) return;
    el.dlAll.disabled = true;
    setProgress(true, 'A preparar arquivo…', 20);
    try {
        const blob = await downloadZip(ids);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'pdfs_comprimidos.zip';
        a.click();
        URL.revokeObjectURL(url);

        const idSet = new Set(ids);
        for (let j = items.length - 1; j >= 0; j--) {
            const sid = items[j].serverId;
            if (sid && idSet.has(sid)) {
                items.splice(j, 1);
            }
        }
        renderList();
    } catch (e) {
        alert(e instanceof Error ? e.message : 'Erro ao descarregar.');
    } finally {
        setProgress(false, '', 0);
        syncEmpty();
    }
}

/* Drag & drop e ficheiros */
el.browse.addEventListener('click', () => el.input.click());
el.input.addEventListener('change', () => {
    const f = Array.from(el.input.files || []);
    el.input.value = '';
    if (f.length) void handleIncomingFiles(f);
});

el.drop.addEventListener('dragover', (e) => {
    e.preventDefault();
    e.stopPropagation();
    el.drop.classList.add('is-dragover');
});

el.drop.addEventListener('dragenter', (e) => {
    e.preventDefault();
    e.stopPropagation();
    el.drop.classList.add('is-dragover');
});

el.drop.addEventListener('dragleave', (e) => {
    e.preventDefault();
    e.stopPropagation();
    const rel = e.relatedTarget;
    if (!rel || !el.drop.contains(rel)) {
        el.drop.classList.remove('is-dragover');
    }
});

el.drop.addEventListener('drop', (e) => {
    e.preventDefault();
    e.stopPropagation();
    el.drop.classList.remove('is-dragover');
    const dt = e.dataTransfer;
    if (!dt || !dt.files) return;
    void handleIncomingFiles(Array.from(dt.files));
});

el.dlAll.addEventListener('click', () => void runDownloadAll());

syncEmpty();
