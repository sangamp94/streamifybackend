<?php
require __DIR__ . '/config.php';
require_auth($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$userId = $in['user_id'] ?? '';
$days = (int)($in['days'] ?? 0);

if ($userId === '') fail('user_id is required');
if ($days < 5) fail('Minimum extension is 5 days');

$stmt = $pdo->prepare('SELECT id, name, token FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) fail('User not found', 404);

$sig = gen_token(24); // 48 hex chars, used as the unguessable link key
$expires = (new DateTime('+3 days'))->format('Y-m-d H:i:s');

$ins = $pdo->prepare('INSERT INTO extend_links (user_id, days, sig, expires_at) VALUES (?, ?, ?, ?)');
$ins->execute([$userId, $days, $sig, $expires]);

$log = $pdo->prepare('INSERT INTO logs (action, text) VALUES (?, ?)');
$log->execute(['generate-link', "Generated extend link for {$user['name']} • +{$days}d"]);

// Build the absolute URL to extend_redeem.php on this same server.
$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower($forwardedProto) === 'https'
    ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$link = "$scheme://$host$dir/extend_redeem.php?sig=$sig";

respond(['link' => $link, 'expires_at' => $expires]);
