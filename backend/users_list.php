<?php
require __DIR__ . '/config.php';
require_auth($pdo);

$users = $pdo->query('SELECT id, name, email, token, status, expiry FROM users ORDER BY name ASC')->fetchAll();

$devicesStmt = $pdo->query('SELECT id, user_id, platform, ip, last_seen FROM devices ORDER BY last_seen DESC');
$devicesByUser = [];
foreach ($devicesStmt->fetchAll() as $d) {
    $devicesByUser[$d['user_id']][] = [
        'id' => $d['id'],
        'platform' => $d['platform'],
        'ip' => $d['ip'],
        'lastSeen' => $d['last_seen'],
    ];
}

foreach ($users as &$u) {
    $u['expiry'] = $u['expiry']; // already Y-m-d
    $u['devices'] = $devicesByUser[$u['id']] ?? [];
}
unset($u);

respond(['users' => $users]);
