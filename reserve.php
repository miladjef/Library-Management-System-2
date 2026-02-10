<?php
require_once __DIR__ . '/includes/security.php';

// reserve.php
if (!$user_logged_in) {
    header('Location: login.php');
    exit;
}

$book_id = $_GET['book_id'] ?? 0;
if (!$book_id) {
    header('Location: books.php');
    exit;
}

require_once 'classes/Book.php';
require_once 'classes/Reservation.php';

$db = Database::getInstance();
$book = new Book($db);
$reservation = new Reservation($db);

// دریافت اطلاعات کتاب
$book_info = $book->getById($book_id);
if (!$book_info) {
    header('Location: books.php');
    exit;
}

// بررسی موجودی
$available_count = $book->getAvailableCount($book_id);
if ($available_count <= 0) {
    $_SESSION['error'] = 'متأسفیم، این کتاب در حال حاضر موجود نیست';
    header("Location: book.php?id=$book_id");
    exit;
}

// بررسی وضعیت کاربر
$member_info = $reservation->checkMemberEligibility($_SESSION['userid']);
if (!$member_info['can_borrow']) {
    $_SESSION['error'] = $member_info['message'];
    header("Location: book.php?id=$book_id");
    exit;
}

$title = 'رزرو کتاب';
include "inc/header.php";

