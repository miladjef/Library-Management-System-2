<?php
// admin/inc/dashboard.php
?>
<div class="main">
    <div class="page-title">
        داشبورد مدیریتی
    </div>

    <!-- بخش آمار کلی -->
    <div class="stats-container">
        <?php
        // دریافت آمار از ویو dashboard_stats
        $stats_query = $conn->prepare("SELECT * FROM dashboard_stats LIMIT 1");
        $stats_query->execute();
        $stats = $stats_query->fetch(PDO::FETCH_ASSOC);

        // اگر ویو خالی بود، مقادیر پیش‌فرض
        if (!$stats) {
            $stats = [
                'total_books' => 0,
                'total_members' => 0,
                'active_reservations' => 0,
                'overdue_reservations' => 0,
                'total_penalties' => 0,
                'unpaid_penalties' => 0
            ];
        }
        ?>

        <div class="stat-card stat-books">
            <div class="stat-icon">📚</div>
            <div class="stat-info">
                <h3><?php echo  number_format($stats['total_books']) ?></h3>
                <p>تعداد کل کتاب‌ها</p>
            </div>
        </div>

        <div class="stat-card stat-members">
            <div class="stat-icon">👥</div>
            <div class="stat-info">
                <h3><?php echo  number_format($stats['total_members']) ?></h3>
                <p>تعداد اعضای فعال</p>
            </div>
        </div>

        <div class="stat-card stat-active">
            <div class="stat-icon">📖</div>
            <div class="stat-info">
                <h3><?php echo  number_format($stats['active_reservations']) ?></h3>
                <p>امانت‌های فعال</p>
            </div>
        </div>

        <div class="stat-card stat-overdue">
            <div class="stat-icon">⏰</div>
            <div class="stat-info">
                <h3><?php echo  number_format($stats['overdue_reservations']) ?></h3>
                <p>امانت‌های معوقه</p>
            </div>
        </div>

        <div class="stat-card stat-penalty">
            <div class="stat-icon">💰</div>
            <div class="stat-info">
                <h3><?php echo  number_format($stats['unpaid_penalties']) ?> تومان</h3>
                <p>جریمه‌های پرداخت نشده</p>
            </div>
        </div>

        <div class="stat-card stat-total-penalty">
            <div class="stat-icon">💵</div>
            <div class="stat-info">
                <h3><?php echo  number_format($stats['total_penalties']) ?> تومان</h3>
                <p>کل جریمه‌های دریافت شده</p>
            </div>
        </div>
    </div>

    <!-- بخش نمودار آماری ماهانه -->
    <div class="chart-section">
        <h2 class="section-title">آمار امانت ماهانه (<?php echo  jdate('Y') ?>)</h2>
        <canvas id="monthlyChart" width="400" height="150"></canvas>
    </div>

    <div class="dashboard-grid">
        <!-- ستون اول: تیکت‌ها -->
        <div class="col-1">
            <h2 class="dashboard-title">
                <span class="title-icon">💬</span>
                تیکت‌های کاربران
                <?php
                $pending_tickets_query = $conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE status = 'pending'");
                $pending_tickets_query->execute();
                $pending_count = $pending_tickets_query->fetch(PDO::FETCH_ASSOC)['count'];
                if ($pending_count > 0) {
                    echo "<span class='badge badge-warning'>$pending_count در انتظار</span>";
                }
                ?>
            </h2>
            <div class="books-list">
                <table>
                    <thead>
                        <tr>
                            <th>شناسه</th>
                            <th>ارسال کننده</th>
                            <th>موضوع</th>
                            <th>تاریخ</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    get_tickets();
                    if (empty($tickets)) {
                        echo "<tr><td colspan='6' class='text-center'>تیکتی ثبت نشده است</td></tr>";
                    } else {
                        foreach (array_slice($tickets, 0, 5) as $ticket) {
                            $status_class = $ticket['status'] == 'pending' ? 'status-pending' : 'status-answered';
                            $status_text = $ticket['status'] == 'pending' ? 'در انتظار' : 'پاسخ داده شده';
                    ?>
                        <tr>
                            <td><?php echo  $ticket['ticket_id'] ?></td>
                            <td><?php echo  get_user_name($ticket['user_id']) ?></td>
                            <td class="ticket-subject"><?php echo  mb_substr($ticket['ticket_title'], 0, 30) ?>...</td>
                            <td><?php echo  jdate('Y/m/d', strtotime($ticket['created_at'])) ?></td>
                            <td><span class="status-badge <?php echo  $status_class ?>"><?php echo  $status_text ?></span></td>
                            <td>
                                <form action="reply_ticket.php" method="POST" style="display:inline;">
                                    <input type="hidden" value="<?php echo  $ticket['ticket_id'] ?>" name="ticket-id">
                                    <button class="edit_delete_btn" name="reply_ticket" title="پاسخ">
                                        <img src="assets/img/reply.svg" alt="پاسخ">
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php
                        }
                    }
                    ?>
                    </tbody>
                </table>
            </div>
            <a class="see-more" href="tickets.php">مشاهده تمام تیکت‌ها</a>
        </div>

        <!-- ستون دوم: امانت‌های اخیر -->
        <div class="col-2">
            <h2 class="dashboard-title">
                <span class="title-icon">📋</span>
                امانت‌های اخیر
            </h2>
            <div class="books-list">
                <table>
                    <thead>
                        <tr>
                            <th>تصویر</th>
                            <th>نام کتاب</th>
                            <th>عضو</th>
                            <th>تاریخ امانت</th>
                            <th>مهلت بازگشت</th>
                            <th>وضعیت</th>
                            <th>جریمه</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // دریافت امانت‌های اخیر
                    $recent_reservations_query = $conn->prepare("
                        SELECT r.*, b.book_name, b.image, m.name, m.surname
                        FROM reservations r
                        JOIN books b ON r.bid = b.bid
                        JOIN members m ON r.mid = m.mid
                        ORDER BY r.reservation_date DESC
                        LIMIT 10
                    ");
                    $recent_reservations_query->execute();
                    $recent_reservations = $recent_reservations_query->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($recent_reservations)) {
                        echo "<tr><td colspan='7' class='text-center'>امانتی ثبت نشده است</td></tr>";
                    } else {
                        foreach ($recent_reservations as $reservation) {
                            // محاسبه روزهای باقیمانده
                            $return_date_ts = strtotime($reservation['return_date']);
                            $today_ts = strtotime(date('Y-m-d'));
                            $days_diff = floor(($return_date_ts - $today_ts) / 86400);

                            // تعیین وضعیت
                            if ($reservation['status'] == 'returned') {
                                $status_class = 'status-returned';
                                $status_text = 'برگشت داده شده';
                                $days_text = '-';
                            } elseif ($reservation['status'] == 'active') {
                                if ($days_diff > 3) {
                                    $status_class = 'status-active';
                                    $status_text = 'فعال';
                                    $days_text = "$days_diff روز باقیمانده";
                                } elseif ($days_diff >= 0) {
                                    $status_class = 'status-warning';
                                    $status_text = 'نزدیک به سررسید';
                                    $days_text = "$days_diff روز باقیمانده";
                                } else {
                                    $status_class = 'status-overdue';
                                    $status_text = 'معوقه';
                                    $days_text = abs($days_diff) . " روز تاخیر";
                                }
                            }
                    ?>
                        <tr>
                            <td><img src='<?php echo  IMG_PATH . $reservation['image'] ?>' width="60px" alt="کتاب"></td>
                            <td>
                                <a href="<?php echo  siteurl() ?>/book.php?bid=<?php echo  $reservation['bid'] ?>" target="_blank">
                                    <?php echo  mb_substr($reservation['book_name'], 0, 25) ?>...
                                </a>
                            </td>
                            <td><?php echo  $reservation['name'] . ' ' . $reservation['surname'] ?></td>
                            <td><?php echo  jdate('Y/m/d', strtotime($reservation['reservation_date'])) ?></td>
                            <td><?php echo  jdate('Y/m/d', strtotime($reservation['return_date'])) ?></td>
                            <td>
                                <span class="status-badge <?php echo  $status_class ?>"><?php echo  $status_text ?></span>
                                <small class="days-info"><?php echo  $days_text ?></small>
                            </td>
                            <td>
                                <?php if ($reservation['penalty'] > 0): ?>
                                    <span class="penalty-amount"><?php echo  number_format($reservation['penalty']) ?> تومان</span>
                                    <?php if ($reservation['penalty_paid']): ?>
                                        <span class="penalty-paid">✓</span>
                                    <?php else: ?>
                                        <span class="penalty-unpaid">✗</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="no-penalty">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php
                        }
                    }
                    ?>
                    </tbody>
                </table>
            </div>
            <a class="see-more" href="reservations.php">مشاهده تمام امانت‌ها</a>
        </div>
    </div>

    <!-- بخش فعالیت‌های اخیر اعضا -->
    <div class="activity-section">
        <h2 class="section-title">
            <span class="title-icon">🔔</span>
            فعالیت‌های اخیر اعضا
        </h2>
        <div class="activity-list">
            <?php
            $activity_query = $conn->prepare("
                SELECT mal.*, m.name, m.surname, m.username
                FROM member_activity_log mal
                JOIN members m ON mal.mid = m.mid
                ORDER BY mal.created_at DESC
                LIMIT 15
            ");
            $activity_query->execute();
            $activities = $activity_query->fetchAll(PDO::FETCH_ASSOC);

            if (empty($activities)) {
                echo "<p class='no-activity'>فعالیتی ثبت نشده است</p>";
            } else {
                foreach ($activities as $activity) {
                    $icon = '';
                    $color_class = '';

                    switch($activity['activity_type']) {
                        case 'register':
                            $icon = '👤';
                            $color_class = 'activity-register';
                            break;
                        case 'borrow':
                            $icon = '📖';
                            $color_class = 'activity-borrow';
                            break;
                        case 'return':
                            $icon = '✅';
                            $color_class = 'activity-return';
                            break;
                        case 'penalty_payment':
                            $icon = '💰';
                            $color_class = 'activity-payment';
                            break;
                        default:
                            $icon = '📝';
                            $color_class = 'activity-other';
                    }

                    $time_ago = time_elapsed_string($activity['created_at']);
            ?>
                <div class="activity-item <?php echo  $color_class ?>">
                    <div class="activity-icon"><?php echo  $icon ?></div>
                    <div class="activity-content">
                        <div class="activity-user">
                            <strong><?php echo  $activity['name'] . ' ' . $activity['surname'] ?></strong>
                            <span class="username">(@<?php echo  $activity['username'] ?>)</span>
                        </div>
                        <div class="activity-description"><?php echo  $activity['description'] ?></div>
                        <div class="activity-time"><?php echo  $time_ago ?></div>
                    </div>
                </div>
            <?php
                }
            }
            ?>
        </div>
    </div>
</div>

<!-- اسکریپت نمودار -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// دریافت داده‌های آماری ماهانه
fetch('get_monthly_stats.php')
    .then(response => response.json())
    .then(data => {
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'تعداد امانت',
                    data: data.values,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            font: {
                                family: 'Vazir',
                                size: 12
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: {
                                family: 'Vazir'
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Vazir'
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<?php
// تابع کمکی برای نمایش زمان گذشته
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'سال',
        'm' => 'ماه',
        'w' => 'هفته',
        'd' => 'روز',
        'h' => 'ساعت',
        'i' => 'دقیقه',
        's' => 'ثانیه',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' پیش' : 'هم اکنون';
}
?>
