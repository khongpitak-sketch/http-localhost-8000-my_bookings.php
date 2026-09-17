<?php
// book.php - แบบฟอร์มขออนุญาตใช้รถยนต์
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// ผู้ใช้ต้องเข้าสู่ระบบก่อนเท่านั้นจึงจะสามารถจองรถได้
requireLogin();

$vehicles = $pdo->query("SELECT * FROM vehicles WHERE status = 'active'")->fetchAll();
$error = '';
$success = '';

// สุ่มคำถามความปลอดภัยป้องกันสแปมและบอท (Security Math Challenge)
if (empty($_SESSION['captcha_ans']) || (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET')) {
    $num1 = rand(2, 9);
    $num2 = rand(1, 9);
    $_SESSION['captcha_q'] = "$num1 + $num2 = ?";
    $_SESSION['captcha_ans'] = $num1 + $num2;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $requester_name = trim($_POST['requester_name'] ?? '');
    $requester_position = trim($_POST['requester_position'] ?? '');
    $requester_department = trim($_POST['requester_department'] ?? '');
    $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $route_from = trim($_POST['route_from'] ?? '');
    $route_to = trim($_POST['route_to'] ?? '');

    // แปลงวัน เดือน ปี (พ.ศ.) เป็น YYYY-MM-DD
    if (!empty($_POST['start_day']) && !empty($_POST['start_month']) && !empty($_POST['start_year'])) {
        $sDay = (int)$_POST['start_day'];
        $sMonth = (int)$_POST['start_month'];
        $sYear = (int)$_POST['start_year'];
        if ($sYear > 2400) {
            $sYear -= 543; // แปลง พ.ศ. เป็น ค.ศ.
        }
        $start_date = sprintf('%04d-%02d-%02d', $sYear, $sMonth, $sDay);
    } else {
        $start_date = trim($_POST['start_date'] ?? '');
    }

    // เวลาออกเดินทาง (ระบบ 24 ชั่วโมง)
    if (isset($_POST['start_hour']) && isset($_POST['start_minute'])) {
        $start_time = sprintf('%02d:%02d', (int)$_POST['start_hour'], (int)$_POST['start_minute']);
    } else {
        $start_time = trim($_POST['start_time'] ?? '');
    }

    if (!empty($_POST['end_day']) && !empty($_POST['end_month']) && !empty($_POST['end_year'])) {
        $eDay = (int)$_POST['end_day'];
        $eMonth = (int)$_POST['end_month'];
        $eYear = (int)$_POST['end_year'];
        if ($eYear > 2400) {
            $eYear -= 543; // แปลง พ.ศ. เป็น ค.ศ.
        }
        $end_date = sprintf('%04d-%02d-%02d', $eYear, $eMonth, $eDay);
    } else {
        $end_date = trim($_POST['end_date'] ?? '');
    }

    // เวลากลับถึง (ระบบ 24 ชั่วโมง)
    if (isset($_POST['end_hour']) && isset($_POST['end_minute'])) {
        $end_time = sprintf('%02d:%02d', (int)$_POST['end_hour'], (int)$_POST['end_minute']);
    } else {
        $end_time = trim($_POST['end_time'] ?? '');
    }
    $passenger_count = (int)($_POST['passenger_count'] ?? 1);
    $passenger_names = trim($_POST['passenger_names'] ?? '');
    $controller_name = trim($_POST['controller_name'] ?? '');

    $start_datetime = "$start_date $start_time:00";
    $end_datetime = "$end_date $end_time:00";

    // ระบบป้องกันคำขอเท็จและสแปม (Anti-Fraud & Anti-Spam Verification)
    $botTrap = trim($_POST['pnu_verification_trap'] ?? '');
    $securityAns = isset($_POST['security_challenge']) ? (int)$_POST['security_challenge'] : null;
    $expectedAns = (int)($_SESSION['captcha_ans'] ?? -999);
    $declarationConfirmed = !empty($_POST['declaration_confirmed']);

    $clientIP = getClientIP();
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

    // 1. ตรวจสอบ Honeypot Trap (บอทแอบกรอก)
    if (!empty($botTrap)) {
        $error = 'ตรวจพบความผิดปกติในการส่งข้อมูล ระบบขอระงับคำขอนี้เพื่อความปลอดภัย';
    }
    // 2. ตรวจสอบคำถามความปลอดภัย (Math Challenge)
    elseif ($securityAns === null || $securityAns !== $expectedAns) {
        $error = 'รหัสความปลอดภัย (คำถามป้องกันสแปม) ไม่ถูกต้อง กรุณากรอกคำตอบตัวเลขให้ถูกต้อง';
    }
    // 3. ตรวจสอบการรับรองข้อมูลจริง
    elseif (!$declarationConfirmed) {
        $error = 'กรุณาติ๊กรับรองว่าข้อมูลทั้งหมดเป็นความจริงตามระเบียบของทางราชการ';
    }
    else {
        // ตรวจสอบ Rate Limit ป้องกันการยิงคำขอสแปมซ้ำซากจาก IP เดียวกัน
        $recentStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE client_ip = ? AND created_at >= datetime('now', '-5 minutes')");
        $recentStmt->execute([$clientIP]);
        if ((int)$recentStmt->fetchColumn() >= 6) {
            $error = 'ตรวจพบการส่งคำขอถี่ผิดปกติจากอุปกรณ์ของท่าน กรุณารอประมาณ 5 นาทีก่อนทำรายการใหม่';
        }
    }

    // ตรวจสอบความถูกต้องของข้อมูลทั่วไป
    if (empty($error)) {
        if (empty($requester_name) || empty($requester_position) || empty($requester_department) ||
            empty($vehicle_id) || empty($purpose) || empty($route_from) || empty($route_to) ||
            empty($start_date) || empty($start_time) || empty($end_date) || empty($end_time) || empty($controller_name)) {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่องที่มีเครื่องหมายดอกจัน (*)';
        } elseif (strtotime($end_datetime) <= strtotime($start_datetime)) {
            $error = 'วัน-เวลาสิ้นสุดการเดินทาง ต้องอยู่หลังจากวัน-เวลาเริ่มต้น';
        } else {
            // ตรวจสอบเงื่อนไขล่วงหน้า 1 วัน
            $diffHours = (strtotime($start_datetime) - time()) / 3600;
            if ($diffHours < 24) {
                // แจ้งเตือนแต่ถ้าต้องการอนุโลมก็ให้ผ่านได้พร้อมบันทึกหมายเหตุ
            }

            // ตรวจสอบคิวรถชนกันหรือไม่ (Overlap Check)
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE vehicle_id = ? 
                  AND status NOT IN ('rejected', 'cancelled', 'rejected_fraud')
                  AND (
                      (start_datetime <= ? AND end_datetime > ?) OR
                      (start_datetime < ? AND end_datetime >= ?) OR
                      (start_datetime >= ? AND end_datetime <= ?)
                  )
            ");
            $checkStmt->execute([
                $vehicle_id,
                $start_datetime, $start_datetime,
                $end_datetime, $end_datetime,
                $start_datetime, $end_datetime
            ]);
            $isConflict = $checkStmt->fetchColumn();

            if ($isConflict > 0) {
                $error = 'รถยนต์หมายเลขทะเบียนที่เลือก มีการจองใช้งานในช่วงวันและเวลาดังกล่าวแล้ว กรุณาเลือกรถคันอื่นหรือเปลี่ยนช่วงเวลา';
            } else {
                // ดึงข้อมูลรถ
                $vehStmt = $pdo->prepare("SELECT plate_number FROM vehicles WHERE id = ?");
                $vehStmt->execute([$vehicle_id]);
                $veh = $vehStmt->fetch();
                $plate_number = $veh['plate_number'] ?? '';

                // รันเลขที่เอกสาร เช่น ควจ. 003/2567
                $currentYearThai = date('Y') + 543;
                $countThisYear = $pdo->query("SELECT COUNT(*) FROM bookings WHERE strftime('%Y', created_date) = '" . date('Y') . "'")->fetchColumn();
                $docNo = sprintf("ควจ. %03d/%d", $countThisYear + 1, $currentYearThai);

                // บันทึกคำขอพร้อมข้อมูลความปลอดภัย (IP Address, User Agent)
                $insertBooking = $pdo->prepare("
                    INSERT INTO bookings (
                        doc_no, created_date, user_id, requester_name, requester_position, requester_department,
                        vehicle_id, plate_number, purpose, route_from, route_to, start_datetime, end_datetime,
                        passenger_count, passenger_names, controller_name, status,
                        client_ip, user_agent, is_flagged_fake
                    ) VALUES (
                        ?, date('now'), ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, 'pending_facility',
                        ?, ?, 0
                    )
                ");
                $insertBooking->execute([
                    $docNo,
                    $currentUser['id'] ?? null,
                    $requester_name,
                    $requester_position,
                    $requester_department,
                    $vehicle_id,
                    $plate_number,
                    $purpose,
                    $route_from,
                    $route_to,
                    $start_datetime,
                    $end_datetime,
                    $passenger_count,
                    $passenger_names,
                    $controller_name,
                    $clientIP,
                    $userAgent
                ]);
                $newBookingId = $pdo->lastInsertId();

                // รีเฟรชคำถามความปลอดภัยข้อใหม่
                $num1 = rand(2, 9);
                $num2 = rand(1, 9);
                $_SESSION['captcha_q'] = "$num1 + $num2 = ?";
                $_SESSION['captcha_ans'] = $num1 + $num2;

                // สร้างแถวในตาราง approvals
                $pdo->prepare("INSERT INTO approvals (booking_id) VALUES (?)")->execute([$newBookingId]);

                header("Location: booking_detail.php?id=$newBookingId&success=1");
                exit;
            }
        }
    }

    // กรณีมีข้อผิดพลาด สุ่มคำถามความปลอดภัยข้อใหม่
    if (!empty($error)) {
        $num1 = rand(2, 9);
        $num2 = rand(1, 9);
        $_SESSION['captcha_q'] = "$num1 + $num2 = ?";
        $_SESSION['captcha_ans'] = $num1 + $num2;
    }
}

$thaiMonths = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

$tomorrow = strtotime('+1 day');
$currBE = (int)date('Y') + 543;

$defStartDay = isset($_POST['start_day']) ? (int)$_POST['start_day'] : (int)date('j', $tomorrow);
$defStartMonth = isset($_POST['start_month']) ? (int)$_POST['start_month'] : (int)date('n', $tomorrow);
$defStartYear = isset($_POST['start_year']) ? (int)$_POST['start_year'] : ((int)date('Y', $tomorrow) + 543);

if (!empty($_POST['start_date']) && empty($_POST['start_day'])) {
    $st = strtotime($_POST['start_date']);
    if ($st) {
        $defStartDay = (int)date('j', $st);
        $defStartMonth = (int)date('n', $st);
        $defStartYear = (int)date('Y', $st) + 543;
    }
}

$defEndDay = isset($_POST['end_day']) ? (int)$_POST['end_day'] : $defStartDay;
$defEndMonth = isset($_POST['end_month']) ? (int)$_POST['end_month'] : $defStartMonth;
$defEndYear = isset($_POST['end_year']) ? (int)$_POST['end_year'] : $defStartYear;

if (!empty($_POST['end_date']) && empty($_POST['end_day'])) {
    $et = strtotime($_POST['end_date']);
    if ($et) {
        $defEndDay = (int)date('j', $et);
        $defEndMonth = (int)date('n', $et);
        $defEndYear = (int)date('Y', $et) + 543;
    }
}

// จัดการค่าเริ่มต้นเวลา (ระบบ 24 ชั่วโมง)
$defStartHour = '08';
$defStartMinute = '00';
if (isset($_POST['start_hour'])) {
    $defStartHour = sprintf('%02d', (int)$_POST['start_hour']);
} elseif (!empty($_POST['start_time'])) {
    $parts = explode(':', $_POST['start_time']);
    $defStartHour = sprintf('%02d', (int)($parts[0] ?? 8));
    $defStartMinute = sprintf('%02d', (int)($parts[1] ?? 0));
}
if (isset($_POST['start_minute'])) {
    $defStartMinute = sprintf('%02d', (int)$_POST['start_minute']);
}

$defEndHour = '17';
$defEndMinute = '00';
if (isset($_POST['end_hour'])) {
    $defEndHour = sprintf('%02d', (int)$_POST['end_hour']);
} elseif (!empty($_POST['end_time'])) {
    $parts = explode(':', $_POST['end_time']);
    $defEndHour = sprintf('%02d', (int)($parts[0] ?? 17));
    $defEndMinute = sprintf('%02d', (int)($parts[1] ?? 0));
}
if (isset($_POST['end_minute'])) {
    $defEndMinute = sprintf('%02d', (int)$_POST['end_minute']);
}

$minuteBase = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];
$minuteStartOptions = $minuteBase;
if (!in_array($defStartMinute, $minuteStartOptions)) {
    $minuteStartOptions[] = $defStartMinute;
    sort($minuteStartOptions);
}
$minuteEndOptions = $minuteBase;
if (!in_array($defEndMinute, $minuteEndOptions)) {
    $minuteEndOptions[] = $defEndMinute;
    sort($minuteEndOptions);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card card-custom p-4 border-top border-4 border-primary">
            <div class="d-flex align-items-center justify-content-between pb-3 mb-4 border-bottom">
                <div>
                    <h4 class="fw-bold text-dark mb-1">
                        <i class="fas fa-file-signature text-primary me-2"></i>แบบฟอร์มขออนุญาตใช้รถยนต์
                    </h4>
                    <span class="text-muted small">คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์</span>
                </div>
                <span class="badge bg-primary fs-6 px-3 py-2">
                    <i class="fas fa-calendar-check me-1"></i> จองออนไลน์
                </span>
            </div>

            <!-- ข้อความเน้นย้ำตามกรอบเอกสาร -->
            <div class="alert alert-warning d-flex align-items-center mb-4">
                <i class="fas fa-bullhorn fs-3 me-3 text-warning"></i>
                <div>
                    <strong class="text-danger">เงื่อนไขสำคัญ:</strong> 
                    ขออนุญาตให้แล้วเสร็จ <u>ก่อนใช้รถอย่างน้อย 1 วัน</u> เพื่อให้การจัดสรรรถยนต์และพนักงานขับรถเป็นไปด้วยความเรียบร้อย
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="book.php" class="needs-validation" novalidate>
                <!-- ส่วนที่ 1: ข้อมูลผู้ขอใช้รถ -->
                <div class="bg-light p-3 rounded-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-primary mb-0">
                            <i class="fas fa-user-tie me-2"></i>1. ข้อมูลผู้ขออนุญาตใช้รถยนต์
                        </h6>
                        <?php if ($isLoggedIn): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small">
                                <i class="fas fa-check-circle me-1"></i>เข้าสู่ระบบ: <?= htmlspecialchars($currentUser['fullname']) ?> (ดึงข้อมูลให้อัตโนมัติ)
                            </span>
                        <?php else: ?>
                            <a href="login.php?redirect=book.php" class="text-primary small text-decoration-none">
                                <i class="fas fa-sign-in-alt me-1"></i>เข้าสู่ระบบเพื่อกรอกข้อมูลอัตโนมัติ
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">ชื่อ-นามสกุล ผู้ขอใช้รถ <span class="text-danger">*</span></label>
                            <input type="text" name="requester_name" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['requester_name'] ?? $currentUser['fullname'] ?? '') ?>" 
                                   placeholder="ระบุชื่อ-นามสกุลผู้ขอใช้รถ" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ตำแหน่ง <span class="text-danger">*</span></label>
                            <input type="text" name="requester_position" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['requester_position'] ?? $currentUser['position'] ?? '') ?>" 
                                   placeholder="เช่น อาจารย์ประจำสาขาวิชา" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">สาขาวิชา / หน่วยงาน <span class="text-danger">*</span></label>
                            <input type="text" name="requester_department" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['requester_department'] ?? $currentUser['department'] ?? '') ?>" 
                                   placeholder="เช่น สาขาวิชาการจัดการ" required>
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 2: ข้อมูลรถยนต์และวัตถุประสงค์ -->
                <div class="bg-light p-3 rounded-3 mb-4">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="fas fa-van-shuttle me-2"></i>2. ข้อมูลการขอใช้รถยนต์และภารกิจ
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">เลือกรถยนต์ที่ต้องการขอใช้ <span class="text-danger">*</span></label>
                            <select name="vehicle_id" class="form-select" required>
                                <option value="">-- กรุณาเลือกรถยนต์ --</option>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?= $v['id'] ?>" <?= ((isset($_POST['vehicle_id']) && $_POST['vehicle_id'] == $v['id']) || count($vehicles) == 1) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['plate_number']) ?> - <?= htmlspecialchars($v['brand_model']) ?> (<?= $v['seats'] ?> ที่นั่ง)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">จำนวนผู้ร่วมเดินทาง (คน) <span class="text-danger">*</span></label>
                            <input type="number" name="passenger_count" min="1" max="50" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['passenger_count'] ?? '1') ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">เพื่อใช้ในงาน (วัตถุประสงค์) <span class="text-danger">*</span></label>
                            <textarea name="purpose" rows="2" class="form-control" placeholder="ระบุภารกิจหรือวัตถุประสงค์การใช้รถยนต์ เช่น นำนักศึกษาเข้าร่วมการแข่งขันทักษะวิชาการ..." required><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">จากเส้นทาง (จุดเริ่มต้น) <span class="text-danger">*</span></label>
                            <input type="text" name="route_from" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['route_from'] ?? 'คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ถึง (ปลายทาง) <span class="text-danger">*</span></label>
                            <input type="text" name="route_to" class="form-control" placeholder="เช่น มหาวิทยาลัยสงขลานครินทร์ วิทยาเขตหาดใหญ่" 
                                   value="<?= htmlspecialchars($_POST['route_to'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 3: วันและเวลาเดินทาง (วัน เดือน ปี) -->
                <div class="bg-light p-3 rounded-3 mb-4">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="fas fa-calendar-alt me-2"></i>3. กำหนดวันและเวลาเดินทาง (วัน เดือน ปี)
                    </h6>
                    <div class="row g-3">
                        <!-- ขาไป / เริ่มต้น -->
                        <div class="col-lg-8 col-md-12">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-play-circle text-success me-1"></i>ตั้งแต่วันที่ (วัน เดือน ปี) <span class="text-danger">*</span>
                            </label>
                            <div class="row g-2">
                                <div class="col-3">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white px-2 small text-muted">วัน</span>
                                        <select name="start_day" class="form-select fw-semibold" required>
                                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                                <option value="<?= $d ?>" <?= ($defStartDay == $d) ? 'selected' : '' ?>><?= $d ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-5">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white px-2 small text-muted">เดือน</span>
                                        <select name="start_month" class="form-select fw-semibold" required>
                                            <?php foreach ($thaiMonths as $mNum => $mName): ?>
                                                <option value="<?= $mNum ?>" <?= ($defStartMonth == $mNum) ? 'selected' : '' ?>><?= $mName ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white px-2 small text-muted">ปี พ.ศ.</span>
                                        <select name="start_year" class="form-select fw-semibold" required>
                                            <?php for ($y = $currBE; $y <= $currBE + 3; $y++): ?>
                                                <option value="<?= $y ?>" <?= ($defStartYear == $y) ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- เวลาออกเดินทาง (ระบบ 24 ชั่วโมง) -->
                        <div class="col-lg-4 col-md-12">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-clock text-primary me-1"></i>เวลาออกเดินทาง (24 ชม.) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white px-2 small text-muted">เวลา</span>
                                <select name="start_hour" id="start_hour" class="form-select fw-semibold text-center" required>
                                    <?php for ($h = 0; $h < 24; $h++): $hStr = sprintf('%02d', $h); ?>
                                        <option value="<?= $hStr ?>" <?= ($defStartHour === $hStr) ? 'selected' : '' ?>><?= $hStr ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="input-group-text bg-white px-2 fw-bold">:</span>
                                <select name="start_minute" id="start_minute" class="form-select fw-semibold text-center" required>
                                    <?php foreach ($minuteStartOptions as $mStr): ?>
                                        <option value="<?= $mStr ?>" <?= ($defStartMinute === $mStr) ? 'selected' : '' ?>><?= $mStr ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="input-group-text bg-white px-2">น.</span>
                            </div>
                            <input type="hidden" name="start_time" id="start_time" value="<?= "$defStartHour:$defStartMinute" ?>">
                        </div>

                        <!-- ขากลับ / สิ้นสุด -->
                        <div class="col-lg-8 col-md-12">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-flag-checkered text-danger me-1"></i>ถึงวันที่ / วันเดินทางกลับ (วัน เดือน ปี) <span class="text-danger">*</span>
                            </label>
                            <div class="row g-2">
                                <div class="col-3">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white px-2 small text-muted">วัน</span>
                                        <select name="end_day" class="form-select fw-semibold" required>
                                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                                <option value="<?= $d ?>" <?= ($defEndDay == $d) ? 'selected' : '' ?>><?= $d ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-5">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white px-2 small text-muted">เดือน</span>
                                        <select name="end_month" class="form-select fw-semibold" required>
                                            <?php foreach ($thaiMonths as $mNum => $mName): ?>
                                                <option value="<?= $mNum ?>" <?= ($defEndMonth == $mNum) ? 'selected' : '' ?>><?= $mName ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white px-2 small text-muted">ปี พ.ศ.</span>
                                        <select name="end_year" class="form-select fw-semibold" required>
                                            <?php for ($y = $currBE; $y <= $currBE + 3; $y++): ?>
                                                <option value="<?= $y ?>" <?= ($defEndYear == $y) ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- เวลากลับถึง (ระบบ 24 ชั่วโมง) -->
                        <div class="col-lg-4 col-md-12">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-clock text-primary me-1"></i>เวลากลับถึง (24 ชม.) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white px-2 small text-muted">เวลา</span>
                                <select name="end_hour" id="end_hour" class="form-select fw-semibold text-center" required>
                                    <?php for ($h = 0; $h < 24; $h++): $hStr = sprintf('%02d', $h); ?>
                                        <option value="<?= $hStr ?>" <?= ($defEndHour === $hStr) ? 'selected' : '' ?>><?= $hStr ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="input-group-text bg-white px-2 fw-bold">:</span>
                                <select name="end_minute" id="end_minute" class="form-select fw-semibold text-center" required>
                                    <?php foreach ($minuteEndOptions as $mStr): ?>
                                        <option value="<?= $mStr ?>" <?= ($defEndMinute === $mStr) ? 'selected' : '' ?>><?= $mStr ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="input-group-text bg-white px-2">น.</span>
                            </div>
                            <input type="hidden" name="end_time" id="end_time" value="<?= "$defEndHour:$defEndMinute" ?>">
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 4: ผู้ควบคุมรถและผู้ร่วมเดินทาง -->
                <div class="bg-light p-3 rounded-3 mb-4">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="fas fa-users-cog me-2"></i>4. ผู้ควบคุมการใช้รถยนต์และผู้ร่วมเดินทาง
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ผู้ควบคุมการใช้รถยนต์ (มอบหมายให้) <span class="text-danger">*</span></label>
                            <input type="text" name="controller_name" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['controller_name'] ?? $currentUser['fullname'] ?? '') ?>" 
                                   placeholder="ระบุชื่อผู้ควบคุมการใช้รถ" required>
                            <small class="text-muted">
                                * เป็นผู้ควบคุมการใช้รถยนต์ และรับผิดชอบหากมีความเสียหายเกิดขึ้นทุกประการในการขออนุญาตใช้รถในครั้งนี้
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">รายชื่อผู้ร่วมเดินทาง (ระบุรายชื่อหรือกลุ่ม)</label>
                            <textarea name="passenger_names" rows="3" class="form-control" 
                                      placeholder="เช่น 1. นาย ก... 2. นางสาว ข..."><?= htmlspecialchars($_POST['passenger_names'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 5: การรับรองข้อมูลและระบบป้องกันคำขอเท็จ (Anti-Spam / Anti-Fraud) -->
                <div class="card border-0 bg-white shadow-sm p-4 mb-4 rounded-3 border-start border-4 border-warning">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="fas fa-shield-halved text-warning me-2"></i>5. การรับรองข้อมูลและระบบป้องกันคำขอเท็จ (Anti-Spam)
                    </h6>

                    <!-- Honeypot Trap (บอทแอบกรอกแต่มนุษย์ไม่เห็น) -->
                    <div style="display: none !important; opacity: 0; position: absolute; left: -9999px;">
                        <label>อย่ากรอกข้อมูลในช่องนี้</label>
                        <input type="text" name="pnu_verification_trap" value="" autocomplete="off" tabindex="-1">
                    </div>

                    <!-- ข้อความรับรองตามระเบียบทางราชการ -->
                    <div class="form-check p-3 bg-light rounded-3 mb-3 border">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="declaration_confirmed" id="declaration_confirmed" value="1" required checked>
                        <label class="form-check-label text-dark fw-semibold" for="declaration_confirmed" style="font-size: 0.95rem; line-height: 1.6;">
                            ข้าพเจ้าขอรับรองว่าข้อความและข้อมูลข้างต้นเป็นความจริงทุกประการ และมีความจำเป็นต้องใช้รถยนต์เพื่อปฏิบัติภารกิจของทางราชการจริง หากตรวจพบว่าเป็นข้อมูลเท็จ ข้าพเจ้ายินยอมให้ยกเลิกคำขอทันทีและรับผิดชอบตามระเบียบของทางราชการ
                        </label>
                    </div>

                    <!-- รหัสความปลอดภัย (Security Math Challenge) -->
                    <div class="row align-items-center g-3 bg-light p-3 rounded-3 border">
                        <div class="col-md-auto col-12">
                            <span class="badge bg-primary fs-6 px-3 py-2">
                                <i class="fas fa-calculator me-1"></i> คำถามป้องกันสแปม: <strong><?= htmlspecialchars($_SESSION['captcha_q'] ?? '5 + 3 = ?') ?></strong>
                            </span>
                        </div>
                        <div class="col-md-3 col-6">
                            <input type="number" name="security_challenge" id="security_challenge" class="form-control fw-bold text-center form-control-lg border-primary" 
                                   placeholder="ใส่ผลลัพธ์ตัวเลข" required autocomplete="off">
                        </div>
                        <div class="col-12 mt-2">
                            <small class="text-muted">
                                <i class="fas fa-lock text-success me-1"></i>ระบบบันทึก IP Address (<?= htmlspecialchars(getClientIP()) ?>) เพื่อความปลอดภัยและป้องกันการส่งคำขอเท็จ
                            </small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="index.php" class="btn btn-outline-secondary px-4">
                        <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
                    </a>
                    <button type="submit" id="btnSubmitBooking" class="btn btn-pnu px-5 py-2 fs-6">
                        <i class="fas fa-paper-plane me-2"></i> ส่งคำขออนุญาตใช้รถยนต์
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function syncTimes() {
        const sh = document.getElementById('start_hour');
        const sm = document.getElementById('start_minute');
        const st = document.getElementById('start_time');
        if (sh && sm && st) {
            st.value = sh.value + ':' + sm.value;
        }

        const eh = document.getElementById('end_hour');
        const em = document.getElementById('end_minute');
        const et = document.getElementById('end_time');
        if (eh && em && et) {
            et.value = eh.value + ':' + em.value;
        }
    }

    ['start_hour', 'start_minute', 'end_hour', 'end_minute'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', syncTimes);
        }
    });

    syncTimes();

    // ป้องกันการกดยื่นซ้ำ (Anti Double-Submission)
    const form = document.querySelector('form');
    const submitBtn = document.getElementById('btnSubmitBooking');
    if (form && submitBtn) {
        form.addEventListener('submit', function() {
            if (form.checkValidity()) {
                setTimeout(function() {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> กำลังตรวจสอบและบันทึกคำขอ...';
                }, 10);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
