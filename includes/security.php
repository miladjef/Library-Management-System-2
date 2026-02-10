<?php
/**
 * Security Functions - سیستم مدیریت کتابخانه
 * فایل توابع امنیتی
 */

// جلوگیری از دسترسی مستقیم
if (!defined('BASEPATH')) {
    define('BASEPATH', true);
}

// ===== تنظیمات Session امن =====
function init_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // تنظیمات امنیتی session
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        session_start();
        
        // بازسازی session ID برای جلوگیری از Session Fixation
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }
}

// ===== CSRF Protection =====
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

// ===== XSS Prevention =====
function escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function escape_array($array) {
    return array_map('escape', $array);
}

// ===== Input Sanitization =====
function sanitize_string($input) {
    return trim(strip_tags($input ?? ''));
}

function sanitize_email($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function sanitize_int($input) {
    return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

function sanitize_isbn($isbn) {
    return preg_replace('/[^0-9Xx-]/', '', $isbn ?? '');
}

// ===== Input Validation =====
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_isbn($isbn) {
    $isbn = preg_replace('/[-]/', '', $isbn);
    return strlen($isbn) === 10 || strlen($isbn) === 13;
}

function validate_password($password, $min_length = 8) {
    // حداقل ۸ کاراکتر، شامل حرف و عدد
    if (strlen($password) < $min_length) {
        return false;
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    return true;
}

function validate_national_code($code) {
    // اعتبارسنجی کد ملی ایران
    if (!preg_match('/^[0-9]{10}$/', $code)) {
        return false;
    }
    
    $check = 0;
    for ($i = 0; $i < 9; $i++) {
        $check += ((10 - $i) * intval($code[$i]));
    }
    $remainder = $check % 11;
    $lastDigit = intval($code[9]);
    
    return ($remainder < 2 && $lastDigit === $remainder) || 
           ($remainder >= 2 && $lastDigit === (11 - $remainder));
}

// ===== Rate Limiting =====
function check_rate_limit($action, $max_attempts = 5, $time_window = 300) {
    $key = 'rate_limit_' . $action . '_' . get_client_ip();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $data = &$_SESSION[$key];
    
    // ریست کردن اگر زمان گذشته
    if (time() - $data['first_attempt'] > $time_window) {
        $data = ['count' => 0, 'first_attempt' => time()];
    }
    
    $data['count']++;
    
    if ($data['count'] > $max_attempts) {
        $remaining = $time_window - (time() - $data['first_attempt']);
        return [
            'allowed' => false,
            'remaining_time' => $remaining,
            'message' => "تعداد تلاش‌های شما بیش از حد مجاز است. لطفاً {$remaining} ثانیه صبر کنید."
        ];
    }
    
    return ['allowed' => true];
}

function reset_rate_limit($action) {
    $key = 'rate_limit_' . $action . '_' . get_client_ip();
    unset($_SESSION[$key]);
}

// ===== IP & User Agent =====
function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            if (filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                return trim($ip);
            }
        }
    }
    
    return '0.0.0.0';
}

function get_user_agent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

// ===== File Upload Security =====
function validate_upload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif'], $max_size = 2097152) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'خطا در آپلود فایل';
        return ['valid' => false, 'errors' => $errors];
    }
    
    // بررسی سایز
    if ($file['size'] > $max_size) {
        $errors[] = 'حجم فایل بیش از حد مجاز است (حداکثر ' . ($max_size / 1024 / 1024) . ' مگابایت)';
    }
    
    // بررسی MIME type واقعی
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $actual_type = $finfo->file($file['tmp_name']);
    
    if (!in_array($actual_type, $allowed_types)) {
        $errors[] = 'نوع فایل مجاز نیست';
    }
    
    // بررسی پسوند
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowed_extensions)) {
        $errors[] = 'پسوند فایل مجاز نیست';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'mime_type' => $actual_type,
        'extension' => $extension
    ];
}

function generate_safe_filename($original_name) {
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    return bin2hex(random_bytes(16)) . '.' . $extension;
}

// ===== SQL Injection Prevention Helper =====
function prepare_like_param($search) {
    // Escape کردن کاراکترهای خاص LIKE
    $search = str_replace(['%', '_'], ['\%', '\_'], $search);
    return '%' . $search . '%';
}

// ===== Security Headers =====
function set_security_headers() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ===== Logging =====
function log_security_event($event_type, $details = [], $user_id = null) {
    global $conn;
    
    $log_data = [
        'event_type' => $event_type,
        'user_id' => $user_id ?? ($_SESSION['userid'] ?? null),
        'ip_address' => get_client_ip(),
        'user_agent' => get_user_agent(),
        'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // ذخیره در فایل لاگ
    $log_file = __DIR__ . '/../logs/security_' . date('Y-m-d') . '.log';
    $log_line = date('Y-m-d H:i:s') . ' | ' . $event_type . ' | ' . 
                get_client_ip() . ' | ' . json_encode($details, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    
    @file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    
    return true;
}

// ===== Initialize =====
init_secure_session();
set_security_headers();
generate_csrf_token();
