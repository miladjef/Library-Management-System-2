<?php
// my-reservations.php
if (!$user_logged_in) {
    header('Location: login.php');
    exit;
}

require_once 'classes/Reservation.php';

$db = Database::getInstance();
$reservation = new Reservation($db);

$member_id = $_SESSION['userid'];
$title = 'امانت‌های من';
include "inc/header.php";

// پردازش تمدید
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['extend_reservation'])) {
    $reservation_id = $_POST['reservation_id'];
    $result = $reservation->extend($reservation_id);
    
    if ($result['success']) {
        $success = 'امانت با موفقیت تمدید شد';
    } else {
        $error = $result['message'];
    }
}

// دریافت فیلترها
$status_filter = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// ساخت کوئری
$where_conditions = ["r.mid = ?"];
$params = [$member_id];

if ($status_filter != 'all') {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// کوئری اصلی
$query = "
    SELECT 
        r.*,
        b.book_name,
        b.author,
        b.image,
        c.category_name,
        DATEDIFF(r.due_date, CURDATE()) as days_remaining,
        CASE 
            WHEN r.status = 'active' AND CURDATE() > r.due_date THEN 'overdue'
            ELSE r.status
        END as actual_status
    FROM reservations r
    JOIN books b ON r.bid = b.bid
    JOIN categories c ON b.category_id = c.cid
    $where_clause
    ORDER BY r.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// شمارش کل
$count_query = "
    SELECT COUNT(*) 
    FROM reservations r
    $where_clause
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_reservations = $count_stmt->fetchColumn();
$total_pages = ceil($total_reservations / $per_page);

// آمار کلی
$stats_query = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
        COUNT(CASE WHEN status = 'returned' THEN 1 END) as returned,
        COUNT(CASE WHEN status = 'active' AND CURDATE() > due_date THEN 1 END) as overdue
    FROM reservations 
    WHERE mid = ?
");
$stats_query->execute([$member_id]);
$stats = $stats_query->fetch(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="reservations-page">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-history"></i>
                امانت‌های من
            </h1>
            <a href="books.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                امانت جدید
            </a>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- آمار سریع -->
        <div class="reservations-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">کل امانت‌ها</div>
                </div>
            </div>
            
            <div class="stat-card active">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['active'] ?></div>
                    <div class="stat-label">امانت فعال</div>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['returned'] ?></div>
                    <div class="stat-label">برگشت داده شده</div>
                </div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $stats['overdue'] ?></div>
                    <div class="stat-label">معوقه</div>
                </div>
            </div>
        </div>

        <!-- فیلترها -->
        <div class="filters-bar">
            <div class="filter-tabs">
                <a href="?status=all" class="filter-tab <?= $status_filter == 'all' ? 'active' : '' ?>">
                    همه
                </a>
                <a href="?status=active" class="filter-tab <?= $status_filter == 'active' ? 'active' : '' ?>">
                    فعال
                </a>
                <a href="?status=returned" class="filter-tab <?= $status_filter == 'returned' ? 'active' : '' ?>">
                    برگشت داده شده
                </a>
            </div>
            
            <div class="results-count">
                <?= number_format($total_reservations) ?> امانت
            </div>
        </div>

        <!-- لیست امانت‌ها -->
        <div class="reservations-list">
            <?php if (empty($reservations)): ?>
                <div class="no-results">
                    <i class="fas fa-book-open"></i>
                    <h3>هیچ امانتی یافت نشد</h3>
                    <p>شما هنوز هیچ کتابی را امانت نگرفته‌اید</p>
                    <a href="books.php" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        جستجوی کتاب
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($reservations as $res): ?>
                    <div class="reservation-card">
                        <div class="reservation-image">
                            <img src="<?= IMG_PATH . $res['image'] ?>" 
                                 alt="<?= htmlspecialchars($res['book_name']) ?>"
                                 onerror="this.src='assets/img/no-image.jpg'">
                        </div>
                        
                        <div class="reservation-details">
                            <div class="reservation-header">
                                <h3 class="reservation-title">
                                    <a href="book.php?id=<?= $res['bid'] ?>">
                                        <?= htmlspecialchars($res['book_name']) ?>
                                    </a>
                                </h3>
                                <div class="reservation-status">
                                    <?php
                                    $status_classes = [
                                        'active' => 'status-active',
                                        'returned' => 'status-success',
                                        'overdue' => 'status-danger',
                                        'cancelled' => 'status-secondary'
                                    ];
                                    $status_labels = [
                                        'active' => 'فعال',
                                        'returned' => 'برگشت داده شده',
                                        'overdue' => 'معوقه',
                                        'cancelled' => 'لغو شده'
                                    ];
                                    $status_class = $status_classes[$res['actual_status']] ?? 'status-secondary';
                                    $status_label = $status_labels[$res['actual_status']] ?? $res['actual_status'];
                                    ?>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?= $status_label ?>
                                    </span>
                                </div>
                            </div>
                            
                            <p class="reservation-author">
                                <i class="fas fa-user"></i>
                                <?= htmlspecialchars($res['author']) ?>
                            </p>
                            
                            <p class="reservation-category">
                                <i class="fas fa-tag"></i>
                                <?= htmlspecialchars($res['category_name']) ?>
                            </p>
                            
                            <div class="reservation-dates">
                                <div class="date-item">
                                    <i class="fas fa-calendar-plus"></i>
                                    <span class="date-label">تاریخ امانت:</span>
                                    <span class="date-value"><?= jdate('Y/m/d', strtotime($res['borrow_date'])) ?></span>
                                </div>
                                
                                <div class="date-item">
                                    <i class="fas fa-calendar-check"></i>
                                    <span class="date-label">تاریخ بازگشت:</span>
                                    <span class="date-value"><?= jdate('Y/m/d', strtotime($res['due_date'])) ?></span>
                                </div>
                                
                                <?php if ($res['actual_status'] == 'active'): ?>
                                    <div class="date-item">
                                        <i class="fas fa-clock"></i>
                                        <span class="date-label">زمان باقیمانده:</span>
                                        <span class="date-value <?= $res['days_remaining'] < 0 ? 'text-danger' : '' ?>">
                                            <?php if ($res['days_remaining'] >= 0): ?>
                                                <?= $res['days_remaining'] ?> روز
                                            <?php else: ?>
                                                <?= abs($res['days_remaining']) ?> روز تأخیر
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($res['actual_status'] == 'returned' && $res['return_date']): ?>
                                    <div class="date-item">
                                        <i class="fas fa-undo"></i>
                                        <span class="date-label">تاریخ بازگشت واقعی:</span>
                                        <span class="date-value"><?= jdate('Y/m/d', strtotime($res['return_date'])) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($res['extension_count'] > 0): ?>
                                <div class="extension-info">
                                    <i class="fas fa-redo"></i>
                                    این امانت <?= $res['extension_count'] ?> بار تمدید شده است
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($res['penalty_amount'] > 0): ?>
                                <div class="penalty-info">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    جریمه: <?= number_format($res['penalty_amount']) ?> تومان
                                    <?php if ($res['penalty_status'] == 'paid'): ?>
                                        <span class="penalty-paid">(پرداخت شده)</span>
                                    <?php else: ?>
                                        <span class="penalty-unpaid">(پرداخت نشده)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="reservation-actions">
                            <a href="book.php?id=<?= $res['bid'] ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-eye"></i>
                                مشاهده کتاب
                            </a>
                            
                            <?php if ($res['actual_status'] == 'active'): ?>
                                <?php
                                // بررسی امکان تمدید
                                $max_extensions = 2; // از تنظیمات سیستم بخوانید
                                $can_extend = $res['extension_count'] < $max_extensions && $res['days_remaining'] >= -3;
                                ?>
                                
                                <?php if ($can_extend): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="reservation_id" value="<?= $res['rid'] ?>">
                                        <button type="submit" name="extend_reservation" class="btn btn-primary btn-sm"
                                                onclick="return confirm('آیا می‌خواهید این امانت را تمدید کنید؟')">
                                            <i class="fas fa-redo"></i>
                                            تمدید (<?= $max_extensions - $res['extension_count'] ?> بار باقیمانده)
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-disabled btn-sm" disabled>
                                        <i class="fas fa-times"></i>
                                        امکان تمدید نیست
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($res['penalty_amount'] > 0 && $res['penalty_status'] == 'unpaid'): ?>
                                <a href="payment.php?reservation_id=<?= $res['rid'] ?>" class="btn btn-danger btn-sm">
                                    <i class="fas fa-credit-card"></i>
                                    پرداخت جریمه
                                </a>
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
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                           class="pagination-btn">
                            <i class="fas fa-chevron-right"></i>
                            قبلی
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="pagination-number <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                           class="pagination-btn">
                            بعدی
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "inc/footer.php"; ?>
