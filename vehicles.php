<?php
// vehicles.php - ข้อมูลรถยนต์ของคณะวิทยาการจัดการ
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$msg = '';

// เพิ่มรถยนต์ใหม่ (เฉพาะ Admin)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['add_vehicle'])) {
    if (!$isAdmin) {
        header("Location: vehicles.php?error=unauthorized");
        exit;
    }
    $plate_number = trim($_POST['plate_number'] ?? '');
    $brand_model = trim($_POST['brand_model'] ?? '');
    $vehicle_type = trim($_POST['vehicle_type'] ?? 'รถตู้โดยสาร');
    $seats = (int)($_POST['seats'] ?? 14);
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($plate_number) && !empty($brand_model)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO vehicles (plate_number, brand_model, vehicle_type, seats, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$plate_number, $brand_model, $vehicle_type, $seats, $notes]);
            $msg = 'เพิ่มรถยนต์สำเร็จ';
        } catch (PDOException $e) {
            $msg = 'ข้อผิดพลาด: ป้ายทะเบียนนี้อาจมีอยู่ในระบบแล้ว';
        }
    }
}

// สลับสถานะรถ (เฉพาะ Admin)
if (isset($_GET['toggle_id'])) {
    if (!$isAdmin) {
        header("Location: vehicles.php?error=unauthorized");
        exit;
    }
    $toggleId = (int)$_GET['toggle_id'];
    $currentStatus = $pdo->query("SELECT status FROM vehicles WHERE id = $toggleId")->fetchColumn();
    $newStatus = ($currentStatus === 'active') ? 'maintenance' : 'active';
    $pdo->prepare("UPDATE vehicles SET status = ? WHERE id = ?")->execute([$newStatus, $toggleId]);
    header("Location: vehicles.php");
    exit;
}

$vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY id ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-van-shuttle text-primary me-2"></i>ข้อมูลยานพาหนะ คณะวิทยาการจัดการ
        </h4>
        <span class="text-muted small">
            รายการรถยนต์ส่วนกลางสำหรับให้บริการคณาจารย์และบุคลากร
        </span>
    </div>
    <?php if ($isAdmin): ?>
    <div>
        <button class="btn btn-pnu btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
            <i class="fas fa-plus-circle me-1"></i> เพิ่มรถยนต์ใหม่
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($vehicles as $v): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card card-custom h-100 p-3 border-top border-4 <?= ($v['status'] == 'active') ? 'border-success' : 'border-danger' ?>">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge <?= ($v['status'] == 'active') ? 'bg-success' : 'bg-danger' ?>">
                    <i class="fas <?= ($v['status'] == 'active') ? 'fa-check-circle' : 'fa-tools' ?> me-1"></i>
                    <?= ($v['status'] == 'active') ? 'พร้อมใช้งาน' : 'ซ่อมบำรุง' ?>
                </span>
                <span class="badge bg-light text-dark border">
                    <i class="fas fa-chair me-1"></i> <?= $v['seats'] ?> ที่นั่ง
                </span>
            </div>

            <div class="text-center py-3">
                <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex p-3 mb-2">
                    <i class="fas <?= (strpos($v['vehicle_type'], 'กระบะ') !== false) ? 'fa-truck-pickup' : 'fa-van-shuttle' ?> fs-1"></i>
                </div>
                <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($v['plate_number']) ?></h5>
                <div class="text-primary fw-semibold"><?= htmlspecialchars($v['brand_model']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars($v['vehicle_type']) ?></div>
            </div>

            <p class="text-muted small mb-3 bg-light p-2 rounded">
                <i class="fas fa-info-circle me-1"></i> <?= htmlspecialchars($v['notes'] ?: 'ไม่มีหมายเหตุเพิ่มเติม') ?>
            </p>

            <div class="mt-auto d-flex gap-2">
                <a href="book.php?vehicle_id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary flex-grow-1 <?= ($v['status'] != 'active') ? 'disabled' : '' ?>">
                    <i class="fas fa-calendar-plus me-1"></i> ขอใช้รถคันนี้
                </a>
                <?php if ($isAdmin): ?>
                    <a href="?toggle_id=<?= $v['id'] ?>" class="btn btn-sm <?= ($v['status'] == 'active') ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="สลับสถานะ">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal เพิ่มรถยนต์ -->
<div class="modal fade" id="addVehicleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="add_vehicle" value="1">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-car me-2"></i>เพิ่มรถยนต์ส่วนกลาง</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">หมายเลขทะเบียน <span class="text-danger">*</span></label>
                    <input type="text" name="plate_number" class="form-control" placeholder="เช่น นข-4521 นราธิวาส" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">ยี่ห้อ / รุ่น <span class="text-danger">*</span></label>
                    <input type="text" name="brand_model" class="form-control" placeholder="เช่น Toyota Commuter 3.0" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">ประเภทรถ</label>
                        <select name="vehicle_type" class="form-select">
                            <option value="รถตู้โดยสาร">รถตู้โดยสาร</option>
                            <option value="รถตู้โดยสาร VIP">รถตู้โดยสาร VIP</option>
                            <option value="รถกระบะ 4 ประตู">รถกระบะ 4 ประตู</option>
                            <option value="รถเก๋ง">รถเก๋ง</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">จำนวนที่นั่ง</label>
                        <input type="number" name="seats" class="form-control" value="14" min="1" max="50">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">หมายเหตุ / ข้อมูลเพิ่มเติม</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="เช่น สภาพรถ, อุปกรณ์ประจำรถ"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-pnu">บันทึกข้อมูลรถยนต์</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
