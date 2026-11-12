<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

ini_set('display_errors', '0');
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

function json_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(405, ['error' => 'Method not allowed.']);
}
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!isset($_GET['id']) || $id === false || $id === null) {
    json_response(400, ['error' => 'A valid positive user ID is required.']);
}

try {
    $statement = db()->prepare('SELECT id, username FROM users WHERE id = :id');
    $statement->execute(['id' => $id]);
    $user = $statement->fetch();
    if (!is_array($user)) {
        json_response(404, ['error' => 'User not found.']);
    }
    json_response(200, ['id' => (int) $user['id'], 'username' => (string) $user['username']]);
} catch (Throwable $exception) {
    error_log('User API failure: ' . $exception->getMessage());
    json_response(500, ['error' => 'Unexpected server failure.']);
}
