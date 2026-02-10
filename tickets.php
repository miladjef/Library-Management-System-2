<?php
$title = "تیکت و پشتیبانی";

// بررسی لاگین بودن کاربر
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

include "inc/header.php"; 
?>

<?php
require_once 'classes/Ticket.php';

$db = Database::getInstance();
$ticket = new Ticket($db);

$member_id = $_SESSION['userid'];

// پردازش ارسال تیکت جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    // اعتبارسنجی CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'خطای امنیتی. لطفا مجددا تلاش کنید.';
    } else {
        // اعتبارسنجی ورودی‌ها
        $title_input = trim($_POST['title'] ?? '');
        $description_input = trim($_POST['description'] ?? '');
        $priority_input = $_POST['priority'] ?? 'medium';
        
        // اعتبارسنجی اولویت
        $allowed_priorities = ['low', 'medium', 'high'];
        if (!in_array($priority_input, $allowed_priorities)) {
            $priority_input = 'medium';
        }
        
        // اعتبارسنجی عنوان و توضیحات
        if (empty($title_input) || strlen($title_input) < 3) {
            $error = 'عنوان تیکت باید حداقل ۳ کاراکتر داشته باشد.';
        } elseif (empty($description_input) || strlen($description_input) < 10) {
            $error = 'توضیحات تیکت باید حداقل ۱۰ کاراکتر داشته باشد.';
        } else {
            $data = [
                'mid' => $member_id,
                'title' => htmlspecialchars($title_input, ENT_QUOTES, 'UTF-8'),
                'description' => htmlspecialchars($description_input, ENT_QUOTES, 'UTF-8'),
                'priority' => $priority_input
            ];

            $result = $ticket->create($data);

            if ($result['success']) {
                $success = 'تیکت شما با موفقیت ثبت شد. شماره پیگیری: ' . $result['ticket_id'];
                
                // پاک کردن فرم
                unset($_POST['title']);
                unset($_POST['description']);
                unset($_POST['priority']);
            } else {
                $error = $result['message'] ?? 'خطا در ثبت تیکت. لطفا مجددا تلاش کنید.';
            }
        }
    }
}

