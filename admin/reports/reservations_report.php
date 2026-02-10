<?php
// admin/inc/reports/reservations_report.php

$query = "
    SELECT
        r.rid,
        r.reservation_date,
        r.return_date,
        r.actual_return_date,
        r.status,
        r.penalty,
        r.penalty_paid,
        b.book_name,
        b.isbn,
        m.name,
        m.surname,
        m.national_code,
        c.category_name
    FROM reservations r
    JOIN books b ON r.bid = b.bid
    JOIN members m ON r.mid = m.mid
    JOIN categories c ON b.category_id = c.cid
    WHERE 1=1
";

$params = [];

if (isset($from_date_sql)) {
    $query .= " AND r.reservation_date >= ?";
    $params[] = $from_date_sql;
}

if (isset($to_date_sql)) {
    $query .= " AND r.reservation_date <= ?";
    $params[] = $to_date_sql;
}

$query .= " ORDER BY r.reservation_date DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// محاسبه آمار کلی
$total_reservations = count($reservations);
$active_count = count(array_filter($reservations, fn($r) => $r['status'] == 'active'));
$returned_count = count(array_filter($reservations, fn($r) => $r['status'] == 'returned'));
$total_penalties = array_sum(array_column($reservations, 'penalty'));
$unpaid_penalties = array_sum(array_map(fn($r) => $r['penalty_paid'] ? 0 : $r['penalty'], $reservations));
?>

<div class="report-summary">
    <h3>خلاصه گزارش امانت‌ها</h3>
    <div class="summary-grid">
        <div class="summary-item">
            <span class="summary-label">کل امانت‌ها:</span>
            <span class="summary-value"><?php echo  number_format($total_reservations) ?></span>
        </div>
        <div class="summary-item">
            <span class="summary-label">امانت‌های فعال:</span>
            <span class="summary-value"><?php echo  number_format($active_count) ?></span>
        </div>
        <div class="summary-item">
            <span class="summary-label">برگشت داده شده:</span>
            <span class="summary-value"><?php echo  number_format($returned_count) ?></span>
        </div>
        <div class="summary-item">
            <span class="summary-label">کل جریمه‌ها:</span>
            <span class="summary-value"><?php echo  number_format($total_penalties) ?> تومان</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">جریمه‌های پرداخت نشده:</span>
            <span class="summary-value text-danger"><?php echo  number_format($unpaid_penalties) ?> تومان</span>
        </div>
    </div>
</div>

<div class="report-table">
    <table class="data-table">
        <thead>
            <tr>
                <th>شناسه</th>
                <th>نام کتاب</th>
                <th>دسته</th>
                <th>عضو</th>
                <th>کد ملی</th>
                <th>تاریخ امانت</th>
                <th>تاریخ بازگشت</th>
                <th>تاریخ واقعی بازگشت</th>
                <th>وضعیت</th>
                <th>جریمه</th>
                <th>پرداخت</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reservations as $r): ?>
            <tr>
                <td><?php echo  $r['rid'] ?></td>
                <td><?php echo  $r['book_name'] ?></td>
                <td><?php echo  $r['category_name'] ?></td>
                <td><?php echo  $r['name'] . ' ' . $r['surname'] ?></td>
                <td><?php echo  $r['national_code'] ?></td>
                <td><?php echo  jdate('Y/m/d', strtotime($r['reservation_date'])) ?></td>
                <td><?php echo  jdate('Y/m/d', strtotime($r['return_date'])) ?></td>
                <td><?php echo  $r['actual_return_date'] ? jdate('Y/m/d', strtotime($r['actual_return_date'])) : '-' ?></td>
                <td>
                    <span class="status-badge status-<?php echo  $r['status'] ?>">
                        <?php echo  $r['status'] == 'active' ? 'فعال' : 'برگشت داده شده' ?>
                    </span>
                </td>
                <td><?php echo  $r['penalty'] > 0 ? number_format($r['penalty']) . ' تومان' : '-' ?></td>
                <td>
                    <?php if ($r['penalty'] > 0): ?>
                        <span class="payment-status <?php echo  $r['penalty_paid'] ? 'paid' : 'unpaid' ?>">
                            <?php echo  $r['penalty_paid'] ? '✓ پرداخت شده' : '✗ پرداخت نشده' ?>
                        </span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
