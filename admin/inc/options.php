<?php
// admin/inc/options.php

require_once '../classes/Settings.php';

$settings = new Settings();
$success = false;
$error = false;

// پردازش فرم ذخیره تنظیمات
if (isset($_POST['save_settings'])) {
    $updates = [];
    
    // تنظیمات عمومی و امانت
    if (isset($_POST['max_borrow_days'])) {
        $updates['max_borrow_days'] = intval($_POST['max_borrow_days']);
    }
    if (isset($_POST['daily_penalty_amount'])) {
        $updates['daily_penalty_amount'] = intval($_POST['daily_penalty_amount']);
    }
    if (isset($_POST['max_active_borrows'])) {
        $updates['max_active_borrows'] = intval($_POST['max_active_borrows']);
    }
    if (isset($_POST['max_extensions'])) {
        $updates['max_extensions'] = intval($_POST['max_extensions']);
    }
    if (isset($_POST['extension_days'])) {
        $updates['extension_days'] = intval($_POST['extension_days']);
    }
    if (isset($_POST['library_name'])) {
        $updates['library_name'] = trim($_POST['library_name']);
    }
    if (isset($_POST['admin_email'])) {
        $updates['admin_email'] = trim($_POST['admin_email']);
    }
    
    // تنظیمات اعلان‌ها (غیرفعال برای استفاده شخصی)
    $updates['enable_email_notifications'] = '0';
    $updates['enable_sms_notifications'] = '0';
    
    if ($settings->updateMultiple($updates)) {
        $success = true;
    } else {
        $error = true;
    }
}

// بازنشانی به پیش‌فرض
if (isset($_POST['reset_defaults'])) {
    if ($settings->resetToDefaults()) {
        $success = true;
    } else {
        $error = true;
    }
}

// دریافت تنظیمات فعلی
$current_settings = $settings->getAll();
$grouped_settings = [];
foreach ($current_settings as $setting) {
    $grouped_settings[$setting['setting_group']][] = $setting;
}
?>

