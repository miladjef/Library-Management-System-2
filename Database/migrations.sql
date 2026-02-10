-- Database/migrations.sql
-- اسکریپت به‌روزرسانی دیتابیس برای نسخه پیشرفته

-- =========================================
-- 1. جدول تنظیمات سیستم
-- =========================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) UNIQUE NOT NULL,
    `setting_value` TEXT NOT NULL,
    `description` VARCHAR(255),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- درج تنظیمات پیش‌فرض
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('daily_penalty_amount', '5000', 'مبلغ جریمه روزانه به تومان'),
('max_active_reservations', '3', 'حداکثر تعداد امانت فعال هر عضو'),
('default_borrow_duration', '15', 'مدت زمان پیش‌فرض امانت (روز)'),
('max_extension_count', '2', 'حداکثر تعداد تمدید مجاز'),
('book_cover_path', '../uploads/book_covers/', 'مسیر ذخیره تصاویر کتاب'),
('api_google_books', '1', 'فعال بودن API کتاب‌های گوگل (1=فعال، 0=غیرفعال)'),
('api_open_library', '1', 'فعال بودن API کتابخانه باز')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- =========================================
-- 2. به‌روزرسانی جدول کتاب‌ها
-- =========================================
ALTER TABLE `books`
ADD COLUMN IF NOT EXISTS `isbn` VARCHAR(20) UNIQUE AFTER `book_name`,
ADD COLUMN IF NOT EXISTS `publisher` VARCHAR(255) AFTER `author`,
ADD COLUMN IF NOT EXISTS `publish_year` YEAR AFTER `publisher`,
ADD COLUMN IF NOT EXISTS `pages` INT AFTER `publish_year`,
ADD COLUMN IF NOT EXISTS `language` VARCHAR(20) DEFAULT 'fa' AFTER `pages`,
ADD COLUMN IF NOT EXISTS `cover_image` VARCHAR(255) AFTER `book_quantity`,
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD INDEX `idx_isbn` (`isbn`),
ADD INDEX `idx_author` (`author`),
ADD INDEX `idx_category` (`category`);

-- =========================================
-- 3. به‌روزرسانی جدول اعضا
-- =========================================
ALTER TABLE `members`
ADD COLUMN IF NOT EXISTS `national_code` VARCHAR(10) UNIQUE AFTER `surname`,
ADD COLUMN IF NOT EXISTS `mobile` VARCHAR(11) AFTER `national_code`,
ADD COLUMN IF NOT EXISTS `address` TEXT AFTER `mobile`,
ADD COLUMN IF NOT EXISTS `is_active` BOOLEAN DEFAULT 1 AFTER `address`,
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD INDEX `idx_national_code` (`national_code`),
ADD INDEX `idx_mobile` (`mobile`),
ADD INDEX `idx_username` (`username`);

-- =========================================
-- 4. به‌روزرسانی جدول رزروها/امانت‌ها
-- =========================================
ALTER TABLE `reservations`
ADD COLUMN IF NOT EXISTS `status` ENUM('active', 'returned', 'cancelled') DEFAULT 'active' AFTER `mid`,
ADD COLUMN IF NOT EXISTS `actual_return_date` DATE AFTER `return_date`,
ADD COLUMN IF NOT EXISTS `penalty` DECIMAL(10,2) DEFAULT 0.00 AFTER `actual_return_date`,
ADD COLUMN IF NOT EXISTS `penalty_paid` BOOLEAN DEFAULT 0 AFTER `penalty`,
ADD COLUMN IF NOT EXISTS `extension_count` TINYINT DEFAULT 0 AFTER `penalty_paid`,
ADD COLUMN IF NOT EXISTS `notes` TEXT AFTER `extension_count`,
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD INDEX `idx_status` (`status`),
ADD INDEX `idx_member` (`mid`),
ADD INDEX `idx_book` (`bid`),
ADD INDEX `idx_borrow_date` (`borrow_date`),
ADD INDEX `idx_return_date` (`return_date`);

