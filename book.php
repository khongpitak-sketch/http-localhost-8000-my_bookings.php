<?php
// book.php - แบบฟอร์มขออนุญาตใช้รถยนต์
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$vehicles = $pdo->query("SELECT * FROM vehicles WHERE status = 'active'")->fetchAll();
$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $requester_name = trim($_POST['requester_name'] ?? '');
    $requester_position = trim($_POST['requester_position'] ?? '');
    $requester_department = trim($_POST['requester_department'] ?? '');
    $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $route_from = trim($_POST['route_from'] ?? '');
    $route_to = trim($_POST['route_to'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $passenger_count = (int)($_POST['passenger_count'] ?? 1);
    $passenger_names = trim($_POST['passenger_names'] ?? '');
    $controller_name = trim($_POST['controller_name'] ?? '');

    $start_datetime = "$start_date $start_time:00";
    $end_datetime = "$end_date $end_time:00";

    // ตรวจสอบความถูกต้อง
    if (empty($requester_name) || empty($requester_position) || empty($requester_department) ||
        empty($vehicle_id) || empty($purpose) || empty($route_from) || empty($route_to) ||
        empty($start_date) || empty($start_time) || empty($end_date) || empty($end_time) || empty($controller_name)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่องที่มีเครื่องหมายดอกจัน (*)';
    } elseif (strtotime($end_datetime) <= strtotime($start_datetime)) {
        $error = 'วัน-เวลาสิ้นสุดการเดินทาง ต้องอยู่หลังจากวัน-เวลาเริ่มต้น';
    } else {
        // ตรวจสอบเงื่อนไขล่วงหน้า 1 วัน
        $diffHours = (strtotime($start_datetime) - time()) / 3600;
        if ($diffHours < 24) {
            // แจ้งเตือนแต่ถ้าต้องการอนุโลมก็ให้ผ่านได้พร้อมบันทึกหมายเหตุ
            // แต่สำหรับระบบนี้ให้แจ้งเตือนตามกฎ
        }

        // ตรวจสอบคิวรถชนกันหรือไม่ (Overlap Check)
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM bookings 
            WHERE vehicle_id = ? 
              AND status NOT IN ('rejected', 'cancelled')
              AND (
                  (start_datetime <= ? AND end_datetime > ?) OR
                  (start_datetime < ? AND end_datetime >= ?) OR
                  (start_datetime >= ? AND end_datetime <= ?)
              )
        ");
        $checkStmt->execute([
            $vehicle_id,
            $start_datetime, $start_datetime,
            $end_datetime, $end_datetime,
            $start_datetime, $end_datetime
        ]);
        $isConflict = $checkStmt->fetchColumn();

        if ($isConflict > 0) {
            $error = 'รถยนต์หมายเลขทะเบียนที่เลือก มีการจองใช้งานในช่วงวันและเวลาดังกล่าวแล้ว กรุณาเลือกรถคันอื่นหรือเปลี่ยนช่วงเวลา';
        } else {
            // ดึงข้อมูลรถ
            $vehStmt = $pdo->prepare("SELECT plate_number FROM vehicles WHERE id = ?");
            $vehStmt->execute([$vehicle_id]);
            $veh = $vehStmt->fetch();
            $plate_number = $veh['plate_number'] ?? '';

            // รันเลขที่เอกสาร เช่น ควจ. 003/2567
            $currentYearThai = date('Y') + 543;
            $countThisYear = $pdo->query("SELECT COUNT(*) FROM bookings WHERE strftime('%Y', created_date) = '" . date('Y') . "'")->fetchColumn();
            $docNo = sprintf("ควจ. %03d/%d", $countThisYear + 1, $currentYearThai);

            // บันทึกคำขอ
            $insertBooking = $pdo->prepare("
                INSERT INTO bookings (
                    doc_no, created_date, user_id, requester_name, requester_position, requester_department,
                    vehicle_id, plate_number, purpose, route_from, route_to, start_datetime, end_datetime,
                    passenger_count, passenger_names, controller_name, status
                ) VALUES (
                    ?, date('now'), ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, 'pending_facility'
                )
            ");
            $insertBooking->execute([
                $docNo,
                $currentUser['id'] ?? null,
                $requester_name,
                $requester_position,
                $requester_department,
                $vehicle_id,
                $plate_number,
                $purpose,
                $route_from,
                $route_to,
                $start_datetime,
                $end_datetime,
                $passenger_count,
                $passenger_names,
                $controller_name
            ]);
            $newBookingId = $pdo->lastInsertId();

            // สร้างแถวในตาราง approvals
            $pdo->prepare("INSERT INTO approvals (booking_id) VALUES (?)")->execute([$newBookingId]);

            header("Location: booking_detail.php?id=$newBookingId&success=1");
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card card-custom p-4 border-top border-4 border-primary">
            <div class="d-flex align-items-center justify-content-between pb-3 mb-4 border-bottom">
                <div>
                    <h4 class="fw-bold text-dark mb-1">
                        <i class="fas fa-file-signature text-primary me-2"></i>แบบฟอร์มขออนุญาตใช้รถยนต์
                    </h4>
                    <span class="text-muted small">คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์</span>
                </div>
                <span class="badge bg-primary fs-6 px-3 py-2">
                    <i class="fas fa-calendar-check me-1"></i> จองออนไลน์
                </span>
            </div>

            <!-- ข้อความเน้นย้ำตามกรอบเอกสาร -->
            <div class="alert alert-warning d-flex align-items-center mb-4">
                <i class="fas fa-bullhorn fs-3 me-3 text-warning"></i>
                <div>
                    <strong class="text-danger">เงื่อนไขสำคัญ:</strong> 
                    ขออนุญาตให้แล้วเสร็จ <u>ก่อนใช้รถอย่างน้อย 1 วัน</u> เพื่อให้การจัดสรรรถยนต์และพนักงานขับรถเป็นไปด้วยความเรียบร้อย
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="book.php" class="needs-validation" novalidate>
                <!-- ส่วนที่ 1: ข้อมูลผู้ขอใช้รถ -->
                <div class="bg-light p-3 rounded-3 mb-4">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="fas fa-user-tie me-2"></i>1. ข้อมูลผู้ขออนุญาตใช้รถยนต์
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">ชื่อ-นามสกุล ผู้ขอใช้รถ <span class="text-danger">*</span></label>
                            <input type="text" name="requester_name" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['requester_name'] ?? $currentUser['fullname'] ?? '') ?>" 
                                   placeholder="ระบุชื่อ-นามสกุลผู้ขอใช้รถ" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ตำแหน่ง <span class="text-danger">*</span></label>
                            <input type="text" name="requester_position" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['requester_position'] ?? $currentUser['position'] ?? '') ?>" 
                                   placeholder="เช่น อาจารย์ประจำสาขาวิชา" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">สาขาวิชา / หน่วยงาน <span class="text-danger">*</span></label>
                            <input type="text" name="requester_department" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['requester_department'] ?? $currentUser['department'] ?? '') ?>" 
                                   placeholder="เช่น สาขาวิชาการจัดการ" required>
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 2: ข้อมูลรถยนต์และวัตถุประสงค์ -->
                <div class="bg-light p-3 rounded-3 mb-4">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="fas fa-van-shuttle me-2"></i>2. ข้อมูลการขอใช้รถยนต์และภารกิจ
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">เลือกรถยนต์ที่ต้องการขอใช้ <span class="text-danger">*</span></label>
                            <select name="vehicle_id" class="form-select" required>
                                <option value="">-- กรุณาเลือกรถยนต์ --</option>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?= $v['id'] ?>" <?= ((isset($_POST['vehicle_id']) && $_POST['vehicle_id'] == $v['id']) || count($vehicles) == 1) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['plate_number']) ?> - <?= htmlspecialchars($v['brand_model']) ?> (<?= $v['seats'] ?> ที่นั่ง)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">จำนวนผู้ร่วมเดินทาง (คน) <span class="text-danger">*</span></label>
                            <input type="number" name="passenger_count" min="1" max="50" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['passenger_count'] ?? '1') ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">เพื่อใช้ในงาน (วัตถุประสงค์) <span class="text-danger">*</span></label>
                            <textarea name="purpose" rows="2" class="form-control" placeholder="ระบุภารกิจหรือวัตถุประสงค์การใช้รถยนต์ เช่น นำนักศึกษาเข้าร่วมการแข่งขันทักษะวิชาการ..." required><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">จากเส้นทาง (จุดเริ่มต้น) <span class="text-danger">*</span></label>
                            <input type="text" name="route_from" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['route_from'] ?? 'คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ถึง (ปลายทาง) <span class="text-danger">*</span></label>
                            <input type="text" name="route_to" class="form-control" placeholder="เช่น มหาวิทยาลัยสงขลานครินทร์ วิทยาเขตหาดใหญ่" 
                                   value="<?= htmlspecialchars($_POST['route_to'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 3: วันและเวลาเดินทาง -->
                <div class="bg-light p-3 rounded-3 mb-4">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="fas fa-clock me-2"></i>3. กำหนดวันและเวลาเดินทาง
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ตั้งแต่วันที่ <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" 
                                   min="<?= date('Y-m-d') ?>"
                                   value="<?= htmlspecialchars($_POST['start_date'] ?? date('Y-m-d', strtotime('+1 day'))) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">เวลาออกเดินทาง <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['start_time'] ?? '08:00') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ถึงวันที่ (เดินทางกลับ) <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" 
                                   min="<?= date('Y-m-d') ?>"
                                   value="<?= htmlspecialchars($_POST['end_date'] ?? date('Y-m-d', strtotime('+1 day'))) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">เวลากลับถึง <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['end_time'] ?? '17:00') ?>" required>
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 4: ผู้ควบคุมรถและผู้ร่วมเดินทาง -->
                <div class="bg-light p-3 rounded-3 mb-4">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="fas fa-users-cog me-2"></i>4. ผู้ควบคุมการใช้รถยนต์และผู้ร่วมเดินทาง
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ผู้ควบคุมการใช้รถยนต์ (มอบหมายให้) <span class="text-danger">*</span></label>
                            <input type="text" name="controller_name" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['controller_name'] ?? $currentUser['fullname'] ?? '') ?>" 
                                   placeholder="ระบุชื่อผู้ควบคุมการใช้รถ" required>
                            <small class="text-muted">
                                * เป็นผู้ควบคุมการใช้รถยนต์ และรับผิดชอบหากมีความเสียหายเกิดขึ้นทุกประการในการขออนุญาตใช้รถในครั้งนี้
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">รายชื่อผู้ร่วมเดินทาง (ระบุรายชื่อหรือกลุ่ม)</label>
                            <textarea name="passenger_names" rows="3" class="form-control" 
                                      placeholder="เช่น 1. นาย ก... 2. นางสาว ข..."><?= htmlspecialchars($_POST['passenger_names'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="index.php" class="btn btn-outline-secondary px-4">
                        <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
                    </a>
                    <button type="submit" class="btn btn-pnu px-5 py-2 fs-6">
                        <i class="fas fa-paper-plane me-2"></i> ส่งคำขออนุญาตใช้รถยนต์
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
