<?php
/**
 * Vercel entry point for the existing PHP endpoints in /backend.
 * The public /backend/*.php URLs are preserved by vercel.json rewrites.
 */

$endpoint = $_GET['endpoint'] ?? '';
if (
    !is_string($endpoint) ||
    $endpoint === '' ||
    basename($endpoint) !== $endpoint ||
    !preg_match('/^[A-Za-z0-9_-]+\.php$/i', $endpoint) ||
    strtolower($endpoint) === 'config.php'
) {
    send_json_error(404, 'Endpoint not found.');
}

$backendRoot = realpath(__DIR__ . '/../../backend');
if ($backendRoot === false) {
    send_json_error(500, 'Backend source directory is unavailable.');
}

$file = realpath($backendRoot . DIRECTORY_SEPARATOR . $endpoint);
if ($file === false || dirname($file) !== $backendRoot || !is_file($file)) {
    send_json_error(404, 'Endpoint not found.');
}

// Existing endpoints initialize headers/database logic from backend/config.php.
require $file;

function send_json_error(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    echo json_encode(['error' => $message]);
    exit;
}
