<?php
require_once 'inc/functions.php';

// بررسی دسترسی ادمین
if (!is_logged_in() || !is_admin($_SESSION['userid'])) {
    header('Location: ../login.php');
    exit;
}

$title = 'افزودن کتاب جدید';

// پردازش فرم
if (isset($_POST['add_book'])) {
    // بررسی CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error_message = 'توکن امنیتی نامعتبر است';
    } elseif (add_book()) {
        $success_message = 'کتاب با موفقیت اضافه شد';
    } else {
        $error_message = 'خطا در افزودن کتاب';
    }
}

// دریافت دسته‌بندی‌ها
$cats = get_categories();

// دریافت پارامترهای API (در صورت جستجو)
$from_api = isset($_GET['from_api']) && $_GET['from_api'] == '1';
$api_data = [];

if ($from_api) {
    $api_data = [
        'isbn' => htmlspecialchars($_GET['isbn'] ?? '', ENT_QUOTES, 'UTF-8'),
        'title' => htmlspecialchars($_GET['title'] ?? '', ENT_QUOTES, 'UTF-8'),
        'author' => htmlspecialchars($_GET['author'] ?? '', ENT_QUOTES, 'UTF-8'),
        'publisher' => htmlspecialchars($_GET['publisher'] ?? '', ENT_QUOTES, 'UTF-8'),
        'year' => htmlspecialchars($_GET['year'] ?? '', ENT_QUOTES, 'UTF-8'),
        'pages' => htmlspecialchars($_GET['pages'] ?? '', ENT_QUOTES, 'UTF-8'),
        'language' => htmlspecialchars($_GET['language'] ?? 'فارسی', ENT_QUOTES, 'UTF-8'),
        'description' => htmlspecialchars($_GET['description'] ?? '', ENT_QUOTES, 'UTF-8')
    ];
}

include "inc/header.php";
?>

<style>
.add-book-container {
    max-width: 900px;
    margin: 20px auto;
    padding: 20px;
}

