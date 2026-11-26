<?php
// users.php - หน้าจัดการผู้ใช้งานและรหัสผ่านสำหรับ Admin
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$alertMsg = $_SESSION['user_alert_msg'] ?? '';
$alertType = $_SESSION['user_alert_type'] ?? 'info';
unset($_SESSION['user_alert_msg'], $_SESSION['user_alert_type']);

// ประมวลผลคำขอจาก Admin
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. เพิ่มผู้ใช้งานใหม่
    if ($action === 'add_user') {
        $prefix = trim($_POST['prefix'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '123456');
        $position = trim($_POST['position'] ?? 'อาจารย์ประจำสาขาวิชา');
        $department = trim($_POST['department'] ?? 'คณะวิทยาการจัดการ');
        $role = trim($_POST['role'] ?? 'requester');
        $phone = trim($_POST['phone'] ?? '');

        // หากไม่ได้ระบุ username ให้ใช้ชื่อจริง นามสกุล
        if (empty($username)) {
            $username = $fullname;
        }

        if (!empty($username) && !empty($fullname)) {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, plain_password, prefix, fullname, position, department, role, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $password, $prefix, $fullname, $position, $department, $role, $phone]);
                $_SESSION['user_alert_msg'] = "เพิ่มผู้ใช้งาน '{$prefix}{$fullname}' (Username: {$username}) เรียบร้อยแล้ว";
                $_SESSION['user_alert_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['user_alert_msg'] = "ข้อผิดพลาด: ชื่อผู้ใช้ (Username) '{$username}' นี้มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น";
                $_SESSION['user_alert_type'] = 'danger';
            }
        } else {
            $_SESSION['user_alert_msg'] = "กรุณาระบุชื่อ-นามสกุล และชื่อผู้ใช้งาน";
            $_SESSION['user_alert_type'] = 'danger';
        }
        header("Location: users.php");
        exit;
    }

    // 2. แก้ไขข้อมูลผู้ใช้งานและรหัสผ่าน
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
                // ตรวจสอบว่าชื่อผู้ใช้ซ้ำกับบัญชีอื่นหรือไม่
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $checkStmt->execute([$username, $userId]);
                if ($checkStmt->fetchColumn()) {
                    $_SESSION['user_alert_msg'] = "ข้อผิดพลาด: ชื่อผู้ใช้ (Username) '{$username}' นี้มีผู้อื่นใช้งานแล้ว กรุณาใช้ชื่ออื่น";
                    $_SESSION['user_alert_type'] = 'danger';
                } else {
                    if (!empty($newPassword)) {
                        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, prefix = ?, fullname = ?, position = ?, department = ?, role = ?, phone = ?, password = ?, plain_password = ? WHERE id = ?");
                        $stmt->execute([$username, $prefix, $fullname, $position, $department, $role, $phone, $hash, $newPassword, $userId]);
                        $_SESSION['user_alert_msg'] = "บันทึกการแก้ไขข้อมูลและรหัสผ่านของผู้ใช้ '{$prefix}{$fullname}' เรียบร้อยแล้ว";
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, prefix = ?, fullname = ?, position = ?, department = ?, role = ?, phone = ? WHERE id = ?");
                        $stmt->execute([$username, $prefix, $fullname, $position, $department, $role, $phone, $userId]);
                        $_SESSION['user_alert_msg'] = "บันทึกการแก้ไขข้อมูลของผู้ใช้ '{$prefix}{$fullname}' เรียบร้อยแล้ว";
                    }

                    // หากแก้ไขบัญชีของตนเองที่กำลังล็อกอินอยู่ ให้อัปเดต Session
                    if ($userId === ($currentUser['id'] ?? 0)) {
                        $stmtCur = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                        $stmtCur->execute([$userId]);
                        $_SESSION['user'] = $stmtCur->fetch();
                    }

                    $_SESSION['user_alert_type'] = 'success';
                }
            } catch (PDOException $e) {
                $_SESSION['user_alert_msg'] = "ข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
                $_SESSION['user_alert_type'] = 'danger';
            }
        } else {
            $_SESSION['user_alert_msg'] = "กรุณาระบุชื่อ-นามสกุล และชื่อผู้ใช้งาน (Username) ให้ครบถ้วน";
            $_SESSION['user_alert_type'] = 'danger';
        }
        header("Location: users.php");
        exit;
    }

    // 3. รีเซ็ตรหัสผ่านอย่างเดียว
    if ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPass = trim($_POST['new_password'] ?? '123456');
        if ($userId > 0 && !empty($newPass)) {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, plain_password = ? WHERE id = ?");
            $stmt->execute([$hash, $newPass, $userId]);
            $_SESSION['user_alert_msg'] = "เปลี่ยนรหัสผ่านใหม่เป็น '{$newPass}' เรียบร้อยแล้ว";
            $_SESSION['user_alert_type'] = 'success';
        }
        header("Location: users.php");
        exit;
    }

    // 4. ลบผู้ใช้งาน
    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === ($currentUser['id'] ?? 0)) {
            $_SESSION['user_alert_msg'] = "ไม่สามารถลบบัญชีผู้ใช้ที่คุณกำลังเข้าสู่ระบบอยู่ได้";
            $_SESSION['user_alert_type'] = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['user_alert_msg'] = "ลบผู้ใช้งานเรียบร้อยแล้ว";
                $_SESSION['user_alert_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['user_alert_msg'] = "ไม่สามารถลบผู้ใช้งานได้: " . $e->getMessage();
                $_SESSION['user_alert_type'] = 'danger';
            }
        }
        header("Location: users.php");
        exit;
    }
}

