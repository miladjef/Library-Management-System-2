<?php
// admin/export_report.php
require_once '../inc/config.php';
require_once '../classes/Database.php';

// بررسی دسترسی ادمین
if (!isset($_SESSION['admin_id'])) {
    die('Access Denied');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$db = Database::getInstance();
$conn = $db->getConnection();

// دریافت پارامترها
$report_type = $_GET['type'] ?? 'reservations';
$from_date = $_GET['from_date'] ?? null;
$to_date = $_GET['to_date'] ?? null;
$status = $_GET['status'] ?? 'all';

// ایجاد Spreadsheet جدید
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// تنظیمات فونت فارسی
$spreadsheet->getDefaultStyle()->getFont()->setName('B Nazanin');
$spreadsheet->getDefaultStyle()->getFont()->setSize(11);
$sheet->setRightToLeft(true);

// متغیرهای مشترک
$filename = '';
$title = '';

switch ($report_type) {
    case 'reservations':
        $filename = 'Reservations_Report_' . jdate('Y-m-d') . '.xlsx';
        $title = 'گزارش امانت‌ها';
        exportReservationsReport($sheet, $conn, $from_date, $to_date, $status);
        break;

    case 'books':
        $filename = 'Books_Report_' . jdate('Y-m-d') . '.xlsx';
        $title = 'گزارش کتاب‌ها';
        exportBooksReport($sheet, $conn);
        break;

    case 'members':
        $filename = 'Members_Report_' . jdate('Y-m-d') . '.xlsx';
        $title = 'گزارش اعضا';
        exportMembersReport($sheet, $conn);
        break;

    case 'financial':
        $filename = 'Financial_Report_' . jdate('Y-m-d') . '.xlsx';
        $title = 'گزارش مالی';
        exportFinancialReport($sheet, $conn, $from_date, $to_date);
        break;

    case 'overdue':
        $filename = 'Overdue_Report_' . jdate('Y-m-d') . '.xlsx';
        $title = 'گزارش معوقات';
        exportOverdueReport($sheet, $conn);
        break;

    default:
        die('Invalid report type');
}

// تنظیم عنوان
$sheet->setTitle(substr($title, 0, 31)); // حداکثر 31 کاراکتر

// ذخیره و دانلود
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

/**
 * گزارش امانت‌ها
 */
function exportReservationsReport($sheet, $conn, $from_date, $to_date, $status) {
    // عنوان گزارش
    $sheet->setCellValue('A1', 'گزارش امانت‌های کتابخانه');
    $sheet->mergeCells('A1:J1');
    styleHeader($sheet, 'A1');

    // اطلاعات گزارش
    $sheet->setCellValue('A2', 'تاریخ تهیه گزارش: ' . jdate('Y/m/d - H:i'));
    $sheet->mergeCells('A2:J2');

    if ($from_date && $to_date) {
        $sheet->setCellValue('A3', "از تاریخ: $from_date تا $to_date");
        $sheet->mergeCells('A3:J3');
        $headerRow = 5;
    } else {
        $headerRow = 4;
    }

    // هدر جدول
    $headers = ['ردیف', 'شناسه', 'نام کتاب', 'عضو', 'تاریخ امانت', 'مدت', 'تاریخ بازگشت', 'وضعیت', 'جریمه', 'توضیحات'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $headerRow, $header);
        styleTableHeader($sheet, $col . $headerRow);
        $col++;
    }

    // دریافت داده‌ها
    $query = "
        SELECT
            r.rid,
            b.book_name,
            CONCAT(m.name, ' ', m.surname) as member_name,
            r.borrow_date,
            r.duration,
            r.return_date,
            r.status,
            r.fine,
            r.notes,
            CASE
                WHEN r.status = 1 THEN 'در انتظار تایید'
                WHEN r.status = 2 THEN 'امانت فعال'
                WHEN r.status = 3 THEN 'بازگشت داده شده'
                WHEN r.status = 4 THEN 'لغو شده'
            END as status_text
        FROM reservations r
        JOIN books b ON r.book_id = b.bid
        JOIN members m ON r.user_id = m.mid
        WHERE 1=1
    ";

    $params = [];

    if ($from_date && $to_date) {
        $query .= " AND DATE(r.borrow_date) BETWEEN ? AND ?";
        $params[] = $from_date;
        $params[] = $to_date;
    }

    if ($status != 'all') {
        $query .= " AND r.status = ?";
        $params[] = $status;
    }

    $query .= " ORDER BY r.borrow_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // پر کردن داده‌ها
    $row = $headerRow + 1;
    $counter = 1;
    $totalFine = 0;

    foreach ($reservations as $reservation) {
        $sheet->setCellValue('A' . $row, $counter);
        $sheet->setCellValue('B' . $row, $reservation['rid']);
        $sheet->setCellValue('C' . $row, $reservation['book_name']);
        $sheet->setCellValue('D' . $row, $reservation['member_name']);
        $sheet->setCellValue('E' . $row, jdate('Y/m/d', strtotime($reservation['borrow_date'])));
        $sheet->setCellValue('F' . $row, $reservation['duration'] . ' روز');

        if ($reservation['return_date']) {
            $sheet->setCellValue('G' . $row, jdate('Y/m/d', strtotime($reservation['return_date'])));
        } else {
            $sheet->setCellValue('G' . $row, '-');
        }

        $sheet->setCellValue('H' . $row, $reservation['status_text']);
        $sheet->setCellValue('I' . $row, number_format($reservation['fine']) . ' تومان');
        $sheet->setCellValue('J' . $row, $reservation['notes'] ?? '-');

        // استایل ردیف
        styleTableRow($sheet, 'A' . $row . ':J' . $row);

        $totalFine += $reservation['fine'];
        $row++;
        $counter++;
    }

    // ردیف جمع کل
    $row++;
    $sheet->setCellValue('A' . $row, 'جمع کل:');
    $sheet->mergeCells('A' . $row . ':H' . $row);
    $sheet->setCellValue('I' . $row, number_format($totalFine) . ' تومان');
    styleTotal($sheet, 'A' . $row . ':J' . $row);

    // تنظیم عرض ستون‌ها
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(10);
    $sheet->getColumnDimension('C')->setWidth(25);
    $sheet->getColumnDimension('D')->setWidth(20);
    $sheet->getColumnDimension('E')->setWidth(12);
    $sheet->getColumnDimension('F')->setWidth(10);
    $sheet->getColumnDimension('G')->setWidth(12);
    $sheet->getColumnDimension('H')->setWidth(15);
    $sheet->getColumnDimension('I')->setWidth(15);
    $sheet->getColumnDimension('J')->setWidth(20);
}

