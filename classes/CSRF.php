<?php
/**
 * کلاس محافظت در برابر CSRF
 * Cross-Site Request Forgery Protection
 */

class CSRF {
    private static $tokenKey = 'csrf_token';
    private static $tokenTimeKey = 'csrf_token_time';
    private static $tokensKey = 'csrf_tokens'; // برای multiple forms
    
    /**
     * تولید توکن CSRF
     */
    public static function generateToken($formName = 'default') {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        // تولید توکن جدید
        $token = bin2hex(random_bytes(32));
        $time = time();
        
        // ذخیره توکن
        if (!isset($_SESSION[self::$tokensKey])) {
            $_SESSION[self::$tokensKey] = [];
        }
        
        $_SESSION[self::$tokensKey][$formName] = [
            'token' => $token,
            'time' => $time
        ];
        
        // پاکسازی توکن‌های قدیمی
        self::cleanOldTokens();
        
        return $token;
    }
    
    /**
     * اعتبارسنجی توکن CSRF
     */
    public static function validateToken($token, $formName = 'default') {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        // چک کردن وجود توکن
        if (!isset($_SESSION[self::$tokensKey][$formName])) {
            logWarning('CSRF token not found', ['form' => $formName]);
            return false;
        }
        
        $storedData = $_SESSION[self::$tokensKey][$formName];
        $storedToken = $storedData['token'];
        $tokenTime = $storedData['time'];
        
        // چک کردن انقضای توکن
        if ((time() - $tokenTime) > CSRF_TOKEN_EXPIRE) {
            logWarning('CSRF token expired', ['form' => $formName]);
            unset($_SESSION[self::$tokensKey][$formName]);
            return false;
        }
        
        // مقایسه امن توکن‌ها
        if (!hash_equals($storedToken, $token)) {
            logWarning('CSRF token mismatch', [
                'form' => $formName,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return false;
        }
        
        // حذف توکن بعد از استفاده (One-Time Token)
        unset($_SESSION[self::$tokensKey][$formName]);
        
        return true;
    }
    
    /**
     * پاکسازی توکن‌های قدیمی
     */
    private static function cleanOldTokens() {
        if (!isset($_SESSION[self::$tokensKey])) {
            return;
        }
        
        $currentTime = time();
        
        foreach ($_SESSION[self::$tokensKey] as $formName => $data) {
            if (($currentTime - $data['time']) > CSRF_TOKEN_EXPIRE) {
                unset($_SESSION[self::$tokensKey][$formName]);
            }
        }
    }
    
    /**
     * دریافت فیلد HTML برای فرم
     */
    public static function getTokenField($formName = 'default') {
        $token = self::generateToken($formName);
        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s" data-form="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($formName, ENT_QUOTES, 'UTF-8')
        );
    }
    
    /**
     * دریافت Meta Tag برای AJAX
     */
    public static function getMetaTag($formName = 'default') {
        $token = self::generateToken($formName);
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }
    
    /**
     * اعتبارسنجی Request (Middleware)
     */
    public static function validateRequest($formName = 'default') {
        // فقط برای POST, PUT, DELETE
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return true;
        }
        
        // دریافت توکن از Request
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!$token) {
            logWarning('CSRF token missing', [
                'method' => $method,
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            http_response_code(403);
            die(json_encode([
                'success' => false,
                'message' => 'خطای امنیتی: توکن CSRF یافت نشد'
            ]));
        }
        
        if (!self::validateToken($token, $formName)) {
            http_response_code(403);
            die(json_encode([
                'success' => false,
                'message' => 'خطای امنیتی: توکن CSRF نامعتبر است'
            ]));
        }
        
        return true;
    }
    
    /**
     * حذف تمام توکن‌ها (برای Logout)
     */
    public static function clearAllTokens() {
        if (isset($_SESSION[self::$tokensKey])) {
            unset($_SESSION[self::$tokensKey]);
        }
    }
    
    /**
     * دریافت توکن برای AJAX
     */
    public static function getToken($formName = 'default') {
        return self::generateToken($formName);
    }
}
?>