// ดึงผู้ใช้งานทั้งหมด
$usersList = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();

// สถิติจำนวนผู้ใช้
$totalUsers = count($usersList);
$requesterCount = 0;
$adminCount = 0;
$driverCount = 0;
foreach ($usersList as $u) {
    if ($u['role'] === 'admin') $adminCount++;
    elseif ($u['role'] === 'driver') $driverCount++;
    else $requesterCount++;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="fas fa-users-gear text-primary me-2"></i>จัดการรายชื่อผู้ใช้งานและรหัสผ่าน
        </h3>
        <span class="text-muted">
            คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์ | ตรวจสอบรหัสผ่าน, แก้ไขข้อมูล และเพิ่มผู้ใช้งาน
        </span>
    </div>
    <div class="d-flex gap-2">
        <a href="admin.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> แผงควบคุม Admin
        </a>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-1"></i> เพิ่มผู้ใช้งานใหม่
        </button>
    </div>
</div>

<?php if (!empty($alertMsg)): ?>
    <div class="alert alert-<?= $alertType ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="fas fa-<?= $alertType === 'danger' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i> <?= htmlspecialchars($alertMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: '<?= $alertType === "danger" ? "error" : "success" ?>',
                title: '<?= $alertType === "danger" ? "แจ้งเตือน" : "สำเร็จ" ?>',
                text: <?= json_encode($alertMsg, JSON_UNESCAPED_UNICODE) ?>,
                timer: 3500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }
    });
    </script>
<?php endif; ?>

<!-- สรุปสถิติผู้ใช้งาน -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card card-custom p-3 bg-white border-start border-primary border-4 shadow-sm">
            <div class="text-muted small">ผู้ใช้งานทั้งหมด</div>
            <div class="fs-4 fw-bold text-primary"><?= $totalUsers ?> <span class="fs-6 text-muted fw-normal">คน</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-custom p-3 bg-white border-start border-info border-4 shadow-sm">
            <div class="text-muted small">ผู้ขอใช้รถ / คณาจารย์</div>
            <div class="fs-4 fw-bold text-info"><?= $requesterCount ?> <span class="fs-6 text-muted fw-normal">คน</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-custom p-3 bg-white border-start border-dark border-4 shadow-sm">
            <div class="text-muted small">พนักงานขับรถยนต์</div>
            <div class="fs-4 fw-bold text-dark"><?= $driverCount ?> <span class="fs-6 text-muted fw-normal">คน</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-custom p-3 bg-white border-start border-danger border-4 shadow-sm">
            <div class="text-muted small">ผู้ดูแลระบบ (Admin)</div>
            <div class="fs-4 fw-bold text-danger"><?= $adminCount ?> <span class="fs-6 text-muted fw-normal">คน</span></div>
        </div>
    </div>
