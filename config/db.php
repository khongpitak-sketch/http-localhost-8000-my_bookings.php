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

// ตรวจสอบการเชื่อมต่อ Cloud Database ภายนอก (Persistent Database for Vercel / Cloud)
// ตรวจสอบการเชื่อมต่อ Cloud Database ภายนอก (Persistent Database for Vercel / Cloud)
// รองรับ DATABASE_URL, MYSQL_URL, POSTGRES_URL หรือ MYSQL_HOST / DB_HOST
$dbUrl = trim($_ENV['DATABASE_URL'] ?? ($_SERVER['DATABASE_URL'] ?? (getenv('DATABASE_URL') ?: '')));
if (empty($dbUrl)) {
    $dbUrl = trim($_ENV['MYSQL_URL'] ?? ($_SERVER['MYSQL_URL'] ?? (getenv('MYSQL_URL') ?: '')));
}
if (empty($dbUrl)) {
    $dbUrl = trim($_ENV['POSTGRES_URL'] ?? ($_SERVER['POSTGRES_URL'] ?? (getenv('POSTGRES_URL') ?: '')));
}

// ค่าเริ่มต้นสำหรับระบบบน Vercel (Supabase Cloud Database ถาวร)
// ช่วยให้เชื่อมต่อฐานข้อมูล Cloud อัตโนมัติทันที ทุกโปรเจกต์บน Vercel
if (empty($dbUrl) && $isVercel) {
    $dbUrl = 'postgresql://postgres:PnuVan2026#@db.rqhmadhdagvbiwlykmyy.supabase.co:5432/postgres';
}

$dbHost = trim($_ENV['MYSQL_HOST'] ?? ($_SERVER['MYSQL_HOST'] ?? (getenv('MYSQL_HOST') ?: (getenv('DB_HOST') ?: ''))));
$dbDriver = 'sqlite';
$dbConnError = '';

if (!function_exists('parseDatabaseUrl')) {
    function parseDatabaseUrl($url) {
        $url = trim($url, " \t\n\r\0\x0B\"'");
        if (strpos($url, '#') !== false || substr_count($url, '@') > 1) {
            if (preg_match('/^([a-zA-Z0-9_\-]+):\/\/([^:]+):(.*)@([^:\/?#]+)(?::(\d+))?\/([^?#]+)(?:\?(.*))?$/', $url, $m)) {
                return [
                    'scheme' => $m[1],
                    'user' => $m[2],
                    'pass' => $m[3],
                    'host' => $m[4],
                    'port' => !empty($m[5]) ? (int)$m[5] : null,
                    'path' => '/' . $m[6],
                    'query' => $m[7] ?? null
                ];
            }
        }
        $p = parse_url($url);
        if ($p !== false && !empty($p['scheme']) && !empty($p['host']) && empty($p['fragment'])) {
            return $p;
        }
        if (preg_match('/^([a-zA-Z0-9_\-]+):\/\/([^:]+):(.*)@([^:\/?#]+)(?::(\d+))?\/([^?#]+)(?:\?(.*))?$/', $url, $m)) {
            return [
                'scheme' => $m[1],
                'user' => $m[2],
                'pass' => $m[3],
                'host' => $m[4],
                'port' => !empty($m[5]) ? (int)$m[5] : null,
                'path' => '/' . $m[6],
                'query' => $m[7] ?? null
            ];
        }
        return false;
    }
}

if (!empty($dbUrl)) {
    $parsed = parseDatabaseUrl($dbUrl);
    $scheme = strtolower($parsed['scheme'] ?? '');
    if (in_array($scheme, ['mysql', 'mariadb'])) {
        $dbDriver = 'mysql';
    } elseif (in_array($scheme, ['postgres', 'postgresql'])) {
        $dbDriver = 'pgsql';
    }
} elseif (!empty($dbHost)) {
    $dbDriver = 'mysql';
}

$pdo = null;