<div class="main">
    <div class="page-title">
        <i class="fas fa-cog"></i>
        تنظیمات سیستم
    </div>
    
    <?php if ($success): ?>
        <div class="success-notification" id="successMsg">
            تنظیمات با موفقیت ذخیره شد.
            <span onclick="document.getElementById('successMsg').style.display='none'">&times;</span>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error-notification" id="errorMsg">
            خطا در ذخیره تنظیمات. لطفا دوباره تلاش کنید.
            <span onclick="document.getElementById('errorMsg').style.display='none'">&times;</span>
        </div>
    <?php endif; ?>

    <form method="POST" class="settings-form">
        
        <!-- تنظیمات عمومی -->
        <div class="settings-section">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i>
                تنظیمات عمومی
            </h3>
            
            <div class="form-group">
                <label for="library_name">نام کتابخانه:</label>
                <input type="text" 
                       name="library_name" 
                       id="library_name"
                       value="<?= htmlspecialchars(Settings::get('library_name')) ?>"
                       class="form-control">
                <small class="form-hint">نام کتابخانه شخصی شما</small>
            </div>
            
            <div class="form-group">
                <label for="admin_email">ایمیل مدیر:</label>
                <input type="email" 
                       name="admin_email" 
                       id="admin_email"
                       value="<?= htmlspecialchars(Settings::get('admin_email')) ?>"
                       class="form-control">
                <small class="form-hint">ایمیل شخصی شما برای دریافت گزارشات</small>
            </div>
        </div>

        <!-- تنظیمات امانت -->
        <div class="settings-section">
            <h3 class="section-title">
                <i class="fas fa-book-reader"></i>
                تنظیمات امانت
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="max_borrow_days">مدت امانت پیش‌فرض (روز):</label>
                    <input type="number" 
                           name="max_borrow_days" 
                           id="max_borrow_days"
                           value="<?= Settings::get('max_borrow_days') ?>"
                           min="1" 
                           max="90"
                           class="form-control">
                    <small class="form-hint">مدت زمان پیش‌فرض برای امانت کتاب</small>
                </div>
                
                <div class="form-group">
                    <label for="max_active_borrows">حداکثر امانت همزمان:</label>
                    <input type="number" 
                           name="max_active_borrows" 
                           id="max_active_borrows"
                           value="<?= Settings::get('max_active_borrows') ?>"
                           min="1" 
                           max="10"
                           class="form-control">
                    <small class="form-hint">تعداد کتاب‌هایی که می‌توان به صورت همزمان امانت گرفت</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="max_extensions">حداکثر تعداد تمدید:</label>
                    <input type="number" 
                           name="max_extensions" 
                           id="max_extensions"
                           value="<?= Settings::get('max_extensions') ?>"
                           min="0" 
                           max="5"
                           class="form-control">
                    <small class="form-hint">تعداد دفعاتی که می‌توان امانت را تمدید کرد</small>
                </div>
                
                <div class="form-group">
                    <label for="extension_days">مدت تمدید (روز):</label>
                    <input type="number" 
                           name="extension_days" 
                           id="extension_days"
                           value="<?= Settings::get('extension_days') ?>"
                           min="1" 
                           max="30"
                           class="form-control">
                    <small class="form-hint">مدت زمان اضافه شده در هر تمدید</small>
                </div>
            </div>
        </div>

        <!-- تنظیمات مالی -->
        <div class="settings-section">
            <h3 class="section-title">
                <i class="fas fa-money-bill-wave"></i>
                تنظیمات مالی
            </h3>
            
            <div class="form-group">
                <label for="daily_penalty_amount">مبلغ جریمه روزانه (تومان):</label>
                <input type="number" 
                       name="daily_penalty_amount" 
                       id="daily_penalty_amount"
                       value="<?= Settings::get('daily_penalty_amount') ?>"
                       min="0" 
                       step="1000"
                       class="form-control">
                <small class="form-hint">مبلغ جریمه برای هر روز تاخیر در بازگشت کتاب</small>
                <div class="amount-preview">
                    پیش‌نمایش: <strong><?= number_format(Settings::get('daily_penalty_amount')) ?></strong> تومان به ازای هر روز
                </div>
            </div>
        </div>

        <!-- تنظیمات اعلان‌ها (غیرفعال) -->
        <div class="settings-section disabled-section">
            <h3 class="section-title">
                <i class="fas fa-bell"></i>
                تنظیمات اعلان‌ها
                <span class="badge-disabled">غیرفعال</span>
            </h3>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <p>
                    این سیستم برای استفاده شخصی طراحی شده است و امکان ارسال خودکار ایمیل یا پیامک وجود ندارد.
                    در صورت نیاز می‌توانید به صورت دستی از طریق پنل مدیریتی، اطلاع‌رسانی‌های لازم را انجام دهید.
                </p>
            </div>
            
            <div class="form-group disabled">
                <label>
                    <input type="checkbox" disabled checked style="opacity: 0.5;">
                    اعلان‌های ایمیل (غیرفعال)
                </label>
            </div>
            
            <div class="form-group disabled">
                <label>
                    <input type="checkbox" disabled style="opacity: 0.5;">
                    اعلان‌های پیامکی (غیرفعال)
                </label>
            </div>
        </div>

        <!-- دکمه‌های عملیات -->
        <div class="form-actions">
            <button type="submit" name="save_settings" class="btn btn-primary">
                <i class="fas fa-save"></i>
                ذخیره تنظیمات
            </button>
            
            <button type="submit" 
                    name="reset_defaults" 
                    class="btn btn-secondary"
                    onclick="return confirm('آیا مطمئن هستید که می‌خواهید تنظیمات را به حالت پیش‌فرض بازگردانید؟')">
                <i class="fas fa-undo"></i>
                بازگشت به پیش‌فرض
            </button>
        </div>
    </form>

    <!-- اطلاعات سیستم -->
    <div class="system-info">
        <h3 class="section-title">
            <i class="fas fa-server"></i>
            اطلاعات سیستم
        </h3>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">نسخه PHP:</span>
                <span class="info-value"><?= phpversion() ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">نسخه MySQL:</span>
                <span class="info-value"><?php
                    $db = Database::getInstance();
                    $version = $db->getConnection()->query('SELECT VERSION()')->fetchColumn();
                    echo $version;
                ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">حجم دیتابیس:</span>
                <span class="info-value"><?php
                    $stmt = $db->getConnection()->query("
                        SELECT 
                            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                        FROM information_schema.TABLES 
                        WHERE table_schema = '" . DB_NAME . "'
                    ");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo $result['size_mb'] . ' MB';
                ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">آخرین به‌روزرسانی:</span>
                <span class="info-value"><?= jdate('Y/m/d H:i') ?></span>
            </div>
        </div>
    </div>
</div>

<style>
.settings-form {
    background: white;
    border-radius: 10px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.settings-section {
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 2px solid #f1f5f9;
}

.settings-section:last-of-type {
    border-bottom: none;
}

.section-title {
    font-size: 1.3rem;
    color: #1e293b;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-title i {
    color: #667eea;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #334155;
    margin-bottom: 0.5rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-hint {
    display: block;
    color: #64748b;
    font-size: 0.875rem;
    margin-top: 0.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.amount-preview {
    margin-top: 1rem;
    padding: 1rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 6px;
    color: #166534;
}

.amount-preview strong {
    color: #15803d;
    font-size: 1.1rem;
}

/* بخش غیرفعال */
.disabled-section {
    opacity: 0.7;
    position: relative;
}

.badge-disabled {
    display: inline-block;
    background: #f87171;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    margin-right: 1rem;
}

.info-box {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
    background: #fef3c7;
    border: 1px solid #fde047;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.info-box i {
    color: #d97706;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.info-box p {
    margin: 0;
    color: #92400e;
    line-height: 1.6;
}

.form-group.disabled {
    opacity: 0.5;
    pointer-events: none;
}

/* دکمه‌های عملیات */
.form-actions {
    display: flex;
    gap: 1rem;
    padding-top: 2rem;
    margin-top: 2rem;
    border-top: 2px solid #f1f5f9;
}

.btn {
    padding: 0.875rem 2rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
    background: #f1f5f9;
    color: #475569;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

/* اطلاعات سیستم */
.system-info {
    background: white;
    border-radius: 10px;
    padding: 2rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.info-item {
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    border-right: 4px solid #667eea;
}

.info-label {
    display: block;
    font-size: 0.875rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.info-value {
    display: block;
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
}

/* نوتیفیکیشن */
.success-notification,
.error-notification {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.success-notification {
    background: #d1fae5;
    border: 1px solid #6ee7b7;
    color: #065f46;
}

.error-notification {
    background: #fee2e2;
    border: 1px solid #fca5a5;
    color: #991b1b;
}

.success-notification span,
.error-notification span {
    cursor: pointer;
    font-size: 1.5rem;
    font-weight: bold;
    opacity: 0.7;
}

.success-notification span:hover,
.error-notification span:hover {
    opacity: 1;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// پیش‌نمایش زنده مبلغ جریمه
document.getElementById('daily_penalty_amount').addEventListener('input', function(e) {
    const amount = parseInt(e.target.value) || 0;
    const preview = document.querySelector('.amount-preview strong');
    if (preview) {
        preview.textContent = new Intl.NumberFormat('fa-IR').format(amount);
    }
});

// اعتبارسنجی فرم
document.querySelector('.settings-form').addEventListener('submit', function(e) {
    const maxBorrowDays = parseInt(document.getElementById('max_borrow_days').value);
    const maxActiveBorrows = parseInt(document.getElementById('max_active_borrows').value);
    const dailyPenalty = parseInt(document.getElementById('daily_penalty_amount').value);
    
    if (maxBorrowDays < 1 || maxBorrowDays > 90) {
        alert('مدت امانت باید بین 1 تا 90 روز باشد');
        e.preventDefault();
        return false;
    }
    
    if (maxActiveBorrows < 1 || maxActiveBorrows > 10) {
        alert('تعداد امانت همزمان باید بین 1 تا 10 باشد');
        e.preventDefault();
        return false;
    }
    
    if (dailyPenalty < 0) {
        alert('مبلغ جریمه نمی‌تواند منفی باشد');
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>
