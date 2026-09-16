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

        $pdo->prepare("
            UPDATE approvals SET 
                facility_status = ?, facility_fuel = ?, facility_allowance = ?, 
                facility_other = ?, facility_signer = ?, facility_comment = ?, 
                office_driver_assigned = ?,
                facility_signed_at = datetime('now') 
            WHERE booking_id = ?
        ")->execute([$facility_status, $facility_fuel, $facility_allowance, $facility_other, $facility_signer, $facility_comment, $office_driver_assigned, $bookingId]);

        $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?")->execute([$newBookingStatus, $bookingId]);
        header("Location: booking_detail.php?id=$bookingId&msg=saved");
        exit;
    }

    // ยกเลิกผลการพิจารณาเพื่อแก้ไขใหม่
    if ($actionType === 'revert_facility') {
        $pdo->prepare("UPDATE bookings SET status = 'pending_facility' WHERE id = ?")->execute([$bookingId]);
        $pdo->prepare("UPDATE approvals SET facility_status = NULL, facility_signed_at = NULL WHERE booking_id = ?")->execute([$bookingId]);
        header("Location: booking_detail.php?id=$bookingId&msg=reset");
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
                            <span class="text-muted">นางซูไบดะห์ หะยีมะ</span><br>
                            <small class="text-secondary">(ให้ความเห็นในเอกสาร)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 border rounded bg-white h-100">
                            <strong>2. คณบดีคณะฯ</strong><br>
                            <span class="text-muted">ผศ. ดร.บงกช กมลเปรม</span><br>
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
                    </form>

                <?php elseif (in_array($booking['status'], ['approved', 'completed', 'pending_office', 'pending_dean', 'pending_driver'])): ?>
                    <!-- สถานะ: เห็นชอบแล้ว พร้อมพิมพ์เสนอต่อ -->
                    <div class="text-center py-2">
                        <div class="mb-3">
                            <i class="fas fa-check-circle text-success fs-1"></i>
                        </div>
                        <h6 class="fw-bold text-success mb-1">หัวหน้างานอาคารสถานที่เห็นชอบแล้ว</h6>
                        <p class="small text-muted mb-3">
                            คำขอนี้ได้รับการตรวจสอบและบันทึกความเห็นชอบในระบบแล้ว พร้อมสำหรับพิมพ์แบบฟอร์มเพื่อเสนอลงนามตามลำดับ
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

                        <a href="print_form.php?id=<?= $booking['id'] ?>" target="_blank" class="btn btn-success btn-lg w-100 fw-bold py-2 mb-2 shadow-sm">
                            <i class="fas fa-print me-1"></i> พิมพ์แบบฟอร์มราชการ (A4)
                        </a>

                        <form method="POST" onsubmit="return confirm('ยืนยันที่จะยกเลิกผลการพิจารณาเพื่อกลับไปพิจารณาใหม่หรือไม่?');" class="mt-2">
                            <input type="hidden" name="action_type" value="revert_facility">
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="fas fa-undo me-1"></i> แก้ไข / พิจารณาใหม่
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