</div>

<!-- กล่องเครื่องมือค้นหาและตัวกรอง -->
<div class="card card-custom shadow-sm mb-4">
    <div class="card-body p-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="userSearchInput" class="form-control border-start-0" 
                           placeholder="พิมพ์ค้นหาตามชื่อจริง, นามสกุล, หรือชื่อผู้ใช้ (Username)...">
                </div>
            </div>
            <div class="col-md-3">
                <select id="roleFilter" class="form-select">
                    <option value="">-- แสดงทุกบทบาท --</option>
                    <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                    <option value="requester">ผู้ขอใช้รถ / คณาจารย์</option>
                    <option value="driver">พนักงานขับรถยนต์</option>
                    <option value="office_head">รักษาการในตำแหน่งหัวหน้าสำนักงานคณบดี</option>
                    <option value="dean">คณบดี</option>
                    <option value="facility_head">หัวหน้าอาคารสถานที่</option>
                </select>
            </div>
            <div class="col-md-3 text-end">
                <button type="button" id="toggleAllPassBtn" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-eye me-1"></i> <span id="toggleAllText">เปิดดูรหัสผ่านทั้งหมด</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ตารางรายชื่อผู้ใช้งานและรหัสผ่าน -->
<div class="card card-custom shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold text-dark mb-0">
            <i class="fas fa-list text-primary me-2"></i>รายชื่อผู้ใช้งานในระบบทั้งหมด (<span id="visibleUserCount"><?= $totalUsers ?></span> คน)
        </h6>
        <span class="badge bg-warning text-dark px-2 py-1"><i class="fas fa-lock me-1"></i>เฉพาะ Admin ที่เห็นรหัสผ่านนี้</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="usersTable">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>คำนำหน้า ชื่อ-นามสกุล</th>
                    <th>ชื่อผู้ใช้ล็อกอิน (Username)</th>
                    <th>รหัสผ่าน (Password)</th>
                    <th>บทบาท (Role)</th>
                    <th>ตำแหน่ง / สังกัด</th>
                    <th>เบอร์โทร</th>
                    <th class="text-center" style="width: 140px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($usersList as $u): 
                    $dispPass = !empty($u['plain_password']) ? $u['plain_password'] : '123456';
                    $roleBadge = [
                        'admin' => '<span class="badge bg-danger">ผู้ดูแลระบบ (Admin)</span>',
                        'requester' => '<span class="badge bg-secondary">ผู้ขอใช้รถ</span>',
                        'facility_head' => '<span class="badge bg-warning text-dark">หน.อาคารสถานที่</span>',
                        'office_head' => '<span class="badge bg-info text-dark">หน.สำนักงานคณบดี</span>',
                        'dean' => '<span class="badge bg-primary">คณบดี</span>',
                        'driver' => '<span class="badge bg-dark">พนักงานขับรถ</span>'
                    ][$u['role']] ?? '<span class="badge bg-light text-dark">' . htmlspecialchars($u['role']) . '</span>';
                ?>
                <tr class="user-row" data-name="<?= htmlspecialchars(mb_strtolower($u['fullname'] . ' ' . $u['username'])) ?>" data-role="<?= htmlspecialchars($u['role']) ?>">
                    <td class="text-muted small"><?= $i++ ?></td>
                    <td>
                        <strong class="text-dark"><?= htmlspecialchars(($u['prefix'] ? $u['prefix'] . ' ' : '') . $u['fullname']) ?></strong>
                        <?php if ($u['id'] === $currentUser['id']): ?>
                            <span class="badge bg-success-subtle text-success ms-1 small">คุณ</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code class="fs-6 text-primary fw-bold bg-light px-2 py-1 rounded border"><?= htmlspecialchars($u['username']) ?></code>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light text-dark border px-2 py-1 font-monospace fs-6 pass-display" data-real-pass="<?= htmlspecialchars($dispPass) ?>">
                                <span class="masked-pass">••••••••</span>
                                <span class="unmasked-pass d-none text-danger fw-bold"><?= htmlspecialchars($dispPass) ?></span>
                            </span>
                            <button type="button" class="btn btn-outline-secondary btn-sm p-1 single-eye-btn" title="ดู/ซ่อนรหัสผ่าน">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm p-1 copy-pass-btn" data-pass="<?= htmlspecialchars($dispPass) ?>" title="คัดลอกรหัสผ่าน">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </td>
                    <td><?= $roleBadge ?></td>
                    <td>
                        <div class="small fw-semibold"><?= htmlspecialchars($u['position'] ?: '-') ?></div>
                        <small class="text-muted"><?= htmlspecialchars($u['department'] ?: 'คณะวิทยาการจัดการ') ?></small>
                    </td>
                    <td><small class="text-muted"><?= htmlspecialchars($u['phone'] ?: '-') ?></small></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <button type="button" 
                                    class="btn btn-outline-primary btn-edit-user" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editUserModal"
                                    data-id="<?= $u['id'] ?>"
                                    data-prefix="<?= htmlspecialchars($u['prefix'] ?? '', ENT_QUOTES) ?>"
                                    data-fullname="<?= htmlspecialchars($u['fullname'] ?? '', ENT_QUOTES) ?>"
                                    data-username="<?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES) ?>"
                                    data-password="<?= htmlspecialchars($dispPass, ENT_QUOTES) ?>"
                                    data-role="<?= htmlspecialchars($u['role'] ?? 'requester', ENT_QUOTES) ?>"
                                    data-phone="<?= htmlspecialchars($u['phone'] ?? '', ENT_QUOTES) ?>"
                                    data-position="<?= htmlspecialchars($u['position'] ?? '', ENT_QUOTES) ?>"
                                    data-department="<?= htmlspecialchars($u['department'] ?? '', ENT_QUOTES) ?>"
                                    title="แก้ไขข้อมูล / เปลี่ยนรหัสผ่าน">
                                <i class="fas fa-edit me-1"></i> แก้ไข
                            </button>
                            <?php if ($u['id'] !== $currentUser['id']): ?>
                                <form method="POST" action="users.php" style="display:inline;" onsubmit="return confirm('ยืนยันต้องการลบผู้ใช้ <?= htmlspecialchars($u['fullname'], ENT_QUOTES) ?> หรือไม่?');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger" title="ลบผู้ใช้">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal แก้ไขข้อมูลผู้ใช้และรหัสผ่าน (Dynamic Modal เดียว ทำงานได้เร็วและไม่กระทบ Pagination) -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="users.php" class="modal-content shadow">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="editUserId" value="">
            
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold" id="editUserModalLabel">
                    <i class="fas fa-user-pen me-2"></i>แก้ไขข้อมูลผู้ใช้งาน
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label small fw-semibold">คำนำหน้า</label>
                        <input type="text" name="prefix" id="editPrefix" class="form-control form-control-sm" list="prefixList" placeholder="เช่น ผศ.ดร.">
                    </div>
                    <div class="col-8">
                        <label class="form-label small fw-semibold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" id="editFullname" class="form-control form-control-sm" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">
                        ชื่อผู้ใช้ล็อกอิน (Username) <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="username" id="editUsername" class="form-control form-control-sm font-monospace" required>
                    <div class="form-text small text-muted">ชื่อสำหรับใช้เข้าสู่ระบบ (สามารถใช้ชื่อจริง นามสกุล หรือชื่อผู้ใช้เฉพาะ)</div>
                </div>

                <div class="mb-3 bg-light p-3 rounded border">
                    <label class="form-label small fw-bold text-dark mb-1 d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-key text-warning me-1"></i> รหัสผ่าน (Password) <span class="text-danger">*</span></span>
                        <small class="text-muted fw-normal">แก้ไขเพื่อเปลี่ยนรหัสผ่านทันที</small>
                    </label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="new_password" id="editPassword" class="form-control font-monospace fw-bold" required>
                        <button type="button" class="btn btn-outline-secondary" id="btnGenPass" title="สุ่มรหัสผ่านใหม่ 5 หลัก + #">
                            <i class="fas fa-dice me-1"></i> สุ่มรหัส
                        </button>
                    </div>
                    <div class="form-text small text-muted">ระบบจะบันทึกทั้งการแสดงผลรหัสผ่านและอัปเดตการเข้ารหัสความปลอดภัยในฐานข้อมูล</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">บทบาท (Role) <span class="text-danger">*</span></label>
                        <select name="role" id="editRole" class="form-select form-select-sm" required>
                            <option value="requester">ผู้ขอใช้รถ (อาจารย์/บุคลากร)</option>
                            <option value="driver">พนักงานขับรถยนต์</option>
                            <option value="office_head">รักษาการในตำแหน่งหัวหน้าสำนักงานคณบดี</option>
                            <option value="dean">คณบดี</option>
                            <option value="facility_head">หัวหน้างานอาคารสถานที่</option>
                            <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" id="editPhone" class="form-control form-control-sm" placeholder="เช่น 081-999-8883">
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">ตำแหน่ง</label>
                        <input type="text" name="position" id="editPosition" class="form-control form-control-sm" placeholder="เช่น อาจารย์ประจำสาขาวิชา">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">สาขาวิชา / หน่วยงาน</label>
                        <input type="text" name="department" id="editDepartment" class="form-control form-control-sm" placeholder="เช่น คณะวิทยาการจัดการ">
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-save me-1"></i> บันทึกการแก้ไข
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal เพิ่มผู้ใช้งานใหม่ -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="users.php" class="modal-content shadow">
            <input type="hidden" name="action" value="add_user">
            <div class="modal-header bg-success text-white">
                <h6 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้งานใหม่</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label small fw-semibold">คำนำหน้า</label>
                        <input type="text" name="prefix" class="form-control form-control-sm" list="prefixList" placeholder="เช่น อาจารย์" value="อาจารย์">
                    </div>
                    <div class="col-8">
                        <label class="form-label small fw-semibold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" id="newFullname" class="form-control form-control-sm" placeholder="เช่น สมเกียรติ มณีโชติ" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">ชื่อผู้ใช้ (Username สำหรับล็อกอิน) <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="newUsername" class="form-control form-control-sm" placeholder="เช่น สมเกียรติ มณีโชติ" required>
                    <div class="form-text small">ระบบแนะนำให้ใช้ชื่อจริง นามสกุล ไม่ต้องใส่คำนำหน้า</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">รหัสผ่าน (Password) <span class="text-danger">*</span></label>
                    <input type="text" name="password" class="form-control form-control-sm font-monospace" placeholder="เช่น 60123#" value="12345#" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">บทบาท (Role)</label>
                        <select name="role" class="form-select form-select-sm">
                            <option value="requester" selected>ผู้ขอใช้รถ (อาจารย์/บุคลากร)</option>
                            <option value="driver">พนักงานขับรถยนต์</option>
                            <option value="office_head">รักษาการในตำแหน่งหัวหน้าสำนักงานคณบดี</option>
                            <option value="dean">คณบดี</option>
                            <option value="facility_head">หัวหน้างานอาคารสถานที่</option>
                            <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" class="form-control form-control-sm" placeholder="08x-xxx-xxxx">
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">ตำแหน่ง</label>
                        <input type="text" name="position" class="form-control form-control-sm" value="อาจารย์ประจำสาขาวิชา">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">สาขาวิชา / หน่วยงาน</label>
                        <input type="text" name="department" class="form-control form-control-sm" value="คณะวิทยาการจัดการ">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="fas fa-plus me-1"></i> บันทึกเพิ่มผู้ใช้
                </button>
            </div>
        </form>
    </div>
