<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Reservation.php';
require_once '../classes/Book.php';
require_once '../classes/Member.php';
require_once '../classes/Validator.php';
require_once '../classes/CSRF.php';

$errors = [];
$success = false;

// پردازش ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reservation'])) {

    // بررسی CSRF
    if (!CSRF::validate($_POST['csrf_token'] ?? '', 'add_reservation')) {
        $errors[] = 'توکن امنیتی نامعتبر است';
    } else {

        $data = [
            'mid' => intval($_POST['mid'] ?? 0),
            'bid' => intval($_POST['bid'] ?? 0),
            'issue_date' => trim($_POST['issue_date'] ?? ''),
            'due_date' => trim($_POST['due_date'] ?? ''),
            'notes' => trim($_POST['notes'] ?? '')
        ];

        // قوانین اعتبارسنجی
        $rules = [
            'mid' => 'required|numeric|min:1',
            'bid' => 'required|numeric|min:1',
            'issue_date' => 'required|jalali_date',
            'due_date' => 'required|jalali_date',
            'notes' => 'max:500'
        ];

        $validator = new Validator($data, $rules);

        if (!$validator->validate()) {
            $errors = $validator->getErrors();
        } else {
            try {
                $reservation = new Reservation();

                // بررسی موجودی کتاب
                $book = new Book();
                $bookData = $book->getById($data['bid']);

                if (!$bookData) {
                    $errors[] = 'کتاب مورد نظر یافت نشد';
                } elseif ($bookData['available_quantity'] < 1) {
                    $errors[] = 'این کتاب در حال حاضر موجود نیست';
                } else {
                    // بررسی محدودیت تعداد امانت فعال عضو
                    $member = new Member();
                    $memberData = $member->getById($data['mid']);

                    if (!$memberData) {
                        $errors[] = 'عضو مورد نظر یافت نشد';
                    } elseif ($memberData['active_reservations'] >= MAX_ACTIVE_RESERVATIONS) {
                        $errors[] = 'این عضو به حداکثر تعداد امانت فعال رسیده است';
                    } else {
                        // ثبت امانت
                        $reservationId = $reservation->create($data);

                        if ($reservationId) {
                            $_SESSION['reservation_add_success'] = true;
                            secureRedirect('reservations.php?add=ok');
                        } else {
                            $errors[] = 'خطا در ثبت امانت';
                        }
                    }
                }
            } catch (Exception $e) {
                logError('Add Reservation Error: ' . $e->getMessage());
                $errors[] = 'خطای سیستمی رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

// دریافت لیست اعضا و کتاب‌های موجود
try {
    $member = new Member();
    $book = new Book();

    $members = $member->getActive();
    $availableBooks = $book->getAvailable();

} catch (Exception $e) {
    logError('Get Data Error: ' . $e->getMessage());
    $members = [];
    $availableBooks = [];
}

// تولید CSRF Token
$csrfToken = CSRF::generate('add_reservation');

// تاریخ پیش‌فرض (امروز و 2 هفته بعد)
$todayJalali = jdate('Y/m/d');
$dueDateJalali = jdate('Y/m/d', strtotime('+' . DEFAULT_LOAN_DAYS . ' days'));
?>

<div class="main">
    <div class="page-title">
        امانت دادن کتاب (دستی)
    </div>

    <a href="reservations.php">
        <div class="back-button">
            بازگشت به لیست امانت‌ها
        </div>
    </a>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" id="errorAlert">
            <strong>خطاها:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo  htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
            <button onclick="closeAlert('errorAlert')" class="close-btn">&times;</button>
        </div>
    <?php endif; ?>

    <form action="#" method="POST" id="addReservationForm">
        <input type="hidden" name="csrf_token" value="<?php echo  htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <label for="mid">عضو: <span class="required">*</span></label>
        <select name="mid" id="mid" required onchange="loadMemberInfo(this.value)">
            <option value="">انتخاب عضو...</option>
            <?php foreach ($members as $m): ?>
                <option value="<?php echo  $m['mid'] ?>"
                        data-active="<?php echo  $m['active_reservations'] ?? 0 ?>"
                        data-penalty="<?php echo  $m['total_penalty'] ?? 0 ?>">
                    <?php echo  htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?> -
                    کد: <?php echo  htmlspecialchars($m['member_code'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div id="memberInfo" class="member-info"></div>

        <label for="bid">کتاب: <span class="required">*</span></label>
        <select name="bid" id="bid" required onchange="loadBookInfo(this.value)">
            <option value="">انتخاب کتاب...</option>
            <?php foreach ($availableBooks as $b): ?>
                <option value="<?php echo  $b['bid'] ?>"
                        data-available="<?php echo  $b['available_quantity'] ?>">
                    <?php echo  htmlspecialchars($b['book_name'], ENT_QUOTES, 'UTF-8') ?> -
                    نویسنده: <?php echo  htmlspecialchars($b['author'], ENT_QUOTES, 'UTF-8') ?>
                    (موجود: <?php echo  $b['available_quantity'] ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <div id="bookInfo" class="book-info"></div>

        <label for="issue_date">تاریخ امانت: <span class="required">*</span></label>
        <input type="text"
               name="issue_date"
               id="issue_date"
               value="<?php echo  $todayJalali ?>"
               placeholder="1402/07/15"
               required>
        <small class="help-text">فرمت: YYYY/MM/DD (شمسی)</small>

        <label for="due_date">تاریخ بازگشت: <span class="required">*</span></label>
        <input type="text"
               name="due_date"
               id="due_date"
               value="<?php echo  $dueDateJalali ?>"
               placeholder="1402/07/29"
               required>
        <small class="help-text">مهلت پیش‌فرض: <?php echo  DEFAULT_LOAN_DAYS ?> روز</small>

        <label for="notes">یادداشت:</label>
        <textarea name="notes"
                  id="notes"
                  rows="3"
                  maxlength="500"
                  placeholder="توضیحات اضافی (اختیاری)..."></textarea>

        <button class="submit" type="submit" name="add_reservation">ثبت امانت</button>
    </form>
</div>

<script src="assets/js/add_reservation.js"></script>
