<?php
require __DIR__ . '/config.php';
require_auth($pdo);

$users = $pdo->query('SELECT id, name, email, token, status, expiry FROM users ORDER BY name ASC')->fetchAll();

// A device (and by extension, its user) counts as "online" if it
// checked in — via user_login.php or user_verify.php — within the
// last ONLINE_THRESHOLD_MINUTES. Computed in SQL so it's always
// measured against the database's own clock, not PHP's.
$onlineWindow = (int) ONLINE_THRESHOLD_MINUTES;
$devicesStmt = $pdo->query(
    "SELECT id, user_id, platform, ip, last_seen,
            (last_seen > NOW() - INTERVAL '{$onlineWindow} minutes') AS online
     FROM devices ORDER BY last_seen DESC"
);
$devicesByUser = [];
foreach ($devicesStmt->fetchAll() as $d) {
    $devicesByUser[$d['user_id']][] = [
        'id' => $d['id'],
        'platform' => $d['platform'],
        'ip' => $d['ip'],
        'lastSeen' => $d['last_seen'],
        'online' => (bool) $d['online'],
    ];
}

foreach ($users as &$u) {
    $u['expiry'] = $u['expiry']; // already Y-m-d
    $u['devices'] = $devicesByUser[$u['id']] ?? [];
    // User-level online flag: true the moment ANY of their devices is
    // within the online window (e.g. logged in from two phones, only
    // one currently active — still shows the user as online).
    $u['online'] = false;
    foreach ($u['devices'] as $d) {
        if ($d['online']) { $u['online'] = true; break; }
    }
}
unset($u);

respond(['users' => $users]);
