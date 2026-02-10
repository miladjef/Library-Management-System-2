<?php
require_once __DIR__ . '/includes/security.php';

// login.php
require_once 'inc/config.php';
require_once 'classes/Auth.php';

$db = Database::getInstance();
$auth = new Auth($db);

// اگر کاربر لاگین است، ریدایرکت به پروفایل
if (isset($_SESSION['userid']) && $_SESSION['userid']) {
    header('Location: profile.php');
    exit;
}

$title = 'ورود به سیستم';

// تنظیمات Rate Limiting
$max_attempts = 5; // حداکثر تعداد تلاش
$lockout_time = 900; // 15 دقیقه قفل شدن (به ثانیه)
$ip = $_SERVER['REMOTE_ADDR'];
$current_time = time();

// بررسی Rate Limiting
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
}

// پاک کردن تلاش‌های قدیمی
foreach ($_SESSION['login_attempts'] as $key => $attempt) {
    if ($current_time - $attempt['time'] > $lockout_time) {
        unset($_SESSION['login_attempts'][$key]);
    }
}

// شمارش تلاش‌های فعلی برای این IP
$attempt_count = 0;
foreach ($_SESSION['login_attempts'] as $attempt) {
    if ($attempt['ip'] === $ip) {
        $attempt_count++;
    }
}

// پردازش ورود
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    // بررسی Rate Limiting
    if ($attempt_count >= $max_attempts) {
        $error = 'تعداد تلاش‌های شما بیش از حد مجاز است. لطفاً ۱۵ دقیقه دیگر دوباره تلاش کنید.';
    } elseif (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'خطای امنیتی. لطفا دوباره تلاش کنید';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

        // اعتبارسنجی اولیه
        if (empty($username) || empty($password)) {
            $error = 'لطفا نام کاربری و رمز عبور را وارد کنید';
        } elseif (strlen($username) > 100 || strlen($password) > 255) {
            $error = 'مقادیر وارد شده معتبر نیستند';
        } else {
            $result = $auth->login($username, $password, $remember);

            if ($result['success']) {
                // بازتولید Session ID برای جلوگیری از Session Fixation
                session_regenerate_id(true);
                
                // تنظیم زمان انقضای نشست
                if ($remember) {
                    // نشست طولانی مدت: 30 روز
                    ini_set('session.gc_maxlifetime', 2592000);
                    session_set_cookie_params(2592000);
                } else {
                    // نشست عادی: 1 ساعت
                    ini_set('session.gc_maxlifetime', 3600);
                    session_set_cookie_params(3600);
                }

                // ثبت لاگ ورود
                $log_data = [
                    'mid' => $result['user_id'],
                    'action_type' => 'login',
                    'description' => 'ورود موفق به سیستم',
                    'ip_address' => $ip,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ];
                $auth->logActivity($log_data);

                // پاک کردن تلاش‌های ناموفق این IP
                foreach ($_SESSION['login_attempts'] as $key => $attempt) {
                    if ($attempt['ip'] === $ip) {
                        unset($_SESSION['login_attempts'][$key]);
                    }
                }

                // تولید CSRF token جدید
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // ریدایرکت بر اساس نقش
                if ($result['role'] == 2) {
                    header('Location: admin/index.php');
                } else {
                    $redirect = isset($_GET['redirect']) ? filter_var($_GET['redirect'], FILTER_SANITIZE_URL) : 'profile.php';
                    // بررسی مجوز برای جلوگیری از Open Redirect
                    $allowed_redirects = ['profile.php', 'dashboard.php', 'index.php'];
                    if (!in_array($redirect, $allowed_redirects)) {
                        $redirect = 'profile.php';
                    }
                    header('Location: ' . $redirect);
                }
                exit;
            } else {
                $error = $result['message'];

                // ثبت تلاش ناموفق
                $auth->logFailedLogin($username, $ip);
                
                // افزودن به لیست تلاش‌های ناموفق
                $_SESSION['login_attempts'][] = [
                    'ip' => $ip,
                    'time' => $current_time,
                    'username' => $username
                ];
                
                // تاخیر برای جلوگیری از Timing Attack
                usleep(rand(100000, 300000)); // تاخیر 100-300 میلی‌ثانیه
            }
        }
    }
}

