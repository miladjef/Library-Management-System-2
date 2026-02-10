<?php
/**
 * Cron Job برای سینک خودکار کتاب‌ها با کتابخانه ملی
 *
 * نحوه استفاده:
 * 1. اجرا از خط فرمان: php sync_national_library.php
 * 2. تنظیم در Crontab:
 *    0 2 * * * /usr/bin/php /path/to/lib/admin/cron/sync_national_library.php >> /path/to/logs/sync.log 2>&1
 */

// تنظیم مسیرها
define('CLI_MODE', php_sapi_name() === 'cli');

if (CLI_MODE) {
    // اجرا از خط فرمان
    $base_path = dirname(dirname(dirname(__FILE__)));
} else {
    // اجرا از مرورگر (برای تست)
    $base_path = dirname(dirname(__DIR__));
}

require_once $base_path . '/config.php';
require_once $base_path . '/admin/inc/functions.php';
require_once $base_path . '/classes/NationalLibraryService.php';

/**
 * تابع لاگ
 */
function log_message($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;

    echo $log_message;

    // ذخیره در فایل لاگ
    $log_file = __DIR__ . '/logs/sync_' . date('Y-m-d') . '.log';
    $log_dir = dirname($log_file);

    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * بررسی نیاز به سینک
 */
function should_sync($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM sync_settings WHERE id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings || !$settings['sync_enabled']) {
            log_message('سینک خودکار غیرفعال است', 'INFO');
            return false;
        }

        // بررسی زمان آخرین سینک
        if ($settings['last_sync_at']) {
            $last_sync = strtotime($settings['last_sync_at']);
            $now = time();
            $interval_seconds = $settings['sync_interval_hours'] * 3600;

            if (($now - $last_sync) < $interval_seconds) {
                log_message('هنوز زمان سینک نرسیده است', 'INFO');
                return false;
            }
        }

        return $settings;

    } catch (PDOException $e) {
        log_message('خطا در بررسی تنظیمات: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * به‌روزرسانی زمان آخرین سینک
 */
function update_sync_time($pdo) {
    try {
        $stmt = $pdo->prepare("
            UPDATE sync_settings
            SET last_sync_at = NOW()
            WHERE id = 1
        ");
        $stmt->execute();

    } catch (PDOException $e) {
        log_message('خطا در به‌روزرسانی زمان سینک: ' . $e->getMessage(), 'ERROR');
    }
}

/**
 * ارسال ایمیل گزارش
 */
function send_report_email($result) {
    // TODO: پیاده‌سازی ارسال ایمیل
    // می‌توانید از PHPMailer یا mail() استفاده کنید
}

// شروع فرآیند سینک
log_message('=== شروع فرآیند سینک ===', 'INFO');

try {
    // بررسی نیاز به سینک
    $settings = should_sync($pdo);

    if (!$settings) {
        log_message('=== پایان فرآیند سینک (بدون نیاز) ===', 'INFO');
        exit(0);
    }

    // ایجاد نمونه سرویس
    $nlService = new NationalLibraryService($conn);

    log_message('شروع سینک کتاب‌ها...', 'INFO');

    // اجرای سینک
    $result = $nlService->syncBooks($settings['auto_download_covers']);

    // نمایش نتایج
    log_message('=== نتایج سینک ===', 'INFO');
    log_message("تعداد کل کتاب‌ها: {$result['total']}", 'INFO');
    log_message("به‌روزرسانی شده: {$result['updated']}", 'SUCCESS');
    log_message("جلدهای دانلود شده: {$result['covers_downloaded']}", 'SUCCESS');
    log_message("خطاها: {$result['errors']}", $result['errors'] > 0 ? 'WARNING' : 'INFO');

    // به‌روزرسانی زمان سینک
    update_sync_time($pdo);

    // ارسال گزارش ایمیل (در صورت تمایل)
    if ($result['errors'] > 0) {
        send_report_email($result);
    }

    log_message('=== پایان فرآیند سینک ===', 'SUCCESS');
    exit(0);

} catch (Exception $e) {
    log_message('خطای کلی: ' . $e->getMessage(), 'ERROR');
    log_message('=== پایان فرآیند سینک (با خطا) ===', 'ERROR');
    exit(1);
}