.api-search-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.api-search-box h3 {
    margin: 0 0 15px 0;
    color: white;
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-form input {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 4px;
}

.search-form button {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    background: white;
    color: #667eea;
    font-weight: bold;
    cursor: pointer;
}

.search-results {
    margin-top: 15px;
    display: none;
}

.result-item {
    background: white;
    color: #333;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: transform 0.2s;
}

.result-item:hover {
    transform: translateX(-5px);
}

.form-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    margin-bottom: 15px;
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
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.image-preview {
    margin-top: 10px;
    max-width: 200px;
    border-radius: 4px;
}
</style>

<div class="add-book-container">

    <!-- جستجوی API -->
    <div class="api-search-box">
        <h3><i class="fas fa-search"></i> جستجو در کتابخانه ملی</h3>

        <div class="search-form">
            <input type="text"
                   id="isbn-search"
                   placeholder="شابک کتاب را وارد کنید..."
                   value="<?php echo htmlspecialchars($api_data['isbn'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <button onclick="searchByISBN()">
                <i class="fas fa-search"></i>
                جستجو
            </button>
        </div>

        <div id="search-results" class="search-results"></div>
    </div>

    <!-- فرم افزودن کتاب -->
    <div class="form-card">
        <h2><i class="fas fa-book"></i> افزودن کتاب جدید</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="isbn">شابک (ISBN):</label>
                    <input type="text"
                           name="isbn"
                           id="isbn"
                           class="form-control"
                           value="<?php echo htmlspecialchars($api_data['isbn'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label for="book_name">نام کتاب: *</label>
                    <input type="text"
                           name="book_name"
                           id="book_name"
                           class="form-control"
                           value="<?php echo htmlspecialchars($api_data['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="author">نویسنده: *</label>
                    <input type="text"
                           name="author"
                           id="author"
                           class="form-control"
                           value="<?php echo htmlspecialchars($api_data['author'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="publisher">ناشر:</label>
                    <input type="text"
                           name="publisher"
                           id="publisher"
                           class="form-control"
                           value="<?php echo htmlspecialchars($api_data['publisher'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="publish_year">سال انتشار:</label>
                    <input type="number"
                           name="publish_year"
                           id="publish_year"
                           class="form-control"
                           value="<?php echo htmlspecialchars($api_data['year'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label for="pages">تعداد صفحات:</label>
                    <input type="number"
                           name="pages"
                           id="pages"
                           class="form-control"
                           value="<?php echo htmlspecialchars($api_data['pages'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label for="language">زبان:</label>
                    <select name="language" id="language" class="form-control">
                        <option value="فارسی" <?php echo ($api_data['language'] ?? '') == 'فارسی' ? 'selected' : '' ?>>فارسی</option>
                        <option value="انگلیسی" <?php echo ($api_data['language'] ?? '') == 'انگلیسی' ? 'selected' : '' ?>>انگلیسی</option>
                        <option value="عربی" <?php echo ($api_data['language'] ?? '') == 'عربی' ? 'selected' : '' ?>>عربی</option>
                        <option value="سایر">سایر</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category">دسته‌بندی: *</label>
                    <select name="category" id="category" class="form-control" required>
                        <option value="">انتخاب کنید...</option>
                        <?php foreach ($cats as $cat): ?>
                            <option value="<?php echo (int)$cat['cat_id'] ?>">
                                <?php echo htmlspecialchars($cat['cat_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="count">تعداد نسخه: *</label>
                    <input type="number"
                           name="count"
                           id="count"
                           class="form-control"
                           value="1"
                           min="1"
                           required>
                </div>
            </div>

            <div class="form-group">
                <label for="description">توضیحات:</label>
                <textarea name="description"
                          id="description"
                          class="form-control"><?php echo htmlspecialchars($api_data['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="form-group">
                <label for="book_img">تصویر جلد:</label>
                <input type="file"
                       name="book_img"
                       id="book_img"
                       class="form-control"
                       accept="image/*"
                       onchange="previewImage(this)">
                <img id="image-preview" class="image-preview" style="display: none;">
            </div>

            <button type="submit" name="add_book" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                افزودن کتاب
            </button>

        </form>
    </div>

</div>

<script>
// جستجو با شابک
function searchByISBN() {
    const isbn = document.getElementById('isbn-search').value.trim();

    if (!isbn) {
        alert('لطفاً شابک را وارد کنید');
        return;
    }

    const resultsDiv = document.getElementById('search-results');
    resultsDiv.innerHTML = '<p style="text-align: center;">در حال جستجو...</p>';
    resultsDiv.style.display = 'block';

    fetch(`<?php echo htmlspecialchars(siteurl(), ENT_QUOTES, 'UTF-8') ?>/admin/api/national_library_api.php?action=search_isbn&isbn=${encodeURIComponent(isbn)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySearchResults([data.data]);
            } else {
                resultsDiv.innerHTML = `<p style="text-align: center; color: #f44336;">${escapeHtml(data.message)}</p>`;
            }
        })
        .catch(error => {
            resultsDiv.innerHTML = '<p style="text-align: center; color: #f44336;">خطا در جستجو</p>';
        });
}

// نمایش نتایج جستجو
function displaySearchResults(results) {
    const resultsDiv = document.getElementById('search-results');

    let html = '';
    results.forEach(book => {
        html += `
            <div class="result-item" onclick="fillForm(${JSON.stringify(book).replace(/"/g, '&quot;')})">
                <strong>${escapeHtml(book.title)}</strong><br>
                <small>نویسنده: ${escapeHtml(book.author)}</small><br>
                <small>ناشر: ${escapeHtml(book.publisher)} | سال: ${escapeHtml(book.year)}</small>
            </div>
        `;
    });

    resultsDiv.innerHTML = html;
}

// پر کردن فرم با اطلاعات انتخاب شده
function fillForm(book) {
    document.getElementById('isbn').value = book.isbn || '';
    document.getElementById('book_name').value = book.title || '';
    document.getElementById('author').value = book.author || '';
    document.getElementById('publisher').value = book.publisher || '';
    document.getElementById('publish_year').value = book.year || '';
    document.getElementById('pages').value = book.pages || '';
    document.getElementById('description').value = book.description || '';

    // دانلود تصویر جلد
    if (book.isbn) {
        downloadCover(book.isbn);
    }

    // بستن نتایج
    document.getElementById('search-results').style.display = 'none';
}

// دانلود تصویر جلد
function downloadCover(isbn) {
    fetch(`<?php echo htmlspecialchars(siteurl(), ENT_QUOTES, 'UTF-8') ?>/admin/api/national_library_api.php?action=download_cover&isbn=${encodeURIComponent(isbn)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const preview = document.getElementById('image-preview');
                preview.src = data.url;
                preview.style.display = 'block';
                alert('تصویر جلد دانلود شد');
            }
        });
}

// پیش‌نمایش تصویر
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('image-preview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// تابع برای escape کردن HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include "inc/footer.php"; ?>