/**
 * گزارش کتاب‌ها
 */
function exportBooksReport($sheet, $conn) {
    $sheet->setCellValue('A1', 'گزارش کامل کتاب‌های کتابخانه');
    $sheet->mergeCells('A1:I1');
    styleHeader($sheet, 'A1');

    $sheet->setCellValue('A2', 'تاریخ: ' . jdate('Y/m/d - H:i'));
    $sheet->mergeCells('A2:I2');

    // هدر جدول
    $headers = ['ردیف', 'شناسه', 'نام کتاب', 'دسته‌بندی', 'نویسنده', 'ناشر', 'سال انتشار', 'موجودی', 'امانت فعال'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '4', $header);
        styleTableHeader($sheet, $col . '4');
        $col++;
    }

    // دریافت داده‌ها
    $stmt = $conn->query("
        SELECT
            b.*,
            c.cat_name,
            COUNT(DISTINCT r.rid) as active_borrows
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.cat_id
        LEFT JOIN reservations r ON b.bid = r.book_id AND r.status = 2
        GROUP BY b.bid
        ORDER BY b.book_name
    ");

    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $row = 5;
    $counter = 1;
    $totalBooks = 0;
    $totalBorrowed = 0;

    foreach ($books as $book) {
        $sheet->setCellValue('A' . $row, $counter);
        $sheet->setCellValue('B' . $row, $book['bid']);
        $sheet->setCellValue('C' . $row, $book['book_name']);
        $sheet->setCellValue('D' . $row, $book['cat_name']);
        $sheet->setCellValue('E' . $row, $book['author']);
        $sheet->setCellValue('F' . $row, $book['publisher'] ?? '-');
        $sheet->setCellValue('G' . $row, $book['publish_year']);
        $sheet->setCellValue('H' . $row, $book['count']);
        $sheet->setCellValue('I' . $row, $book['active_borrows']);

        styleTableRow($sheet, 'A' . $row . ':I' . $row);

        $totalBooks += $book['count'];
        $totalBorrowed += $book['active_borrows'];
        $row++;
        $counter++;
    }

    // جمع کل
    $row++;
    $sheet->setCellValue('A' . $row, 'جمع کل:');
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->setCellValue('H' . $row, $totalBooks);
    $sheet->setCellValue('I' . $row, $totalBorrowed);
    styleTotal($sheet, 'A' . $row . ':I' . $row);

    // عرض ستون‌ها
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setWidth(15);
    }
    $sheet->getColumnDimension('C')->setWidth(30);
}

