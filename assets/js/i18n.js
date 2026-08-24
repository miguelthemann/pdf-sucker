// Desenvolvido pelo Sr. Engenheiro João

const STORAGE_KEY = 'pdf-sucker-lang';

/** @typedef {'pt' | 'en'} Lang */

/** @type {Record<Lang, Record<string, string | ((...args: unknown[]) => string)>>} */
const STRINGS = {
    pt: {
        brandSub: 'Menos megabytes, mesmo PDF — Made by João e Miguel',
        limitPerFile: 'Limite {mb} MB / ficheiro',
        maxFiles: 'Máx. {n} ficheiros',
        gsMissingTitle: 'Ghostscript não detetado.',
        gsMissingBody:
            'Em Ubuntu Server instale com <code>sudo apt install ghostscript</code>. Se o PHP não encontrar <code>gs</code> no PATH, defina <code>ghostscript_bin</code> em <code>includes/config.php</code> (ex.: <code>/usr/bin/gs</code>).',
        gsOk: 'Ghostscript disponível no servidor.',
        dropZoneLabel: 'Área de envio de ficheiros',
        dropTitle: 'Largue os PDFs aqui',
        dropHintPrefix: 'ou ',
        dropHintBrowse: 'escolha ficheiros',
        dropHintSuffix: '· apenas PDF',
        compressionLegend: 'Nível de compressão',
        compressionGroupLabel: 'Nível de compressão',
        qualityLow: 'Baixa qualidade',
        qualityLowHint: 'Melhor imagem · 🖨️',
        qualityMedium: 'Média qualidade',
        qualityMediumHint: 'Equilíbrio · 📖',
        qualityHigh: 'Alta compressão',
        qualityHighHint: 'Ficheiro menor · 🖥️',
        downloadAll: 'Descarregar todos',
        preparing: 'A preparar…',
        filesTitle: 'Ficheiros',
        filesListLabel: 'Lista de ficheiros',
        emptyHint: 'Ainda não adicionou PDFs. Utilize a área acima para começar.',
        footNote:
            'Os ficheiros são temporários: são eliminados após descarga ou após {ttl} minutos. Os ficheiros e o histórico de uploads não são guardados.',
        contact: 'Contacto',
        download: 'Descarregar',
        removeFromList: 'Remover da lista',
        langSwitchLabel: 'Idioma',
        onlyPdf: 'Só são aceites ficheiros PDF.',
        fileTooLarge: 'O ficheiro «{name}» excede o limite de tamanho.',
        invalidPdf: 'O ficheiro «{name}» não parece ser um PDF válido.',
        maxFilesReached: 'Atingiu o número máximo de ficheiros na lista.',
        roomLeft: 'Só pode adicionar mais {n} ficheiro(s). O restante foi ignorado.',
        uploading: 'A enviar ficheiros…',
        uploadDone: 'Envio concluído.',
        unknownError: 'Erro desconhecido.',
        uploadError: 'Erro no envio.',
        compressFailed: 'Falha na compressão.',
        compressionProgress: '{completed} ficheiros de {total} comprimidos',
        noPdfsToCompress: 'Não há PDFs prontos a comprimir na lista.',
        preparingArchive: 'A preparar arquivo…',
        downloadFailed: 'Não foi possível descarregar.',
        downloadError: 'Erro ao descarregar.',
        metaOriginal: 'Original',
        metaCompressed: 'Comprimido',
        metaReady: 'Pronto a comprimir',
        metaCompressing: 'A comprimir…',
        suffixCompressed: '_comprimido.pdf',
        zipName: 'pdfs_comprimidos.zip',
    },
    en: {
        brandSub: 'Fewer megabytes, same PDF — Made by João e Miguel',
        limitPerFile: 'Limit {mb} MB / file',
        maxFiles: 'Max {n} files',
        gsMissingTitle: 'Ghostscript not detected.',
        gsMissingBody:
            'On Ubuntu Server install with <code>sudo apt install ghostscript</code>. If PHP cannot find <code>gs</code> in PATH, set <code>ghostscript_bin</code> in <code>includes/config.php</code> (e.g. <code>/usr/bin/gs</code>).',
        gsOk: 'Ghostscript is available on the server.',
        dropZoneLabel: 'File upload area',
        dropTitle: 'Drop PDFs here',
        dropHintPrefix: 'or ',
        dropHintBrowse: 'choose files',
        dropHintSuffix: '· PDF only',
        compressionLegend: 'Compression level',
        compressionGroupLabel: 'Compression level',
        qualityLow: 'Low quality',
        qualityLowHint: 'Best image · 🖨️',
        qualityMedium: 'Medium quality',
        qualityMediumHint: 'Balanced · 📖',
        qualityHigh: 'High compression',
        qualityHighHint: 'Smaller file · 🖥️',
        downloadAll: 'Download all',
        preparing: 'Preparing…',
        filesTitle: 'Files',
        filesListLabel: 'File list',
        emptyHint: 'No PDFs added yet. Use the area above to get started.',
        footNote:
            'Files are temporary: they are deleted after download or after {ttl} minutes. Files and upload history are not stored.',
        contact: 'Contact',
        download: 'Download',
        removeFromList: 'Remove from list',
        langSwitchLabel: 'Language',
        onlyPdf: 'Only PDF files are accepted.',
        fileTooLarge: 'The file «{name}» exceeds the size limit.',
        invalidPdf: 'The file «{name}» does not appear to be a valid PDF.',
        maxFilesReached: 'You have reached the maximum number of files in the list.',
        roomLeft: 'You can only add {n} more file(s). The rest was ignored.',
        uploading: 'Uploading files…',
        uploadDone: 'Upload complete.',
        unknownError: 'Unknown error.',
        uploadError: 'Upload error.',
        compressFailed: 'Compression failed.',
        compressionProgress: '{completed} of {total} files compressed',
        noPdfsToCompress: 'There are no PDFs ready to compress in the list.',
        preparingArchive: 'Preparing archive…',
        downloadFailed: 'Could not download.',
        downloadError: 'Download error.',
        metaOriginal: 'Original',
        metaCompressed: 'Compressed',
        metaReady: 'Ready to compress',
        metaCompressing: 'Compressing…',
        suffixCompressed: '_compressed.pdf',
        zipName: 'compressed_pdfs.zip',
    },
};

