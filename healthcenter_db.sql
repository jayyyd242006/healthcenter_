-- ============================================================
--  Health Center Online Appointment System
--  Database: health_center_db
--
--  HOW TO USE:
--  1. Copy everything in this file
--  2. Paste into phpMyAdmin > SQL tab, then click Go
--     OR paste into your MySQL shell and press Enter
--  3. It will drop and recreate the database cleanly
--
--  WORKING LOGIN CREDENTIALS:
--  -----------------------------------------------
--  Role    | Email                         | Password
--  -----------------------------------------------
--  Admin   | admin@healthcenter.com        | Admin@1234
--  Staff   | maria.staff@healthcenter.com  | Staff@1234
--  Staff   | jose.staff@healthcenter.com   | Staff@1234
--  Doctor  | dr.lim@healthcenter.com       | Doctor@1234
--  Doctor  | dr.cruz@healthcenter.com      | Doctor@1234
--  Patient | juan@email.com                | Patient@1234
--  Patient | lucia@email.com               | Patient@1234
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS `health_center_db`;
CREATE DATABASE `health_center_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `health_center_db`;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  TABLE: users
-- ============================================================
CREATE TABLE `users` (
  `user_id`       INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`     VARCHAR(100)     NOT NULL,
  `email`         VARCHAR(150)     NOT NULL,
  `password_hash` VARCHAR(255)     NOT NULL,
  `phone`         VARCHAR(20)      DEFAULT NULL,
  `role`          ENUM('admin','staff','patient') NOT NULL DEFAULT 'patient',
  `is_active`     TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_role`  (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: patients
-- ============================================================
CREATE TABLE `patients` (
  `patient_id`              INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`                 INT(10) UNSIGNED NOT NULL,
  `date_of_birth`           DATE             DEFAULT NULL,
  `sex`                     ENUM('male','female','other') DEFAULT NULL,
  `address`                 TEXT             DEFAULT NULL,
  `blood_type`              VARCHAR(5)       DEFAULT NULL,
  `allergies`               TEXT             DEFAULT NULL,
  `emergency_contact_name`  VARCHAR(100)     DEFAULT NULL,
  `emergency_contact_phone` VARCHAR(20)      DEFAULT NULL,
  `created_at`              DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `uq_patients_user` (`user_id`),
  CONSTRAINT `fk_patients_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: doctors
-- ============================================================
CREATE TABLE `doctors` (
  `doctor_id`    INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT(10) UNSIGNED NOT NULL,
  `specialty`    VARCHAR(100)     NOT NULL,
  `license_no`   VARCHAR(50)      DEFAULT NULL,
  `bio`          TEXT             DEFAULT NULL,
  `is_available` TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doctor_id`),
  UNIQUE KEY `uq_doctors_user` (`user_id`),
  KEY `idx_doctors_avail` (`is_available`),
  CONSTRAINT `fk_doctors_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: schedules
-- ============================================================
CREATE TABLE `schedules` (
  `schedule_id`   INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id`     INT(10) UNSIGNED NOT NULL,
  `schedule_date` DATE             NOT NULL,
  `start_time`    TIME             NOT NULL,
  `end_time`      TIME             NOT NULL,
  `slot_duration` INT(10) UNSIGNED NOT NULL DEFAULT 30,
  `max_patients`  INT(10) UNSIGNED NOT NULL DEFAULT 10,
  `is_open`       TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  UNIQUE KEY `uq_schedule_slot` (`doctor_id`, `schedule_date`, `start_time`),
  KEY `idx_schedules_date` (`schedule_date`),
  CONSTRAINT `fk_schedules_doctor`
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: appointments
-- ============================================================
CREATE TABLE `appointments` (
  `appointment_id`   INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`       INT(10) UNSIGNED NOT NULL,
  `doctor_id`        INT(10) UNSIGNED NOT NULL,
  `schedule_id`      INT(10) UNSIGNED NOT NULL,
  `appointment_date` DATE             NOT NULL,
  `appointment_time` TIME             NOT NULL,
  `reason`           TEXT             DEFAULT NULL,
  `status`           ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `confirmed_by`     INT(10) UNSIGNED DEFAULT NULL,
  `confirmed_at`     DATETIME         DEFAULT NULL,
  `cancelled_reason` TEXT             DEFAULT NULL,
  `notes`            TEXT             DEFAULT NULL,
  `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`appointment_id`),
  KEY `idx_appt_patient`    (`patient_id`),
  KEY `idx_appt_doctor`     (`doctor_id`),
  KEY `idx_appt_date`       (`appointment_date`),
  KEY `idx_appt_status`     (`status`),
  KEY `fk_appt_schedule`    (`schedule_id`),
  KEY `fk_appt_confirmedby` (`confirmed_by`),
  CONSTRAINT `fk_appt_patient`
    FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_doctor`
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_schedule`
    FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`schedule_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_confirmedby`
    FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: medical_records
