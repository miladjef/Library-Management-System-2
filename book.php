<?php
// books.php
$title = 'لیست کتاب‌ها';
include "inc/header.php";

require_once 'classes/Book.php';
require_once 'classes/Category.php';

$db = Database::getInstance();
$book = new Book($db);
$category = new Category($db);

// دریافت پارامترهای جستجو و فیلتر
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$author_filter = $_GET['author'] ?? '';
$year_filter = $_GET['year'] ?? '';
$available_only = isset($_GET['available_only']);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;

// محاسبه offset
$offset = ($page - 1) * $per_page;

// ساخت کوئری
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(b.book_name LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($category_filter)) {
    $where_conditions[] = "b.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($author_filter)) {
    $where_conditions[] = "b.author LIKE ?";
    $params[] = "%$author_filter%";
}

if (!empty($year_filter)) {
    $where_conditions[] = "b.publish_year = ?";
    $params[] = $year_filter;
}

if ($available_only) {
    $where_conditions[] = "(b.book_quantity - COALESCE(active_reservations.count, 0)) > 0";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// کوئری اصلی
$query = "
    SELECT
        b.*,
        c.category_name,
        (b.book_quantity - COALESCE(active_reservations.count, 0)) as available_count,
        COALESCE(active_reservations.count, 0) as borrowed_count
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.cid
    LEFT JOIN (
        SELECT bid, COUNT(*) as count
        FROM reservations
        WHERE status = 'active'
        GROUP BY bid
    ) active_reservations ON b.bid = active_reservations.bid
    $where_clause
    ORDER BY b.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// شمارش کل نتایج برای pagination
$count_query = str_replace('SELECT b.*, c.category_name, (b.book_quantity - COALESCE(active_reservations.count, 0)) as available_count, COALESCE(active_reservations.count, 0) as borrowed_count', 'SELECT COUNT(*)', $query);
$count_query = str_replace("LIMIT $per_page OFFSET $offset", '', $count_query);
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_books = $count_stmt->fetchColumn();
$total_pages = ceil($total_books / $per_page);

// دریافت تمام دسته‌ها برای فیلتر
$categories = $category->getAll();
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-book-open"></i>
            کتاب‌های کتابخانه
        </h1>
        <p class="page-subtitle">
            مجموعه‌ای از بهترین کتاب‌های موجود در کتابخانه ما
        </p>
    </div>

    <!-- فیلترها و جستجو -->
    <div class="filters-section">
        <form method="GET" action="" class="filters-form">
            <div class="search-box">
                <input type="text" name="search" placeholder="جستجو در نام کتاب، نویسنده یا ISBN..."
                       value="<?php echo  htmlspecialchars($search) ?>" class="search-input">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </div>

            <div class="filter-row">
                <div class="filter-group">
                    <label>دسته‌بندی:</label>
                    <select name="category" class="filter-select">
                        <option value="">همه دسته‌ها</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo  $cat['cid'] ?>"
                                    <?php echo  $category_filter == $cat['cid'] ? 'selected' : '' ?>>
                                <?php echo  $cat['category_name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>نویسنده:</label>
                    <input type="text" name="author" placeholder="نام نویسنده"
                           value="<?php echo  htmlspecialchars($author_filter) ?>" class="filter-input">
                </div>

                <div class="filter-group">
                    <label>سال انتشار:</label>
                    <input type="number" name="year" placeholder="1400" min="1300" max="1410"
                           value="<?php echo  htmlspecialchars($year_filter) ?>" class="filter-input">
                </div>

                <div class="filter-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="available_only"
                               <?php echo  $available_only ? 'checked' : '' ?> class="filter-checkbox">
                        <span class="checkmark"></span>
                        فقط کتاب‌های موجود
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    اعمال فیلتر
                </button>

                <a href="books.php" class="btn btn-outline">
                    <i class="fas fa-times"></i>
                    پاک کردن
                </a>
            </div>
        </form>
    </div>

    <!-- نتایج -->
    <div class="results-info">
        <span class="results-count">
            <?php echo  number_format($total_books) ?> کتاب یافت شد
        </span>
        <?php if (!empty($search)): ?>
            <span class="search-term">برای: "<?php echo  htmlspecialchars($search) ?>"</span>
        <?php endif; ?>
    </div>

    <!-- لیست کتاب‌ها -->
    <div class="books-grid">
        <?php if (empty($books)): ?>
            <div class="no-results">
                <i class="fas fa-book-open"></i>
                <h3>کتابی یافت نشد</h3>
                <p>لطفا فیلترهای جستجو را تغییر دهید</p>
            </div>
        <?php else: ?>
            <?php foreach ($books as $book_item): ?>
                <div class="book-card">
                    <div class="book-image">
                        <img src="<?php echo  IMG_PATH . $book_item['image'] ?>"
                             alt="<?php echo  htmlspecialchars($book_item['book_name']) ?>"
                             onerror="this.src='assets/img/no-image.jpg'">

                        <?php if ($book_item['available_count'] > 0): ?>
                            <div class="availability-badge available">
                                <i class="fas fa-check-circle"></i>
                                موجود
                            </div>
                        <?php else: ?>
                            <div class="availability-badge unavailable">
                                <i class="fas fa-times-circle"></i>
                                تمام شده
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="book-info">
                        <h3 class="book-title">
                            <a href="book.php?id=<?php echo  $book_item['bid'] ?>">
                                <?php echo  htmlspecialchars($book_item['book_name']) ?>
                            </a>
                        </h3>

                        <p class="book-author">
                            <i class="fas fa-user"></i>
                            <?php echo  htmlspecialchars($book_item['author']) ?>
                        </p>

                        <p class="book-category">
                            <i class="fas fa-tag"></i>
                            <?php echo  htmlspecialchars($book_item['category_name']) ?>
                        </p>

                        <p class="book-year">
                            <i class="fas fa-calendar"></i>
                            <?php echo  $book_item['publish_year'] ?>
                        </p>

                        <div class="book-stats">
                            <span class="stat">
                                <i class="fas fa-copy"></i>
                                <?php echo  $book_item['book_quantity'] ?> نسخه
                            </span>
                            <span class="stat available-stat">
                                <i class="fas fa-check"></i>
                                <?php echo  $book_item['available_count'] ?> موجود
                            </span>
                        </div>
                    </div>

                    <div class="book-actions">
                        <a href="book.php?id=<?php echo  $book_item['bid'] ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-eye"></i>
                            مشاهده جزئیات
                        </a>

                        <?php if ($user_logged_in && $book_item['available_count'] > 0): ?>
                            <a href="reserve.php?book_id=<?php echo  $book_item['bid'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-bookmark"></i>
                                رزرو کتاب
                            </a>
                        <?php elseif (!$user_logged_in): ?>
                            <a href="login.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-sign-in-alt"></i>
                                ورود برای رزرو
                            </a>
                        <?php else: ?>
                            <button class="btn btn-disabled btn-sm" disabled>
                                <i class="fas fa-times"></i>
                                موجود نیست
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper">
            <nav class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo  http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                       class="pagination-btn">
                        <i class="fas fa-chevron-right"></i>
                        قبلی
                    </a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);

                if ($start_page > 1): ?>
                    <a href="?<?php echo  http_build_query(array_merge($_GET, ['page' => 1])) ?>"
                       class="pagination-number">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="pagination-dots">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?<?php echo  http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                       class="pagination-number <?php echo  $i == $page ? 'active' : '' ?>"><?php echo  $i ?></a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="pagination-dots">...</span>
                    <?php endif; ?>
                    <a href="?<?php echo  http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"
                       class="pagination-number"><?php echo  $total_pages ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo  http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                       class="pagination-btn">
                        بعدی
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
            </nav>

            <div class="pagination-info">
                صفحه <?php echo  $page ?> از <?php echo  $total_pages ?>
                (<?php echo  number_format($total_books) ?> کتاب)
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// اتوکامپلیت برای فیلد نویسنده
$(document).ready(function() {
    $('input[name="author"]').on('input', function() {
        const query = $(this).val();
        if (query.length >= 2) {
            $.ajax({
                url: 'api/search_authors.php',
                method: 'GET',
                data: { q: query },
                success: function(data) {
                    // پیاده‌سازی autocomplete
                }
            });
        }
    });
});
</script>

<?php include "inc/footer.php"; ?>
