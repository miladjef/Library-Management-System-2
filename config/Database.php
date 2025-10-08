<?php
// classes/Database.php

class Database {
    private static $instance = null;
    private $connection;
    private $transactionStarted = false;
    
    private function __construct() {
        try {
            $this->connection = new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME
            );
            
            if ($this->connection->connect_error) {
                throw new Exception("اتصال به دیتابیس برقرار نشد: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset(DB_CHARSET);
            
            // تنظیمات امنیتی MySQL
            $this->connection->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
            
        } catch (Exception $e) {
            logError("Database Connection Error", ['error' => $e->getMessage()]);
            die("خطا در اتصال به دیتابیس");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($query) {
        $stmt = $this->connection->prepare($query);
        
        if (!$stmt) {
            logError("Prepare Statement Error", [
                'query' => $query,
                'error' => $this->connection->error
            ]);
            throw new Exception("خطا در آماده‌سازی query");
        }
        
        return $stmt;
    }
    
    public function beginTransaction() {
        $this->connection->begin_transaction();
        $this->transactionStarted = true;
    }
    
    public function commit() {
        if ($this->transactionStarted) {
            $this->connection->commit();
            $this->transactionStarted = false;
        }
    }
    
    public function rollback() {
        if ($this->transactionStarted) {
            $this->connection->rollback();
            $this->transactionStarted = false;
        }
    }
    
    public function lastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    // جلوگیری از clone
    private function __clone() {}
    
    // جلوگیری از unserialize
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>
