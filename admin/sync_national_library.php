<?php
require_once 'inc/functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$title = 'مدیریت سینک با کتابخانه ملی';

// دریافت تنظیمات فعلی
$stmt = $pdo->query("SELECT * FROM sync_settings WHERE id = 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    // ایجاد رکورد پیش‌فرض
    $pdo->query("INSERT INTO sync_settings (id, sync_enabled, sync_interval_hours, auto_download_covers) VALUES (1, 0, 24, 1)");
    $settings = [
        'sync_enabled' => 0,
        'sync_interval_hours' => 24,
        'auto_download_covers' => 1,
        'last_sync_at' => null
    ];
}

// پردازش فرم تنظیمات
if (isset($_POST['update_settings'])) {
    $sync_enabled = isset($_POST['sync_enabled']) ? 1 : 0;
    $sync_interval = intval($_POST['sync_interval_hours']);
    $auto_download = isset($_POST['auto_download_covers']) ? 1 : 0;

    if ($sync_interval < 1) $sync_interval = 24;

    $stmt = $pdo->prepare("
        UPDATE sync_settings
        SET sync_enabled = ?,
            sync_interval_hours = ?,
            auto_download_covers = ?
        WHERE id = 1
    ");

    if ($stmt->execute([$sync_enabled, $sync_interval, $auto_download])) {
        $success_message = 'تنظیمات با موفقیت ذخیره شد';

        // بارگذاری مجدد تنظیمات
        $stmt = $pdo->query("SELECT * FROM sync_settings WHERE id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error_message = 'خطا در ذخیره تنظیمات';
    }
}

// دریافت آمار
$stats_query = "
    SELECT
        operation_type,
        status,
        COUNT(*) as count,
        MAX(created_at) as last_operation
    FROM national_library_logs
    GROUP BY operation_type, status
";
$stats = $pdo->query($stats_query)->fetchAll(PDO::FETCH_ASSOC);

// آخرین لاگ‌ها
$logs_query = "
    SELECT * FROM national_library_logs
    ORDER BY created_at DESC
    LIMIT 50
";
$logs = $pdo->query($logs_query)->fetchAll(PDO::FETCH_ASSOC);

include "inc/header.php";
?>

<style>
.sync-container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
}

.sync-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 25px;
    margin-bottom: 20px;
}

.sync-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.sync-header i {
    font-size: 32px;
    color: #2196F3;
}

.sync-header h2 {
    margin: 0;
    color: #333;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.stat-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.stat-box.success {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
}

