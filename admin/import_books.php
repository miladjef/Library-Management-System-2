<?php
// admin/import_books.php
require_once '../inc/config.php';
require_once 'inc/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$title = 'وارد کردن کتاب‌های دسته‌جمعی';

use PhpOffice\PhpSpreadsheet\IOFactory;

// پردازش آپلود فایل
if (isset($_POST['import_books']) && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];

    // بررسی خطا
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'خطا در آپلود فایل';
    } else {
        $allowed_extensions = ['xlsx', 'xls', 'csv'];
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (!in_array(strtolower($file_extension), $allowed_extensions)) {
            $error = 'فقط فایل‌های Excel مجاز است';
        } else {
            try {
                $spreadsheet = IOFactory::load($file['tmp_name']);
                $sheet = $spreadsheet->getActiveSheet();
                $data = $sheet->toArray();

                $db = Database::getInstance();
                $conn = $db->getConnection();

                $success_count = 0;
                $error_count = 0;
                $errors = [];

                // شروع از ردیف 2 (ردیف 1 هدر است)
                for ($i = 1; $i < count($data); $i++) {
                    $row = $data[$i];

                    // بررسی خالی نبودن ردیف
                    if (empty($row[0])) continue;

                    try {
                        $stmt = $conn->prepare("
                            INSERT INTO books
                            (book_name, author, publisher, publish_year, isbn,
                             category_id, count, description, image, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");

                        // دریافت شناسه دسته‌بندی
                        $cat_stmt = $conn->prepare("
                            SELECT cat_id FROM categories WHERE cat_name = ? LIMIT 1
                        ");
                        $cat_stmt->execute([$row[5]]);
                        $category = $cat_stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$category) {
                            // ایجاد دسته‌بندی جدید
                            $insert_cat = $conn->prepare("
                                INSERT INTO categories (cat_name) VALUES (?)
                            ");
                            $insert_cat->execute([$row[5]]);
                            $category_id = $conn->lastInsertId();
                        } else {
                            $category_id = $category['cat_id'];
                        }

                        $result = $stmt->execute([
                            $row[0],  // نام کتاب
                            $row[1],  // نویسنده
                            $row[2] ?? null,  // ناشر
                            $row[3] ?? null,  // سال انتشار
                            $row[4] ?? null,  // ISBN
                            $category_id,     // دسته‌بندی
                            $row[6] ?? 1,     // تعداد
                            $row[7] ?? null,  // توضیحات
                            'default.jpg'     // تصویر پیش‌فرض
                        ]);

                        if ($result) {
                            $success_count++;
                        } else {
                            $error_count++;
                            $errors[] = "ردیف " . ($i + 1) . ": خطا در ذخیره";
                        }

                    } catch (PDOException $e) {
                        $error_count++;
                        $errors[] = "ردیف " . ($i + 1) . ": " . $e->getMessage();
                    }
                }

                $success_message = "عملیات با موفقیت انجام شد. $success_count کتاب اضافه شد";
                if ($error_count > 0) {
                    $success_message .= " و $error_count خطا رخ داد";
                }

            } catch (Exception $e) {
                $error = 'خطا در خواندن فایل: ' . $e->getMessage();
            }
        }
    }
}

include "inc/header.php";
?>

<div class="main">
    <div class="page-title">
        وارد کردن کتاب‌های دسته‌جمعی
    </div>

    <a href="books.php" class="back-button">
        <i class="fas fa-arrow-right"></i>
        بازگشت به لیست کتاب‌ها
    </a>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo  $success_message ?>

            <?php if (!empty($errors)): ?>
                <details style="margin-top: 10px;">
                    <summary>مشاهده خطاها (<?php echo  count($errors) ?>)</summary>
                    <ul style="margin-top: 10px;">
                        <?php foreach (array_slice($errors, 0, 10) as $err): ?>
                            <li><?php echo  htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo  $error ?>
        </div>
    <?php endif; ?>

    <div class="import-container">
        <div class="import-card">
            <div class="import-header">
                <i class="fas fa-file-excel"></i>
                <h2>آپلود فایل Excel</h2>
            </div>

            <div class="import-info">
                <h3>راهنمای استفاده:</h3>
                <ol>
                    <li>فایل Excel خود را با ستون‌های زیر آماده کنید:
                        <ul>
                            <li><strong>ستون A:</strong> نام کتاب (اجباری)</li>
                            <li><strong>ستون B:</strong> نویسنده (اجباری)</li>
                            <li><strong>ستون C:</strong> ناشر</li>
                            <li><strong>ستون D:</strong> سال انتشار</li>
                            <li><strong>ستون E:</strong> شابک (ISBN)</li>
                            <li><strong>ستون F:</strong> دسته‌بندی (اجباری)</li>
                            <li><strong>ستون G:</strong> تعداد</li>
                            <li><strong>ستون H:</strong> توضیحات</li>
                        </ul>
                    </li>
                    <li>ردیف اول باید شامل عنوان ستون‌ها باشد</li>
                    <li>فایل نمونه را دانلود و مطابق آن پر کنید</li>
                </ol>

                <a href="download_template.php" class="btn btn-secondary">
                    <i class="fas fa-download"></i>
                    دانلود فایل نمونه
                </a>
            </div>

            <form method="POST" enctype="multipart/form-data" class="import-form">
                <div class="file-upload-wrapper">
                    <input type="file"
                           name="excel_file"
                           id="excel_file"
                           accept=".xlsx,.xls,.csv"
                           required>
                    <label for="excel_file" class="file-upload-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>انتخاب فایل Excel</span>
                        <small>فرمت‌های مجاز: XLSX, XLS, CSV</small>
                    </label>
                </div>

                <button type="submit" name="import_books" class="btn btn-primary">
                    <i class="fas fa-file-import"></i>
                    وارد کردن کتاب‌ها
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.import-container {
    max-width: 800px;
    margin: 2rem auto;
}

.import-card {
    background: white;
    border-radius: 10px;
    padding: 2rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.import-header {
    text-align: center;
    margin-bottom: 2rem;
}

.import-header i {
    font-size: 3rem;
    color: #10b981;
    margin-bottom: 1rem;
}

.import-info {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.import-info h3 {
    color: #166534;
    margin-bottom: 1rem;
}

.import-info ol {
    margin-right: 1.5rem;
    line-height: 1.8;
}

.import-info ul {
    margin: 0.5rem 0 0.5rem 1.5rem;
    list-style-type: circle;
}

.file-upload-wrapper {
    position: relative;
    margin-bottom: 1.5rem;
}

.file-upload-wrapper input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.file-upload-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    border: 2px dashed #cbd5e1;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.file-upload-label:hover {
    border-color: #667eea;
    background: #f8fafc;
}

.file-upload-label i {
    font-size: 3rem;
    color: #667eea;
    margin-bottom: 1rem;
}

.file-upload-label span {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.file-upload-label small {
    color: #64748b;
}
</style>

<script>
document.getElementById('excel_file').addEventListener('change', function(e) {
    const label = document.querySelector('.file-upload-label span');
    if (this.files.length > 0) {
        label.textContent = this.files[0].name;
    }
});
</script>

<?php include "inc/footer.php"; ?>