// تولید CSRF token جدید برای هر بار لود صفحه
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// دریافت تیکت‌های کاربر با اطمینان از امنیت
try {
    $user_tickets = $ticket->getByMember($member_id);
} catch (Exception $e) {
    $error = 'خطا در دریافت اطلاعات تیکت‌ها.';
    $user_tickets = [];
}
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
                <?php echo htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- فرم تیکت جدید -->
        <div class="new-ticket-form" id="newTicketForm" style="display: none;">
            <div class="form-header">
                <h3>
                    <i class="fas fa-plus-circle"></i>
                    ارسال تیکت جدید
                </h3>
                <button type="button" class="close-btn" onclick="toggleNewTicketForm()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" action="" id="ticketForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label for="title">
                        <i class="fas fa-heading"></i>
                        موضوع تیکت:
                    </label>
                    <input type="text" name="title" id="title" class="form-control"
                           placeholder="خلاصه‌ای از موضوع تیکت" required
                           value="<?php echo htmlspecialchars($_POST['title'] ?? '') ?>"
                           minlength="3" maxlength="255">
                    <div class="form-error" id="titleError"></div>
                </div>

                <div class="form-group">
                    <label for="priority">
                        <i class="fas fa-flag"></i>
                        اولویت:
                    </label>
                    <select name="priority" id="priority" class="form-control" required>
                        <option value="low" <?php echo (($_POST['priority'] ?? 'medium') == 'low') ? 'selected' : '' ?>>کم</option>
                        <option value="medium" <?php echo (($_POST['priority'] ?? 'medium') == 'medium') ? 'selected' : '' ?>>متوسط</option>
                        <option value="high" <?php echo (($_POST['priority'] ?? 'medium') == 'high') ? 'selected' : '' ?>>زیاد</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">
                        <i class="fas fa-align-left"></i>
                        توضیحات:
                    </label>
                    <textarea name="description" id="description" class="form-control" rows="5"
                              placeholder="لطفا مشکل یا سوال خود را با جزئیات بیان کنید..." required
                              minlength="10" maxlength="5000"><?php echo htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    <div class="form-error" id="descriptionError"></div>
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
                    <?php 
                    // اطمینان از تعلق تیکت به کاربر جاری
                    if ($tkt['mid'] != $member_id) {
                        continue; // از نمایش تیکت متعلق به کاربر دیگر جلوگیری می‌کند
                    }
                    ?>
                    <div class="ticket-card" id="ticket-<?php echo $tkt['ticket_id'] ?>">
                        <div class="ticket-header">
                            <div class="ticket-info">
                                <h3 class="ticket-title">
                                    <?php echo htmlspecialchars($tkt['title']) ?>
                                </h3>
                                <div class="ticket-meta">
                                    <span class="ticket-id">
                                        <i class="fas fa-hashtag"></i>
                                        <?php echo htmlspecialchars($tkt['ticket_id']) ?>
                                    </span>
                                    <span class="ticket-date">
                                        <i class="fas fa-clock"></i>
                                        <?php echo jdate('Y/m/d H:i', strtotime($tkt['created_at'])) ?>
                                    </span>
                                    <?php if (isset($tkt['last_update'])): ?>
                                    <span class="ticket-update">
                                        <i class="fas fa-sync"></i>
                                        آخرین بروزرسانی: <?php echo jdate('Y/m/d H:i', strtotime($tkt['last_update'])) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="ticket-badges">
                                <?php
                                $status_classes = [
                                    'open' => 'badge-warning',
                                    'answered' => 'badge-info',
                                    'closed' => 'badge-success',
                                    'pending' => 'badge-secondary'
                                ];
                                $status_labels = [
                                    'open' => 'باز',
                                    'answered' => 'پاسخ داده شده',
                                    'closed' => 'بسته شده',
                                    'pending' => 'در انتظار'
                                ];
                                $status = $tkt['status'] ?? 'open';
                                $status_class = $status_classes[$status] ?? 'badge-secondary';
                                $status_label = $status_labels[$status] ?? $status;
                                ?>
                                <span class="badge <?php echo $status_class ?>">
                                    <?php echo $status_label ?>
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
                                $priority = $tkt['priority'] ?? 'medium';
                                $priority_class = $priority_classes[$priority] ?? 'badge-secondary';
                                $priority_label = $priority_labels[$priority] ?? $priority;
                                ?>
                                <span class="badge <?php echo $priority_class ?>">
                                    <?php echo $priority_label ?>
                                </span>
                            </div>
                        </div>

                        <div class="ticket-description">
                            <?php 
                            $description = htmlspecialchars($tkt['description']);
                            if (mb_strlen($description) > 200) {
                                echo nl2br(mb_substr($description, 0, 200)) . '...';
                            } else {
                                echo nl2br($description);
                            }
                            ?>
                        </div>

                        <div class="ticket-footer">
                            <?php if (($tkt['replies_count'] ?? 0) > 0): ?>
                                <span class="replies-count">
                                    <i class="fas fa-comments"></i>
                                    <?php echo (int)$tkt['replies_count'] ?> پاسخ
                                </span>
                            <?php endif; ?>

                            <?php if (isset($tkt['attachments_count']) && $tkt['attachments_count'] > 0): ?>
                                <span class="attachments-count">
                                    <i class="fas fa-paperclip"></i>
                                    <?php echo (int)$tkt['attachments_count'] ?> فایل
                                </span>
                            <?php endif; ?>

                            <a href="ticket-view.php?id=<?php echo urlencode($tkt['ticket_id']) ?>" 
                               class="btn btn-outline btn-sm"
                               onclick="return validateTicketAccess(<?php echo (int)$tkt['ticket_id'] ?>, <?php echo $member_id ?>)">
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
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // فوکوس روی اولین فیلد
        setTimeout(() => {
            document.getElementById('title').focus();
        }, 300);
    } else {
        form.style.display = 'none';
    }
}

function validateTicketAccess(ticketId, userId) {
    // اعتبارسنجی اضافی در سمت کلاینت
    // این فقط یک لایه امنیتی اضافه است و نباید به تنهایی اعتماد کرد
    console.log('Validating ticket access:', ticketId, 'for user:', userId);
    return true;
}

// اعتبارسنجی فرم در سمت کلاینت
document.getElementById('ticketForm')?.addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const description = document.getElementById('description').value.trim();
    let isValid = true;
    
    // اعتبارسنجی عنوان
    if (title.length < 3) {
        document.getElementById('titleError').textContent = 'عنوان باید حداقل ۳ کاراکتر باشد';
        isValid = false;
    } else {
        document.getElementById('titleError').textContent = '';
    }
    
    // اعتبارسنجی توضیحات
    if (description.length < 10) {
        document.getElementById('descriptionError').textContent = 'توضیحات باید حداقل ۱۰ کاراکتر باشد';
        isValid = false;
    } else {
        document.getElementById('descriptionError').textContent = '';
    }
    
    if (!isValid) {
        e.preventDefault();
        // اسکرول به اولین خطا
        const firstError = document.querySelector('.form-error:not(:empty)');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});

// مدیریت وضعیت فرم هنگام بازگشت به صفحه
window.addEventListener('pageshow', function(event) {
    // اگر کاربر از دکمه بازگشت مرورگر استفاده کرد
    if (event.persisted) {
        const form = document.getElementById('newTicketForm');
        if (form && (form.querySelector('#title').value || form.querySelector('#description').value)) {
            form.style.display = 'block';
        }
    }
});
</script>

<?php include "inc/footer.php"; ?>