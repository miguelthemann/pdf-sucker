<?php
// Desenvolvido pelo Sr. Engenheiro João

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

[$gsBin, $gsOk] = resolveGhostscriptBinary((string) ($config['ghostscript_bin'] ?? 'gs'));
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
    <link rel="stylesheet" href="assets/css/app.css?v=1">
</head>
<body>
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
                <p class="brand-sub">Menos megabytes, mesmo PDF — Made by João e Miguel</p>
            </div>
        </div>
        <div class="header-meta">
            <span class="pill pill-soft">Limite <?php echo htmlspecialchars((string) $maxMb, ENT_QUOTES, 'UTF-8'); ?> MB / ficheiro</span>
            <span class="pill pill-soft">Máx. <?php echo htmlspecialchars((string) $maxFiles, ENT_QUOTES, 'UTF-8'); ?> ficheiros</span>
        </div>
    </header>

    <main class="shell">
        <?php if (!$gsOk): ?>
            <div class="alert alert-warn" role="alert" data-gs-status="missing">
                <strong>Ghostscript não detetado.</strong>
                Em Ubuntu Server instale com <code>sudo apt install ghostscript</code>. Se o PHP não encontrar <code>gs</code> no PATH, defina <code>ghostscript_bin</code> em <code>includes/config.php</code> (ex.: <code>/usr/bin/gs</code>).
            </div>
        <?php else: ?>
            <div class="alert alert-ok visually-hidden" data-gs-status="ok" aria-live="polite">
                Ghostscript disponível no servidor.
            </div>
        <?php endif; ?>

        <section class="panel drop-panel" id="drop-zone" aria-label="Área de envio de ficheiros">
            <input type="file" id="file-input" class="sr-only" accept="application/pdf,.pdf" multiple>
            <div class="drop-inner">
                <div class="drop-icon" aria-hidden="true">
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14 32h20M24 10v16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M18 18l6-6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <p class="drop-title">Largue os PDFs aqui</p>
                <p class="drop-hint">ou <button type="button" class="linkish" id="browse-btn">escolha ficheiros</button> · apenas PDF</p>
            </div>
        </section>

        <section class="panel controls-panel">
            <div class="row row-top">
                <fieldset class="quality-fieldset">
                    <legend class="legend">Nível de compressão</legend>
                    <div class="seg" role="radiogroup" aria-label="Nível de compressão">
                        <label class="seg-item">
                            <input type="radio" name="quality" value="low">
                            <span>Baixa qualidade</span>
                            <small>Melhor imagem · 🖨️</small>
                        </label>
                        <label class="seg-item">
                            <input type="radio" name="quality" value="medium" checked>
                            <span>Média qualidade</span>
                            <small>Equilíbrio · 📖</small>
                        </label>
                        <label class="seg-item">
                            <input type="radio" name="quality" value="high">
                            <span>Alta compressão</span>
                            <small>Ficheiro menor · 🖥️</small>
                        </label>
                    </div>
                </fieldset>
            </div>

            <div class="row row-actions">
                <button type="button" class="btn btn-ghost" id="download-all-btn" disabled>
                    Descarregar todos
                </button>
            </div>

            <div class="progress-wrap" id="progress-wrap" hidden>
                <div class="progress-label" id="progress-label">A preparar…</div>
                <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
            </div>
        </section>

        <section class="panel list-panel" aria-label="Lista de ficheiros">
            <div class="list-head">
                <h2 class="list-title">Ficheiros</h2>
                <span class="list-count" id="file-count">0</span>
            </div>
            <ul class="file-list" id="file-list"></ul>
            <p class="empty-hint" id="empty-hint">Ainda não adicionou PDFs. Utilize a área acima para começar.</p>
        </section>

        <footer class="foot-note">
            <p>Os ficheiros são temporários: são eliminados após descarga ou após <?php echo (int) $ttl; ?> minutos. Os ficheiros e o histórico de uploads não são guardados.</p>
            <div class="foot-links">
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
                <button type="button" class="btn btn-icon btn-dl" title="Descarregar" hidden>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <button type="button" class="btn btn-icon btn-rm" title="Remover da lista">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 7l10 10M17 7L7 17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </button>
            </div>
        </li>
    </template>

    <script>
        window.__APP__ = {
            gsOk: <?php echo $gsOk ? 'true' : 'false'; ?>,
            maxFileBytes: <?php echo (int) $config['max_file_bytes']; ?>,
            maxFiles: <?php echo (int) $maxFiles; ?>,
            maxParallelCompression: <?php echo (int) ($config['max_parallel_compression'] ?? 100); ?>
        };
    </script>
    <script type="module" src="assets/js/app.js?v=1"></script>
</body>
</html>
