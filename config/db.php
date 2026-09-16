<?php
// config/db.php - การตั้งค่าและเชื่อมต่อฐานข้อมูล
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบสภาพแวดล้อม Vercel Serverless
$isVercel = (getenv('VERCEL') || isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']));
if ($isVercel) {
    $dbDir = '/tmp/data';
    if (!file_exists($dbDir)) {
        @mkdir($dbDir, 0777, true);
    }
    $dbPath = $dbDir . '/van_booking.sqlite';
    $sourceDb = __DIR__ . '/../data/van_booking.sqlite';
    if (!file_exists($dbPath) && file_exists($sourceDb)) {
        @copy($sourceDb, $dbPath);
    }
} else {
    $dbDir = __DIR__ . '/../data';
    if (!file_exists($dbDir)) {
        @mkdir($dbDir, 0777, true);
    }
    $dbPath = $dbDir . '/van_booking.sqlite';
}

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;");
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// สร้างตารางหากยังไม่มี
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    prefix TEXT,
    fullname TEXT NOT NULL,
    position TEXT NOT NULL,
    department TEXT NOT NULL,
    role TEXT NOT NULL, -- requester, facility_head, office_head, dean, driver, admin
    phone TEXT,
    signature_img TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vehicles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plate_number TEXT UNIQUE NOT NULL,
    brand_model TEXT NOT NULL,
    vehicle_type TEXT NOT NULL, -- รถตู้, รถกระบะ, รถเก๋ง
    seats INTEGER DEFAULT 14,
    status TEXT DEFAULT 'active', -- active, maintenance, inactive
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    doc_no TEXT UNIQUE,
    created_date DATE NOT NULL,
    user_id INTEGER,
    requester_name TEXT NOT NULL,
    requester_position TEXT NOT NULL,
    requester_department TEXT NOT NULL,
    vehicle_id INTEGER NOT NULL,
    plate_number TEXT NOT NULL,
    purpose TEXT NOT NULL,
    route_from TEXT NOT NULL,
    route_to TEXT NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    passenger_count INTEGER DEFAULT 1,
    passenger_names TEXT,
    controller_name TEXT NOT NULL,
    status TEXT DEFAULT 'pending_facility', 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(vehicle_id) REFERENCES vehicles(id)
);

CREATE TABLE IF NOT EXISTS approvals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    booking_id INTEGER UNIQUE NOT NULL,
    -- 1. หัวหน้างานอาคารสถานที่
    facility_status TEXT, -- approved, rejected
    facility_fuel INTEGER DEFAULT 0, -- 1 = มีค่าน้ำมัน
    facility_allowance INTEGER DEFAULT 0, -- 1 = มีเบี้ยเลี้ยง
    facility_other TEXT,
    facility_signer TEXT,
    facility_comment TEXT,
    facility_signed_at DATETIME,
    
    -- 2. หัวหน้าสำนักงานคณบดี
    office_status TEXT, -- approved, rejected
    office_driver_assigned TEXT,
    office_reason TEXT,
    office_other TEXT,
    office_signer TEXT,
    office_signed_at DATETIME,
    
    -- 3. คณบดีคณะวิทยาการจัดการ
    dean_status TEXT, -- approved, rejected
    dean_reason TEXT,
    dean_other TEXT,
    dean_signer TEXT,
    dean_signed_at DATETIME,
    
    -- 4. พนักงานขับรถยนต์
    driver_ack_status TEXT, -- acknowledged
    driver_signer TEXT,
    driver_acknowledged_at DATETIME,
    
    FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);
");

