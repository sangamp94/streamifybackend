<?php
require __DIR__ . '/config.php';
// Public endpoint — hit from the "New user? Generate a token" form on
// frontend/extend.html, right before being sent through the shortener
// link. No require_auth() on purpose: they have no token yet.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$deviceId = trim($in['device_id'] ?? '');
$name = trim($in['name'] ?? '');
$email = trim($in['email'] ?? '');

if ($deviceId === '') fail('Missing device id — please reload the page and try again.');
if ($name === '') fail('Please enter your name');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email');

$ip = client_ip();

// One token per device: block if this browser OR this IP already has a
// token on record (see config.php for why both are checked).
$stmt = $pdo->prepare('SELECT 1 FROM device_registrations WHERE device_id = ? OR ip = ?');
$stmt->execute([$deviceId, $ip]);
if ($stmt->fetch()) {
    fail('A token has already been issued for this device. If you lost it, contact support instead of generating a new one.', 409);
}

// Reuse an existing pending (unredeemed, not yet expired) request for
// this device instead of piling up duplicate rows on repeat clicks.
$stmt = $pdo->prepare(
    'SELECT sig FROM signup_requests
     WHERE device_id = ? AND redeemed = 0 AND expires_at > NOW()
     ORDER BY created_at DESC LIMIT 1'
);
$stmt->execute([$deviceId]);
$existing = $stmt->fetch();

if ($existing) {
    $sig = $existing['sig'];
} else {
    $sig = gen_token(24);
    // MySQL's own NOW(), same reason as user_request_extend.php.
    // Postgres: same NOW() + interval*placeholder pattern as extend.
    $ins = $pdo->prepare(
        "INSERT INTO signup_requests (device_id, ip, name, email, sig, expires_at)
         VALUES (?, ?, ?, ?, ?, NOW() + (INTERVAL '1 minute' * ?))"
    );
    $ins->execute([$deviceId, $ip, $name, $email, $sig, SIGNUP_LINK_LIFETIME_MIN]);
}

respond(['ok' => true, 'redirect' => SHORTENER_URL, 'sig' => $sig]);
