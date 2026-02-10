<?php
require_once __DIR__ . '/includes/security.php';

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/BookAPIHandler.php';
require_once '../classes/CSRF.php';

header('Content-Type: application/json; charset=utf-8');

// بررسی ورود کاربر
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'دسترسی غیرمجاز'
    ]);
    exit;
}

// بررسی CSRF Token
if (!CSRF::validate($_POST['csrf_token'] ?? '', 'book_search')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'توکن امنیتی نامعتبر است'
    ]);
    exit;
}

// دریافت ISBN
$isbn = trim($_POST['isbn'] ?? '');

if (empty($isbn)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'شناسه ISBN الزامی است'
    ]);
    exit;
}

// اعتبارسنجی فرمت ISBN
if (!preg_match('/^(?:\d{10}|\d{13})$/', $isbn)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'فرمت ISBN نامعتبر است (باید 10 یا 13 رقم باشد)'
    ]);
    exit;
}

try {
    $apiHandler = new BookAPIHandler();
    $bookData = $apiHandler->searchByISBN($isbn);

    if ($bookData) {
        echo json_encode([
            'success' => true,
            'data' => $bookData
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'کتابی با این ISBN یافت نشد'
        ]);
    }
} catch (Exception $e) {
    logError('Book API Search Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطا در جستجوی کتاب. لطفاً دوباره تلاش کنید.'
    ]);
}
