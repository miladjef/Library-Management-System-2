<?php
/**
 * سرویس ادغام با API کتابخانه ملی ایران
 * National Library of Iran API Integration Service
 *
 * @version 1.0
 * @author Your Name
 */

class NationalLibraryService {

    private $db;
    private $api_base_url = 'https://opac.nlai.ir/opac-api';
    private $cover_base_url = 'https://opac.nlai.ir/opac-api/cover';
    private $timeout = 30;
    private $cover_save_path;

    /**
     * سازنده کلاس
     */
    public function __construct($database) {
        $this->db = $database;
        $this->cover_save_path = __DIR__ . '/../assets/img/books/covers/';

        // ایجاد پوشه ذخیره جلدها
        if (!is_dir($this->cover_save_path)) {
            mkdir($this->cover_save_path, 0755, true);
        }
    }

    /**
     * جستجو بر اساس شابک (ISBN)
     *
     * @param string $isbn شابک کتاب
     * @return array نتیجه جستجو
     */
    public function searchByISBN($isbn) {
        $isbn = $this->cleanISBN($isbn);

        if (empty($isbn)) {
            return [
                'success' => false,
                'message' => 'شابک نامعتبر است'
            ];
        }

        // جستجو در API
        $url = "{$this->api_base_url}/search.php?look=isbn&term={$isbn}";
        $response = $this->makeRequest($url, 'search_isbn', $isbn);

        if ($response['success']) {
            $data = $response['data'];

            // پردازش نتایج
            if (isset($data['records']) && count($data['records']) > 0) {
                $book = $this->parseBookData($data['records'][0]);

                $this->logOperation('search_isbn', $isbn, 'success', json_encode($book));

                return [
                    'success' => true,
                    'data' => $book,
                    'source' => 'national_library'
                ];
            } else {
                $this->logOperation('search_isbn', $isbn, 'error', null, 'کتابی یافت نشد');

                return [
                    'success' => false,
                    'message' => 'کتابی با این شابک یافت نشد'
                ];
            }
        }

        return $response;
    }

    /**
     * جستجو بر اساس عنوان
     *
     * @param string $title عنوان کتاب
     * @param int $limit تعداد نتایج
     * @return array نتایج جستجو
     */
    public function searchByTitle($title, $limit = 10) {
        if (empty($title)) {
            return [
                'success' => false,
                'message' => 'عنوان نامعتبر است'
            ];
        }

        // جستجو در API
        $encoded_title = urlencode($title);
        $url = "{$this->api_base_url}/search.php?look=title&term={$encoded_title}&limit={$limit}";
        $response = $this->makeRequest($url, 'search_title', $title);

        if ($response['success']) {
            $data = $response['data'];

            if (isset($data['records']) && count($data['records']) > 0) {
                $books = [];
                foreach ($data['records'] as $record) {
                    $books[] = $this->parseBookData($record);
                }

                $this->logOperation('search_title', $title, 'success', json_encode($books));

                return [
                    'success' => true,
                    'data' => $books,
                    'total' => count($books),
                    'source' => 'national_library'
                ];
            } else {
                $this->logOperation('search_title', $title, 'error', null, 'نتیجه‌ای یافت نشد');

                return [
                    'success' => false,
                    'message' => 'کتابی با این عنوان یافت نشد'
                ];
            }
        }

        return $response;
    }

