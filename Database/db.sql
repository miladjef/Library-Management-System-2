-- اصلاح جدول books برای افزودن فیلدهای جدید
ALTER TABLE `books`
ADD COLUMN IF NOT EXISTS `isbn` VARCHAR(20) NULL AFTER `description`,
ADD COLUMN IF NOT EXISTS `pages` INT NULL AFTER `isbn`,
ADD COLUMN IF NOT EXISTS `language` VARCHAR(50) DEFAULT 'فارسی' AFTER `pages`,
ADD COLUMN IF NOT EXISTS `book_img` VARCHAR(255) DEFAULT 'default.jpg' AFTER `language`,
ADD INDEX `idx_isbn` (`isbn`);

-- تغییر نام ستون image به book_img (در صورت وجود)
-- ALTER TABLE `books` CHANGE `image` `book_img` VARCHAR(255);

-- ایجاد جدول لاگ‌های API کتابخانه ملی
CREATE TABLE IF NOT EXISTS `national_library_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `operation_type` VARCHAR(50) NOT NULL COMMENT 'نوع عملیات: search_isbn, search_title, download_cover, sync',
  `search_query` VARCHAR(255) NULL COMMENT 'کوئری جستجو',
  `status` ENUM('success', 'error') NOT NULL,
  `response_data` TEXT NULL COMMENT 'داده‌های پاسخ API',
  `error_message` TEXT NULL COMMENT 'پیام خطا',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_operation` (`operation_type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ایجاد جدول تنظیمات سینک
CREATE TABLE IF NOT EXISTS `sync_settings` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `sync_enabled` TINYINT(1) DEFAULT 0 COMMENT 'فعال/غیرفعال سینک خودکار',
  `sync_interval_hours` INT DEFAULT 24 COMMENT 'فاصله زمانی سینک (ساعت)',
  `auto_download_covers` TINYINT(1) DEFAULT 1 COMMENT 'دانلود خودکار جلدها',
  `last_sync_at` TIMESTAMP NULL COMMENT 'زمان آخرین سینک',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- درج رکورد پیش‌فرض تنظیمات
INSERT INTO `sync_settings` (`id`, `sync_enabled`, `sync_interval_hours`, `auto_download_covers`)
VALUES (1, 0, 24, 1)
ON DUPLICATE KEY UPDATE id=id;

-- ایجاد view برای آمار داشبورد
CREATE OR REPLACE VIEW `dashboard_stats` AS
SELECT
    (SELECT COUNT(*) FROM books) as total_books,
    (SELECT COUNT(*) FROM members WHERE role = 1) as total_members,
    (SELECT COUNT(*) FROM reservations WHERE status = 'active') as active_reservations,
    (SELECT COUNT(*) FROM reservations
     WHERE status = 'active' AND return_date < CURDATE()) as overdue_reservations,
    (SELECT COALESCE(SUM(penalty), 0) FROM reservations
     WHERE penalty > 0 AND penalty_paid = 0) as unpaid_penalties,
    (SELECT COALESCE(SUM(penalty), 0) FROM reservations
     WHERE penalty > 0 AND penalty_paid = 1) as total_penalties;

-- ایجاد جدول لاگ فعالیت اعضا
CREATE TABLE IF NOT EXISTS `member_activity_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `mid` INT NOT NULL,
  `activity_type` ENUM('register', 'borrow', 'return', 'penalty_payment', 'other') NOT NULL,
  `description` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`mid`) REFERENCES `members`(`mid`) ON DELETE CASCADE,
  INDEX `idx_member` (`mid`),
  INDEX `idx_type` (`activity_type`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- اصلاح جدول reservations برای سینک بهتر
ALTER TABLE `reservations`
ADD COLUMN IF NOT EXISTS `reservation_date` DATE NULL AFTER `date`,
ADD COLUMN IF NOT EXISTS `return_date` DATE NULL AFTER `reservation_date`,
ADD COLUMN IF NOT EXISTS `penalty` DECIMAL(10,2) DEFAULT 0.00 AFTER `return_date`,
ADD COLUMN IF NOT EXISTS `penalty_paid` TINYINT(1) DEFAULT 0 AFTER `penalty`;

-- به‌روزرسانی داده‌های موجود
UPDATE `reservations`
SET `reservation_date` = DATE(`date`),
    `return_date` = DATE_ADD(DATE(`date`), INTERVAL `duration` DAY)
WHERE `reservation_date` IS NULL;