if ($dbDriver === 'mysql') {
    try {
        if (!empty($dbUrl)) {
            $p = parseDatabaseUrl($dbUrl);
            $h = $p['host'] ?? 'localhost';
            $port = $p['port'] ?? 3306;
            $u = isset($p['user']) ? urldecode($p['user']) : '';
            $pw = isset($p['pass']) ? urldecode($p['pass']) : '';
            $db = ltrim($p['path'] ?? '', '/');
        } else {
            $h = $dbHost;
            $port = getenv('MYSQL_PORT') ?: (getenv('DB_PORT') ?: 3306);
            $u = getenv('MYSQL_USER') ?: (getenv('DB_USER') ?: 'root');
            $pw = getenv('MYSQL_PASSWORD') ?: (getenv('DB_PASS') ?: '');
            $db = getenv('MYSQL_DATABASE') ?: (getenv('DB_NAME') ?: 'van_booking');
        }

        $dsn = "mysql:host={$h};port={$port};dbname={$db};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        // ตรวจสอบ CA Bundle อัตโนมัติสำหรับ Managed Cloud Database เช่น TiDB Cloud / Aiven
        $caPaths = [
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/ssl/cert.pem'
        ];
        foreach ($caPaths as $ca) {
            if (file_exists($ca)) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
                break;
            }
        }
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        $pdo = new PDO($dsn, $u, $pw, $options);
    } catch (PDOException $e) {
        $dbConnError = "MySQL: " . $e->getMessage();
        error_log("Remote MySQL Connection Failed, falling back to SQLite: " . $e->getMessage());
        $dbDriver = 'sqlite';
    }
} elseif ($dbDriver === 'pgsql') {
    $p = parseDatabaseUrl($dbUrl);
    $endpoints = [];
    if (!empty($p)) {
        // สำหรับ Supabase บน Vercel ให้ใช้ Pooler (IPv4) เป็นอันดับแรกเพื่อความเร็วสูงสุด
        if (strpos($p['host'] ?? '', 'supabase.co') !== false && preg_match('/db\.([a-zA-Z0-9]+)\.supabase\.co/', $p['host'], $sbMatch)) {
            $ref = $sbMatch[1];
            $pooler = $p;
            $pooler['host'] = "aws-0-ap-northeast-2.pooler.supabase.com";
            $pooler['port'] = 6543;
            $pooler['user'] = "postgres." . $ref;
            $endpoints[] = $pooler;
        }
        $endpoints[] = $p;
    }

    $connected = false;
    $lastErr = '';
    foreach ($endpoints as $currP) {
        try {
            $h = $currP['host'] ?? 'localhost';
            $port = $currP['port'] ?? 5432;
            $u = isset($currP['user']) ? urldecode($currP['user']) : '';
            $pw = isset($currP['pass']) ? urldecode($currP['pass']) : '';
            $db = ltrim($currP['path'] ?? '', '/');
            $dsn = "pgsql:host={$h};port={$port};dbname={$db};sslmode=require;connect_timeout=3";
            $pdo = new PDO($dsn, $u, $pw, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true
            ]);
            $connected = true;
            break;
        } catch (PDOException $e) {
            $lastErr = $e->getMessage();
        }
    }

    if (!$connected) {
        $dbConnError = "PostgreSQL: " . ($lastErr ?: 'Connection failed');
        error_log("Remote PostgreSQL Connection Failed, falling back to SQLite: " . $dbConnError);
        $dbDriver = 'sqlite';
    }
}

if ($dbDriver === 'sqlite') {
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
        $pdo->exec("PRAGMA journal_mode = WAL;");
        $pdo->exec("PRAGMA synchronous = NORMAL;");
        $pdo->exec("PRAGMA cache_size = -64000;");
        $pdo->exec("PRAGMA busy_timeout = 5000;");
        $pdo->exec("PRAGMA temp_store = MEMORY;");
    } catch (PDOException $e) {
        die("Database Connection Error: " . $e->getMessage());
    }
}

