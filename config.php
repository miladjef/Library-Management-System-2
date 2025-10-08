<?php
/**
 * تنظیمات اصلی پروژه
 */

// تنظیمات دیتابیس
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lib');

// URL سایت
define('SITE_URL', 'http://localhost/lib');

// مسیرهای فایل
define('BASE_PATH', __DIR__);
define('ADMIN_PATH', BASE_PATH . '/admin');
define('UPLOAD_PATH', BASE_PATH . '/assets/img/books');
define('UPLOAD_URL', SITE_URL . '/assets/img/books');

// تنظیمات امنیتی
define('SESSION_LIFETIME', 3600); // 1 hour
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);

// تنظیمات API کتابخانه ملی
define('NL_API_BASE_URL', 'https://opac.nlai.ir');
define('NL_API_TIMEOUT', 30);
define('NL_COVER_PATH', UPLOAD_PATH . '/covers/national_library');
define('NL_COVER_URL', UPLOAD_URL . '/covers/national_library');

// تنظیمات لاگ
define('LOG_PATH', BASE_PATH . '/logs');
define('ENABLE_LOGGING', true);

// منطقه زمانی
date_default_timezone_set('Asia/Tehran');

// تنظیمات خطایابی (فقط در محیط توسعه)
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
