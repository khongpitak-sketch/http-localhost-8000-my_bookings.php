<?php
// approvals.php - ศูนย์รวมการพิจารณาอนุมัติ 4 ขั้นตอน
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$userRole = $currentUser['role'];
$msg = $_GET['msg'] ?? '';

// จัดการคำสั่งของ Admin
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    $bId = (int)($_POST['booking_id'] ?? 0);

    // ปฏิเสธคำขอที่เป็นเท็จ / สแปม
    if ($action === 'reject_fraud' && $bId > 0) {
        $reason = trim($_POST['fake_reason'] ?? 'ข้อมูลเท็จ / สแปม');
        $pdo->prepare("UPDATE bookings SET status = 'rejected_fraud', is_flagged_fake = 1, fake_reason = ? WHERE id = ?")->execute([$reason, $bId]);
        $pdo->prepare("UPDATE approvals SET facility_status = 'rejected', facility_comment = ? WHERE booking_id = ?")->execute(['ปฏิเสธเนื่องจากเป็นข้อมูลเท็จ: ' . $reason, $bId]);
        header("Location: approvals.php?msg=fraud_rejected");
        exit;
    }

    // กู้คืนคำขอกลับมาพิจารณาใหม่
    if ($action === 'restore_fraud' && $bId > 0) {
        $pdo->prepare("UPDATE bookings SET status = 'pending_facility', is_flagged_fake = 0, fake_reason = NULL WHERE id = ?")->execute([$bId]);
        $pdo->prepare("UPDATE approvals SET facility_status = NULL, facility_comment = NULL WHERE booking_id = ?")->execute([$bId]);
        header("Location: approvals.php?msg=restored");
        exit;
    }
}

// คำขอที่รอหัวหน้างานอาคารสถานที่พิจารณา (เฉพาะคำขอปกติ ไม่ใช่ข้อมูลเท็จ)
$pendingSql = "SELECT b.*, v.brand_model, v.vehicle_type 
               FROM bookings b 
               JOIN vehicles v ON b.vehicle_id = v.id 
               WHERE b.status = 'pending_facility' AND (b.is_flagged_fake IS NULL OR b.is_flagged_fake = 0)
               ORDER BY b.id DESC";
$pendingList = $pdo->query($pendingSql)->fetchAll();

// คำขอที่เห็นชอบแล้ว / พร้อมพิมพ์เสนอต่อ
$approvedSql = "SELECT b.*, v.brand_model, v.vehicle_type 
                FROM bookings b 
                JOIN vehicles v ON b.vehicle_id = v.id 
                WHERE b.status IN ('approved', 'completed', 'pending_office', 'pending_dean', 'pending_driver') 
                ORDER BY b.id DESC";
$approvedList = $pdo->query($approvedSql)->fetchAll();

// คำขอที่เป็นเท็จ / สแปมที่ถูกปฏิเสธ
$fraudSql = "SELECT b.*, v.brand_model, v.vehicle_type 
             FROM bookings b 
             JOIN vehicles v ON b.vehicle_id = v.id 
             WHERE b.status = 'rejected_fraud' OR b.is_flagged_fake = 1
             ORDER BY b.id DESC";
$fraudList = $pdo->query($fraudSql)->fetchAll();

