<?php
/**
 * فایل پیکربندی اصلی سیستم
 * Library Management System v2.0
 * @author Enhanced Version
 * @date 2025
 */

// شروع Session با تنظیمات امن
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 7200);
    session_start();
}

// تعریف مسیرهای اصلی
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('CLASSES_PATH', ROOT_PATH . '/classes');
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('CACHE_PATH', ROOT_PATH . '/cache');
define('LOGS_PATH', ROOT_PATH . '/logs');

// بارگذاری فایل .env
function loadEnv($path = null) {
    $envPath = $path ?? CONFIG_PATH . '/.env';

    if (!file_exists($envPath)) {
        // استفاده از مقادیر پیش‌فرض
        return false;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // رد کردن کامنت‌ها
        if (strpos($line, '#') === 0) {
            continue;
        }

        // پارس کردن خط
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // حذف کوتیشن‌ها
            $value = trim($value, '"\'');

            // تنظیم در $_ENV
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
        }
    }

    return true;
}

// بارگذاری Environment Variables
loadEnv();

// تابع helper برای دریافت env
function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

// === تنظیمات دیتابیس ===
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'library_db'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// === تنظیمات سایت ===
define('SITE_URL', rtrim(env('SITE_URL', 'http://localhost/library'), '/'));
define('SITE_NAME', env('SITE_NAME', 'سیستم مدیریت کتابخانه'));
define('SITE_LANG', env('SITE_LANG', 'fa'));
define('ADMIN_EMAIL', env('ADMIN_EMAIL', 'admin@library.local'));

// === تنظیمات امنیتی ===
define('SESSION_LIFETIME', intval(env('SESSION_LIFETIME', 7200)));
define('CSRF_TOKEN_EXPIRE', intval(env('CSRF_TOKEN_EXPIRE', 3600)));
define('PASSWORD_MIN_LENGTH', intval(env('PASSWORD_MIN_LENGTH', 8)));
define('MAX_LOGIN_ATTEMPTS', intval(env('MAX_LOGIN_ATTEMPTS', 5)));
define('LOGIN_TIMEOUT', intval(env('LOGIN_TIMEOUT', 900)));

// === تنظیمات API ===
define('GOOGLE_BOOKS_API_KEY', env('GOOGLE_BOOKS_API_KEY', ''));
define('OPEN_LIBRARY_API_ENABLE', filter_var(env('OPEN_LIBRARY_API_ENABLE', true), FILTER_VALIDATE_BOOLEAN));
define('API_TIMEOUT', intval(env('API_TIMEOUT', 10)));
define('API_CACHE_DURATION', intval(env('API_CACHE_DURATION', 86400)));

// === تنظیمات آپلود فایل ===
define('MAX_UPLOAD_SIZE', intval(env('MAX_UPLOAD_SIZE', 5242880))); // 5MB
define('ALLOWED_IMAGE_TYPES', explode(',', env('ALLOWED_IMAGE_TYPES', 'jpg,jpeg,png,gif,webp')));
define('UPLOAD_PATH', env('UPLOAD_PATH', 'uploads/books/'));
define('COVER_WIDTH', intval(env('COVER_WIDTH', 400)));
define('COVER_HEIGHT', intval(env('COVER_HEIGHT', 600)));

// === تنظیمات کش ===
define('CACHE_ENABLE', filter_var(env('CACHE_ENABLE', true), FILTER_VALIDATE_BOOLEAN));
define('CACHE_DURATION', intval(env('CACHE_DURATION', 3600)));

// === تنظیمات لاگ ===
define('DEBUG_MODE', filter_var(env('DEBUG_MODE', false), FILTER_VALIDATE_BOOLEAN));
define('ERROR_LOGGING', filter_var(env('ERROR_LOGGING', true), FILTER_VALIDATE_BOOLEAN));
define('LOG_LEVEL', env('LOG_LEVEL', 'ERROR'));

// === تنظیمات صفحه‌بندی ===
define('ITEMS_PER_PAGE', intval(env('ITEMS_PER_PAGE', 20)));
define('BOOKS_PER_PAGE', intval(env('BOOKS_PER_PAGE', 12)));

// === تنظیمات رزرو ===
define('DEFAULT_BORROW_DAYS', intval(env('DEFAULT_BORROW_DAYS', 15)));
define('MAX_BORROW_DAYS', intval(env('MAX_BORROW_DAYS', 30)));
define('MAX_ACTIVE_RESERVATIONS', intval(env('MAX_ACTIVE_RESERVATIONS', 3)));
define('LATE_FEE_PER_DAY', intval(env('LATE_FEE_PER_DAY', 5000)));