.stat-box.error {
    background: linear-gradient(135deg, #f44336 0%, #e53935 100%);
}

.stat-box .number {
    font-size: 36px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-box .label {
    font-size: 14px;
    opacity: 0.9;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-wrapper input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    transition: all 0.3s;
}

.btn-primary {
    background: #2196F3;
    color: white;
}

.btn-primary:hover {
    background: #1976D2;
}

.btn-success {
    background: #4CAF50;
    color: white;
}

.btn-success:hover {
    background: #45a049;
}

.btn-warning {
    background: #FF9800;
    color: white;
}

.btn-warning:hover {
    background: #F57C00;
}

.log-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.log-table th,
.log-table td {
    padding: 12px;
    text-align: right;
    border-bottom: 1px solid #ddd;
}

.log-table th {
    background: #f5f5f5;
    font-weight: bold;
    color: #333;
}

.log-table tr:hover {
    background: #f9f9f9;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.status-badge.success {
    background: #4CAF50;
    color: white;
}

.status-badge.error {
    background: #f44336;
    color: white;
}

.alert {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.info-box {
    background: #e3f2fd;
    border-right: 4px solid #2196F3;
    padding: 15px;
    border-radius: 4px;
    margin: 15px 0;
}

.info-box strong {
    color: #1976D2;
}

.button-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.loading {
    display: inline-block;
    margin-right: 10px;
}

.loading:after {
    content: " ";
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid #f3f3f3;
    border-top-color: #2196F3;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="sync-container">

    <!-- هدر صفحه -->
    <div class="sync-card">
        <div class="sync-header">
            <i class="fas fa-sync-alt"></i>
            <div>
                <h2>مدیریت سینک با کتابخانه ملی ایران</h2>
                <p style="margin: 5px 0 0 0; color: #666;">
                    به‌روزرسانی خودکار اطلاعات کتاب‌ها و دانلود تصاویر جلد
                </p>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo  $success_message ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo  $error_message ?>
            </div>
        <?php endif; ?>

        <!-- آمار کلی -->
        <div class="stats-grid">
            <?php
            $total_operations = 0;
            $successful_operations = 0;
            $failed_operations = 0;

            foreach ($stats as $stat) {
                $total_operations += $stat['count'];
                if ($stat['status'] == 'success') {
                    $successful_operations += $stat['count'];
                } else {
                    $failed_operations += $stat['count'];
                }
            }
            ?>

            <div class="stat-box">
                <div class="number"><?php echo  $total_operations ?></div>
                <div class="label">کل عملیات</div>
            </div>

            <div class="stat-box success">
                <div class="number"><?php echo  $successful_operations ?></div>
                <div class="label">موفق</div>
            </div>

            <div class="stat-box error">
                <div class="number"><?php echo  $failed_operations ?></div>
                <div class="label">ناموفق</div>
            </div>

            <div class="stat-box">
                <div class="number">
                    <?php if ($settings['last_sync_at']): ?>
                        <?php echo  jdate('H:i - Y/m/d', strtotime($settings['last_sync_at'])) ?>
                    <?php else: ?>
                        ---
                    <?php endif; ?>
                </div>
                <div class="label">آخرین سینک</div>
            </div>
        </div>
    </div>

    <!-- تنظیمات سینک خودکار -->
    <div class="sync-card">
        <h3><i class="fas fa-cog"></i> تنظیمات سینک خودکار</h3>

        <form method="POST">
            <div class="form-group">
                <div class="checkbox-wrapper">
                    <input type="checkbox"
                           name="sync_enabled"
                           id="sync_enabled"
                           <?php echo  $settings['sync_enabled'] ? 'checked' : '' ?>>
                    <label for="sync_enabled" style="margin: 0;">
                        فعال‌سازی سینک خودکار
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="sync_interval_hours">
                    فاصله زمانی سینک (ساعت):
                </label>
                <input type="number"
                       name="sync_interval_hours"
                       id="sync_interval_hours"
                       class="form-control"
                       value="<?php echo  $settings['sync_interval_hours'] ?>"
                       min="1"
                       max="168">
                <small style="color: #666;">توصیه می‌شود: 24 ساعت</small>
            </div>

            <div class="form-group">
                <div class="checkbox-wrapper">
                    <input type="checkbox"
                           name="auto_download_covers"
                           id="auto_download_covers"
                           <?php echo  $settings['auto_download_covers'] ? 'checked' : '' ?>>
                    <label for="auto_download_covers" style="margin: 0;">
                        دانلود خودکار تصاویر جلد
                    </label>
                </div>
            </div>

            <div class="info-box">
                <strong>نکته:</strong> برای فعال‌سازی سینک خودکار، باید یک Cron Job تنظیم کنید:
                <pre style="margin-top: 10px; padding: 10px; background: white; border-radius: 4px;">0 2 * * * /usr/bin/php <?php echo  __DIR__ ?>/cron/sync_national_library.php</pre>
            </div>

            <button type="submit" name="update_settings" class="btn btn-primary">
                <i class="fas fa-save"></i>
                ذخیره تنظیمات
            </button>
        </form>
    </div>

    <!-- عملیات دستی -->
    <div class="sync-card">
        <h3><i class="fas fa-hand-pointer"></i> عملیات دستی</h3>

        <div class="button-group">
            <button onclick="runFullSync()" class="btn btn-success">
                <i class="fas fa-sync"></i>
                اجرای سینک کامل
            </button>

            <button onclick="downloadAllCovers()" class="btn btn-warning">
                <i class="fas fa-images"></i>
                دانلود همه جلدها
            </button>

            <button onclick="clearLogs()" class="btn btn-danger">
                <i class="fas fa-trash"></i>
                پاک‌سازی لاگ‌ها
            </button>
        </div>

        <div id="sync-progress" style="margin-top: 20px; display: none;">
            <div class="alert alert-info">
                <span class="loading"></span>
                <span id="progress-text">در حال پردازش...</span>
            </div>
        </div>
    </div>

    <!-- آخرین لاگ‌ها -->
    <div class="sync-card">
        <h3><i class="fas fa-list"></i> آخرین لاگ‌های عملیات</h3>

        <div style="overflow-x: auto;">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>زمان</th>
                        <th>نوع عملیات</th>
                        <th>جستجو</th>
                        <th>وضعیت</th>
                        <th>پیام</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <?php echo  jdate('H:i:s - Y/m/d', strtotime($log['created_at'])) ?>
                            </td>
                            <td>
                                <?php
                                $operation_names = [
                                    'search_isbn' => 'جستجو با شابک',
                                    'search_title' => 'جستجو با عنوان',
                                    'download_cover' => 'دانلود جلد',
                                    'sync' => 'سینک'
                                ];
                                echo $operation_names[$log['operation_type']] ?? $log['operation_type'];
                                ?>
                            </td>
                            <td><?php echo  e($log['search_query']) ?></td>
                            <td>
                                <span class="status-badge <?php echo  $log['status'] ?>">
                                    <?php echo  $log['status'] == 'success' ? 'موفق' : 'ناموفق' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['error_message']): ?>
                                    <small style="color: #f44336;">
                                        <?php echo  e($log['error_message']) ?>
                                    </small>
                                <?php else: ?>
                                    <small style="color: #4CAF50;">✓</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// اجرای سینک کامل
function runFullSync() {
    if (!confirm('آیا از اجرای سینک کامل اطمینان دارید؟ این عملیات ممکن است چند دقیقه طول بکشد.')) {
        return;
    }

    const progressDiv = document.getElementById('sync-progress');
    const progressText = document.getElementById('progress-text');

    progressDiv.style.display = 'block';
    progressText.textContent = 'در حال سینک کتاب‌ها...';

    fetch('<?php echo  siteurl() ?>/admin/cron/sync_national_library.php')
        .then(response => response.text())
        .then(data => {
            progressText.textContent = 'سینک با موفقیت انجام شد';
            setTimeout(() => {
                location.reload();
            }, 2000);
        })
        .catch(error => {
            progressText.textContent = 'خطا در سینک: ' + error;
        });
}

// دانلود همه جلدها
function downloadAllCovers() {
    if (!confirm('آیا از دانلود همه تصاویر جلد اطمینان دارید؟')) {
        return;
    }

    const progressDiv = document.getElementById('sync-progress');
    const progressText = document.getElementById('progress-text');

    progressDiv.style.display = 'block';
    progressText.textContent = 'در حال دانلود تصاویر جلد...';

    // TODO: پیاده‌سازی دانلود دسته‌جمعی
    alert('این قابلیت به زودی اضافه خواهد شد');
    progressDiv.style.display = 'none';
}

// پاک‌سازی لاگ‌ها
function clearLogs() {
    if (!confirm('آیا از پاک‌سازی تمام لاگ‌ها اطمینان دارید؟')) {
        return;
    }

    fetch('<?php echo  siteurl() ?>/admin/api/clear_logs.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('لاگ‌ها با موفقیت پاک شدند');
            location.reload();
        } else {
            alert('خطا: ' + data.message);
        }
    })
    .catch(error => {
        alert('خطا در پاک‌سازی لاگ‌ها');
    });
}
</script>

<?php include "inc/footer.php"; ?>
