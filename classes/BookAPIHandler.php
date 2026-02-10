<?php
/**
 * BookAPIHandler Class
 * مدیریت جستجو و دریافت اطلاعات کتاب از APIهای بین‌المللی
 *
 * @version 2.0
 * @author Library Management System
 */

class BookAPIHandler {
    private $db;
    private $timeout = 10; // ثانیه
    private $logSearches = true;

    // اولویت APIها
    private $apiPriority = [
        'google_books',
        'open_library',
        'isbndb' // نیاز به کلید API
    ];

    public function __construct($database) {
        $this->db = $database;
    }

    /**
     * جستجوی کتاب از تمام منابع با الگوریتم Fallback
     *
     * @param string $isbn شناسه ISBN (10 یا 13 رقمی)
     * @return array|false اطلاعات کتاب یا false
     */
    public function searchByISBN($isbn) {
        // پاکسازی و اعتبارسنجی ISBN
        $isbn = $this->cleanISBN($isbn);

        if (!$this->validateISBN($isbn)) {
            return [
                'success' => false,
                'error' => 'شناسه ISBN معتبر نیست'
            ];
        }

        // بررسی کش (اگر قبلاً جستجو شده)
        $cachedResult = $this->getCachedBook($isbn);
        if ($cachedResult) {
            return array_merge($cachedResult, ['from_cache' => true]);
        }

        // تلاش برای جستجو از APIها به ترتیب اولویت
        foreach ($this->apiPriority as $apiName) {
            $method = 'searchFrom' . str_replace('_', '', ucwords($apiName, '_'));

            if (method_exists($this, $method)) {
                $result = $this->$method($isbn);

                if ($result && $result['success']) {
                    // ذخیره در لاگ و کش
                    $this->logSearch($isbn, $apiName, true, json_encode($result));
                    $this->cacheBookData($isbn, $result);

                    return array_merge($result, ['source' => $apiName]);
                }
            }
        }

        // در صورت عدم موفقیت
        $this->logSearch($isbn, 'all', false, 'کتاب یافت نشد');

        return [
            'success' => false,
            'error' => 'کتاب با این شناسه در هیچ منبعی یافت نشد'
        ];
    }

    /**
     * جستجو از Google Books API
     */
    private function searchFromGoogleBooks($isbn) {
        $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . $isbn;

        $response = $this->makeRequest($url);

        if (!$response) {
            return false;
        }

        $data = json_decode($response, true);

        if (!isset($data['items']) || count($data['items']) === 0) {
            return false;
        }

        $book = $data['items'][0]['volumeInfo'];

        return [
            'success' => true,
            'data' => [
                'title' => $book['title'] ?? '',
                'authors' => isset($book['authors']) ? implode(', ', $book['authors']) : '',
                'publisher' => $book['publisher'] ?? '',
                'publish_date' => $book['publishedDate'] ?? '',
                'page_count' => $book['pageCount'] ?? 0,
                'description' => $book['description'] ?? '',
                'categories' => isset($book['categories']) ? $book['categories'][0] : '',
                'language' => $book['language'] ?? 'fa',
                'image_url' => $book['imageLinks']['thumbnail'] ?? '',
                'isbn' => $isbn
            ]
        ];
    }

    /**
     * جستجو از Open Library API
     */
    private function searchFromOpenLibrary($isbn) {
        $url = "https://openlibrary.org/api/books?bibkeys=ISBN:" . $isbn . "&format=json&jscmd=data";

        $response = $this->makeRequest($url);

        if (!$response) {
            return false;
        }

        $data = json_decode($response, true);
        $key = "ISBN:" . $isbn;

        if (!isset($data[$key])) {
            return false;
        }

        $book = $data[$key];

        return [
            'success' => true,
            'data' => [
                'title' => $book['title'] ?? '',
                'authors' => isset($book['authors']) ? implode(', ', array_column($book['authors'], 'name')) : '',
                'publisher' => isset($book['publishers']) ? $book['publishers'][0]['name'] : '',
                'publish_date' => $book['publish_date'] ?? '',
                'page_count' => $book['number_of_pages'] ?? 0,
                'description' => $book['notes'] ?? $book['subtitle'] ?? '',
                'categories' => isset($book['subjects']) ? $book['subjects'][0]['name'] : '',
                'language' => 'en',
                'image_url' => $book['cover']['medium'] ?? '',
                'isbn' => $isbn
            ]
        ];
    }

    /**
     * جستجو از ISBNdb API (نیاز به کلید API)
     */
    private function searchFromIsbndb($isbn) {
        // برای استفاده از این API باید کلید API در .env تنظیم شود
        $apiKey = getenv('ISBNDB_API_KEY');

        if (!$apiKey) {
            return false; // اگر کلید API وجود ندارد
        }

        $url = "https://api2.isbndb.com/book/" . $isbn;

        $headers = [
            "Authorization: " . $apiKey
        ];

        $response = $this->makeRequest($url, $headers);

        if (!$response) {
            return false;
        }

        $data = json_decode($response, true);

        if (!isset($data['book'])) {
            return false;
        }

        $book = $data['book'];

        return [
            'success' => true,
            'data' => [
                'title' => $book['title'] ?? '',
                'authors' => isset($book['authors']) ? implode(', ', $book['authors']) : '',
                'publisher' => $book['publisher'] ?? '',
                'publish_date' => $book['date_published'] ?? '',
                'page_count' => $book['pages'] ?? 0,
                'description' => $book['synopsis'] ?? '',
                'categories' => isset($book['subjects']) ? $book['subjects'][0] : '',
                'language' => $book['language'] ?? 'en',
                'image_url' => $book['image'] ?? '',
                'isbn' => $isbn
            ]
        ];
    }