// ใส่ข้อมูลเริ่มต้น (Seed Data) เมื่อสร้างฐานข้อมูลใหม่
$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($userCount == 0) {
    // ผู้ใช้งานเริ่มต้น
    $defaultUsers = [
        ['Aeksit', '30052525', 'นาย', 'เอกสิทธิ์ คงพิทักษ์', 'หัวหน้างานอาคารสถานที่ / ผู้ดูแลระบบ', 'งานอาคารสถานที่และยานพาหนะ', 'admin', '081-999-8881'],
        ['user1', '123456', 'อาจารย์ ดร.', 'สมชาย ใจดี', 'อาจารย์ประจำสาขาวิชา', 'สาขาวิชาคอมพิวเตอร์ธุรกิจ', 'requester', '089-111-2222'],
        ['user2', '123456', 'อาจารย์', 'อารีนา สุมาลี', 'อาจารย์ประจำสาขาวิชา', 'สาขาวิชาการจัดการ', 'requester', '089-333-4444'],
        ['facility_head', '123456', 'นาย', 'เอกสิทธิ์ คงพิทักษ์', 'หัวหน้างานอาคารสถานที่', 'งานอาคารสถานที่และยานพาหนะ', 'facility_head', '081-999-8881'],
        ['office_head', '123456', 'นาง', 'ซูไบดะห์ หะยีมะ', 'หัวหน้าสำนักงานคณบดี', 'สำนักงานคณบดี', 'office_head', '081-999-8882'],
        ['dean', '123456', 'ผู้ช่วยศาสตราจารย์ ดร.', 'บงกช กมลเปรม', 'คณบดีคณะวิทยาการจัดการ', 'คณะวิทยาการจัดการ', 'dean', '081-999-8883'],
        ['driver1', '123456', 'นาย', 'ธเนศ อินเอิบ', 'พนักงานขับรถยนต์', 'งานอาคารสถานที่และยานพาหนะ', 'driver', '082-777-6661']
    ];

    $stmtUser = $pdo->prepare("INSERT INTO users (username, password, prefix, fullname, position, department, role, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($defaultUsers as $u) {
        $stmtUser->execute([$u[0], password_hash($u[1], PASSWORD_DEFAULT), $u[2], $u[3], $u[4], $u[5], $u[6], $u[7]]);
    }

    // รถยนต์เริ่มต้นของคณะ
    $defaultVehicles = [
        ['นข.1332 นธ.', 'Toyota Commuter', 'รถตู้โดยสารปรับอากาศ', 14, 'active', 'รถตู้ประจำคณะวิทยาการจัดการ']
    ];

    $stmtVeh = $pdo->prepare("INSERT INTO vehicles (plate_number, brand_model, vehicle_type, seats, status, notes) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($defaultVehicles as $v) {
        $stmtVeh->execute($v);
    }

    // สร้างข้อมูลคำขอตัวอย่าง เพื่อให้เห็นภาพการทำงาน
    $tomorrow = date('Y-m-d', strtotime('+2 days'));
    $next2Days = date('Y-m-d', strtotime('+3 days'));
    
    $docNo1 = "ควจ. 001/" . (date('Y') + 543);
    $pdo->prepare("
        INSERT INTO bookings (
            doc_no, created_date, user_id, requester_name, requester_position, requester_department,
            vehicle_id, plate_number, purpose, route_from, route_to, start_datetime, end_datetime,
            passenger_count, passenger_names, controller_name, status
        ) VALUES (
            ?, date('now'), 2, 'อาจารย์ ดร.สมชาย ใจดี', 'อาจารย์ประจำสาขาวิชา', 'สาขาวิชาคอมพิวเตอร์ธุรกิจ',
            1, 'นข.1332 นธ.', 'นำนักศึกษาเข้าร่วมการแข่งขันทักษะวิชาการระดับชาติ', 
            'คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์', 'มหาวิทยาลัยสงขลานครินทร์ วิทยาเขตหาดใหญ่',
            ?, ?, 12, '1. อ.ดร.สมชาย ใจดี\n2. น.ส.ฟาติมา มะลี\n3. นายอับดุลเลาะห์ สาและ และนักศึกษาตัวแทนรวม 12 คน',
            'อาจารย์ ดร.สมชาย ใจดี', 'completed'
        )
    ")->execute([
        $docNo1,
        $tomorrow . " 06:00:00",
        $next2Days . " 18:00:00"
    ]);
    $bookingId1 = $pdo->lastInsertId();

    // บันทึกการอนุมัติครบทั้ง 4 ขั้นตอนของตัวอย่างแรก
    $pdo->prepare("
        INSERT INTO approvals (
            booking_id, 
            facility_status, facility_fuel, facility_allowance, facility_other, facility_signer, facility_signed_at,
            office_status, office_driver_assigned, office_reason, office_signer, office_signed_at,
            dean_status, dean_reason, dean_signer, dean_signed_at,
            driver_ack_status, driver_signer, driver_acknowledged_at
        ) VALUES (
            ?,
            'approved', 1, 1, 'ขอสนับสนุนน้ำมัน 1 ถัง', 'นายเอกสิทธิ์ คงพิทักษ์', datetime('now', '-2 days'),
            'approved', 'นายธเนศ อินเอิบ', '', 'นางซูไบดะห์ หะยีมะ', datetime('now', '-2 days'),
            'approved', '', 'ผู้ช่วยศาสตราจารย์ ดร.บงกช กมลเปรม', datetime('now', '-1 days'),
            'acknowledged', 'นายธเนศ อินเอิบ', datetime('now', '-12 hours')
        )
    ")->execute([$bookingId1]);

    // ตัวอย่างที่ 2: รอการพิจารณาจากหัวหน้างานอาคารสถานที่
    $docNo2 = "ควจ. 002/" . (date('Y') + 543);
    $in3Days = date('Y-m-d', strtotime('+4 days'));
    $in4Days = date('Y-m-d', strtotime('+4 days'));
    $pdo->prepare("
        INSERT INTO bookings (
            doc_no, created_date, user_id, requester_name, requester_position, requester_department,
            vehicle_id, plate_number, purpose, route_from, route_to, start_datetime, end_datetime,
            passenger_count, passenger_names, controller_name, status
        ) VALUES (
            ?, date('now'), 3, 'อาจารย์อารีนา สุมาลี', 'อาจารย์ประจำสาขาวิชา', 'สาขาวิชาการจัดการ',
            1, 'นข.1332 นธ.', 'เข้าร่วมประชุมทางวิชาการและศึกษาดูงานการบริหารจัดการองค์กร', 
            'คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์', 'ศาลากลางจังหวัดยะลา',
            ?, ?, 6, 'คณะอาจารย์สาขาวิชาการจัดการ 6 ท่าน',
            'อาจารย์อารีนา สุมาลี', 'pending_facility'
        )
    ")->execute([
        $docNo2,
        $in3Days . " 08:30:00",
        $in4Days . " 16:30:00"
    ]);
    $bookingId2 = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO approvals (booking_id) VALUES (?)")->execute([$bookingId2]);
}

