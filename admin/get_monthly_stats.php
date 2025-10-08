<?php
// admin/get_monthly_stats.php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once 'inc/functions.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    echo json_encode(['error' => 'دسترسی غیرمجاز']);
    exit;
}

require_once '../classes/jdf.php';

// دریافت آمار 12 ماه گذشته
$monthly_stats = $conn->prepare("
    SELECT 
        DATE_FORMAT(reservation_date, '%Y-%m') as month,
        COUNT(*) as count
    FROM reservations
    WHERE reservation_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(reservation_date, '%Y-%m')
    ORDER BY month ASC
");
$monthly_stats->execute();
$results = $monthly_stats->fetchAll(PDO::FETCH_ASSOC);

// تبدیل به آرایه‌های label و value
$labels = [];
$values = [];

foreach ($results as $row) {
    list($year, $month) = explode('-', $row['month']);
    
    // تبدیل به تاریخ شمسی
    $jdate = gregorian_to_jalali((int)$year, (int)$month, 1);
    $persian_months = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
        4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
        10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
    ];
    
    $labels[] = $persian_months[$jdate[1]] . ' ' . $jdate[0];
    $values[] = (int)$row['count'];
}

echo json_encode([
    'labels' => $labels,
    'values' => $values
], JSON_UNESCAPED_UNICODE);
