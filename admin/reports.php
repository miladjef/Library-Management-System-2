<?php
// admin/reports.php
$title = 'گزارشات';
include "inc/header.php";
?>

<div class="main">
    <div class="page-title">
        گزارشات و آمار سیستم
    </div>

    <!-- فیلتر گزارشات -->
    <div class="report-filters">
        <form method="GET" action="">
            <div class="filter-group">
                <label>از تاریخ:</label>
                <input type="text" name="from_date" id="from_date" class="persian-datepicker" 
                       value="<?= $_GET['from_date'] ?? '' ?>" placeholder="1404/01/01">
            </div>
            
            <div class="filter-group">
                <label>تا تاریخ:</label>
                <input type="text" name="to_date" id="to_date" class="persian-datepicker" 
                       value="<?= $_GET['to_date'] ?? '' ?>" placeholder="1404/12/29">
            </div>
            
            <div class="filter-group">
                <label>نوع گزارش:</label>
                <select name="report_type">
                    <option value="reservations">امانت‌ها</option>
                    <option value="members">اعضا</option>
                    <option value="penalties">جریمه‌ها</option>
                    <option value="books">کتاب‌ها</option>
                </select>
            </div>
            
            <button type="submit" class="btn-primary">نمایش گزارش</button>
            <button type="button" onclick="exportReport()" class="btn-success">خروجی Excel</button>
        </form>
    </div>

    <?php
    if (isset($_GET['report_type'])) {
        $report_type = $_GET['report_type'];
        $from_date = $_GET['from_date'] ?? null;
        $to_date = $_GET['to_date'] ?? null;
        
        // تبدیل تاریخ شمسی به میلادی
        if ($from_date) {
            list($y, $m, $d) = explode('/', $from_date);
            $from_gregorian = jalali_to_gregorian($y, $m, $d);
            $from_date_sql = implode('-', $from_gregorian);
        }
        
        if ($to_date) {
            list($y, $m, $d) = explode('/', $to_date);
            $to_gregorian = jalali_to_gregorian($y, $m, $d);
            $to_date_sql = implode('-', $to_gregorian);
        }
        
        switch ($report_type) {
            case 'reservations':
                include 'inc/reports/reservations_report.php';
                break;
            case 'members':
                include 'inc/reports/members_report.php';
                break;
            case 'penalties':
                include 'inc/reports/penalties_report.php';
                break;
            case 'books':
                include 'inc/reports/books_report.php';
                break;
        }
    }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<script>
$(document).ready(function(){
    $('.persian-datepicker').persianDatepicker({
        format: 'YYYY/MM/DD',
        initialValue: false,
        autoClose: true
    });
});

function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.append('export', 'excel');
    window.location.href = 'export_report.php?' + params.toString();
}
</script>

<?php include "inc/footer.php"; ?>
