<?php
$title = "تیکت و پشتیبانی";
include "inc/header.php"; ?>

<?php include "inc/tickets.php"; ?>

<?php include "inc/footer.php"; ?><?php
// tickets.php
if (!$user_logged_in) {
    header('Location: login.php');
    exit;
}

require_once 'classes/Ticket.php';

$db = Database::getInstance();
$ticket = new Ticket($db);

$member_id = $_SESSION['userid'];
$title = 'تیکت‌های پشتیبانی';
include "inc/header.php";

// پردازش ارسال تیکت جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'خطای امنیتی';
    } else {
        $data = [
            'mid' => $member_id,
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description']),
            'priority' => $_POST['priority']
        ];
        
        $result = $ticket->create($data);
        
        if ($result['success']) {
            $success = 'تیکت شما با موفقیت ثبت شد. شماره پیگیری: ' . $result['ticket_id'];
        } else {
            $error = $result['message'];
        }
    }
}

// تولید CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// دریافت تیکت‌های کاربر
$user_tickets = $ticket->getByMember($member_id);
?>

<div class="container">
    <div class="tickets-page">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-headset"></i>
                تیکت‌های پشتیبانی
            </h1>
            <button class="btn btn-primary" onclick="toggleNewTicketForm()">
                <i class="fas fa-plus"></i>
                تیکت جدید
            </button>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- فرم تیکت جدید -->
        <div class="new-ticket-form" id="newTicketForm" style="display: none;">
            <div class="form-header">
                <h3>
                    <i class="fas fa-plus-circle"></i>
                    ارسال تیکت جدید
                </h3>
                <button class="close-btn" onclick="toggleNewTicketForm()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-group">
                    <label for="title">
                        <i class="fas fa-heading"></i>
                        موضوع تیکت:
                    </label>
                    <input type="text" name="title" id="title" class="form-control" 
                           placeholder="خلاصه‌ای از موضوع تیکت" required>
                </div>
                
                <div class="form-group">
                    <label for="priority">
                        <i class="fas fa-flag"></i>
                        اولویت:
                    </label>
                    <select name="priority" id="priority" class="form-control" required>
                        <option value="low">کم</option>
                        <option value="medium" selected>متوسط</option>
                        <option value="high">زیاد</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">
                        <i class="fas fa-align-left"></i>
                        توضیحات:
                    </label>
                    <textarea name="description" id="description" class="form-control" rows="5" 
                              placeholder="لطفا مشکل یا سوال خود را با جزئیات بیان کنید..." required></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="submit_ticket" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        ارسال تیکت
                    </button>
                    <button type="button" class="btn btn-outline" onclick="toggleNewTicketForm()">
                        <i class="fas fa-times"></i>
                        انصراف
                    </button>
                </div>
            </form>
        </div>

        <!-- لیست تیکت‌ها -->
        <div class="tickets-list">
            <?php if (empty($user_tickets)): ?>
                <div class="no-results">
                    <i class="fas fa-ticket-alt"></i>
                    <h3>هیچ تیکتی وجود ندارد</h3>
                    <p>شما هنوز هیچ تیکتی ارسال نکرده‌اید</p>
                    <button class="btn btn-primary" onclick="toggleNewTicketForm()">
                        <i class="fas fa-plus"></i>
                        ارسال اولین تیکت
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($user_tickets as $tkt): ?>
                    <div class="ticket-card">
                        <div class="ticket-header">
                            <div class="ticket-info">
                                <h3 class="ticket-title">
                                    <?= htmlspecialchars($tkt['title']) ?>
                                </h3>
                                <div class="ticket-meta">
                                    <span class="ticket-id">
                                        <i class="fas fa-hashtag"></i>
                                        <?= $tkt['ticket_id'] ?>
                                    </span>
                                    <span class="ticket-date">
                                        <i class="fas fa-clock"></i>
                                        <?= jdate('Y/m/d H:i', strtotime($tkt['created_at'])) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="ticket-badges">
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
                                $status_class = $status_classes[$tkt['status']] ?? 'badge-secondary';
                                $status_label = $status_labels[$tkt['status']] ?? $tkt['status'];
                                ?>
                                <span class="badge <?= $status_class ?>">
                                    <?= $status_label ?>
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
                                $priority_class = $priority_classes[$tkt['priority']] ?? 'badge-secondary';
                                $priority_label = $priority_labels[$tkt['priority']] ?? $tkt['priority'];
                                ?>
                                <span class="badge <?= $priority_class ?>">
                                    <?= $priority_label ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="ticket-description">
                            <?= nl2br(htmlspecialchars(mb_substr($tkt['description'], 0, 200))) ?>
                            <?php if (mb_strlen($tkt['description']) > 200): ?>
                                ...
                            <?php endif; ?>
                        </div>
                        
                        <div class="ticket-footer">
                            <?php if ($tkt['replies_count'] > 0): ?>
                                <span class="replies-count">
                                    <i class="fas fa-comments"></i>
                                    <?= $tkt['replies_count'] ?> پاسخ
                                </span>
                            <?php endif; ?>
                            
                            <a href="ticket-view.php?id=<?= $tkt['ticket_id'] ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-eye"></i>
                                مشاهده جزئیات
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleNewTicketForm() {
    const form = document.getElementById('newTicketForm');
    if (form.style.display === 'none') {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth' });
    } else {
        form.style.display = 'none';
    }
}
</script>

<?php include "inc/footer.php"; ?>
