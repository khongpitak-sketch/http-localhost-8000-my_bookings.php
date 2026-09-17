<?php
// index.php - หน้าหลัก แดชบอร์ด และปฏิทินการใช้รถ
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

// ดึงสถิติ (ไม่รวมคำขอเท็จ/สแปม)
$totalBookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status != 'rejected_fraud' AND (is_flagged_fake IS NULL OR is_flagged_fake = 0)")->fetchColumn();
$pendingBookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status LIKE 'pending_%' AND (is_flagged_fake IS NULL OR is_flagged_fake = 0)")->fetchColumn();
$approvedBookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('approved', 'completed')")->fetchColumn();
$totalVehicles = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'active'")->fetchColumn();

// ดึงรายการคำขอล่าสุด (ไม่รวมคำขอเท็จ/สแปม)
$recentBookings = $pdo->query("
    SELECT b.*, v.brand_model 
    FROM bookings b 
    JOIN vehicles v ON b.vehicle_id = v.id 
    WHERE b.status != 'rejected_fraud' AND (b.is_flagged_fake IS NULL OR b.is_flagged_fake = 0)
    ORDER BY b.id DESC 
    LIMIT 6
")->fetchAll();

// ดึงรายการคำขอทั้งหมดสำหรับส่งเข้าปฏิทิน FullCalendar (ไม่รวมคำขอยกเลิกและคำขอเท็จ)
$calendarEvents = [];
$eventsData = $pdo->query("
    SELECT b.id, b.doc_no, b.purpose, b.plate_number, b.requester_name, b.start_datetime, b.end_datetime, b.status 
    FROM bookings b 
    WHERE b.status NOT IN ('cancelled', 'rejected', 'rejected_fraud') AND (b.is_flagged_fake IS NULL OR b.is_flagged_fake = 0)
")->fetchAll();

foreach ($eventsData as $ev) {
    $color = '#ffc107'; // pending
    if ($ev['status'] == 'completed') {
        $color = '#198754'; // approved
    }
    $calendarEvents[] = [
        'id' => $ev['id'],
        'title' => $ev['plate_number'] . ' - ' . $ev['purpose'] . ' (' . $ev['requester_name'] . ')',
        'start' => $ev['start_datetime'],
        'end' => $ev['end_datetime'],
        'backgroundColor' => $color,
        'borderColor' => $color,
        'url' => 'booking_detail.php?id=' . $ev['id']
    ];
}
?>

<?php if (!$isLoggedIn): ?>
<div class="alert alert-primary border-primary d-flex flex-wrap align-items-center justify-content-between p-3 rounded-3 shadow-sm mb-4 gap-3">
    <div class="d-flex align-items-center">
        <div class="bg-primary text-white p-2 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
            <i class="fas fa-eye fs-5"></i>
        </div>
        <div>
            <div class="fw-bold text-dark">โหมดดูข้อมูลทั่วไป (ดูได้อย่างเดียว)</div>
            <div class="small text-secondary">คุณสามารถตรวจสอบปฏิทินตารางการใช้รถและรายการคำขอได้ หากต้องการยื่นคำขอจองรถยนต์ กรุณาเข้าสู่ระบบก่อนทำรายการ</div>
        </div>
    </div>
    <div>
        <a href="login.php?redirect=book.php&login_required=1" class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm text-nowrap">
            <i class="fas fa-sign-in-alt me-1"></i> เข้าสู่ระบบก่อนจองรถ
        </a>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <!-- กล่องสถิติ -->
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom p-3 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">คำขอทั้งหมด</div>
                    <div class="fs-3 fw-bold text-primary"><?= $totalBookings ?></div>
                </div>
                <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                    <i class="fas fa-file-alt fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom p-3 border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">อยู่ระหว่างพิจารณา</div>
                    <div class="fs-3 fw-bold text-warning"><?= $pendingBookings ?></div>
                </div>
                <div class="bg-warning-subtle text-warning p-3 rounded-circle">
                    <i class="fas fa-clock fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom p-3 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">อนุมัติแล้ว</div>
                    <div class="fs-3 fw-bold text-success"><?= $approvedBookings ?></div>
                </div>
                <div class="bg-success-subtle text-success p-3 rounded-circle">
                    <i class="fas fa-check-circle fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom p-3 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">รถยนต์ที่พร้อมบริการ</div>
                    <div class="fs-3 fw-bold text-info"><?= $totalVehicles ?> คัน</div>
                </div>
                <div class="bg-info-subtle text-info p-3 rounded-circle">
                    <i class="fas fa-van-shuttle fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- กล่องแจ้งเตือนเงื่อนไขตามแบบฟอร์ม -->
<div class="notice-box mb-4 d-flex align-items-center shadow-sm">
    <i class="fas fa-exclamation-triangle fs-3 text-warning me-3"></i>
    <div>
        <strong>ระเบียบการขออนุญาตใช้รถยนต์:</strong> 
        กรุณายื่นขออนุญาตให้แล้วเสร็จ <span class="badge bg-danger">ก่อนใช้รถอย่างน้อย 1 วัน</span> 
        เพื่อให้งานอาคารสถานที่และพนักงานขับรถได้เตรียมความพร้อมของยานพาหนะ
    </div>
</div>

<div class="row g-4">
    <!-- ปฏิทินแสดงตารางการใช้รถ -->
    <div class="col-lg-8">
        <div class="card card-custom p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-calendar-alt text-primary me-2"></i>ปฏิทินตารางการใช้รถยนต์
                </h5>
                <div class="small">
                    <span class="badge bg-success me-1">■ อนุมัติแล้ว</span>
                    <span class="badge bg-warning text-dark">■ อยู่ระหว่างพิจารณา</span>
                </div>
            </div>
            <div id="calendar" style="min-height: 520px;"></div>
        </div>
    </div>

    <!-- รายการคำขอล่าสุด -->
    <div class="col-lg-4">
        <div class="card card-custom p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-history text-secondary me-2"></i>รายการคำขอล่าสุด
                </h6>
                <a href="my_bookings.php" class="small text-decoration-none">ดูทั้งหมด</a>
            </div>

            <div class="list-group list-group-flush">
                <?php if (empty($recentBookings)): ?>
                    <div class="text-center py-4 text-muted">ยังไม่มีรายการคำขอ</div>
                <?php else: ?>
                    <?php foreach ($recentBookings as $b): ?>
                    <a href="booking_detail.php?id=<?= $b['id'] ?>" class="list-group-item list-group-item-action px-2 py-3 border-bottom">
                        <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($b['doc_no'] ?? '-') ?></span>
                            <div><?= getStatusBadge($b['status']) ?></div>
                        </div>
                        <h6 class="mb-1 text-primary fw-bold fs-6"><?= htmlspecialchars($b['plate_number']) ?></h6>
                        <p class="mb-1 text-muted small text-truncate" title="<?= htmlspecialchars($b['purpose']) ?>">
                            <i class="fas fa-bullseye me-1"></i><?= htmlspecialchars($b['purpose']) ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($b['requester_name']) ?></span>
                            <span><i class="fas fa-calendar-day me-1"></i><?= thaiDateShort($b['start_datetime']) ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mt-3 text-center">
                <a href="book.php" class="btn btn-pnu w-100 py-2">
                    <i class="fas fa-plus-circle me-1"></i> สร้างคำขอจองรถยนต์
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'th',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth'
        },
        buttonText: {
            today: 'วันนี้',
            month: 'เดือน',
            week: 'สัปดาห์',
            list: 'รายการ'
        },
        events: <?= json_encode($calendarEvents, JSON_UNESCAPED_UNICODE) ?>,
        eventClick: function(info) {
            if (info.event.url) {
                window.location.href = info.event.url;
                info.jsEvent.preventDefault();
            }
        }
    });
    calendar.render();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
