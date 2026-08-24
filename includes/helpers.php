<?php
// Desenvolvido pelo Sr. Engenheiro João

declare(strict_types=1);

/** Tamanho máximo do corpo JSON (compress, delete, zip, etc.) — evita OOM. */
const PDF_SUCKER_JSON_BODY_MAX = 262144;

function appJsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function ghostscriptAvailable(string $bin): bool
{
    if ($bin === '') {
        return false;
    }
    $cmd = escapeshellarg($bin) . ' --version 2>&1';
    $out = @shell_exec($cmd);
    return is_string($out) && trim($out) !== '';
}

/**
 * Resolve o executável do Ghostscript (PATH ou caminhos habituais em Linux).
 *
 * @return array{0: string, 1: bool} [caminho ou preferido, disponível]
 */
function resolveGhostscriptBinary(string $configured): array
{
    $configured = trim($configured);
    if ($configured === '') {
        $configured = 'gs';
    }

    $candidates = [$configured];
    if ($configured === 'gs') {
        $candidates[] = '/usr/bin/gs';
        $candidates[] = '/usr/local/bin/gs';
    }

    foreach (array_unique($candidates) as $bin) {
        if (ghostscriptAvailable($bin)) {
            return [$bin, true];
        }
    }

    return [$configured, false];
}

/**
 * Nome de ficheiro seguro para disco / ZIP / cabeçalhos HTTP, preservando acentos e línguas não latinas.
 * Remove controlos, separadores de caminho e caracteres inválidos no Windows (<>:"/\|?*).
 */
function sanitizeStoredBasename(string $name): string
{
    $base = basename(str_replace("\0", '', $name));
    if ($base === '' || $base === '.' || $base === '..') {
        return 'documento.pdf';
    }

    if (class_exists(\Normalizer::class)) {
        $n = \Normalizer::normalize($base, \Normalizer::FORM_C);
        if (is_string($n) && $n !== '') {
            $base = $n;
        }
    }

    // Controlos Unicode e caracteres de formato (ex.: zero-width)
    $base = preg_replace('/[\p{Cc}\p{Cf}]/u', '', $base) ?? $base;

    $base = str_replace(['<', '>', ':', '"', '/', '\\', '|', '?', '*'], '_', $base);

    $base = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $base) ?? $base;
    $base = trim($base, '.');
    if ($base === '') {
        return 'documento.pdf';
    }

    $lower = function_exists('mb_strtolower')
        ? mb_strtolower($base, 'UTF-8')
        : strtolower($base);
    if (!str_ends_with($lower, '.pdf')) {
        $base .= '.pdf';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($base, 0, 200, 'UTF-8');
    }

    return substr($base, 0, 200);
}

/**
 * Valor para Content-Disposition com suporte a nomes Unicode (RFC 5987 filename*).
 */
function httpContentDispositionAttachment(string $utf8Filename): string
{
    $safe = str_replace(["\r", "\n", '"', '\\'], '', $utf8Filename);
    $fallback = preg_replace('/[^\x20-\x7E]/', '_', $safe);
    if (!is_string($fallback) || trim($fallback) === '') {
        $fallback = 'download.pdf';
    }
    return 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($safe);
}

function isPdfMagic(string $path): bool
{
    $h = @fopen($path, 'rb');
    if ($h === false) {
        return false;
    }
    $sig = fread($h, 5);
    fclose($h);
    return $sig === '%PDF-';
}

function generateFileId(): string
{
    return bin2hex(random_bytes(16));
}

function ensureDir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0750, true);
    }
}

/**
 * Remove ficheiros órfãos mais antigos que TTL em temp e compressed.
 */
function cleanupExpiredFiles(array $config): void
{
    $ttl = (int) ($config['ttl_minutes'] ?? 30);
    if ($ttl < 1) {
        $ttl = 30;
    }
    $maxAge = $ttl * 60;

    foreach (['temp', 'compressed'] as $key) {
        $dir = $config['uploads'][$key] ?? null;
        if (!is_string($dir) || !is_dir($dir)) {
            continue;
        }
        $it = new DirectoryIterator($dir);
        foreach ($it as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }
            if (time() - $file->getMTime() > $maxAge) {
                @unlink($file->getPathname());
            }
        }
    }

    // Limpar entradas de sessão cujo ficheiro já não existe ou caminho é inválido
    if (isset($_SESSION['files']) && is_array($_SESSION['files'])) {
        $temp = (string) ($config['uploads']['temp'] ?? '');
        $compressed = (string) ($config['uploads']['compressed'] ?? '');
        foreach ($_SESSION['files'] as $id => $meta) {
            if (!is_array($meta)) {
                unset($_SESSION['files'][$id]);
                continue;
            }
            $orig = $meta['original_path'] ?? '';
            $comp = $meta['compressed_path'] ?? '';
            $hasOrig = is_string($orig)
                && $orig !== ''
                && $temp !== ''
                && pathIsFileInsideDir($orig, $temp);
            $hasComp = is_string($comp)
                && $comp !== ''
                && $compressed !== ''
                && pathIsFileInsideDir($comp, $compressed);
            if (!$hasOrig && !$hasComp) {
                unset($_SESSION['files'][$id]);
            }
        }
    }
}

function unlinkIfExists(?string $path): void
{
    if (is_string($path) && $path !== '' && is_file($path)) {
        @unlink($path);
    }
}

/**
 * O ficheiro existe, é regular (não symlink) e o realpath está dentro de $directory?
 */
function pathIsFileInsideDir(string $path, string $directory): bool
{
    if (!is_file($path) || is_link($path)) {
        return false;
    }
    $rpFile = realpath($path);
    $rpDir = realpath($directory);
    if ($rpFile === false || $rpDir === false || !is_dir($rpDir)) {
        return false;
    }
    $prefix = $rpDir . DIRECTORY_SEPARATOR;

    return str_starts_with($rpFile, $prefix);
}

/**
 * Ficheiro dentro de temp ou compressed (defesa contra sessão manipulada).
 */
function pathIsFileInsideUploadDirs(?string $path, array $config): bool
{
    if (!is_string($path) || $path === '') {
        return false;
    }
    $temp = $config['uploads']['temp'] ?? '';
    $compressed = $config['uploads']['compressed'] ?? '';
    foreach ([$temp, $compressed] as $root) {
        if (is_string($root) && $root !== '' && pathIsFileInsideDir($path, $root)) {
            return true;
        }
    }

    return false;
}

function unlinkUploadPathIfExists(?string $path, array $config): void
{
    if (pathIsFileInsideUploadDirs($path, $config)) {
        @unlink($path);
    }
}

/**
 * Lê e descodifica JSON do corpo do pedido com limites de tamanho e profundidade.
 *
 * @return array<string, mixed>|null
 */
function readJsonRequestBody(int $maxBytes = PDF_SUCKER_JSON_BODY_MAX, int $maxDepth = 32): ?array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '' || strlen($raw) > $maxBytes) {
        return null;
    }
    try {
        $data = json_decode($raw, true, $maxDepth, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        return null;
    }

    return is_array($data) ? $data : null;
}