// สร้างตารางสำหรับระบบฐานข้อมูลที่ใช้งาน
if ($dbDriver === 'mysql') {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(191) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        plain_password VARCHAR(255),
        prefix VARCHAR(50),
        fullname VARCHAR(255) NOT NULL,
        position VARCHAR(255) NOT NULL,
        department VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL,
        phone VARCHAR(50),
        signature_img TEXT,
        remember_token VARCHAR(255),
        remember_token_expiry DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plate_number VARCHAR(100) UNIQUE NOT NULL,
        brand_model VARCHAR(255) NOT NULL,
        vehicle_type VARCHAR(100) NOT NULL,
        seats INT DEFAULT 14,
        status VARCHAR(50) DEFAULT 'active',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doc_no VARCHAR(100) UNIQUE,
        created_date DATE NOT NULL,
        user_id INT,
        requester_name VARCHAR(255) NOT NULL,
        requester_position VARCHAR(255) NOT NULL,
        requester_department VARCHAR(255) NOT NULL,
        requester_phone VARCHAR(50),
        vehicle_id INT NOT NULL,
        plate_number VARCHAR(100) NOT NULL,
        purpose TEXT NOT NULL,
        route_from VARCHAR(255) NOT NULL,
        route_to VARCHAR(255) NOT NULL,
        start_datetime DATETIME NOT NULL,
        end_datetime DATETIME NOT NULL,
        passenger_count INT DEFAULT 1,
        passenger_names TEXT,
        controller_name VARCHAR(255) NOT NULL,
        status VARCHAR(50) DEFAULT 'pending_facility',
        client_ip VARCHAR(100),
        user_agent TEXT,
        is_flagged_fake INT DEFAULT 0,
        fake_reason TEXT,
        actual_end_datetime DATETIME,
        start_mileage INT,
        end_mileage INT,
        fuel_level VARCHAR(50),
        vehicle_condition TEXT,
        return_notes TEXT,
        returned_by VARCHAR(255),
        return_recorded_by VARCHAR(255),
        return_recorded_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_b_status (status),
        INDEX idx_b_user_id (user_id),
        INDEX idx_b_vehicle_id (vehicle_id),
        INDEX idx_b_start_dt (start_datetime),
        INDEX idx_b_end_dt (end_datetime),
        INDEX idx_b_doc_no (doc_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS approvals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT UNIQUE NOT NULL,
        facility_status VARCHAR(50),
        facility_fuel INT DEFAULT 0,
        facility_allowance INT DEFAULT 0,
        facility_other TEXT,
        facility_signer VARCHAR(255),
        facility_comment TEXT,
        facility_signed_at DATETIME,
        office_status VARCHAR(50),
        office_driver_assigned VARCHAR(255),
        office_reason TEXT,
        office_other TEXT,
        office_signer VARCHAR(255),
        office_signed_at DATETIME,
        dean_status VARCHAR(50),
        dean_reason TEXT,
        dean_other TEXT,
        dean_signer VARCHAR(255),
        dean_signed_at DATETIME,
        driver_ack_status VARCHAR(50),
        driver_signer VARCHAR(255),
        driver_acknowledged_at DATETIME,
        INDEX idx_appr_booking (booking_id),
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} elseif ($dbDriver === 'pgsql') {
    // ตาราง PostgreSQL (เช่น Supabase / Neon)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(191) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        plain_password VARCHAR(255),
        prefix VARCHAR(50),
        fullname VARCHAR(255) NOT NULL,
        position VARCHAR(255) NOT NULL,
        department VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL,
        phone VARCHAR(50),
        signature_img TEXT,
        remember_token VARCHAR(255),
        remember_token_expiry TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS vehicles (
        id SERIAL PRIMARY KEY,
        plate_number VARCHAR(100) UNIQUE NOT NULL,
        brand_model VARCHAR(255) NOT NULL,
        vehicle_type VARCHAR(100) NOT NULL,
        seats INT DEFAULT 14,
        status VARCHAR(50) DEFAULT 'active',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS bookings (
        id SERIAL PRIMARY KEY,
        doc_no VARCHAR(100) UNIQUE,
        created_date DATE NOT NULL,
        user_id INT,
        requester_name VARCHAR(255) NOT NULL,
        requester_position VARCHAR(255) NOT NULL,
        requester_department VARCHAR(255) NOT NULL,
        requester_phone VARCHAR(50),
        vehicle_id INT NOT NULL,
        plate_number VARCHAR(100) NOT NULL,
        purpose TEXT NOT NULL,
        route_from VARCHAR(255) NOT NULL,
        route_to VARCHAR(255) NOT NULL,
        start_datetime TIMESTAMP NOT NULL,
        end_datetime TIMESTAMP NOT NULL,
        passenger_count INT DEFAULT 1,
        passenger_names TEXT,
        controller_name VARCHAR(255) NOT NULL,
        status VARCHAR(50) DEFAULT 'pending_facility',
        client_ip VARCHAR(100),
        user_agent TEXT,
        is_flagged_fake INT DEFAULT 0,
        fake_reason TEXT,
        actual_end_datetime TIMESTAMP,
        start_mileage INT,
        end_mileage INT,
        fuel_level VARCHAR(50),
        vehicle_condition TEXT,
        return_notes TEXT,
        returned_by VARCHAR(255),
        return_recorded_by VARCHAR(255),
        return_recorded_at TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS approvals (
        id SERIAL PRIMARY KEY,
        booking_id INT UNIQUE NOT NULL,
        facility_status VARCHAR(50),
        facility_fuel INT DEFAULT 0,
        facility_allowance INT DEFAULT 0,
        facility_other TEXT,
        facility_signer VARCHAR(255),
        facility_comment TEXT,
        facility_signed_at TIMESTAMP,
        office_status VARCHAR(50),
        office_driver_assigned VARCHAR(255),
        office_reason TEXT,
        office_other TEXT,
        office_signer VARCHAR(255),
        office_signed_at TIMESTAMP,
        dean_status VARCHAR(50),
        dean_reason TEXT,
        dean_other TEXT,
        dean_signer VARCHAR(255),
        dean_signed_at TIMESTAMP,
        driver_ack_status VARCHAR(50),
        driver_signer VARCHAR(255),
        driver_acknowledged_at TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    );

    CREATE INDEX IF NOT EXISTS idx_b_status ON bookings(status);
    CREATE INDEX IF NOT EXISTS idx_b_user_id ON bookings(user_id);
    CREATE INDEX IF NOT EXISTS idx_b_vehicle_id ON bookings(vehicle_id);
    CREATE INDEX IF NOT EXISTS idx_b_start_dt ON bookings(start_datetime);
    CREATE INDEX IF NOT EXISTS idx_b_end_dt ON bookings(end_datetime);
    CREATE INDEX IF NOT EXISTS idx_b_doc_no ON bookings(doc_no);
    CREATE INDEX IF NOT EXISTS idx_appr_booking ON approvals(booking_id);
    CREATE INDEX IF NOT EXISTS idx_u_username ON users(username);
    ");
} else {
    // ตาราง SQLite
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        plain_password TEXT,
        prefix TEXT,
        fullname TEXT NOT NULL,
        position TEXT NOT NULL,
        department TEXT NOT NULL,
        role TEXT NOT NULL,
        phone TEXT,
        signature_img TEXT,
        remember_token TEXT,
        remember_token_expiry DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS vehicles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        plate_number TEXT UNIQUE NOT NULL,
        brand_model TEXT NOT NULL,
        vehicle_type TEXT NOT NULL,
        seats INTEGER DEFAULT 14,
        status TEXT DEFAULT 'active',
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
        requester_phone TEXT,
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
        client_ip TEXT,
        user_agent TEXT,
        is_flagged_fake INTEGER DEFAULT 0,
        fake_reason TEXT,
        actual_end_datetime DATETIME,
        start_mileage INTEGER,
        end_mileage INTEGER,
        fuel_level TEXT,
        vehicle_condition TEXT,
        return_notes TEXT,
        returned_by TEXT,
        return_recorded_by TEXT,
        return_recorded_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(vehicle_id) REFERENCES vehicles(id)
    );

    CREATE TABLE IF NOT EXISTS approvals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        booking_id INTEGER UNIQUE NOT NULL,
        facility_status TEXT,
        facility_fuel INTEGER DEFAULT 0,
        facility_allowance INTEGER DEFAULT 0,
        facility_other TEXT,
        facility_signer TEXT,
        facility_comment TEXT,
        facility_signed_at DATETIME,
        office_status TEXT,
        office_driver_assigned TEXT,
        office_reason TEXT,
        office_other TEXT,
        office_signer TEXT,
        office_signed_at DATETIME,
        dean_status TEXT,
        dean_reason TEXT,
        dean_other TEXT,
        dean_signer TEXT,
        dean_signed_at DATETIME,
        driver_ack_status TEXT,
        driver_signer TEXT,
        driver_acknowledged_at DATETIME,
        FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    );
    ");

    // อัปเกรดคอลัมน์ใน SQLite หากยังไม่มี
    try {
        $existingCols = $pdo->query("PRAGMA table_info(bookings)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('client_ip', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN client_ip TEXT");
        if (!in_array('user_agent', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN user_agent TEXT");
        if (!in_array('is_flagged_fake', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN is_flagged_fake INTEGER DEFAULT 0");
        if (!in_array('fake_reason', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN fake_reason TEXT");
        if (!in_array('actual_end_datetime', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN actual_end_datetime DATETIME");
        if (!in_array('start_mileage', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN start_mileage INTEGER");
        if (!in_array('end_mileage', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN end_mileage INTEGER");
        if (!in_array('fuel_level', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN fuel_level TEXT");
        if (!in_array('vehicle_condition', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN vehicle_condition TEXT");
        if (!in_array('return_notes', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN return_notes TEXT");
        if (!in_array('returned_by', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN returned_by TEXT");
        if (!in_array('return_recorded_by', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN return_recorded_by TEXT");
        if (!in_array('return_recorded_at', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN return_recorded_at DATETIME");
        if (!in_array('requester_phone', $existingCols)) $pdo->exec("ALTER TABLE bookings ADD COLUMN requester_phone TEXT");

        $existingUserCols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('plain_password', $existingUserCols)) $pdo->exec("ALTER TABLE users ADD COLUMN plain_password TEXT");
        if (!in_array('remember_token', $existingUserCols)) $pdo->exec("ALTER TABLE users ADD COLUMN remember_token TEXT");
        if (!in_array('remember_token_expiry', $existingUserCols)) $pdo->exec("ALTER TABLE users ADD COLUMN remember_token_expiry DATETIME");

        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(status);
            CREATE INDEX IF NOT EXISTS idx_bookings_user_id ON bookings(user_id);
            CREATE INDEX IF NOT EXISTS idx_bookings_vehicle_id ON bookings(vehicle_id);
            CREATE INDEX IF NOT EXISTS idx_bookings_start_dt ON bookings(start_datetime);
            CREATE INDEX IF NOT EXISTS idx_bookings_end_dt ON bookings(end_datetime);
            CREATE INDEX IF NOT EXISTS idx_bookings_doc_no ON bookings(doc_no);
            CREATE INDEX IF NOT EXISTS idx_approvals_booking_id ON approvals(booking_id);
            CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
        ");
    } catch (Exception $e) {}
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
    ['วิภาดา ทองปิ่น', '18456#', 'นางสาว', 'วิภาดา ทองปิ่น', 'รักษาการในตำแหน่งหัวหน้าสำนักงานคณบดี', 'สำนักงานคณบดี', 'office_head', '081-999-8882'],
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

// ซิงค์หรือสร้างผู้ใช้งานในระบบ (หากยังไม่ครบ 50 คน)
$userCount = 0;
try {
    $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {}

if ($userCount < 50) {
    foreach ($facultyUserList as $fu) {
        $uUsername = $fu[0];
        $uPlainPass = $fu[1];
        $uPrefix = $fu[2];
        $uFullname = $fu[3];
        $uPos = $fu[4];
        $uDept = $fu[5];
        $uRole = $fu[6];
        $uPhone = $fu[7];

        try {
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
        } catch (Exception $e) {}
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

// ระบบคำขอใช้รถยนต์เริ่มต้นในสถานะว่าง พร้อมรับการจองจริงใหม่ (Clean Slate for Real Bookings)

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
function getStatusBadge($status, $bookingOrEndTime = null) {
    $endDatetime = null;
    if (is_array($bookingOrEndTime)) {
        $endDatetime = $bookingOrEndTime['end_datetime'] ?? null;
    } elseif (is_string($bookingOrEndTime)) {
        $endDatetime = $bookingOrEndTime;
    }

    switch ($status) {
        case 'pending_facility':
            return '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> รอหัวหน้าอาคารสถานที่พิจารณา</span>';
        case 'completed':
            return '<span class="badge bg-primary text-white"><i class="fas fa-flag-checkered me-1"></i> สิ้นสุดการใช้รถแล้ว</span>';
        case 'approved':
        case 'pending_office':
        case 'pending_dean':
        case 'pending_driver':
            if ($endDatetime && strtotime($endDatetime) <= time()) {
                return '<span class="badge bg-info text-dark" title="ถึงกำหนดเวลาสิ้นสุดการใช้รถแล้ว รอการบันทึกคืนรถ"><i class="fas fa-clock-rotate-left me-1"></i> อนุมัติแล้ว (ครบกำหนดคืนรถ)</span>';
            }
            return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> เห็นชอบแล้ว (พร้อมพิมพ์/ใช้งาน)</span>';
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
