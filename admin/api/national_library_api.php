<?php
/**
 * API Endpoint برای تعامل با سرویس کتابخانه ملی
 */

require_once '../../config.php';
require_once '../inc/functions.php';
require_once '../../classes/NationalLibraryService.php';

header('Content-Type: application/json; charset=utf-8');

// بررسی احراز هویت
if (!is_logged_in() || !is_admin($_SESSION['userid'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'دسترسی غیرمجاز'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ایجاد نمونه سرویس
$nlService = new NationalLibraryService($conn);

// دریافت پارامترها
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// مدیریت درخواست‌ها
try {
    switch ($action) {
        
        // جستجو با شابک
        case 'search_isbn':
            $isbn = $_GET['isbn'] ?? $_POST['isbn'] ?? '';
            
            if (empty($isbn)) {
                throw new Exception('شابک وارد نشده است');
            }
            
            $result = $nlService->searchByISBN($isbn);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
        
        // جستجو با عنوان
        case 'search_title':
            $title = $_GET['title'] ?? $_POST['title'] ?? '';
            $limit = intval($_GET['limit'] ?? $_POST['limit'] ?? 10);
            
            if (empty($title)) {
                throw new Exception('عنوان وارد نشده است');
            }
            
            if ($limit < 1 || $limit > 50) {
                $limit = 10;
            }
            
            $result = $nlService->searchByTitle($title, $limit);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
        
        // دانلود تصویر جلد
        case 'download_cover':
            $isbn = $_GET['isbn'] ?? $_POST['isbn'] ?? '';
            
            if (empty($isbn)) {
                throw new Exception('شابک وارد نشده است');
            }
            
            $result = $nlService->downloadCoverImage($isbn);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
        
        // سینک دستی کتاب واحد
        case 'sync_single':
            $book_id = intval($_POST['book_id'] ?? 0);
            
            if ($book_id < 1) {
                throw new Exception('شناسه کتاب نامعتبر است');
            }
            
            // دریافت اطلاعات کتاب
            $book = get_book_data($book_id);
            
            if (!$book || empty($book['isbn'])) {
                throw new Exception('کتاب یافت نشد یا شابک ندارد');
            }
            
            // جستجو در کتابخانه ملی
            $searchResult = $nlService->searchByISBN($book['isbn']);
            
            if (!$searchResult['success']) {
                throw new Exception($searchResult['message']);
            }
            
            // به‌روزرسانی اطلاعات
            $nlBook = $searchResult['data'];
            
            $stmt = $pdo->prepare("
                UPDATE books SET 
                    book_name = ?,
                    author = ?,
                    publisher = ?,
                    publish_year = ?,
                    pages = ?,
                    language = ?
                WHERE bid = ?
            ");
            
            $stmt->execute([
                $nlBook['title'],
                $nlBook['author'],
                $nlBook['publisher'],
                $nlBook['year'],
                $nlBook['pages'],
                $nlBook['language'],
                $book_id
            ]);
            
            // دانلود جلد
            $coverResult = $nlService->downloadCoverImage($book['isbn']);
            
            if ($coverResult['success']) {
                $pdo->prepare("UPDATE books SET book_img = ? WHERE bid = ?")
                    ->execute([$coverResult['filename'], $book_id]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'کتاب با موفقیت به‌روزرسانی شد',
                'data' => $nlBook,
                'cover' => $coverResult
            ], JSON_UNESCAPED_UNICODE);
            break;
        
        // دریافت آمار لاگ‌ها
        case 'get_stats':
            $stmt = $pdo->query("
                SELECT 
                    operation_type,
                    status,
                    COUNT(*) as count,
                    MAX(created_at) as last_operation
                FROM national_library_logs
                GROUP BY operation_type, status
            ");
            
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ], JSON_UNESCAPED_UNICODE);
            break;
        
        // دریافت آخرین لاگ‌ها
        case 'get_recent_logs':
            $limit = intval($_GET['limit'] ?? 20);
            
            $stmt = $pdo->prepare("
                SELECT * FROM national_library_logs 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'logs' => $logs
            ], JSON_UNESCAPED_UNICODE);
            break;
        
        default:
            throw new Exception('عملیات نامعتبر است');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