/** @type {Lang} */
let currentLang = 'pt';

/** @type {Set<() => void>} */
const listeners = new Set();

/** @type {Record<string, string | number>} */
let appVars = {};

/**
 * @param {Record<string, string | number>} vars
 */
function mergedVars(vars = {}) {
    return { ...appVars, ...vars };
}
function interpolate(template, vars = {}) {
    return template.replace(/\{(\w+)\}/g, (_, key) => String(vars[key] ?? ''));
}

/**
 * @returns {Lang}
 */
export function getLang() {
    return currentLang;
}

/**
 * @returns {string}
 */
export function localeTag() {
    return currentLang === 'pt' ? 'pt-PT' : 'en';
}

/**
 * @param {string} key
 * @param {Record<string, string | number>} [vars]
 */
export function t(key, vars = {}) {
    const raw = STRINGS[currentLang][key] ?? STRINGS.pt[key] ?? key;
    return typeof raw === 'string' ? interpolate(raw, vars) : key;
}

/**
 * Apply a translation that may contain <code>…</code> only.
 * Other markup is shown as text, never interpreted as HTML.
 * @param {Element} node
 * @param {string} text
 */
function setCodeMarkup(node, text) {
    const frag = document.createDocumentFragment();
    const re = /<code>([\s\S]*?)<\/code>/gi;
    let last = 0;
    let match;
    while ((match = re.exec(text)) !== null) {
        if (match.index > last) {
            frag.appendChild(document.createTextNode(text.slice(last, match.index)));
        }
        const code = document.createElement('code');
        code.textContent = match[1];
        frag.appendChild(code);
        last = re.lastIndex;
    }
    if (last < text.length) {
        frag.appendChild(document.createTextNode(text.slice(last)));
    }
    node.replaceChildren(frag);
}

/**
 * @param {Lang} lang
 */
export function setLang(lang) {
    if (lang !== 'pt' && lang !== 'en') return;
    currentLang = lang;
    try {
        localStorage.setItem(STORAGE_KEY, lang);
    } catch {
        /* ignore */
    }
    document.documentElement.lang = localeTag();
    applyStaticTranslations(mergedVars());
    updateLangSwitch();
    for (const fn of listeners) fn();
}

/**
 * @param {() => void} fn
 */
export function onLangChange(fn) {
    listeners.add(fn);
    return () => listeners.delete(fn);
}

/**
 * @param {Record<string, string | number>} [vars]
 */
export function applyStaticTranslations(vars = {}) {
    const all = mergedVars(vars);
    document.querySelectorAll('[data-i18n]').forEach((node) => {
        const key = node.getAttribute('data-i18n');
        if (!key) return;
        node.textContent = t(key, all);
    });

    document.querySelectorAll('[data-i18n-html]').forEach((node) => {
        const key = node.getAttribute('data-i18n-html');
        if (!key) return;
        setCodeMarkup(node, t(key, all));
    });

    document.querySelectorAll('[data-i18n-aria]').forEach((node) => {
        const key = node.getAttribute('data-i18n-aria');
        if (!key) return;
        node.setAttribute('aria-label', t(key, all));
    });

    document.querySelectorAll('[data-i18n-title]').forEach((node) => {
        const key = node.getAttribute('data-i18n-title');
        if (!key) return;
        node.setAttribute('title', t(key, all));
    });
}

function updateLangSwitch() {
    document.querySelectorAll('.lang-opt').forEach((btn) => {
        const lang = btn.getAttribute('data-lang');
        btn.classList.toggle('is-active', lang === currentLang);
        btn.setAttribute('aria-pressed', lang === currentLang ? 'true' : 'false');
    });
}

/**
 * @param {Record<string, string | number>} appVars
 */
export function initI18n(vars = {}) {
    appVars = {
        mb: vars.maxMb ?? vars.mb ?? '',
        n: vars.maxFiles ?? vars.n ?? '',
        ttl: vars.ttl ?? '',
    };

    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'en' || stored === 'pt') {
            currentLang = stored;
        }
    } catch {
        /* ignore */
    }

    document.documentElement.lang = localeTag();
    applyStaticTranslations();
    updateLangSwitch();

    document.querySelectorAll('.lang-opt').forEach((btn) => {
        btn.addEventListener('click', () => {
            const lang = btn.getAttribute('data-lang');
            if (lang === 'pt' || lang === 'en') {
                setLang(lang);
            }
        });
    });
}
