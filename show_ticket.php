<?php
session_start();
if (!isset($_SESSION['userid'])) {
    header('Location: login.php');
    exit;
}

$title = "نمایش تیکت";
include "inc/header.php"; 

// اتصال به پایگاه داده
require_once "config/database.php";

// بررسی وجود ID تیکت
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: tickets.php');
    exit;
}

$ticket_id = intval($_GET['id']);

// دریافت تیکت با بررسی مالکیت کاربر
$stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
$stmt->execute([$ticket_id, $_SESSION['userid']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: tickets.php');
    exit;
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">تیکت شماره <?php echo htmlspecialchars($ticket['id']); ?></h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>موضوع:</strong>
                        <p><?php echo htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="mb-3">
                        <strong>اولویت:</strong>
                        <span class="badge bg-<?php 
                            switch($ticket['priority']) {
                                case 'high': echo 'danger'; break;
                                case 'medium': echo 'warning'; break;
                                case 'low': echo 'success'; break;
                                default: echo 'secondary';
                            }
                        ?>">
                            <?php echo htmlspecialchars($ticket['priority'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>وضعیت:</strong>
                        <span class="badge bg-<?php 
                            switch($ticket['status']) {
                                case 'open': echo 'primary'; break;
                                case 'closed': echo 'secondary'; break;
                                case 'pending': echo 'warning'; break;
                                default: echo 'light';
                            }
                        ?>">
                            <?php echo htmlspecialchars($ticket['status'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>پیام:</strong>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($ticket['message'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong>تاریخ ایجاد:</strong>
                        <p><?php echo htmlspecialchars($ticket['created_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="tickets.php" class="btn btn-secondary">بازگشت به لیست تیکت‌ها</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "inc/footer.php"; ?>