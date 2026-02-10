<?php
// classes/Member.php

class Member {
    private $db;
    private $conn;

    public function __construct($database) {
        $this->db = $database;
        $this->conn = $this->db->getConnection();
    }

    /**
     * بررسی وجود نام کاربری
     */
    public function usernameExists($username) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM members WHERE username = ?
        ");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * بررسی وجود ایمیل
     */
    public function emailExists($email) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM members WHERE email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * دریافت اطلاعات کاربر
     */
    public function getMemberById($mid) {
        $stmt = $this->conn->prepare("
            SELECT * FROM members WHERE mid = ? LIMIT 1
        ");
        $stmt->execute([$mid]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * به‌روزرسانی پروفایل کاربر
     */
    public function updateProfile($mid, $data) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE members
                SET name = ?, surname = ?, email = ?, phone = ?,
                    address = ?, bio = ?, updated_at = NOW()
                WHERE mid = ?
            ");

            $result = $stmt->execute([
                $data['name'],
                $data['surname'],
                $data['email'],
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['bio'] ?? null,
                $mid
            ]);

            return [
                'success' => $result,
                'message' => $result ? 'اطلاعات با موفقیت به‌روزرسانی شد' : 'خطا در به‌روزرسانی'
            ];

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return [
                    'success' => false,
                    'message' => 'این ایمیل قبلا ثبت شده است'
                ];
            }

            return [
                'success' => false,
                'message' => 'خطا در به‌روزرسانی اطلاعات'
            ];
        }
    }

    /**
     * تغییر رمز عبور
     */
    public function changePassword($mid, $current_password, $new_password) {
        try {
            // دریافت رمز عبور فعلی
            $stmt = $this->conn->prepare("
                SELECT password FROM members WHERE mid = ? LIMIT 1
            ");
            $stmt->execute([$mid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد'
                ];
            }

            // بررسی رمز عبور فعلی
            if (!password_verify($current_password, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'رمز عبور فعلی اشتباه است'
                ];
            }

            // به‌روزرسانی رمز عبور
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $this->conn->prepare("
                UPDATE members SET password = ?, updated_at = NOW() WHERE mid = ?
            ");
            $result = $stmt->execute([$hashed_password, $mid]);

            return [
                'success' => $result,
                'message' => $result ? 'رمز عبور با موفقیت تغییر یافت' : 'خطا در تغییر رمز عبور'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'خطا در تغییر رمز عبور'
            ];
        }
    }

    /**
     * آپلود تصویر پروفایل
     */
    public function uploadAvatar($mid, $file) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        // بررسی نوع فایل
        if (!in_array($file['type'], $allowed_types)) {
            return [
                'success' => false,
                'message' => 'فقط تصاویر JPG، PNG و GIF مجاز است'
            ];
        }

        // بررسی حجم فایل
        if ($file['size'] > $max_size) {
            return [
                'success' => false,
                'message' => 'حجم فایل نباید بیشتر از 2 مگابایت باشد'
            ];
        }

        // تولید نام یکتا
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $mid . '_' . time() . '.' . $extension;
        $upload_path = 'assets/img/avatars/' . $filename;

        // ایجاد پوشه در صورت عدم وجود
        if (!file_exists('assets/img/avatars/')) {
            mkdir('assets/img/avatars/', 0777, true);
        }

        // آپلود فایل
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // حذف تصویر قبلی
            $stmt = $this->conn->prepare("SELECT avatar FROM members WHERE mid = ?");
            $stmt->execute([$mid]);
            $old_avatar = $stmt->fetchColumn();

            if ($old_avatar && file_exists('assets/img/avatars/' . $old_avatar)) {
                unlink('assets/img/avatars/' . $old_avatar);
            }

            // به‌روزرسانی دیتابیس
            $stmt = $this->conn->prepare("
                UPDATE members SET avatar = ?, updated_at = NOW() WHERE mid = ?
            ");
            $stmt->execute([$filename, $mid]);

            return [
                'success' => true,
                'filename' => $filename,
                'message' => 'تصویر پروفایل با موفقیت آپلود شد'
            ];
        }

        return [
            'success' => false,
            'message' => 'خطا در آپلود فایل'
        ];
    }

    /**
     * دریافت آمار کاربر
     */
    public function getMemberStats($mid) {
        // تعداد امانت‌های فعال
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM reservations
            WHERE user_id = ? AND status = 2
        ");
        $stmt->execute([$mid]);
        $active_reservations = $stmt->fetchColumn();

        // تعداد کل امانت‌ها
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM reservations WHERE user_id = ?
        ");
        $stmt->execute([$mid]);
        $total_reservations = $stmt->fetchColumn();

        // جریمه فعلی
        $stmt = $this->conn->prepare("
            SELECT penalty FROM members WHERE mid = ?
        ");
        $stmt->execute([$mid]);
        $penalty = $stmt->fetchColumn();

        // تعداد نظرات
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM book_reviews WHERE mid = ?
        ");
        $stmt->execute([$mid]);
        $reviews_count = $stmt->fetchColumn();

        // تعداد تیکت‌ها
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM tickets WHERE user_id = ?
        ");
        $stmt->execute([$mid]);
        $tickets_count = $stmt->fetchColumn();

        return [
            'active_reservations' => $active_reservations,
            'total_reservations' => $total_reservations,
            'penalty' => $penalty,
            'reviews_count' => $reviews_count,
            'tickets_count' => $tickets_count
        ];
    }

    /**
     * دریافت فعالیت‌های اخیر کاربر
     */
    public function getRecentActivity($mid, $limit = 10) {
        $stmt = $this->conn->prepare("
            SELECT * FROM member_activity_log
            WHERE mid = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$mid, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * حذف حساب کاربری
     */
    public function deleteAccount($mid, $password) {
        try {
            // بررسی رمز عبور
            $stmt = $this->conn->prepare("
                SELECT password FROM members WHERE mid = ? LIMIT 1
            ");
            $stmt->execute([$mid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'رمز عبور اشتباه است'
                ];
            }

            // بررسی امانت‌های فعال
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) FROM reservations
                WHERE user_id = ? AND status = 2
            ");
            $stmt->execute([$mid]);
            $active_count = $stmt->fetchColumn();

            if ($active_count > 0) {
                return [
                    'success' => false,
                    'message' => 'شما امانت‌های فعال دارید. ابتدا باید کتاب‌ها را بازگردانید'
                ];
            }

            // بررسی جریمه معوقه
            $stmt = $this->conn->prepare("
                SELECT penalty FROM members WHERE mid = ?
            ");
            $stmt->execute([$mid]);
            $penalty = $stmt->fetchColumn();

            if ($penalty > 0) {
                return [
                    'success' => false,
                    'message' => 'شما جریمه معوقه دارید. ابتدا باید جریمه را پرداخت کنید'
                ];
            }

            // غیرفعال کردن حساب (نه حذف کامل)
            $stmt = $this->conn->prepare("
                UPDATE members
                SET is_active = 0,
                    deleted_at = NOW(),
                    email = CONCAT('deleted_', mid, '@deleted.com')
                WHERE mid = ?
            ");
            $result = $stmt->execute([$mid]);

            return [
                'success' => $result,
                'message' => $result ? 'حساب کاربری با موفقیت حذف شد' : 'خطا در حذف حساب'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'خطا در حذف حساب کاربری'
            ];
        }
    }
}