// تولید CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// تنظیم هدرهای امنیتی
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

include "inc/header.php";
?>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <!-- Header -->
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-book-reader"></i>
                </div>
                <h1 class="auth-title">ورود به کتابخانه</h1>
                <p class="auth-subtitle">به کتابخانه مجازی خوش آمدید</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <!-- نمایش وضعیت Rate Limiting -->
            <?php if ($attempt_count > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-shield-alt"></i>
                    تلاش‌های ناموفق: <?php echo $attempt_count ?> از <?php echo $max_attempts ?>
                    <?php if ($attempt_count >= $max_attempts): ?>
                        <br><small>دسترسی تا ۱۵ دقیقه دیگر مسدود است</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- فرم ورود -->
            <form method="POST" action="" class="auth-form" id="loginForm" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i>
                        نام کاربری یا ایمیل
                    </label>
                    <input type="text"
                           name="username"
                           id="username"
                           class="form-control"
                           placeholder="نام کاربری یا ایمیل خود را وارد کنید"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : '' ?>"
                           required
                           autofocus
                           maxlength="100"
                           <?php echo $attempt_count >= $max_attempts ? 'disabled' : '' ?>>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i>
                        رمز عبور
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password"
                               name="password"
                               id="password"
                               class="form-control"
                               placeholder="رمز عبور خود را وارد کنید"
                               required
                               maxlength="255"
                               autocomplete="current-password"
                               <?php echo $attempt_count >= $max_attempts ? 'disabled' : '' ?>>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1" <?php echo $attempt_count >= $max_attempts ? 'disabled' : '' ?>>
                        <span>مرا به خاطر بسپار</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">
                        فراموشی رمز عبور؟
                    </a>
                </div>

                <button type="submit" 
                        name="login" 
                        class="btn btn-primary btn-block"
                        <?php echo $attempt_count >= $max_attempts ? 'disabled' : '' ?>>
                    <i class="fas fa-sign-in-alt"></i>
                    <?php echo $attempt_count >= $max_attempts ? 'دسترسی موقتاً مسدود است' : 'ورود به سیستم' ?>
                </button>
            </form>

            <!-- خط جداکننده -->
            <div class="auth-divider">
                <span>یا</span>
            </div>

            <!-- لینک ثبت نام -->
            <div class="auth-footer">
                <p>حساب کاربری ندارید؟</p>
                <a href="register.php" class="btn btn-outline btn-block">
                    <i class="fas fa-user-plus"></i>
                    ثبت نام کنید
                </a>
            </div>

            <!-- بازگشت به خانه -->
            <div class="auth-back">
                <a href="<?php echo siteurl() ?>">
                    <i class="fas fa-arrow-right"></i>
                    بازگشت به صفحه اصلی
                </a>
            </div>
        </div>

        <!-- بخش تصویری -->
        <div class="auth-visual">
            <div class="visual-content">
                <img src="assets/img/login-illustration.svg" alt="Login">
                <h2>دسترسی به هزاران کتاب</h2>
                <p>با ورود به سیستم می‌توانید کتاب‌های مورد علاقه خود را امانت بگیرید</p>

                <div class="features-list">
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>دسترسی به کتابخانه جامع</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>امانت آنلاین کتاب</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>پشتیبانی ۲۴ ساعته</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تاخیر برای جلوگیری از تشخیص فرم خالی
let formSubmitted = false;

// Toggle Password Visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(inputId + '-icon');

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Form Validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    if (formSubmitted) {
        e.preventDefault();
        return false;
    }
    
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;

    if (!username || !password) {
        e.preventDefault();
        alert('لطفا تمام فیلدها را پر کنید');
        return false;
    }
    
    // جلوگیری از ارسال چندگانه
    formSubmitted = true;
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال بررسی...';
});

// جلوگیری از ارسال فرم با کلید Enter چند بار
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && formSubmitted) {
        e.preventDefault();
    }
});
</script>

<?php include "inc/footer.php"; ?>