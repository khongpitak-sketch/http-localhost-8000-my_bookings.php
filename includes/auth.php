<?php
// includes/auth.php - จัดการ Session และการเข้าสู่ระบบ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

$currentUser = $_SESSION['user'] ?? null;
$isLoggedIn = !empty($currentUser);
$isAdmin = $isLoggedIn && ($currentUser['role'] === 'admin');

// ฟังก์ชันบังคับสิทธิ์ Admin
function requireAdmin() {
    global $isAdmin;
    if (!$isAdmin) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'admin.php');
        header("Location: login.php?redirect=$redirect");
        exit;
    }
}

// ฟังก์ชันบังคับล็อกอิน
function requireLogin() {
    global $isLoggedIn;
    if (!$isLoggedIn) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header("Location: login.php?redirect=$redirect&login_required=1");
        exit;
    }
}
