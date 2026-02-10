<?php
/**
 * Category Class
 * مدیریت دسته‌بندی کتاب‌ها
 *
 * @version 2.0
 */

class Category {
    private $db;
    private $validator;

    public function __construct($database, $validator) {
        $this->db = $database;
        $this->validator = $validator;
    }

    /**
     * افزودن دسته‌بندی جدید
     */
    public function addCategory($data) {
        $rules = [
            'cat_name' => 'required|max:100|unique:categories,cat_name',
            'cat_description' => 'max:500'
        ];

        $validation = $this->validator->validate($data, $rules);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        $stmt = $this->db->prepare("
            INSERT INTO categories (cat_name, cat_description, created_at)
            VALUES (?, ?, NOW())
        ");

        $result = $stmt->execute([
            $data['cat_name'],
            $data['cat_description'] ?? ''
        ]);

        return [
            'success' => $result,
            'category_id' => $this->db->lastInsertId(),
            'message' => $result ? 'دسته‌بندی با موفقیت اضافه شد' : 'خطا در افزودن دسته‌بندی'
        ];
    }

    /**
     * ویرایش دسته‌بندی
     */
    public function updateCategory($catId, $data) {
        $rules = [
            'cat_name' => 'required|max:100',
            'cat_description' => 'max:500'
        ];

        $validation = $this->validator->validate($data, $rules);

        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        // بررسی تکراری نبودن نام (به جز خود رکورد)
        if ($this->isCategoryNameExists($data['cat_name'], $catId)) {
            return [
                'success' => false,
                'errors' => ['cat_name' => 'این نام دسته‌بندی قبلاً استفاده شده است']
            ];
        }

        $stmt = $this->db->prepare("
            UPDATE categories
            SET cat_name = ?, cat_description = ?, updated_at = NOW()
            WHERE cat_id = ?
        ");

        $result = $stmt->execute([
            $data['cat_name'],
            $data['cat_description'] ?? '',
            $catId
        ]);

        return [
            'success' => $result,
            'message' => $result ? 'دسته‌بندی با موفقیت به‌روزرسانی شد' : 'خطا در به‌روزرسانی'
        ];
    }

    /**
     * حذف دسته‌بندی
     */
    public function deleteCategory($catId) {
        // بررسی وجود کتاب در این دسته
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM books WHERE category_id = ?");
        $stmt->execute([$catId]);

        if ($stmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'error' => 'این دسته‌بندی دارای کتاب است و قابل حذف نیست. ابتدا کتاب‌ها را به دسته دیگری منتقل کنید.'
            ];
        }

        $stmt = $this->db->prepare("DELETE FROM categories WHERE cat_id = ?");
        $result = $stmt->execute([$catId]);

        return [
            'success' => $result,
            'message' => $result ? 'دسته‌بندی با موفقیت حذف شد' : 'خطا در حذف دسته‌بندی'
        ];
    }

    /**
     * دریافت اطلاعات یک دسته‌بندی
     */
    public function getCategoryById($catId) {
        $stmt = $this->db->prepare("
            SELECT c.*, COUNT(b.bid) as book_count
            FROM categories c
            LEFT JOIN books b ON c.cat_id = b.category_id
            WHERE c.cat_id = ?
            GROUP BY c.cat_id
        ");

        $stmt->execute([$catId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * دریافت لیست تمام دسته‌بندی‌ها
     */
    public function getAllCategories($withCount = true) {
        if ($withCount) {
            $sql = "SELECT c.*, COUNT(b.bid) as book_count
                    FROM categories c
                    LEFT JOIN books b ON c.cat_id = b.category_id
                    GROUP BY c.cat_id
                    ORDER BY c.cat_name ASC";
        } else {
            $sql = "SELECT * FROM categories ORDER BY cat_name ASC";
        }

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * بررسی تکراری بودن نام دسته‌بندی
     */
    private function isCategoryNameExists($name, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM categories WHERE cat_name = ?";
        $params = [$name];

        if ($excludeId) {
            $sql .= " AND cat_id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * دریافت دسته‌بندی‌های پرطرفدار
     */
    public function getPopularCategories($limit = 5) {
        $stmt = $this->db->prepare("
            SELECT c.*, COUNT(b.bid) as book_count
            FROM categories c
            INNER JOIN books b ON c.cat_id = b.category_id
            GROUP BY c.cat_id
            ORDER BY book_count DESC
            LIMIT ?
        ");

        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
