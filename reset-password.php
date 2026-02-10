<?php
require_once __DIR__ . '/includes/security.php';

// reset-password.php
require_once 'inc/config.php';
require_once 'classes/Auth.php';

$db = Database::getInstance();
$auth = new Auth($db);

$title = 'تغییر رمز عبور';

// بررسی وجود توکن
if (!isset($_GET['token']) || empty($_GET['token'])) {
    header('Location: login.php');
    exit;
}

$token = $_GET['token'];

// بررسی اعتبار توکن
$conn = $db->getConnection();
$stmt = $conn->prepare("
    SELECT pr.*, m.email, m.name
    FROM password_resets pr
    JOIN members m ON pr.mid = m.mid
    WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
    LIMIT 1
");
$stmt->execute([$token]);
$reset_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reset_data) {
    $error_message = 'لینک بازیابی نامعتبر یا منقضی شده است';
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($new_password) < 6) {
        $error = 'رمز عبور باید حداقل 6 کاراکتر باشد';
    } elseif ($new_password !== $confirm_password) {
        $error = 'رمز عبور و تکرار آن یکسان نیستند';
    } else {
        $result = $auth->resetPassword($token, $new_password);

        if ($result['success']) {
            $_SESSION['success_message'] = 'رمز عبور با موفقیت تغییر یافت. لطفا وارد شوید';
            header('Location: login.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

include "inc/header.php";
?>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-lock"></i>
                </div>
                <h1 class="auth-title">تغییر رمز عبور</h1>
                <?php if (isset($reset_data)): ?>
                    <p class="auth-subtitle">سلام <?php echo  htmlspecialchars($reset_data['name']) ?>، رمز عبور جدید خود را وارد کنید</p>
                <?php endif; ?>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo  htmlspecialchars($error_message) ?>
                </div>
                <div class="auth-footer">
                    <a href="forgot-password.php" class="btn btn-primary btn-block">
                        <i class="fas fa-redo"></i>
                        درخواست مجدد
                    </a>
                </div>
            <?php else: ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo  htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="auth-form" id="resetForm">
                    <input type="hidden" name="token" value="<?php echo  htmlspecialchars($token) ?>">

                    <div class="form-group">
                        <label for="new_password" class="form-label">
                            <i class="fas fa-lock"></i>
                            رمز عبور جدید
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password"
                                   name="new_password"
                                   id="new_password"
                                   class="form-control"
                                   placeholder="رمز عبور جدید (حداقل 6 کاراکتر)"
                                   minlength="6"
                                   required>
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye" id="new_password-icon"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="password-strength"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock"></i>
                            تکرار رمز عبور جدید
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password"
                                   name="confirm_password"
                                   id="confirm_password"
                                   class="form-control"
                                   placeholder="رمز عبور را دوباره وارد کنید"
                                   minlength="6"
                                   required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="confirm_password-icon"></i>
                            </button>
                        </div>
                        <div class="password-match" id="password-match"></div>
                    </div>

                    <button type="submit" name="reset_password" class="btn btn-primary btn-block">
                        <i class="fas fa-check"></i>
                        تغییر رمز عبور
                    </button>
                </form>

                <div class="auth-footer">
                    <a href="login.php">
                        <i class="fas fa-arrow-right"></i>
                        بازگشت به صفحه ورود
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
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
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('password-strength');

    if (password.length === 0) {
        strengthDiv.innerHTML = '';
        return;
    }

    let strength = 0;

    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
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
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('new_password').value;
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
</script>

<?php include "inc/footer.php"; ?>
