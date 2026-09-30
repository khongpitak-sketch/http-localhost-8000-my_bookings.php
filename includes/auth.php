<?php
// includes/auth.php - จัดการ Session และการเข้าสู่ระบบแบบถาวร (ป้องกันระบบเด้งออก)

if (session_status() === PHP_SESSION_NONE) {
    // 1. กำหนดโฟลเดอร์สำหรับเก็บ Session ใน data/sessions (หรือ /tmp/sessions บน Vercel)
    $isVercel = (getenv('VERCEL') || isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']));
    $sessionSavePath = $isVercel ? '/tmp/sessions' : (__DIR__ . '/../data/sessions');
    if (!file_exists($sessionSavePath)) {
        @mkdir($sessionSavePath, 0777, true);
    }
    if (is_dir($sessionSavePath) && is_writable($sessionSavePath)) {
        session_save_path($sessionSavePath);
    }

    // 2. กำหนดอายุ Session เป็น 30 วัน (2,592,000 วินาที) ทั้งตัวเก็บใน Server และคุกกี้ในเบราว์เซอร์
    $sessionLifetime = 86400 * 30; // 30 วัน
    ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
    ini_set('session.cookie_lifetime', (string)$sessionLifetime);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '1000');

    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
               || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

require_once __DIR__ . '/../config/db.php';

// 3. ระบบ Auto-Login (Remember-Me Token) ป้องกันการหลุดจากระบบแม้เปิดปิดเบราว์เซอร์ใหม่
if (empty($_SESSION['user']) && !empty($_COOKIE['pnu_remember'])) {
    $rememberToken = $_COOKIE['pnu_remember'];
    if (is_string($rememberToken) && strlen($rememberToken) >= 32) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ? AND (remember_token_expiry IS NULL OR remember_token_expiry > datetime('now'))");
            $stmt->execute([$rememberToken]);
            $autoUser = $stmt->fetch();
            if ($autoUser) {
                $_SESSION['user'] = $autoUser;
            } else {
                setcookie('pnu_remember', '', time() - 3600, '/');
            }
        } catch (Exception $e) {
            // กรณีเกิดข้อผิดพลาดในการตรวจสอบ token ข้ามไปล็อกอินปกติ
        }
    }
}

$currentUser = $_SESSION['user'] ?? null;
$isLoggedIn = !empty($currentUser);
$isAdmin = $isLoggedIn && (($currentUser['role'] ?? '') === 'admin');

// ฟังก์ชันสร้าง Remember Token เก็บในฐานข้อมูลและฝังคุกกี้ระยะยาว (90 วัน)
function issueRememberToken($pdo, $userId) {
    try {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', time() + (86400 * 90)); // 90 วัน
        $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, remember_token_expiry = ? WHERE id = ?");
        $stmt->execute([$token, $expiry, $userId]);

        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
                   || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        setcookie('pnu_remember', $token, [
            'expires' => time() + (86400 * 90),
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ฟังก์ชันยกเลิก Remember Token เมื่อผู้ใช้กด Logout
function clearRememberToken($pdo, $userId = null) {
    if ($userId) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_token_expiry = NULL WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {}
    }
    setcookie('pnu_remember', '', time() - 86400, '/');
}

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
