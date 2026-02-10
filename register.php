<?php
require_once __DIR__ . '/includes/security.php';

// register.php
require_once 'inc/config.php';
require_once 'classes/Auth.php';
require_once 'classes/Member.php';

$db = Database::getInstance();
$auth = new Auth($db);
$member = new Member($db);

// اگر کاربر لاگین است، ریدایرکت
if (isset($_SESSION['userid']) && $_SESSION['userid']) {
    header('Location: profile.php');
    exit;
}

$title = 'ثبت نام';

// پردازش ثبت نام
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'خطای امنیتی. لطفا دوباره تلاش کنید';
    } else {
        $data = [
            'name' => trim($_POST['name']),
            'surname' => trim($_POST['surname']),
            'username' => trim($_POST['username']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phone']),
            'password' => $_POST['password'],
            'password_confirm' => $_POST['password_confirm'],
            'terms' => isset($_POST['terms'])
        ];

        // اعتبارسنجی
        $validation_errors = [];

        // بررسی نام و نام خانوادگی
        if (empty($data['name']) || mb_strlen($data['name']) < 2) {
            $validation_errors[] = 'نام باید حداقل ۲ کاراکتر باشد';
        }

        if (empty($data['surname']) || mb_strlen($data['surname']) < 2) {
            $validation_errors[] = 'نام خانوادگی باید حداقل ۲ کاراکتر باشد';
        }

        // بررسی نام کاربری
        if (empty($data['username']) || strlen($data['username']) < 3) {
            $validation_errors[] = 'نام کاربری باید حداقل ۳ کاراکتر باشد';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $validation_errors[] = 'نام کاربری فقط می‌تواند شامل حروف انگلیسی، اعداد و آندرلاین باشد';
        } elseif ($member->usernameExists($data['username'])) {
            $validation_errors[] = 'این نام کاربری قبلا استفاده شده است';
        }

        // بررسی ایمیل
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = 'ایمیل معتبر نیست';
        } elseif ($member->emailExists($data['email'])) {
            $validation_errors[] = 'این ایمیل قبلا ثبت شده است';
        }

        // بررسی شماره تلفن
        if (!empty($data['phone']) && !preg_match('/^09\d{9}$/', $data['phone'])) {
            $validation_errors[] = 'شماره تلفن معتبر نیست (مثال: ۰۹۱۲۳۴۵۶۷۸۹)';
        }

        // بررسی رمز عبور
        if (strlen($data['password']) < 6) {
            $validation_errors[] = 'رمز عبور باید حداقل ۶ کاراکتر باشد';
        } elseif ($data['password'] !== $data['password_confirm']) {
            $validation_errors[] = 'رمز عبور و تکرار آن یکسان نیستند';
        }

        // بررسی قبول قوانین
        if (!$data['terms']) {
            $validation_errors[] = 'باید قوانین و مقررات را بپذیرید';
        }

        if (!empty($validation_errors)) {
            $error = implode('<br>', $validation_errors);
        } else {
            // ثبت نام
            $result = $auth->register($data);

            if ($result['success']) {
                $_SESSION['success_message'] = 'ثبت نام با موفقیت انجام شد. لطفا وارد شوید';
                // حذف توکن CSRF قدیم
                unset($_SESSION['csrf_token']);
                header('Location: login.php');
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

// تولید CSRF token - همیشه تولید جدید
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

include "inc/header.php";
?>

<div class="auth-page">
    <div class="auth-container register-container">
        <div class="auth-card">
            <!-- Header -->
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1 class="auth-title">ثبت نام در کتابخانه</h1>
                <p class="auth-subtitle">عضو خانواده بزرگ کتابخوانان شوید</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            <?php endif; ?>

            <!-- فرم ثبت نام -->
            <form method="POST" action="" class="auth-form" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">
                            <i class="fas fa-user"></i>
                            نام
                        </label>
                        <input type="text"
                               name="name"
                               id="name"
                               class="form-control"
                               placeholder="نام خود را وارد کنید"
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8') : '' ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="surname" class="form-label">
                            <i class="fas fa-user"></i>
                            نام خانوادگی
                        </label>
                        <input type="text"
                               name="surname"
                               id="surname"
                               class="form-control"
                               placeholder="نام خانوادگی خود را وارد کنید"
                               value="<?php echo isset($_POST['surname']) ? htmlspecialchars($_POST['surname'], ENT_QUOTES, 'UTF-8') : '' ?>"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-at"></i>
                        نام کاربری
                    </label>
                    <input type="text"
                           name="username"
                           id="username"
                           class="form-control"
                           placeholder="نام کاربری (فقط حروف انگلیسی و اعداد)"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : '' ?>"
                           pattern="[a-zA-Z0-9_]+"
                           required>
                    <small class="form-help">فقط حروف انگلیسی، اعداد و آندرلاین (_)</small>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i>
                        ایمیل
                    </label>
                    <input type="email"
                           name="email"
                           id="email"
                           class="form-control"
                           placeholder="ایمیل خود را وارد کنید"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : '' ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">
                        <i class="fas fa-phone"></i>
                        شماره تماس
                    </label>
                    <input type="tel"
                           name="phone"
                           id="phone"
                           class="form-control"
                           placeholder="۰۹۱۲۳۴۵۶۷۸۹"
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8') : '' ?>"
                           pattern="09\d{9}">
                    <small class="form-help">مثال: ۰۹۱۲۳۴۵۶۷۸۹</small>
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
                               placeholder="رمز عبور (حداقل ۶ کاراکتر)"
                               minlength="6"
                               required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="password-strength"></div>
                </div>

                <div class="form-group">
                    <label for="password_confirm" class="form-label">
                        <i class="fas fa-lock"></i>
                        تکرار رمز عبور
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password"
                               name="password_confirm"
                               id="password_confirm"
                               class="form-control"
                               placeholder="رمز عبور را دوباره وارد کنید"
                               minlength="6"
                               required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password_confirm')">
                            <i class="fas fa-eye" id="password_confirm-icon"></i>
                        </button>
                    </div>
                    <div class="password-match" id="password-match"></div>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" value="1" required>
                        <span>
                            <a href="terms.php" target="_blank">قوانین و مقررات</a> را مطالعه کرده و می‌پذیرم
                        </span>
                    </label>
                </div>

                <button type="submit" name="register" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i>
                    ثبت نام
                </button>
            </form>

            <!-- خط جداکننده -->
            <div class="auth-divider">
                <span>یا</span>
            </div>

            <!-- لینک ورود -->
            <div class="auth-footer">
                <p>قبلا ثبت نام کرده‌اید؟</p>
                <a href="login.php" class="btn btn-outline btn-block">
                    <i class="fas fa-sign-in-alt"></i>
                    وارد شوید
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
                <img src="assets/img/register-illustration.svg" alt="Register">
                <h2>همین حالا عضو شوید</h2>
                <p>با عضویت در کتابخانه، به هزاران کتاب دسترسی پیدا کنید</p>

                <div class="stats-list">
                    <div class="stat-item">
                        <div class="stat-number">۵۰۰۰+</div>
                        <div class="stat-label">کتاب</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">۱۲۰۰+</div>
                        <div class="stat-label">کاربر فعال</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">۲۴/۷</div>
                        <div class="stat-label">پشتیبانی</div>
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

// Password Strength Checker
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('password-strength');

    if (password.length === 0) {
        strengthDiv.innerHTML = '';
        return;
    }

    let strength = 0;
    let feedback = '';

    // Length
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;

    // Character types
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;

    if (strength < 3) {
        strengthDiv.innerHTML = '<span class="strength-weak">ضعیف</span>';
    } else if (strength < 5) {
        strengthDiv.innerHTML = '<span class="strength-medium">متوسط</span>';
    } else {
        strengthDiv.innerHTML = '<span class="strength-strong">قوی</span>';
    }
});

// Password Match Checker
document.getElementById('password_confirm').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    const matchDiv = document.getElementById('password-match');

    if (confirmPassword.length === 0) {
        matchDiv.innerHTML = '';
        return;
    }

    if (password === confirmPassword) {
        matchDiv.innerHTML = '<span class="match-success"><i class="fas fa-check"></i> رمز عبور مطابقت دارد</span>';
    } else {
        matchDiv.innerHTML = '<span class="match-error"><i class="fas fa-times"></i> رمز عبور مطابقت ندارد</span>';
    }
});

// Username Availability Check
let usernameTimeout;
document.getElementById('username').addEventListener('input', function() {
    clearTimeout(usernameTimeout);
    const username = this.value;

    if (username.length < 3) return;

    usernameTimeout = setTimeout(() => {
        fetch(`api/check_username.php?username=${encodeURIComponent(username)}`)
            .then(response => response.json())
            .then(data => {
                const usernameInput = document.getElementById('username');
                if (data.available) {
                    usernameInput.style.borderColor = '#10b981';
                } else {
                    usernameInput.style.borderColor = '#ef4444';
                }
            });
    }, 500);
});

// Form Validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('password_confirm').value;

    if (password !== confirmPassword) {
        e.preventDefault();
        alert('رمز عبور و تکرار آن یکسان نیستند');
        return false;
    }

    if (password.length < 6) {
        e.preventDefault();
        alert('رمز عبور باید حداقل ۶ کاراکتر باشد');
        return false;
    }
});
</script>

<?php include "inc/footer.php"; ?>