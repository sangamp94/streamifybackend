<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);

$in = json_input();
$username = trim($in['username'] ?? '');
$password = $in['password'] ?? '';

if ($username === '' || $password === '') fail('Username and password are required');

$stmt = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE username = ?');
$stmt->execute([$username]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    fail('Invalid username or password', 401);
}

$token = gen_token();
// Postgres: NOW() + INTERVAL literal (7 days is fixed here, no placeholder needed).
$ins = $pdo->prepare(
    "INSERT INTO admin_sessions (token, admin_id, expires_at) VALUES (?, ?, NOW() + INTERVAL '7 days')"
);
$ins->execute([$token, $admin['id']]);

respond(['token' => $token, 'username' => $username]);
