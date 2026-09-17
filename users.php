<?php
// users.php - หน้าจัดการผู้ใช้งานและรหัสผ่านสำหรับ Admin
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$alertMsg = '';
$alertType = 'info';

// ประมวลผลคำขอจาก Admin
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. เพิ่มผู้ใช้งานใหม่
    if ($action === 'add_user') {
        $prefix = trim($_POST['prefix'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '123456');
        $position = trim($_POST['position'] ?? 'อาจารย์ / บุคลากร');
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
                $alertMsg = "เพิ่มผู้ใช้งาน '{$prefix}{$fullname}' (Username: {$username}) เรียบร้อยแล้ว";
                $alertType = 'success';
            } catch (PDOException $e) {
                $alertMsg = "ข้อผิดพลาด: ชื่อผู้ใช้ (Username) '{$username}' นี้มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น";
                $alertType = 'danger';
            }
        } else {
            $alertMsg = "กรุณาระบุชื่อ-นามสกุล และชื่อผู้ใช้งาน";
            $alertType = 'danger';
        }
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

    // 3. รีเซ็ตรหัสผ่านอย่างเดียว
    if ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPass = trim($_POST['new_password'] ?? '123456');
        if ($userId > 0 && !empty($newPass)) {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, plain_password = ? WHERE id = ?");
            $stmt->execute([$hash, $newPass, $userId]);
            $alertMsg = "เปลี่ยนรหัสผ่านใหม่เป็น '{$newPass}' เรียบร้อยแล้ว";
            $alertType = 'success';
        }
    }

    // 4. ลบผู้ใช้งาน
    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === $currentUser['id']) {
            $alertMsg = "ไม่สามารถลบบัญชีผู้ใช้ที่คุณกำลังเข้าสู่ระบบอยู่ได้";
            $alertType = 'danger';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $alertMsg = "ลบผู้ใช้งานเรียบร้อยแล้ว";
            $alertType = 'success';
        }
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
        <i class="fas fa-info-circle me-2"></i> <?= htmlspecialchars($alertMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
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
                    <option value="office_head">หัวหน้าสำนักงานคณบดี</option>
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
                        <strong class="text-dark"><?= htmlspecialchars($u['prefix'] . ' ' . $u['fullname']) ?></strong>
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
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>" title="แก้ไขข้อมูล / เปลี่ยนรหัสผ่าน">
                                <i class="fas fa-edit me-1"></i> แก้ไข
                            </button>
                            <?php if ($u['id'] !== $currentUser['id']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันต้องการลบผู้ใช้ <?= htmlspecialchars($u['fullname']) ?> หรือไม่?');">
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
                <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <form method="POST" class="modal-content">
                            <input type="hidden" name="action" value="edit_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            
                            <div class="modal-header bg-primary text-white">
                                <h6 class="modal-title fw-bold">
                                    <i class="fas fa-user-pen me-2"></i>แก้ไขผู้ใช้: <?= htmlspecialchars($u['fullname']) ?>
                                </h6>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>

                            <div class="modal-body">
                                <div class="row g-2 mb-3">
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold">คำนำหน้า</label>
                                        <input type="text" name="prefix" class="form-control form-control-sm" value="<?= htmlspecialchars($u['prefix']) ?>" placeholder="นาย/นาง/นางสาว">
                                    </div>
                                    <div class="col-8">
                                        <label class="form-label small fw-semibold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                        <input type="text" name="fullname" class="form-control form-control-sm" value="<?= htmlspecialchars($u['fullname']) ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">
                                        ชื่อผู้ใช้สำหรับล็อกอิน (Username) <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="username" class="form-control form-control-sm" value="<?= htmlspecialchars($u['username']) ?>" required>
                                    <div class="form-text small">ชื่อจริง นามสกุล ไม่ต้องใส่คำนำหน้า</div>
                                </div>

                                <div class="mb-3 bg-warning-subtle p-2 rounded border border-warning">
                                    <label class="form-label small fw-bold text-dark mb-1">
                                        <i class="fas fa-key text-warning me-1"></i> รหัสผ่าน (Password)
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="new_password" class="form-control" value="<?= htmlspecialchars($dispPass) ?>" required>
                                    </div>
                                    <div class="form-text small text-dark">สามารถพิมพ์รหัสผ่านใหม่เพื่อเปลี่ยนแปลงได้ทันที</div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">บทบาท (Role)</label>
                                        <select name="role" class="form-select form-select-sm">
                                            <option value="requester" <?= ($u['role'] == 'requester') ? 'selected' : '' ?>>ผู้ขอใช้รถ (อาจารย์/บุคลากร)</option>
                                            <option value="driver" <?= ($u['role'] == 'driver') ? 'selected' : '' ?>>พนักงานขับรถยนต์</option>
                                            <option value="office_head" <?= ($u['role'] == 'office_head') ? 'selected' : '' ?>>หัวหน้าสำนักงานคณบดี</option>
                                            <option value="dean" <?= ($u['role'] == 'dean') ? 'selected' : '' ?>>คณบดี</option>
                                            <option value="facility_head" <?= ($u['role'] == 'facility_head') ? 'selected' : '' ?>>หัวหน้างานอาคารสถานที่</option>
                                            <option value="admin" <?= ($u['role'] == 'admin') ? 'selected' : '' ?>>ผู้ดูแลระบบ (Admin)</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">เบอร์โทรศัพท์</label>
                                        <input type="text" name="phone" class="form-control form-control-sm" value="<?= htmlspecialchars($u['phone']) ?>" placeholder="08x-xxx-xxxx">
                                    </div>
                                </div>

                                <div class="row g-2 mb-2">
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

<!-- Modal เพิ่มผู้ใช้งานใหม่ -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="add_user">
            <div class="modal-header bg-success text-white">
                <h6 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้งานใหม่</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label small fw-semibold">คำนำหน้า</label>
                        <select name="prefix" class="form-select form-select-sm">
                            <option value="นาย">นาย</option>
                            <option value="นาง">นาง</option>
                            <option value="นางสาว">นางสาว</option>
                            <option value="อาจารย์">อาจารย์</option>
                            <option value="ผศ.ดร.">ผศ.ดร.</option>
                            <option value="ดร.">ดร.</option>
                        </select>
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
                            <option value="office_head">หัวหน้าสำนักงานคณบดี</option>
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
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-success btn-sm">บันทึกเพิ่มผู้ใช้</button>
            </div>
        </form>
    </div>
</div>

<script>
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
            const masked = row.querySelector('.masked-pass');
            const unmasked = row.querySelector('.unmasked-pass');
            const icon = this.querySelector('i');
            
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
                const eyeIcon = display.closest('td').querySelector('.single-eye-btn i');
                
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
            });
            toggleAllText.textContent = allVisible ? 'ซ่อนรหัสผ่านทั้งหมด' : 'เปิดดูรหัสผ่านทั้งหมด';
            toggleAllPassBtn.classList.toggle('btn-outline-secondary', !allVisible);
            toggleAllPassBtn.classList.toggle('btn-warning', allVisible);
        });
    }

    // คัดลอกรหัสผ่าน
    document.querySelectorAll('.copy-pass-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const pass = this.getAttribute('data-pass');
            navigator.clipboard.writeText(pass).then(() => {
                const icon = this.querySelector('i');
                icon.classList.remove('fa-copy');
                icon.classList.add('fa-check', 'text-success');
                setTimeout(() => {
                    icon.classList.remove('fa-check', 'text-success');
                    icon.classList.add('fa-copy');
                }, 1500);
            });
        });
    });

    // ค้นหาผู้ใช้งานและกรองบทบาท Real-time
    const searchInput = document.getElementById('userSearchInput');
    const roleFilter = document.getElementById('roleFilter');
    const rows = document.querySelectorAll('.user-row');
    const visibleCount = document.getElementById('visibleUserCount');

    function filterUsers() {
        const query = (searchInput.value || '').trim().toLowerCase();
        const selectedRole = roleFilter.value;
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