// ฟังก์ชันแปลงวันที่เป็นภาษาไทย
function thaiDate($datetime, $showTime = true) {
    if (!$datetime || $datetime == '0000-00-00 00:00:00') return '-';
    $time = strtotime($datetime);
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $day = date('j', $time);
    $month = $thaiMonths[(int)date('n', $time)];
    $year = date('Y', $time) + 543;
    $timeStr = date('H:i', $time) . ' น.';
    
    if ($showTime) {
        return "$day $month $year เวลา $timeStr";
    }
    return "$day $month $year";
}

function thaiDateShort($date) {
    if (!$date) return '-';
    $time = strtotime($date);
    $thaiMonthsShort = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    return date('j', $time) . ' ' . $thaiMonthsShort[(int)date('n', $time)] . ' ' . (date('Y', $time) + 543);
}

// ฟังก์ชันแสดงป้ายสถานะ
function getStatusBadge($status) {
    switch ($status) {
        case 'pending_facility':
            return '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> รอหัวหน้าอาคารสถานที่พิจารณา</span>';
        case 'approved':
        case 'completed':
        case 'pending_office':
        case 'pending_dean':
        case 'pending_driver':
            return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> เห็นชอบแล้ว (พร้อมพิมพ์เสนอต่อ)</span>';
        case 'rejected':
            return '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> ไม่เห็นชอบ</span>';
        case 'cancelled':
            return '<span class="badge bg-dark"><i class="fas fa-ban me-1"></i> ยกเลิก</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}
