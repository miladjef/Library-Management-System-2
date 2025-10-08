<?php
// classes/Settings.php

class Settings {
    private $conn;
    private static $cache = [];
    
    public function __construct() {
        $db = Database::getInstance();
        $this->conn = $db->getConnection();
        $this->loadAllSettings();
    }
    
    /**
     * بارگذاری تمام تنظیمات به کش
     */
    private function loadAllSettings() {
        if (empty(self::$cache)) {
            $stmt = $this->conn->query("SELECT setting_key, setting_value FROM system_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    
    /**
     * دریافت مقدار یک تنظیم
     */
    public static function get($key, $default = null) {
        return self::$cache[$key] ?? $default;
    }
    
    /**
     * ذخیره یک تنظیم
     */
    public function set($key, $value) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE system_settings 
                SET setting_value = ?, updated_at = NOW()
                WHERE setting_key = ?
            ");
            
            $result = $stmt->execute([$value, $key]);
            
            if ($result) {
                self::$cache[$key] = $value;
                return true;
            }
            return false;
            
        } catch (PDOException $e) {
            error_log("Settings Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ذخیره چندین تنظیم به صورت دسته‌جمعی
     */
    public function updateMultiple($settings) {
        try {
            $this->conn->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $this->set($key, $value);
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Settings Batch Update Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت تنظیمات بر اساس گروه
     */
    public function getByGroup($group) {
        $stmt = $this->conn->prepare("
            SELECT * FROM system_settings 
            WHERE setting_group = ? 
            ORDER BY id
        ");
        $stmt->execute([$group]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * دریافت تمام تنظیمات برای نمایش
     */
    public function getAll() {
        $stmt = $this->conn->query("
            SELECT * FROM system_settings 
            ORDER BY setting_group, id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * بازنشانی تنظیمات به مقادیر پیش‌فرض
     */
    public function resetToDefaults() {
        $defaults = [
            'max_borrow_days' => '14',
            'daily_penalty_amount' => '5000',
            'max_active_borrows' => '3',
            'max_extensions' => '2',
            'extension_days' => '7'
        ];
        
        return $this->updateMultiple($defaults);
    }
}
