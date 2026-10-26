<?php
// Desenvolvido pelo Sr. Engenheiro João

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['session_name']);
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $isHttps,
    ]);
}

if (!isset($_SESSION['files']) || !is_array($_SESSION['files'])) {
    $_SESSION['files'] = [];
}

require_once __DIR__ . '/helpers.php';

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff', false);
    header('Referrer-Policy: strict-origin-when-cross-origin', false);
    header('X-Frame-Options: SAMEORIGIN', false);
}

cleanupExpiredFiles($config);
