<?php
// includes/header.php
require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบขออนุญาตใช้รถยนต์ - คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts (Prompt & Sarabun) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:ital,wght@0,300;0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <style>
        :root {
            --pnu-blue: #0b3c6d;
            --pnu-gold: #c59b27;
            --pnu-light: #f4f7fb;
        }
        body {
            font-family: 'Prompt', sans-serif;
            background-color: var(--pnu-light);
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar-pnu {
            background: linear-gradient(135deg, #0b3c6d 0%, #1e5799 100%);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .navbar-pnu .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 0.6rem 1rem !important;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .navbar-pnu .nav-link:hover, .navbar-pnu .nav-link.active {
            color: #fff !important;
            background-color: rgba(255, 255, 255, 0.15);
        }
        .role-bar {
            background-color: #212529;
            color: #fff;
            padding: 6px 0;
            font-size: 0.85rem;
            border-bottom: 2px solid var(--pnu-gold);
        }
        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card-custom:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        .badge-step {
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 20px;
        }
        .btn-pnu {
            background-color: var(--pnu-blue);
            color: white;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-pnu:hover {
            background-color: #072747;
            color: white;
            transform: translateY(-1px);
        }
        .btn-gold {
            background-color: var(--pnu-gold);
            color: #212529;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-gold:hover {
            background-color: #a8831e;
            color: white;
        }
        .timeline-step {
            position: relative;
            padding-left: 30px;
            margin-bottom: 20px;
        }
        .timeline-step::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: -20px;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-step:last-child::before {
            display: none;
        }
        .timeline-icon {
            position: absolute;
            left: 0;
            top: 2px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #6c757d;
            border: 2px solid #fff;
        }
        .timeline-step.completed .timeline-icon {
            background: #198754;
        }
        .timeline-step.active .timeline-icon {
            background: #ffc107;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.3);
        }
        .timeline-step.rejected .timeline-icon {
            background: #dc3545;
        }
        .notice-box {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>

<!-- Role & Status Bar -->
<div class="role-bar">
    <div class="container d-flex flex-wrap justify-content-between align-items-center">
        <?php if ($isAdmin): ?>
            <div class="d-flex align-items-center">
                <span class="badge bg-success me-2"><i class="fas fa-user-shield me-1"></i> เข้าสู่ระบบในฐานะ Admin</span>
                <strong class="text-warning me-2"><?= htmlspecialchars($currentUser['fullname']) ?></strong>
                <span class="text-light opacity-75 small">(<?= htmlspecialchars($currentUser['position']) ?>)</span>
            </div>
            <div>
                <a href="logout.php" class="btn btn-outline-light btn-sm py-0 px-2 small" onclick="return confirm('ยืนยันต้องการออกจากระบบ?');">
                    <i class="fas fa-sign-out-alt me-1"></i> ออกจากระบบ
                </a>
            </div>
        <?php else: ?>
            <div class="small text-light">
                <i class="fas fa-info-circle text-info me-1"></i> โหมดผู้ใช้ทั่วไป: ยื่นคำขอจองรถยนต์และติดตามผล (แก้ไขข้อมูลไม่ได้)
            </div>
            <div>
                <a href="login.php" class="btn btn-warning btn-sm py-0 px-2 fw-bold text-dark">
                    <i class="fas fa-lock me-1"></i> เข้าสู่ระบบ Admin
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Main Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-pnu sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <div class="bg-white rounded-circle p-1 me-2 d-flex align-items-center justify-content-center overflow-hidden" style="width: 44px; height: 44px;">
                <img src="assets/pnu_emblem.png" alt="PNU Emblem" style="height: 38px; width: auto;">
            </div>
            <div>
                <div class="fw-bold fs-6 lh-1">ระบบขออนุญาตใช้รถยนต์</div>
                <small class="text-light opacity-75" style="font-size: 0.75rem;">คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์</small>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage == 'index.php') ? 'active' : '' ?>" href="index.php">
                        <i class="fas fa-home me-1"></i> หน้าหลัก & ปฏิทิน
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage == 'book.php') ? 'active' : '' ?>" href="book.php">
                        <i class="fas fa-calendar-plus me-1"></i> ยื่นขอใช้รถยนต์
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage == 'my_bookings.php') ? 'active' : '' ?>" href="my_bookings.php">
                        <i class="fas fa-list-check me-1"></i> ติดตามสถานะคำขอ
                    </a>
                </li>

                <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage == 'approvals.php') ? 'active' : '' ?>" href="approvals.php">
                            <i class="fas fa-signature me-1"></i> ศูนย์พิจารณาอนุมัติ
                            <?php
                            $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status LIKE 'pending_%' AND (is_flagged_fake IS NULL OR is_flagged_fake = 0)")->fetchColumn();
                            if ($pendingCount > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage == 'vehicles.php') ? 'active' : '' ?>" href="vehicles.php">
                            <i class="fas fa-car me-1"></i> ข้อมูลรถยนต์
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-warning fw-bold <?= ($currentPage == 'admin.php') ? 'active' : '' ?>" href="admin.php">
                            <i class="fas fa-shield-halved me-1"></i> จัดการระบบ (Admin)
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <a href="book.php" class="btn btn-gold btn-sm px-3 shadow-sm">
                    <i class="fas fa-plus-circle me-1"></i> จองรถใหม่
                </a>
                <?php if (!$isAdmin): ?>
                    <a href="login.php" class="btn btn-outline-light btn-sm px-3">
                        <i class="fas fa-sign-in-alt me-1"></i> เข้าสู่ระบบ Admin
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="container my-4 flex-grow-1">