-- ============================================================
CREATE TABLE `medical_records` (
  `record_id`      INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` INT(10) UNSIGNED NOT NULL,
  `patient_id`     INT(10) UNSIGNED NOT NULL,
  `doctor_id`      INT(10) UNSIGNED NOT NULL,
  `diagnosis`      TEXT             DEFAULT NULL,
  `prescription`   TEXT             DEFAULT NULL,
  `lab_requests`   TEXT             DEFAULT NULL,
  `follow_up_date` DATE             DEFAULT NULL,
  `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `uq_record_appt` (`appointment_id`),
  KEY `idx_record_patient` (`patient_id`),
  KEY `fk_records_doctor`  (`doctor_id`),
  CONSTRAINT `fk_records_appointment`
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_records_patient`
    FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_records_doctor`
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: notifications
-- ============================================================
CREATE TABLE `notifications` (
  `notification_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT(10) UNSIGNED NOT NULL,
  `appointment_id`  INT(10) UNSIGNED DEFAULT NULL,
  `type`            ENUM('confirmation','reminder','cancellation','reschedule','general') NOT NULL DEFAULT 'general',
  `channel`         ENUM('email','sms','in_app') NOT NULL DEFAULT 'in_app',
  `subject`         VARCHAR(255)     DEFAULT NULL,
  `message`         TEXT             NOT NULL,
  `is_sent`         TINYINT(1)       NOT NULL DEFAULT 0,
  `sent_at`         DATETIME         DEFAULT NULL,
  `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_sent` (`is_sent`),
  KEY `idx_notif_appt` (`appointment_id`),
  CONSTRAINT `fk_notif_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_appointment`
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  SAMPLE DATA (with real working password hashes)
-- ============================================================

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `phone`, `role`, `is_active`) VALUES
(1, 'System Administrator',    'admin@healthcenter.com',       '$2y$10$bCjY7m/G4gvo7FNDlZTpT.psw0m.wFeaD9GzrlVUpyRRgUSVlh13S', '09000000001', 'admin',   1),
(2, 'Maria Santos',            'maria.staff@healthcenter.com', '$2y$10$wZ/.Wlx.McD6PSm1ShxaTu4Qit2TDqtDP/l3W3HxOK2le699i5I/W', '09000000002', 'staff',   1),
(3, 'Jose Reyes',              'jose.staff@healthcenter.com',  '$2y$10$wZ/.Wlx.McD6PSm1ShxaTu4Qit2TDqtDP/l3W3HxOK2le699i5I/W', '09000000003', 'staff',   1),
(4, 'Dr. Ana Lim',             'dr.lim@healthcenter.com',      '$2y$10$XovbU5/7Pvi9CdB3kWAX2e5G.CgMDGOaVHVHhbk.SM1Knkr6T/4kK', '09111000001', 'staff',   1),
(5, 'Dr. Carlos Cruz',         'dr.cruz@healthcenter.com',     '$2y$10$XovbU5/7Pvi9CdB3kWAX2e5G.CgMDGOaVHVHhbk.SM1Knkr6T/4kK', '09111000002', 'staff',   1),
(6, 'Juan dela Cruz',          'juan@email.com',               '$2y$10$uWB.1iULd94v8Tv94AC11ucTfZmMdYsTDDOFwdnJ308Qpmg7ZeIs6', '09222000001', 'patient', 1),
(7, 'Lucia Mendoza',           'lucia@email.com',              '$2y$10$uWB.1iULd94v8Tv94AC11ucTfZmMdYsTDDOFwdnJ308Qpmg7ZeIs6', '09222000002', 'patient', 1),
(8, 'Zhanjianah Tabilin Jaji', 'zhanjianah21@gmail.com',       '$2y$10$FQfAH5vFg3g8ZDPgJH4qouyHPO/RkMHUQ8u4F2MVfLqb9NFuOllSe', '09606696641', 'patient', 1);

INSERT INTO `patients` (`patient_id`, `user_id`, `date_of_birth`, `sex`, `address`, `blood_type`) VALUES
(1, 6, '1990-05-14', 'male',   'Zamboanga City', 'O+'),
(2, 7, '1995-09-22', 'female', 'Zamboanga City', 'A+'),
(3, 8, NULL,         NULL,      NULL,             NULL);

INSERT INTO `doctors` (`doctor_id`, `user_id`, `specialty`, `license_no`, `is_available`) VALUES
(1, 4, 'General Medicine', 'PRC-GM-00001',  1),
(2, 5, 'Pediatrics',       'PRC-PED-00002', 1);

INSERT INTO `schedules` (`schedule_id`, `doctor_id`, `schedule_date`, `start_time`, `end_time`, `slot_duration`, `max_patients`, `is_open`) VALUES
(1, 1, CURDATE(), '08:00:00', '12:00:00', 30, 8, 1),
(2, 2, CURDATE(), '13:00:00', '17:00:00', 30, 8, 1);

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `doctor_id`, `schedule_id`, `appointment_date`, `appointment_time`, `reason`, `status`) VALUES
(1, 1, 1, 1, CURDATE(), '08:00:00', 'Routine check-up',   'confirmed'),
(2, 2, 2, 2, CURDATE(), '13:00:00', 'Fever and headache', 'pending');

-- ============================================================
--  CONNECT IN PHP (save this as db.php in your project folder)
--
--  <?php
--  $pdo = new PDO(
--      'mysql:host=localhost;dbname=health_center_db;charset=utf8mb4',
--      'root',  // XAMPP default username
--      '',      // XAMPP default password (blank)
--      [
--        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
--        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
--      ]
--  );
-- ============================================================