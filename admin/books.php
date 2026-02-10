<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Book.php';

// پردازش حذف کتاب
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $bookId = intval($_GET['id']);

    if ($bookId > 0) {
        try {
            $book = new Book();

            // بررسی امکان حذف (نباید رزرو فعال داشته باشد)
            if ($book->canDelete($bookId)) {
                if ($book->delete($bookId)) {
                    $_SESSION['book_delete_success'] = true;
                    secureRedirect('books.php?delete=ok');
                } else {
                    $_SESSION['error'] = 'خطا در حذف کتاب';
                    secureRedirect('books.php');
                }
            } else {
                $_SESSION['error'] = 'این کتاب دارای رزرو فعال است و قابل حذف نیست';
                secureRedirect('books.php');
            }
        } catch (Exception $e) {
            logError('Delete Book Error: ' . $e->getMessage());
            $_SESSION['error'] = 'خطای سیستمی رخ داد';
            secureRedirect('books.php');
        }
    }
}

// دریافت پارامترهای جستجو و فیلتر
$searchTerm = trim($_GET['search'] ?? '');
$categoryFilter = intval($_GET['category'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

try {
    $book = new Book();
    $category = new Category();

    // دریافت لیست کتاب‌ها
    $filters = [
        'search' => $searchTerm,
        'category_id' => $categoryFilter > 0 ? $categoryFilter : null,
        'page' => $page,
        'per_page' => $perPage
    ];

    $result = $book->getAll($filters);
    $books = $result['data'];
    $totalBooks = $result['total'];
    $totalPages = ceil($totalBooks / $perPage);

    // دریافت لیست دسته‌بندی‌ها برای فیلتر
    $categories = $category->getAll();

} catch (Exception $e) {
    logError('Get Books Error: ' . $e->getMessage());
    $books = [];
    $totalBooks = 0;
    $totalPages = 0;
    $categories = [];
}
?>

<div class="main">
    <div class="page-title">
        مدیریت کتاب‌ها
        <span class="badge"><?php echo  number_format($totalBooks) ?> کتاب</span>
    </div>

    <!-- پیام‌های موفقیت -->
    <?php if (isset($_SESSION['book_add_success'])): ?>
        <div class="alert alert-success" id="successAlert">
            ✅ کتاب جدید با موفقیت اضافه شد
            <button onclick="closeAlert('successAlert')" class="close-btn">&times;</button>
        </div>
        <?php unset($_SESSION['book_add_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['book_edit_success'])): ?>
        <div class="alert alert-success" id="successAlert">
            ✅ کتاب با موفقیت به‌روزرسانی شد
            <button onclick="closeAlert('successAlert')" class="close-btn">&times;</button>
        </div>
        <?php unset($_SESSION['book_edit_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['book_delete_success'])): ?>
        <div class="alert alert-success" id="successAlert">
            ✅ کتاب با موفقیت حذف شد
            <button onclick="closeAlert('successAlert')" class="close-btn">&times;</button>
        </div>
        <?php unset($_SESSION['book_delete_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error" id="errorAlert">
            ❌ <?php echo  htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
            <button onclick="closeAlert('errorAlert')" class="close-btn">&times;</button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- دکمه افزودن کتاب جدید -->
    <a href="add_book.php">
        <div class="add-button">
            ➕ افزودن کتاب جدید
        </div>
    </a>

    <!-- فرم جستجو و فیلتر -->
    <div class="search-filter-section">
        <form action="books.php" method="GET" class="search-form">
            <div class="search-group">
                <input type="text"
                       name="search"
                       placeholder="جستجو بر اساس نام، نویسنده، ناشر یا ISBN..."
                       value="<?php echo  htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>">

                <select name="category" onchange="this.form.submit()">
                    <option value="0">همه دسته‌بندی‌ها</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo  $cat['cid'] ?>"
                                <?php echo  $categoryFilter == $cat['cid'] ? 'selected' : '' ?>>
                            <?php echo  htmlspecialchars($cat['cat_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-search">🔍 جستجو</button>

                <?php if ($searchTerm || $categoryFilter): ?>
                    <a href="books.php" class="btn-clear">✖ پاک کردن فیلتر</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- جدول کتاب‌ها -->
    <?php if (empty($books)): ?>
        <div class="no-data">
            📚 هیچ کتابی یافت نشد
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>تصویر</th>
                        <th>نام کتاب</th>
                        <th>ISBN</th>
                        <th>نویسنده</th>
                        <th>دسته‌بندی</th>
                        <th>موجودی</th>
                        <th>امانت داده شده</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $row = ($page - 1) * $perPage + 1;
                    foreach ($books as $book):
                    ?>
                        <tr>
                            <td><?php echo  $row++ ?></td>
                            <td>
                                <?php if (!empty($book['book_image'])): ?>
                                    <img src="../<?php echo  htmlspecialchars($book['book_image'], ENT_QUOTES, 'UTF-8') ?>"
                                         alt="جلد کتاب"
                                         class="book-thumbnail"
                                         onclick="showImageModal(this.src)">
                                <?php else: ?>
                                    <div class="no-image">🚫</div>
                                <?php endif; ?>
                            </td>
                            <td class="book-title">
                                <strong><?php echo  htmlspecialchars($book['book_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if (!empty($book['publisher'])): ?>
                                    <br><small>ناشر: <?php echo  htmlspecialchars($book['publisher'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="isbn-cell">
                                <code><?php echo  htmlspecialchars($book['isbn'], ENT_QUOTES, 'UTF-8') ?></code>
                            </td>
                            <td><?php echo  htmlspecialchars($book['author'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge badge-category">
                                    <?php echo  htmlspecialchars($book['cat_name'] ?? 'نامشخص', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo  $book['quantity'] > 0 ? 'badge-success' : 'badge-danger' ?>">
                                    <?php echo  $book['quantity'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo  $book['borrowed_count'] ?? 0 ?>
                                </span>
                            </td>
                            <td class="actions-cell">
                                <a href="edit_book.php?id=<?php echo  $book['bid'] ?>"
                                   class="btn-edit"
                                   title="ویرایش">
                                    ✏️
                                </a>
                                <a href="#"
                                   onclick="confirmDelete(<?php echo  $book['bid'] ?>, '<?php echo  htmlspecialchars($book['book_name'], ENT_QUOTES, 'UTF-8') ?>')"
                                   class="btn-delete"
                                   title="حذف">
                                    🗑️
                                </a>
                                <a href="book_details.php?id=<?php echo  $book['bid'] ?>"
                                   class="btn-view"
                                   title="جزئیات">
                                    👁️
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- صفحه‌بندی -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo  $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?><?php echo  $categoryFilter ? '&category=' . $categoryFilter : '' ?>"
                       class="page-link">اولین</a>
                    <a href="?page=<?php echo  $page - 1 ?><?php echo  $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?><?php echo  $categoryFilter ? '&category=' . $categoryFilter : '' ?>"
                       class="page-link">قبلی</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);

                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?page=<?php echo  $i ?><?php echo  $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?><?php echo  $categoryFilter ? '&category=' . $categoryFilter : '' ?>"
                       class="page-link <?php echo  $i == $page ? 'active' : '' ?>">
                        <?php echo  $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo  $page + 1 ?><?php echo  $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?><?php echo  $categoryFilter ? '&category=' . $categoryFilter : '' ?>"
                       class="page-link">بعدی</a>
                    <a href="?page=<?php echo  $totalPages ?><?php echo  $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?><?php echo  $categoryFilter ? '&category=' . $categoryFilter : '' ?>"
                       class="page-link">آخرین</a>
                <?php endif; ?>
            </div>

            <div class="pagination-info">
                صفحه <?php echo  $page ?> از <?php echo  $totalPages ?> (مجموع: <?php echo  number_format($totalBooks) ?> کتاب)
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal نمایش تصویر -->
<div id="imageModal" class="modal" onclick="closeImageModal()">
    <span class="close">&times;</span>
    <img class="modal-content" id="modalImage">
</div>

<script src="assets/js/books.js"></script>
