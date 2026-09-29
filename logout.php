<?php
// logout.php - ออกจากระบบ
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// เคลียร์ Remember Token ในฐานข้อมูลและลบคุกกี้ถาวร
if (!empty($currentUser['id'])) {
    clearRememberToken($pdo, $currentUser['id']);
} else {
    clearRememberToken($pdo);
}

// ล้างข้อมูล Session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: index.php");
exit;
