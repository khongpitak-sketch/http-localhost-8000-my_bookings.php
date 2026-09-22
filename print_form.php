<?php
// print_form.php - หนังสือขออนุญาตใช้รถยนต์ (ถอดแบบตามเอกสารราชการต้นฉบับ 100%)
require_once __DIR__ . '/config/db.php';

$bookingId = (int)($_GET['id'] ?? 0);
$b = null;
$app = [];

if ($bookingId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $b = $stmt->fetch();
    if ($b) {
        $appStmt = $pdo->prepare("SELECT * FROM approvals WHERE booking_id = ?");
        $appStmt->execute([$bookingId]);
        $app = $appStmt->fetch() ?: [];
    }
}

// หากไม่ระบุ ID หรือไม่พบข้อมูล ให้แสดงแบบฟอร์มเปล่าสำหรับพิมพ์ตัวอย่าง
if (!$b) {
    $b = [
        'id' => 0,
        'doc_no' => 'ควจ. ...../' . (date('Y') + 543),
        'created_date' => date('Y-m-d'),
        'requester_name' => '',
        'requester_position' => '',
        'requester_department' => '',
        'vehicle_id' => 1,
        'plate_number' => 'นข.1332 นธ.',
        'purpose' => '',
        'route_from' => '',
        'route_to' => '',
        'start_datetime' => '',
        'end_datetime' => '',
        'passenger_count' => '',
        'passenger_names' => '',
        'controller_name' => '',
        'status' => 'draft'
    ];
    $app = [];
}


