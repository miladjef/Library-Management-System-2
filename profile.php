<?php
// profile.php
if (!$user_logged_in) {
    header('Location: login.php');
    exit;
}

require_once 'classes/Member.php';
require_once 'classes/Reservation.php';

$db = Database::getInstance();
$member = new Member($db);
$reservation = new Reservation($db);

$member_id = $_SESSION['userid'];
$user_info = $member->getById($member_id);

if (!$user_info) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$title = 'پروفایل من';
include "inc/header.php";

// پردازش ویرایش پروفایل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'خطای امنیتی';
    } else {
        $data = [
            'name' => trim($_POST['name']),
            'surname' => trim($_POST['surname']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phone']),
            'address' => trim($_POST['address'])
        ];

        $result = $member->update($member_id, $data);

        if ($result['success']) {
            $success = 'اطلاعات با موفقیت به‌روزرسانی شد';
            $user_info = $member->getById($member_id); // بارگذاری مجدد اطلاعات
        } else {
            $error = $result['message'];
        }
    }
}

// پردازش تغییر رمز عبور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $password_error = 'خطای امنیتی';
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $password_error = 'رمز عبور جدید و تکرار آن مطابقت ندارند';
        } elseif (strlen($new_password) < 6) {
            $password_error = 'رمز عبور باید حداقل 6 کاراکتر باشد';
        } else {
            // بررسی رمز عبور فعلی
            if (!password_verify($current_password, $user_info['password'])) {
                $password_error = 'رمز عبور فعلی صحیح نیست';
            } else {
                $result = $member->changePassword($member_id, $new_password);

                if ($result['success']) {
                    $password_success = 'رمز عبور با موفقیت تغییر یافت';
                } else {
                    $password_error = $result['message'];
                }
            }
        }
    }
}

