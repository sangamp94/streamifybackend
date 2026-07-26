<?php
require __DIR__ . '/config.php';
// Public endpoint — called by JS on extend_gateway.php (the page the
// shortener redirects back to), passing the signup "sig" it saved to
// localStorage before the user left for the shortener.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$sig = trim($in['sig'] ?? '');
if ($sig === '') fail('Missing signup reference');

$stmt = $pdo->prepare(
    'SELECT * FROM signup_requests WHERE sig = ? AND redeemed = 0 AND expires_at > NOW() LIMIT 1'
);
$stmt->execute([$sig]);
$request = $stmt->fetch();

if (!$request) {
    fail('This link has expired or was already used. Go back and click "Generate a token" again — the link is valid for ' . SIGNUP_LINK_LIFETIME_MIN . ' minutes.', 404);
}

// Re-check one-per-device at finalize time too, in case two tabs raced
// two requests through the shortener at the same time.
$stmt = $pdo->prepare('SELECT 1 FROM device_registrations WHERE device_id = ? OR ip = ?');
$stmt->execute([$request['device_id'], $request['ip']]);
if ($stmt->fetch()) {
    $pdo->prepare('UPDATE signup_requests SET redeemed = 1 WHERE id = ?')->execute([$request['id']]);
    fail('A token has already been issued for this device.', 409);
}

$token = gen_app_token($pdo);
$userId = gen_user_id($pdo);
$expiry = (new DateTime('+' . SIGNUP_TOKEN_DAYS . ' days'))->format('Y-m-d');

$pdo->beginTransaction();
$pdo->prepare('INSERT INTO users (id, name, email, token, status, expiry) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$userId, $request['name'], $request['email'], $token, 'active', $expiry]);
$pdo->prepare('INSERT INTO device_registrations (device_id, ip, user_id) VALUES (?, ?, ?)')
    ->execute([$request['device_id'], $request['ip'], $userId]);
$pdo->prepare('UPDATE signup_requests SET redeemed = 1 WHERE id = ?')->execute([$request['id']]);
$pdo->prepare('INSERT INTO logs (action, text) VALUES (?, ?)')
    ->execute(['signup', "Self-service token generated for {$request['name']} ({$request['email']})"]);
$pdo->commit();

respond([
    'ok' => true,
    'kind' => 'signup',
    'token' => $token,
    'name' => $request['name'],
    'nice_expiry' => (new DateTime($expiry))->format('d M Y'),
]);