</div>

<datalist id="prefixList">
    <option value="นาย">
    <option value="นาง">
    <option value="นางสาว">
    <option value="อาจารย์">
    <option value="อาจารย์ ดร.">
    <option value="ดร.">
    <option value="ผศ.">
    <option value="ผศ.ดร.">
    <option value="รศ.">
    <option value="รศ.ดร.">
</datalist>

<script>
    // ฟังก์ชันกำหนดข้อมูลลงใน Modal แก้ไขผู้ใช้งาน
    function populateEditModal(btn) {
        if (!btn) return;
        const id = btn.getAttribute('data-id') || '';
        const prefix = btn.getAttribute('data-prefix') || '';
        const fullname = btn.getAttribute('data-fullname') || '';
        const username = btn.getAttribute('data-username') || '';
        const password = btn.getAttribute('data-password') || '';
        const role = btn.getAttribute('data-role') || 'requester';
        const phone = btn.getAttribute('data-phone') || '';
        const position = btn.getAttribute('data-position') || '';
        const department = btn.getAttribute('data-department') || '';

        const editUserId = document.getElementById('editUserId');
        const editPrefix = document.getElementById('editPrefix');
        const editFullname = document.getElementById('editFullname');
        const editUsername = document.getElementById('editUsername');
        const editPassword = document.getElementById('editPassword');
        const editRole = document.getElementById('editRole');
        const editPhone = document.getElementById('editPhone');
        const editPosition = document.getElementById('editPosition');
        const editDepartment = document.getElementById('editDepartment');
        const modalTitle = document.getElementById('editUserModalLabel');

        if (editUserId) editUserId.value = id;
        if (editPrefix) editPrefix.value = prefix;
        if (editFullname) editFullname.value = fullname;
        if (editUsername) editUsername.value = username;
        if (editPassword) editPassword.value = password;
        if (editRole) editRole.value = role;
        if (editPhone) editPhone.value = phone;
        if (editPosition) editPosition.value = position;
        if (editDepartment) editDepartment.value = department;

        if (modalTitle) {
            modalTitle.innerHTML = '<i class="fas fa-user-pen me-2"></i>แก้ไขผู้ใช้: ' + (prefix ? prefix + ' ' : '') + fullname;
        }
    }

    // รองรับการคลิกปุ่มแก้ไขทั้งผ่าน Bootstrap event และ click listener (ป้องกันปัญหา pagination/sorting)
    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            if (event.relatedTarget) {
                populateEditModal(event.relatedTarget);
            }
        });
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-edit-user');
        if (btn) {
            populateEditModal(btn);
        }
    });

    // ปุ่มสุ่มรหัสผ่าน
    const btnGenPass = document.getElementById('btnGenPass');
    if (btnGenPass) {
        btnGenPass.addEventListener('click', function() {
            const randomNum = Math.floor(10000 + Math.random() * 90000);
            const passInput = document.getElementById('editPassword');
            if (passInput) passInput.value = randomNum + '#';
        });
    }

    // คัดลอกชื่อไปเป็น Username อัตโนมัติใน Modal เพิ่มผู้ใช้
    const newFullname = document.getElementById('newFullname');
    const newUsername = document.getElementById('newUsername');
    if (newFullname && newUsername) {
        newFullname.addEventListener('input', function() {
            if (!newUsername.value || newUsername.dataset.custom !== '1') {
                newUsername.value = this.value.trim();
            }
        });
        newUsername.addEventListener('input', function() {
            this.dataset.custom = '1';
        });
    }

    // ดู/ซ่อนรหัสผ่านรายบุคคล
    document.querySelectorAll('.single-eye-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            if (!row) return;
            const masked = row.querySelector('.masked-pass');
            const unmasked = row.querySelector('.unmasked-pass');
            const icon = this.querySelector('i');
            
            if (unmasked && masked && icon) {
                if (unmasked.classList.contains('d-none')) {
                    unmasked.classList.remove('d-none');
                    masked.classList.add('d-none');
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                } else {
                    unmasked.classList.add('d-none');
                    masked.classList.remove('d-none');
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            }
        });
    });

    // ดู/ซ่อนรหัสผ่านทั้งหมด
    let allVisible = false;
    const toggleAllPassBtn = document.getElementById('toggleAllPassBtn');
    const toggleAllText = document.getElementById('toggleAllText');
    if (toggleAllPassBtn) {
        toggleAllPassBtn.addEventListener('click', function() {
            allVisible = !allVisible;
            document.querySelectorAll('.pass-display').forEach(display => {
                const masked = display.querySelector('.masked-pass');
                const unmasked = display.querySelector('.unmasked-pass');
                const eyeIcon = display.closest('td') ? display.closest('td').querySelector('.single-eye-btn i') : null;
                
                if (masked && unmasked) {
                    if (allVisible) {
                        unmasked.classList.remove('d-none');
                        masked.classList.add('d-none');
                        if (eyeIcon) {
                            eyeIcon.classList.remove('fa-eye-slash');
                            eyeIcon.classList.add('fa-eye');
                        }
                    } else {
                        unmasked.classList.add('d-none');
                        masked.classList.remove('d-none');
                        if (eyeIcon) {
                            eyeIcon.classList.remove('fa-eye');
                            eyeIcon.classList.add('fa-eye-slash');
                        }
                    }
                }
            });
            if (toggleAllText) {
                toggleAllText.textContent = allVisible ? 'ซ่อนรหัสผ่านทั้งหมด' : 'เปิดดูรหัสผ่านทั้งหมด';
            }
            toggleAllPassBtn.classList.toggle('btn-outline-secondary', !allVisible);
            toggleAllPassBtn.classList.toggle('btn-warning', allVisible);
        });
    }

    // คัดลอกรหัสผ่าน
    document.querySelectorAll('.copy-pass-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const pass = this.getAttribute('data-pass');
            if (pass) {
                navigator.clipboard.writeText(pass).then(() => {
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-copy');
                        icon.classList.add('fa-check', 'text-success');
                        setTimeout(() => {
                            icon.classList.remove('fa-check', 'text-success');
                            icon.classList.add('fa-copy');
                        }, 1500);
                    }
                });
            }
        });
    });

    // ค้นหาผู้ใช้งานและกรองบทบาท Real-time
    const searchInput = document.getElementById('userSearchInput');
    const roleFilter = document.getElementById('roleFilter');
    const rows = document.querySelectorAll('.user-row');
    const visibleCount = document.getElementById('visibleUserCount');

    function filterUsers() {
        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
        const selectedRole = roleFilter ? roleFilter.value : '';
        let count = 0;

        rows.forEach(row => {
            const nameData = row.getAttribute('data-name') || '';
            const roleData = row.getAttribute('data-role') || '';
            const matchesSearch = !query || nameData.includes(query);
            const matchesRole = !selectedRole || roleData === selectedRole;

            if (matchesSearch && matchesRole) {
                row.style.display = '';
                count++;
            } else {
                row.style.display = 'none';
            }
        });

        if (visibleCount) visibleCount.textContent = count;
    }

    if (searchInput) searchInput.addEventListener('input', filterUsers);
    if (roleFilter) roleFilter.addEventListener('change', filterUsers);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