// تولید CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// دریافت آمار کاربر
$stats_query = $conn->prepare("
    SELECT
        COUNT(*) as total_reservations,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_reservations,
        COUNT(CASE WHEN status = 'returned' THEN 1 END) as returned_reservations,
        COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_reservations
    FROM reservations
    WHERE mid = ?
");
$stats_query->execute([$member_id]);
$user_stats = $stats_query->fetch(PDO::FETCH_ASSOC);

// محاسبه جریمه‌های پرداخت نشده
$penalty_query = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_penalty
    FROM penalties
    WHERE mid = ? AND status = 'unpaid'
");
$penalty_query->execute([$member_id]);
$total_penalty = $penalty_query->fetchColumn();

// دریافت آخرین فعالیت‌ها
$activity_query = $conn->prepare("
    SELECT * FROM member_activity_log
    WHERE mid = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$activity_query->execute([$member_id]);
$recent_activities = $activity_query->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="profile-page">
        <!-- Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="profile-info">
                <h1><?php echo  htmlspecialchars($user_info['name'] . ' ' . $user_info['surname']) ?></h1>
                <p class="username">@<?php echo  htmlspecialchars($user_info['username']) ?></p>
                <div class="member-badges">
                    <?php if ($user_info['status'] == 'active'): ?>
                        <span class="badge badge-success">
                            <i class="fas fa-check-circle"></i>
                            عضو فعال
                        </span>
                    <?php else: ?>
                        <span class="badge badge-danger">
                            <i class="fas fa-times-circle"></i>
                            غیرفعال
                        </span>
                    <?php endif; ?>

                    <span class="badge badge-info">
                        <i class="fas fa-calendar"></i>
                        عضو از: <?php echo  jdate('Y/m/d', strtotime($user_info['created_at'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- آمار سریع -->
        <div class="profile-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo  $user_stats['total_reservations'] ?></div>
                    <div class="stat-label">کل امانت‌ها</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon active">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo  $user_stats['active_reservations'] ?></div>
                    <div class="stat-label">امانت فعال</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo  $user_stats['returned_reservations'] ?></div>
                    <div class="stat-label">بازگشت داده شده</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo  number_format($total_penalty) ?></div>
                    <div class="stat-label">جریمه (تومان)</div>
                </div>
            </div>
        </div>

        <!-- تب‌ها -->
        <div class="profile-tabs">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="info">
                    <i class="fas fa-user"></i>
                    اطلاعات شخصی
                </button>
                <button class="tab-btn" data-tab="security">
                    <i class="fas fa-lock"></i>
                    امنیت
                </button>
                <button class="tab-btn" data-tab="activity">
                    <i class="fas fa-history"></i>
                    فعالیت‌های اخیر
                </button>
            </div>

            <!-- محتوای تب اطلاعات شخصی -->
            <div class="tab-content active" id="tab-info">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo  htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo  htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="profile-form">
                    <input type="hidden" name="csrf_token" value="<?php echo  $_SESSION['csrf_token'] ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">
                                <i class="fas fa-user"></i>
                                نام:
                            </label>
                            <input type="text" name="name" id="name" class="form-control"
                                   value="<?php echo  htmlspecialchars($user_info['name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="surname">
                                <i class="fas fa-user"></i>
                                نام خانوادگی:
                            </label>
                            <input type="text" name="surname" id="surname" class="form-control"
                                   value="<?php echo  htmlspecialchars($user_info['surname']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-at"></i>
                            نام کاربری:
                        </label>
                        <input type="text" name="username" id="username" class="form-control"
                               value="<?php echo  htmlspecialchars($user_info['username']) ?>" disabled>
                        <small class="form-help">نام کاربری قابل تغییر نیست</small>
                    </div>

                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            ایمیل:
                        </label>
                        <input type="email" name="email" id="email" class="form-control"
                               value="<?php echo  htmlspecialchars($user_info['email'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone">
                            <i class="fas fa-phone"></i>
                            شماره تماس:
                        </label>
                        <input type="tel" name="phone" id="phone" class="form-control"
                               value="<?php echo  htmlspecialchars($user_info['phone'] ?? '') ?>"
                               placeholder="09123456789">
                    </div>

                    <div class="form-group">
                        <label for="address">
                            <i class="fas fa-map-marker-alt"></i>
                            آدرس:
                        </label>
                        <textarea name="address" id="address" class="form-control" rows="3"><?php echo  htmlspecialchars($user_info['address'] ?? '') ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            ذخیره تغییرات
                        </button>
                    </div>
                </form>
            </div>

            <!-- محتوای تب امنیت -->
            <div class="tab-content" id="tab-security">
                <?php if (isset($password_success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo  htmlspecialchars($password_success) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($password_error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo  htmlspecialchars($password_error) ?>
                    </div>
                <?php endif; ?>

                <div class="security-section">
                    <h3>
                        <i class="fas fa-key"></i>
                        تغییر رمز عبور
                    </h3>

                    <form method="POST" action="" class="password-form">
                        <input type="hidden" name="csrf_token" value="<?php echo  $_SESSION['csrf_token'] ?>">

                        <div class="form-group">
                            <label for="current_password">
                                <i class="fas fa-lock"></i>
                                رمز عبور فعلی:
                            </label>
                            <input type="password" name="current_password" id="current_password"
                                   class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">
                                <i class="fas fa-lock"></i>
                                رمز عبور جدید:
                            </label>
                            <input type="password" name="new_password" id="new_password"
                                   class="form-control" minlength="6" required>
                            <small class="form-help">حداقل 6 کاراکتر</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-lock"></i>
                                تکرار رمز عبور جدید:
                            </label>
                            <input type="password" name="confirm_password" id="confirm_password"
                                   class="form-control" minlength="6" required>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key"></i>
                                تغییر رمز عبور
                            </button>
                        </div>
                    </form>
                </div>

                <div class="security-tips">
                    <h4>
                        <i class="fas fa-shield-alt"></i>
                        نکات امنیتی
                    </h4>
                    <ul>
                        <li>از رمز عبور قوی و پیچیده استفاده کنید</li>
                        <li>رمز عبور خود را با دیگران به اشتراک نگذارید</li>
                        <li>رمز عبور را به صورت دوره‌ای تغییر دهید</li>
                        <li>از استفاده مجدد رمز عبور در سایت‌های مختلف خودداری کنید</li>
                    </ul>
                </div>
            </div>

            <!-- محتوای تب فعالیت‌ها -->
            <div class="tab-content" id="tab-activity">
                <h3>
                    <i class="fas fa-history"></i>
                    فعالیت‌های اخیر
                </h3>

                <div class="activity-list">
                    <?php if (empty($recent_activities)): ?>
                        <div class="no-activity">
                            <i class="fas fa-info-circle"></i>
                            <p>هیچ فعالیتی ثبت نشده است</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php
                                    $icon_map = [
                                        'login' => 'fa-sign-in-alt',
                                        'logout' => 'fa-sign-out-alt',
                                        'reservation' => 'fa-bookmark',
                                        'return' => 'fa-undo',
                                        'profile_update' => 'fa-user-edit',
                                        'password_change' => 'fa-key'
                                    ];
                                    $icon = $icon_map[$activity['action_type']] ?? 'fa-info-circle';
                                    ?>
                                    <i class="fas <?php echo  $icon ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-description">
                                        <?php echo  htmlspecialchars($activity['description']) ?>
                                    </div>
                                    <div class="activity-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo  jdate('Y/m/d H:i', strtotime($activity['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // مدیریت تب‌ها
    $('.tab-btn').click(function() {
        const tabId = $(this).data('tab');

        // حذف کلاس active از همه
        $('.tab-btn').removeClass('active');
        $('.tab-content').removeClass('active');

        // افزودن کلاس active به تب انتخاب شده
        $(this).addClass('active');
        $('#tab-' + tabId).addClass('active');
    });

    // اعتبارسنجی فرم رمز عبور
    $('.password-form').submit(function(e) {
        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();

        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('رمز عبور جدید و تکرار آن مطابقت ندارند');
            return false;
        }
    });
});
</script>

<?php include "inc/footer.php"; ?>
