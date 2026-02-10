<?php
/**
 * نتایج جستجوی کتاب
 * این فایل توسط search.php فراخوانی می‌شود
 */

// بررسی دسترسی مستقیم
if (!defined('BASEPATH') && basename($_SERVER['PHP_SELF']) === 'search.php') {
    // OK - فراخوانی از search.php
} elseif (!isset($_SESSION)) {
    die('دسترسی مستقیم مجاز نیست');
}

require_once __DIR__ . '/functions.php';

// دریافت و پاکسازی پارامتر جستجو
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$category_id = isset($_GET['cat']) ? filter_var($_GET['cat'], FILTER_VALIDATE_INT) : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$books = [];
$total_books = 0;
$error_message = '';

if (!empty($search_query) || $category_id) {
    
    // اعتبارسنجی طول جستجو
    if (!empty($search_query) && strlen($search_query) < 2) {
        $error_message = 'عبارت جستجو باید حداقل ۲ کاراکتر باشد';
    } elseif (strlen($search_query) > 100) {
        $error_message = 'عبارت جستجو نباید بیشتر از ۱۰۰ کاراکتر باشد';
    } else {
        
        try {
            global $conn;
            
            // ساخت کوئری امن با Prepared Statement
            $where_conditions = [];
            $params = [];
            
            if (!empty($search_query)) {
                // Escape کردن کاراکترهای خاص LIKE
                $search_escaped = str_replace(['%', '_'], ['\%', '\_'], $search_query);
                $search_param = '%' . $search_escaped . '%';
                
                $where_conditions[] = "(
                    book_name LIKE ? OR 
                    author LIKE ? OR 
                    publisher LIKE ? OR 
                    isbn LIKE ? OR
                    description LIKE ?
                )";
                $params = array_merge($params, [
                    $search_param, 
                    $search_param, 
                    $search_param, 
                    $search_param,
                    $search_param
                ]);
            }
            
            if ($category_id) {
                $where_conditions[] = "cid = ?";
                $params[] = $category_id;
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            // شمارش کل نتایج
            $count_sql = "SELECT COUNT(*) FROM books {$where_clause}";
            $stmt = $conn->prepare($count_sql);
            $stmt->execute($params);
            $total_books = $stmt->fetchColumn();
            
            // دریافت نتایج با صفحه‌بندی
            $sql = "
                SELECT 
                    b.bid,
                    b.book_name,
                    b.author,
                    b.publisher,
                    b.publish_year,
                    b.isbn,
                    b.book_img,
                    b.available_quantity,
                    b.quantity,
                    c.cat_name
                FROM books b
                LEFT JOIN category c ON b.cid = c.cid
                {$where_clause}
                ORDER BY b.book_name ASC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $per_page;
            $params[] = $offset;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ثبت لاگ جستجو
            log_activity('book_search', [
                'query' => $search_query,
                'category' => $category_id,
                'results_count' => $total_books
            ]);
            
        } catch (PDOException $e) {
            $error_message = 'خطا در جستجو. لطفاً دوباره تلاش کنید.';
            log_error('search_error', $e->getMessage());
        }
    }
}

// محاسبه صفحات
$total_pages = ceil($total_books / $per_page);
?>

<div class="container mt-4">
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($search_query) || $category_id): ?>
        
        <!-- نمایش تعداد نتایج -->
        <div class="mb-4">
            <h5>
                <i class="fas fa-search me-2"></i>
                <?php if (!empty($search_query)): ?>
                    نتایج جستجو برای: 
                    <span class="text-primary">"<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>"</span>
                <?php endif; ?>
                <span class="badge bg-secondary"><?php echo number_format($total_books); ?> کتاب</span>
            </h5>
        </div>
        
        <?php if (empty($books)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                کتابی با این مشخصات یافت نشد.
            </div>
        <?php else: ?>
            
            <!-- نمایش کتاب‌ها -->
            <div class="row">
                <?php foreach ($books as $book): ?>
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="card h-100 book-card">
                            <!-- تصویر کتاب -->
                            <div class="card-img-top book-cover">
                                <?php if (!empty($book['book_img'])): ?>
                                    <img src="<?php echo htmlspecialchars(siteurl() . '/uploads/books/' . $book['book_img'], ENT_QUOTES, 'UTF-8'); ?>" 
                                         alt="<?php echo htmlspecialchars($book['book_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                         class="img-fluid"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="no-cover">
                                        <i class="fas fa-book fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body">
                                <h6 class="card-title">
                                    <?php echo htmlspecialchars($book['book_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </h6>
                                <p class="card-text text-muted small">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($book['author'] ?? 'نامشخص', ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <p class="card-text text-muted small">
                                    <i class="fas fa-building me-1"></i>
                                    <?php echo htmlspecialchars($book['publisher'] ?? 'نامشخص', ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                
                                <!-- وضعیت موجودی -->
                                <?php if ($book['available_quantity'] > 0): ?>
                                    <span class="badge bg-success">
                                        موجود (<?php echo (int)$book['available_quantity']; ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">ناموجود</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-footer bg-transparent">
                                <a href="book.php?id=<?php echo (int)$book['bid']; ?>" 
                                   class="btn btn-sm btn-outline-primary w-100">
                                    <i class="fas fa-eye me-1"></i>
                                    مشاهده جزئیات
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- صفحه‌بندی -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        
                        <!-- صفحه قبل -->
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- شماره صفحات -->
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- صفحه بعد -->
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php endif; ?>
        
    <?php else: ?>
        
        <!-- صفحه اولیه بدون جستجو -->
        <div class="text-center py-5">
            <i class="fas fa-search fa-4x text-muted mb-4"></i>
            <h4>جستجو در کتابخانه</h4>
            <p class="text-muted">برای یافتن کتاب مورد نظر، عبارتی را در کادر بالا وارد کنید.</p>
        </div>
        
    <?php endif; ?>
    
</div>

<style>
.book-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.book-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.book-cover {
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    overflow: hidden;
}
.book-cover img {
    max-height: 100%;
    object-fit: contain;
}
.no-cover {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    width: 100%;
}
</style>
