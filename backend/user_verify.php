<?php
require __DIR__ . '/config.php';
// Public endpoint — this is the one the ACTUAL APP should call (not
// user_login.php). The difference matters:
//
//   user_login.php  -> ALWAYS returns HTTP 200, even for a blocked or
//                       expired token, because frontend/extend.html
//                       needs to show a status card ("your token
//                       expired, tap to extend") to people who are
//                       exactly in that state. Failing the request
//                       there would break the self-service extend page.
//
//   user_verify.php -> Returns a real HTTP 403 the moment a token is
//                       blocked or expired. Nothing further is sent
//                       back except the reason — no bypassable "please
//                       be nice and stop" flag. This is what should
//                       gate whether the app is allowed to open/play.
//
// Call this from the app:
//   1. Once at startup / login, to decide whether to let the user in.
//   2. Again every few minutes while the app stays open (e.g. every
//      3-5 minutes). This does two things: (a) if an admin blocks the
//      account or it expires mid-session, access is cut off on the
//      very next check instead of only at the next app restart; and
//      (b) every successful call refreshes this device's "last seen"
//      time, which is what makes the Online/Offline indicator in the
//      admin console actually mean something.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$token = trim($in['token'] ?? '');
if ($token === '') fail('Token is required');

$user = find_user_by_token($pdo, $token);

$platform = trim($in['platform'] ?? '') ?: 'Unknown device';
$ip = client_ip();
touch_device($pdo, $user['id'], $ip, $platform);

if ($user['status'] === 'blocked') {
    respond([
        'ok' => false,
        'allowed' => false,
        'reason' => 'blocked',
        'error' => 'Your account is blocked. Contact support to resolve this.',
        'name' => $user['name'],
        'expiry' => $user['expiry'],
    ], 403);
}

$daysLeft = days_until($user['expiry']);
if ($daysLeft < 0) {
    respond([
        'ok' => false,
        'allowed' => false,
        'reason' => 'expired',
        'error' => 'Your access expired. Extend your token to continue using the app.',
        'name' => $user['name'],
        'expiry' => $user['expiry'],
    ], 403);
}

respond([
    'ok' => true,
    'allowed' => true,
    'name' => $user['name'],
    'status' => $user['status'],
    'expiry' => $user['expiry'],
    'days_left' => $daysLeft,
]);
