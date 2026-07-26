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

// Swap in a brand new token. The old one stops working immediately —
// it's just not in the users table anymore, so find_user_by_token()
// (used by login/extend endpoints) will no longer match it.
$newToken = gen_app_token($pdo);
$upd = $pdo->prepare('UPDATE users SET token = ? WHERE id = ?');
$upd->execute([$newToken, $userId]);

$log = $pdo->prepare('INSERT INTO logs (action, text) VALUES (?, ?)');
$log->execute(['reset-token', "Reset token for {$user['name']} — old token {$user['token']} is now invalid"]);

respond(['ok' => true, 'token' => $newToken]);
