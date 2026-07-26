<?php
require __DIR__ . '/config.php';
$adminId = require_auth($pdo);

$stmt = $pdo->prepare('SELECT username FROM admin_users WHERE id = ?');
$stmt->execute([$adminId]);
$row = $stmt->fetch();

respond(['username' => $row['username'] ?? null]);
