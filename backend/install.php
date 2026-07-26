<?php
/**
 * install.php — one-time web installer.
 *
 * Visit this file in your browser after uploading. Paste in the four
 * values from InfinityFree control panel -> MySQL Databases, and this
 * page will:
 *   1. Test the DB connection and tell you EXACTLY what's wrong if it fails
 *   2. Create all tables + demo data (from schema.sql) if they don't exist yet
 *   3. Write those four values into config.php for you
 *
 * When it says "Setup complete", go run setup.php next.
 * Then DELETE both install.php and setup.php from the server.
 */

$configPath = __DIR__ . '/config.php';
$schemaPath = __DIR__ . '/schema.sql';

// ---- Pull current values out of config.php just to prefill the form ----
function current_value($key, $configText) {
    if (preg_match('/define\(\'' . $key . '\',\s*\'(.*?)\'\)/', $configText, $m)) {
        return $m[1];
    }
    return '';
}

$configText = is_readable($configPath) ? file_get_contents($configPath) : '';
$prefill = [
    'host' => current_value('DB_HOST', $configText),
    'name' => current_value('DB_NAME', $configText),
    'user' => current_value('DB_USER', $configText),
];
foreach ($prefill as $k => $v) {
    if ($v === 'sqlXXX.infinityfree.com' || strpos($v, 'XXXXXXXX') !== false || $v === 'your-db-password-here') {
        $prefill[$k] = '';
    }
}

