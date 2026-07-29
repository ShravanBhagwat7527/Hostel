-- ============================================================
-- Hostel Complaint Portal — Database Schema
-- Engine: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS hostel_complaint_portal
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE hostel_complaint_portal;

-- ------------------------------------------------------------
-- wardens
-- Login accounts for hostel staff. Students do NOT get an
-- account — they identify themselves by name + room number
-- when filing/tracking a ticket, same as the original design.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS wardens (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- complaints
-- One row per ticket. Mirrors the fields used by the frontend.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS complaints (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_no     VARCHAR(20)  NOT NULL UNIQUE,
  student_name  VARCHAR(100) NOT NULL,
  room_number   VARCHAR(20)  NOT NULL,
  block         VARCHAR(50)  NOT NULL DEFAULT 'Not specified',
  category      ENUM(
                  'Electrical',
                  'Plumbing',
                  'Carpentry',
                  'Wi-Fi / Internet',
                  'Water Supply',
                  'Cleanliness / Pest',
                  'Cleanliness',
                  'Security / Safety',
                  'Mess / Food',
                  'Furniture',
                  'Other'
                 ) NOT NULL DEFAULT 'Other',
  priority      ENUM('Low','Medium','High','Emergency','Urgent') NOT NULL DEFAULT 'Medium',
  description   TEXT NOT NULL,
  status        ENUM('Pending','In Progress','Resolved','Rejected') NOT NULL DEFAULT 'Pending',
  remark        TEXT NULL,
  handled_by    INT UNSIGNED NULL,
  image_path    VARCHAR(255) NULL DEFAULT NULL,
  ip_address    VARCHAR(45) NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_complaints_warden
    FOREIGN KEY (handled_by) REFERENCES wardens(id)
    ON DELETE SET NULL,

  INDEX idx_status (status),
  INDEX idx_category (category),
  INDEX idx_priority (priority),
  INDEX idx_student_lookup (student_name, room_number)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- complaint_logs
-- Audit trail & comments for tickets.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS complaint_logs (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id     INT UNSIGNED NOT NULL,
  warden_username  VARCHAR(50) NULL,
  old_status       VARCHAR(20) NOT NULL,
  new_status       VARCHAR(20) NOT NULL,
  comment          TEXT NOT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_complaint_logs_complaint
    FOREIGN KEY (complaint_id) REFERENCES complaints(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ticket_counters
-- Guarantees gap-free, race-safe ticket numbers per year
-- (HC-<year>-<0001>) without scanning the complaints table.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_counters (
  year        SMALLINT UNSIGNED PRIMARY KEY,
  last_number INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- No warden rows are seeded here on purpose — run
-- backend/setup_warden.php once after import to create the
-- first warden account with a properly bcrypt-hashed password.

-- ------------------------------------------------------------
-- announcements
-- Broadcast notices that automatically expire after a set time.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warden_username VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    priority_tag ENUM('General', 'Urgent', 'Maintenance') DEFAULT 'General',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
