-- database.sql
-- ฐานข้อมูลระบบขออนุญาตใช้รถยนต์ คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์
-- สำหรับนำเข้า MySQL / MariaDB ผ่าน phpMyAdmin

CREATE DATABASE IF NOT EXISTS `van_booking` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `van_booking`;

-- ตารางผู้ใช้งาน
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `prefix` VARCHAR(20),
  `fullname` VARCHAR(100) NOT NULL,
  `position` VARCHAR(100) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `role` VARCHAR(30) NOT NULL, -- requester, facility_head, office_head, dean, driver, admin
  `phone` VARCHAR(30),
  `signature_img` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางยานพาหนะ
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plate_number` VARCHAR(50) NOT NULL UNIQUE,
  `brand_model` VARCHAR(100) NOT NULL,
  `vehicle_type` VARCHAR(50) NOT NULL,
  `seats` INT DEFAULT 14,
  `status` VARCHAR(20) DEFAULT 'active',
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางการจอง / คำขอใช้รถยนต์
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doc_no` VARCHAR(50) UNIQUE,
  `created_date` DATE NOT NULL,
  `user_id` INT,
  `requester_name` VARCHAR(100) NOT NULL,
  `requester_position` VARCHAR(100) NOT NULL,
  `requester_department` VARCHAR(100) NOT NULL,
  `vehicle_id` INT NOT NULL,
  `plate_number` VARCHAR(50) NOT NULL,
  `purpose` TEXT NOT NULL,
  `route_from` VARCHAR(255) NOT NULL,
  `route_to` VARCHAR(255) NOT NULL,
  `start_datetime` DATETIME NOT NULL,
  `end_datetime` DATETIME NOT NULL,
  `passenger_count` INT DEFAULT 1,
  `passenger_names` TEXT,
  `controller_name` VARCHAR(100) NOT NULL,
  `status` VARCHAR(30) DEFAULT 'pending_facility',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางการพิจารณาอนุมัติ 4 ลำดับ
CREATE TABLE IF NOT EXISTS `approvals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT UNIQUE NOT NULL,
  
  -- 1. หัวหน้างานอาคารสถานที่
  `facility_status` VARCHAR(20),
  `facility_fuel` TINYINT(1) DEFAULT 0,
  `facility_allowance` TINYINT(1) DEFAULT 0,
  `facility_other` VARCHAR(255),
  `facility_signer` VARCHAR(100),
  `facility_comment` TEXT,
  `facility_signed_at` DATETIME,
  
  -- 2. หัวหน้าสำนักงานคณบดี
  `office_status` VARCHAR(20),
  `office_driver_assigned` VARCHAR(100),
  `office_reason` TEXT,
  `office_other` VARCHAR(255),
  `office_signer` VARCHAR(100),
  `office_signed_at` DATETIME,
  
  -- 3. คณบดีคณะวิทยาการจัดการ
  `dean_status` VARCHAR(20),
  `dean_reason` TEXT,
  `dean_other` VARCHAR(255),
  `dean_signer` VARCHAR(100),
  `dean_signed_at` DATETIME,
  
  -- 4. พนักงานขับรถยนต์
  `driver_ack_status` VARCHAR(20),
  `driver_signer` VARCHAR(100),
  `driver_acknowledged_at` DATETIME,
  
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลผู้ใช้งานเริ่มต้น
INSERT INTO `users` (`username`, `password`, `prefix`, `fullname`, `position`, `department`, `role`, `phone`) VALUES
('admin', '$2y$10$eA8P...dummy', 'นาย', 'ผู้ดูแลระบบ', 'นักวิชาการคอมพิวเตอร์', 'สำนักงานคณบดี', 'admin', '081-234-5678'),
('user1', '$2y$10$eA8P...dummy', 'อาจารย์ ดร.', 'สมชาย ใจดี', 'อาจารย์ประจำสาขาวิชา', 'สาขาวิชาคอมพิวเตอร์ธุรกิจ', 'requester', '089-111-2222'),
('user2', '$2y$10$eA8P...dummy', 'อาจารย์', 'อารีนา สุมาลี', 'อาจารย์ประจำสาขาวิชา', 'สาขาวิชาการจัดการ', 'requester', '089-333-4444'),
('facility_head', '$2y$10$eA8P...dummy', 'นาย', 'เอกสิทธิ์ คงพิทักษ์', 'หัวหน้างานอาคารสถานที่', 'งานอาคารสถานที่และยานพาหนะ', 'facility_head', '081-999-8881'),
('office_head', '$2y$10$eA8P...dummy', 'นาง', 'ซูไบดะห์ หะยีมะ', 'หัวหน้าสำนักงานคณบดี', 'สำนักงานคณบดี', 'office_head', '081-999-8882'),
('dean', '$2y$10$eA8P...dummy', 'ผู้ช่วยศาสตราจารย์ ดร.', 'บงกช กมลเปรม', 'คณบดีคณะวิทยาการจัดการ', 'คณะวิทยาการจัดการ', 'dean', '081-999-8883'),
('driver1', '$2y$10$eA8P...dummy', 'นาย', 'ธเนศ อินเอิบ', 'พนักงานขับรถยนต์', 'งานอาคารสถานที่และยานพาหนะ', 'driver', '082-777-6661');

-- ข้อมูลรถยนต์เริ่มต้น
INSERT INTO `vehicles` (`plate_number`, `brand_model`, `vehicle_type`, `seats`, `status`, `notes`) VALUES
('นข.1332 นธ.', 'Toyota Commuter', 'รถตู้โดยสารปรับอากาศ', 14, 'active', 'รถตู้ประจำคณะวิทยาการจัดการ');
