<?php
require __DIR__ . '/config.php';
// Public endpoint — this is for the APP USER (not an admin), used by
// frontend/extend.html. No require_auth() here on purpose.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$token = trim($in['token'] ?? '');
if ($token === '') fail('Please enter your token');

$user = find_user_by_token($pdo, $token);

respond([
    'ok' => true,
    'name' => $user['name'],
    'status' => $user['status'],
    'expiry' => $user['expiry'],
    'blocked' => $user['status'] === 'blocked',
]);
