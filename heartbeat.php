<?php
// heartbeat.php - รักษาสถานะการเชื่อมต่อ Session (Session Keep-Alive)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode([
    'status' => 'ok',
    'logged_in' => $isLoggedIn,
    'user' => $isLoggedIn ? ($currentUser['fullname'] ?? '') : null,
    'role' => $isLoggedIn ? ($currentUser['role'] ?? '') : null,
    'timestamp' => time()
], JSON_UNESCAPED_UNICODE);
exit;
