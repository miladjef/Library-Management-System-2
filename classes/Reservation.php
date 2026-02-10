<?php
/**
 * Reservation Class
 * مدیریت رزرو و امانت کتاب‌ها
 *
 * @version 2.0
 */

class Reservation {
    private $db;
    private $validator;
    private $jdf; // کلاس تاریخ شمسی

    // مقادیر پیش‌فرض
    private $penaltyPerDay = 5000; // ریال
    private $maxActiveBorrows = 3;
    private $defaultBorrowDays = 15;

    public function __construct($database, $validator) {
        $this->db = $database;
        $this->validator = $validator;

        // بارگذاری تنظیمات از دیتابیس
        $this->loadSettings();
    }

    /**
     * بارگذاری تنظیمات از جدول options
     */
    private function loadSettings() {
        $stmt = $this->db->query("SELECT option_key, option_value FROM options WHERE option_key IN ('penalty_per_day', 'max_active_borrows', 'default_borrow_days')");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['option_key']) {
                case 'penalty_per_day':
                    $this->penaltyPerDay = (int)$row['option_value'];
                    break;
                case 'max_active_borrows':
                    $this->maxActiveBorrows = (int)$row['option_value'];
                    break;
                case 'default_borrow_days':
                    $this->defaultBorrowDays = (int)$row['option_value'];
                    break;
            }
        }
    }

    /**
     * ثبت درخواست رزرو جدید
     */
    public function createReservation($memberId, $bookId, $duration = null) {
        // اعتبارسنجی ورودی
        if (!is_numeric($memberId) || !is_numeric($bookId)) {
            return ['success' => false, 'error' => 'اطلاعات نامعتبر'];
        }

        // بررسی فعال بودن عضو
        $member = $this->getMemberStatus($memberId);
        if (!$member || !$member['is_active']) {
            return ['success' => false, 'error' => 'حساب کاربری غیرفعال است'];
        }

        // بررسی جریمه پرداخت نشده
        if ($this->hasUnpaidPenalty($memberId)) {
            return [
                'success' => false,
                'error' => 'شما جریمه پرداخت نشده دارید. لطفاً ابتدا جریمه را پرداخت کنید.'
            ];
        }

        // بررسی تعداد رزروهای فعال
        if ($this->getActiveBorrowCount($memberId) >= $this->maxActiveBorrows) {
            return [
                'success' => false,
                'error' => "حداکثر تعداد کتاب‌های قابل امانت: {$this->maxActiveBorrows}"
            ];
        }

        // بررسی موجودی کتاب
        if (!$this->isBookAvailable($bookId)) {
            return ['success' => false, 'error' => 'این کتاب در حال حاضر موجود نیست'];
        }

        // محاسبه تاریخ بازگشت
        $borrowDays = $duration ?? $this->defaultBorrowDays;
        $borrowDate = date('Y-m-d H:i:s');
        $returnDate = date('Y-m-d H:i:s', strtotime("+{$borrowDays} days"));

        // درج رزرو
        $stmt = $this->db->prepare("
            INSERT INTO reservations (
                member_id, book_id, borrow_date, return_date,
                status, created_at
            ) VALUES (?, ?, ?, ?, 'borrowed', NOW())
        ");

        $result = $stmt->execute([
            $memberId,
            $bookId,
            $borrowDate,
            $returnDate
        ]);

        if ($result) {
            // کاهش موجودی کتاب
            $this->decrementBookCount($bookId);

            return [
                'success' => true,
                'reservation_id' => $this->db->lastInsertId(),
                'return_date' => $returnDate,
                'message' => 'رزرو با موفقیت ثبت شد'
            ];
        }

        return ['success' => false, 'error' => 'خطا در ثبت رزرو'];
    }

    /**
     * بازگشت کتاب
     */
    public function returnBook($reservationId) {
        // دریافت اطلاعات رزرو
        $reservation = $this->getReservationById($reservationId);

        if (!$reservation) {
            return ['success' => false, 'error' => 'رزرو یافت نشد'];
        }

        if ($reservation['status'] !== 'borrowed') {
            return ['success' => false, 'error' => 'این رزرو قبلاً بسته شده است'];
        }

        $actualReturnDate = date('Y-m-d H:i:s');
        $returnDate = strtotime($reservation['return_date']);
        $actualReturn = strtotime($actualReturnDate);

        // محاسبه جریمه در صورت تاخیر
        $penalty = 0;
        $delayDays = 0;

        if ($actualReturn > $returnDate) {
            $delayDays = ceil(($actualReturn - $returnDate) / 86400);
            $penalty = $delayDays * $this->penaltyPerDay;
        }

        // آپدیت رزرو
        $stmt = $this->db->prepare("
            UPDATE reservations
            SET status = 'returned',
                actual_return_date = ?,
                delay_days = ?,
                penalty_amount = ?,
                updated_at = NOW()
            WHERE rid = ?
        ");

        $result = $stmt->execute([
            $actualReturnDate,
            $delayDays,
            $penalty,
            $reservationId
        ]);

        if ($result) {
            // افزایش موجودی کتاب
            $this->incrementBookCount($reservation['book_id']);

            return [
                'success' => true,
                'penalty' => $penalty,
                'delay_days' => $delayDays,
                'message' => $penalty > 0 ?
                    "کتاب با {$delayDays} روز تاخیر بازگشت داده شد. جریمه: {$penalty} ریال" :
                    'کتاب با موفقیت بازگشت داده شد'
            ];
        }

        return ['success' => false, 'error' => 'خطا در ثبت بازگشت کتاب'];
    }

    /**
     * تمدید رزرو
     */
    public function extendReservation($reservationId, $extraDays = 7) {
        $reservation = $this->getReservationById($reservationId);

        if (!$reservation || $reservation['status'] !== 'borrowed') {
            return ['success' => false, 'error' => 'رزرو معتبر نیست'];
        }

        // بررسی تعداد دفعات تمدید
        if ($reservation['extension_count'] >= 2) {
            return ['success' => false, 'error' => 'حداکثر دو بار قابل تمدید است'];
        }

        // بررسی عدم تاخیر
        if (strtotime($reservation['return_date']) < time()) {
            return ['success' => false, 'error' => 'کتاب با تاخیر است و قابل تمدید نیست'];
        }

        $newReturnDate = date('Y-m-d H:i:s', strtotime($reservation['return_date'] . " +{$extraDays} days"));

        $stmt = $this->db->prepare("
            UPDATE reservations
            SET return_date = ?,
                extension_count = extension_count + 1,
                updated_at = NOW()
            WHERE rid = ?
        ");

        $result = $stmt->execute([$newReturnDate, $reservationId]);

        return [
            'success' => $result,
            'new_return_date' => $newReturnDate,
            'message' => $result ? 'رزرو با موفقیت تمدید شد' : 'خطا در تمدید رزرو'
        ];
    }

    /**
     * لغو رزرو
     */
    public function cancelReservation($reservationId) {
        $reservation = $this->getReservationById($reservationId);

        if (!$reservation) {
            return ['success' => false, 'error' => 'رزرو یافت نشد'];
        }

        if ($reservation['status'] === 'returned') {
            return ['success' => false, 'error' => 'رزرو قبلاً بسته شده است'];
        }

        $stmt = $this->db->prepare("
            UPDATE reservations
            SET status = 'cancelled', updated_at = NOW()
            WHERE rid = ?
        ");

        $result = $stmt->execute([$reservationId]);

        if ($result && $reservation['status'] === 'borrowed') {
            // افزایش موجودی در صورت لغو رزرو فعال
            $this->incrementBookCount($reservation['book_id']);
        }

        return [
            'success' => $result,
            'message' => $result ? 'رزرو لغو شد' : 'خطا در لغو رزرو'
        ];
    }

    /**
     * پرداخت جریمه
     */
    public function payPenalty($reservationId, $amount) {
        $reservation = $this->getReservationById($reservationId);

        if (!$reservation || $reservation['penalty_amount'] == 0) {
            return ['success' => false, 'error' => 'جریمه‌ای برای پرداخت وجود ندارد'];
        }

        if ($amount < $reservation['penalty_amount']) {
            return ['success' => false, 'error' => 'مبلغ پرداختی کمتر از جریمه است'];
        }

        $stmt = $this->db->prepare("
            UPDATE reservations
            SET penalty_paid = 1,
                penalty_paid_date = NOW(),
                updated_at = NOW()
            WHERE rid = ?
        ");

        $result = $stmt->execute([$reservationId]);

        return [
            'success' => $result,
            'message' => $result ? 'جریمه با موفقیت پرداخت شد' : 'خطا در ثبت پرداخت'
        ];
    }

    /**
     * دریافت اطلاعات یک رزرو
     */
    public function getReservationById($reservationId) {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   m.name, m.surname, m.username,
                   b.book_name, b.book_isbn, b.author
            FROM reservations r
            INNER JOIN members m ON r.member_id = m.mid
            INNER JOIN books b ON r.book_id = b.bid
            WHERE r.rid = ?
        ");

        $stmt->execute([$reservationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * دریافت لیست رزروها با فیلتر
     */
    public function getAllReservations($page = 1, $perPage = 20, $filters = []) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT r.*,
                       m.name, m.surname, m.username,
                       b.book_name, b.book_isbn
                FROM reservations r
                INNER JOIN members m ON r.member_id = m.mid
                INNER JOIN books b ON r.book_id = b.bid
                WHERE 1=1";

        $params = [];

        // فیلتر وضعیت
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filters['status'];
        }

        // فیلتر عضو
        if (!empty($filters['member_id'])) {
            $sql .= " AND r.member_id = ?";
            $params[] = $filters['member_id'];
        }

        // فیلتر کتاب
        if (!empty($filters['book_id'])) {
            $sql .= " AND r.book_id = ?";
            $params[] = $filters['book_id'];
        }

        // فیلتر تاخیر
        if (isset($filters['delayed']) && $filters['delayed']) {
            $sql .= " AND r.return_date < NOW() AND r.status = 'borrowed'";
        }

        $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return [
            'reservations' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $this->getReservationCount($filters),
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * شمارش رزروها
     */
    private function getReservationCount($filters = []) {
        $sql = "SELECT COUNT(*) FROM reservations r WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['member_id'])) {
            $sql .= " AND r.member_id = ?";
            $params[] = $filters['member_id'];
        }

        if (!empty($filters['book_id'])) {
            $sql .= " AND r.book_id = ?";
            $params[] = $filters['book_id'];
        }

        if (isset($filters['delayed']) && $filters['delayed']) {
            $sql .= " AND r.return_date < NOW() AND r.status = 'borrowed'";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    // متدهای کمکی

    private function getMemberStatus($memberId) {
        $stmt = $this->db->prepare("SELECT is_active FROM members WHERE mid = ?");
        $stmt->execute([$memberId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function hasUnpaidPenalty($memberId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM reservations
            WHERE member_id = ? AND penalty_amount > 0 AND penalty_paid = 0
        ");
        $stmt->execute([$memberId]);
        return $stmt->fetchColumn() > 0;
    }

    private function getActiveBorrowCount($memberId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM reservations
            WHERE member_id = ? AND status = 'borrowed'
        ");
        $stmt->execute([$memberId]);
        return $stmt->fetchColumn();
    }

    private function isBookAvailable($bookId) {
        $stmt = $this->db->prepare("
            SELECT (book_count - COALESCE(
                (SELECT COUNT(*) FROM reservations
                 WHERE book_id = ? AND status = 'borrowed'), 0
            )) as available
            FROM books WHERE bid = ?
        ");
        $stmt->execute([$bookId, $bookId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && $result['available'] > 0;
    }

    private function decrementBookCount($bookId) {
        $stmt = $this->db->prepare("
            UPDATE books
            SET book_count = book_count - 1
            WHERE bid = ? AND book_count > 0
        ");
        $stmt->execute([$bookId]);
    }

    private function incrementBookCount($bookId) {
        $stmt = $this->db->prepare("
            UPDATE books
            SET book_count = book_count + 1
            WHERE bid = ?
        ");
        $stmt->execute([$bookId]);
    }
}
