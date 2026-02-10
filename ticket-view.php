<?php
// ticket-view.php
if (!$user_logged_in) {
    header('Location: login.php');
    exit;
}

$ticket_id = $_GET['id'] ?? 0;
if (!$ticket_id) {
    header('Location: tickets.php');
    exit;
}

require_once 'classes/Ticket.php';

$db = Database::getInstance();
$ticket = new Ticket($db);

$member_id = $_SESSION['userid'];

// دریافت اطلاعات تیکت با بررسی مالکیت
$ticket_info = $ticket->getByIdAndUserId($ticket_id, $member_id);

// بررسی وجود تیکت
if (!$ticket_info) {
    header('Location: tickets.php');
    exit;
}

$title = 'مشاهده تیکت';
include "inc/header.php";

// پردازش ارسال پاسخ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_reply'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'خطای امنیتی';
    } else {
        $reply_data = [
            'ticket_id' => $ticket_id,
            'mid' => $member_id,
            'message' => trim($_POST['message']),
            'sender_type' => 'user'
        ];

        $result = $ticket->addReply($reply_data);

        if ($result['success']) {
            $success = 'پاسخ شما ثبت شد';
            // بارگذاری مجدد اطلاعات
            $ticket_info = $ticket->getByIdAndUserId($ticket_id, $member_id);
        } else {
            $error = $result['message'];
        }
    }
}

// تولید CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// دریافت پاسخ‌های تیکت با بررسی مالکیت
$replies = $ticket->getRepliesByTicketIdAndUserId($ticket_id, $member_id);
?>

<div class="container">
    <div class="ticket-view-page">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="<?php echo siteurl() ?>">خانه</a>
            <i class="fas fa-chevron-left"></i>
            <a href="tickets.php">تیکت‌ها</a>
            <i class="fas fa-chevron-left"></i>
            <span>تیکت #<?php echo htmlspecialchars($ticket_info['ticket_id'], ENT_QUOTES, 'UTF-8') ?></span>
        </nav>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- اطلاعات تیکت -->
        <div class="ticket-details">
            <div class="ticket-header-section">
                <div class="ticket-title-section">
                    <h1><?php echo htmlspecialchars($ticket_info['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <div class="ticket-meta-info">
                        <span class="meta-item">
                            <i class="fas fa-hashtag"></i>
                            شماره تیکت: <?php echo htmlspecialchars($ticket_info['ticket_id'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-calendar"></i>
                            ایجاد شده: <?php echo jdate('Y/m/d H:i', strtotime($ticket_info['created_at'])) ?>
                        </span>
                        <?php if ($ticket_info['updated_at']): ?>
                            <span class="meta-item">
                                <i class="fas fa-clock"></i>
                                آخرین به‌روزرسانی: <?php echo jdate('Y/m/d H:i', strtotime($ticket_info['updated_at'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ticket-badges-section">
                    <?php
                    $status_classes = [
                        'open' => 'badge-warning',
                        'answered' => 'badge-info',
                        'closed' => 'badge-success'
                    ];
                    $status_labels = [
                        'open' => 'باز',
                        'answered' => 'پاسخ داده شده',
                        'closed' => 'بسته شده'
                    ];
                    $status_class = $status_classes[$ticket_info['status']] ?? 'badge-secondary';
                    $status_label = $status_labels[$ticket_info['status']] ?? $ticket_info['status'];
                    ?>
                    <span class="badge badge-large <?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8') ?>">
                        <?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <?php
                    $priority_classes = [
                        'low' => 'badge-success',
                        'medium' => 'badge-warning',
                        'high' => 'badge-danger'
                    ];
                    $priority_labels = [
                        'low' => 'کم',
                        'medium' => 'متوسط',
                        'high' => 'زیاد'
                    ];
                    $priority_class = $priority_classes[$ticket_info['priority']] ?? 'badge-secondary';
                    $priority_label = $priority_labels[$ticket_info['priority']] ?? $ticket_info['priority'];
                    ?>
                    <span class="badge badge-large <?php echo htmlspecialchars($priority_class, ENT_QUOTES, 'UTF-8') ?>">
                        اولویت: <?php echo htmlspecialchars($priority_label, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            </div>

            <!-- متن اصلی تیکت -->
            <div class="ticket-message original-message">
                <div class="message-header">
                    <div class="sender-info">
                        <i class="fas fa-user-circle"></i>
                        <span class="sender-name">شما</span>
                    </div>
                    <span class="message-date">
                        <?php echo jdate('Y/m/d H:i', strtotime($ticket_info['created_at'])) ?>
                    </span>
                </div>
                <div class="message-content">
                    <?php echo nl2br(htmlspecialchars($ticket_info['description'], ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </div>
        </div>

        <!-- پاسخ‌ها -->
        <?php if (!empty($replies)): ?>
            <div class="ticket-replies">
                <h3>
                    <i class="fas fa-comments"></i>
                    پاسخ‌ها (<?php echo count($replies) ?>)
                </h3>

                <?php foreach ($replies as $reply): ?>
                    <div class="ticket-message <?php echo htmlspecialchars($reply['sender_type'] == 'admin' ? 'admin-message' : 'user-message', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="message-header">
                            <div class="sender-info">
                                <i class="fas <?php echo htmlspecialchars($reply['sender_type'] == 'admin' ? 'fa-user-shield' : 'fa-user-circle', ENT_QUOTES, 'UTF-8') ?>"></i>
                                <span class="sender-name">
                                    <?php if ($reply['sender_type'] == 'admin'): ?>
                                        پشتیبانی کتابخانه
                                    <?php else: ?>
                                        شما
                                    <?php endif; ?>
                                </span>
                            </div>
                            <span class="message-date">
                                <?php echo jdate('Y/m/d H:i', strtotime($reply['created_at'])) ?>
                            </span>
                        </div>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($reply['message'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- فرم پاسخ -->
        <?php if ($ticket_info['status'] != 'closed'): ?>
            <div class="reply-form">
                <h3>
                    <i class="fas fa-reply"></i>
                    پاسخ جدید
                </h3>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group">
                        <textarea name="message" class="form-control" rows="5"
                                  placeholder="پاسخ خود را بنویسید..." required></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="submit_reply" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            ارسال پاسخ
                        </button>
                        <a href="tickets.php" class="btn btn-outline">
                            <i class="fas fa-arrow-right"></i>
                            بازگشت به لیست
                        </a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="ticket-closed-notice">
                <i class="fas fa-lock"></i>
                <p>این تیکت بسته شده است و امکان ارسال پاسخ جدید وجود ندارد</p>
                <a href="tickets.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    بازگشت به لیست تیکت‌ها
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "inc/footer.php"; ?>