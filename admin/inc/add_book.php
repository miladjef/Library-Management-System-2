<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Book.php';
require_once '../classes/Category.php';
require_once '../classes/Validator.php';
require_once '../classes/CSRF.php';

$errors = [];
$success = false;
$bookData = [];

// پردازش ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {

    // بررسی CSRF
    if (!CSRF::validate($_POST['csrf_token'] ?? '', 'add_book')) {
        $errors[] = 'توکن امنیتی نامعتبر است';
    } else {

        $data = [
            'book_name' => trim($_POST['book_name'] ?? ''),
            'isbn' => trim($_POST['isbn'] ?? ''),
            'cid' => intval($_POST['cid'] ?? 0),
            'author' => trim($_POST['author'] ?? ''),
            'publisher' => trim($_POST['publisher'] ?? ''),
            'publish_year' => trim($_POST['publish_year'] ?? ''),
            'pages' => intval($_POST['pages'] ?? 0),
            'quantity' => intval($_POST['quantity'] ?? 1),
            'description' => trim($_POST['description'] ?? ''),
            'book_image' => $_FILES['book_image'] ?? null
        ];

        // قوانین اعتبارسنجی
        $rules = [
            'book_name' => 'required|min:2|max:255',
            'isbn' => 'required|isbn|unique:books,isbn',
            'cid' => 'required|numeric|min:1',
            'author' => 'required|min:2|max:255',
            'publisher' => 'max:255',
            'publish_year' => 'numeric|min:1300|max:1450',
            'pages' => 'numeric|min:1',
            'quantity' => 'required|numeric|min:1',
            'description' => 'max:2000'
        ];

        $validator = new Validator($data, $rules);

        if (!$validator->validate()) {
            $errors = $validator->getErrors();
        } else {
            try {
                $book = new Book();
                $bookId = $book->create($data);

                if ($bookId) {
                    $success = true;
                    $_SESSION['book_add_success'] = true;

                    // ریدایرکت پس از موفقیت
                    secureRedirect('books.php?add=ok');
                } else {
                    $errors[] = 'خطا در ثبت کتاب';
                }
            } catch (Exception $e) {
                logError('Add Book Error: ' . $e->getMessage());
                $errors[] = 'خطای سیستمی رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }

        // در صورت خطا، داده‌ها را حفظ کن
        if (!empty($errors)) {
            $bookData = $data;
        }
    }
}

// دریافت لیست دسته‌بندی‌ها
try {
    $category = new Category();
    $categories = $category->getAll();
} catch (Exception $e) {
    logError('Get Categories Error: ' . $e->getMessage());
    $categories = [];
}

// تولید CSRF Token
$csrfToken = CSRF::generate('add_book');
?>

<div class="main">
    <div class="page-title">
        افزودن کتاب جدید
    </div>

    <a href="books.php">
        <div class="back-button">
            بازگشت به لیست کتاب‌ها
        </div>
    </a>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" id="errorAlert">
            <strong>خطاها:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo  htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
            <button onclick="closeAlert('errorAlert')" class="close-btn">&times;</button>
        </div>
    <?php endif; ?>

    <form action="#" method="POST" enctype="multipart/form-data" id="addBookForm">
        <input type="hidden" name="csrf_token" value="<?php echo  htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <!-- بخش جستجوی ISBN -->
        <div class="isbn-search-section">
            <label>جستجوی خودکار از روی ISBN:</label>
            <div class="isbn-search-group">
                <input type="text"
                       id="isbn_search"
                       placeholder="ISBN را وارد کنید یا اسکن کنید..."
                       pattern="\d{10}|\d{13}"
                       title="ISBN باید 10 یا 13 رقم باشد">
                <button type="button" id="searchISBNBtn" class="btn-secondary">
                    🔍 جستجو
                </button>
                <button type="button" id="scanBarcodeBtn" class="btn-secondary">
                    📷 اسکن بارکد
                </button>
            </div>
            <div id="searchResult" class="search-result"></div>
        </div>

        <hr>

        <!-- فیلدهای اصلی فرم -->
        <label for="book_name">نام کتاب: <span class="required">*</span></label>
        <input type="text"
               name="book_name"
               id="book_name"
               value="<?php echo  htmlspecialchars($bookData['book_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               required
               maxlength="255">

        <label for="isbn">شابک (ISBN): <span class="required">*</span></label>
        <input type="text"
               name="isbn"
               id="isbn"
               value="<?php echo  htmlspecialchars($bookData['isbn'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               pattern="\d{10}|\d{13}"
               title="ISBN باید 10 یا 13 رقم باشد"
               required>
        <small class="help-text">فقط اعداد، 10 یا 13 رقم</small>

        <label for="cid">دسته‌بندی: <span class="required">*</span></label>
        <select name="cid" id="cid" required>
            <option value="">انتخاب کنید...</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo  $cat['cid'] ?>"
                        <?php echo  (isset($bookData['cid']) && $bookData['cid'] == $cat['cid']) ? 'selected' : '' ?>>
                    <?php echo  htmlspecialchars($cat['cat_name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="author">نویسنده: <span class="required">*</span></label>
        <input type="text"
               name="author"
               id="author"
               value="<?php echo  htmlspecialchars($bookData['author'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               required
               maxlength="255">

        <label for="publisher">ناشر:</label>
        <input type="text"
               name="publisher"
               id="publisher"
               value="<?php echo  htmlspecialchars($bookData['publisher'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               maxlength="255">

        <label for="publish_year">سال انتشار (شمسی):</label>
        <input type="number"
               name="publish_year"
               id="publish_year"
               value="<?php echo  htmlspecialchars($bookData['publish_year'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               min="1300"
               max="1450"
               placeholder="مثال: 1402">

        <label for="pages">تعداد صفحات:</label>
        <input type="number"
               name="pages"
               id="pages"
               value="<?php echo  htmlspecialchars($bookData['pages'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               min="1"
               placeholder="مثال: 250">

        <label for="quantity">تعداد نسخه: <span class="required">*</span></label>
        <input type="number"
               name="quantity"
               id="quantity"
               value="<?php echo  htmlspecialchars($bookData['quantity'] ?? '1', ENT_QUOTES, 'UTF-8') ?>"
               required
               min="1">

        <label for="description">توضیحات:</label>
        <textarea name="description"
                  id="description"
                  rows="4"
                  maxlength="2000"><?php echo  htmlspecialchars($bookData['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

        <label for="book_image">تصویر جلد کتاب:</label>
        <input type="file"
               name="book_image"
               id="book_image"
               accept="image/jpeg,image/png,image/jpg">
        <small class="help-text">فرمت‌های مجاز: JPG, PNG (حداکثر 5MB)</small>

        <div id="imagePreview" class="image-preview"></div>

        <button class="submit" type="submit" name="add_book">افزودن کتاب</button>
    </form>
</div>

<!-- Modal برای اسکن بارکد -->
<div id="barcodeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeBarcodeScanner()">&times;</span>
        <h3>اسکن بارکد ISBN</h3>
        <div id="barcode-scanner">
            <video id="video" width="100%" autoplay></video>
            <canvas id="canvas" style="display:none;"></canvas>
        </div>
        <div id="scanResult"></div>
    </div>
</div>

<script src="assets/js/add_book.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
