<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Book.php';
require_once '../classes/Category.php';
require_once '../classes/Validator.php';
require_once '../classes/CSRF.php';

// 🔴 بررسی دسترسی ادمین
if (!is_logged_in() || !is_admin($_SESSION['userid'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = false;
$bookData = [];

// 🔴 اعتبارسنجی book_id
$bookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($bookId < 1) {
    header('Location: books.php');
    exit;
}

// دریافت اطلاعات کتاب فعلی
try {
    $book = new Book();
    $bookData = $book->getById($bookId);

    if (!$bookData) {
        $_SESSION['error'] = 'کتاب مورد نظر یافت نشد';
        secureRedirect('books.php');
    }
} catch (Exception $e) {
    logError('Get Book Error: ' . $e->getMessage());
    secureRedirect('books.php');
}

// پردازش ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_book'])) {

    // بررسی CSRF
    if (!CSRF::validate($_POST['csrf_token'] ?? '', 'edit_book')) {
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
            'book_image' => $_FILES['book_image'] ?? null,
            'remove_image' => isset($_POST['remove_image']) ? 1 : 0
        ];

        // قوانین اعتبارسنجی
        $rules = [
            'book_name' => 'required|min:2|max:255',
            'isbn' => "required|isbn|unique:books,isbn,{$bookId}",
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
                $updated = $book->update($bookId, $data);

                if ($updated) {
                    $_SESSION['book_edit_success'] = true;
                    secureRedirect('books.php?edit=ok');
                } else {
                    $errors[] = 'خطا در به‌روزرسانی کتاب';
                }
            } catch (Exception $e) {
                logError('Edit Book Error: ' . $e->getMessage());
                $errors[] = 'خطای سیستمی رخ داد. لطفاً دوباره تلاش کنید.';
            }
        }

        // در صورت خطا، داده‌های جدید را نگه دار
        if (!empty($errors)) {
            $bookData = array_merge($bookData, $data);
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
$csrfToken = CSRF::generate('edit_book');
?>

<div class="main">
    <div class="page-title">
        ویرایش کتاب
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
                    <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
            <button onclick="closeAlert('errorAlert')" class="close-btn">&times;</button>
        </div>
    <?php endif; ?>

    <form action="#" method="POST" enctype="multipart/form-data" id="editBookForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <label for="book_name">نام کتاب: <span class="required">*</span></label>
        <input type="text"
               name="book_name"
               id="book_name"
               value="<?php echo htmlspecialchars($bookData['book_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               required
               maxlength="255">

        <label for="isbn">شابک (ISBN): <span class="required">*</span></label>
        <input type="text"
               name="isbn"
               id="isbn"
               value="<?php echo htmlspecialchars($bookData['isbn'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               pattern="\d{10}|\d{13}"
               title="ISBN باید 10 یا 13 رقم باشد"
               required>
        <small class="help-text">فقط اعداد، 10 یا 13 رقم</small>

        <label for="cid">دسته‌بندی: <span class="required">*</span></label>
        <select name="cid" id="cid" required>
            <option value="">انتخاب کنید...</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['cid'] ?>"
                        <?php echo (isset($bookData['cid']) && $bookData['cid'] == $cat['cid']) ? 'selected' : '' ?>>
                    <?php echo htmlspecialchars($cat['cat_name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="author">نویسنده: <span class="required">*</span></label>
        <input type="text"
               name="author"
               id="author"
               value="<?php echo htmlspecialchars($bookData['author'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               required
               maxlength="255">

        <label for="publisher">ناشر:</label>
        <input type="text"
               name="publisher"
               id="publisher"
               value="<?php echo htmlspecialchars($bookData['publisher'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               maxlength="255">

        <label for="publish_year">سال انتشار (شمسی):</label>
        <input type="number"
               name="publish_year"
               id="publish_year"
               value="<?php echo htmlspecialchars($bookData['publish_year'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               min="1300"
               max="1450"
               placeholder="مثال: 1402">

        <label for="pages">تعداد صفحات:</label>
        <input type="number"
               name="pages"
               id="pages"
               value="<?php echo htmlspecialchars($bookData['pages'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               min="1"
               placeholder="مثال: 250">

        <label for="quantity">تعداد نسخه: <span class="required">*</span></label>
        <input type="number"
               name="quantity"
               id="quantity"
               value="<?php echo htmlspecialchars($bookData['quantity'] ?? '1', ENT_QUOTES, 'UTF-8') ?>"
               required
               min="1">

        <label for="description">توضیحات:</label>
        <textarea name="description"
                  id="description"
                  rows="4"
                  maxlength="2000"><?php echo htmlspecialchars($bookData['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

        <!-- نمایش تصویر فعلی -->
        <?php if (!empty($bookData['book_image'])): ?>
            <div class="current-image">
                <label>تصویر جلد فعلی:</label>
                <img src="../<?php echo htmlspecialchars($bookData['book_image'], ENT_QUOTES, 'UTF-8') ?>"
                     alt="جلد کتاب"
                     style="max-width: 200px; border-radius: 5px; display: block; margin: 10px 0;">
                <label>
                    <input type="checkbox" name="remove_image" value="1">
                    حذف تصویر فعلی
                </label>
            </div>
        <?php endif; ?>

        <label for="book_image">تصویر جدید جلد کتاب:</label>
        <input type="file"
               name="book_image"
               id="book_image"
               accept="image/jpeg,image/png,image/jpg">
        <small class="help-text">فرمت‌های مجاز: JPG, PNG (حداکثر 5MB)</small>

        <div id="imagePreview" class="image-preview"></div>

        <button class="submit" type="submit" name="edit_book">ذخیره تغییرات</button>
    </form>
</div>

<script src="assets/js/edit_book.js"></script>