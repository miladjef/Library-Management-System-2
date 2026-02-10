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

// پردازش ورود
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'خطای امنیتی. لطفا دوباره تلاش کنید';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

        $result = $auth->login($username, $password, $remember);

        if ($result['success']) {
            // ثبت لاگ ورود
            $log_data = [
                'mid' => $result['user_id'],
                'action_type' => 'login',
                'description' => 'ورود موفق به سیستم',
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ];
            $auth->logActivity($log_data);

            // ریدایرکت بر اساس نقش
            if ($result['role'] == 2) {
                header('Location: admin/index.php');
            } else {
                $redirect = $_GET['redirect'] ?? 'profile.php';
                header('Location: ' . $redirect);
            }
            exit;
        } else {
            $error = $result['message'];

            // ثبت تلاش ناموفق
            $auth->logFailedLogin($username, $_SERVER['REMOTE_ADDR']);
        }
    }
}

// تولید CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
                    <?php echo  htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo  htmlspecialchars($_SESSION['success_message']) ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <!-- فرم ورود -->
            <form method="POST" action="" class="auth-form" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo  $_SESSION['csrf_token'] ?>">

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
                           value="<?php echo  isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                           required
                           autofocus>
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
                               required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        <span>مرا به خاطر بسپار</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">
                        فراموشی رمز عبور؟
                    </a>
                </div>

                <button type="submit" name="login" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i>
                    ورود به سیستم
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
                <a href="<?php echo  siteurl() ?>">
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
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;

    if (!username || !password) {
        e.preventDefault();
        alert('لطفا تمام فیلدها را پر کنید');
        return false;
    }
});
</script>

<?php include "inc/footer.php"; ?>
