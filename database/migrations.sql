-- Audit Log Table
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    role VARCHAR(20) DEFAULT 'system',
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clinic working hours
CREATE TABLE IF NOT EXISTS working_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    UNIQUE KEY uk_doctor_day (doctor_id, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add queue_number to appointments if not exists
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'lifemed' AND table_name = 'appointments' AND column_name = 'queue_number') = 0,
    'ALTER TABLE appointments ADD COLUMN queue_number INT DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add deleted_at to patients if not exists (soft delete)
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'lifemed' AND table_name = 'patients' AND column_name = 'deleted_at') = 0,
    'ALTER TABLE patients ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add deleted_at to appointments if not exists
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'lifemed' AND table_name = 'appointments' AND column_name = 'deleted_at') = 0,
    'ALTER TABLE appointments ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add sequential receipt_number column
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'lifemed' AND table_name = 'appointments' AND column_name = 'receipt_number') = 0,
    'ALTER TABLE appointments ADD COLUMN receipt_number INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add referring_doctor_name to appointments (replaces referring_doctor_id)
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'lifemed' AND table_name = 'appointments' AND column_name = 'referring_doctor_id') > 0,
    'ALTER TABLE appointments DROP COLUMN referring_doctor_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'lifemed' AND table_name = 'appointments' AND column_name = 'referring_doctor_name') = 0,
    'ALTER TABLE appointments ADD COLUMN referring_doctor_name VARCHAR(255) NULL DEFAULT NULL AFTER doctor_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add specimen_code to appointments
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'lifemed' AND table_name = 'appointments' AND column_name = 'specimen_code') = 0,
    'ALTER TABLE appointments ADD COLUMN specimen_code VARCHAR(30) NULL DEFAULT NULL AFTER receipt_number',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add commission_pct to services
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'lifemed' AND table_name = 'services' AND column_name = 'commission_pct') = 0,
    'ALTER TABLE services ADD COLUMN commission_pct DECIMAL(5,2) DEFAULT 0 AFTER price',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add telegram_id to users
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'lifemed' AND table_name = 'users' AND column_name = 'telegram_id') = 0,
    'ALTER TABLE users ADD COLUMN telegram_id VARCHAR(20) NULL DEFAULT NULL AFTER phone',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add telegram_id to doctors
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'lifemed' AND table_name = 'doctors' AND column_name = 'telegram_id') = 0,
    'ALTER TABLE doctors ADD COLUMN telegram_id VARCHAR(20) NULL DEFAULT NULL AFTER phone',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add telegram_id to patients
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'lifemed' AND table_name = 'patients' AND column_name = 'telegram_id') = 0,
    'ALTER TABLE patients ADD COLUMN telegram_id VARCHAR(20) NULL DEFAULT NULL AFTER phone',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Telegram logs table
CREATE TABLE IF NOT EXISTS telegram_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    direction ENUM('incoming', 'outgoing') NOT NULL,
    chat_id VARCHAR(20) NOT NULL,
    message_type VARCHAR(30) DEFAULT 'text',
    message_text TEXT NULL,
    response_status VARCHAR(20) NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_id (chat_id),
    INDEX idx_direction (direction),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Telegram blacklist table
CREATE TABLE IF NOT EXISTS telegram_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id VARCHAR(20) NOT NULL UNIQUE,
    reason VARCHAR(255) NULL,
    blocked_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_id (chat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== PERFORMANCE INDEXES ====================

-- Index on appointment_date (used in daily queries, reports, doctor panel)
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'lifemed' AND table_name = 'appointments' AND index_name = 'idx_appointment_date') = 0,
    'ALTER TABLE appointments ADD INDEX idx_appointment_date (appointment_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Composite index for doctor daily schedule
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'lifemed' AND table_name = 'appointments' AND index_name = 'idx_doctor_date') = 0,
    'ALTER TABLE appointments ADD INDEX idx_doctor_date (doctor_id, appointment_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index on receipt_id for receipt lookups
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'lifemed' AND table_name = 'appointments' AND index_name = 'idx_receipt_id') = 0,
    'ALTER TABLE appointments ADD INDEX idx_receipt_id (receipt_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index on payment_status for unpaid count
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'lifemed' AND table_name = 'appointments' AND index_name = 'idx_payment_status') = 0,
    'ALTER TABLE appointments ADD INDEX idx_payment_status (payment_status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Patient search indexes
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'lifemed' AND table_name = 'patients' AND index_name = 'idx_full_name') = 0,
    'ALTER TABLE patients ADD INDEX idx_full_name (full_name)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'lifemed' AND table_name = 'patients' AND index_name = 'idx_phone') = 0,
    'ALTER TABLE patients ADD INDEX idx_phone (phone)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'lifemed' AND table_name = 'patients' AND index_name = 'idx_deleted_at') = 0,
    'ALTER TABLE patients ADD INDEX idx_deleted_at (deleted_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Telegram ID indexes (for webhook user detection)
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'lifemed' AND table_name = 'users' AND index_name = 'idx_telegram_id') = 0,
    'ALTER TABLE users ADD INDEX idx_telegram_id (telegram_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'lifemed' AND table_name = 'doctors' AND index_name = 'idx_telegram_id') = 0,
    'ALTER TABLE doctors ADD INDEX idx_telegram_id (telegram_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'lifemed' AND table_name = 'patients' AND index_name = 'idx_telegram_id') = 0,
    'ALTER TABLE patients ADD INDEX idx_telegram_id (telegram_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Lab results index on template_id
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'lifemed' AND table_name = 'lab_results' AND index_name = 'idx_template_id') = 0,
    'ALTER TABLE lab_results ADD INDEX idx_template_id (template_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
