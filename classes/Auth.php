<?php
// classes/Auth.php

class Auth {
    private $db;
    private $conn;
    
    public function __construct($database) {
        $this->db = $database;
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * ورود کاربر
     */
    public function login($username, $password, $remember = false) {
        try {
            // جستجوی کاربر با نام کاربری یا ایمیل
            $stmt = $this->conn->prepare("
                SELECT * FROM members 
                WHERE (username = ? OR email = ?) AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'نام کاربری یا رمز عبور اشتباه است'
                ];
            }
            
            // بررسی رمز عبور
            if (!password_verify($password, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'نام کاربری یا رمز عبور اشتباه است'
                ];
            }
            
            // بررسی جریمه معوقه
            if ($user['penalty'] > 0) {
                $_SESSION['warning_message'] = 'شما ' . number_format($user['penalty']) . ' تومان جریمه معوقه دارید';
            }
            
            // ست کردن Session
            $_SESSION['userid'] = $user['mid'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'] . ' ' . $user['surname'];
            $_SESSION['login_time'] = time();
            
            // به‌روزرسانی آخرین ورود
            $this->updateLastLogin($user['mid']);
            
            // Remember Me
            if ($remember) {
                $this->setRememberToken($user['mid']);
            }
            
            return [
                'success' => true,
                'user_id' => $user['mid'],
                'role' => $user['role']
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'خطا در ورود به سیستم'
            ];
        }
    }
    
    /**
     * ثبت نام کاربر جدید
     */
    public function register($data) {
        try {
            // هش کردن رمز عبور
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // کد تایید ایمیل (اختیاری)
            $verification_code = bin2hex(random_bytes(16));
            
            $stmt = $this->conn->prepare("
                INSERT INTO members (
                    name, surname, username, email, phone, 
                    password, verification_code, created_at, role, is_active
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, NOW(), 1, 1
                )
            ");
            
            $result = $stmt->execute([
                $data['name'],
                $data['surname'],
                $data['username'],
                $data['email'],
                $data['phone'] ?? null,
                $hashed_password,
                $verification_code
            ]);
            
            if ($result) {
                $user_id = $this->conn->lastInsertId();
                
                // ثبت لاگ
                $this->logActivity([
                    'mid' => $user_id,
                    'action_type' => 'register',
                    'description' => 'ثبت نام موفق در سیستم',
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT']
                ]);
                
                // ارسال ایمیل خوش‌آمدگویی (اختیاری)
                // $this->sendWelcomeEmail($data['email'], $data['name']);
                
                return [
                    'success' => true,
                    'user_id' => $user_id
                ];
            }
            
            return [
                'success' => false,
                'message' => 'خطا در ثبت نام'
            ];
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return [
                    'success' => false,
                    'message' => 'این نام کاربری یا ایمیل قبلا ثبت شده است'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'خطا در ثبت نام'
            ];
        }
    }
    
    /**
     * خروج کاربر
     */
    public function logout() {
        // ثبت لاگ خروج
        if (isset($_SESSION['userid'])) {
            $this->logActivity([
                'mid' => $_SESSION['userid'],
                'action_type' => 'logout',
                'description' => 'خروج از سیستم',
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        
        // حذف Remember Token
        if (isset($_COOKIE['remember_token'])) {
            $this->removeRememberToken($_SESSION['userid']);
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        // پاک کردن Session
        $_SESSION = array();
        session_destroy();
        
        return true;
    }
    
    /**
     * بررسی وضعیت لاگین
     */
    public function checkLogin() {
        // بررسی Session
        if (isset($_SESSION['userid']) && $_SESSION['userid']) {
            return true;
        }
        
        // بررسی Remember Token
        if (isset($_COOKIE['remember_token'])) {
            return $this->loginWithToken($_COOKIE['remember_token']);
        }
        
        return false;
    }
    
    /**
     * ست کردن Remember Token
     */
    private function setRememberToken($user_id) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $this->conn->prepare("
            INSERT INTO remember_tokens (mid, token, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, hash('sha256', $token), $expires]);
        
        // ست کردن Cookie
        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
        
        return true;
    }
    
    /**
     * ورود با Token
     */
    private function loginWithToken($token) {
        $stmt = $this->conn->prepare("
            SELECT m.* FROM members m
            JOIN remember_tokens rt ON m.mid = rt.mid
            WHERE rt.token = ? AND rt.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([hash('sha256', $token)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['userid'] = $user['mid'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'] . ' ' . $user['surname'];
            $_SESSION['login_time'] = time();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * حذف Remember Token
     */
    private function removeRememberToken($user_id) {
        $stmt = $this->conn->prepare("
            DELETE FROM remember_tokens WHERE mid = ?
        ");
        $stmt->execute([$user_id]);
    }
    
    /**
     * به‌روزرسانی آخرین ورود
     */
    private function updateLastLogin($user_id) {
        $stmt = $this->conn->prepare("
            UPDATE members 
            SET last_login = NOW(), 
                login_count = login_count + 1
            WHERE mid = ?
        ");
        $stmt->execute([$user_id]);
    }
    
    /**
     * ثبت فعالیت
     */
    public function logActivity($data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO member_activity_log 
                (mid, action_type, description, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $data['mid'],
                $data['action_type'],
                $data['description'],
                $data['ip_address'],
                $data['user_agent']
            ]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * ثبت تلاش ناموفق ورود
     */
    public function logFailedLogin($username, $ip_address) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO failed_login_attempts 
                (username, ip_address, attempt_time)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$username, $ip_address]);
            
            // بررسی تعداد تلاش‌های ناموفق در 15 دقیقه گذشته
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) FROM failed_login_attempts
                WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute([$ip_address]);
            $count = $stmt->fetchColumn();
            
            // مسدود کردن IP در صورت بیش از 5 تلاش
            if ($count >= 5) {
                $this->blockIP($ip_address);
            }
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * مسدود کردن IP
     */
    private function blockIP($ip_address) {
        $stmt = $this->conn->prepare("
            INSERT INTO blocked_ips (ip_address, blocked_until, created_at)
            VALUES (?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())
            ON DUPLICATE KEY UPDATE blocked_until = DATE_ADD(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$ip_address]);
    }
    
    /**
     * بررسی مسدود بودن IP
     */
    public function isIPBlocked($ip_address) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM blocked_ips
            WHERE ip_address = ? AND blocked_until > NOW()
        ");
        $stmt->execute([$ip_address]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * بازیابی رمز عبور
     */
    public function requestPasswordReset($email) {
        try {
            // بررسی وجود ایمیل
            $stmt = $this->conn->prepare("
                SELECT mid, name, email FROM members WHERE email = ? LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'ایمیل یافت نشد'
                ];
            }
            
            // تولید توکن بازیابی
            $reset_token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $this->conn->prepare("
                INSERT INTO password_resets (mid, token, expires_at, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$user['mid'], $reset_token, $expires_at]);
            
            // ارسال ایمیل بازیابی
            // $this->sendPasswordResetEmail($user['email'], $user['name'], $reset_token);
            
            return [
                'success' => true,
                'message' => 'لینک بازیابی به ایمیل شما ارسال شد'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'خطا در ارسال درخواست'
            ];
        }
    }
    
    /**
     * بازنشانی رمز عبور
     */
    public function resetPassword($token, $new_password) {
        try {
            // بررسی اعتبار توکن
            $stmt = $this->conn->prepare("
                SELECT mid FROM password_resets
                WHERE token = ? AND expires_at > NOW() AND used = 0
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reset) {
                return [
                    'success' => false,
                    'message' => 'لینک بازیابی نامعتبر یا منقضی شده است'
                ];
            }
            
            // به‌روزرسانی رمز عبور
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $this->conn->prepare("
                UPDATE members SET password = ? WHERE mid = ?
            ");
            $stmt->execute([$hashed_password, $reset['mid']]);
            
            // علامت‌گذاری توکن به عنوان استفاده شده
            $stmt = $this->conn->prepare("
                UPDATE password_resets SET used = 1 WHERE token = ?
            ");
            $stmt->execute([$token]);
            
            return [
                'success' => true,
                'message' => 'رمز عبور با موفقیت تغییر یافت'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'خطا در تغییر رمز عبور'
            ];
        }
    }
}