    /**
     * دانلود تصویر جلد کتاب
     *
     * @param string $isbn شابک کتاب
     * @return array نتیجه دانلود
     */
    public function downloadCoverImage($isbn) {
        $isbn = $this->cleanISBN($isbn);

        if (empty($isbn)) {
            return [
                'success' => false,
                'message' => 'شابک نامعتبر است'
            ];
        }

        try {
            // URL تصویر جلد
            $cover_url = "{$this->cover_base_url}/{$isbn}.jpg";

            // دانلود تصویر
            $ch = curl_init($cover_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $image_data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200 || empty($image_data)) {
                throw new Exception('تصویر جلد یافت نشد');
            }

            // ذخیره تصویر
            $filename = 'cover_' . $isbn . '_' . time() . '.jpg';
            $filepath = $this->cover_save_path . $filename;

            if (!file_put_contents($filepath, $image_data)) {
                throw new Exception('خطا در ذخیره تصویر');
            }

            // تغییر اندازه تصویر (400x600)
            $this->resizeImage($filepath, 400, 600);

            $this->logOperation('download_cover', $isbn, 'success', $filename);

            return [
                'success' => true,
                'filename' => 'covers/' . $filename,
                'path' => $filepath,
                'url' => siteurl() . '/assets/img/books/covers/' . $filename
            ];

        } catch (Exception $e) {
            $this->logOperation('download_cover', $isbn, 'error', null, $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * سینک دوره‌ای کتاب‌ها با کتابخانه ملی
     *
     * @param bool $downloadCovers دانلود خودکار جلدها
     * @return array نتیجه سینک
     */
    public function syncBooks($downloadCovers = true) {
        $result = [
            'total' => 0,
            'updated' => 0,
            'covers_downloaded' => 0,
            'errors' => 0
        ];

        try {
            // دریافت کتاب‌های دارای شابک
            $query = "SELECT * FROM books WHERE isbn IS NOT NULL AND isbn != ''";
            $stmt = $this->db->query($query);

            while ($book = $stmt->fetch_assoc()) {
                $result['total']++;

                // جستجو در کتابخانه ملی
                $searchResult = $this->searchByISBN($book['isbn']);

                if ($searchResult['success']) {
                    $nlBook = $searchResult['data'];

                    // به‌روزرسانی اطلاعات کتاب
                    $updateQuery = "UPDATE books SET
                        book_name = ?,
                        author = ?,
                        publisher = ?,
                        publish_year = ?,
                        pages = ?
                        WHERE bid = ?";

                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->bind_param(
                        'ssssii',
                        $nlBook['title'],
                        $nlBook['author'],
                        $nlBook['publisher'],
                        $nlBook['year'],
                        $nlBook['pages'],
                        $book['bid']
                    );

                    if ($updateStmt->execute()) {
                        $result['updated']++;
                    }

                    // دانلود جلد
                    if ($downloadCovers && (empty($book['book_img']) || $book['book_img'] == 'default.jpg')) {
                        $coverResult = $this->downloadCoverImage($book['isbn']);

                        if ($coverResult['success']) {
                            $coverUpdateQuery = "UPDATE books SET book_img = ? WHERE bid = ?";
                            $coverStmt = $this->db->prepare($coverUpdateQuery);
                            $coverStmt->bind_param('si', $coverResult['filename'], $book['bid']);
                            $coverStmt->execute();

                            $result['covers_downloaded']++;
                        }
                    }
                } else {
                    $result['errors']++;
                }

                // تاخیر برای جلوگیری از بلاک شدن
                usleep(500000); // 0.5 ثانیه
            }

            $this->logOperation('sync', 'all_books', 'success', json_encode($result));

        } catch (Exception $e) {
            $this->logOperation('sync', 'all_books', 'error', null, $e->getMessage());
            $result['errors']++;
        }

        return $result;
    }

    /**
     * ارسال درخواست به API
     */
    private function makeRequest($url, $operation_type, $search_query) {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'User-Agent: LibraryManagementSystem/1.0'
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception("خطای cURL: " . $error);
            }

            if ($http_code !== 200) {
                throw new Exception("خطای HTTP: " . $http_code);
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("خطا در تجزیه JSON");
            }

            return [
                'success' => true,
                'data' => $data
            ];

        } catch (Exception $e) {
            $this->logOperation($operation_type, $search_query, 'error', null, $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * پردازش و استاندارد‌سازی داده‌های کتاب
     */
    private function parseBookData($record) {
        return [
            'title' => $record['title'] ?? '',
            'author' => $record['author'] ?? '',
            'publisher' => $record['publisher'] ?? '',
            'year' => $record['publish_year'] ?? '',
            'isbn' => $record['isbn'] ?? '',
            'pages' => $record['pages'] ?? 0,
            'language' => $record['language'] ?? 'فارسی',
            'description' => $record['description'] ?? '',
            'subjects' => $record['subjects'] ?? [],
            'cover_available' => isset($record['isbn']) && !empty($record['isbn'])
        ];
    }

    /**
     * تمیزکاری شابک
     */
    private function cleanISBN($isbn) {
        return preg_replace('/[^0-9X]/', '', strtoupper($isbn));
    }

    /**
     * تغییر اندازه تصویر
     */
    private function resizeImage($filepath, $width, $height) {
        if (!file_exists($filepath)) {
            return false;
        }

        $info = getimagesize($filepath);
        $mime = $info['mime'];

        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($filepath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($filepath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($filepath);
                break;
            default:
                return false;
        }

        $original_width = imagesx($source);
        $original_height = imagesy($source);

        // محاسبه نسبت ابعاد
        $ratio = min($width / $original_width, $height / $original_height);
        $new_width = intval($original_width * $ratio);
        $new_height = intval($original_height * $ratio);

        // ایجاد تصویر جدید
        $destination = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($destination, $source, 0, 0, 0, 0,
            $new_width, $new_height, $original_width, $original_height);

        // ذخیره تصویر
        imagejpeg($destination, $filepath, 90);

        // آزادسازی حافظه
        imagedestroy($source);
        imagedestroy($destination);

        return true;
    }

    /**
     * ثبت لاگ عملیات
     */
    private function logOperation($operation_type, $search_query, $status, $response_data = null, $error_message = null) {
        try {
            $query = "INSERT INTO national_library_logs
                (operation_type, search_query, status, response_data, error_message)
                VALUES (?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($query);
            $stmt->bind_param('sssss',
                $operation_type,
                $search_query,
                $status,
                $response_data,
                $error_message
            );
            $stmt->execute();

        } catch (Exception $e) {
            error_log("Error logging operation: " . $e->getMessage());
        }
    }
}
