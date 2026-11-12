<?php

declare(strict_types=1);

// The flat source tree is not a public directory. Serve only known entry points.
// Keep this list in sync with .htaccess when adding a public route or asset.
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cross-Origin-Opener-Policy: same-origin');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '';
$pages = [
    '/', '/index.php', '/login.php', '/register.php', '/logout.php',
    '/profile.php', '/notes.php', '/search.php', '/challenges.php',
    '/challenge.php', '/learned.php', '/api/users.php',
];
// Full Content-Type per asset: text gets a charset, binary fonts must not.
$assets = [
    '/assets/css/style.css' => 'text/css; charset=UTF-8',
    '/assets/js/app.js' => 'text/javascript; charset=UTF-8',
    '/assets/fonts/plex-sans-400.woff2' => 'font/woff2',
    '/assets/fonts/plex-sans-600.woff2' => 'font/woff2',
    '/assets/fonts/plex-sans-700.woff2' => 'font/woff2',
    '/assets/fonts/jetbrains-mono-400.woff2' => 'font/woff2',
    '/assets/fonts/jetbrains-mono-700.woff2' => 'font/woff2',
];
if (in_array($path, $pages, true)) {
    require __DIR__ . ($path === '/' ? '/index.php' : $path);
} elseif (isset($assets[$path])) {
    header('Content-Type: ' . $assets[$path]);
    readfile(__DIR__ . $path);
} else {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo 'Not found.';
}
return true;