$step = 'form';   // form | error | success
$errorTitle = '';
$errorDetail = '';
$errorHint = '';
$schemaLog = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['host'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $user = trim($_POST['user'] ?? '');
    $pass = $_POST['pass'] ?? '';

    $prefill = ['host' => $host, 'name' => $name, 'user' => $user];

    if ($host === '' || $name === '' || $user === '') {
        $step = 'error';
        $errorTitle = 'Missing fields';
        $errorDetail = 'Host, database name, and username are all required.';
    } else {
        try {
            $pdo = new PDO(
                'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 8,
                ]
            );

            // ---- Connected. Check whether ALL required tables already exist. ----
            // (Checking only one table is fragile: if an earlier install run
            // stopped partway through, that one table can exist while others
            // are still missing, and every future run would wrongly skip the
            // whole schema step forever.)
            $requiredTables = ['admin_users', 'admin_sessions', 'users', 'devices', 'logs', 'update_history', 'extend_links'];
            $missingTables = [];
            foreach ($requiredTables as $t) {
                $chk = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
                if (!$chk || $chk->rowCount() === 0) {
                    $missingTables[] = $t;
                }
            }
            $tablesExist = empty($missingTables);

            if (!$tablesExist) {
                if (!is_readable($schemaPath)) {
                    throw new RuntimeException('Connected fine, but schema.sql was not found next to install.php — re-upload it and try again.');
                }
                $sql = file_get_contents($schemaPath);
                // Strip -- comments, then split into individual statements.
                $sql = preg_replace('/^--.*$/m', '', $sql);
                $statements = array_filter(array_map('trim', explode(";\n", $sql)));
                // Run every statement. CREATE TABLE IF NOT EXISTS is safe to
                // repeat; seed INSERTs may legitimately fail with a duplicate-key
                // error if some data already exists — log those but keep going
                // instead of aborting the rest of the file over one bad statement.
                $schemaErrors = [];
                foreach ($statements as $stmtSql) {
                    $stmtSql = rtrim(trim($stmtSql), ';');
                    if ($stmtSql === '') continue;
                    try {
                        $pdo->exec($stmtSql);
                        $schemaLog[] = $stmtSql;
                    } catch (PDOException $stmtEx) {
                        $schemaErrors[] = substr($stmtSql, 0, 60) . '... — ' . $stmtEx->getMessage();
                    }
                }

                // Re-check afterwards so we can tell the user exactly what's
                // still missing instead of assuming success.
                $stillMissing = [];
                foreach ($requiredTables as $t) {
                    $chk = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
                    if (!$chk || $chk->rowCount() === 0) {
                        $stillMissing[] = $t;
                    }
                }
                if ($stillMissing) {
                    throw new RuntimeException(
                        'Ran schema.sql, but these tables are still missing: ' . implode(', ', $stillMissing) . '.' .
                        ($schemaErrors ? ' Statement errors: ' . implode(' | ', $schemaErrors) : '') .
                        ' You can also paste backend/schema.sql directly into phpMyAdmin\'s SQL tab as a fallback.'
                    );
                }
            }

            // ---- Write config.php with the working values ----
            if ($configText === '') {
                throw new RuntimeException('config.php was not found next to install.php.');
            }
            $newConfig = $configText;
            $newConfig = preg_replace(
                '/define\(\'DB_HOST\',\s*.*?\);/',
                "define('DB_HOST', " . var_export($host, true) . ');',
                $newConfig
            );
            $newConfig = preg_replace(
                '/define\(\'DB_NAME\',\s*.*?\);/',
                "define('DB_NAME', " . var_export($name, true) . ');',
                $newConfig
            );
            $newConfig = preg_replace(
                '/define\(\'DB_USER\',\s*.*?\);/',
                "define('DB_USER', " . var_export($user, true) . ');',
                $newConfig
            );
            $newConfig = preg_replace(
                '/define\(\'DB_PASS\',\s*.*?\);/',
                "define('DB_PASS', " . var_export($pass, true) . ');',
                $newConfig
            );

            if (!is_writable($configPath) && file_exists($configPath)) {
                throw new RuntimeException('Connected and schema is ready, but config.php is not writable by PHP. Open config.php in the File Manager and paste in these four lines yourself:<br><br>' .
                    '<code>define(\'DB_HOST\', ' . htmlspecialchars(var_export($host, true)) . ');<br>' .
                    'define(\'DB_NAME\', ' . htmlspecialchars(var_export($name, true)) . ');<br>' .
                    'define(\'DB_USER\', ' . htmlspecialchars(var_export($user, true)) . ');<br>' .
                    'define(\'DB_PASS\', ' . htmlspecialchars(var_export($pass, true)) . ');</code>');
            }

            $written = @file_put_contents($configPath, $newConfig);
            if ($written === false) {
                throw new RuntimeException('Could not write config.php (permission denied). Paste these four lines into it yourself via the File Manager:<br><br>' .
                    '<code>define(\'DB_HOST\', ' . htmlspecialchars(var_export($host, true)) . ');<br>' .
                    'define(\'DB_NAME\', ' . htmlspecialchars(var_export($name, true)) . ');<br>' .
                    'define(\'DB_USER\', ' . htmlspecialchars(var_export($user, true)) . ');<br>' .
                    'define(\'DB_PASS\', ' . htmlspecialchars(var_export($pass, true)) . ');</code>');
            }

            $step = 'success';

        } catch (PDOException $e) {
            $step = 'error';
            $msg = $e->getMessage();
            $errorTitle = 'Could not connect to the database';
            $errorDetail = $msg;
            if (stripos($msg, '1045') !== false || stripos($msg, 'Access denied') !== false) {
                $errorHint = 'This means the username or password is wrong. Copy them again directly from the InfinityFree control panel — don\'t retype them by hand.';
            } elseif (stripos($msg, '1049') !== false || stripos($msg, 'Unknown database') !== false) {
                $errorHint = 'This means the database name doesn\'t exist under that user. Double check the exact name shown in the control panel (it usually looks like if0_XXXXXXXX_appguard).';
            } elseif (stripos($msg, '2002') !== false || stripos($msg, 'getaddrinfo') !== false || stripos($msg, 'Connection refused') !== false || stripos($msg, 'Connection timed out') !== false) {
                $errorHint = 'This means the hostname is wrong or unreachable. It should look like sql200.infinityfree.com — copy it exactly from the control panel, no http://, no trailing slash.';
            } elseif (stripos($msg, 'could not find driver') !== false) {
                $errorHint = 'The PHP on this server is missing the MySQL/PDO extension. This is unusual on InfinityFree — contact their support if it persists.';
            } else {
                $errorHint = 'Double-check all four values against the InfinityFree control panel -> MySQL Databases.';
            }
        } catch (Exception $e) {
            $step = 'error';
            $errorTitle = 'Setup failed';
            $errorDetail = $e->getMessage();
            $errorHint = 'You can also do this step manually: paste the full contents of backend/schema.sql into phpMyAdmin\'s SQL tab and click Go.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AppGuard — Install</title>
<style>
  body { font-family: system-ui, sans-serif; background:#10141C; color:#ECEFF4; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; padding:20px; box-sizing:border-box; }
  .box { background:#171D28; border:1px solid #2A3240; border-radius:12px; padding:28px; width:100%; max-width:440px; }
  h1 { font-size:18px; margin:0 0 6px; }
  p { font-size:13px; color:#9AA5B8; margin:0 0 18px; line-height:1.5; }
  label { font-size:12px; color:#9AA5B8; display:block; margin-bottom:6px; }
  input { width:100%; box-sizing:border-box; background:#1B2230; border:1px solid #2A3240; color:#ECEFF4; padding:9px 11px; border-radius:8px; margin-bottom:14px; font-size:13px; }
  button { width:100%; background:#C9A24B; color:#14100a; border:none; padding:10px; border-radius:8px; font-weight:600; cursor:pointer; font-size:13px; }
  .msg { color:#E0625B; font-size:12.5px; margin-bottom:12px; line-height:1.5; }
  .ok { color:#4FA8A0; }
  .warn { background:rgba(224,98,91,0.12); border:1px solid rgba(224,98,91,0.3); padding:12px; border-radius:8px; font-size:12.5px; margin-top:16px; line-height:1.5; }
  .hint { background:rgba(201,162,75,0.1); border:1px solid rgba(201,162,75,0.3); padding:12px; border-radius:8px; font-size:12.5px; margin-bottom:14px; line-height:1.5; }
  code { background:#0d1017; padding:2px 5px; border-radius:4px; font-size:12px; word-break:break-all; }
  a { color:#C9A24B; }
  .step { font-size:11px; color:#5B6472; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:4px; }
</style>
</head>
<body>
  <div class="box">
    <div class="step">AppGuard setup — step 1 of 2</div>

    <?php if ($step === 'success'): ?>
      <h1 class="ok">Database connected ✓</h1>
      <p>Tables are ready<?= $schemaLog ? ' (created just now from schema.sql, with demo data)' : ' (already existed, left untouched)' ?>, and <code>config.php</code> has been updated with your credentials.</p>
      <p><strong>Next:</strong> go to <a href="setup.php">setup.php</a> to create your admin login.</p>
      <div class="warn">⚠ Once setup.php is done, delete <strong>both</strong> <code>install.php</code> and <code>setup.php</code> from the server — leaving either one up is a security risk.</div>

    <?php else: ?>
      <h1>Connect your database</h1>
      <p>Paste the four values from InfinityFree control panel → <strong>MySQL Databases</strong>. This page tests the connection, creates the tables, and saves everything into <code>config.php</code> for you.</p>

      <?php if ($step === 'error'): ?>
        <div class="msg"><strong><?= htmlspecialchars($errorTitle) ?></strong><br><?= htmlspecialchars($errorDetail) ?></div>
        <?php if ($errorHint): ?><div class="hint"><?= $errorHint ?></div><?php endif; ?>
      <?php endif; ?>

      <form method="post">
        <label>DB Host <span style="color:#5B6472">(e.g. sql200.infinityfree.com)</span></label>
        <input type="text" name="host" value="<?= htmlspecialchars($prefill['host']) ?>" required>
        <label>DB Name <span style="color:#5B6472">(e.g. if0_12345678_appguard)</span></label>
        <input type="text" name="name" value="<?= htmlspecialchars($prefill['name']) ?>" required>
        <label>DB Username <span style="color:#5B6472">(e.g. if0_12345678)</span></label>
        <input type="text" name="user" value="<?= htmlspecialchars($prefill['user']) ?>" required>
        <label>DB Password</label>
        <input type="password" name="pass" required>
        <button type="submit">Test connection &amp; install</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
