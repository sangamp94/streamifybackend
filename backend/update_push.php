<?php
require __DIR__ . '/config.php';
require_auth($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$version = trim($in['version'] ?? '');
$notes = trim($in['notes'] ?? '');
$force = !empty($in['force']) ? 1 : 0;
$target = $in['target'] ?? 'all';

if ($version === '') fail('Version is required');
if (!in_array($target, ['all', 'active', 'flagged'], true)) $target = 'all';

$ins = $pdo->prepare('INSERT INTO update_history (version, notes, force_update, target) VALUES (?, ?, ?, ?)');
$ins->execute([$version, $notes, $force, $target]);

$targetLabel = $target === 'all' ? 'all users' : $target;
$log = $pdo->prepare('INSERT INTO logs (action, text) VALUES (?, ?)');
$log->execute(['push-update', 'Pushed v' . $version . ' (' . ($force ? 'forced' : 'optional') . ') to ' . $targetLabel]);

respond(['ok' => true]);
