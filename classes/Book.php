<?php
/**
 * Book Class
 * مدیریت عملیات مربوط به کتاب‌ها (CRUD)
 *
 * @version 2.0
 */

class Book {
    private $db;
    private $validator;

    public function __construct($database, $validator) {
        $this->db = $database;
        $this->validator = $validator;
    }

    /**
     * افزودن کتاب جدید (از فرم دستی یا API)
     *
     * @param array $data اطلاعات کتاب
     * @return array نتیجه عملیات
     */
    public function addBook($data) {
        // اعتبارسنجی داده‌های ورودی
        $rules = [
            'book_name' => 'required|max:255',
            'book_isbn' => 'required|isbn',
            'author' => 'required|max:255',
            'publisher' => 'required|max:255',
            'publish_year' => 'required|shamsi_year',
            'book_count' => 'required|integer|min:0',
            'category_id' => 'required|integer',
            'description' => 'max:2000'
        ];

        $validation = $this->validator->validate($data, $rules);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // بررسی تکراری نبودن ISBN
        if ($this->isISBNExists($data['book_isbn'])) {
            return [
                'success' => false,
                'errors' => ['book_isbn' => 'کتابی با این شناسه قبلاً ثبت شده است']
            ];
        }

        // مدیریت آپلود تصویر
        $imagePath = $this->handleImageUpload($data['book_image'] ?? null);

        // درج در دیتابیس
        $stmt = $this->db->prepare("
            INSERT INTO books (
                book_name, book_isbn, author, publisher,
                publish_year, book_count, category_id,
                description, book_image, book_page_count,
                book_language, book_api_source, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $result = $stmt->execute([
            $data['book_name'],
            $data['book_isbn'],
            $data['author'],
            $data['publisher'],
            $data['publish_year'],
            $data['book_count'],
            $data['category_id'],
            $data['description'] ?? '',
            $imagePath,
            $data['book_page_count'] ?? 0,
            $data['book_language'] ?? 'fa',
            $data['book_api_source'] ?? 'manual'
        ]);

        if ($result) {
            return [
                'success' => true,
                'book_id' => $this->db->lastInsertId(),
                'message' => 'کتاب با موفقیت اضافه شد'
            ];
        }

        return [
            'success' => false,
            'errors' => ['database' => 'خطا در ذخیره اطلاعات']
        ];
    }

    /**
     * ویرایش کتاب
     */
    public function updateBook($bookId, $data) {
        // اعتبارسنجی مشابه addBook
        $rules = [
            'book_name' => 'required|max:255',
            'author' => 'required|max:255',
            'publisher' => 'required|max:255',
            'publish_year' => 'required|shamsi_year',
            'book_count' => 'required|integer|min:0',
            'category_id' => 'required|integer'
        ];

        $validation = $this->validator->validate($data, $rules);

        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        // آپدیت تصویر در صورت وجود
        $imagePath = isset($data['book_image']) ?
            $this->handleImageUpload($data['book_image']) :
            null;

        $sql = "UPDATE books SET
                book_name = ?, author = ?, publisher = ?,
                publish_year = ?, book_count = ?, category_id = ?,
                description = ?, updated_at = NOW()";

        $params = [
            $data['book_name'],
            $data['author'],
            $data['publisher'],
            $data['publish_year'],
            $data['book_count'],
            $data['category_id'],
            $data['description'] ?? ''
        ];

        if ($imagePath) {
            $sql .= ", book_image = ?";
            $params[] = $imagePath;
        }

        $sql .= " WHERE bid = ?";
        $params[] = $bookId;

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);

        return [
            'success' => $result,
            'message' => $result ? 'کتاب با موفقیت به‌روزرسانی شد' : 'خطا در به‌روزرسانی'
        ];
    }

    /**
     * حذف کتاب
     */
    public function deleteBook($bookId) {
        // بررسی وجود رزرو فعال
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM reservations
            WHERE book_id = ? AND status IN ('borrowed', 'reserved')
        ");
        $stmt->execute([$bookId]);

        if ($stmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'error' => 'این کتاب دارای رزرو فعال است و قابل حذف نیست'
            ];
        }

        // حذف تصویر از سرور
        $book = $this->getBookById($bookId);
        if ($book && !empty($book['book_image'])) {
            $imagePath = dirname(__DIR__) . '/' . $book['book_image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        // حذف از دیتابیس
        $stmt = $this->db->prepare("DELETE FROM books WHERE bid = ?");
        $result = $stmt->execute([$bookId]);

        return [
            'success' => $result,
            'message' => $result ? 'کتاب با موفقیت حذف شد' : 'خطا در حذف کتاب'
        ];
    }

    /**
     * دریافت اطلاعات یک کتاب
     */
    public function getBookById($bookId) {
        $stmt = $this->db->prepare("
            SELECT b.*, c.cat_name
            FROM books b
            LEFT JOIN categories c ON b.category_id = c.cat_id
            WHERE b.bid = ?
        ");

        $stmt->execute([$bookId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * دریافت لیست تمام کتاب‌ها با صفحه‌بندی
     */
    public function getAllBooks($page = 1, $perPage = 20, $filters = []) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT b.*, c.cat_name,
                (b.book_count - COALESCE(
                    (SELECT COUNT(*) FROM reservations
                     WHERE book_id = b.bid AND status = 'borrowed'), 0
                )) as available_count
                FROM books b
                LEFT JOIN categories c ON b.category_id = c.cat_id
                WHERE 1=1";

        $params = [];

        // فیلتر جستجو
        if (!empty($filters['search'])) {
            $sql .= " AND (b.book_name LIKE ? OR b.author LIKE ? OR b.book_isbn LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // فیلتر دسته‌بندی
        if (!empty($filters['category_id'])) {
            $sql .= " AND b.category_id = ?";
            $params[] = $filters['category_id'];
        }

        // فیلتر موجودی
        if (isset($filters['availability'])) {
            if ($filters['availability'] === 'available') {
                $sql .= " HAVING available_count > 0";
            } elseif ($filters['availability'] === 'unavailable') {
                $sql .= " HAVING available_count = 0";
            }
        }

        $sql .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return [
            'books' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $this->getBookCount($filters),
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * شمارش تعداد کل کتاب‌ها (برای صفحه‌بندی)
     */
    private function getBookCount($filters = []) {
        $sql = "SELECT COUNT(*) FROM books WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (book_name LIKE ? OR author LIKE ? OR book_isbn LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }

        if (!empty($filters['category_id'])) {
            $sql .= " AND category_id = ?";
            $params[] = $filters['category_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    /**
     * بررسی وجود ISBN تکراری
     */
    private function isISBNExists($isbn, $excludeBookId = null) {
        $sql = "SELECT COUNT(*) FROM books WHERE book_isbn = ?";
        $params = [$isbn];

        if ($excludeBookId) {
            $sql .= " AND bid != ?";
            $params[] = $excludeBookId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * مدیریت آپلود تصویر کتاب
     */
    private function handleImageUpload($fileData) {
        if (!$fileData || !isset($fileData['tmp_name'])) {
            return 'uploads/books/default.jpg';
        }

        // اعتبارسنجی فایل
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($fileData['type'], $allowedTypes)) {
            throw new Exception('فرمت تصویر باید JPG یا PNG باشد');
        }

        if ($fileData['size'] > $maxSize) {
            throw new Exception('حجم تصویر نباید بیشتر از 2 مگابایت باشد');
        }

        // ایجاد دایرکتوری در صورت عدم وجود
        $uploadDir = dirname(__DIR__) . '/uploads/books/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // نام‌گذاری منحصر به فرد
        $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
        $filename = 'book_' . uniqid() . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // انتقال فایل
        if (move_uploaded_file($fileData['tmp_name'], $filepath)) {
            // کاهش کیفیت تصویر (اختیاری)
            $this->compressImage($filepath, $filepath, 80);

            return 'uploads/books/' . $filename;
        }

        throw new Exception('خطا در آپلود تصویر');
    }

    /**
     * فشرده‌سازی تصویر برای کاهش حجم
     */
    private function compressImage($source, $destination, $quality) {
        $info = getimagesize($source);

        if ($info['mime'] == 'image/jpeg') {
            $image = imagecreatefromjpeg($source);
        } elseif ($info['mime'] == 'image/png') {
            $image = imagecreatefrompng($source);
        } else {
            return false;
        }

        imagejpeg($image, $destination, $quality);
        imagedestroy($image);

        return true;
    }

    /**
     * دریافت کتاب‌های پرطرفدار (برای داشبورد)
     */
    public function getPopularBooks($limit = 10) {
        $stmt = $this->db->prepare("
            SELECT b.*, COUNT(r.rid) as borrow_count
            FROM books b
            LEFT JOIN reservations r ON b.bid = r.book_id
            WHERE r.status IN ('borrowed', 'returned')
            GROUP BY b.bid
            ORDER BY borrow_count DESC
            LIMIT ?
        ");

        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
