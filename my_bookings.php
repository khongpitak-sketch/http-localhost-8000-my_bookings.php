<?php
// my_bookings.php - ติดตามสถานะคำขอใช้รถยนต์
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// ยกเลิกคำขอ (เฉพาะ Admin เท่านั้นที่สามารถยกเลิกได้ตามนโยบาย)
if (isset($_GET['cancel_id'])) {
    if (!$isAdmin) {
        header("Location: my_bookings.php?error=unauthorized");
        exit;
    }
    $cancelId = (int)$_GET['cancel_id'];
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND status LIKE 'pending_%'");
    $stmt->execute([$cancelId]);
    header("Location: my_bookings.php?cancelled=1");
    exit;
}

$searchQuery = trim($_GET['q'] ?? '');

// ดึงรายการคำขอ
if (!empty($searchQuery)) {
    $stmt = $pdo->prepare("
        SELECT b.*, v.brand_model, v.vehicle_type 
        FROM bookings b 
        JOIN vehicles v ON b.vehicle_id = v.id 
        WHERE b.doc_no LIKE ? 
           OR b.requester_name LIKE ? 
           OR b.requester_department LIKE ?
           OR b.route_to LIKE ?
           OR b.purpose LIKE ?
        ORDER BY b.id DESC
    ");
    $term = '%' . $searchQuery . '%';
    $stmt->execute([$term, $term, $term, $term, $term]);
} else {
    $stmt = $pdo->query("
        SELECT b.*, v.brand_model, v.vehicle_type 
        FROM bookings b 
        JOIN vehicles v ON b.vehicle_id = v.id 
        ORDER BY b.id DESC
    ");
}
$bookings = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-list-check text-primary me-2"></i>ติดตามสถานะคำขอใช้รถยนต์
        </h4>
        <span class="text-muted small">
            <?php if ($isAdmin): ?>
                เข้าสู่ระบบในฐานะ Admin: <strong><?= htmlspecialchars($currentUser['fullname'] ?? 'ผู้ดูแลระบบ') ?></strong> (สามารถจัดการสถานะและยกเลิกคำขอได้)
            <?php else: ?>
                ตรวจสอบสถานะความคืบหน้าของคำขอใช้รถยนต์ พิมพ์แบบฟอร์ม หรือค้นหารายการของคุณ
            <?php endif; ?>
        </span>
    </div>
    <div class="d-flex gap-2">
        <a href="book.php" class="btn btn-gold btn-sm px-3 shadow-sm">
            <i class="fas fa-plus-circle me-1"></i> ยื่นขอใช้รถยนต์ใหม่
        </a>
    </div>
</div>

<!-- ค้นหาคำขอ -->
<div class="card card-custom p-3 mb-4 bg-light border-0 shadow-sm">
    <form method="GET" action="my_bookings.php" class="row g-2 align-items-center">
        <div class="col-md-9 col-lg-10">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="q" class="form-control" placeholder="ค้นหาด้วยชื่อผู้ขอ, เลขที่เอกสาร, หน่วยงาน หรือสถานที่ปลายทาง..." value="<?= htmlspecialchars($searchQuery) ?>">
            </div>
        </div>
        <div class="col-md-3 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                <i class="fas fa-search me-1"></i> ค้นหา
            </button>
            <?php if (!empty($searchQuery)): ?>
                <a href="my_bookings.php" class="btn btn-outline-secondary btn-sm" title="ล้างการค้นหา">
                    <i class="fas fa-times"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (isset($_GET['cancelled'])): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-info-circle me-2"></i> ยกเลิกคำขอเรียบร้อยแล้ว
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i> สำหรับผู้ดูแลระบบเท่านั้น ผู้ใช้ทั่วไปไม่สามารถแก้ไขหรือยกเลิกคำขอได้
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card card-custom p-3">
    <?php if (empty($bookings)): ?>
        <div class="text-center py-5">
            <i class="fas fa-calendar-times text-muted fs-1 mb-3"></i>
            <h5 class="fw-bold text-dark">ยังไม่มีประวัติการขอใช้รถยนต์</h5>
            <p class="text-muted small mb-3">คุณยังไม่ได้สร้างคำขอใช้รถยนต์ในระบบ</p>
            <a href="book.php" class="btn btn-pnu btn-sm px-4">
                <i class="fas fa-plus me-1"></i> ยื่นขอใช้รถยนต์ตอนนี้
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>เลขที่เอกสาร</th>
                        <th>รถยนต์</th>
                        <th>ภารกิจ / วัตถุประสงค์</th>
                        <th>เส้นทาง</th>
                        <th>วันและเวลาเดินทาง</th>
                        <th>สถานะคำขอ</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td>
                            <strong class="text-secondary"><?= htmlspecialchars($b['doc_no'] ?? '-') ?></strong>
                            <div class="small text-muted"><?= thaiDateShort($b['created_date']) ?></div>
                        </td>
                        <td>
                            <strong class="text-primary"><?= htmlspecialchars($b['plate_number']) ?></strong>
                            <div class="small text-muted"><?= htmlspecialchars($b['brand_model']) ?></div>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($b['purpose']) ?>">
                                <?= htmlspecialchars($b['purpose']) ?>
                            </div>
                            <div class="small text-muted">ผู้ร่วมทาง <?= $b['passenger_count'] ?> คน (คุมรถ: <?= htmlspecialchars($b['controller_name']) ?>)</div>
                        </td>
                        <td>
                            <div class="small">
                                <div><strong>จาก:</strong> <?= htmlspecialchars($b['route_from']) ?></div>
                                <div class="text-danger"><strong>ถึง:</strong> <?= htmlspecialchars($b['route_to']) ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="small">
                                <div><i class="fas fa-arrow-right text-success me-1"></i> <?= thaiDate($b['start_datetime']) ?></div>
                                <div><i class="fas fa-arrow-left text-danger me-1"></i> <?= thaiDate($b['end_datetime']) ?></div>
                            </div>
                        </td>
                        <td><?= getStatusBadge($b['status']) ?></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-outline-primary" title="ดูรายละเอียดและขั้นตอน">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="print_form.php?id=<?= $b['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="พิมพ์แบบฟอร์มราชการ">
                                    <i class="fas fa-print"></i>
                                </a>
                                <?php if ($isAdmin && strpos($b['status'], 'pending_') === 0): ?>
                                    <a href="?cancel_id=<?= $b['id'] ?>" 
                                       onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการยกเลิกคำขอนี้?')" 
                                       class="btn btn-outline-danger" title="ยกเลิกคำขอ (Admin)">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
