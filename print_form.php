<?php
// print_form.php - หนังสือขออนุญาตใช้รถยนต์ (ถอดแบบตามเอกสารต้นฉบับ 100%)
require_once __DIR__ . '/config/db.php';

$bookingId = (int)($_GET['id'] ?? 0);
if (!$bookingId) {
    die("ไม่พบรหัสคำขอ");
}

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->execute([$bookingId]);
$b = $stmt->fetch();

if (!$b) {
    die("ไม่พบข้อมูลคำขอ");
}

$appStmt = $pdo->prepare("SELECT * FROM approvals WHERE booking_id = ?");
$appStmt->execute([$bookingId]);
$app = $appStmt->fetch() ?: [];

// แยกวัน เดือน ปี เวลา
function parseDateParts($datetime) {
    if (!$datetime) return ['d' => '.....', 'm' => '..................', 'y' => '..........', 'time' => '..........'];
    $t = strtotime($datetime);
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    return [
        'd' => date('j', $t),
        'm' => $thaiMonths[(int)date('n', $t)],
        'y' => (string)(date('Y', $t) + 543),
        'time' => date('H:i', $t)
    ];
}

$createdParts = parseDateParts($b['created_at']);
$startParts = parseDateParts($b['start_datetime']);
$endParts = parseDateParts($b['end_datetime']);

