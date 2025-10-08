<?php
// api/get_member_info.php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once '../classes/Database.php';
require_once '../classes/Member.php';
require_once '../admin/inc/functions.php';

// بررسی احراز هویت
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
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
        'message' => 'شناسه عضو نامعتبر است'
    ]);
    exit;
}

$member_id = (int)$_GET['id'];

try {
    $db = Database::getInstance();
    $member = new Member($db);
    
    // دریافت اطلاعات کامل عضو
    $memberInfo = $member->getMemberFullInfo($member_id);
    
    if (!$memberInfo) {
        echo json_encode([
            'success' => false,
            'message' => 'عضو یافت نشد'
        ]);
        exit;
    }
    
    // دریافت تنظیمات سیستم
    $settings_stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('max_active_reservations', 'daily_penalty_amount')");
    $settings_stmt->execute();
    $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $max_active = isset($settings['max_active_reservations']) ? (int)$settings['max_active_reservations'] : 3;
    $daily_penalty = isset($settings['daily_penalty_amount']) ? (float)$settings['daily_penalty_amount'] : 5000;
    
    // محاسبه جریمه‌های پرداخت نشده
    $penalty_stmt = $db->prepare("
        SELECT SUM(penalty) as total_penalty 
        FROM reservations 
        WHERE mid = ? AND penalty_paid = 0 AND penalty > 0
    ");
    $penalty_stmt->execute([$member_id]);
    $penalty_data = $penalty_stmt->fetch(PDO::FETCH_ASSOC);
    $unpaid_penalties = $penalty_data['total_penalty'] ?? 0;
    
    // شمارش امانت‌های فعال
    $active_stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM reservations 
        WHERE mid = ? AND status = 'active'
    ");
    $active_stmt->execute([$member_id]);
    $active_data = $active_stmt->fetch(PDO::FETCH_ASSOC);
    $active_reservations = $active_data['count'];
    
    // آماده‌سازی پاسخ
    $response = [
        'success' => true,
        'member' => [
            'id' => $memberInfo['mid'],
            'name' => $memberInfo['name'],
            'surname' => $memberInfo['surname'],
            'username' => $memberInfo['username'],
            'national_code' => $memberInfo['national_code'] ?? 'ثبت نشده',
            'mobile' => $memberInfo['mobile'] ?? 'ثبت نشده',
            'email' => $memberInfo['email'] ?? 'ثبت نشده',
            'address' => $memberInfo['address'] ?? 'ثبت نشده',
            'is_active' => (bool)$memberInfo['is_active'],
            'active_reservations' => (int)$active_reservations,
            'total_reservations' => (int)$memberInfo['total_reservations'],
            'returned_reservations' => (int)$memberInfo['returned_reservations'],
            'unpaid_penalties' => (float)$unpaid_penalties,
            'max_active' => $max_active,
            'can_borrow' => ($active_reservations < $max_active && $unpaid_penalties == 0 && $memberInfo['is_active'])
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت اطلاعات: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
