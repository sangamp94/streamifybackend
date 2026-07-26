<?php
require __DIR__ . '/config.php';
// Public endpoint — the app user hits this after logging in with their
// token, right before being sent through the shortener link.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$token = trim($in['token'] ?? '');
if ($token === '') fail('Please enter your token');

$user = find_user_by_token($pdo, $token);
if ($user['status'] === 'blocked') {
    fail('Your account is blocked. Contact support to resolve this before extending.', 403);
}

// Reuse an existing pending (unredeemed, not yet expired) self-service
// request for this user instead of piling up duplicate rows every time
// they reopen the page or click twice.
$stmt = $pdo->prepare(
    'SELECT sig FROM extend_links
     WHERE user_id = ? AND redeemed = 0 AND expires_at > NOW() AND days = ?
     ORDER BY created_at DESC LIMIT 1'
);
$stmt->execute([$user['id'], SELF_EXTEND_DAYS]);
$existing = $stmt->fetch();

if (!$existing) {
    $sig = gen_token(24);
    // Compute expires_at with MySQL's own NOW(), not PHP's DateTime — the
    // later "expires_at > NOW()" checks also run in MySQL, so both sides
    // must share the same clock or a row can look expired the instant
    // it's created if PHP's and MySQL's server timezones differ.
    // Postgres: multiply an INTERVAL literal by the placeholder instead
    // of MySQL's DATE_ADD(NOW(), INTERVAL ? MINUTE).
    $ins = $pdo->prepare(
        "INSERT INTO extend_links (user_id, days, sig, expires_at)
         VALUES (?, ?, ?, NOW() + (INTERVAL '1 minute' * ?))"
    );
    $ins->execute([$user['id'], SELF_EXTEND_DAYS, $sig, SELF_EXTEND_LINK_LIFETIME_MIN]);

    $log = $pdo->prepare('INSERT INTO logs (action, text) VALUES (?, ?)');
    $log->execute(['self-extend-request', "{$user['name']} requested a self-service +" . SELF_EXTEND_DAYS . "d extension"]);
}

respond(['ok' => true, 'redirect' => SHORTENER_URL]);
