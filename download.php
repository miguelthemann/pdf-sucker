<?php
// Desenvolvido pelo Sr. Engenheiro João

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

if ($id !== '' && !preg_match('/^[a-f0-9]{32}$/', $id)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Pedido inválido.';
    exit;
}

if ($action === 'zip' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handleZipDownload($config);
}

if ($id === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Identificador em falta.';
    exit;
}

if (!isset($_SESSION['files'][$id]) || !is_array($_SESSION['files'][$id])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ficheiro não encontrado ou já foi removido.';
    exit;
}

$meta = $_SESSION['files'][$id];
$type = isset($_GET['type']) ? (string) $_GET['type'] : 'compressed';
if ($type !== 'compressed' && $type !== 'original') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Tipo de ficheiro inválido.';
    exit;
}

$tempRoot = (string) ($config['uploads']['temp'] ?? '');
$compressedRoot = (string) ($config['uploads']['compressed'] ?? '');

if ($type === 'compressed') {
    $path = $meta['compressed_path'] ?? null;
    $downloadName = preg_replace('/\.pdf$/iu', '', (string) ($meta['original_name'] ?? 'documento')) . '_comprimido.pdf';
} else {
    $path = $meta['original_path'] ?? null;
    $downloadName = (string) ($meta['original_name'] ?? 'documento.pdf');
}

$rootForPath = $type === 'compressed' ? $compressedRoot : $tempRoot;
if (
    !is_string($path)
    || $path === ''
    || $rootForPath === ''
    || str_contains($path, '://')
) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ficheiro não disponível.';
    exit;
}

$safePath = realpath($path);
if ($safePath === false || !pathIsFileInsideDir($safePath, $rootForPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ficheiro não disponível.';
    exit;
}

$downloadName = sanitizeStoredBasename($downloadName);

header('Content-Type: application/pdf');
header('Content-Disposition: ' . httpContentDispositionAttachment($downloadName));
header('Content-Length: ' . (string) filesize($safePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

readfile($safePath);

// Após descarga: remover temporários deste item
unlinkUploadPathIfExists($meta['original_path'] ?? null, $config);
unlinkUploadPathIfExists($meta['compressed_path'] ?? null, $config);
unset($_SESSION['files'][$id]);
exit;

/**
 * Nome dentro do ZIP = nome original sanitizado; em colisões usa "nome (2).pdf", etc.
 *
 * @param array<string, true> $usedKeys chaves em minúsculas já reservadas no ZIP
 */
function zipUniqueEntryName(string $basename, array &$usedKeys): string
{
    $lower = static function (string $s): string {
        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    };

    $key = $lower($basename);
    if (!isset($usedKeys[$key])) {
        $usedKeys[$key] = true;
        return $basename;
    }
    if (!preg_match('/^(.+)\.pdf$/iu', $basename, $m)) {
        $usedKeys[$lower($basename)] = true;
        return $basename;
    }
    $stem = $m[1];
    for ($n = 2; ; $n++) {
        $candidate = $stem . ' (' . $n . ').pdf';
        if (function_exists('mb_substr')) {
            $candidate = mb_substr($candidate, 0, 200, 'UTF-8');
        } else {
            $candidate = substr($candidate, 0, 200);
        }
        if (!str_ends_with($lower($candidate), '.pdf')) {
            $candidate = function_exists('mb_substr')
                ? mb_substr($candidate, 0, 196, 'UTF-8') . '.pdf'
                : substr($candidate, 0, 196) . '.pdf';
        }
        $k = $lower($candidate);
        if (!isset($usedKeys[$k])) {
            $usedKeys[$k] = true;
            return $candidate;
        }
    }
}

/**
 * @param array<string, mixed> $config
 */
function handleZipDownload(array $config): void
{
    $data = readJsonRequestBody();
    if ($data === null || !isset($data['ids']) || !is_array($data['ids'])) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Pedido inválido.';
        exit;
    }

    $maxBatchIds = (int) ($config['max_files_per_upload'] ?? 20);
    if (count($data['ids']) > $maxBatchIds) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Demasiados ficheiros no pedido.';
        exit;
    }

    $compressedRoot = (string) ($config['uploads']['compressed'] ?? '');
    if ($compressedRoot === '') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Configuração inválida.';
        exit;
    }

    if (!class_exists(ZipArchive::class)) {
        http_response_code(501);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'A extensão ZIP do PHP não está disponível no servidor.';
        exit;
    }

    $pathsToDelete = [];
    $zip = new ZipArchive();
    $zipPath = $config['uploads']['temp'] . DIRECTORY_SEPARATOR . 'bundle_' . bin2hex(random_bytes(8)) . '.zip';
    ensureDir($config['uploads']['temp']);

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo 'Não foi possível criar o arquivo.';
        exit;
    }

    $usedZipNames = [];
    foreach ($data['ids'] as $rid) {
        if (!is_string($rid) || !preg_match('/^[a-f0-9]{32}$/', $rid)) {
            continue;
        }
        if (!isset($_SESSION['files'][$rid]) || !is_array($_SESSION['files'][$rid])) {
            continue;
        }
        $m = $_SESSION['files'][$rid];
        $cp = $m['compressed_path'] ?? null;
        if (!is_string($cp) || !pathIsFileInsideDir($cp, $compressedRoot)) {
            continue;
        }
        $base = sanitizeStoredBasename((string) ($m['original_name'] ?? 'documento.pdf'));
        $entryName = zipUniqueEntryName($base, $usedZipNames);
        $zip->addFile($cp, $entryName);
        $pathsToDelete[$rid] = $m;
    }

    if ($zip->numFiles === 0) {
        $zip->close();
        @unlink($zipPath);
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Nenhum PDF comprimido disponível para descarregar.';
        exit;
    }

    $zip->close();

    if (!is_file($zipPath) || is_link($zipPath) || !pathIsFileInsideDir($zipPath, $config['uploads']['temp'])) {
        @unlink($zipPath);
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Não foi possível preparar o arquivo.';
        exit;
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="pdfs_comprimidos.zip"');
    header('Content-Length: ' . (string) filesize($zipPath));
    header('Cache-Control: no-store');
    readfile($zipPath);
    @unlink($zipPath);

    foreach ($pathsToDelete as $rid => $m) {
        unlinkUploadPathIfExists(is_array($m) ? ($m['original_path'] ?? null) : null, $config);
        unlinkUploadPathIfExists(is_array($m) ? ($m['compressed_path'] ?? null) : null, $config);
        unset($_SESSION['files'][$rid]);
    }
    exit;
}