// === تنظیمات خطا ===
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// تنظیم error log
if (ERROR_LOGGING) {
    ini_set('error_log', LOGS_PATH . '/php_errors.log');
}

// === تنظیمات زمان ===
date_default_timezone_set('Asia/Tehran');

// === Autoloader ===
spl_autoload_register(function ($class) {
    $file = CLASSES_PATH . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
});

// === توابع Helper ===

/**
 * لاگ کردن خطا
 */
function logError($message, $context = [], $level = 'ERROR') {
    if (!ERROR_LOGGING) return false;

    // ایجاد پوشه logs اگر وجود نداشته باشد
    if (!file_exists(LOGS_PATH)) {
        mkdir(LOGS_PATH, 0755, true);
    }

    $logFile = LOGS_PATH . '/app_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');

    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logMessage = "[$timestamp] [$level] $message$contextStr\n";

    return file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * لاگ کردن اطلاعات
 */
function logInfo($message, $context = []) {
    return logError($message, $context, 'INFO');
}

/**
 * لاگ کردن هشدار
 */
function logWarning($message, $context = []) {
    return logError($message, $context, 'WARNING');
}

/**
 * Redirect امن
 */
function secureRedirect($url, $statusCode = 302) {
    // جلوگیری از Open Redirect Vulnerability
    $parsedUrl = parse_url($url);

    // اگر URL خارجی باشد، به صفحه اصلی هدایت کن
    if (isset($parsedUrl['scheme']) || isset($parsedUrl['host'])) {
        $url = SITE_URL;
    }

    // پاکسازی URL
    $url = filter_var($url, FILTER_SANITIZE_URL);

    // تنظیم header
    header("Location: $url", true, $statusCode);
    exit;
}

/**
 * دریافت URL کامل
 */
function getFullUrl($path = '') {
    return SITE_URL . '/' . ltrim($path, '/');
}

/**
 * دریافت Asset URL
 */
function asset($path) {
    return SITE_URL . '/assets/' . ltrim($path, '/');
}

/**
 * دریافت Upload URL
 */
function uploadUrl($path) {
    return SITE_URL . '/' . UPLOAD_PATH . ltrim($path, '/');
}

/**
 * فرمت کردن تاریخ شمسی
 */
function formatJalaliDate($timestamp = null, $format = 'Y/m/d') {
    if ($timestamp === null) {
        $timestamp = time();
    }

    require_once __DIR__ . '/jdf.php';
    return jdate($format, $timestamp);
}

/**
 * تبدیل تاریخ میلادی به شمسی
 */
function gregorianToJalali($date) {
    require_once __DIR__ . '/jdf.php';
    list($y, $m, $d) = explode('-', $date);
    return gregorian_to_jalali($y, $m, $d);
}

/**
 * ساخت slug فارسی
 */
function createSlug($text) {
    $text = trim($text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/[^\p{L}\p{N}\-]/u', '', $text);
    return $text;
}

/**
 * Sanitize Output
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * چک کردن اینکه کاربر لاگین کرده یا نه
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * چک کردن اینکه کاربر ادمین است یا نه
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * دریافت اطلاعات کاربر جاری
 */
function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'name' => $_SESSION['name'] ?? null,
        'role' => $_SESSION['role'] ?? 'member'
    ];
}

/**
 * نمایش پیام Flash
 */
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = [
        'text' => $message,
        'type' => $type
    ];
}

/**
 * دریافت و حذف پیام Flash
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * فرمت کردن حجم فایل
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * تولید رمز عبور تصادفی
 */
function generateRandomPassword($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
    $password = '';
    $charactersLength = strlen($characters);

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $password;
}

/**
 * کوتاه کردن متن
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length) . $suffix;
}

// === ساخت دایرکتوری‌های ضروری ===
$requiredDirs = [
    UPLOADS_PATH,
    UPLOADS_PATH . '/books',
    UPLOADS_PATH . '/users',
    CACHE_PATH,
    LOGS_PATH
];

foreach ($requiredDirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// === Security Headers ===
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (isset($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// لاگ شروع برنامه
if (DEBUG_MODE) {
    logInfo('Application Started', [
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ]);
}
?>
