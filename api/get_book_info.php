<?php
// api/get_book_info.php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once '../classes/Database.php';
require_once '../classes/Book.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'دسترسی غیرمجاز'
    ]);
    exit;
}

// بررسی پارامتر ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'شناسه کتاب نامعتبر است'
    ]);
    exit;
}

$book_id = (int)$_GET['id'];

try {
    $db = Database::getInstance();
    $book = new Book($db);

    // دریافت اطلاعات کتاب
    $bookInfo = $book->getById($book_id);

    if (!$bookInfo) {
        echo json_encode([
            'success' => false,
            'message' => 'کتاب یافت نشد'
        ]);
        exit;
    }

    // محاسبه موجودی قابل امانت
    $availability_stmt = $db->prepare("
        SELECT
            b.book_quantity as total,
            COUNT(r.rid) as borrowed
        FROM books b
        LEFT JOIN reservations r ON b.bid = r.bid AND r.status = 'active'
        WHERE b.bid = ?
        GROUP BY b.bid
    ");
    $availability_stmt->execute([$book_id]);
    $availability = $availability_stmt->fetch(PDO::FETCH_ASSOC);

    $total_quantity = $availability['total'] ?? 0;
    $borrowed_count = $availability['borrowed'] ?? 0;
    $available_quantity = $total_quantity - $borrowe