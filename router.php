<?php

declare(strict_types=1);

// The flat source tree is not a public directory. Serve only known entry points.
// Keep this list in sync with .htaccess when adding a public route or asset.
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
    '/challenge.php', '/api/users.php',
];
$assets = ['/assets/css/style.css' => 'text/css', '/assets/js/app.js' => 'text/javascript'];
if (in_array($path, $pages, true)) {
    require __DIR__ . ($path === '/' ? '/index.php' : $path);
} elseif (isset($assets[$path])) {
    header('Content-Type: ' . $assets[$path] . '; charset=UTF-8');
    readfile(__DIR__ . $path);
} else {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo 'Not found.';
}
return true;
