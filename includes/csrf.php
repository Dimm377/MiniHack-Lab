<?php

declare(strict_types=1);

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_or_abort(): void
{
    $submitted = $_POST['csrf_token'] ?? null;
    $expected = $_SESSION['csrf_token'] ?? null;
    if (!is_string($submitted) || !is_string($expected) || !hash_equals($expected, $submitted)) {
        error_log(sprintf('CSRF verification failed for %s', $_SERVER['REQUEST_URI'] ?? '/'));
        http_response_code(403);
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Forbidden</title>';
        echo '<body><h1>Forbidden</h1><p>The request could not be verified.</p></body></html>';
        exit;
    }
}