-- =========================================
-- 5. جدول لاگ فعالیت‌های کاربران
-- =========================================
CREATE TABLE IF NOT EXISTS `member_activity_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mid` INT NOT NULL,
    `activity_type` VARCHAR(50) NOT NULL,
    `description` TEXT,
    `ip_address` VARCHAR(45),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`mid`) REFERENCES `members`(`mid`) ON DELETE CASCADE,
    INDEX `idx_member` (`mid`),
    INDEX `idx_type` (`activity_type`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- 6. جدول تاریخچه جریمه‌ها
-- =========================================
CREATE TABLE IF NOT EXISTS `penalty_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reservation_id` INT NOT NULL,
    `mid` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_method` VARCHAR(50),
    `reference_number` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`rid`) ON DELETE CASCADE,
    FOREIGN KEY (`mid`) REFERENCES `members`(`mid`) ON DELETE CASCADE,
    INDEX `idx_reservation` (`reservation_id`),
    INDEX `idx_member` (`mid`),
    INDEX `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- 7. جدول نظرات و رتبه‌بندی کتاب‌ها
-- =========================================
CREATE TABLE IF NOT EXISTS `book_reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bid` INT NOT NULL,
    `mid` INT NOT NULL,
    `rating` TINYINT CHECK (`rating` BETWEEN 1 AND 5),
    `review_text` TEXT,
    `is_approved` BOOLEAN DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`bid`) REFERENCES `books`(`bid`) ON DELETE CASCADE,
    FOREIGN KEY (`mid`) REFERENCES `members`(`mid`) ON DELETE CASCADE,
    UNIQUE KEY `unique_review` (`bid`, `mid`),
    INDEX `idx_book` (`bid`),
    INDEX `idx_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- 8. ویوی آماری برای داشبورد
-- =========================================
CREATE OR REPLACE VIEW `dashboard_stats` AS
SELECT
    (SELECT COUNT(*) FROM books) AS total_books,
    (SELECT SUM(book_quantity) FROM books) AS total_book_copies,
    (SELECT COUNT(*) FROM members WHERE is_active = 1) AS active_members,
    (SELECT COUNT(*) FROM reservations WHERE status = 'active') AS active_reservations,
    (SELECT COUNT(*) FROM reservations WHERE status = 'active' AND return_date < CURDATE()) AS overdue_reservations,
    (SELECT SUM(penalty) FROM reservations WHERE penalty_paid = 0) AS total_unpaid_penalties;

-- =========================================
-- 9. تریگر برای ثبت خودکار لاگ
-- =========================================
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS `log_member_registration`
AFTER INSERT ON `members`
FOR EACH ROW
BEGIN
    INSERT INTO member_activity_log (mid, activity_type, description)
    VALUES (NEW.mid, 'registration', CONCAT('ثبت‌نام کاربر جدید: ', NEW.username));
END$$

CREATE TRIGGER IF NOT EXISTS `log_reservation_create`
AFTER INSERT ON `reservations`
FOR EACH ROW
BEGIN
    INSERT INTO member_activity_log (mid, activity_type, description)
    VALUES (NEW.mid, 'borrow', CONCAT('امانت کتاب - شناسه: ', NEW.bid));
END$$

CREATE TRIGGER IF NOT EXISTS `log_reservation_return`
AFTER UPDATE ON `reservations`
FOR EACH ROW
BEGIN
    IF NEW.status = 'returned' AND OLD.status = 'active' THEN
        INSERT INTO member_activity_log (mid, activity_type, description)
        VALUES (NEW.mid, 'return', CONCAT('بازگشت کتاب - شناسه: ', NEW.bid));
    END IF;
END$$

DELIMITER ;

-- =========================================
-- 10. پروسیجر محاسبه خودکار جریمه
-- =========================================
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS `calculate_overdue_penalties`()
BEGIN
    DECLARE daily_penalty DECIMAL(10,2);

    -- خواندن مبلغ جریمه روزانه از تنظیمات
    SELECT CAST(setting_value AS DECIMAL(10,2)) INTO daily_penalty
    FROM system_settings
    WHERE setting_key = 'daily_penalty_amount';

    -- به‌روزرسانی جریمه‌های تاخیری
    UPDATE reservations
    SET penalty = DATEDIFF(CURDATE(), return_date) * daily_penalty
    WHERE status = 'active'
    AND return_date < CURDATE()
    AND penalty < (DATEDIFF(CURDATE(), return_date) * daily_penalty);
END$$

DELIMITER ;

-- =========================================
-- 11. رویداد خودکار برای محاسبه روزانه جریمه
-- =========================================
SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS `daily_penalty_calculation`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
CALL calculate_overdue_penalties();

-- =========================================
-- 12. به‌روزرسانی داده‌های موجود (Migration)
-- =========================================

-- افزودن وضعیت پیش‌فرض برای رزروهای قدیمی
UPDATE reservations
SET status = 'active'
WHERE status IS NULL OR status = '';

-- فعال کردن تمام اعضای موجود
UPDATE members
SET is_active = 1
WHERE is_active IS NULL;

-- =========================================
-- پایان اسکریپت مهاجرت
-- =========================================
