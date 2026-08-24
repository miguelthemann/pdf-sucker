<?php
// Desenvolvido pelo Sr. Engenheiro João

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

[$qpdfBin, $qpdfOk] = resolveQpdfBinary((string) ($config['qpdf_bin'] ?? 'qpdf'));
$maxMb = (int) round((int) $config['max_file_bytes'] / (1024 * 1024));
$ttl = (int) ($config['ttl_minutes'] ?? 30);
$maxFiles = (int) ($config['max_files_per_upload'] ?? 20);

?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <title>PDF Sucker</title>
    <link rel="icon" href="favicon.png" type="image/png">
    <link rel="stylesheet" href="assets/css/app.css?v=2">
</head>
<body>
    <div class="lang-switch" aria-label="Idioma" data-i18n-aria="langSwitchLabel">
        <button type="button" class="lang-opt is-active" data-lang="pt" aria-pressed="true">PT</button>
        <span class="lang-sep" aria-hidden="true">|</span>
        <button type="button" class="lang-opt" data-lang="en" aria-pressed="false">EN</button>
    </div>

    <div class="bg-grid" aria-hidden="true"></div>
    <div class="glow glow-a" aria-hidden="true"></div>
    <div class="glow glow-b" aria-hidden="true"></div>

    <header class="site-header">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">
                <img src="logo.png" alt="" width="320" height="120" decoding="async" class="brand-logo">
            </span>
            <div>
                <h1 class="brand-title">PDF Sucker</h1>
                <p class="brand-sub" data-i18n="brandSub">Menos megabytes, mesmo PDF — Made by João e Miguel</p>
            </div>
        </div>
        <div class="header-meta">
            <span class="pill pill-soft" id="pill-limit" data-i18n="limitPerFile">Limite <?php echo htmlspecialchars((string) $maxMb, ENT_QUOTES, 'UTF-8'); ?> MB / ficheiro</span>
            <span class="pill pill-soft" id="pill-max-files" data-i18n="maxFiles">Máx. <?php echo htmlspecialchars((string) $maxFiles, ENT_QUOTES, 'UTF-8'); ?> ficheiros</span>
        </div>
    </header>

    <main class="shell">
        <?php if (!$qpdfOk): ?>
            <div class="alert alert-warn" role="alert" data-qpdf-status="missing">
                <strong data-i18n="qpdfMissingTitle">qpdf não detetado.</strong>
                <span data-i18n-html="qpdfMissingBody">Em Ubuntu Server instale com <code>sudo apt install qpdf</code>. Se o PHP não encontrar <code>qpdf</code> no PATH, defina <code>qpdf_bin</code> em <code>includes/config.php</code> (ex.: <code>/usr/bin/qpdf</code>).</span>
            </div>
        <?php else: ?>
            <div class="alert alert-ok visually-hidden" data-qpdf-status="ok" aria-live="polite" data-i18n="qpdfOk">
                qpdf disponível no servidor.
            </div>
        <?php endif; ?>

        <section class="panel drop-panel" id="drop-zone" data-i18n-aria="dropZoneLabel" aria-label="Área de envio de ficheiros">
            <input type="file" id="file-input" class="sr-only" accept="application/pdf,.pdf" multiple>
            <div class="drop-inner">
                <div class="drop-icon" aria-hidden="true">
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14 32h20M24 10v16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M18 18l6-6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p class="drop-title" data-i18n="dropTitle">Largue os PDFs aqui</p>
                <p class="drop-hint">
                    <span data-i18n="dropHintPrefix">ou</span>
                    <button type="button" class="linkish" id="browse-btn" data-i18n="dropHintBrowse">escolha ficheiros</button>
                    <span data-i18n="dropHintSuffix">· apenas PDF</span>
                </p>
            </div>
        </section>

        <section class="panel controls-panel">
            <div class="row row-top">
                <fieldset class="quality-fieldset">
                    <legend class="legend" data-i18n="compressionLegend">Nível de compressão</legend>
                    <div class="seg" role="radiogroup" data-i18n-aria="compressionGroupLabel" aria-label="Nível de compressão">
                        <label class="seg-item">
                            <input type="radio" name="quality" value="low">
                            <span data-i18n="qualityLow">Baixa qualidade</span>
                            <small data-i18n="qualityLowHint">Melhor imagem · 🖨️</small>
                        </label>
                        <label class="seg-item">
                            <input type="radio" name="quality" value="medium" checked>
                            <span data-i18n="qualityMedium">Média qualidade</span>
                            <small data-i18n="qualityMediumHint">Equilíbrio · 📖</small>
                        </label>
                        <label class="seg-item">
                            <input type="radio" name="quality" value="high">
                            <span data-i18n="qualityHigh">Alta compressão</span>
                            <small data-i18n="qualityHighHint">Ficheiro menor · 🖥️</small>
                        </label>
                    </div>
                </fieldset>
            </div>

            <div class="row row-actions">
                <button type="button" class="btn btn-ghost" id="download-all-btn" disabled data-i18n="downloadAll">
                    Descarregar todos
                </button>
            </div>

            <div class="progress-wrap" id="progress-wrap" hidden>
                <div class="progress-label" id="progress-label" data-i18n="preparing">A preparar…</div>
                <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
            </div>
        </section>

        <section class="panel list-panel" data-i18n-aria="filesListLabel" aria-label="Lista de ficheiros">
            <div class="list-head">
                <h2 class="list-title" data-i18n="filesTitle">Ficheiros</h2>
                <span class="list-count" id="file-count">0</span>
            </div>
            <ul class="file-list" id="file-list"></ul>
            <p class="empty-hint" id="empty-hint" data-i18n="emptyHint">Ainda não adicionou PDFs. Utilize a área acima para começar.</p>
        </section>

        <footer class="foot-note">
            <p id="foot-note-text" data-i18n="footNote">Os ficheiros são temporários: são eliminados após descarga ou após <?php echo (int) $ttl; ?> minutos. Os ficheiros e o histórico de uploads não são guardados.</p>
            <div class="foot-links">
                <a href="mailto:support@entr0py.cc" class="btn btn-ghost foot-btn" data-i18n="contact">Contacto</a>
                <span class="foot-sep" aria-hidden="true"></span>
                <a href="https://status.entr0py.cc" class="btn btn-ghost foot-btn" target="_blank" rel="noopener noreferrer">Status</a>
                <span class="foot-sep" aria-hidden="true"></span>
                <a href="https://github.com/miguelthemann/pdf-sucker" class="btn btn-ghost foot-btn" target="_blank" rel="noopener noreferrer">GitHub</a>
            </div>
        </footer>
    </main>

    <template id="row-template">
        <li class="file-row" data-state="pending">
            <div class="file-main">
                <span class="status-dot" aria-hidden="true"></span>
                <div class="file-text">
                    <span class="file-name"></span>
                    <span class="file-meta"></span>
                </div>
            </div>
            <div class="file-actions">
                <button type="button" class="btn btn-icon btn-dl" data-i18n-title="download" title="Descarregar" hidden>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <button type="button" class="btn btn-icon btn-rm" data-i18n-title="removeFromList" title="Remover da lista">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 7l10 10M17 7L7 17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </button>
            </div>
        </li>
    </template>

    <script>
        window.__APP__ = {
            qpdfOk: <?php echo $qpdfOk ? 'true' : 'false'; ?>,
            maxFileBytes: <?php echo (int) $config['max_file_bytes']; ?>,
            maxFiles: <?php echo (int) $maxFiles; ?>,
            maxMb: <?php echo (int) $maxMb; ?>,
            ttl: <?php echo (int) $ttl; ?>,
            maxParallelCompression: <?php echo (int) ($config['max_parallel_compression'] ?? 4); ?>
        };
    </script>
    <script type="module" src="assets/js/app.js?v=2"></script>
</body>
</html>