// ฟังก์ชันแยกวัน เดือน ปี เวลา
function parseDateParts($datetime) {
    if (!$datetime || $datetime == '0000-00-00 00:00:00') {
        return ['d' => '&nbsp;&nbsp;&nbsp;&nbsp;', 'm' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', 'm_short' => '&nbsp;&nbsp;&nbsp;&nbsp;', 'y' => '&nbsp;&nbsp;&nbsp;&nbsp;', 'time' => '&nbsp;&nbsp;&nbsp;&nbsp;'];
    }
    $t = strtotime($datetime);
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $thaiMonthsShort = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    return [
        'd' => date('j', $t),
        'm' => $thaiMonths[(int)date('n', $t)],
        'm_short' => $thaiMonthsShort[(int)date('n', $t)],
        'y' => (string)(date('Y', $t) + 543),
        'time' => date('H:i', $t)
    ];
}

$createdParts = parseDateParts($b['created_at'] ?? $b['created_date'] ?? date('Y-m-d'));
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
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 4mm 7mm 3mm 7mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'TH Sarabun New', 'TH Sarabun PSK', 'Sarabun', sans-serif;
            font-size: 14.5pt;
            line-height: 1.3;
            color: #000;
            background-color: #f0f2f5;
            margin: 0;
            padding: 15px 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .page-container {
            width: 210mm;
            min-height: 297mm;
            padding: 6mm 12mm 4mm 12mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.15);
            position: relative;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        
        /* สไตล์เส้นประสำหรับข้อความที่กรอก (ไม่ตัดทอนข้อความ) */
        .dots-fill {
            flex: 1;
            border-bottom: 1px dotted #000;
            text-align: center;
            padding: 0 4px;
            font-weight: 500;
            white-space: nowrap;
            height: 1.22em;
            line-height: 1.22em;
        }
        .dots-inline {
            display: inline-block;
            border-bottom: 1px dotted #000;
            text-align: center;
            padding: 0 4px;
            font-weight: 500;
            height: 1.22em;
            line-height: 1.22em;
        }
        .nowrap {
            white-space: nowrap;
        }

        /* แต่ละบรรทัดของแบบฟอร์ม */
        .form-line {
            display: flex;
            align-items: baseline;
            line-height: 1.32;
            margin-bottom: 1px;
            font-size: 14.5pt;
            width: 100%;
        }
        .form-line.indent {
            padding-left: 2cm; /* ย่อหน้ามาตรฐานหนังสือราชการไทย */
        }

        .divider {
            border-top: 1px solid #000;
            margin: 2.5px 0;
        }
        .two-cols {
            display: flex;
            width: 100%;
        }
        .col-half {
            width: 50%;
            padding: 0 6px;
        }
        .col-half:first-child {
            border-right: 1px solid #000;
        }
        .notice-box-stamp {
            border: 1.5px solid #000;
            padding: 3px 8px;
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            display: inline-block;
            line-height: 1.2;
        }
        .controller-box {
            border: 1px solid #000;
            padding: 3px 8px;
            text-align: center;
            width: 180px;
        }
        .checkbox-symbol {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            font-size: 11pt;
            margin-right: 3px;
        }

        /* แถบเครื่องมือสั่งพิมพ์ด้านบน */
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
            @page {
                size: A4 portrait;
                margin: 4mm 6mm 3mm 6mm;
            }
            html, body {
                width: 100% !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                background: transparent !important;
            }
            .page-container {
                box-shadow: none !important;
                border: none !important;
                padding: 2mm 6mm 2mm 6mm !important;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                margin: 0 !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
                page-break-before: avoid !important;
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
    <div class="text-end" style="font-size: 13pt; line-height: 1.15; margin-bottom: 2px;">
        <div>เลขที่ <span class="dots-inline" style="min-width: 90px;"><?= htmlspecialchars($b['doc_no'] ?? '') ?></span></div>
        <div>คณะวิทยาการจัดการ</div>
    </div>

    <!-- ตราสัญลักษณ์และหัวเอกสาร (จัดวางตรงตามแบบฟอร์มต้นฉบับ) -->
    <div style="position: relative; min-height: 62px; margin-bottom: 2px;">
        <!-- ตราสัญลักษณ์มหาวิทยาลัยนราธิวาสราชนครินทร์ (ตำแหน่งด้านซ้ายบนตามเอกสารจริง) -->
        <?php if ($emblemBase64): ?>
        <div style="position: absolute; left: 10px; top: -3px;">
            <img src="<?= $emblemBase64 ?>" alt="ตราสัญลักษณ์ มหาวิทยาลัยนราธิวาสราชนครินทร์" style="height: 64px; width: auto;">
        </div>
        <?php endif; ?>

        <div class="text-center" style="margin-left: 50px;">
            <div class="fw-bold" style="font-size: 16.5pt; line-height: 1.15;">หนังสือขออนุญาตใช้รถยนต์</div>
            <div class="fw-bold" style="font-size: 14.5pt; line-height: 1.2; margin-top: 1px;">คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์</div>
            <div style="font-size: 13.5pt; margin-top: 1px;">
                วันที่ <span class="dots-inline" style="min-width: 30px;"><?= $createdParts['d'] ?></span> 
                เดือน <span class="dots-inline" style="min-width: 85px;"><?= $createdParts['m'] ?></span> 
                พ.ศ. <span class="dots-inline" style="min-width: 50px;"><?= $createdParts['y'] ?></span>
            </div>
        </div>
    </div>

    <!-- เรื่อง และ เรียน -->
    <div style="margin-bottom: 2px; line-height: 1.25; font-size: 14pt;">
        <div><strong>เรื่อง</strong>&nbsp;&nbsp;ขออนุญาตใช้รถยนต์</div>
        <div><strong>เรียน</strong>&nbsp;&nbsp;คณบดีคณะวิทยาการจัดการ</div>
    </div>

    <!-- เนื้อความคำขอ (จัด 6 บรรทัด + จึงเรียนมาฯ ตรงตามแบบฟอร์มราชการต้นฉบับ 100%) -->
    <div class="form-line indent">
        <span class="nowrap">ด้วยข้าพเจ้า (นาย/นาง/นางสาว)</span>
        <span class="dots-fill" style="flex: 1.2;"><?= htmlspecialchars($b['requester_name']) ?></span>
        <span class="nowrap">&nbsp;ตำแหน่ง&nbsp;</span>
        <span class="dots-fill" style="flex: 1;"><?= htmlspecialchars($b['requester_position']) ?></span>
    </div>

    <div class="form-line">
        <span class="nowrap">สาขาวิชา&nbsp;</span>
        <span class="dots-fill" style="flex: 1.1;"><?= htmlspecialchars($b['requester_department']) ?></span>
        <span class="nowrap">&nbsp;มีความประสงค์ขอใช้รถยนต์ หมายเลขทะเบียน&nbsp;</span>
        <span class="dots-fill" style="flex: 0.8; font-weight: bold;"><?= htmlspecialchars($b['plate_number']) ?></span>
        <span class="nowrap">&nbsp;ของคณะวิทยาการจัดการ</span>
    </div>

    <?php
    $rfLen = mb_strlen($b['route_from']);
    $rfStyle = ($rfLen > 40) ? 'font-size: 12.5pt; letter-spacing: -0.2px;' : (($rfLen > 30) ? 'font-size: 13pt;' : '');
    $rtLen = mb_strlen($b['route_to']);
    $rtStyle = ($rtLen > 35) ? 'font-size: 12.5pt;' : '';
    ?>
    <div class="form-line">
        <span class="nowrap">เพื่อใช้ในงาน&nbsp;</span>
        <span class="dots-inline" style="min-width: 70px; flex-shrink: 0;"><?= htmlspecialchars($b['purpose']) ?></span>
        <span class="nowrap">&nbsp;จากเส้นทาง&nbsp;</span>
        <span class="dots-fill" style="flex: 1; min-width: 140px; <?= $rfStyle ?>"><?= htmlspecialchars($b['route_from']) ?></span>
        <span class="nowrap">&nbsp;ถึง&nbsp;</span>
        <span class="dots-inline" style="min-width: 70px; flex-shrink: 0; <?= $rtStyle ?>"><?= htmlspecialchars($b['route_to']) ?></span>
    </div>

    <div class="form-line">
        <span class="nowrap">ตั้งแต่วันที่&nbsp;</span>
        <span class="dots-inline" style="min-width: 26px;"><?= $startParts['d'] ?></span>
        <span class="nowrap">&nbsp;เดือน&nbsp;</span>
        <span class="dots-inline" style="min-width: 75px;"><?= $startParts['m'] ?></span>
        <span class="nowrap">&nbsp;พ.ศ.&nbsp;</span>
        <span class="dots-inline" style="min-width: 45px;"><?= $startParts['y'] ?></span>
        <span class="nowrap">&nbsp;เวลา&nbsp;</span>
        <span class="dots-inline" style="min-width: 45px;"><?= $startParts['time'] ?></span>
        <span class="nowrap">&nbsp;น. ถึงวันที่&nbsp;</span>
        <span class="dots-inline" style="min-width: 26px;"><?= $endParts['d'] ?></span>
        <span class="nowrap">&nbsp;เดือน&nbsp;</span>
        <span class="dots-inline" style="min-width: 75px;"><?= $endParts['m'] ?></span>
        <span class="nowrap">&nbsp;พ.ศ.&nbsp;</span>
        <span class="dots-inline" style="min-width: 45px;"><?= $endParts['y'] ?></span>
    </div>

    <div class="form-line">
        <span class="nowrap">เวลา&nbsp;</span>
        <span class="dots-inline" style="min-width: 45px;"><?= $endParts['time'] ?></span>
        <span class="nowrap">&nbsp;น. โดยมีผู้ร่วมทาง จำนวน&nbsp;</span>
        <span class="dots-inline" style="min-width: 35px; font-weight: bold;"><?= $b['passenger_count'] ?></span>
        <span class="nowrap">&nbsp;คน และมอบหมายให้&nbsp;</span>
        <span class="dots-fill" style="flex: 1; font-weight: bold;"><?= htmlspecialchars($b['controller_name']) ?></span>
    </div>

    <div class="form-line">
        <span class="nowrap">เป็นผู้ควบคุมการใช้รถยนต์ และรับผิดชอบหากมีความเสียหายเกิดขึ้นทุกประการในการขออนุญาตใช้รถในครั้งนี้</span>
    </div>

    <div class="form-line indent" style="margin-top: 1px; margin-bottom: 1px;">
        <span>จึงเรียนมาเพื่อโปรดทราบและพิจารณา</span>
    </div>

    <!-- ส่วนลงชื่อผู้ขอ และผู้ควบคุมรถ -->
    <div style="display: flex; justify-content: flex-end; align-items: flex-start; margin-top: 1px; margin-bottom: 1px; gap: 20px;">
        <!-- ขอแสดงความนับถือ -->
        <div style="text-align: center; width: 250px; font-size: 13.2pt; line-height: 1.18;">
            <div>ขอแสดงความนับถือ</div>
            <div style="margin-top: 5px;">
                ลงชื่อ <span class="dots-inline" style="min-width: 130px;"><?= htmlspecialchars($b['requester_name']) ?></span>
            </div>
            <div style="margin-top: 1px;">
                (<span class="dots-inline" style="min-width: 130px;"><?= htmlspecialchars($b['requester_name']) ?></span>)
            </div>
            <div style="margin-top: 1px;">
                ตำแหน่ง <span class="dots-inline" style="min-width: 125px;"><?= htmlspecialchars($b['requester_position']) ?></span>
            </div>
            <div style="margin-top: 1px;">
                <span class="dots-inline" style="min-width: 25px;"><?= $createdParts['d'] ?></span> /
                <span class="dots-inline" style="min-width: 60px;"><?= $createdParts['m'] ?></span> /
                <span class="dots-inline" style="min-width: 40px;"><?= $createdParts['y'] ?></span>
            </div>
        </div>

        <!-- กล่องผู้ควบคุมรถ -->
        <div class="controller-box" style="font-size: 13.2pt; line-height: 1.18;">
            <div class="fw-bold" style="font-size: 13.2pt; margin-bottom: 1px;">ผู้ควบคุมรถ</div>
            <div style="margin-top: 5px;">
                ลงชื่อ <span class="dots-inline" style="min-width: 120px;"><?= htmlspecialchars($b['controller_name'] ?? '') ?></span>
            </div>
            <div style="margin-top: 1px;">
                (<span class="dots-inline" style="min-width: 120px;"><?= htmlspecialchars($b['controller_name'] ?? '') ?></span>)
            </div>
            <div style="margin-top: 1px;">
                <span class="dots-inline" style="min-width: 25px;"><?= $createdParts['d'] ?></span> /
                <span class="dots-inline" style="min-width: 50px;"><?= $createdParts['m'] ?></span> /
                <span class="dots-inline" style="min-width: 38px;"><?= $createdParts['y'] ?></span>
            </div>
        </div>
    </div>

    <div class="divider"></div>

    <!-- สองคอลัมน์: อาคารสถานที่ vs หัวหน้าสำนักงาน -->
    <div class="two-cols" style="font-size: 13.2pt;">
        <!-- ด้านซ้าย: หัวหน้างานอาคารสถานที่ -->
        <div class="col-half">
            <div class="fw-bold" style="font-size: 13.2pt; margin-bottom: 1px;">ความเห็นของหัวหน้างานอาคารสถานที่</div>
            <div style="margin-top: 1px;">
                <span class="checkbox-symbol"><?= (($app['facility_status'] ?? '') == 'approved') ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> เห็นชอบ
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="checkbox-symbol"><?= (!empty($app['facility_fuel'])) ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> ค่าน้ำมันเชื้อเพลิง
            </div>
            <div style="margin-top: 1px;">
                <span class="checkbox-symbol"><?= (($app['facility_status'] ?? '') == 'rejected') ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> ไม่เห็นชอบ
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="checkbox-symbol"><?= (!empty($app['facility_allowance'])) ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> เบี้ยเลี้ยง/ค่าตอบแทน
            </div>
            <div style="margin-top: 1px; display: flex; align-items: baseline;">
                <span class="nowrap"><span class="checkbox-symbol"><?= (!empty($app['facility_other'])) ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> อื่นๆ ระบุ&nbsp;</span>
                <span class="dots-fill" style="min-width: 140px;"><?= htmlspecialchars($app['facility_other'] ?? '') ?></span>
            </div>
            <div style="margin-top: 5px; text-align: center; line-height: 1.2;">
                ลงชื่อ <span class="dots-inline" style="min-width: 130px;"><?= (!empty($app['facility_status'])) ? htmlspecialchars($app['facility_signer'] ?? 'นายเอกสิทธิ์ คงพิทักษ์') : '' ?></span><br>
                (<?= (!empty($app['facility_status'])) ? htmlspecialchars($app['facility_signer'] ?? 'นายเอกสิทธิ์ คงพิทักษ์') : 'นายเอกสิทธิ์ คงพิทักษ์' ?>)<br>
                หัวหน้างานอาคารสถานที่<br>
                <span style="display: inline-block; margin-top: 1px;">
                    <span class="dots-inline" style="min-width: 25px;"><?= (!empty($app['facility_signed_at'])) ? date('j', strtotime($app['facility_signed_at'])) : '' ?></span> /
                    <span class="dots-inline" style="min-width: 45px;"><?= (!empty($app['facility_signed_at'])) ? parseDateParts($app['facility_signed_at'])['m'] : '' ?></span> /
                    <span class="dots-inline" style="min-width: 38px;"><?= (!empty($app['facility_signed_at'])) ? (date('Y', strtotime($app['facility_signed_at'])) + 543) : '' ?></span>
                </span>
            </div>
        </div>

        <!-- ด้านขวา: หัวหน้าสำนักงานคณบดี -->
        <div class="col-half">
            <div class="fw-bold" style="font-size: 13.2pt; margin-bottom: 1px;">ความเห็นของหัวหน้าสำนักงาน</div>
            <div style="margin-top: 1px; display: flex; align-items: baseline;">
                <span class="checkbox-symbol"><?= (($app['office_status'] ?? '') == 'approved') ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> 
                <span class="nowrap">ควรอนุญาตให้นาย&nbsp;</span>
                <span class="dots-fill" style="min-width: 70px; font-weight: bold;"><?= htmlspecialchars($app['office_driver_assigned'] ?? '') ?></span>
                <span class="nowrap">&nbsp;ปฏิบัติหน้าที่พนักงานขับรถ</span>
            </div>
            <div style="margin-top: 1px; display: flex; align-items: baseline;">
                <span class="nowrap"><span class="checkbox-symbol"><?= (($app['office_status'] ?? '') == 'rejected') ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> ไม่อนุญาต เพราะ&nbsp;</span>
                <span class="dots-fill" style="min-width: 120px;"><?= htmlspecialchars($app['office_reason'] ?? '') ?></span>
            </div>
            <div style="margin-top: 1px; display: flex; align-items: baseline;">
                <span class="nowrap"><span class="checkbox-symbol"><?= (!empty($app['office_other'])) ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> อื่นๆ&nbsp;</span>
                <span class="dots-fill" style="min-width: 160px;"><?= htmlspecialchars($app['office_other'] ?? '') ?></span>
            </div>
            <div style="margin-top: 5px; text-align: center; line-height: 1.2;">
                ลงชื่อ <span class="dots-inline" style="min-width: 130px;"><?= (!empty($app['office_status'])) ? htmlspecialchars($app['office_signer'] ?? 'นางสาววิภาดา ทองปิ่น') : '' ?></span><br>
                (<?= (!empty($app['office_status'])) ? htmlspecialchars($app['office_signer'] ?? 'นางสาววิภาดา ทองปิ่น') : 'นางสาววิภาดา ทองปิ่น' ?>)<br>
                หัวหน้าสำนักงานคณบดี<br>
                <span style="display: inline-block; margin-top: 1px;">
                    <span class="dots-inline" style="min-width: 25px;"><?= (!empty($app['office_signed_at'])) ? date('j', strtotime($app['office_signed_at'])) : '' ?></span> /
                    <span class="dots-inline" style="min-width: 45px;"><?= (!empty($app['office_signed_at'])) ? parseDateParts($app['office_signed_at'])['m'] : '' ?></span> /
                    <span class="dots-inline" style="min-width: 38px;"><?= (!empty($app['office_signed_at'])) ? (date('Y', strtotime($app['office_signed_at'])) + 543) : '' ?></span>
                </span>
            </div>
        </div>
    </div>

    <div class="divider"></div>

    <!-- ส่วนคำสั่งคณบดี -->
    <div style="font-size: 13.2pt;">
        <div class="fw-bold" style="font-size: 13.2pt; margin-bottom: 1px;">คำสั่ง</div>
        <div style="margin-top: 1px; font-size: 13.2pt; display: flex; align-items: baseline;">
            <span class="nowrap"><span class="checkbox-symbol"><?= (($app['dean_status'] ?? '') == 'approved') ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> อนุญาต</span>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <span class="nowrap"><span class="checkbox-symbol"><?= (($app['dean_status'] ?? '') == 'rejected') ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> ไม่อนุญาต เพราะ&nbsp;</span>
            <span class="dots-fill" style="min-width: 120px;"><?= htmlspecialchars($app['dean_reason'] ?? '') ?></span>
            &nbsp;&nbsp;&nbsp;
            <span class="nowrap"><span class="checkbox-symbol"><?= (!empty($app['dean_other'])) ? '(&nbsp;✓&nbsp;)' : '(&nbsp;&nbsp;&nbsp;)' ?></span> อื่นๆ ระบุ&nbsp;</span>
            <span class="dots-fill" style="min-width: 110px;"><?= htmlspecialchars($app['dean_other'] ?? '') ?></span>
        </div>
        <div style="margin-top: 5px; text-align: center; line-height: 1.2;">
            ลงชื่อ <span class="dots-inline" style="min-width: 150px;"><?= (!empty($app['dean_status'])) ? htmlspecialchars($app['dean_signer'] ?? 'อาจารย์ ดร.สุมาลี กรดกางกั้น') : '' ?></span><br>
            (<?= (!empty($app['dean_status'])) ? htmlspecialchars($app['dean_signer'] ?? 'อาจารย์ ดร.สุมาลี กรดกางกั้น') : 'อาจารย์ ดร.สุมาลี กรดกางกั้น' ?>)<br>
            คณบดีคณะวิทยาการจัดการ<br>
            <span style="display: inline-block; margin-top: 1px;">
                <span class="dots-inline" style="min-width: 25px;"><?= (!empty($app['dean_signed_at'])) ? date('j', strtotime($app['dean_signed_at'])) : '' ?></span> /
                <span class="dots-inline" style="min-width: 45px;"><?= (!empty($app['dean_signed_at'])) ? parseDateParts($app['dean_signed_at'])['m'] : '' ?></span> /
                <span class="dots-inline" style="min-width: 38px;"><?= (!empty($app['dean_signed_at'])) ? (date('Y', strtotime($app['dean_signed_at'])) + 543) : '' ?></span>
            </span>
        </div>
    </div>

    <div class="divider"></div>

    <!-- ส่วนบันทึกพนักงานขับรถ -->
    <div style="font-size: 13.2pt;">
        <div class="text-center fw-bold" style="font-size: 13.2pt; margin-bottom: 1px;">บันทึกพนักงานขับรถ</div>
        <div style="margin-top: 1px; font-size: 13.2pt; display: flex; align-items: baseline;">
            <span class="nowrap">ข้าพเจ้านาย&nbsp;</span>
            <span class="dots-fill" style="min-width: 180px; font-weight: bold;"><?= htmlspecialchars($app['driver_signer'] ?? $app['office_driver_assigned'] ?? 'นายธเนศ อินเอิบ') ?></span>
            <span class="nowrap">&nbsp;ได้รับทราบการขอใช้รถยนต์แล้ว</span>
        </div>

        <div style="margin-top: 4px; display: flex; justify-content: space-between; align-items: center;">
            <!-- กล่องข้อความกรอบซ้าย -->
            <div class="notice-box-stamp">
                ขออนุญาตให้แล้วเสร็จ<br>ก่อนใช้รถอย่างน้อย 1 วัน
            </div>

            <!-- ส่วนลงชื่อพนักงานขับรถ -->
            <div style="text-align: center; width: 240px; line-height: 1.2;">
                ลงชื่อ <span class="dots-inline" style="min-width: 130px;"><?= (!empty($app['driver_ack_status'])) ? htmlspecialchars($app['driver_signer'] ?? 'นายธเนศ อินเอิบ') : '' ?></span><br>
                (<?= (!empty($app['driver_ack_status'])) ? htmlspecialchars($app['driver_signer'] ?? 'นายธเนศ อินเอิบ') : 'นายธเนศ อินเอิบ' ?>)<br>
                พนักงานขับรถยนต์<br>
                <span style="display: inline-block; margin-top: 1px;">
                    <span class="dots-inline" style="min-width: 25px;"><?= (!empty($app['driver_acknowledged_at'])) ? date('j', strtotime($app['driver_acknowledged_at'])) : '' ?></span> /
                    <span class="dots-inline" style="min-width: 45px;"><?= (!empty($app['driver_acknowledged_at'])) ? parseDateParts($app['driver_acknowledged_at'])['m'] : '' ?></span> /
                    <span class="dots-inline" style="min-width: 38px;"><?= (!empty($app['driver_acknowledged_at'])) ? (date('Y', strtotime($app['driver_acknowledged_at'])) + 543) : '' ?></span>
                </span>
            </div>
        </div>
    </div>
</div>

</body>
</html>
