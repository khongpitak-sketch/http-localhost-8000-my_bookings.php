<?php
// login.php - หน้าเข้าสู่ระบบสำหรับ Admin และเจ้าหน้าที่
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$redirect = $_GET['redirect'] ?? 'admin.php';

if ($isLoggedIn && $isAdmin) {
    header("Location: " . $redirect);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            header("Location: " . $redirect);
            exit;
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบผู้ดูแลระบบ (Admin Login) - คณะวิทยาการจัดการ ม.นราธิวาสราชนครินทร์</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --pnu-blue: #0b3c6d;
            --pnu-gold: #c59b27;
        }
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #0b3c6d 0%, #1e5799 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
        }
        .login-header {
            background: #f8fafc;
            border-bottom: 2px solid var(--pnu-gold);
            padding: 30px 25px 20px;
            text-align: center;
        }
        .btn-pnu {
            background-color: var(--pnu-blue);
            color: #fff;
            font-weight: 600;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-pnu:hover {
            background-color: #072747;
            color: #fff;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="mb-2">
            <img src="assets/pnu_emblem.png" alt="PNU Logo" style="height: 75px; width: auto;">
        </div>
        <h5 class="fw-bold text-dark mb-1">เข้าสู่ระบบผู้ดูแลระบบ</h5>
        <div class="text-muted small">ระบบขออนุญาตใช้รถยนต์</div>
        <div class="text-muted small">คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์</div>
    </div>

    <div class="p-4">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php?redirect=<?= htmlspecialchars($redirect) ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold small text-secondary">
                    <i class="fas fa-user me-1 text-primary"></i> ชื่อผู้ใช้งาน (Username)
                </label>
                <input type="text" name="username" class="form-control form-control-lg fs-6" 
                       placeholder="กรอกชื่อผู้ใช้ เช่น Aeksit" 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold small text-secondary">
                    <i class="fas fa-lock me-1 text-primary"></i> รหัสผ่าน (Password)
                </label>
                <input type="password" name="password" class="form-control form-control-lg fs-6" 
                       placeholder="กรอกรหัสผ่าน" required>
            </div>

            <button type="submit" class="btn btn-pnu w-100 mb-3">
                <i class="fas fa-sign-in-alt me-2"></i> เข้าสู่ระบบ Admin
            </button>

            <div class="text-center pt-2 border-top">
                <a href="index.php" class="text-decoration-none small text-muted">
                    <i class="fas fa-arrow-left me-1"></i> กลับหน้าหลักสำหรับผู้ใช้ทั่วไป
                </a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
