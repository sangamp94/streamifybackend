<?php
require __DIR__ . '/config.php';
// Public endpoint — this is for the APP USER (not an admin), used by
// frontend/extend.html. No require_auth() here on purpose.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$token = trim($in['token'] ?? '');
if ($token === '') fail('Please enter your token');

$user = find_user_by_token($pdo, $token);

// ---------------------------------------------------------------
// Record this as a device/session so it shows up in the admin
// console (Users -> Devices column, device modal, IP, "flagged"
// count). Before this, nothing in the whole backend ever wrote to
// the `devices` table outside the one-time seed data in
// schema-postgres.sql — so no matter how many real people logged in
// through the app, the admin dashboard kept showing zero activity.
//
// `platform` is optional — pass it from the app if you have a
// friendly device string (e.g. "Android 14 • Pixel 8"); it falls
// back to "Unknown device" if not sent. Same (user, ip, platform)
// combo is treated as the same device across repeated logins (we
// update last_seen) instead of creating a new row every time.
// ---------------------------------------------------------------
$platform = trim($in['platform'] ?? '') ?: 'Unknown device';
$ip = client_ip();

touch_device($pdo, $user['id'], $ip, $platform);

respond([
    'ok' => true,
    'name' => $user['name'],
    'status' => $user['status'],
    'expiry' => $user['expiry'],
    'blocked' => $user['status'] === 'blocked',
    // Previously nothing checked this at login time — a token past its
    // expiry date would still come back as a normal successful login.
    // The app should treat expired:true the same as blocked and refuse
    // to open, prompting the user to renew instead.
    'expired' => days_until($user['expiry']) < 0,
]);
