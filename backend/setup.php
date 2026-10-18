<?php
/**
 * One-time admin bootstrap / password recovery.
 *
 * Set ADMIN_SETUP_SECRET in Vercel Environment Variables first.
 * This endpoint never creates or resets an admin without that secret.
 */

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST only', 405);
}

$expectedSecret = getenv('ADMIN_SETUP_SECRET') ?: '';
if ($expectedSecret === '') {
    fail('Admin setup is disabled. Add ADMIN_SETUP_SECRET in Vercel Environment Variables, then redeploy.', 503);
}

$in = json_input();
$providedSecret = (string)($in['setup_secret'] ?? '');
if ($providedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
    fail('Invalid setup secret.', 403);
}

$action = strtolower(trim((string)($in['action'] ?? 'create')));
$username = trim((string)($in['username'] ?? ''));
$password = (string)($in['password'] ?? '');

if (!in_array($action, ['create', 'reset'], true)) {
    fail('Action must be create or reset.');
}
if (!preg_match('/^[A-Za-z0-9_.@-]{3,50}$/', $username)) {
    fail('Username must be 3-50 characters and use only letters, numbers, dot, underscore, @ or hyphen.');
}
if (strlen($password) < 12) {
    fail('Password must be at least 12 characters long.');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    if ($action === 'create') {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($count > 0) {
            fail('An admin account already exists. Choose Reset Password and enter its existing username.', 409);
        }

        $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$username, $passwordHash]);
        respond([
            'ok' => true,
            'message' => 'Admin account created. Go back to the admin panel and sign in.',
            'username' => $username,
        ], 201);
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE username = ?');
    $stmt->execute([$passwordHash, $username]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        fail('That admin username was not found. Check the username in Neon and try again.', 404);
    }

    $stmt = $pdo->prepare('DELETE FROM admin_sessions WHERE admin_id = (SELECT id FROM admin_users WHERE username = ?)');
    $stmt->execute([$username]);
    $pdo->commit();

    respond([
        'ok' => true,
        'message' => 'Password reset successfully. Existing admin sessions were signed out; you can now log in.',
        'username' => $username,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (strpos($e->getCode(), '23') === 0) {
        fail('Could not create this admin. The username may already exist.', 409);
    }
    throw $e;
}
