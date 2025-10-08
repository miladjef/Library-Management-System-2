<?php
require_once 'inc/functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$title = 'افزودن کتاب جدید';

// پردازش فرم
if (isset($_POST['add_book'])) {
    if (add_book()) {
        $success_message = 'کتاب با موفقیت اضافه شد';
    } else {
        $error_message = 'خطا در افزودن کتاب';
    }
}

// دریافت دسته‌بندی‌ها
get_categories();

// دریافت پارامترهای API (در صورت جستجو)
$from_api = isset($_GET['from_api']) && $_GET['from_api'] == '1';
$api_data = [];

if ($from_api) {
    $api_data = [
        'isbn' => $_GET['isbn'] ?? '',
        'title' => $_GET['title'] ?? '',
        'author' => $_GET['author'] ?? '',
        'publisher' => $_GET['publisher'] ?? '',
        'year' => $_GET['year'] ?? '',
        'pages' => $_GET['pages'] ?? '',
        'language' => $_GET['language'] ?? 'فارسی',
        'description' => $_GET['description'] ?? ''
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
                   value="<?= $api_data['isbn'] ?? '' ?>">
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
                <?= $success_message ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?= $error_message ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="isbn">شابک (ISBN):</label>
                    <input type="text" 
                           name="isbn" 
                           id="isbn"
                           class="form-control"
                           value="<?= $api_data['isbn'] ?? '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="book_name">نام کتاب: *</label>
                    <input type="text" 
                           name="book_name" 
                           id="book_name"
                           class="form-control"
                           value="<?= $api_data['title'] ?? '' ?>"
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
                           value="<?= $api_data['author'] ?? '' ?>"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="publisher">ناشر:</label>
                    <input type="text" 
                           name="publisher" 
                           id="publisher"
                           class="form-control"
                           value="<?= $api_data['publisher'] ?? '' ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="publish_year">سال انتشار:</label>
                    <input type="number" 
                           name="publish_year" 
                           id="publish_year"
                           class="form-control"
                           value="<?= $api_data['year'] ?? '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="pages">تعداد صفحات:</label>
                    <input type="number" 
                           name="pages" 
                           id="pages"
                           class="form-control"
                           value="<?= $api_data['pages'] ?? '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="language">زبان:</label>
                    <select name="language" id="language" class="form-control">
                        <option value="فارسی" <?= ($api_data['language'] ?? '') == 'فارسی' ? 'selected' : '' ?>>فارسی</option>
                        <option value="انگلیسی" <?= ($api_data['language'] ?? '') == 'انگلیسی' ? 'selected' : '' ?>>انگلیسی</option>
                        <option value="عربی" <?= ($api_data['language'] ?? '') == 'عربی' ? 'selected' : '' ?>>عربی</option>
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
                            <option value="<?= $cat['cat_id'] ?>">
                                <?= $cat['cat_name'] ?>
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
                          class="form-control"><?= $api_data['description'] ?? '' ?></textarea>
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
    
    fetch(`<?= siteurl() ?>/admin/api/national_library_api.php?action=search_isbn&isbn=${isbn}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySearchResults([data.data]);
            } else {
                resultsDiv.innerHTML = `<p style="text-align: center; color: #f44336;">${data.message}</p>`;
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
                <strong>${book.title}</strong><br>
                <small>نویسنده: ${book.author}</small><br>
                <small>ناشر: ${book.publisher} | سال: ${book.year}</small>
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
    fetch(`<?= siteurl() ?>/admin/api/national_library_api.php?action=download_cover&isbn=${isbn}`)
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
</script>

<?php include "inc/footer.php"; ?>
