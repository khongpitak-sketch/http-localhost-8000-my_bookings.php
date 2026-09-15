<?php
// booking_detail.php - หน้ารายละเอียดคำขอและขั้นตอนการอนุมัติ
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$bookingId = (int)($_GET['id'] ?? 0);
if (!$bookingId) {
    header("Location: index.php");
    exit;
}

// ดึงข้อมูลการจอง
$stmt = $pdo->prepare("
    SELECT b.*, v.brand_model, v.vehicle_type, v.seats 
    FROM bookings b 
    JOIN vehicles v ON b.vehicle_id = v.id 
    WHERE b.id = ?
");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    die("ไม่พบข้อมูลคำขอจองรถยนต์");
}

// ดึงข้อมูลการอนุมัติ
$appStmt = $pdo->prepare("SELECT * FROM approvals WHERE booking_id = ?");
$appStmt->execute([$bookingId]);
$approval = $appStmt->fetch() ?: [];

// ประมวลผลการอนุมัติผ่านหน้านี้ (เฉพาะ Admin ผู้มีสิทธิ์)
$actionMessage = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_type']) && $isAdmin) {
    $actionType = $_POST['action_type'];
    
    // 1. หัวหน้างานอาคารสถานที่
    if ($actionType === 'facility' && in_array($currentUser['role'], ['facility_head', 'admin'])) {
        $facility_status = $_POST['facility_status'] ?? 'approved';
        $facility_fuel = isset($_POST['facility_fuel']) ? 1 : 0;
        $facility_allowance = isset($_POST['facility_allowance']) ? 1 : 0;
        $facility_other = trim($_POST['facility_other'] ?? '');
        $facility_comment = trim($_POST['facility_comment'] ?? '');
        $facility_signer = $currentUser['fullname'];

        $newBookingStatus = ($facility_status === 'approved') ? 'pending_office' : 'rejected';

        $pdo->prepare("
            UPDATE approvals SET 
                facility_status = ?, facility_fuel = ?, facility_allowance = ?, 
                facility_other = ?, facility_signer = ?, facility_comment = ?, 
                facility_signed_at = datetime('now') 
            WHERE booking_id = ?
        ")->execute([$facility_status, $facility_fuel, $facility_allowance, $facility_other, $facility_signer, $facility_comment, $bookingId]);

        $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?")->execute([$newBookingStatus, $bookingId]);
        header("Location: booking_detail.php?id=$bookingId&msg=saved");
        exit;
    }

    // 2. หัวหน้าสำนักงานคณบดี
    if ($actionType === 'office' && in_array($currentUser['role'], ['office_head', 'admin'])) {
        $office_status = $_POST['office_status'] ?? 'approved';
        $office_driver_assigned = trim($_POST['office_driver_assigned'] ?? '');
        $office_reason = trim($_POST['office_reason'] ?? '');
        $office_other = trim($_POST['office_other'] ?? '');
        $office_signer = $currentUser['fullname'];

        $newBookingStatus = ($office_status === 'approved') ? 'pending_dean' : 'rejected';

        $pdo->prepare("
            UPDATE approvals SET 
                office_status = ?, office_driver_assigned = ?, office_reason = ?, 
                office_other = ?, office_signer = ?, office_signed_at = datetime('now') 
            WHERE booking_id = ?
        ")->execute([$office_status, $office_driver_assigned, $office_reason, $office_other, $office_signer, $bookingId]);

        $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?")->execute([$newBookingStatus, $bookingId]);
        header("Location: booking_detail.php?id=$bookingId&msg=saved");
        exit;
    }

    // 3. คณบดีคณะวิทยาการจัดการ
    if ($actionType === 'dean' && in_array($currentUser['role'], ['dean', 'admin'])) {
        $dean_status = $_POST['dean_status'] ?? 'approved';
        $dean_reason = trim($_POST['dean_reason'] ?? '');
        $dean_other = trim($_POST['dean_other'] ?? '');
        $dean_signer = $currentUser['fullname'];

        $newBookingStatus = ($dean_status === 'approved') ? 'pending_driver' : 'rejected';

        $pdo->prepare("
            UPDATE approvals SET 
                dean_status = ?, dean_reason = ?, dean_other = ?, 
                dean_signer = ?, dean_signed_at = datetime('now') 
            WHERE booking_id = ?
        ")->execute([$dean_status, $dean_reason, $dean_other, $dean_signer, $bookingId]);

        $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?")->execute([$newBookingStatus, $bookingId]);
        header("Location: booking_detail.php?id=$bookingId&msg=saved");
        exit;
    }

    // 4. พนักงานขับรถ
    if ($actionType === 'driver' && in_array($currentUser['role'], ['driver', 'admin'])) {
        $driver_signer = $currentUser['fullname'];

        $pdo->prepare("
            UPDATE approvals SET 
                driver_ack_status = 'acknowledged', 
                driver_signer = ?, 
                driver_acknowledged_at = datetime('now') 
            WHERE booking_id = ?
        ")->execute([$driver_signer, $bookingId]);

        $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?")->execute([$bookingId]);
        header("Location: booking_detail.php?id=$bookingId&msg=saved");
        exit;
    }
}

// ดึงรายชื่อพนักงานขับรถ
$drivers = $pdo->query("SELECT fullname FROM users WHERE role = 'driver'")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i> บันทึกคำขอขออนุญาตใช้รถยนต์เรียบร้อยแล้ว เลขที่คำขอ: <strong><?= htmlspecialchars($booking['doc_no']) ?></strong>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="fas fa-info-circle me-2"></i> บันทึกผลการพิจารณาเรียบร้อยแล้ว
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- รายละเอียดคำขอและขั้นตอน -->
    <div class="col-lg-8">
        <div class="card card-custom p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-3 border-bottom">
                <div>
                    <span class="badge bg-secondary mb-1">เลขที่: <?= htmlspecialchars($booking['doc_no'] ?? '-') ?></span>
                    <h4 class="fw-bold mb-0 text-dark">คำขออนุญาตใช้รถยนต์</h4>
                </div>
                <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
                    <div><?= getStatusBadge($booking['status']) ?></div>
                    <a href="print_form.php?id=<?= $booking['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm px-3">
                        <i class="fas fa-print me-1"></i> พิมพ์แบบฟอร์มราชการ (A4)
                    </a>
                </div>
            </div>

            <!-- ข้อมูลสรุป -->
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr>
                            <th class="bg-light" style="width: 25%;">ผู้ขอใช้รถ</th>
                            <td>
                                <strong><?= htmlspecialchars($booking['requester_name']) ?></strong><br>
                                <span class="text-muted small">ตำแหน่ง <?= htmlspecialchars($booking['requester_position']) ?> (<?= htmlspecialchars($booking['requester_department']) ?>)</span>
                            </td>
                            <th class="bg-light" style="width: 25%;">รถยนต์ที่ขอใช้</th>
                            <td>
                                <strong class="text-primary"><?= htmlspecialchars($booking['plate_number']) ?></strong><br>
                                <span class="text-muted small"><?= htmlspecialchars($booking['brand_model']) ?> (<?= htmlspecialchars($booking['vehicle_type']) ?>)</span>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">เพื่อใช้ในงาน (วัตถุประสงค์)</th>
                            <td colspan="3"><?= nl2br(htmlspecialchars($booking['purpose'])) ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">เส้นทางเดินทาง</th>
                            <td colspan="3">
                                <strong>จาก:</strong> <?= htmlspecialchars($booking['route_from']) ?><br>
                                <strong>ถึง:</strong> <?= htmlspecialchars($booking['route_to']) ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">วัน-เวลาเดินทาง</th>
                            <td colspan="3">
                                <div><i class="fas fa-plane-departure text-success me-2"></i><strong>ออกเดินทาง:</strong> <?= thaiDate($booking['start_datetime']) ?></div>
                                <div><i class="fas fa-plane-arrival text-danger me-2"></i><strong>เดินทางกลับ:</strong> <?= thaiDate($booking['end_datetime']) ?></div>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">ผู้ร่วมทาง</th>
                            <td><?= $booking['passenger_count'] ?> คน</td>
                            <th class="bg-light">ผู้ควบคุมการใช้รถ</th>
                            <td><strong><?= htmlspecialchars($booking['controller_name']) ?></strong></td>
                        </tr>
                        <?php if (!empty($booking['passenger_names'])): ?>
                        <tr>
                            <th class="bg-light">รายชื่อผู้ร่วมเดินทาง</th>
                            <td colspan="3"><?= nl2br(htmlspecialchars($booking['passenger_names'])) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- กล่องไทม์ไลน์ขั้นตอนการอนุมัติ 4 ลำดับ -->
        <div class="card card-custom p-4">
            <h5 class="fw-bold mb-4 text-dark">
                <i class="fas fa-tasks text-primary me-2"></i>ขั้นตอนการพิจารณาอนุมัติ 4 ลำดับ
            </h5>

            <!-- 1. หัวหน้างานอาคารสถานที่ -->
            <div class="border rounded-3 p-3 mb-3 <?= ($approval['facility_status'] == 'approved') ? 'border-success bg-success-subtle' : (($booking['status'] == 'pending_facility') ? 'border-warning bg-warning-subtle' : 'bg-light') ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">
                        <span class="badge bg-primary me-2">ลำดับที่ 1</span>
                        ความเห็นของหัวหน้างานอาคารสถานที่
                    </h6>
                    <?php if ($approval['facility_status'] == 'approved'): ?>
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> เห็นชอบแล้ว</span>
                    <?php elseif ($approval['facility_status'] == 'rejected'): ?>
                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i> ไม่เห็นชอบ</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> รอพิจารณา</span>
                    <?php endif; ?>
                </div>

                <?php if ($approval['facility_status']): ?>
                    <div class="small">
                        <div><strong>ผลการพิจารณา:</strong> <?= ($approval['facility_status'] == 'approved') ? 'เห็นชอบ' : 'ไม่เห็นชอบ' ?></div>
                        <div><strong>การสนับสนุน:</strong> 
                            <?= ($approval['facility_fuel']) ? '✓ ค่าน้ำมันเชื้อเพลิง ' : '' ?>
                            <?= ($approval['facility_allowance']) ? '✓ เบี้ยเลี้ยง/ค่าตอบแทน ' : '' ?>
                            <?= (!empty($approval['facility_other'])) ? ' (อื่นๆ: ' . htmlspecialchars($approval['facility_other']) . ')' : '' ?>
                            <?= (!$approval['facility_fuel'] && !$approval['facility_allowance'] && empty($approval['facility_other'])) ? 'ไม่ระบุ' : '' ?>
                        </div>
                        <div><strong>ผู้ลงนาม:</strong> <?= htmlspecialchars($approval['facility_signer'] ?? 'นายเอกสิทธิ์ คงพิทักษ์') ?> (<?= thaiDate($approval['facility_signed_at']) ?>)</div>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">รอหัวหน้างานอาคารสถานที่ (นายเอกสิทธิ์ คงพิทักษ์) พิจารณาเห็นชอบและรายการสนับสนุน</p>
                <?php endif; ?>
            </div>

            <!-- 2. หัวหน้าสำนักงานคณบดี -->
            <div class="border rounded-3 p-3 mb-3 <?= ($approval['office_status'] == 'approved') ? 'border-success bg-success-subtle' : (($booking['status'] == 'pending_office') ? 'border-warning bg-warning-subtle' : 'bg-light') ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">
                        <span class="badge bg-primary me-2">ลำดับที่ 2</span>
                        ความเห็นของหัวหน้าสำนักงานคณบดี
                    </h6>
                    <?php if ($approval['office_status'] == 'approved'): ?>
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> ให้ความเห็นชอบแล้ว</span>
                    <?php elseif ($approval['office_status'] == 'rejected'): ?>
                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i> ไม่อนุญาต</span>
                    <?php else: ?>
                        <span class="badge bg-secondary"><i class="fas fa-clock me-1"></i> รอการพิจารณา</span>
                    <?php endif; ?>
                </div>

                <?php if ($approval['office_status']): ?>
                    <div class="small">
                        <div><strong>ผลการพิจารณา:</strong> <?= ($approval['office_status'] == 'approved') ? 'ควรอนุญาต' : 'ไม่อนุญาต' ?></div>
                        <?php if (!empty($approval['office_driver_assigned'])): ?>
                            <div><strong>พนักงานขับรถที่มอบหมาย:</strong> <?= htmlspecialchars($approval['office_driver_assigned']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($approval['office_reason'])): ?>
                            <div><strong>เหตุผล:</strong> <?= htmlspecialchars($approval['office_reason']) ?></div>
                        <?php endif; ?>
                        <div><strong>ผู้ลงนาม:</strong> <?= htmlspecialchars($approval['office_signer'] ?? 'นางซูไบดะห์ หะยีมะ') ?> (<?= thaiDate($approval['office_signed_at']) ?>)</div>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">รอหัวหน้าสำนักงานคณบดี (นางซูไบดะห์ หะยีมะ) มอบหมายพนักงานขับรถ</p>
                <?php endif; ?>
            </div>

            <!-- 3. คณบดีคณะวิทยาการจัดการ -->
            <div class="border rounded-3 p-3 mb-3 <?= ($approval['dean_status'] == 'approved') ? 'border-success bg-success-subtle' : (($booking['status'] == 'pending_dean') ? 'border-warning bg-warning-subtle' : 'bg-light') ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">
                        <span class="badge bg-primary me-2">ลำดับที่ 3</span>
                        คำสั่งคณบดีคณะวิทยาการจัดการ
                    </h6>
                    <?php if ($approval['dean_status'] == 'approved'): ?>
                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> อนุมัติแล้ว</span>
                    <?php elseif ($approval['dean_status'] == 'rejected'): ?>
                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i> ไม่อนุญาต</span>
                    <?php else: ?>
                        <span class="badge bg-secondary"><i class="fas fa-clock me-1"></i> รอคำสั่งคณบดี</span>
                    <?php endif; ?>
                </div>

                <?php if ($approval['dean_status']): ?>
                    <div class="small">
                        <div><strong>คำสั่ง:</strong> <span class="fw-bold text-success"><?= ($approval['dean_status'] == 'approved') ? 'อนุญาต' : 'ไม่อนุญาต' ?></span></div>
                        <?php if (!empty($approval['dean_reason'])): ?>
                            <div><strong>หมายเหตุ:</strong> <?= htmlspecialchars($approval['dean_reason']) ?></div>
                        <?php endif; ?>
                        <div><strong>ผู้ลงนาม:</strong> <?= htmlspecialchars($approval['dean_signer'] ?? 'ผู้ช่วยศาสตราจารย์ ดร.บงกช กมลเปรม') ?> (<?= thaiDate($approval['dean_signed_at']) ?>)</div>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">รอคณบดีคณะวิทยาการจัดการ (ผศ. ดร.บงกช กมลเปรม) พิจารณาสั่งการ</p>
                <?php endif; ?>
            </div>

            <!-- 4. พนักงานขับรถยนต์ -->
            <div class="border rounded-3 p-3 <?= ($approval['driver_ack_status'] == 'acknowledged') ? 'border-success bg-success-subtle' : (($booking['status'] == 'pending_driver') ? 'border-warning bg-warning-subtle' : 'bg-light') ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">
                        <span class="badge bg-primary me-2">ลำดับที่ 4</span>
                        บันทึกพนักงานขับรถยนต์
                    </h6>
                    <?php if ($approval['driver_ack_status'] == 'acknowledged'): ?>
                        <span class="badge bg-success"><i class="fas fa-check-double me-1"></i> รับทราบการขอใช้รถแล้ว</span>
                    <?php else: ?>
                        <span class="badge bg-secondary"><i class="fas fa-clock me-1"></i> รอคนขับลงชื่อรับทราบ</span>
                    <?php endif; ?>
                </div>

                <?php if ($approval['driver_ack_status'] == 'acknowledged'): ?>
                    <div class="small">
                        <div><strong>สถานะ:</strong> ได้รับทราบการขอใช้รถยนต์แล้ว</div>
                        <div><strong>พนักงานขับรถยนต์:</strong> <?= htmlspecialchars($approval['driver_signer'] ?? 'นายธเนศ อินเอิบ') ?> (<?= thaiDate($approval['driver_acknowledged_at']) ?>)</div>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">เมื่อคณบดีอนุมัติ พนักงานขับรถจะลงชื่อรับทราบภารกิจเดินทาง</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ส่วนพิจารณาอนุมัติสำหรับผู้มีสิทธิ์ตามบทบาทปัจจุบัน -->
    <div class="col-lg-4">
        <div class="card card-custom p-4 border-top border-4 border-warning sticky-top" style="top: 80px;">
            <h5 class="fw-bold text-dark mb-3">
                <i class="fas fa-user-shield text-warning me-2"></i>กล่องดำเนินการพิจารณา
            </h5>

            <?php if (!$isAdmin): ?>
                <!-- ผู้ใช้ทั่วไป: อ่านอย่างเดียว แก้ไขหรืออนุมัติไม่ได้ -->
                <div class="alert alert-light border text-center p-3 mb-3">
                    <i class="fas fa-lock text-secondary fs-1 mb-2 d-block"></i>
                    <h6 class="fw-bold text-dark mb-1">โหมดผู้ใช้ทั่วไป</h6>
                    <p class="small text-muted mb-3">
                        ผู้ใช้ทั่วไปสามารถติดตามผลการพิจารณาและพิมพ์เอกสารได้ แต่<strong>ไม่สามารถแก้ไขหรืออนุมัติคำขอได้</strong>
                    </p>
                    <a href="login.php?redirect=<?= urlencode('booking_detail.php?id=' . $booking['id']) ?>" class="btn btn-warning btn-sm w-100 fw-bold text-dark">
                        <i class="fas fa-key me-1"></i> เข้าสู่ระบบ Admin เพื่อดำเนินการ
                    </a>
                </div>
            <?php else: ?>
                <div class="small text-muted mb-3">
                    เข้าสู่ระบบในฐานะ Admin: <strong class="text-primary"><?= htmlspecialchars($currentUser['fullname']) ?></strong>
                </div>

                <!-- สิทธิ์ Admin: พิจารณาได้ทุกขั้นตอน -->
                <?php if ($booking['status'] == 'pending_facility'): ?>
                    <div class="alert alert-primary p-2 small mb-3">
                        <i class="fas fa-info-circle me-1"></i> 1. บันทึกความเห็นของหัวหน้างานอาคารสถานที่
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action_type" value="facility">
                        <div class="mb-3">
                            <label class="form-label fw-bold">ความเห็น</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="facility_status" id="fac_app" value="approved" checked>
                                <label class="form-check-label text-success fw-bold" for="fac_app">เห็นชอบ</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="facility_status" id="fac_rej" value="rejected">
                                <label class="form-check-label text-danger fw-bold" for="fac_rej">ไม่เห็นชอบ</label>
                            </div>
                        </div>
                        <div class="mb-3 bg-light p-2 rounded">
                            <label class="form-label fw-bold small">รายการสนับสนุน</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="facility_fuel" value="1" id="fuel_check" checked>
                                <label class="form-check-label" for="fuel_check">ค่าน้ำมันเชื้อเพลิง</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="facility_allowance" value="1" id="allow_check" checked>
                                <label class="form-check-label" for="allow_check">เบี้ยเลี้ยง / ค่าตอบแทน</label>
                            </div>
                            <div class="mt-2">
                                <input type="text" name="facility_other" class="form-control form-control-sm" placeholder="อื่นๆ ระบุ (ถ้ามี)">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 py-2">
                            <i class="fas fa-check me-1"></i> บันทึกความเห็นอาคารสถานที่
                        </button>
                    </form>

                <!-- 2. หัวหน้าสำนักงานคณบดี -->
                <?php elseif ($booking['status'] == 'pending_office'): ?>
                    <div class="alert alert-info p-2 small mb-3">
                        <i class="fas fa-info-circle me-1"></i> 2. บันทึกความเห็นหัวหน้าสำนักงาน และมอบหมายคนขับ
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action_type" value="office">
                        <div class="mb-3">
                            <label class="form-label fw-bold">ความเห็น</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="office_status" id="off_app" value="approved" checked>
                                <label class="form-check-label text-success fw-bold" for="off_app">ควรอนุญาต</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="office_status" id="off_rej" value="rejected">
                                <label class="form-check-label text-danger fw-bold" for="off_rej">ไม่อนุญาต</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">มอบหมายพนักงานขับรถ <span class="text-danger">*</span></label>
                            <input type="text" name="office_driver_assigned" class="form-control form-control-sm" 
                                   value="นายธเนศ อินเอิบ" placeholder="ระบุชื่อพนักงานขับรถ" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">เหตุผล / ข้อเสนอแนะเพิ่มเติม</label>
                            <textarea name="office_reason" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-paper-plane me-1"></i> บันทึกและส่งต่อคณบดี
                        </button>
                    </form>

                <!-- 3. คณบดีคณะวิทยาการจัดการ -->
                <?php elseif ($booking['status'] == 'pending_dean'): ?>
                    <div class="alert alert-warning p-2 small mb-3">
                        <i class="fas fa-stamp me-1"></i> 3. คณบดีคณะวิทยาการจัดการ พิจารณาสั่งการ
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action_type" value="dean">
                        <div class="mb-3">
                            <label class="form-label fw-bold">คำสั่ง</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="dean_status" id="dean_app" value="approved" checked>
                                <label class="form-check-label text-success fw-bold fs-6" for="dean_app">✓ อนุญาต</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="dean_status" id="dean_rej" value="rejected">
                                <label class="form-check-label text-danger fw-bold fs-6" for="dean_rej">✗ ไม่อนุญาต</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">เหตุผล (กรณีไม่อนุญาต หรือข้อสั่งการอื่นๆ)</label>
                            <textarea name="dean_reason" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100 py-2">
                            <i class="fas fa-signature me-1"></i> สั่งการ / ลงนาม
                        </button>
                    </form>

                <!-- 4. พนักงานขับรถยนต์ -->
                <?php elseif ($booking['status'] == 'pending_driver'): ?>
                    <div class="alert alert-success p-2 small mb-3">
                        <i class="fas fa-car me-1"></i> 4. คณบดีอนุมัติแล้ว ลงชื่อรับทราบภารกิจ
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action_type" value="driver">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="driver_ack" checked required>
                                <label class="form-check-label" for="driver_ack">
                                    บันทึกรับทราบการขอใช้รถยนต์แล้ว (นายธเนศ อินเอิบ)
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-check-double me-1"></i> ยืนยันรับทราบงาน
                        </button>
                    </form>

                <?php else: ?>
                    <div class="text-center py-3">
                        <?php if ($booking['status'] == 'completed'): ?>
                            <i class="fas fa-check-circle text-success fs-1 mb-2 d-block"></i>
                            <span class="text-success fw-bold">คำขอนี้ได้รับการอนุมัติเสร็จสมบูรณ์แล้ว</span>
                        <?php elseif ($booking['status'] == 'rejected'): ?>
                            <i class="fas fa-times-circle text-danger fs-1 mb-2 d-block"></i>
                            <span class="text-danger fw-bold">คำขอนี้ไม่ได้รับการอนุมัติ</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <hr>
            <div class="d-grid gap-2">
                <a href="print_form.php?id=<?= $booking['id'] ?>" target="_blank" class="btn btn-outline-dark btn-sm">
                    <i class="fas fa-print me-1"></i> ดูตัวอย่างก่อนพิมพ์แบบราชการ
                </a>
                <a href="index.php" class="btn btn-light btn-sm text-secondary">
                    <i class="fas fa-home me-1"></i> กลับหน้าหลัก
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
