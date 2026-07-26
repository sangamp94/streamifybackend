<?php
/**
 * setup.php — run this ONCE in your browser after uploading, to create
 * your admin login. Then DELETE this file from the server.
 *
 * Visit: https://yoursite.infinityfreeapp.com/backend/setup.php
 */
require __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

$stmt = $pdo->query('SELECT COUNT(*) AS c FROM admin_users');
$adminExists = $stmt->fetch()['c'] > 0;

$message = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$adminExists) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (strlen($username) < 3) {
        $message = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
        $ins->execute([$username, $hash]);
        $done = true;
        $adminExists = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>AppGuard — First-time setup</title>
<style>
  body { font-family: system-ui, sans-serif; background:#10141C; color:#ECEFF4; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
  .box { background:#171D28; border:1px solid #2A3240; border-radius:12px; padding:28px; width:100%; max-width:380px; }
  h1 { font-size:18px; margin:0 0 6px; }
  p { font-size:13px; color:#9AA5B8; margin:0 0 18px; }
  label { font-size:12px; color:#9AA5B8; display:block; margin-bottom:6px; }
  input { width:100%; box-sizing:border-box; background:#1B2230; border:1px solid #2A3240; color:#ECEFF4; padding:9px 11px; border-radius:8px; margin-bottom:14px; font-size:13px; }
  button { width:100%; background:#C9A24B; color:#14100a; border:none; padding:10px; border-radius:8px; font-weight:600; cursor:pointer; }
  .msg { color:#E0625B; font-size:12.5px; margin-bottom:12px; }
  .ok { color:#4FA8A0; }
  .warn { background:rgba(224,98,91,0.12); border:1px solid rgba(224,98,91,0.3); padding:12px; border-radius:8px; font-size:12.5px; margin-top:16px; }
</style>
</head>
<body>
  <div class="box">
    <h1>AppGuard setup</h1>
    <?php if ($done): ?>
      <p class="ok">Admin account created. You can now log in from the app.</p>
      <div class="warn">⚠ Delete <code>backend/setup.php</code> from your server now — leaving it up lets anyone re-run setup.</div>
    <?php elseif ($adminExists): ?>
      <p>An admin account already exists.</p>
      <div class="warn">⚠ Delete <code>backend/setup.php</code> from your server — it no longer does anything useful and should not stay online.</div>
    <?php else: ?>
      <p>Create the first admin login for this console.</p>
      <?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <form method="post">
        <label>Username</label>
        <input type="text" name="username" required minlength="3">
        <label>Password (min 8 characters)</label>
        <input type="password" name="password" required minlength="8">
        <button type="submit">Create admin account</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