    /**
     * دانلود تصویر جلد کتاب و ذخیره در سرور
     *
     * @param string $imageUrl آدرس تصویر
     * @param string $isbn شناسه ISBN برای نام‌گذاری
     * @return string|false مسیر فایل ذخیره شده یا false
     */
    public function downloadCoverImage($imageUrl, $isbn) {
        if (empty($imageUrl)) {
            return false;
        }

        // دایرکتوری ذخیره تصاویر
        $uploadDir = dirname(__DIR__) . '/uploads/books/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // نام فایل منحصر به فرد
        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'book_' . $isbn . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // دانلود تصویر
        $imageData = $this->makeRequest($imageUrl);

        if ($imageData && file_put_contents($filepath, $imageData)) {
            return 'uploads/books/' . $filename; // مسیر نسبی برای ذخیره در دیتابیس
        }

        return false;
    }

    /**
     * تبدیل تاریخ میلادی به شمسی (برای سال چاپ)
     */
    public function convertToShamsi($gregorianDate) {
        if (empty($gregorianDate)) {
            return '';
        }

        // استخراج سال
        preg_match('/\d{4}/', $gregorianDate, $matches);

        if (!isset($matches[0])) {
            return $gregorianDate;
        }

        $gregorianYear = (int)$matches[0];

        // تبدیل تقریبی (سال میلادی - 621)
        $shamsiYear = $gregorianYear - 621;

        return (string)$shamsiYear;
    }

    /**
     * ارسال درخواست HTTP با cURL
     */
    private function makeRequest($url, $headers = []) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Library Management System/2.0'
        ]);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return ($httpCode === 200) ? $response : false;
    }

    /**
     * پاکسازی ISBN از کاراکترهای اضافی
     */
    private function cleanISBN($isbn) {
        return preg_replace('/[^0-9X]/i', '', $isbn);
    }

    /**
     * اعتبارسنجی ISBN (10 یا 13 رقمی)
     */
    private function validateISBN($isbn) {
        $length = strlen($isbn);

        if ($length === 10) {
            return $this->validateISBN10($isbn);
        } elseif ($length === 13) {
            return $this->validateISBN13($isbn);
        }

        return false;
    }

    /**
     * اعتبارسنجی ISBN-10 با الگوریتم Checksum
     */
    private function validateISBN10($isbn) {
        $check = 0;

        for ($i = 0; $i < 10; $i++) {
            $digit = ($isbn[$i] === 'X') ? 10 : (int)$isbn[$i];
            $check += $digit * (10 - $i);
        }

        return ($check % 11 === 0);
    }

    /**
     * اعتبارسنجی ISBN-13 با الگوریتم Checksum
     */
    private function validateISBN13($isbn) {
        $check = 0;

        for ($i = 0; $i < 13; $i++) {
            $check += (int)$isbn[$i] * (($i % 2 === 0) ? 1 : 3);
        }

        return ($check % 10 === 0);
    }

    /**
     * بررسی کش (دیتابیس لوکال)
     */
    private function getCachedBook($isbn) {
        $stmt = $this->db->prepare("
            SELECT * FROM api_book_cache
            WHERE isbn = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            LIMIT 1
        ");

        $stmt->execute([$isbn]);
        $cached = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cached) {
            return [
                'success' => true,
                'data' => json_decode($cached['book_data'], true)
            ];
        }

        return false;
    }

    /**
     * ذخیره اطلاعات در کش
     */
    private function cacheBookData($isbn, $bookData) {
        $stmt = $this->db->prepare("
            INSERT INTO api_book_cache (isbn, book_data, created_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE book_data = ?, created_at = NOW()
        ");

        $jsonData = json_encode($bookData['data']);
        $stmt->execute([$isbn, $jsonData, $jsonData]);
    }

    /**
     * ثبت لاگ جستجو
     */
    private function logSearch($isbn, $source, $success, $response) {
        if (!$this->logSearches) {
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO api_search_logs
            (isbn, api_source, success, response_data, searched_at)
            VALUES (?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $isbn,
            $source,
            $success ? 1 : 0,
            substr($response, 0, 5000) // محدود کردن حجم لاگ
        ]);
    }

    /**
     * دریافت آمار جستجوها (برای داشبورد ادمین)
     */
    public function getSearchStats() {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total_searches,
                SUM(success) as successful_searches,
                api_source,
                DATE(searched_at) as search_date
            FROM api_search_logs
            WHERE searched_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY api_source, DATE(searched_at)
            ORDER BY searched_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
