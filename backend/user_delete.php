<?php
require __DIR__ . '/config.php';
require_auth($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$userId = $in['user_id'] ?? '';
if ($userId === '') fail('user_id is required');

$stmt = $pdo->prepare('SELECT id, name, token FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) fail('User not found', 404);

// devices, extend_links and device_registrations all reference users
// with ON DELETE CASCADE (see schema-postgres.sql), so this single
// DELETE removes the user's row, their token, their device history,
// and any pending extend/signup links together.
$del = $pdo->prepare('DELETE FROM users WHERE id = ?');
$del->execute([$userId]);

$log = $pdo->prepare('INSERT INTO logs (action, text) VALUES (?, ?)');
$log->execute(['delete', "Deleted user {$user['name']} ({$user['token']})"]);

respond(['ok' => true]);
