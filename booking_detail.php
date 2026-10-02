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
    LEFT JOIN vehicles v ON b.vehicle_id = v.id 
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
    
    // 1. หัวหน้างานอาคารสถานที่ (ขั้นตอนเดียวในระบบออนไลน์ จากนั้นพิมพ์เสนอต่อ)
    if ($actionType === 'facility') {
        $facility_status = $_POST['facility_status'] ?? 'approved';
        $facility_fuel = isset($_POST['facility_fuel']) ? 1 : 0;
        $facility_allowance = isset($_POST['facility_allowance']) ? 1 : 0;
        $facility_other = trim($_POST['facility_other'] ?? '');
        $facility_comment = trim($_POST['facility_comment'] ?? '');
        $office_driver_assigned = trim($_POST['office_driver_assigned'] ?? 'นายธเนศ อินเอิบ');
        $facility_signer = $currentUser['fullname'] ?? 'นายเอกสิทธิ์ คงพิทักษ์';

        $newBookingStatus = ($facility_status === 'approved') ? 'approved' : 'rejected';

        $now = date('Y-m-d H:i:s');
        $pdo->prepare("
            UPDATE approvals SET 
                facility_status = ?, facility_fuel = ?, facility_allowance = ?, 
                facility_other = ?, facility_signer = ?, facility_comment = ?, 
                office_driver_assigned = ?,
                facility_signed_at = ? 
            WHERE booking_id = ?
        ")->execute([$facility_status, $facility_fuel, $facility_allowance, $facility_other, $facility_signer, $facility_comment, $office_driver_assigned, $now, $bookingId]);

        $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?")->execute([$newBookingStatus, $bookingId]);
        header("Location: booking_detail.php?id=$bookingId&msg=saved");
        exit;
    }

    // ปฏิเสธคำขอเนื่องจากเป็นข้อมูลเท็จ / สแปม
    if ($actionType === 'reject_fraud') {
        $fakeReason = trim($_POST['fake_reason'] ?? 'ข้อมูลเท็จ / สแปม');
        $pdo->prepare("UPDATE bookings SET status = 'rejected_fraud', is_flagged_fake = 1, fake_reason = ? WHERE id = ?")->execute([$fakeReason, $bookingId]);
        $pdo->prepare("UPDATE approvals SET facility_status = 'rejected', facility_comment = ? WHERE booking_id = ?")->execute(['ปฏิเสธเนื่องจากเป็นข้อมูลเท็จ: ' . $fakeReason, $bookingId]);
        header("Location: booking_detail.php?id=$bookingId&msg=fraud_rejected");
        exit;
    }

    // ยกเลิกผลการพิจารณาเพื่อแก้ไขใหม่
    if ($actionType === 'revert_facility') {
        $pdo->prepare("UPDATE bookings SET status = 'pending_facility', is_flagged_fake = 0, fake_reason = NULL WHERE id = ?")->execute([$bookingId]);
        $pdo->prepare("UPDATE approvals SET facility_status = NULL, facility_signed_at = NULL, facility_comment = NULL WHERE booking_id = ?")->execute([$bookingId]);
        header("Location: booking_detail.php?id=$bookingId&msg=reset");
        exit;
    }

    // บันทึกข้อมูลการสิ้นสุดการใช้รถ (Vehicle Return & Completion)
    if ($actionType === 'complete_trip') {
        $actual_end_datetime = trim($_POST['actual_end_datetime'] ?? '');
        if (empty($actual_end_datetime)) {
            $actual_end_datetime = date('Y-m-d H:i:s');
        } else {
            $actual_end_datetime = str_replace('T', ' ', $actual_end_datetime);
            if (strlen($actual_end_datetime) == 16) {
                $actual_end_datetime .= ':00';
            }
        }
        $start_mileage = (isset($_POST['start_mileage']) && $_POST['start_mileage'] !== '') ? (int)$_POST['start_mileage'] : null;
        $end_mileage = (isset($_POST['end_mileage']) && $_POST['end_mileage'] !== '') ? (int)$_POST['end_mileage'] : null;
        $fuel_level = trim($_POST['fuel_level'] ?? 'เต็มถัง');
        $vehicle_condition = trim($_POST['vehicle_condition'] ?? 'ปกติเรียบร้อยดี');
        $return_notes = trim($_POST['return_notes'] ?? '');
        $returned_by = trim($_POST['returned_by'] ?? ($approval['office_driver_assigned'] ?? 'นายธเนศ อินเอิบ'));
        $return_recorded_by = $currentUser['fullname'] ?? 'ผู้ดูแลระบบ';

        $pdo->prepare("
            UPDATE bookings SET 
                status = 'completed',
                actual_end_datetime = ?,
                start_mileage = ?,
                end_mileage = ?,
                fuel_level = ?,
                vehicle_condition = ?,
                return_notes = ?,
                returned_by = ?,
                return_recorded_by = ?,
                return_recorded_at = ?
            WHERE id = ?
        ")->execute([
            $actual_end_datetime,
            $start_mileage,
            $end_mileage,
            $fuel_level,
            $vehicle_condition,
            $return_notes,
            $returned_by,
            $return_recorded_by,
            date('Y-m-d H:i:s'),
            $bookingId
        ]);

        header("Location: booking_detail.php?id=$bookingId&msg=completed");
        exit;
    }

    // ยกเลิกสถานะสิ้นสุดการใช้รถ เพื่อกลับไปเป็นสถานะเห็นชอบแล้ว
    if ($actionType === 'revert_complete') {
        $pdo->prepare("UPDATE bookings SET status = 'approved' WHERE id = ?")->execute([$bookingId]);
        header("Location: booking_detail.php?id=$bookingId&msg=reverted_complete");
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

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'fraud_rejected'): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-shield-virus me-2"></i> ปฏิเสธคำขอนี้เนื่องจากเป็นข้อมูลเท็จ/สแปม เรียบร้อยแล้ว (ปลดออกจากปฏิทินและตารางงานแล้ว)
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'completed'): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-flag-checkered me-2"></i> <strong>บันทึกสิ้นสุดการใช้รถเรียบร้อยแล้ว:</strong> ระบบได้จัดเก็บข้อมูลการส่งมอบคืนยานพาหนะ วันเวลาจริง เลขไมล์ และสภาพรถยนต์เข้าสู่ระบบเรียบร้อยแล้ว
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'reverted_complete'): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-undo me-2"></i> ยกเลิกสถานะสิ้นสุดการใช้รถ กลับสู่สถานะเห็นชอบแล้วเรียบร้อย
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'reset'): ?>
<div class="alert alert-secondary alert-dismissible fade show" role="alert">
    <i class="fas fa-undo me-2"></i> ยกเลิกผลการพิจารณาเพื่อกลับไปพิจารณาใหม่เรียบร้อยแล้ว
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
                                <?php if (!empty($booking['requester_phone'])): ?>
                                    <br><span class="text-dark small"><i class="fas fa-phone-alt text-success me-1"></i>โทร: <strong><?= htmlspecialchars($booking['requester_phone']) ?></strong></span>
                                <?php endif; ?>
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

        <?php if ($isAdmin): ?>
        <!-- กล่องข้อมูลความปลอดภัยและการตรวจสอบ (Audit & Security Info) -->
        <div class="card card-custom p-3 mb-4 border-start border-4 border-info bg-light">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-shield-alt text-info me-2"></i>ข้อมูลความปลอดภัยและการตรวจสอบ (Security & Audit)
                </h6>
                <?php if (!empty($booking['is_flagged_fake']) || $booking['status'] === 'rejected_fraud'): ?>
                    <span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i> ข้อมูลเท็จ / สแปม</span>
                <?php else: ?>
                    <span class="badge bg-success"><i class="fas fa-check-shield me-1"></i> ผ่านการตรวจสอบ</span>
                <?php endif; ?>
            </div>
            <div class="row g-2 small">
                <div class="col-sm-6">
                    <strong>IP Address:</strong> <code><?= htmlspecialchars($booking['client_ip'] ?? 'Local/N/A') ?></code>
                </div>
                <div class="col-sm-6">
                    <strong>ประเภทผู้ยื่น:</strong> <?= (!empty($booking['user_id'])) ? '<span class="text-success fw-semibold">สมาชิกในระบบ (ID: ' . $booking['user_id'] . ')</span>' : '<span class="text-muted">บุคคลภายนอก (ผ่าน Math Captcha)</span>' ?>
                </div>
                <div class="col-12 text-truncate" title="<?= htmlspecialchars($booking['user_agent'] ?? '-') ?>">
                    <strong>อุปกรณ์/เบราว์เซอร์:</strong> <?= htmlspecialchars($booking['user_agent'] ?? '-') ?>
                </div>
                <?php if (!empty($booking['fake_reason'])): ?>
                <div class="col-12 text-danger">
                    <strong>เหตุผลที่ระบุว่าเท็จ:</strong> <?= htmlspecialchars($booking['fake_reason']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- กล่องขั้นตอนการพิจารณาและการเสนอเอกสาร -->
        <div class="card card-custom p-4">
            <h5 class="fw-bold mb-3 text-dark">
                <i class="fas fa-tasks text-primary me-2"></i>ขั้นตอนการพิจารณาและเสนอเอกสาร
            </h5>

            <!-- 1. ขั้นตอนในระบบออนไลน์: หัวหน้างานอาคารสถานที่ -->
            <div class="border rounded-3 p-3 mb-3 <?= (in_array($booking['status'], ['approved', 'completed', 'pending_office', 'pending_dean', 'pending_driver'])) ? 'border-success bg-success-subtle' : (($booking['status'] == 'pending_facility') ? 'border-warning bg-warning-subtle' : 'bg-light') ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">
                        <span class="badge bg-primary me-2"><i class="fas fa-desktop me-1"></i> ขั้นตอนในระบบ</span>
                        ความเห็นของหัวหน้างานอาคารสถานที่
                    </h6>
                    <?php if (in_array($booking['status'], ['approved', 'completed', 'pending_office', 'pending_dean', 'pending_driver'])): ?>
                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> เห็นชอบแล้ว</span>
                    <?php elseif ($booking['status'] == 'rejected'): ?>
                        <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> ไม่เห็นชอบ</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> รอพิจารณา</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($approval['facility_status'])): ?>
                    <div class="small">
                        <div><strong>ผลการพิจารณา:</strong> <?= ($approval['facility_status'] == 'approved') ? 'เห็นชอบ' : 'ไม่เห็นชอบ' ?></div>
                        <div><strong>การสนับสนุน:</strong> 
                            <?= ($approval['facility_fuel']) ? '✓ ค่าน้ำมันเชื้อเพลิง ' : '' ?>
                            <?= ($approval['facility_allowance']) ? '✓ เบี้ยเลี้ยง/ค่าตอบแทน ' : '' ?>
                            <?= (!empty($approval['facility_other'])) ? ' (อื่นๆ: ' . htmlspecialchars($approval['facility_other']) . ')' : '' ?>
                            <?= (!$approval['facility_fuel'] && !$approval['facility_allowance'] && empty($approval['facility_other'])) ? 'ไม่ระบุ' : '' ?>
                        </div>
                        <?php if (!empty($approval['office_driver_assigned'])): ?>
                            <div><strong>พนักงานขับรถ:</strong> <?= htmlspecialchars($approval['office_driver_assigned']) ?></div>
                        <?php endif; ?>
                        <div><strong>ผู้ลงนาม:</strong> <?= htmlspecialchars($approval['facility_signer'] ?? 'นายเอกสิทธิ์ คงพิทักษ์') ?> (<?= thaiDate($approval['facility_signed_at']) ?>)</div>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">รอหัวหน้างานอาคารสถานที่ (นายเอกสิทธิ์ คงพิทักษ์) บันทึกความเห็นชอบและรายการสนับสนุน</p>
                <?php endif; ?>
            </div>

            <!-- 2. ขั้นตอนต่อไป: เสนอลงนามในเอกสารจริง (ออฟไลน์) -->
            <div class="border rounded-3 p-3 bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0 text-secondary">
                        <span class="badge bg-secondary me-2"><i class="fas fa-file-signature me-1"></i> ขั้นตอนเอกสารจริง</span>
                        การเสนอลงนามในแบบฟอร์มขอใช้รถยนต์ (กระดาษ A4)
                    </h6>
                </div>
                <p class="small text-muted mb-2">
                    เมื่อหัวหน้างานอาคารสถานที่ลงนามเห็นชอบในระบบแล้ว ให้คลิกพิมพ์แบบฟอร์ม (A4) เพื่อนำเสนอผู้บริหารลงนามต่อไปตามระเบียบ:
                </p>
                <div class="row g-2 small">
                    <div class="col-md-4">
                        <div class="p-2 border rounded bg-white h-100">
                            <strong>1. หัวหน้าสำนักงาน</strong><br>
                            <span class="text-muted">นางสาววิภาดา ทองปิ่น</span><br>
                            <small class="text-secondary">(ให้ความเห็นในเอกสาร)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 border rounded bg-white h-100">
                            <strong>2. คณบดีคณะฯ</strong><br>
                            <span class="text-muted">อาจารย์ ดร.สุมาลี กรดกางกั้น</span><br>
                            <small class="text-secondary">(ลงนามคำสั่งอนุมัติ)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 border rounded bg-white h-100">
                            <strong>3. พนักงานขับรถ</strong><br>
                            <span class="text-muted"><?= htmlspecialchars($approval['office_driver_assigned'] ?? 'นายธเนศ อินเอิบ') ?></span><br>
                            <small class="text-secondary">(ลงชื่อรับทราบภารกิจ)</small>
                        </div>
                    </div>
                </div>

                <?php if (in_array($booking['status'], ['approved', 'completed', 'pending_office', 'pending_dean', 'pending_driver'])): ?>
                    <div class="mt-3 text-center">
                        <a href="print_form.php?id=<?= $booking['id'] ?>" target="_blank" class="btn btn-gold btn-sm px-4 fw-bold shadow-sm">
                            <i class="fas fa-print me-1"></i> พิมพ์แบบฟอร์มราชการ (A4) เพื่อเสนอลงนามต่อ
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 3. ข้อมูลการสิ้นสุดการใช้รถ (Vehicle Return & Trip Completion) -->
            <?php if ($booking['status'] === 'completed'): ?>
            <div class="border border-primary rounded-3 p-3 bg-primary-subtle mt-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom border-primary-subtle">
                    <h6 class="fw-bold mb-0 text-primary">
                        <span class="badge bg-primary text-white me-2"><i class="fas fa-flag-checkered me-1"></i> สิ้นสุดการใช้รถแล้ว</span>
                        บันทึกข้อมูลการสิ้นสุดการใช้รถและการส่งมอบคืนยานพาหนะ
                    </h6>
                    <?php if ($isAdmin): ?>
                    <div class="d-flex gap-2 mt-2 mt-sm-0">
                        <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalCompleteTrip">
                            <i class="fas fa-edit me-1"></i> แก้ไขข้อมูลสิ้นสุด
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="row g-3 small">
                    <div class="col-md-6">
                        <div class="p-2 bg-white rounded border h-100">
                            <div class="text-muted mb-1"><i class="fas fa-calendar-check text-success me-1"></i> <strong>วัน-เวลาสิ้นสุดการใช้รถจริง:</strong></div>
                            <div class="fs-6 fw-bold text-dark"><?= !empty($booking['actual_end_datetime']) ? thaiDate($booking['actual_end_datetime']) : '-' ?></div>
                            <div class="text-muted small mt-1">กำหนดเดิม: <?= thaiDate($booking['end_datetime']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 bg-white rounded border h-100">
                            <div class="text-muted mb-1"><i class="fas fa-id-badge text-primary me-1"></i> <strong>พนักงานขับรถ / ผู้ส่งมอบคืน:</strong></div>
                            <div class="fs-6 fw-bold text-dark"><?= htmlspecialchars($booking['returned_by'] ?? ($approval['office_driver_assigned'] ?? 'นายธเนศ อินเอิบ')) ?></div>
                            <div class="text-muted small mt-1">ผู้บันทึก: <?= htmlspecialchars($booking['return_recorded_by'] ?? '-') ?> (<?= !empty($booking['return_recorded_at']) ? thaiDateShort($booking['return_recorded_at']) : '' ?>)</div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-2 bg-white rounded border text-center h-100">
                            <div class="text-muted small mb-1">เลขไมล์ก่อนเดินทาง</div>
                            <div class="fw-bold text-dark fs-6"><?= !empty($booking['start_mileage']) ? number_format($booking['start_mileage']) . ' กม.' : '-' ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 bg-white rounded border text-center h-100">
                            <div class="text-muted small mb-1">เลขไมล์เมื่อสิ้นสุด</div>
                            <div class="fw-bold text-dark fs-6"><?= !empty($booking['end_mileage']) ? number_format($booking['end_mileage']) . ' กม.' : '-' ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 bg-white rounded border text-center h-100">
                            <div class="text-muted small mb-1">ระยะทางที่ใช้จริง</div>
                            <div class="fw-bold text-primary fs-6">
                                <?= (!empty($booking['end_mileage']) && !empty($booking['start_mileage']) && $booking['end_mileage'] >= $booking['start_mileage']) ? number_format($booking['end_mileage'] - $booking['start_mileage']) . ' กม.' : '-' ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-2 bg-white rounded border h-100">
                            <span class="text-muted"><strong>สภาพรถยนต์เมื่อส่งคืน:</strong></span>
                            <?php 
                                $isClean = ($booking['vehicle_condition'] == 'ปกติเรียบร้อยดี' || empty($booking['vehicle_condition']));
                                $condBadge = $isClean ? 'bg-success' : 'bg-warning text-dark';
                            ?>
                            <span class="badge <?= $condBadge ?> ms-1"><?= htmlspecialchars($booking['vehicle_condition'] ?? 'ปกติเรียบร้อยดี') ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 bg-white rounded border h-100">
                            <span class="text-muted"><strong>ระดับน้ำมันคงเหลือ:</strong></span>
                            <span class="badge bg-info text-dark ms-1"><?= htmlspecialchars($booking['fuel_level'] ?? 'เต็มถัง') ?></span>
                        </div>
                    </div>

                    <?php if (!empty($booking['return_notes'])): ?>
                    <div class="col-12">
                        <div class="p-2 bg-white rounded border">
                            <strong>หมายเหตุหลังเสร็จสิ้นภารกิจ:</strong> <?= nl2br(htmlspecialchars($booking['return_notes'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif (in_array($booking['status'], ['approved', 'pending_office', 'pending_dean', 'pending_driver'])): ?>
            <!-- เมื่ออนุมัติแล้ว และรอสิ้นสุดการใช้รถ -->
            <div class="border rounded-3 p-3 bg-light mt-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0 text-dark">
                        <span class="badge bg-secondary me-2"><i class="fas fa-flag-checkered me-1"></i> ขั้นตอนสิ้นสุด</span>
                        การสิ้นสุดการใช้รถและการส่งคืนยานพาหนะ
                    </h6>
                    <?php if (strtotime($booking['end_datetime']) <= time()): ?>
                        <span class="badge bg-info text-dark"><i class="fas fa-clock-rotate-left me-1"></i> ครบกำหนดเวลาสิ้นสุดแล้ว</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark"><i class="fas fa-car-side me-1"></i> อยู่ระหว่างใช้งาน / รอเดินทาง</span>
                    <?php endif; ?>
                </div>

                <p class="small text-muted mb-2">
                    เมื่อเสร็จสิ้นภารกิจการเดินทางและนำรถยนต์กลับมาส่งมอบคืนแล้ว เจ้าหน้าที่หรือพนักงานขับรถสามารถบันทึกข้อมูลสิ้นสุดการใช้รถ (เลขไมล์, สภาพรถ, น้ำมัน) เพื่อเก็บบันทึกประวัติและปิดคำขอได้
                </p>

                <?php if ($isAdmin): ?>
                <div class="mt-2 text-center text-sm-start">
                    <button type="button" class="btn btn-primary btn-sm px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCompleteTrip">
                        <i class="fas fa-flag-checkered me-1"></i> บันทึกข้อมูลสิ้นสุดการใช้รถ (ส่งมอบคืนยานพาหนะ)
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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

                <!-- สิทธิ์ Admin: ขั้นตอนเดียวออนไลน์ (หัวหน้างานอาคารสถานที่) -->
                <?php if ($booking['status'] == 'pending_facility'): ?>
                    <div class="alert alert-primary p-3 small mb-3">
                        <div class="fw-bold fs-6 mb-1"><i class="fas fa-signature me-1"></i> พิจารณาโดย: หัวหน้างานอาคารสถานที่</div>
                        <div class="text-muted">ตรวจสอบยานพาหนะ รายการสนับสนุน และมอบหมายคนขับ เมื่อบันทึกเห็นชอบแล้วจะสามารถพิมพ์แบบเสนอต่อได้ทันที</div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action_type" value="facility">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">ผลการพิจารณา <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="facility_status" id="fac_app" value="approved" checked>
                                    <label class="form-check-label text-success fw-bold" for="fac_app">
                                        <i class="fas fa-check-circle me-1"></i> เห็นชอบ (พร้อมพิมพ์เสนอต่อ)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="facility_status" id="fac_rej" value="rejected">
                                    <label class="form-check-label text-danger fw-bold" for="fac_rej">
                                        <i class="fas fa-times-circle me-1"></i> ไม่เห็นชอบ
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 bg-light p-3 rounded-3 border">
                            <label class="form-label fw-bold small text-dark mb-2">
                                <i class="fas fa-gas-pump text-warning me-1"></i> รายการสนับสนุนงบประมาณ
                            </label>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="facility_fuel" value="1" id="fuel_check" checked>
                                <label class="form-check-label" for="fuel_check">ค่าน้ำมันเชื้อเพลิง</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="facility_allowance" value="1" id="allow_check" checked>
                                <label class="form-check-label" for="allow_check">เบี้ยเลี้ยง / ค่าตอบแทน</label>
                            </div>
                            <div>
                                <input type="text" name="facility_other" class="form-control form-control-sm" placeholder="อื่นๆ ระบุ (ถ้ามี)">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">
                                <i class="fas fa-id-card text-primary me-1"></i> มอบหมายพนักงานขับรถ <span class="text-danger">*</span>
                            </label>
                            <select name="office_driver_assigned" class="form-select form-select-sm" required>
                                <option value="นายธเนศ อินเอิบ" selected>นายธเนศ อินเอิบ (พนักงานขับรถยนต์)</option>
                                <?php foreach ($drivers as $d): ?>
                                    <?php if ($d !== 'นายธเนศ อินเอิบ'): ?>
                                        <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">ความเห็น / ข้อเสนอแนะเพิ่มเติม</label>
                            <textarea name="facility_comment" class="form-control form-control-sm" rows="2" placeholder="ความเห็นเพิ่มเติม (ถ้ามี)"></textarea>
                        </div>

                        <div class="mb-3 small text-muted bg-white p-2 border rounded">
                            <i class="fas fa-user-check text-success me-1"></i> ผู้ลงนาม: <strong><?= htmlspecialchars($currentUser['fullname'] ?? 'นายเอกสิทธิ์ คงพิทักษ์') ?></strong><br>
                            <span class="text-muted">(หัวหน้างานอาคารสถานที่และยานพาหนะ)</span>
                        </div>

                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold shadow-sm">
                            <i class="fas fa-check-circle me-1"></i> บันทึกเห็นชอบและเปิดให้พิมพ์เสนอต่อ
                        </button>

                        <div class="mt-3 pt-2 border-top">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100" data-bs-toggle="modal" data-bs-target="#modalRejectFraud">
                                <i class="fas fa-shield-virus me-1"></i> ปฏิเสธ (ข้อมูลเท็จ / สแปม)
                            </button>
                        </div>
                    </form>

                <?php elseif ($booking['status'] == 'completed'): ?>
                    <!-- สถานะ: สิ้นสุดการใช้รถแล้ว -->
                    <div class="text-center py-2">
                        <div class="mb-2">
                            <i class="fas fa-flag-checkered text-primary fs-1"></i>
                        </div>
                        <h6 class="fw-bold text-primary mb-1">สิ้นสุดการใช้รถเรียบร้อยแล้ว</h6>
                        <span class="badge bg-primary text-white mb-3">บันทึกส่งมอบคืนยานพาหนะแล้ว</span>

                        <div class="bg-light p-3 rounded text-start small mb-3 border">
                            <div class="mb-1">
                                <strong>เวลาสิ้นสุดจริง:</strong> <?= !empty($booking['actual_end_datetime']) ? thaiDateShort($booking['actual_end_datetime']) : '-' ?>
                            </div>
                            <div class="mb-1">
                                <strong>ระยะทางที่ใช้:</strong> 
                                <?= (!empty($booking['end_mileage']) && !empty($booking['start_mileage']) && $booking['end_mileage'] >= $booking['start_mileage']) ? number_format($booking['end_mileage'] - $booking['start_mileage']) . ' กม.' : (!empty($booking['end_mileage']) ? number_format($booking['end_mileage']) . ' กม. (เลขไมล์คืน)' : 'ไม่ได้ระบุ') ?>
                            </div>
                            <div class="mb-1">
                                <strong>สภาพรถ:</strong> <span class="badge bg-success-subtle text-success"><?= htmlspecialchars($booking['vehicle_condition'] ?? 'ปกติ') ?></span>
                            </div>
                            <div>
                                <strong>ผู้ส่งคืน:</strong> <?= htmlspecialchars($booking['returned_by'] ?? ($approval['office_driver_assigned'] ?? '-')) ?>
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm w-100 fw-bold py-2 mb-2" data-bs-toggle="modal" data-bs-target="#modalCompleteTrip">
                            <i class="fas fa-edit me-1"></i> แก้ไขข้อมูลสิ้นสุดการใช้รถ
                        </button>

                        <a href="print_form.php?id=<?= $booking['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                            <i class="fas fa-print me-1"></i> พิมพ์แบบฟอร์มราชการ (A4)
                        </a>

                        <form method="POST" onsubmit="return confirm('ยืนยันที่จะยกเลิกสถานะสิ้นสุดการใช้รถ และย้อนกลับไปเป็นสถานะเห็นชอบแล้วหรือไม่?');" class="mt-2">
                            <input type="hidden" name="action_type" value="revert_complete">
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="fas fa-undo me-1"></i> ยกเลิกสถานะสิ้นสุด (กลับไปเห็นชอบแล้ว)
                            </button>
                        </form>
                    </div>

                <?php elseif (in_array($booking['status'], ['approved', 'pending_office', 'pending_dean', 'pending_driver'])): ?>
                    <!-- สถานะ: เห็นชอบแล้ว พร้อมพิมพ์เสนอต่อ / อยู่ระหว่างใช้งาน -->
                    <div class="text-center py-2">
                        <div class="mb-2">
                            <i class="fas fa-check-circle text-success fs-1"></i>
                        </div>
                        <h6 class="fw-bold text-success mb-1">หัวหน้างานอาคารสถานที่เห็นชอบแล้ว</h6>
                        <p class="small text-muted mb-3">
                            คำขอนี้ได้รับการตรวจสอบและบันทึกความเห็นชอบในระบบแล้ว สามารถพิมพ์แบบฟอร์ม หรือบันทึกสิ้นสุดการใช้รถเมื่อเสร็จสิ้นภารกิจ
                        </p>

                        <div class="bg-light p-3 rounded text-start small mb-3 border">
                            <div class="mb-1">
                                <strong>ผู้พิจารณา:</strong> <?= htmlspecialchars($approval['facility_signer'] ?? 'นายเอกสิทธิ์ คงพิทักษ์') ?>
                            </div>
                            <div class="mb-1">
                                <strong>เวลาพิจารณา:</strong> <?= !empty($approval['facility_signed_at']) ? thaiDateShort($approval['facility_signed_at']) : 'บันทึกแล้ว' ?>
                            </div>
                            <div class="mb-1">
                                <strong>พนักงานขับรถ:</strong> <?= htmlspecialchars($approval['office_driver_assigned'] ?? 'นายธเนศ อินเอิบ') ?>
                            </div>
                            <div>
                                <strong>การสนับสนุน:</strong> 
                                <?= (!empty($approval['facility_fuel']) ? 'ค่าน้ำมัน' : '') ?>
                                <?= (!empty($approval['facility_allowance']) ? ' + ค่าเบี้ยเลี้ยง' : '') ?>
                                <?= (!empty($approval['facility_other']) ? ' (' . htmlspecialchars($approval['facility_other']) . ')' : '') ?>
                            </div>
                        </div>

                        <!-- กล่องบันทึกสิ้นสุดการใช้รถ -->
                        <div class="p-2 mb-3 bg-primary-subtle rounded border border-primary text-center">
                            <div class="small fw-bold text-primary mb-2">
                                <i class="fas fa-flag-checkered me-1"></i> สิ้นสุดภารกิจเดินทางแล้ว?
                            </div>
                            <button type="button" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCompleteTrip">
                                <i class="fas fa-flag-checkered me-1"></i> บันทึกสิ้นสุดการใช้รถ
                            </button>
                        </div>

                        <a href="print_form.php?id=<?= $booking['id'] ?>" target="_blank" class="btn btn-success btn-sm w-100 fw-bold py-2 mb-2 shadow-sm">
                            <i class="fas fa-print me-1"></i> พิมพ์แบบฟอร์มราชการ (A4)
                        </a>

                        <form method="POST" onsubmit="return confirm('ยืนยันที่จะยกเลิกผลการพิจารณาเพื่อกลับไปพิจารณาใหม่หรือไม่?');" class="mt-2">
                            <input type="hidden" name="action_type" value="revert_facility">
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="fas fa-undo me-1"></i> แก้ไข / พิจารณาใหม่
                            </button>
                        </form>
                    </div>

                <?php elseif ($booking['status'] == 'rejected_fraud'): ?>
                    <!-- สถานะ: ปฏิเสธเนื่องจากเป็นข้อมูลเท็จ / สแปม -->
                    <div class="text-center py-3">
                        <i class="fas fa-shield-virus text-danger fs-1 mb-2 d-block"></i>
                        <span class="text-danger fw-bold fs-6">ถูกระงับ: ข้อมูลเท็จ / สแปม</span>
                        <p class="small text-muted mt-2 mb-3">
                            คำขอนี้ถูกรายงานว่าเป็นข้อมูลเท็จและถูกตัดออกจากตารางงานเรียบร้อยแล้ว<br>
                            <?php if (!empty($booking['fake_reason'])): ?>
                                <span class="badge bg-danger mt-1">เหตุผล: <?= htmlspecialchars($booking['fake_reason']) ?></span>
                            <?php endif; ?>
                        </p>
                        
                        <form method="POST" onsubmit="return confirm('ยืนยันที่จะกู้คืนคำขอนี้กลับมาพิจารณาใหม่หรือไม่?');">
                            <input type="hidden" name="action_type" value="revert_facility">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                <i class="fas fa-undo me-1"></i> กู้คืนกลับมาพิจารณาใหม่
                            </button>
                        </form>
                    </div>

                <?php elseif ($booking['status'] == 'rejected'): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-times-circle text-danger fs-1 mb-2 d-block"></i>
                        <span class="text-danger fw-bold fs-6">คำขอนี้ไม่เห็นชอบ / ไม่อนุมัติ</span>
                        <p class="small text-muted mt-1">หัวหน้างานอาคารสถานที่บันทึกผลว่าไม่เห็นชอบ</p>
                        
                        <form method="POST" onsubmit="return confirm('ยืนยันที่จะเปิดให้พิจารณาคำขอนี้ใหม่อีกครั้งหรือไม่?');" class="mt-3">
                            <input type="hidden" name="action_type" value="revert_facility">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                <i class="fas fa-redo me-1"></i> เปิดพิจารณาใหม่อีกครั้ง
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="text-center py-3">
                        <span class="text-muted">สถานะ: <?= htmlspecialchars($booking['status']) ?></span>
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

<!-- Modal ปฏิเสธข้อมูลเท็จ / สแปม -->
<div class="modal fade" id="modalRejectFraud" tabindex="-1" aria-labelledby="modalRejectFraudLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action_type" value="reject_fraud">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold" id="modalRejectFraudLabel">
                    <i class="fas fa-shield-virus me-2"></i> ปฏิเสธคำขอที่เป็นเท็จ / สแปม
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-3">
                    <i class="fas fa-exclamation-triangle me-1"></i> เมื่อยืนยัน สถานะคำขอนี้จะเปลี่ยนเป็น <strong>"ปฏิเสธ (ข้อมูลเท็จ/สแปม)"</strong> และจะถูกปลดออกจากปฏิทินและตารางงานทันที
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">ระบุเหตุผล / หมายเหตุ:</label>
                    <input type="text" name="fake_reason" class="form-control" value="ตรวจพบเป็นข้อมูลเท็จ / สแปมก่อกวน" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-danger btn-sm fw-bold">
                    <i class="fas fa-shield-virus me-1"></i> ยืนยันปฏิเสธข้อมูลเท็จ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal บันทึกข้อมูลการสิ้นสุดการใช้รถ (ส่งมอบคืนยานพาหนะ) -->
<div class="modal fade" id="modalCompleteTrip" tabindex="-1" aria-labelledby="modalCompleteTripLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action_type" value="complete_trip">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalCompleteTripLabel">
                    <i class="fas fa-flag-checkered me-2"></i> บันทึกข้อมูลการสิ้นสุดการใช้รถ (ส่งมอบคืนยานพาหนะ)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info py-2 px-3 small mb-3">
                    <i class="fas fa-info-circle me-1"></i> กรุณากรอกข้อมูลหลังเสร็จสิ้นภารกิจ เพื่อเก็บบันทึกประวัติการใช้รถยนต์ เลขไมล์ สภาพรถ และปิดคำขอจอง
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">วัน-เวลาสิ้นสุดการใช้รถจริง <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="actual_end_datetime" class="form-control" 
                               value="<?= !empty($booking['actual_end_datetime']) ? date('Y-m-d\TH:i', strtotime($booking['actual_end_datetime'])) : (!empty($booking['end_datetime']) ? date('Y-m-d\TH:i', strtotime($booking['end_datetime'])) : date('Y-m-d\TH:i')) ?>" required>
                        <small class="text-muted">กำหนดเดิมตามคำขอ: <?= thaiDate($booking['end_datetime']) ?></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">พนักงานขับรถ / ผู้ส่งมอบคืน <span class="text-danger">*</span></label>
                        <input type="text" name="returned_by" class="form-control" 
                               value="<?= htmlspecialchars($booking['returned_by'] ?? $approval['office_driver_assigned'] ?? 'นายธเนศ อินเอิบ') ?>" required>
                    </div>
                </div>

                <div class="row g-3 mb-3 bg-light p-3 rounded-3 border">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-dark"><i class="fas fa-tachometer-alt me-1 text-primary"></i> เลขไมล์ก่อนเดินทาง (กม.)</label>
                        <input type="number" name="start_mileage" id="start_mileage" class="form-control form-control-sm" 
                               placeholder="เช่น 120500" value="<?= htmlspecialchars($booking['start_mileage'] ?? '') ?>" oninput="calcDistance()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-dark"><i class="fas fa-tachometer-alt me-1 text-danger"></i> เลขไมล์เมื่อสิ้นสุด (กม.)</label>
                        <input type="number" name="end_mileage" id="end_mileage" class="form-control form-control-sm" 
                               placeholder="เช่น 120850" value="<?= htmlspecialchars($booking['end_mileage'] ?? '') ?>" oninput="calcDistance()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-dark"><i class="fas fa-route me-1 text-success"></i> ระยะทางที่ใช้จริง</label>
                        <div class="form-control form-control-sm bg-white text-primary fw-bold" id="total_distance_display">
                            <?= (!empty($booking['end_mileage']) && !empty($booking['start_mileage']) && $booking['end_mileage'] >= $booking['start_mileage']) ? number_format($booking['end_mileage'] - $booking['start_mileage']) . ' กม.' : '- กม.' ?>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">สภาพรถยนต์เมื่อส่งคืน <span class="text-danger">*</span></label>
                        <select name="vehicle_condition" class="form-select" required>
                            <?php $currCond = $booking['vehicle_condition'] ?? 'ปกติเรียบร้อยดี'; ?>
                            <option value="ปกติเรียบร้อยดี" <?= ($currCond == 'ปกติเรียบร้อยดี') ? 'selected' : '' ?>>✓ ปกติเรียบร้อยดี (พร้อมใช้งานต่อ)</option>
                            <option value="ต้องนำไปล้างทำความสะอาด" <?= ($currCond == 'ต้องนำไปล้างทำความสะอาด') ? 'selected' : '' ?>>ต้องนำไปล้างทำความสะอาด</option>
                            <option value="มีรอยเฉี่ยวชน / ชำรุดรอซ่อม" <?= ($currCond == 'มีรอยเฉี่ยวชน / ชำรุดรอซ่อม') ? 'selected' : '' ?>>มีรอยเฉี่ยวชน / ชำรุดรอซ่อม</option>
                            <option value="อุปกรณ์หรือเครื่องยนต์ขัดข้อง" <?= ($currCond == 'อุปกรณ์หรือเครื่องยนต์ขัดข้อง') ? 'selected' : '' ?>>อุปกรณ์หรือเครื่องยนต์ขัดข้อง</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">ระดับน้ำมันเชื้อเพลิงเมื่อสิ้นสุด</label>
                        <select name="fuel_level" class="form-select">
                            <?php $currFuel = $booking['fuel_level'] ?? 'เต็มถัง'; ?>
                            <option value="เต็มถัง" <?= ($currFuel == 'เต็มถัง') ? 'selected' : '' ?>>เต็มถัง</option>
                            <option value="3/4 ถัง" <?= ($currFuel == '3/4 ถัง') ? 'selected' : '' ?>>3/4 ถัง</option>
                            <option value="1/2 ถัง" <?= ($currFuel == '1/2 ถัง') ? 'selected' : '' ?>>1/2 ถัง</option>
                            <option value="1/4 ถัง" <?= ($currFuel == '1/4 ถัง') ? 'selected' : '' ?>>1/4 ถัง</option>
                            <option value="ใกล้หมด / ไฟเตือนติด" <?= ($currFuel == 'ใกล้หมด / ไฟเตือนติด') ? 'selected' : '' ?>>ใกล้หมด / ไฟเตือนติด</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">หมายเหตุ / บันทึกเพิ่มเติมหลังเสร็จสิ้นภารกิจ</label>
                    <textarea name="return_notes" class="form-control" rows="2" placeholder="ระบุรายละเอียดเพิ่มเติม เช่น รายการซ่อม ปัญหาการเดินทาง หรือข้อสังเกต (ถ้ามี)"><?= htmlspecialchars($booking['return_notes'] ?? '') ?></textarea>
                </div>

                <div class="small text-muted bg-light p-2 rounded">
                    <i class="fas fa-user-edit text-primary me-1"></i> ผู้บันทึกข้อมูล: <strong><?= htmlspecialchars($currentUser['fullname'] ?? 'ผู้ดูแลระบบ') ?></strong> (บันทึกเข้าระบบทันทีเมื่อกดยืนยัน)
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                    <i class="fas fa-save me-1"></i> บันทึกข้อมูลสิ้นสุดการใช้รถ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function calcDistance() {
    var start = parseFloat(document.getElementById('start_mileage').value);
    var end = parseFloat(document.getElementById('end_mileage').value);
    var display = document.getElementById('total_distance_display');
    if (!isNaN(start) && !isNaN(end) && end >= start) {
        display.innerText = (end - start).toLocaleString() + ' กม.';
    } else {
        display.innerText = '- กม.';
    }
}

document.addEventListener("DOMContentLoaded", function() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('action') === 'return' || params.get('action') === 'complete' || window.location.hash === '#modalCompleteTrip' || window.location.hash === '#returnSection') {
        var el = document.getElementById('modalCompleteTrip');
        if (el) {
            <?php if ($isAdmin): ?>
            var modal = bootstrap.Modal.getOrCreateInstance(el);
            modal.show();
            <?php else: ?>
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: 'บันทึกสิ้นสุดการใช้รถ',
                    text: 'กรุณาเข้าสู่ระบบในฐานะเจ้าหน้าที่/Admin เพื่อบันทึกข้อมูลการส่งมอบคืนรถและเลขไมล์',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    confirmButtonText: '<i class="fas fa-key me-1"></i> เข้าสู่ระบบ Admin',
                    cancelButtonText: 'ปิด'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                    }
                });
            }
            <?php endif; ?>
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
