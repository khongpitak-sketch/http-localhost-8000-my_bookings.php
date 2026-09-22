<?php
// config/db.php - การตั้งค่าและเชื่อมต่อฐานข้อมูล
date_default_timezone_set('Asia/Bangkok');

// ระบบเวอร์ชัน (System Version)
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2.0');
    define('APP_VERSION_FULL', 'Version 2.0 (Build 2026)');
}

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

// อัปเกรดคอลัมน์ความปลอดภัยในตาราง bookings (หากยังไม่มี)
try {
    $existingCols = $pdo->query("PRAGMA table_info(bookings)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('client_ip', $existingCols)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN client_ip TEXT");
    }
    if (!in_array('user_agent', $existingCols)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN user_agent TEXT");
    }
    if (!in_array('is_flagged_fake', $existingCols)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN is_flagged_fake INTEGER DEFAULT 0");
    }
    if (!in_array('fake_reason', $existingCols)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN fake_reason TEXT");
    }

    // อัปเกรดคอลัมน์ในตาราง users สำหรับเก็บรหัสผ่านที่ Admin ตรวจสอบและแก้ไขได้
    $existingUserCols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('plain_password', $existingUserCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN plain_password TEXT");
    }
} catch (Exception $e) {
    // ข้ามกรณีมีคอลัมน์อยู่แล้ว
}

