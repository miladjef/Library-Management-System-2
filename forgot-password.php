<?php
// forgot-password.php
require_once 'inc/config.php';
require_once 'classes/Auth.php';

$db = Database::getInstance();
$auth = new Auth($db);

$title = 'بازیابی رمز عبور';

// پردازش درخواست
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_request'])) {
    $email = trim($_POST['email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'لطفا ایمیل معتبر وارد کنید';
    } else {
        $result = $auth->requestPasswordReset($email);
        
        if ($result['success']) {
            $success = $result['message'];
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
                    <i class="fas fa-key"></i>
                </div>
                <h1 class="auth-title">بازیابی رمز عبور</h1>
                <p class="auth-subtitle">ایمیل خود را وارد کنید</p>
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

            <form method="POST" action="" class="auth-form">
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
                           required>
                </div>

                <button type="submit" name="reset_request" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane"></i>
                    ارسال لینک بازیابی
                </button>
            </form>

            <div class="auth-footer">
                <a href="login.php">
                    <i class="fas fa-arrow-right"></i>
                    بازگشت به صفحه ورود
                </a>
            </div>
        </div>
    </div>
</div>

<?php include "inc/footer.php"; ?>