/**
 * گزارش اعضا
 */
function exportMembersReport($sheet, $conn) {
    $sheet->setCellValue('A1', 'گزارش اعضای کتابخانه');
    $sheet->mergeCells('A1:H1');
    styleHeader($sheet, 'A1');

    $sheet->setCellValue('A2', 'تاریخ: ' . jdate('Y/m/d - H:i'));
    $sheet->mergeCells('A2:H2');

    $headers = ['ردیف', 'نام و نام خانوادگی', 'نام کاربری', 'ایمیل', 'تلفن', 'تاریخ عضویت', 'امانت فعال', 'جریمه'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '4', $header);
        styleTableHeader($sheet, $col . '4');
        $col++;
    }

    $stmt = $conn->query("
        SELECT
            m.*,
            COUNT(DISTINCT r.rid) as active_borrows
        FROM members m
        LEFT JOIN reservations r ON m.mid = r.user_id AND r.status = 2
        WHERE m.is_active = 1
        GROUP BY m.mid
        ORDER BY m.created_at DESC
    ");

    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $row = 5;
    $counter = 1;
    $totalPenalty = 0;

    foreach ($members as $member) {
        $sheet->setCellValue('A' . $row, $counter);
        $sheet->setCellValue('B' . $row, $member['name'] . ' ' . $member['surname']);
        $sheet->setCellValue('C' . $row, $member['username']);
        $sheet->setCellValue('D' . $row, $member['email']);
        $sheet->setCellValue('E' . $row, $member['phone'] ?? '-');
        $sheet->setCellValue('F' . $row, jdate('Y/m/d', strtotime($member['created_at'])));
        $sheet->setCellValue('G' . $row, $member['active_borrows']);
        $sheet->setCellValue('H' . $row, number_format($member['penalty']) . ' تومان');

        styleTableRow($sheet, 'A' . $row . ':H' . $row);

        $totalPenalty += $member['penalty'];
        $row++;
        $counter++;
    }

    $row++;
    $sheet->setCellValue('A' . $row, 'جمع جریمه‌ها:');
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->setCellValue('H' . $row, number_format($totalPenalty) . ' تومان');
    styleTotal($sheet, 'A' . $row . ':H' . $row);

    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setWidth(18);
    }
}

/**
 * گزارش مالی
 */
function exportFinancialReport($sheet, $conn, $from_date, $to_date) {
    $sheet->setCellValue('A1', 'گزارش مالی کتابخانه');
    $sheet->mergeCells('A1:E1');
    styleHeader($sheet, 'A1');

    $period = $from_date && $to_date ? "از $from_date تا $to_date" : 'کل دوره';
    $sheet->setCellValue('A2', 'دوره گزارش: ' . $period);
    $sheet->mergeCells('A2:E2');

    $headers = ['شرح', 'تعداد', 'مبلغ (تومان)', 'درصد', 'توضیحات'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '4', $header);
        styleTableHeader($sheet, $col . '4');
        $col++;
    }

    // محاسبات مالی
    $where = "1=1";
    $params = [];

    if ($from_date && $to_date) {
        $where .= " AND DATE(payment_date) BETWEEN ? AND ?";
        $params = [$from_date, $to_date];
    }

    $stmt = $conn->prepare("
        SELECT
            SUM(fine) as total_fines,
            COUNT(*) as fine_count
        FROM reservations
        WHERE fine > 0 AND $where
    ");
    $stmt->execute($params);
    $fines = $stmt->fetch(PDO::FETCH_ASSOC);

    $row = 5;
    $total = $fines['total_fines'] ?? 0;

    // ردیف جریمه‌ها
    $sheet->setCellValue('A' . $row, 'جریمه تاخیر در بازگشت');
    $sheet->setCellValue('B' . $row, $fines['fine_count'] ?? 0);
    $sheet->setCellValue('C' . $row, number_format($total));
    $sheet->setCellValue('D' . $row, '100%');
    $sheet->setCellValue('E' . $row, 'جریمه امانت‌های معوقه');
    styleTableRow($sheet, 'A' . $row . ':E' . $row);

    // جمع کل
    $row += 2;
    $sheet->setCellValue('A' . $row, 'جمع کل درآمد:');
    $sheet->mergeCells('A' . $row . ':B' . $row);
    $sheet->setCellValue('C' . $row, number_format($total) . ' تومان');
    styleTotal($sheet, 'A' . $row . ':E' . $row);

    foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
        $sheet->getColumnDimension($col)->setWidth(20);
    }
}

