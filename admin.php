<?php
// admin.php - หน้าควบคุมระบบสำหรับผู้ดูแลระบบ (Admin Dashboard)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$alertMsg = '';
$alertType = 'info';

// ประมวลผลคำสั่งของ Admin
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. เปลี่ยนสถานะคำขอด่วน (Admin Override)
    if ($action === 'change_booking_status') {
        $bookingId = (int)$_POST['booking_id'];
        $newStatus = $_POST['new_status'];
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $bookingId]);

        // หากตั้งเป็น approved หรือ completed ให้อัปเดตสถานะใน approvals ด้วย
        if ($newStatus === 'approved' || $newStatus === 'completed') {
            $pdo->prepare("
                UPDATE approvals SET 
                    facility_status = COALESCE(facility_status, 'approved'),
                    facility_signer = COALESCE(facility_signer, 'นายเอกสิทธิ์ คงพิทักษ์'),
                    facility_fuel = COALESCE(facility_fuel, 1),
                    facility_allowance = COALESCE(facility_allowance, 1),
                    office_driver_assigned = COALESCE(office_driver_assigned, 'นายธเนศ อินเอิบ'),
                    facility_signed_at = COALESCE(facility_signed_at, datetime('now'))
                WHERE booking_id = ?
            ")->execute([$bookingId]);
        }

        // หากตั้งเป็น rejected_fraud (ข้อมูลเท็จ/สแปม)
        if ($newStatus === 'rejected_fraud') {
            $pdo->prepare("UPDATE bookings SET is_flagged_fake = 1, fake_reason = 'ผู้ดูแลระบบระบุว่าเป็นข้อมูลเท็จ' WHERE id = ?")->execute([$bookingId]);
            $pdo->prepare("UPDATE approvals SET facility_status = 'rejected', facility_comment = 'ผู้ดูแลระบบระบุว่าเป็นข้อมูลเท็จ' WHERE booking_id = ?")->execute([$bookingId]);
        } elseif ($newStatus === 'pending_facility') {
            $pdo->prepare("UPDATE bookings SET is_flagged_fake = 0, fake_reason = NULL WHERE id = ?")->execute([$bookingId]);
        }

        $alertMsg = "ปรับปรุงสถานะคำขอ #$bookingId เป็น '$newStatus' สำเร็จ";
        $alertType = 'success';
    }

    // 2. ลบคำขอ
    if ($action === 'delete_booking') {
        $bookingId = (int)$_POST['booking_id'];
        $pdo->prepare("DELETE FROM approvals WHERE booking_id = ?")->execute([$bookingId]);
        $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$bookingId]);
        $alertMsg = "ลบคำขอ #$bookingId เรียบร้อยแล้ว";
        $alertType = 'success';
    }

    // 3. เพิ่มผู้ใช้ใหม่
    if ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '123456');
        $prefix = trim($_POST['prefix'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $role = trim($_POST['role'] ?? 'requester');
        $phone = trim($_POST['phone'] ?? '');

        if (!empty($username) && !empty($fullname)) {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, plain_password, prefix, fullname, position, department, role, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $password, $prefix, $fullname, $position, $department, $role, $phone]);
                $alertMsg = "เพิ่มผู้ใช้งาน '$fullname' เรียบร้อยแล้ว";
                $alertType = 'success';
            } catch (PDOException $e) {
                $alertMsg = "ข้อผิดพลาด: ชื่อผู้ใช้ (Username) นี้มีอยู่ในระบบแล้ว";
                $alertType = 'danger';
            }
        }
    }

    // 4. แก้ไขข้อมูลผู้ใช้งานและรหัสผ่าน
    if ($action === 'edit_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $prefix = trim($_POST['prefix'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $role = trim($_POST['role'] ?? 'requester');
        $phone = trim($_POST['phone'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');

        if ($userId > 0 && !empty($username) && !empty($fullname)) {
            try {
                if (!empty($newPassword)) {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, prefix = ?, fullname = ?, position = ?, department = ?, role = ?, phone = ?, password = ?, plain_password = ? WHERE id = ?");
                    $stmt->execute([$username, $prefix, $fullname, $position, $department, $role, $phone, $hash, $newPassword, $userId]);
                    $alertMsg = "แก้ไขข้อมูลและเปลี่ยนรหัสผ่านของผู้ใช้ '{$fullname}' เป็น '{$newPassword}' สำเร็จ";
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, prefix = ?, fullname = ?, position = ?, department = ?, role = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$username, $prefix, $fullname, $position, $department, $role, $phone, $userId]);
                    $alertMsg = "บันทึกการแก้ไขข้อมูลผู้ใช้ '{$fullname}' สำเร็จ";
                }
                $alertType = 'success';
            } catch (PDOException $e) {
                $alertMsg = "ข้อผิดพลาด: ไม่สามารถแก้ไขได้เนื่องจาก Username ซ้ำกับบัญชีอื่น";
                $alertType = 'danger';
            }
        }
    }

    // 5. ลบผู้ใช้งาน
    if ($action === 'delete_user') {
        $userId = (int)$_POST['user_id'];
        if ($userId != $currentUser['id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            $alertMsg = "ลบผู้ใช้งานเรียบร้อยแล้ว";
            $alertType = 'success';
        } else {
            $alertMsg = "ไม่สามารถลบบัญชีที่กำลังเข้าสู่ระบบอยู่ได้";
            $alertType = 'danger';
        }
    }

    // 6. รีเซ็ตรหัสผ่านผู้ใช้
    if ($action === 'reset_password') {
        $userId = (int)$_POST['user_id'];
        $newPass = trim($_POST['new_password'] ?? '123456');
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ?, plain_password = ? WHERE id = ?")->execute([$hash, $newPass, $userId]);
        $alertMsg = "รีเซ็ตรหัสผ่านของผู้ใช้เป็น '$newPass' เรียบร้อยแล้ว";
        $alertType = 'success';
    }
}

