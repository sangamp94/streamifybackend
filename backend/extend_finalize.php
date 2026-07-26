<?php
require __DIR__ . '/config.php';
// Public endpoint — called by JS on extend_gateway.php (the page the
// shortener redirects back to), passing the token it saved to
// localStorage before the user left for the shortener.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$token = trim($in['token'] ?? '');
if ($token === '') fail('Missing token');

$user = find_user_by_token($pdo, $token);

$stmt = $pdo->prepare(
    'SELECT * FROM extend_links
     WHERE user_id = ? AND redeemed = 0 AND expires_at > NOW()
     ORDER BY created_at DESC LIMIT 1'
);
$stmt->execute([$user['id']]);
$link = $stmt->fetch();

if (!$link) {
    fail('No pending extension request found for this account. Go back and click "Extend" again — the request link expires after ' . SELF_EXTEND_LINK_LIFETIME_MIN . ' minutes.', 404);
}

$today = new DateTime('today');
$currentExpiry = DateTime::createFromFormat('Y-m-d', $user['expiry']);
$base = ($currentExpiry < $today) ? $today : $currentExpiry;
$base->modify('+' . (int)$link['days'] . ' days');
$newExpiry = $base->format('Y-m-d');

$pdo->beginTransaction();
$pdo->prepare('UPDATE users SET expiry = ? WHERE id = ?')->execute([$newExpiry, $user['id']]);
$pdo->prepare('UPDATE extend_links SET redeemed = 1 WHERE id = ?')->execute([$link['id']]);
$pdo->prepare('INSERT INTO logs (action, text) VALUES (?, ?)')
    ->execute(['extend', "Self-service extended {$user['name']} by {$link['days']} days (new expiry $newExpiry)"]);
$pdo->commit();

respond(['ok' => true, 'new_expiry' => $newExpiry, 'nice_expiry' => $base->format('d M Y')]);
