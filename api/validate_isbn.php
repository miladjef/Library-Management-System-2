<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Book.php';

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

$isbn = trim($_GET['isbn'] ?? '');
$currentBookId = intval($_GET['book_id'] ?? 0);

if (empty($isbn)) {
    echo json_encode(['valid' => false, 'message' => 'ISBN الزامی است']);
    exit;
}

// اعتبارسنجی فرمت
if (!preg_match('/^(?:\d{10}|\d{13})$/', $isbn)) {
    echo json_encode(['valid' => false, 'message' => 'فرمت ISBN نامعتبر است']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // بررسی تکراری بودن (به جز کتاب فعلی در حالت ویرایش)
    $query = "SELECT COUNT(*) as count FROM books WHERE isbn = :isbn";
    if ($currentBookId > 0) {
        $query .= " AND bid != :book_id";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':isbn', $isbn);
    if ($currentBookId > 0) {
        $stmt->bindParam(':book_id', $currentBookId, PDO::PARAM_INT);
    }
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo json_encode([
            'valid' => false,
            'message' => 'این ISBN قبلاً ثبت شده است'
        ]);
    } else {
        echo json_encode([
            'valid' => true,
            'message' => 'ISBN معتبر است'
        ]);
    }
    
} catch (Exception $e) {
    logError('ISBN Validation Error: ' . $e->getMessage());
    echo json_encode([
        'valid' => false,
        'message' => 'خطا در بررسی ISBN'
    ]);
}
