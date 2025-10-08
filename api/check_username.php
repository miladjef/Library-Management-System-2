<?php
// api/check_username.php
header('Content-Type: application/json');
require_once '../inc/config.php';

if (!isset($_GET['username'])) {
    echo json_encode(['error' => 'Username not provided']);
    exit;
}

$username = trim($_GET['username']);

if (strlen($username) < 3) {
    echo json_encode(['available' => false, 'message' => 'حداقل 3 کاراکتر']);
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT COUNT(*) FROM members WHERE username = ?");
$stmt->execute([$username]);
$count = $stmt->fetchColumn();

echo json_encode([
    'available' => $count == 0,
    'message' => $count == 0 ? 'نام کاربری موجود است' : 'این نام کاربری قبلا گرفته شده'
]);
