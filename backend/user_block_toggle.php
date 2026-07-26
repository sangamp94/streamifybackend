<?php
require __DIR__ . '/config.php';
require_auth($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$userId = $in['user_id'] ?? '';
if ($userId === '') fail('user_id is required');

$stmt = $pdo->prepare('SELECT id, name, token, status FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) fail('User not found', 404);

$newStatus = $user['status'] === 'blocked' ? 'active' : 'blocked';
$upd = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
$upd->execute([$newStatus, $userId]);

$action = $newStatus === 'blocked' ? 'block' : 'unblock';
$verb = $newStatus === 'blocked' ? 'Blocked' : 'Unblocked';
$log = $pdo->prepare('INSERT INTO logs (action, text) VALUES (?, ?)');
$log->execute([$action, "$verb {$user['name']} ({$user['token']})"]);

respond(['status' => $newStatus]);
