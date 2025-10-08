<?php
// admin/inc/members.php
require_once '../classes/Member.php';
require_once '../classes/Database.php';

$db = Database::getInstance();
$member = new Member($db);

// مدیریت اکشن‌ها
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    switch ($action) {
        case 'delete':
            if ($id > 0) {
                try {
                    $member->softDelete($id);
                    header('Location: members.php?msg=deleted');
                    exit;
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
            break;
            
        case 'activate':
            if ($id > 0) {
                try {
                    $stmt = $db->prepare("UPDATE members SET is_active = 1 WHERE mid = ?");
                    $stmt->execute([$id]);
                    header('Location: members.php?msg=activated');
                    exit;
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
            break;
    }
}

// دریافت فیلترها
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$has_penalty = isset($_GET['has_penalty']) ? $_GET['has_penalty'] : 'all';

// ساخت کوئری
$sql = "SELECT m.*, 
        COUNT(DISTINCT r.rid) as total_reservations,
        COUNT(DISTINCT CASE WHEN r.status = 'active' THEN r.rid END) as active_reservations,
        SUM(CASE WHEN r.penalty_paid = 0 THEN r.penalty ELSE 0 END) as unpaid_penalties
        FROM members m
        LEFT JOIN reservations r ON m.mid = r.mid
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (m.name LIKE ? OR m.surname LIKE ? OR m.username LIKE ? OR m.national_code LIKE ?)";
    $searchParam = "%$search%";
    $params = array_fill(0, 4, $searchParam);
}

if ($status === 'active') {
    $sql .= " AND m.is_active = 1";
} elseif ($status === 'inactive') {
    $sql .= " AND m.is_active = 0";
}

$sql .= " GROUP BY m.mid";

if ($has_penalty === 'yes') {
    $sql .= " HAVING unpaid_penalties > 0";
} elseif ($has_penalty === 'no') {
    $sql .= " HAVING unpaid_penalties = 0 OR unpaid_penalties IS NULL";
}

$sql .= " ORDER BY m.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// آمار کلی
$stats_sql = "SELECT 
              COUNT(*) as total,
              COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
              COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive
              FROM members";
$stats = $db->query($stats_sql)->fetch(PDO::FETCH_ASSOC);
?>

<div class="main">
    <div class="page-title">
        <h1>مدیریت اعضا</h1>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <?php
            switch ($_GET['msg']) {
                case 'deleted':
                    echo 'عضو با موفقیت غیرفعال شد';
                    break;
                case 'activated':
                    echo 'عضو با موفقیت فعال شد';
                    break;
                case 'updated':
                    echo 'اطلاعات عضو با موفقیت به‌روزرسانی شد';
                    break;
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- آمار -->
    <div class="stats-container">
        <div class="stat-box">
            <h3><?= number_format($stats['total']) ?></h3>
            <p>کل اعضا</p>
        </div>
        <div class="stat-box stat-success">
            <h3><?= number_format($stats['active']) ?></h3>
            <p>اعضای فعال</p>
        </div>
        <div class="stat-box stat-warning">
            <h3><?= number_format($stats['inactive']) ?></h3>
            <p>اعضای غیرفعال</p>
        </div>
    </div>

    <!-- فرم جستجو و فیلتر -->
    <div class="filters-container">
        <form method="GET" action="" id="filter-form" class="filter-form">
            <div class="filter-group">
                <input type="text" 
                       name="search" 
                       id="search-member"
                       placeholder="جستجو (نام، نام خانوادگی، کد ملی، نام کاربری...)" 
                       value="<?= htmlspecialchars($search) ?>"
                       class="search-input">
            </div>
            
            <div class="filter-group">
                <label for="status-filter">وضعیت:</label>
                <select name="status" id="status-filter">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>همه</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>فعال</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="penalty-filter">جریمه:</label>
                <select name="has_penalty" id="penalty-filter">
                    <option value="all" <?= $has_penalty === 'all' ? 'selected' : '' ?>>همه</option>
                    <option value="yes" <?= $has_penalty === 'yes' ? 'selected' : '' ?>>دارای جریمه</option>
                    <option value="no" <?= $has_penalty === 'no' ? 'selected' : '' ?>>بدون جریمه</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
            <a href="members.php" class="btn btn-secondary">پاک کردن</a>
        </form>
    </div>

    <!-- جدول اعضا -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>شناسه</th>
                    <th>نام و نام خانوادگی</th>
                    <th>نام کاربری</th>
                    <th>کد ملی</th>
                    <th>موبایل</th>
                    <th>امانت فعال</th>
                    <th>کل امانت‌ها</th>
                    <th>جریمه معوقه</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="10" class="text-center">عضوی یافت نشد</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($members as $m): ?>
                        <tr class="<?= $m['is_active'] ? '' : 'inactive-row' ?>">
                            <td><?= $m['mid'] ?></td>
                            <td>
                                <a href="edit_member.php?id=<?= $m['mid'] ?>" class="member-name">
                                    <?= htmlspecialchars($m['name'] . ' ' . $m['surname']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($m['username']) ?></td>
                            <td class="ltr-text"><?= htmlspecialchars($m['national_code']) ?></td>
                            <td class="ltr-text"><?= htmlspecialchars($m['mobile']) ?></td>
                            <td>
                                <?php if ($m['active_reservations'] > 0): ?>
                                    <span class="badge badge-info"><?= $m['active_reservations'] ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">0</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $m['total_reservations'] ?></td>
                            <td>
                                <?php if ($m['unpaid_penalties'] > 0): ?>
                                    <span class="badge badge-danger">
                                        <?= number_format($m['unpaid_penalties']) ?> تومان
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-success">ندارد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($m['is_active']): ?>
                                    <span class="status-badge status-active">فعال</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive">غیرفعال</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-buttons">
                                <a href="edit_member.php?id=<?= $m['mid'] ?>" 
                                   class="btn btn-sm btn-edit" 
                                   title="ویرایش">
                                    ✏️
                                </a>
                                <?php if ($m['is_active']): ?>
                                    <button onclick="confirmDeactivate(<?= $m['mid'] ?>, '<?= htmlspecialchars($m['name']) ?>')" 
                                            class="btn btn-sm btn-delete" 
                                            title="غیرفعال کردن">
                                        🚫
                                    </button>
                                <?php else: ?>
                                    <button onclick="confirmActivate(<?= $m['mid'] ?>, '<?= htmlspecialchars($m['name']) ?>')" 
                                            class="btn btn-sm btn-success" 
                                            title="فعال کردن">
                                        ✅
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="assets/js/members.js"></script>