// คำขอทั้งหมดสำหรับแท็บประวัติ
$allBookings = $pdo->query("
    SELECT b.*, v.brand_model, v.vehicle_type 
    FROM bookings b 
    JOIN vehicles v ON b.vehicle_id = v.id 
    ORDER BY b.id DESC
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($msg === 'fraud_rejected'): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-shield-virus me-2"></i> ปฏิเสธคำขอเนื่องจากเป็นข้อมูลเท็จ/สแปม เรียบร้อยแล้ว (ปลดออกจากรายการรอพิจารณาและปฏิทินแล้ว)
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($msg === 'restored'): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-undo me-2"></i> กู้คืนคำขอกลับมาสู่สถานะรอพิจารณาเรียบร้อยแล้ว
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-signature text-primary me-2"></i>พิจารณาอนุมัติคำขอใช้รถยนต์ (หัวหน้างานอาคารสถานที่)
        </h4>
        <span class="text-muted small">
            ผู้ดำเนินการ: <strong><?= htmlspecialchars($currentUser['fullname']) ?></strong> (หัวหน้างานอาคารสถานที่และยานพาหนะ)
        </span>
    </div>
    <div>
        <span class="badge bg-warning text-dark fs-6 px-3 py-2 border">
            <i class="fas fa-clock me-1"></i> รอพิจารณา <?= count($pendingList) ?> รายการ
        </span>
    </div>
</div>

<div class="alert alert-info py-2 px-3 small mb-4">
    <i class="fas fa-info-circle me-1"></i> <strong>ระบบอนุมัติขั้นตอนเดียว:</strong> หัวหน้างานอาคารสถานที่พิจารณาเห็นชอบ มอบหมายคนขับ และสนับสนุนงบประมาณในระบบ จากนั้นพิมพ์แบบฟอร์มราชการ (A4) เพื่อเสนอลงนามตามลำดับสายงานต่อไป
</div>

<!-- แท็บเลือกมุมมอง -->
<ul class="nav nav-pills mb-4 bg-white p-2 rounded-3 shadow-sm" id="approvalTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pending-content" type="button" role="tab">
            <i class="fas fa-clock text-warning me-1"></i> รอพิจารณา (<?= count($pendingList) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="approved-tab" data-bs-toggle="pill" data-bs-target="#approved-content" type="button" role="tab">
            <i class="fas fa-check-circle text-success me-1"></i> เห็นชอบแล้ว / พร้อมพิมพ์ (<?= count($approvedList) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="all-tab" data-bs-toggle="pill" data-bs-target="#all-content" type="button" role="tab">
            <i class="fas fa-list text-primary me-1"></i> คำขอทั้งหมด (<?= count($allBookings) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold text-danger" id="fraud-tab" data-bs-toggle="pill" data-bs-target="#fraud-content" type="button" role="tab">
            <i class="fas fa-shield-virus text-danger me-1"></i> ข้อมูลเท็จ / สแปม (<?= count($fraudList) ?>)
        </button>
    </li>
</ul>

<div class="tab-content" id="approvalTabsContent">
    <!-- แท็บ 1: รอพิจารณา -->
    <div class="tab-pane fade show active" id="pending-content" role="tabpanel">
        <?php if (empty($pendingList)): ?>
            <div class="card card-custom p-5 text-center">
                <i class="fas fa-check-circle text-success fs-1 mb-3"></i>
                <h5 class="fw-bold text-dark">ไม่มีคำขอที่ค้างพิจารณา</h5>
                <p class="text-muted small">
                    คำขอใช้รถยนต์ทั้งหมดได้รับการพิจารณาเรียบร้อยแล้ว
                </p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($pendingList as $item): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-custom h-100 p-3 border-top border-4 border-warning">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['doc_no'] ?? '-') ?></span>
                            <div><?= getStatusBadge($item['status']) ?></div>
                        </div>

                        <h6 class="fw-bold text-primary mb-1"><?= htmlspecialchars($item['plate_number']) ?></h6>
                        <div class="text-muted small mb-2"><?= htmlspecialchars($item['brand_model']) ?></div>

                        <p class="text-dark small mb-2" style="min-height: 40px;">
                            <strong>ภารกิจ:</strong> <?= htmlspecialchars($item['purpose']) ?>
                        </p>

                        <div class="bg-light p-2 rounded-2 small mb-2">
                            <div><i class="fas fa-user text-secondary me-1"></i><strong>ผู้ขอ:</strong> <?= htmlspecialchars($item['requester_name']) ?></div>
                            <div><i class="fas fa-map-marker-alt text-danger me-1"></i><strong>ปลายทาง:</strong> <?= htmlspecialchars($item['route_to']) ?></div>
                            <div><i class="fas fa-calendar-alt text-primary me-1"></i><strong>วันที่:</strong> <?= thaiDateShort($item['start_datetime']) ?></div>
                        </div>

                        <!-- แถบตรวจสอบความปลอดภัย (Security Check) -->
                        <div class="d-flex justify-content-between align-items-center px-1 mb-3 small text-muted">
                            <span><i class="fas fa-network-wired me-1"></i>IP: <?= htmlspecialchars($item['client_ip'] ?? 'Local') ?></span>
                            <?php if (!empty($item['user_id'])): ?>
                                <span class="badge bg-success-subtle text-success border border-success"><i class="fas fa-user-check me-1"></i>สมาชิกในระบบ</span>
                            <?php else: ?>
                                <span class="badge bg-light text-secondary border"><i class="fas fa-shield-halved me-1 text-primary"></i>ผ่านรหัสป้องกัน</span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-auto d-flex gap-2">
                            <a href="booking_detail.php?id=<?= $item['id'] ?>" class="btn btn-warning flex-grow-1 fw-bold">
                                <i class="fas fa-pen-nib me-1"></i> พิจารณา
                            </a>
                            <form method="POST" action="approvals.php" class="d-inline" onsubmit="return confirm('ยืนยันปฏิเสธคำขอนี้เนื่องจากเป็นข้อมูลเท็จ / สแปม? (จะนำออกจากรายการรอพิจารณาและปฏิทินทันที)');">
                                <input type="hidden" name="action" value="reject_fraud">
                                <input type="hidden" name="booking_id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger" title="ปฏิเสธ (ข้อมูลเท็จ / สแปม)">
                                    <i class="fas fa-shield-virus"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- แท็บ 2: เห็นชอบแล้ว พร้อมพิมพ์ -->
    <div class="tab-pane fade" id="approved-content" role="tabpanel">
        <?php if (empty($approvedList)): ?>
            <div class="card card-custom p-5 text-center">
                <i class="fas fa-folder-open text-muted fs-1 mb-3"></i>
                <h5 class="fw-bold text-dark">ยังไม่มีคำขอที่ผ่านการเห็นชอบ</h5>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($approvedList as $item): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-custom h-100 p-3 border-top border-4 border-success">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['doc_no'] ?? '-') ?></span>
                            <div><?= getStatusBadge($item['status']) ?></div>
                        </div>

                        <h6 class="fw-bold text-success mb-1"><?= htmlspecialchars($item['plate_number']) ?></h6>
                        <div class="text-muted small mb-2"><?= htmlspecialchars($item['brand_model']) ?></div>

                        <p class="text-dark small mb-2" style="min-height: 40px;">
                            <strong>ภารกิจ:</strong> <?= htmlspecialchars($item['purpose']) ?>
                        </p>

                        <div class="bg-light p-2 rounded-2 small mb-3">
                            <div><i class="fas fa-user text-secondary me-1"></i><strong>ผู้ขอ:</strong> <?= htmlspecialchars($item['requester_name']) ?></div>
                            <div><i class="fas fa-map-marker-alt text-danger me-1"></i><strong>ปลายทาง:</strong> <?= htmlspecialchars($item['route_to']) ?></div>
                            <div><i class="fas fa-calendar-alt text-primary me-1"></i><strong>วันที่:</strong> <?= thaiDateShort($item['start_datetime']) ?></div>
                        </div>

                        <div class="mt-auto d-flex gap-2">
                            <a href="print_form.php?id=<?= $item['id'] ?>" target="_blank" class="btn btn-success flex-grow-1 fw-bold">
                                <i class="fas fa-print me-1"></i> พิมพ์แบบฟอร์ม A4
                            </a>
                            <a href="booking_detail.php?id=<?= $item['id'] ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- แท็บ: คำขอทั้งหมด -->
    <div class="tab-pane fade" id="all-content" role="tabpanel">
        <div class="card card-custom p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>เลขที่เอกสาร</th>
                            <th>ผู้ขอใช้รถ</th>
                            <th>รถยนต์</th>
                            <th>ภารกิจ / ปลายทาง</th>
                            <th>วันเวลาเดินทาง</th>
                            <th>สถานะ</th>
                            <th class="text-center">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allBookings as $b): ?>
                        <tr>
                            <td>
                                <span class="fw-bold"><?= htmlspecialchars($b['doc_no'] ?? '-') ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($b['requester_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($b['requester_department']) ?></small>
                            </td>
                            <td>
                                <div><strong><?= htmlspecialchars($b['plate_number']) ?></strong></div>
                                <small class="text-muted"><?= htmlspecialchars($b['brand_model']) ?></small>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($b['purpose']) ?>">
                                    <?= htmlspecialchars($b['purpose']) ?>
                                </div>
                                <small class="text-danger"><i class="fas fa-map-pin me-1"></i><?= htmlspecialchars($b['route_to']) ?></small>
                            </td>
                            <td>
                                <small>
                                    <div><strong>ไป:</strong> <?= thaiDateShort($b['start_datetime']) ?></div>
                                    <div><strong>กลับ:</strong> <?= thaiDateShort($b['end_datetime']) ?></div>
                                </small>
                            </td>
                            <td><?= getStatusBadge($b['status']) ?></td>
                            <td class="text-center">
                                <a href="booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-outline-primary btn-sm me-1" title="ดูรายละเอียด">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="print_form.php?id=<?= $b['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="พิมพ์แบบฟอร์ม">
                                    <i class="fas fa-print"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- แท็บ 4: รายการคำขอเท็จ / สแปม ที่ถูกบล็อก -->
    <div class="tab-pane fade" id="fraud-content" role="tabpanel">
        <div class="card card-custom p-3 border-top border-4 border-danger">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="fw-bold text-danger mb-0">
                        <i class="fas fa-shield-virus me-2"></i>รายการคำขอที่เป็นเท็จ / สแปมที่ถูกปฏิเสธ (<?= count($fraudList) ?> รายการ)
                    </h6>
                    <small class="text-muted">รายการเหล่านี้จะไม่ปรากฏในตารางงาน ไม่นับในสถิติ และไม่แสดงในปฏิทิน</small>
                </div>
            </div>

            <?php if (empty($fraudList)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="fas fa-shield-check text-success fs-1 mb-3"></i>
                    <h6>ไม่มีรายการคำขอเท็จหรือสแปมในระบบ</h6>
                    <small>ระบบป้องกัน (Anti-Spam Challenge & Honeypot) กำลังทำงานอย่างมีประสิทธิภาพ</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-danger">
                            <tr>
                                <th>เลขที่เอกสาร</th>
                                <th>ผู้ยื่นคำขอ</th>
                                <th>ภารกิจที่อ้าง</th>
                                <th>IP Address / อุปกรณ์</th>
                                <th>เหตุผลที่ระบุ</th>
                                <th class="text-center">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fraudList as $fb): ?>
                            <tr>
                                <td><span class="fw-bold text-muted"><?= htmlspecialchars($fb['doc_no'] ?? '-') ?></span></td>
                                <td>
                                    <div class="fw-semibold text-danger"><?= htmlspecialchars($fb['requester_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($fb['requester_department']) ?></small>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 200px;"><?= htmlspecialchars($fb['purpose']) ?></div>
                                    <small class="text-muted">ไป: <?= htmlspecialchars($fb['route_to']) ?></small>
                                </td>
                                <td>
                                    <small>
                                        <div><i class="fas fa-network-wired text-muted me-1"></i><?= htmlspecialchars($fb['client_ip'] ?? 'N/A') ?></div>
                                        <div class="text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($fb['user_agent'] ?? '') ?>">
                                            <?= htmlspecialchars($fb['user_agent'] ?? '-') ?>
                                        </div>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-danger"><?= htmlspecialchars($fb['fake_reason'] ?? 'ข้อมูลเท็จ/สแปม') ?></span>
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="approvals.php" class="d-inline" onsubmit="return confirm('ต้องการกู้คืนคำขอนี้กลับมาสู่สถานะรอพิจารณาหรือไม่?');">
                                        <input type="hidden" name="action" value="restore_fraud">
                                        <input type="hidden" name="booking_id" value="<?= $fb['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-undo me-1"></i> กู้คืน
                                        </button>
                                    </form>
                                    <a href="booking_detail.php?id=<?= $fb['id'] ?>" class="btn btn-sm btn-outline-secondary ms-1">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
