<?php
/**
 * فرم امانت دادن دستی کتاب
 * این فایل توسط admin/add_reservation.php فراخوانی می‌شود
 */

// بررسی دسترسی مستقیم
if (!defined('BASEPATH') && !isset($_SESSION)) {
    die('دسترسی مستقیم مجاز نیست');
}

require_once __DIR__ . '/functions.php';

// متغیرهای پیش‌فرض
$error_message = '';
$success_message = '';
$selected_book = null;
$selected_member = null;

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // بررسی CSRF Token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'توکن امنیتی نامعتبر است. لطفاً صفحه را رفرش کنید.';
    } else {
        
        // دریافت و پاکسازی ورودی‌ها
        $member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
        $book_id = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
        $due_days = filter_input(INPUT_POST, 'due_days', FILTER_VALIDATE_INT) ?: 14;
        $notes = trim($_POST['notes'] ?? '');
        
        // اعتبارسنجی
        $errors = [];
        
        if (!$member_id || $member_id < 1) {
            $errors[] = 'لطفاً یک عضو را انتخاب کنید';
        }
        
        if (!$book_id || $book_id < 1) {
            $errors[] = 'لطفاً یک کتاب را انتخاب کنید';
        }
        
        if ($due_days < 1 || $due_days > 60) {
            $errors[] = 'مدت امانت باید بین ۱ تا ۶۰ روز باشد';
        }
        
        if (strlen($notes) > 500) {
            $errors[] = 'توضیحات نباید بیشتر از ۵۰۰ کاراکتر باشد';
        }
        
        if (empty($errors)) {
            try {
                global $conn;
                
                // بررسی وجود عضو
                $stmt = $conn->prepare("SELECT id, name, email, status FROM members WHERE id = ?");
                $stmt->execute([$member_id]);
                $member = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$member) {
                    throw new Exception('عضو مورد نظر یافت نشد');
                }
                
                if ($member['status'] !== 'active') {
                    throw new Exception('حساب کاربری این عضو فعال نیست');
                }
                
                // بررسی وجود کتاب و موجودی
                $stmt = $conn->prepare("SELECT bid, book_name, quantity, available_quantity FROM books WHERE bid = ?");
                $stmt->execute([$book_id]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$book) {
                    throw new Exception('کتاب مورد نظر یافت نشد');
                }
                
                if ($book['available_quantity'] < 1) {
                    throw new Exception('موجودی این کتاب به پایان رسیده است');
                }
                
                // بررسی عدم وجود امانت تکراری
                $stmt = $conn->prepare("
                    SELECT id FROM reservations 
                    WHERE member_id = ? AND book_id = ? AND status = 'borrowed'
                ");
                $stmt->execute([$member_id, $book_id]);
                
                if ($stmt->fetch()) {
                    throw new Exception('این عضو قبلاً این کتاب را امانت گرفته و هنوز برنگردانده است');
                }
                
                // بررسی سقف امانت (مثلاً حداکثر ۵ کتاب)
                $stmt = $conn->prepare("
                    SELECT COUNT(*) FROM reservations 
                    WHERE member_id = ? AND status = 'borrowed'
                ");
                $stmt->execute([$member_id]);
                $borrowed_count = $stmt->fetchColumn();
                
                $max_borrowed = 5; // قابل تنظیم
                if ($borrowed_count >= $max_borrowed) {
                    throw new Exception("این عضو حداکثر تعداد کتاب مجاز ({$max_borrowed} کتاب) را امانت گرفته است");
                }
                
                // محاسبه تاریخ‌ها
                $borrow_date = date('Y-m-d H:i:s');
                $due_date = date('Y-m-d H:i:s', strtotime("+{$due_days} days"));
                
                // شروع تراکنش
                $conn->beginTransaction();
                
                try {
                    // ثبت امانت
                    $stmt = $conn->prepare("
                        INSERT INTO reservations (
                            member_id, 
                            book_id, 
                            borrow_date, 
                            due_date, 
                            status, 
                            notes,
                            created_by,
                            created_at
                        ) VALUES (?, ?, ?, ?, 'borrowed', ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $member_id,
                        $book_id,
                        $borrow_date,
                        $due_date,
                        $notes,
                        $_SESSION['userid']
                    ]);
                    
                    $reservation_id = $conn->lastInsertId();
                    
                    // کاهش موجودی کتاب
                    $stmt = $conn->prepare("
                        UPDATE books 
                        SET available_quantity = available_quantity - 1 
                        WHERE bid = ? AND available_quantity > 0
                    ");
                    $stmt->execute([$book_id]);
                    
                    if ($stmt->rowCount() === 0) {
                        throw new Exception('خطا در به‌روزرسانی موجودی کتاب');
                    }
                    
                    // ثبت لاگ
                    log_activity('reservation_created', [
                        'reservation_id' => $reservation_id,
                        'member_id' => $member_id,
                        'book_id' => $book_id,
                        'due_date' => $due_date
                    ]);
                    
                    $conn->commit();
                    
                    $success_message = sprintf(
                        'کتاب «%s» با موفقیت به «%s» امانت داده شد. تاریخ بازگشت: %s',
                        htmlspecialchars($book['book_name'], ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8'),
                        jdate('Y/m/d', strtotime($due_date))
                    );
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    throw $e;
                }
                
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                log_error('add_reservation_error', $e->getMessage());
            }
        } else {
            $error_message = implode('<br>', array_map('htmlspecialchars', $errors));
        }
    }
}

// دریافت لیست اعضا
$members = [];
try {
    $stmt = $conn->query("SELECT id, name, email, national_code FROM members WHERE status = 'active' ORDER BY name");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    log_error('get_members_error', $e->getMessage());
}

// دریافت لیست کتاب‌های موجود
$books = [];
try {
    $stmt = $conn->query("
        SELECT bid, book_name, author, isbn, available_quantity 
        FROM books 
        WHERE available_quantity > 0 
        ORDER BY book_name
    ");
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    log_error('get_books_error', $e->getMessage());
}
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-plus-circle me-2"></i>
                        امانت دادن دستی کتاب
                    </h5>
                </div>
                <div class="card-body">
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="reservationForm">
                        <?php echo csrf_field(); ?>
                        
                        <!-- انتخاب عضو -->
                        <div class="mb-4">
                            <label for="member_id" class="form-label">
                                <i class="fas fa-user me-1"></i>
                                انتخاب عضو <span class="text-danger">*</span>
                            </label>
                            <select name="member_id" id="member_id" class="form-select select2" required>
                                <option value="">-- عضو را انتخاب کنید --</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?php echo (int)$m['id']; ?>">
                                        <?php echo htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        (<?php echo htmlspecialchars($m['national_code'], ENT_QUOTES, 'UTF-8'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- انتخاب کتاب -->
                        <div class="mb-4">
                            <label for="book_id" class="form-label">
                                <i class="fas fa-book me-1"></i>
                                انتخاب کتاب <span class="text-danger">*</span>
                            </label>
                            <select name="book_id" id="book_id" class="form-select select2" required>
                                <option value="">-- کتاب را انتخاب کنید --</option>
                                <?php foreach ($books as $b): ?>
                                    <option value="<?php echo (int)$b['bid']; ?>">
                                        <?php echo htmlspecialchars($b['book_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        - <?php echo htmlspecialchars($b['author'], ENT_QUOTES, 'UTF-8'); ?>
                                        (موجودی: <?php echo (int)$b['available_quantity']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- مدت امانت -->
                        <div class="mb-4">
                            <label for="due_days" class="form-label">
                                <i class="fas fa-calendar-alt me-1"></i>
                                مدت امانت (روز)
                            </label>
                            <input type="number" 
                                   name="due_days" 
                                   id="due_days" 
                                   class="form-control" 
                                   value="14" 
                                   min="1" 
                                   max="60">
                            <small class="text-muted">پیش‌فرض: ۱۴ روز | حداکثر: ۶۰ روز</small>
                        </div>
                        
                        <!-- توضیحات -->
                        <div class="mb-4">
                            <label for="notes" class="form-label">
                                <i class="fas fa-sticky-note me-1"></i>
                                توضیحات (اختیاری)
                            </label>
                            <textarea name="notes" 
                                      id="notes" 
                                      class="form-control" 
                                      rows="3" 
                                      maxlength="500"
                                      placeholder="توضیحات اضافی..."></textarea>
                        </div>
                        
                        <!-- دکمه‌ها -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                ثبت امانت
                            </button>
                            <a href="reservations.php" class="btn btn-secondary">
                                <i class="fas fa-list me-1"></i>
                                لیست امانت‌ها
                            </a>
                        </div>
                    </form>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Select2 for better dropdowns -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({
        dir: 'rtl',
        language: {
            noResults: function() {
                return 'نتیجه‌ای یافت نشد';
            },
            searching: function() {
                return 'در حال جستجو...';
            }
        }
    });
});
</script>
