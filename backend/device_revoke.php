<?php
require __DIR__ . '/config.php';
require_auth($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$deviceId = $in['device_id'] ?? '';
if ($deviceId === '') fail('device_id is required');

$stmt = $pdo->prepare('SELECT d.id, d.ip, u.id AS user_id, u.name FROM devices d JOIN users u ON u.id = d.user_id WHERE d.id = ?');
$stmt->execute([$deviceId]);
$device = $stmt->fetch();
if (!$device) fail('Device not found', 404);

$del = $pdo->prepare('DELETE FROM devices WHERE id = ?');
$del->execute([$deviceId]);

$log = $pdo->prepare('INSERT INTO logs (action, text) VALUES (?, ?)');
$log->execute(['revoke', "Revoked device {$device['id']} ({$device['ip']}) — {$device['name']}"]);

respond(['ok' => true]);
