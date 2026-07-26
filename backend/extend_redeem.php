<?php
require __DIR__ . '/config.php';
// Public endpoint — intentionally no require_auth() here, this is the link
// an end-user (not an admin) opens from WhatsApp/email/SMS.

header('Content-Type: text/html; charset=utf-8');

function page($title, $body, $ok = true) {
    $color = $ok ? '#4FA8A0' : '#E0625B';
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>$title</title>
    <style>
      body{font-family:system-ui,sans-serif;background:#10141C;color:#ECEFF4;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px;}
      .box{background:#171D28;border:1px solid #2A3240;border-radius:12px;padding:32px;max-width:380px;}
      h1{font-size:18px;margin:0 0 10px;color:$color;}
      p{font-size:13.5px;color:#9AA5B8;line-height:1.6;}
    </style></head><body><div class='box'><h1>$title</h1><p>$body</p></div></body></html>";
    exit;
}

$sig = $_GET['sig'] ?? '';
if ($sig === '') page('Invalid link', 'This extension link is missing required information.', false);

$stmt = $pdo->prepare('SELECT el.*, u.name, u.expiry FROM extend_links el JOIN users u ON u.id = el.user_id WHERE el.sig = ?');
$stmt->execute([$sig]);
$link = $stmt->fetch();

if (!$link) page('Invalid link', 'This extension link doesn\'t exist or has already been used.', false);
if ($link['redeemed']) page('Already used', 'This extension link has already been redeemed.', false);
if (new DateTime($link['expires_at']) < new DateTime()) page('Link expired', 'This extension link has expired. Please ask for a new one.', false);

$today = new DateTime('today');
$currentExpiry = DateTime::createFromFormat('Y-m-d', $link['expiry']);
$base = ($currentExpiry < $today) ? $today : $currentExpiry;
$base->modify('+' . (int)$link['days'] . ' days');
$newExpiry = $base->format('Y-m-d');

$pdo->beginTransaction();
$pdo->prepare('UPDATE users SET expiry = ? WHERE id = ?')->execute([$newExpiry, $link['user_id']]);
$pdo->prepare('UPDATE extend_links SET redeemed = 1 WHERE id = ?')->execute([$link['id']]);
$pdo->prepare('INSERT INTO logs (action, text) VALUES (?, ?)')
    ->execute(['extend', "Extended {$link['name']} token by {$link['days']} days (new expiry $newExpiry)"]);
$pdo->commit();

$niceDate = $base->format('d M Y');
page('Extension applied ✓', "Your access has been extended by {$link['days']} days. Your token is now valid until <strong>$niceDate</strong>. You can close this page.");
