<?php
/**
 * کلاس اعتبارسنجی داده‌ها
 * Validation + Sanitization
 */

class Validator {
    private $errors = [];
    private $data = [];

    /**
     * اعتبارسنجی داده‌ها بر اساس قوانین
     *
     * @param array $data داده‌های ورودی
     * @param array $rules قوانین اعتبارسنجی
     * @return bool
     */
    public function validate($data, $rules) {
        $this->errors = [];
        $this->data = $data;

        foreach ($rules as $field => $ruleSet) {
            $rulesArray = explode('|', $ruleSet);
            $value = $data[$field] ?? null;

            foreach ($rulesArray as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * اعمال یک قانون
     */
    private function applyRule($field, $value, $rule) {
        $params = [];

        // استخراج پارامترهای قانون
        if (strpos($rule, ':') !== false) {
            list($rule, $paramStr) = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        }

        $fieldLabel = $this->getFieldLabel($field);

        switch ($rule) {
            case 'required':
                if ($this->isEmpty($value)) {
                    $this->addError($field, "فیلد {$fieldLabel} الزامی است");
                }
                break;

            case 'email':
                if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "فرمت {$fieldLabel} نامعتبر است");
                }
                break;

            case 'min':
                $min = intval($params[0]);
                if (!$this->isEmpty($value) && mb_strlen($value) < $min) {
                    $this->addError($field, "{$fieldLabel} باید حداقل {$min} کاراکتر باشد");
                }
                break;

            case 'max':
                $max = intval($params[0]);
                if (!$this->isEmpty($value) && mb_strlen($value) > $max) {
                    $this->addError($field, "{$fieldLabel} نباید بیشتر از {$max} کاراکتر باشد");
                }
                break;

            case 'numeric':
                if (!$this->isEmpty($value) && !is_numeric($value)) {
                    $this->addError($field, "{$fieldLabel} باید عدد باشد");
                }
                break;

            case 'integer':
                if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "{$fieldLabel} باید عدد صحیح باشد");
                }
                break;

            case 'positive':
                if (!$this->isEmpty($value) && (is_numeric($value) && $value <= 0)) {
                    $this->addError($field, "{$fieldLabel} باید عدد مثبت باشد");
                }
                break;

            case 'isbn':
                if (!$this->isEmpty($value) && !$this->validateISBN($value)) {
                    $this->addError($field, "شابک ({$fieldLabel}) نامعتبر است");
                }
                break;

            case 'persian':
                if (!$this->isEmpty($value) && !$this->isPersian($value)) {
                    $this->addError($field, "{$fieldLabel} فقط باید شامل حروف فارسی باشد");
                }
                break;

            case 'english':
                if (!$this->isEmpty($value) && !$this->isEnglish($value)) {
                    $this->addError($field, "{$fieldLabel} فقط باید شامل حروف انگلیسی باشد");
                }
                break;

            case 'alpha':
                if (!$this->isEmpty($value) && !preg_match('/^[\p{L}\s]+$/u', $value)) {
                    $this->addError($field, "{$fieldLabel} فقط باید شامل حروف باشد");
                }
                break;

            case 'alphanumeric':
                if (!$this->isEmpty($value) && !preg_match('/^[\p{L}\p{N}\s]+$/u', $value)) {
                    $this->addError($field, "{$fieldLabel} فقط باید شامل حروف و اعداد باشد");
                }
                break;

            case 'url':
                if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "آدرس {$fieldLabel} نامعتبر است");
                }
                break;

            case 'date':
                if (!$this->isEmpty($value) && !$this->isValidDate($value)) {
                    $this->addError($field, "تاریخ {$fieldLabel} نامعتبر است");
                }
                break;

            case 'jalali_date':
                if (!$this->isEmpty($value) && !$this->isValidJalaliDate($value)) {
                    $this->addError($field, "تاریخ شمسی {$fieldLabel} نامعتبر است (فرمت: 1400/01/01)");
                }
                break;

            case 'username':
                if (!$this->isEmpty($value) && !preg_match('/^[a-zA-Z0-9_]{3,20}$/u', $value)) {
                    $this->addError($field, "{$fieldLabel} باید 3-20 کاراکتر و شامل حروف انگلیسی، اعداد و _ باشد");
                }
                break;

            case 'password':
                if (!$this->isEmpty($value)) {
                    $minLength = PASSWORD_MIN_LENGTH;
                    if (strlen($value) < $minLength) {
                        $this->addError($field, "رمز عبور باید حداقل {$minLength} کاراکتر باشد");
                    }
                    if (!preg_match('/[A-Z]/', $value)) {
                        $this->addError($field, "رمز عبور باید حداقل یک حرف بزرگ داشته باشد");
                    }
                    if (!preg_match('/[a-z]/', $value)) {
                        $this->addError($field, "رمز عبور باید حداقل یک حرف کوچک داشته باشد");
                    }
                    if (!preg_match('/[0-9]/', $value)) {
                        $this->addError($field, "رمز عبور باید حداقل یک عدد داشته باشد");
                    }
                }
                break;

            case 'match':
                $matchField = $params[0];
                $matchValue = $this->data[$matchField] ?? null;
                if ($value !== $matchValue) {
                    $this->addError($field, "{$fieldLabel} با {$this->getFieldLabel($matchField)} مطابقت ندارد");
                }
                break;

            case 'unique':
                // قانون unique نیاز به پارامترهای بیشتری دارد
                // params[0] = table name
                // params[1] = column name
                // params[2] = except id (optional)
                if (count($params) >= 2) {
                    $table = $params[0];
                    $column = $params[1];
                    $exceptId = $params[2] ?? null;

                    if (!$this->isUnique($table, $column, $value, $exceptId)) {
                        $this->addError($field, "{$fieldLabel} قبلاً ثبت شده است");
                    }
                }
                break;

            case 'exists':
                // چک کردن اینکه مقدار در جدول دیگری وجود دارد
                // params[0] = table name
                // params[1] = column name
                if (count($params) >= 2) {
                    $table = $params[0];
                    $column = $params[1];

                    if (!$this->recordExists($table, $column, $value)) {
                        $this->addError($field, "{$fieldLabel} نامعتبر است");
                    }
                }
                break;

            case 'in':
                // چک کردن اینکه مقدار در لیست مجاز است
                if (!in_array($value, $params)) {
                    $this->addError($field, "{$fieldLabel} نامعتبر است");
                }
                break;

            case 'file':
                // اعتبارسنجی فایل آپلودی
                if (isset($_FILES[$field])) {
                    $this->validateFile($field, $params);
                }
                break;
        }
    }

    /**
     * اعتبارسنجی فایل
     */
    private function validateFile($field, $params) {
        $file = $_FILES[$field];

        // چک کردن خطای آپلود
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, "خطا در آپلود فایل");
            return;
        }

        // چک کردن حجم فایل
        $maxSize = isset($params[0]) ? intval($params[0]) : MAX_UPLOAD_SIZE;
        if ($file['size'] > $maxSize) {
            $maxSizeMB = round($maxSize / 1048576, 2);
            $this->addError($field, "حجم فایل نباید بیشتر از {$maxSizeMB} مگابایت باشد");
            return;
        }

        // چک کردن نوع فایل
        if (isset($params[1])) {
            $allowedTypes = explode(',', $params[1]);
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedTypes)) {
                $this->addError($field, "فرمت فایل باید یکی از موارد زیر باشد: " . implode(', ', $allowedTypes));
            }
        }
    }

    /**
     * چک کردن خالی بودن مقدار
     */
    private function isEmpty($value) {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    /**
     * افزودن خطا
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * دریافت label فیلد
     */
    private function getFieldLabel($field) {
        $labels = [
            'book_name' => 'نام کتاب',
            'book_isbn' => 'شابک',
            'book_author' => 'نویسنده',
            'book_publisher' => 'ناشر',
            'book_year' => 'سال انتشار',
            'book_count' => 'تعداد',
            'book_category' => 'دسته‌بندی',
            'username' => 'نام کاربری',
            'password' => 'رمز عبور',
            'email' => 'ایمیل',
            'name' => 'نام',
            'surname' => 'نام خانوادگی',
            'phone' => 'شماره تلفن',
        ];

        return $labels[$field] ?? $field;
    }

    /**
     * اعتبارسنجی شابک
     */
    private function validateISBN($isbn) {
        // حذف کاراکترهای غیرضروری
        $isbn = preg_replace('/[^0-9X]/i', '', $isbn);
        $length = strlen($isbn);

        if ($length === 10) {
            return $this->validateISBN10($isbn);
        } elseif ($length === 13) {
            return $this->validateISBN13($isbn);
        }

        return false;
    }

    /**
     * اعتبارسنجی ISBN-10
     */
    private function validateISBN10($isbn) {
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $digit = ($isbn[$i] === 'X' || $isbn[$i] === 'x') ? 10 : intval($isbn[$i]);
            $sum += $digit * (10 - $i);
        }
        return ($sum % 11 === 0);
    }

    /**
     * اعتبارسنجی ISBN-13
     */
    private function validateISBN13($isbn) {
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $multiplier = ($i % 2 === 0) ? 1 : 3;
            $sum += intval($isbn[$i]) * $multiplier;
        }
        return ($sum % 10 === 0);
    }

    /**
     * چک کردن فارسی بودن متن
     */
    private function isPersian($str) {
        return preg_match('/^[\x{0600}-\x{06FF}\x{200C}\s]+$/u', $str);
    }

    /**
     * چک کردن انگلیسی بودن متن
     */
    private function isEnglish($str) {
        return preg_match('/^[a-zA-Z\s]+$/', $str);
    }

    /**
     * اعتبارسنجی تاریخ میلادی
     */
    private function isValidDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * اعتبارسنجی تاریخ شمسی
     */
    private function isValidJalaliDate($date) {
        // فرمت: 1400/01/01 یا 1400-01-01
        $pattern = '/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/';

        if (!preg_match($pattern, $date, $matches)) {
            return false;
        }

        $year = intval($matches[1]);
        $month = intval($matches[2]);
        $day = intval($matches[3]);

        // بازه معتبر سال
        if ($year < 1300 || $year > 1500) {
            return false;
        }

        // بازه معتبر ماه
        if ($month < 1 || $month > 12) {
            return false;
        }

        // بازه معتبر روز
        if ($month <= 6) {
            $maxDay = 31;
        } elseif ($month <= 11) {
            $maxDay = 30;
        } else {
            // اسفند
            $maxDay = $this->isLeapYear($year) ? 30 : 29;
        }

        if ($day < 1 || $day > $maxDay) {
            return false;
        }

        return true;
    }

    /**
     * چک کردن سال کبیسه شمسی
     */
    private function isLeapYear($year) {
        $mod = $year % 33;
        return ($mod === 1 || $mod === 5 || $mod === 9 || $mod === 13 ||
                $mod === 17 || $mod === 22 || $mod === 26 || $mod === 30);
    }

    /**
     * چک کردن یکتا بودن در دیتابیس
     */
    private function isUnique($table, $column, $value, $exceptId = null) {
        $db = Database::getInstance();

        $query = "SELECT COUNT(*) as count FROM `$table` WHERE `$column` = ?";
        $params = [$value];
        $types = 's';

        if ($exceptId !== null) {
            $query .= " AND id != ?";
            $params[] = $exceptId;
            $types .= 'i';
        }

        $result = $db->selectOne($query, $params, $types);
        return $result ? intval($result['count']) === 0 : true;
    }

    /**
     * چک کردن وجود رکورد در دیتابیس
     */
    private function recordExists($table, $column, $value) {
        $db = Database::getInstance();
        $query = "SELECT COUNT(*) as count FROM `$table` WHERE `$column` = ?";
        $result = $db->selectOne($query, [$value], 's');
        return $result ? intval($result['count']) > 0 : false;
    }

    /**
     * دریافت تمام خطاها
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * دریافت خطاهای یک فیلد خاص
     */
    public function getFieldErrors($field) {
        return $this->errors[$field] ?? [];
    }

    /**
     * دریافت اولین خطای هر فیلد
     */
    public function getFirstErrors() {
        $firstErrors = [];
        foreach ($this->errors as $field => $errors) {
            $firstErrors[$field] = $errors[0];
        }
        return $firstErrors;
    }

    /**
     * دریافت تمام پیام‌های خطا در یک آرایه
     */
    public function getErrorMessages() {
        $messages = [];
        foreach ($this->errors as $field => $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }
        return $messages;
    }

    /**
     * دریافت اولین پیام خطا
     */
    public function getFirstErrorMessage() {
        $messages = $this->getErrorMessages();
        return !empty($messages) ? $messages[0] : null;
    }

    /**
     * چک کردن وجود خطا
     */
    public function hasErrors() {
        return !empty($this->errors);
    }

    /**
     * پاکسازی خطاها
     */
    public function clearErrors() {
        $this->errors = [];
    }

    // === متدهای Static برای Sanitization ===

    /**
     * پاکسازی عدد صحیح
     */
    public static function sanitizeInt($value, $default = 0) {
        return filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['default' => $default, 'min_range' => 0]
        ]);
    }

    /**
     * پاکسازی متن
     */
    public static function sanitizeString($value) {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * پاکسازی ایمیل
     */
    public static function sanitizeEmail($value) {
        return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
    }

    /**
     * پاکسازی URL
     */
    public static function sanitizeUrl($value) {
        return filter_var(trim($value), FILTER_SANITIZE_URL);
    }

    /**
     * پاکسازی نام فایل
     */
    public static function sanitizeFilename($filename) {
        // حذف کاراکترهای خطرناک
        $filename = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '', $filename);
        // جلوگیری از Path Traversal
        $filename = basename($filename);
        return $filename;
    }

    /**
     * پاکسازی شابک
     */
    public static function sanitizeISBN($isbn) {
        // فقط نگه داشتن اعداد و X
        return preg_replace('/[^0-9X]/i', '', strtoupper($isbn));
    }

    /**
     * پاکسازی شماره تلفن
     */
    public static function sanitizePhone($phone) {
        // حذف تمام کاراکترها به جز اعداد و +
        return preg_replace('/[^0-9+]/', '', $phone);
    }
}
?>