/**
 * گزارش معوقات
 */
function exportOverdueReport($sheet, $conn) {
    $sheet->setCellValue('A1', 'گزارش امانت‌های معوقه');
    $sheet->mergeCells('A1:H1');
    styleHeader($sheet, 'A1');

    $sheet->setCellValue('A2', 'تاریخ: ' . jdate('Y/m/d - H:i'));
    $sheet->mergeCells('A2:H2');

    $headers = ['ردیف', 'نام کتاب', 'عضو', 'تلفن', 'تاریخ امانت', 'موعد بازگشت', 'تاخیر (روز)', 'جریمه'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '4', $header);
        styleTableHeader($sheet, $col . '4');
        $col++;
    }

    $stmt = $conn->query("
        SELECT
            r.*,
            b.book_name,
            CONCAT(m.name, ' ', m.surname) as member_name,
            m.phone,
            DATEDIFF(CURDATE(), DATE_ADD(r.borrow_date, INTERVAL r.duration DAY)) as overdue_days
        FROM reservations r
        JOIN books b ON r.book_id = b.bid
        JOIN members m ON r.user_id = m.mid
        WHERE r.status = 2
        AND DATE_ADD(r.borrow_date, INTERVAL r.duration DAY) < CURDATE()
        ORDER BY overdue_days DESC
    ");

    $overdues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $row = 5;
    $counter = 1;
    $totalFine = 0;

    foreach ($overdues as $overdue) {
        $sheet->setCellValue('A' . $row, $counter);
        $sheet->setCellValue('B' . $row, $overdue['book_name']);
        $sheet->setCellValue('C' . $row, $overdue['member_name']);
        $sheet->setCellValue('D' . $row, $overdue['phone'] ?? '-');
        $sheet->setCellValue('E' . $row, jdate('Y/m/d', strtotime($overdue['borrow_date'])));

        $return_deadline = date('Y-m-d', strtotime($overdue['borrow_date'] . ' + ' . $overdue['duration'] . ' days'));
        $sheet->setCellValue('F' . $row, jdate('Y/m/d', strtotime($return_deadline)));
        $sheet->setCellValue('G' . $row, $overdue['overdue_days']);
        $sheet->setCellValue('H' . $row, number_format($overdue['fine']) . ' تومان');

        // رنگ قرمز برای تاخیرهای بیش از 30 روز
        if ($overdue['overdue_days'] > 30) {
            $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFCCCC');
        }

        styleTableRow($sheet, 'A' . $row . ':H' . $row);

        $totalFine += $overdue['fine'];
        $row++;
        $counter++;
    }

    $row++;
    $sheet->setCellValue('A' . $row, 'جمع جریمه‌ها:');
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->setCellValue('H' . $row, number_format($totalFine) . ' تومان');
    styleTotal($sheet, 'A' . $row . ':H' . $row);

    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setWidth(18);
    }
    $sheet->getColumnDimension('B')->setWidth(25);
}

/**
 * توابع استایل‌دهی
 */
function styleHeader($sheet, $cell) {
    $sheet->getStyle($cell)->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 14,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4472C4']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ]);
    $sheet->getRowDimension(substr($cell, 1))->setRowHeight(30);
}

function styleTableHeader($sheet, $cell) {
    $sheet->getStyle($cell)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '5B9BD5']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);
}

function styleTableRow($sheet, $range) {
    $sheet->getStyle($range)->applyFromArray([
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ]
    ]);
}

function styleTotal($sheet, $range) {
    $sheet->getStyle($range)->applyFromArray([
        'font' => ['bold' => true, 'size' => 12],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E7E6E6']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_MEDIUM,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);
}