$emblemPath = __DIR__ . '/assets/pnu_emblem.png';
$emblemBase64 = file_exists($emblemPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($emblemPath)) : '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หนังสือขออนุญาตใช้รถยนต์ - <?= htmlspecialchars($b['doc_no'] ?? '') ?></title>
    <!-- Google Font Sarabun สำหรับหนังสือราชการ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }
        body {
            font-family: 'TH Sarabun New', 'Sarabun', sans-serif;
            font-size: 15pt;
            line-height: 1.35;
            color: #000;
            background-color: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        .page-container {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm 15mm 20mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.15);
            box-sizing: border-box;
            position: relative;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        
        .dots {
            border-bottom: 1px dotted #000;
            display: inline-block;
            padding: 0 4px;
            min-width: 50px;
            text-align: center;
        }
        .emblem-box {
            width: 60px;
            height: 60px;
            margin: 0 auto 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .emblem-img {
            max-width: 60px;
            max-height: 60px;
        }
        .divider {
            border-top: 1px solid #000;
            margin: 8px 0;
        }
        .two-cols {
            display: flex;
            width: 100%;
        }
        .col-half {
            width: 50%;
            padding: 0 8px;
            box-sizing: border-box;
        }
        .col-half:first-child {
            border-right: 1px solid #000;
        }
        .notice-box-stamp {
            border: 1.5px solid #000;
            padding: 8px 12px;
            text-align: center;
            font-weight: bold;
            font-size: 13pt;
            display: inline-block;
            line-height: 1.25;
        }
        .controller-box {
            border: 1px solid #000;
            padding: 6px 12px;
            text-align: center;
            width: 180px;
            float: right;
            margin-top: -15px;
        }
        .checkbox-symbol {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            font-size: 13pt;
            margin-right: 4px;
        }
        .no-print-bar {
            width: 210mm;
            margin: 0 auto 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .btn-action {
            background-color: #0b3c6d;
            color: white;
            padding: 8px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-action:hover {
            background-color: #072747;
        }
        .btn-outline {
            background: white;
            color: #333;
            border: 1px solid #ccc;
        }
        .btn-outline:hover {
            background: #eee;
        }

        @media print {
            body {
                background: transparent;
                padding: 0;
            }
            .page-container {
                box-shadow: none;
                padding: 0;
                width: 100%;
                min-height: auto;
            }
            .no-print-bar {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- แถบเครื่องมือสั่งพิมพ์ (ไม่แสดงเมื่อกด Print) -->
<div class="no-print-bar">
    <div>
        <a href="booking_detail.php?id=<?= $b['id'] ?>" class="btn-action btn-outline">
            <i class="fas fa-arrow-left"></i> กลับหน้ารายละเอียดคำขอ
        </a>
    </div>
    <div style="font-size: 14px; color: #555;">
        เลขที่เอกสาร: <strong><?= htmlspecialchars($b['doc_no'] ?? '-') ?></strong> | สถานะ: <?= getStatusBadge($b['status']) ?>
    </div>
    <div>
        <button onclick="window.print()" class="btn-action">
            <i class="fas fa-print"></i> พิมพ์เอกสาร / บันทึก PDF
        </button>
    </div>
</div>

<div class="page-container">
    <!-- มุมขวาบน -->
    <div class="text-end" style="font-size: 14pt; line-height: 1.2;">
        <div>เลขที่ <span class="dots" style="min-width: 80px;"><?= htmlspecialchars($b['doc_no'] ?? '') ?></span></div>
        <div>คณะวิทยาการจัดการ</div>
    </div>

    <!-- ตราสัญลักษณ์และหัวเอกสาร (จัดวางตรงตามแบบฟอร์มต้นฉบับ) -->
    <div style="position: relative; min-height: 85px; margin-top: -5px; margin-bottom: 10px;">
        <!-- ตราสัญลักษณ์มหาวิทยาลัยนราธิวาสราชนครินทร์ (ตำแหน่งด้านซ้ายบนตามเอกสารจริง) -->
        <?php if ($emblemBase64): ?>
        <div style="position: absolute; left: 15px; top: -5px;">
            <img src="<?= $emblemBase64 ?>" alt="ตราสัญลักษณ์ มหาวิทยาลัยนราธิวาสราชนครินทร์" style="height: 85px; width: auto;">
        </div>
        <?php endif; ?>

        <div class="text-center" style="margin-left: 60px;">
            <div class="fw-bold" style="font-size: 17pt;">หนังสือขออนุญาตใช้รถยนต์</div>
            <div class="fw-bold" style="font-size: 15pt;">คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์</div>
            <div style="font-size: 14pt; margin-top: 2px;">
                วันที่ <span class="dots" style="min-width: 40px;"><?= $createdParts['d'] ?></span> 
                เดือน <span class="dots" style="min-width: 100px;"><?= $createdParts['m'] ?></span> 
                พ.ศ. <span class="dots" style="min-width: 60px;"><?= $createdParts['y'] ?></span>
            </div>
        </div>
    </div>

    <!-- เรื่อง และ เรียน -->
    <div style="margin-top: 10px;">
        <div><strong>เรื่อง</strong>&nbsp;&nbsp;ขออนุญาตใช้รถยนต์</div>
        <div><strong>เรียน</strong>&nbsp;&nbsp;คณบดีคณะวิทยาการจัดการ</div>
    </div>

    <!-- เนื้อความคำขอ -->
    <div style="text-indent: 45px; margin-top: 6px; text-align: justify;">
        ด้วยข้าพเจ้า (นาย/นาง/นางสาว) <span class="dots" style="min-width: 220px; font-weight: bold;"><?= htmlspecialchars($b['requester_name']) ?></span>
        ตำแหน่ง <span class="dots" style="min-width: 190px;"><?= htmlspecialchars($b['requester_position']) ?></span>
    </div>
    <div style="text-align: justify; margin-top: 4px;">
        สาขาวิชา <span class="dots" style="min-width: 160px;"><?= htmlspecialchars($b['requester_department']) ?></span>
        มีความประสงค์ขอใช้รถยนต์ หมายเลขทะเบียน <span class="dots" style="min-width: 140px; font-weight: bold;"><?= htmlspecialchars($b['plate_number']) ?></span>
        ของคณะวิทยาการจัดการ
    </div>
    <div style="text-align: justify; margin-top: 4px;">
        เพื่อใช้ในงาน <span class="dots" style="min-width: 250px;"><?= htmlspecialchars($b['purpose']) ?></span>
        จากเส้นทาง <span class="dots" style="min-width: 150px;"><?= htmlspecialchars($b['route_from']) ?></span>
        ถึง <span class="dots" style="min-width: 180px;"><?= htmlspecialchars($b['route_to']) ?></span>
    </div>
    <div style="text-align: justify; margin-top: 4px;">
        ตั้งแต่วันที่ <span class="dots" style="min-width: 35px;"><?= $startParts['d'] ?></span>
        เดือน <span class="dots" style="min-width: 90px;"><?= $startParts['m'] ?></span>
        พ.ศ. <span class="dots" style="min-width: 50px;"><?= $startParts['y'] ?></span>
        เวลา <span class="dots" style="min-width: 50px;"><?= $startParts['time'] ?></span> น. 
        ถึงวันที่ <span class="dots" style="min-width: 35px;"><?= $endParts['d'] ?></span>
        เดือน <span class="dots" style="min-width: 90px;"><?= $endParts['m'] ?></span>
        พ.ศ. <span class="dots" style="min-width: 50px;"><?= $endParts['y'] ?></span>
    </div>
    <div style="text-align: justify; margin-top: 4px;">
        เวลา <span class="dots" style="min-width: 50px;"><?= $endParts['time'] ?></span> น. 
        โดยมีผู้ร่วมทาง จำนวน <span class="dots" style="min-width: 40px; font-weight: bold;"><?= $b['passenger_count'] ?></span> คน 
        และมอบหมายให้ <span class="dots" style="min-width: 200px; font-weight: bold;"><?= htmlspecialchars($b['controller_name']) ?></span>
    </div>
    <div style="text-align: justify; margin-top: 4px;">
        เป็นผู้ควบคุมการใช้รถยนต์ และรับผิดชอบหากมีความเสียหายเกิดขึ้นทุกประการในการขออนุญาตใช้รถในครั้งนี้
    </div>
    <div style="text-indent: 45px; margin-top: 6px;">
        จึงเรียนมาเพื่อโปรดทราบและพิจารณา
    </div>

    <!-- ส่วนลงชื่อผู้ขอ และผู้ควบคุมรถ -->
    <div style="margin-top: 15px; position: relative; min-height: 120px;">
        <!-- กล่องผู้ควบคุมรถ (ทางขวา) -->
        <div class="controller-box">
            <div class="fw-bold">ผู้ควบคุมรถ</div>
            <div style="margin-top: 20px;">
                <span class="dots" style="min-width: 130px;">
                    <?= (!empty($b['controller_name'])) ? htmlspecialchars($b['controller_name']) : '' ?>
                </span>
            </div>
            <div>(<span class="dots" style="min-width: 120px;"><?= htmlspecialchars($b['controller_name']) ?></span>)</div>
            <div style="margin-top: 5px;">
                <span class="dots" style="min-width: 20px;"><?= $createdParts['d'] ?></span>/
                <span class="dots" style="min-width: 40px;"><?= $createdParts['m'] ?></span>/
                <span class="dots" style="min-width: 35px;"><?= $createdParts['y'] ?></span>
            </div>
        </div>

        <!-- ขอแสดงความนับถือ (ตรงกลางค่อนขวา) -->
        <div style="margin-left: 260px; text-align: center; width: 250px;">
            <div>ขอแสดงความนับถือ</div>
            <div style="margin-top: 25px;">
                ลงชื่อ <span class="dots" style="min-width: 140px;"><?= htmlspecialchars($b['requester_name']) ?></span>
            </div>
            <div>
                (<span class="dots" style="min-width: 140px;"><?= htmlspecialchars($b['requester_name']) ?></span>)
            </div>
            <div>
                ตำแหน่ง <span class="dots" style="min-width: 130px;"><?= htmlspecialchars($b['requester_position']) ?></span>
            </div>
            <div>
                <span class="dots" style="min-width: 25px;"><?= $createdParts['d'] ?></span>/
                <span class="dots" style="min-width: 45px;"><?= $createdParts['m'] ?></span>/
                <span class="dots" style="min-width: 35px;"><?= $createdParts['y'] ?></span>
            </div>
        </div>
    </div>

    <div class="divider"></div>

    <!-- สองคอลัมน์: อาคารสถานที่ vs หัวหน้าสำนักงาน -->
    <div class="two-cols">
        <!-- ด้านซ้าย: หัวหน้างานอาคารสถานที่ -->
        <div class="col-half">
            <div class="fw-bold" style="font-size: 14pt;">ความเห็นของหัวหน้างานอาคารสถานที่</div>
            <div style="margin-top: 4px;">
                <span class="checkbox-symbol"><?= ($app['facility_status'] == 'approved') ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> เห็นชอบ
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="checkbox-symbol"><?= (!empty($app['facility_fuel'])) ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> ค่าน้ำมันเชื้อเพลิง
            </div>
            <div>
                <span class="checkbox-symbol"><?= ($app['facility_status'] == 'rejected') ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> ไม่เห็นชอบ
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="checkbox-symbol"><?= (!empty($app['facility_allowance'])) ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> เบี้ยเลี้ยง/ค่าตอบแทน
            </div>
            <div>
                <span class="checkbox-symbol"><?= (!empty($app['facility_other'])) ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> อื่นๆ ระบุ <span class="dots" style="min-width: 170px;"><?= htmlspecialchars($app['facility_other'] ?? '') ?></span>
            </div>
            <div style="margin-top: 15px; text-align: center;">
                ลงชื่อ <span class="dots" style="min-width: 140px;"><?= ($app['facility_status']) ? htmlspecialchars($app['facility_signer'] ?? 'นายเอกสิทธิ์ คงพิทักษ์') : '' ?></span><br>
                (นายเอกสิทธิ์ คงพิทักษ์)<br>
                หัวหน้างานอาคารสถานที่<br>
                <span class="dots" style="min-width: 30px;"><?= ($app['facility_signed_at']) ? date('j', strtotime($app['facility_signed_at'])) : '' ?></span>/
                <span class="dots" style="min-width: 50px;"><?= ($app['facility_signed_at']) ? date('n', strtotime($app['facility_signed_at'])) : '' ?></span>/
                <span class="dots" style="min-width: 35px;"><?= ($app['facility_signed_at']) ? (date('Y', strtotime($app['facility_signed_at'])) + 543) : '' ?></span>
            </div>
        </div>

        <!-- ด้านขวา: หัวหน้าสำนักงานคณบดี -->
        <div class="col-half">
            <div class="fw-bold" style="font-size: 14pt;">ความเห็นของหัวหน้าสำนักงาน</div>
            <div style="margin-top: 4px;">
                <span class="checkbox-symbol"><?= ($app['office_status'] == 'approved') ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> 
                ควรอนุญาตให้นาย <span class="dots" style="min-width: 100px; font-weight: bold;"><?= htmlspecialchars($app['office_driver_assigned'] ?? '') ?></span>
            </div>
            <div style="padding-left: 20px;">
                ปฏิบัติหน้าที่พนักงานขับรถ
            </div>
            <div>
                <span class="checkbox-symbol"><?= ($app['office_status'] == 'rejected') ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> 
                ไม่อนุญาต เพราะ <span class="dots" style="min-width: 150px;"><?= htmlspecialchars($app['office_reason'] ?? '') ?></span>
            </div>
            <div>
                <span class="checkbox-symbol"><?= (!empty($app['office_other'])) ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> 
                อื่นๆ <span class="dots" style="min-width: 200px;"><?= htmlspecialchars($app['office_other'] ?? '') ?></span>
            </div>
            <div style="margin-top: 5px; text-align: center;">
                ลงชื่อ <span class="dots" style="min-width: 140px;"><?= ($app['office_status']) ? htmlspecialchars($app['office_signer'] ?? 'นางซูไบดะห์ หะยีมะ') : '' ?></span><br>
                (นางซูไบดะห์ หะยีมะ)<br>
                หัวหน้าสำนักงานคณบดี<br>
                <span class="dots" style="min-width: 30px;"><?= ($app['office_signed_at']) ? date('j', strtotime($app['office_signed_at'])) : '' ?></span>/
                <span class="dots" style="min-width: 50px;"><?= ($app['office_signed_at']) ? date('n', strtotime($app['office_signed_at'])) : '' ?></span>/
                <span class="dots" style="min-width: 35px;"><?= ($app['office_signed_at']) ? (date('Y', strtotime($app['office_signed_at'])) + 543) : '' ?></span>
            </div>
        </div>
    </div>

    <div class="divider"></div>

    <!-- ส่วนคำสั่งคณบดี -->
    <div>
        <div class="fw-bold" style="font-size: 14pt;">คำสั่ง</div>
        <div style="margin-top: 3px;">
            <span class="checkbox-symbol"><?= ($app['dean_status'] == 'approved') ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> อนุญาต
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <span class="checkbox-symbol"><?= ($app['dean_status'] == 'rejected') ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> ไม่อนุญาต เพราะ <span class="dots" style="min-width: 160px;"><?= htmlspecialchars($app['dean_reason'] ?? '') ?></span>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <span class="checkbox-symbol"><?= (!empty($app['dean_other'])) ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> อื่นๆ ระบุ <span class="dots" style="min-width: 150px;"><?= htmlspecialchars($app['dean_other'] ?? '') ?></span>
        </div>
        <div style="margin-top: 15px; text-align: center;">
            ลงชื่อ <span class="dots" style="min-width: 160px;"><?= ($app['dean_status']) ? htmlspecialchars($app['dean_signer'] ?? 'ผู้ช่วยศาสตราจารย์ ดร.บงกช กมลเปรม') : '' ?></span><br>
            (ผู้ช่วยศาสตราจารย์ ดร.บงกช กมลเปรม)<br>
            คณบดีคณะวิทยาการจัดการ<br>
            <span class="dots" style="min-width: 30px;"><?= ($app['dean_signed_at']) ? date('j', strtotime($app['dean_signed_at'])) : '' ?></span>/
            <span class="dots" style="min-width: 50px;"><?= ($app['dean_signed_at']) ? date('n', strtotime($app['dean_signed_at'])) : '' ?></span>/
            <span class="dots" style="min-width: 35px;"><?= ($app['dean_signed_at']) ? (date('Y', strtotime($app['dean_signed_at'])) + 543) : '' ?></span>
        </div>
    </div>

    <div class="divider"></div>

    <!-- ส่วนบันทึกพนักงานขับรถ -->
    <div>
        <div class="text-center fw-bold" style="font-size: 14pt;">บันทึกพนักงานขับรถ</div>
        <div style="margin-top: 3px;">
            ข้าพเจ้านาย <span class="dots" style="min-width: 220px; font-weight: bold;"><?= htmlspecialchars($app['driver_signer'] ?? $app['office_driver_assigned'] ?? 'ธเนศ อินเอิบ') ?></span>
            ได้รับทราบการขอใช้รถยนต์แล้ว
        </div>

        <div style="margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
            <!-- กล่องข้อความกรอบซ้าย -->
            <div class="notice-box-stamp">
                ขออนุญาตให้แล้วเสร็จ<br>ก่อนใช้รถอย่างน้อย 1 วัน
            </div>

            <!-- ส่วนลงชื่อพนักงานขับรถ -->
            <div style="text-align: center; width: 250px;">
                ลงชื่อ <span class="dots" style="min-width: 140px;"><?= ($app['driver_ack_status']) ? htmlspecialchars($app['driver_signer'] ?? 'นายธเนศ อินเอิบ') : '' ?></span><br>
                (นายธเนศ อินเอิบ)<br>
                พนักงานขับรถยนต์<br>
                <span class="dots" style="min-width: 30px;"><?= ($app['driver_acknowledged_at']) ? date('j', strtotime($app['driver_acknowledged_at'])) : '' ?></span>/
                <span class="dots" style="min-width: 50px;"><?= ($app['driver_acknowledged_at']) ? date('n', strtotime($app['driver_acknowledged_at'])) : '' ?></span>/
                <span class="dots" style="min-width: 35px;"><?= ($app['driver_acknowledged_at']) ? (date('Y', strtotime($app['driver_acknowledged_at'])) + 543) : '' ?></span>
            </div>
        </div>
    </div>
</div>

</body>
</html>