// پردازش فرم رزرو
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_reservation'])) {
    // اعتبارسنجی CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'خطای امنیتی. لطفا مجددا تلاش کنید';
    } else {
        $duration_days = (int)$_POST['duration_days'];
        $notes = trim($_POST['notes'] ?? '');

        // اعتبارسنجی مدت امانت
        if ($duration_days < 1 || $duration_days > 30) {
            $error = 'مدت امانت باید بین 1 تا 30 روز باشد';
        } else {
            $result = $reservation->create([
                'mid' => $_SESSION['userid'],
                'bid' => $book_id,
                'duration_days' => $duration_days,
                'notes' => $notes
            ]);

            if ($result['success']) {
                $_SESSION['success'] = 'رزرو شما با موفقیت ثبت شد. کد رزرو: ' . $result['reservation_id'];
                header("Location: my-reservations.php");
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

// تولید CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// دریافت تنظیمات سیستم
$settings_query = $conn->prepare("
    SELECT setting_key, setting_value
    FROM system_settings
    WHERE setting_key IN ('max_borrow_days', 'daily_penalty_amount')
");
$settings_query->execute();
$settings = $settings_query->fetchAll(PDO::FETCH_KEY_PAIR);

$max_days = $settings['max_borrow_days'] ?? 14;
$daily_penalty = $settings['daily_penalty_amount'] ?? 5000;
?>

<div class="container">
    <div class="reservation-page">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="<?php echo  siteurl() ?>">خانه</a>
            <i class="fas fa-chevron-left"></i>
            <a href="books.php">کتاب‌ها</a>
            <i class="fas fa-chevron-left"></i>
            <a href="book.php?id=<?php echo  $book_id ?>"><?php echo  htmlspecialchars($book_info['book_name']) ?></a>
            <i class="fas fa-chevron-left"></i>
            <span>رزرو کتاب</span>
        </nav>

        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-bookmark"></i>
                رزرو کتاب
            </h1>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo  htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="reservation-content">
            <!-- اطلاعات کتاب -->
            <div class="book-summary">
                <div class="book-summary-image">
                    <img src="<?php echo  IMG_PATH . $book_info['image'] ?>"
                         alt="<?php echo  htmlspecialchars($book_info['book_name']) ?>"
                         onerror="this.src='assets/img/no-image.jpg'">
                </div>
                <div class="book-summary-info">
                    <h3><?php echo  htmlspecialchars($book_info['book_name']) ?></h3>
                    <p class="author">
                        <i class="fas fa-user"></i>
                        <?php echo  htmlspecialchars($book_info['author']) ?>
                    </p>
                    <p class="category">
                        <i class="fas fa-tag"></i>
                        <?php echo  htmlspecialchars($book_info['category_name']) ?>
                    </p>
                    <p class="availability">
                        <i class="fas fa-check-circle"></i>
                        <?php echo  $available_count ?> نسخه موجود
                    </p>
                </div>
            </div>

            <!-- فرم رزرو -->
            <div class="reservation-form">
                <h3>
                    <i class="fas fa-calendar-alt"></i>
                    اطلاعات رزرو
                </h3>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo  $_SESSION['csrf_token'] ?>">

                    <div class="form-group">
                        <label for="duration_days">
                            <i class="fas fa-clock"></i>
                            مدت امانت (روز):
                        </label>
                        <select name="duration_days" id="duration_days" class="form-control" required>
                            <option value="">انتخاب کنید</option>
                            <?php for ($i = 1; $i <= $max_days; $i++): ?>
                                <option value="<?php echo  $i ?>" <?php echo  $i == 14 ? 'selected' : '' ?>>
                                    <?php echo  $i ?> روز
                                    <?php if ($i == 7): ?>
                                        (1 هفته)
                                    <?php elseif ($i == 14): ?>
                                        (2 هفته - پیشنهادی)
                                    <?php elseif ($i == 30): ?>
                                        (1 ماه)
                                    <?php endif; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <small class="form-help">
                            حداکثر مدت امانت <?php echo  $max_days ?> روز می‌باشد
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="notes">
                            <i class="fas fa-sticky-note"></i>
                            یادداشت (اختیاری):
                        </label>
                        <textarea name="notes" id="notes" class="form-control" rows="3"
                                  placeholder="یادداشت یا توضیحات اضافی در مورد این رزرو..."></textarea>
                    </div>

                    <!-- خلاصه رزرو -->
                    <div class="reservation-summary">
                        <h4>
                            <i class="fas fa-list"></i>
                            خلاصه رزرو
                        </h4>
                        <div class="summary-item">
                            <span>تاریخ رزرو:</span>
                            <span><?php echo  jdate('Y/m/d') ?></span>
                        </div>
                        <div class="summary-item">
                            <span>تاریخ بازگشت:</span>
                            <span id="return_date">-</span>
                        </div>
                        <div class="summary-item">
                            <span>جریمه روزانه تأخیر:</span>
                            <span><?php echo  number_format($daily_penalty) ?> تومان</span>
                        </div>
                        <div class="summary-item important">
                            <span>نکته مهم:</span>
                            <span>در صورت عدم بازگردانی کتاب در موعد مقرر، روزانه <?php echo  number_format($daily_penalty) ?> تومان جریمه محاسبه خواهد شد</span>
                        </div>
                    </div>

                    <!-- قوانین -->
                    <div class="reservation-rules">
                        <h4>
                            <i class="fas fa-gavel"></i>
                            قوانین امانت
                        </h4>
                        <ul>
                            <li>هر عضو حداکثر می‌تواند <?php echo  $member_info['max_active'] ?> کتاب به صورت همزمان امانت بگیرد</li>
                            <li>امکان تمدید امانت تا <?php echo  $settings['max_extensions'] ?? 2 ?> بار وجود دارد</li>
                            <li>در صورت وجود جریمه پرداخت نشده، امکان امانت جدید وجود ندارد</li>
                            <li>کتاب‌ها باید در وضعیت سالم بازگردانده شوند</li>
                            <li>در صورت آسیب یا گم کردن کتاب، هزینه جایگزینی محاسبه می‌شود</li>
                        </ul>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="submit_reservation" class="btn btn-primary btn-large">
                            <i class="fas fa-check"></i>
                            تأیید و ثبت رزرو
                        </button>
                        <a href="book.php?id=<?php echo  $book_id ?>" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            انصراف
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // محاسبه تاریخ بازگشت
    $('#duration_days').change(function() {
        const days = parseInt($(this).val());
        if (days) {
            const today = new Date();
            const returnDate = new Date(today);
            returnDate.setDate(today.getDate() + days);

            // تبدیل به تاریخ شمسی (فرض می‌کنیم تابع jDate در دسترس است)
            const jalaliDate = toJalali(returnDate);
            $('#return_date').text(jalaliDate);
        } else {
            $('#return_date').text('-');
        }
    });

    // تریگر کردن تغییر برای مقدار پیش‌فرض
    $('#duration_days').trigger('change');
});

// تابع ساده تبدیل تاریخ (باید با کتابخانه تاریخ شمسی جایگزین شود)
function toJalali(date) {
    // پیاده‌سازی ساده - باید با کتابخانه تاریخ شمسی جایگزین شود
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}/${month}/${day}`;
}
</script>

<?php include "inc/footer.php"; ?>