// สถิติสำหรับ Admin
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'pending_facility' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending_facility'")->fetchColumn(),
    'pending_office' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending_office'")->fetchColumn(),
    'pending_dean' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending_dean'")->fetchColumn(),
    'pending_driver' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending_driver'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('approved', 'completed', 'pending_office', 'pending_dean', 'pending_driver')")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed'")->fetchColumn(),
    'rejected' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'rejected'")->fetchColumn(),
    'cancelled' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn(),
    'total_vehicles' => $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn(),
    'active_vehicles' => $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'active'")->fetchColumn(),
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'fuel_support_count' => $pdo->query("SELECT COUNT(*) FROM approvals WHERE facility_fuel = 1")->fetchColumn(),
    'allowance_support_count' => $pdo->query("SELECT COUNT(*) FROM approvals WHERE facility_allowance = 1")->fetchColumn(),
];

// ดึงคำขอทั้งหมด
$allBookings = $pdo->query("
    SELECT b.*, v.brand_model, v.vehicle_type 
    FROM bookings b 
    JOIN vehicles v ON b.vehicle_id = v.id 
    ORDER BY b.id DESC
")->fetchAll();

// ดึงรถยนต์ทั้งหมด
$allVehicles = $pdo->query("SELECT * FROM vehicles ORDER BY id ASC")->fetchAll();

// ดึงผู้ใช้งานทั้งหมด
$allUsersList = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();

// สถิติการใช้รถแต่ละคัน
$vehicleUsage = $pdo->query("
    SELECT v.plate_number, v.brand_model, COUNT(b.id) as total_trips 
    FROM vehicles v 
    LEFT JOIN bookings b ON v.id = b.vehicle_id 
    GROUP BY v.id 
    ORDER BY total_trips DESC
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-shield-halved text-primary me-2"></i>แผงควบคุมระบบ (Administrator Panel) <span class="badge bg-secondary fs-6 fw-normal ms-1">v<?= APP_VERSION ?></span>
        </h3>
        <span class="text-muted">
            คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์ | จัดการคำขอ, ยานพาหนะ, ผู้ใช้งาน และรายงานสถิติ
        </span>
    </div>
    <div class="d-flex gap-2">
        <a href="print_form.php?id=<?= $allBookings[0]['id'] ?? 1 ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-invoice me-1"></i> ตัวอย่างเอกสาร A4
        </a>
        <a href="book.php" class="btn btn-pnu btn-sm">
            <i class="fas fa-plus me-1"></i> จองรถใหม่
        </a>
    </div>
</div>

<?php if ($alertMsg): ?>
<div class="alert alert-<?= $alertType ?> alert-dismissible fade show" role="alert">
    <i class="fas fa-info-circle me-2"></i> <?= htmlspecialchars($alertMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- การ์ดสรุปสถิติ 4 ใบ -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom p-3 border-start border-4 border-primary">
            <div class="text-muted small">คำขอทั้งหมด</div>
            <div class="fs-3 fw-bold text-primary"><?= $stats['total'] ?></div>
            <div class="small text-muted mt-1"><i class="fas fa-list me-1"></i>รายการคำขอในระบบ</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom p-3 border-start border-4 border-warning">
            <div class="text-muted small">รอหัวหน้าอาคารสถานที่พิจารณา</div>
            <div class="fs-3 fw-bold text-warning">
                <?= $stats['pending_facility'] ?>
            </div>
            <div class="small text-muted mt-1"><i class="fas fa-clock me-1"></i>ขั้นตอนเดียวในระบบ</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom p-3 border-start border-4 border-success">
            <div class="text-muted small">เห็นชอบแล้ว (พร้อมพิมพ์)</div>
            <div class="fs-3 fw-bold text-success"><?= $stats['approved'] ?></div>
            <div class="small text-muted mt-1"><i class="fas fa-print me-1"></i>พิมพ์เสนอลงนามต่อ</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom p-3 border-start border-4 border-info">
            <div class="text-muted small">ยานพาหนะส่วนกลาง</div>
            <div class="fs-3 fw-bold text-info"><?= $stats['active_vehicles'] ?> / <?= $stats['total_vehicles'] ?> คัน</div>
            <div class="small text-muted mt-1"><i class="fas fa-circle text-success me-1"></i>พร้อมบริการ: <?= $stats['active_vehicles'] ?> คัน</div>
        </div>
    </div>
</div>

<!-- แท็บการจัดการของ Admin -->
<div class="card card-custom">
    <div class="card-header bg-white border-bottom p-3">
        <ul class="nav nav-tabs card-header-tabs" id="adminTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-semibold" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#tab-bookings" type="button" role="tab">
                    <i class="fas fa-list-check text-primary me-2"></i>จัดการคำขอใช้รถยนต์ (<?= count($allBookings) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold" id="users-tab" data-bs-toggle="tab" data-bs-target="#tab-users" type="button" role="tab">
                    <i class="fas fa-users text-success me-2"></i>จัดการผู้ใช้งานและผู้ลงนาม (<?= count($allUsersList) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold" id="fleet-tab" data-bs-toggle="tab" data-bs-target="#tab-fleet" type="button" role="tab">
                    <i class="fas fa-van-shuttle text-info me-2"></i>จัดการยานพาหนะ (<?= count($allVehicles) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold" id="stats-tab" data-bs-toggle="tab" data-bs-target="#tab-stats" type="button" role="tab">
                    <i class="fas fa-chart-pie text-warning me-2"></i>รายงานและสถิติการใช้งาน
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-4">
        <div class="tab-content" id="adminTabsContent">
            
            <!-- TAB 1: จัดการคำขอทั้งหมด -->
            <div class="tab-pane fade show active" id="tab-bookings" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0">รายการคำขอทั้งหมดในระบบ</h5>
                    <div class="text-muted small">แอดมินสามารถเปลี่ยนสถานะด่วนหรือลบรายการทดสอบได้</div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>เลขที่</th>
                                <th>ผู้ขอใช้รถ</th>
                                <th>รถยนต์</th>
                                <th>วัตถุประสงค์ / ปลายทาง</th>
                                <th>วันเดินทาง</th>
                                <th>สถานะปัจจุบัน</th>
                                <th class="text-center" style="min-width: 220px;">ดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allBookings as $b): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($b['doc_no'] ?? '-') ?></span>
                                    <div class="small text-muted">ID: #<?= $b['id'] ?></div>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($b['requester_name']) ?></strong>
                                    <div class="small text-muted"><?= htmlspecialchars($b['requester_department']) ?></div>
                                </td>
                                <td>
                                    <strong class="text-primary"><?= htmlspecialchars($b['plate_number']) ?></strong>
                                    <div class="small text-muted"><?= htmlspecialchars($b['brand_model']) ?></div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($b['purpose']) ?>">
                                        <?= htmlspecialchars($b['purpose']) ?>
                                    </div>
                                    <small class="text-danger"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($b['route_to']) ?></small>
                                </td>
                                <td>
                                    <small>
                                        <div><strong>ไป:</strong> <?= thaiDateShort($b['start_datetime']) ?></div>
                                        <div><strong>กลับ:</strong> <?= thaiDateShort($b['end_datetime']) ?></div>
                                    </small>
                                </td>
                                <td><?= getStatusBadge($b['status']) ?></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-outline-primary btn-sm" title="เปิดดูรายละเอียด">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="print_form.php?id=<?= $b['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="พิมพ์ A4">
                                            <i class="fas fa-print"></i>
                                        </a>

                                        <!-- ปุ่มสลับสถานะด่วนสำหรับแอดมิน -->
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                ปรับสถานะ
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><h6 class="dropdown-header">เลือกสถานะใหม่</h6></li>
                                                <li>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="change_booking_status">
                                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                        <input type="hidden" name="new_status" value="approved">
                                                        <button type="submit" class="dropdown-item text-success"><i class="fas fa-check-circle me-1"></i> เห็นชอบ (พร้อมพิมพ์เสนอต่อ)</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="change_booking_status">
                                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                        <input type="hidden" name="new_status" value="pending_facility">
                                                        <button type="submit" class="dropdown-item"><i class="fas fa-undo me-1"></i> รอหัวหน้าอาคารสถานที่พิจารณา</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="change_booking_status">
                                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                        <input type="hidden" name="new_status" value="rejected">
                                                        <button type="submit" class="dropdown-item text-danger"><i class="fas fa-times-circle me-1"></i> ไม่เห็นชอบ / ไม่อนุมัติ</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="change_booking_status">
                                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                        <input type="hidden" name="new_status" value="rejected_fraud">
                                                        <button type="submit" class="dropdown-item text-danger fw-semibold"><i class="fas fa-shield-virus me-1"></i> ปฏิเสธ (ข้อมูลเท็จ / สแปม)</button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" onsubmit="return confirm('คุณแน่ใจว่าต้องการลบคำขอนี้ถาวร?');">
                                                        <input type="hidden" name="action" value="delete_booking">
                                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                        <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash me-1"></i> ลบคำขอนี้</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: จัดการผู้ใช้งาน -->
            <div class="tab-pane fade" id="tab-users" role="tabpanel">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">ผู้ใช้งานและรหัสผ่าน (User Accounts & Passwords)</h5>
                        <small class="text-muted">ตรวจสอบรหัสผ่าน แก้ไขข้อมูล หรือเพิ่มผู้ใช้ใหม่</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="users.php" class="btn btn-pnu btn-sm">
                            <i class="fas fa-users-gear me-1"></i> ไปที่หน้าจัดการผู้ใช้ & รหัสผ่านแบบเต็ม
                        </a>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-user-plus me-1"></i> เพิ่มผู้ใช้งานใหม่
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>ชื่อผู้ใช้ (Username)</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>รหัสผ่าน (Password)</th>
                                <th>ตำแหน่ง</th>
                                <th>สาขาวิชา / หน่วยงาน</th>
                                <th>บทบาท (Role)</th>
                                <th>เบอร์โทร</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsersList as $u): 
                                $dispPass = !empty($u['plain_password']) ? $u['plain_password'] : '123456';
                            ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><code class="text-primary fw-bold"><?= htmlspecialchars($u['username']) ?></code></td>
                                <td>
                                    <strong><?= htmlspecialchars($u['prefix'] . ' ' . $u['fullname']) ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-light text-danger border px-2 py-1 font-monospace fw-bold">
                                            <?= htmlspecialchars($dispPass) ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($u['position']) ?></td>
                                <td><?= htmlspecialchars($u['department']) ?></td>
                                <td>
                                    <?php
                                    $roleBadges = [
                                        'admin' => '<span class="badge bg-danger">แอดมิน (Admin)</span>',
                                        'requester' => '<span class="badge bg-secondary">ผู้ขอใช้รถ</span>',
                                        'facility_head' => '<span class="badge bg-warning text-dark">หัวหน้างานอาคารสถานที่</span>',
                                        'office_head' => '<span class="badge bg-info text-dark">หัวหน้าสำนักงานคณบดี</span>',
                                        'dean' => '<span class="badge bg-primary">คณบดี</span>',
                                        'driver' => '<span class="badge bg-dark">พนักงานขับรถ</span>'
                                    ];
                                    echo $roleBadges[$u['role']] ?? '<span class="badge bg-light text-dark">' . $u['role'] . '</span>';
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($u['phone'] ?: '-') ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModalAdmin<?= $u['id'] ?>" title="แก้ไขข้อมูล / เปลี่ยนรหัสผ่าน">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($u['id'] != $currentUser['id']): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('ต้องการลบผู้ใช้นี้หรือไม่?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="ลบผู้ใช้">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- Modal แก้ไขข้อมูลผู้ใช้และรหัสผ่าน -->
                            <div class="modal fade" id="editUserModalAdmin<?= $u['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="POST" class="modal-content">
                                        <input type="hidden" name="action" value="edit_user">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <div class="modal-header bg-primary text-white">
                                            <h6 class="modal-title fw-bold">แก้ไขผู้ใช้: <?= htmlspecialchars($u['fullname']) ?></h6>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-2 mb-3">
                                                <div class="col-4">
                                                    <label class="form-label small fw-semibold">คำนำหน้า</label>
                                                    <input type="text" name="prefix" class="form-control form-control-sm" value="<?= htmlspecialchars($u['prefix']) ?>">
                                                </div>
                                                <div class="col-8">
                                                    <label class="form-label small fw-semibold">ชื่อ-นามสกุล</label>
                                                    <input type="text" name="fullname" class="form-control form-control-sm" value="<?= htmlspecialchars($u['fullname']) ?>" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">ชื่อผู้ใช้ล็อกอิน (Username)</label>
                                                <input type="text" name="username" class="form-control form-control-sm" value="<?= htmlspecialchars($u['username']) ?>" required>
                                            </div>
                                            <div class="mb-3 bg-warning-subtle p-2 rounded border border-warning">
                                                <label class="form-label small fw-bold text-dark mb-1">รหัสผ่าน (Password)</label>
                                                <input type="text" name="new_password" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($dispPass) ?>" required>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold">บทบาท (Role)</label>
                                                    <select name="role" class="form-select form-select-sm">
                                                        <option value="requester" <?= ($u['role'] == 'requester') ? 'selected' : '' ?>>ผู้ขอใช้รถ</option>
                                                        <option value="driver" <?= ($u['role'] == 'driver') ? 'selected' : '' ?>>พนักงานขับรถ</option>
                                                        <option value="office_head" <?= ($u['role'] == 'office_head') ? 'selected' : '' ?>>หัวหน้าสำนักงานคณบดี</option>
                                                        <option value="dean" <?= ($u['role'] == 'dean') ? 'selected' : '' ?>>คณบดี</option>
                                                        <option value="facility_head" <?= ($u['role'] == 'facility_head') ? 'selected' : '' ?>>หัวหน้างานอาคารสถานที่</option>
                                                        <option value="admin" <?= ($u['role'] == 'admin') ? 'selected' : '' ?>>แอดมิน (Admin)</option>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold">เบอร์โทรศัพท์</label>
                                                    <input type="text" name="phone" class="form-control form-control-sm" value="<?= htmlspecialchars($u['phone']) ?>">
                                                </div>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold">ตำแหน่ง</label>
                                                    <input type="text" name="position" class="form-control form-control-sm" value="<?= htmlspecialchars($u['position']) ?>">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold">สาขาวิชา / หน่วยงาน</label>
                                                    <input type="text" name="department" class="form-control form-control-sm" value="<?= htmlspecialchars($u['department']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                                            <button type="submit" class="btn btn-primary btn-sm">บันทึกการแก้ไข</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: จัดการยานพาหนะ -->
            <div class="tab-pane fade" id="tab-fleet" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0">ข้อมูลรถยนต์ส่วนกลางของคณะ</h5>
                    <a href="vehicles.php" class="btn btn-pnu btn-sm">
                        <i class="fas fa-cog me-1"></i> ไปที่หน้าจัดการยานพาหนะแบบเต็ม
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>ทะเบียนรถ</th>
                                <th>ยี่ห้อ / รุ่น</th>
                                <th>ประเภท</th>
                                <th>ที่นั่ง</th>
                                <th>สถานะ</th>
                                <th>หมายเหตุ</th>
                                <th class="text-center">สลับสถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allVehicles as $v): ?>
                            <tr>
                                <td><?= $v['id'] ?></td>
                                <td><strong class="text-primary"><?= htmlspecialchars($v['plate_number']) ?></strong></td>
                                <td><?= htmlspecialchars($v['brand_model']) ?></td>
                                <td><?= htmlspecialchars($v['vehicle_type']) ?></td>
                                <td><?= $v['seats'] ?> ที่นั่ง</td>
                                <td>
                                    <span class="badge <?= ($v['status'] == 'active') ? 'bg-success' : 'bg-danger' ?>">
                                        <?= ($v['status'] == 'active') ? 'พร้อมใช้งาน' : 'ซ่อมบำรุง' ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($v['notes'] ?: '-') ?></small></td>
                                <td class="text-center">
                                    <a href="vehicles.php?toggle_id=<?= $v['id'] ?>" class="btn btn-sm <?= ($v['status'] == 'active') ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                        <i class="fas fa-sync-alt me-1"></i> สลับสถานะ
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 4: รายงานและสถิติ -->
            <div class="tab-pane fade" id="tab-stats" role="tabpanel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="fas fa-tachometer-alt text-primary me-2"></i>สถิติความถี่การใช้งานของรถยนต์แต่ละคัน
                            </h6>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($vehicleUsage as $vu): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                                    <div>
                                        <strong><?= htmlspecialchars($vu['plate_number']) ?></strong>
                                        <span class="text-muted small ms-2">(<?= htmlspecialchars($vu['brand_model']) ?>)</span>
                                    </div>
                                    <span class="badge bg-primary rounded-pill fs-6"><?= $vu['total_trips'] ?> ครั้ง</span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="fas fa-gas-pump text-danger me-2"></i>สรุปรายการสนับสนุนงบประมาณ
                            </h6>
                            <div class="p-3 bg-white rounded-3 shadow-sm mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">คำขอที่สนับสนุนค่าน้ำมันเชื้อเพลิง</div>
                                        <div class="fs-4 fw-bold text-primary"><?= $stats['fuel_support_count'] ?> รายการ</div>
                                    </div>
                                    <i class="fas fa-oil-can text-warning fs-2"></i>
                                </div>
                            </div>
                            <div class="p-3 bg-white rounded-3 shadow-sm">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">คำขอที่สนับสนุนเบี้ยเลี้ยง/ค่าตอบแทน</div>
                                        <div class="fs-4 fw-bold text-success"><?= $stats['allowance_support_count'] ?> รายการ</div>
                                    </div>
                                    <i class="fas fa-coins text-success fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal เพิ่มผู้ใช้งานใหม่ -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="add_user">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้งานใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">คำนำหน้า</label>
                        <input type="text" name="prefix" class="form-control form-control-sm" placeholder="เช่น อาจารย์ ดร., นาย">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" class="form-control form-control-sm" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">ชื่อผู้ใช้ (Username) <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">รหัสผ่านเริ่มต้น</label>
                        <input type="text" name="password" class="form-control form-control-sm" value="123456">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">ตำแหน่ง</label>
                        <input type="text" name="position" class="form-control form-control-sm" placeholder="เช่น อาจารย์ประจำสาขาวิชา">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">สาขาวิชา / หน่วยงาน</label>
                        <input type="text" name="department" class="form-control form-control-sm" placeholder="เช่น สาขาวิชาการจัดการ">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">สิทธิ์การใช้งาน (Role)</label>
                        <select name="role" class="form-select form-select-sm">
                            <option value="requester">ผู้ขอใช้รถ (Requester)</option>
                            <option value="facility_head">หัวหน้างานอาคารสถานที่ (ลำดับ 1)</option>
                            <option value="office_head">หัวหน้าสำนักงานคณบดี (ลำดับ 2)</option>
                            <option value="dean">คณบดี (ลำดับ 3)</option>
                            <option value="driver">พนักงานขับรถยนต์ (ลำดับ 4)</option>
                            <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" class="form-control form-control-sm" placeholder="08x-xxx-xxxx">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary btn-sm">บันทึกผู้ใช้</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