// ฟังก์ชันดึง Client IP Address จริง
if (!function_exists('getClientIP')) {
    function getClientIP() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

// รายชื่อผู้ใช้งานและรหัสผ่าน คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์ (58 รายชื่อ)
$facultyUserList = [
    ['ซูไบดะห์ หะยีมะ', '12345#', 'นาง', 'ซูไบดะห์ หะยีมะ', 'เจ้าหน้าที่บริหารงานทั่วไป', 'คณะวิทยาการจัดการ', 'requester', '081-999-8882'],
    ['บงกช กมลเปรม', '23456#', 'ผศ.ดร.', 'บงกช กมลเปรม', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', '081-999-8883'],
    ['พูนพิศ ธิตินันท์', '33456#', 'นาง', 'พูนพิศ ธิตินันท์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['พระรักษ์ อมรศักดิ์', '43456#', 'นาย', 'พระรักษ์ อมรศักดิ์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['นันทพร โกสิยาภรณ์', '53456#', 'นางสาว', 'นันทพร โกสิยาภรณ์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['สุมาลี กรดกางกั้น', '63456#', 'อาจารย์ ดร.', 'สุมาลี กรดกางกั้น', 'คณบดีคณะวิทยาการจัดการ', 'คณะวิทยาการจัดการ', 'dean', '081-999-8883'],
    ['เกศแก้ว ประดิษฐ์', '73456#', 'นางสาว', 'เกศแก้ว ประดิษฐ์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['กฤษณา พรหมชาติ', '83456#', 'นางสาว', 'กฤษณา พรหมชาติ', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ชนาธิป หวังวรวงศ์', '93456#', 'นาง', 'ชนาธิป หวังวรวงศ์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ธมยันตี ประยูรพันธ์', '10456#', 'นางสาว', 'ธมยันตี ประยูรพันธ์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['อัฟซา อาแว', '11456#', 'นาง', 'อัฟซา อาแว', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ศิริลักษณ์ อินทสไร', '12456#', 'นางสาว', 'ศิริลักษณ์ อินทสไร', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ศิรินุช แก้วคงสุข', '13456#', 'นางสาว', 'ศิรินุช แก้วคงสุข', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['มูฮำมัดรอซาลี บือราเฮง', '14456#', 'นาย', 'มูฮำมัดรอซาลี บือราเฮง', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ปุณยนุช ขจร', '15456#', 'นางสาว', 'ปุณยนุช ขจร', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['สุวรรณา คงเต็ม', '16456#', 'นางสาว', 'สุวรรณา คงเต็ม', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['นำทิพย์ ชำนิอารัญ', '17456#', 'นางสาว', 'นำทิพย์ ชำนิอารัญ', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['วิภาดา ทองปิ่น', '18456#', 'นางสาว', 'วิภาดา ทองปิ่น', 'หัวหน้าสำนักงานคณบดี', 'สำนักงานคณบดี', 'office_head', '081-999-8882'],
    ['แวฟารูก แวตี', '19456#', 'นาย', 'แวฟารูก แวตี', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['เอกสิทธิ คงพิทักษ์', '20556#', 'นาย', 'เอกสิทธิ์ คงพิทักษ์', 'หัวหน้างานอาคารสถานที่และยานพาหนะ', 'งานอาคารสถานที่และยานพาหนะ', 'admin', '081-999-8881'],
    ['มอส ป้องยะ', '21556#', 'นางสาว', 'มอส ป้องยะ', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['อำภาพร บุปผาคร', '22756#', 'นาง', 'อำภาพร บุปผาคร', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ธเนศ อินเอิบ', '23856#', 'นาย', 'ธเนศ อินเอิบ', 'พนักงานขับรถยนต์', 'งานอาคารสถานที่และยานพาหนะ', 'driver', '082-777-6661'],
    ['อิดรีส เจ๊ะมะลี', '24956#', 'นาย', 'อิดรีส เจ๊ะมะลี', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ลัคนา วิชญกุล', '25056#', 'นางสาว', 'ลัคนา วิชญกุล', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['พานี แก้วสีขวัญ', '26156#', 'นางสาว', 'พานี แก้วสีขวัญ', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['อนิลต้า อุนทริจันทร์', '27256#', 'นางสาว', 'อนิลต้า อุนทริจันทร์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['พิมลพรรณ สุวรรณรัตน์', '28356#', 'นางสาว', 'พิมลพรรณ สุวรรณรัตน์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['อนภิชฌา เพ็ชรเทพ', '29456#', 'นางสาว', 'อนภิชฌา เพ็ชรเทพ', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ฮาเซน เงาะหิ', '30556#', 'นาย', 'ฮาเซน เงาะหิ', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ซูไรดา บินมูซอ', '31656#', 'นางสาว', 'ซูไรดา บินมูซอ', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ชลกานจน์ สถะบดี', '32756#', 'นางสาว', 'ชลกานจน์ สถะบดี', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['เนตรวดี เพชรประดับ', '33856#', 'นางสาว', 'เนตรวดี เพชรประดับ', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['นิฟาตีฮะ ปัตนวงศ์', '34956#', 'นางสาว', 'นิฟาตีฮะ ปัตนวงศ์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['มัณฑนา กระใหมวงศ์', '35056#', 'นาง', 'มัณฑนา กระใหมวงศ์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['อิบรอฮิม สารีมาแซ', '36156#', 'นาย', 'อิบรอฮิม สารีมาแซ', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['พัชนี ตูเล๊ะ', '37256#', 'นางสาว', 'พัชนี ตูเล๊ะ', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ผการัตน์ ทองจันทร์', '38356#', 'นาง', 'ผการัตน์ ทองจันทร์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['สุรเชษฐ์ สังขพันธ์', '39456#', 'นาย', 'สุรเชษฐ์ สังขพันธ์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['พรทิพย์ มานพดำ', '40556#', 'นางสาว', 'พรทิพย์ มานพดำ', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['รอยฮาน สะอารี', '41656#', 'นางสาว', 'รอยฮาน สะอารี', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ไซเฟีย สามะอาลี', '42756#', 'นาง', 'ไซเฟีย สามะอาลี', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ทิพวรรณ รัตนพรหม', '43856#', 'นางสาว', 'ทิพวรรณ รัตนพรหม', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['เปาซี วานอง', '44956#', 'นาย', 'เปาซี วานอง', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ศรัณ เนื้อน้อย', '45056#', 'นาย', 'ศรัณ เนื้อน้อย', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['วีรศักดิ โศจิพันธุ์', '46166#', 'นาย', 'วีรศักดิ์ โศจิพันธุ์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['สรัญณี อุเส็นยาง', '47266#', 'นางสาว', 'สรัญณี อุเส็นยาง', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['กชพรพรรณ พงค์ทองเมือง', '48366#', 'นางสาว', 'กชพรพรรณ พงค์ทองเมือง', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['ไฮดา สูดินปรีดา', '49466#', 'นาง', 'ไฮดา สูดินปรีดา', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['เจ๊ะอีลย๊าส โตะตาหยง', '50566#', 'นาย', 'เจ๊ะอีลย๊าส โตะตาหยง', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['นันทิกานต์ ประสพสุข', '51666#', 'นางสาว', 'นันทิกานต์ ประสพสุข', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['มธุรส ทองอินทราช', '52766#', 'นางสาว', 'มธุรส ทองอินทราช', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['รุ่งศิริ ผดุงรัตน์', '53866#', 'นางสาว', 'รุ่งศิริ ผดุงรัตน์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['บารมี หลังยาหน่าย', '54966#', 'นาย', 'บารมี หลังยาหน่าย', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['คมสัน หลงละเลิง', '57276#', 'นาย', 'คมสัน หลงละเลิง', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['บูชิตา อารียาภรณ์', '58376#', 'นางสาว', 'บูชิตา อารียาภรณ์', 'อาจารย์ประจำสาขาวิชา', 'คณะวิทยาการจัดการ', 'requester', ''],
    ['Aeksit', '30052525', 'นาย', 'เอกสิทธิ์ คงพิทักษ์', 'หัวหน้างานอาคารสถานที่ / ผู้ดูแลระบบ', 'งานอาคารสถานที่และยานพาหนะ', 'admin', '081-999-8881']
];

// ซิงค์หรือสร้างผู้ใช้งานในระบบ
foreach ($facultyUserList as $fu) {
    $uUsername = $fu[0];
    $uPlainPass = $fu[1];
    $uPrefix = $fu[2];
    $uFullname = $fu[3];
    $uPos = $fu[4];
    $uDept = $fu[5];
    $uRole = $fu[6];
    $uPhone = $fu[7];

    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->execute([$uUsername]);
    $existingId = $checkStmt->fetchColumn();

    if ($existingId) {
        $upd = $pdo->prepare("UPDATE users SET plain_password = ?, prefix = ?, fullname = ?, position = ?, department = ?, role = ?, phone = COALESCE(phone, ?) WHERE id = ?");
        $upd->execute([$uPlainPass, $uPrefix, $uFullname, $uPos, $uDept, $uRole, $uPhone, $existingId]);
    } else {
        $ins = $pdo->prepare("INSERT INTO users (username, password, plain_password, prefix, fullname, position, department, role, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$uUsername, password_hash($uPlainPass, PASSWORD_DEFAULT), $uPlainPass, $uPrefix, $uFullname, $uPos, $uDept, $uRole, $uPhone]);
    }
}

// ลบผู้ใช้ตัวอย่างเก่าที่ไม่จำเป็น
try {
    $pdo->exec("DELETE FROM users WHERE username IN ('user1', 'user2', 'facility_head', 'office_head', 'driver1')");
} catch (Exception $e) {}

// รถยนต์เริ่มต้นของคณะ (หากยังไม่มี)
$vehCount = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
if ($vehCount == 0) {
    $defaultVehicles = [
        ['นข.1332 นธ.', 'Toyota Commuter', 'รถตู้โดยสารปรับอากาศ', 14, 'active', 'รถตู้ประจำคณะวิทยาการจัดการ']
    ];
    $stmtVeh = $pdo->prepare("INSERT INTO vehicles (plate_number, brand_model, vehicle_type, seats, status, notes) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($defaultVehicles as $v) {
        $stmtVeh->execute($v);
    }
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
        case 'rejected_fraud':
            return '<span class="badge bg-danger text-white"><i class="fas fa-shield-virus me-1"></i> ปฏิเสธ (ข้อมูลเท็จ/สแปม)</span>';
        case 'cancelled':
            return '<span class="badge bg-dark"><i class="fas fa-ban me-1"></i> ยกเลิก</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}
