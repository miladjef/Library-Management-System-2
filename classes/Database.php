<?php
/**
 * کلاس مدیریت دیتابیس با امنیت بالا
 * Singleton Pattern + Prepared Statements
 */

class Database {
    private static $instance = null;
    private $connection;
    private $transactionStarted = false;
    private $queryCount = 0;
    private $lastQuery = '';

    /**
     * Constructor - Private برای Singleton
     */
    private function __construct() {
        try {
            $this->connection = new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME
            );

            if ($this->connection->connect_error) {
                throw new Exception("Database Connection Failed: " . $this->connection->connect_error);
            }

            // تنظیم Character Set
            if (!$this->connection->set_charset(DB_CHARSET)) {
                throw new Exception("Error loading character set " . DB_CHARSET);
            }

            // تنظیمات امنیتی MySQL
            $this->connection->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
            $this->connection->query("SET SESSION time_zone = '+03:30'"); // Iran Time

            // لاگ اتصال موفق
            if (DEBUG_MODE) {
                logInfo('Database connected successfully');
            }

        } catch (Exception $e) {
            logError('Database connection error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            if (DEBUG_MODE) {
                die("خطا در اتصال به دیتابیس: " . $e->getMessage());
            } else {
                die("خطای سیستمی. لطفاً با مدیر تماس بگیرید.");
            }
        }
    }

    /**
     * دریافت نمونه Singleton
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * دریافت Connection
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * آماده‌سازی Prepared Statement
     */
    public function prepare($query) {
        $this->lastQuery = $query;
        $this->queryCount++;

        $stmt = $this->connection->prepare($query);

        if (!$stmt) {
            logError('Prepare statement failed', [
                'query' => $query,
                'error' => $this->connection->error,
                'errno' => $this->connection->errno
            ]);
            throw new Exception("خطا در آماده‌سازی Query: " . $this->connection->error);
        }

        return $stmt;
    }

    /**
     * اجرای Query ساده (فقط برای موارد خاص)
     */
    public function query($query) {
        $this->lastQuery = $query;
        $this->queryCount++;

        $result = $this->connection->query($query);

        if ($result === false) {
            logError('Query execution failed', [
                'query' => $query,
                'error' => $this->connection->error
            ]);
            throw new Exception("خطا در اجرای Query");
        }

        return $result;
    }

    /**
     * اجرای SELECT و دریافت تمام رکوردها
     */
    public function select($query, $params = [], $types = '') {
        $stmt = $this->prepare($query);

        if (!empty($params)) {
            if (empty($types)) {
                // تشخیص خودکار نوع پارامترها
                $types = $this->detectParamTypes($params);
            }
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        $stmt->close();
        return $data;
    }

    /**
     * اجرای SELECT و دریافت یک رکورد
     */
    public function selectOne($query, $params = [], $types = '') {
        $data = $this->select($query, $params, $types);
        return !empty($data) ? $data[0] : null;
    }

    /**
     * اجرای INSERT
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $values = array_values($data);

        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $fieldList = implode(',', array_map(function($f) {
            return "`$f`";
        }, $fields));

        $query = "INSERT INTO `$table` ($fieldList) VALUES ($placeholders)";
        $types = $this->detectParamTypes($values);

        $stmt = $this->prepare($query);
        $stmt->bind_param($types, ...$values);

        $success = $stmt->execute();
        $insertId = $stmt->insert_id;
        $affectedRows = $stmt->affected_rows;

        $stmt->close();

        if ($success) {
            logInfo("Record inserted", [
                'table' => $table,
                'id' => $insertId
            ]);
        }

        return [
            'success' => $success,
            'insert_id' => $insertId,
            'affected_rows' => $affectedRows
        ];
    }

    /**
     * اجرای UPDATE
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        $values = [];

        foreach ($data as $field => $value) {
            $setParts[] = "`$field` = ?";
            $values[] = $value;
        }

        $setClause = implode(', ', $setParts);
        $values = array_merge($values, $whereParams);

        $query = "UPDATE `$table` SET $setClause WHERE $where";
        $types = $this->detectParamTypes($values);

        $stmt = $this->prepare($query);
        $stmt->bind_param($types, ...$values);

        $success = $stmt->execute();
        $affectedRows = $stmt->affected_rows;

        $stmt->close();

        if ($success) {
            logInfo("Record updated", [
                'table' => $table,
                'affected_rows' => $affectedRows
            ]);
        }

        return [
            'success' => $success,
            'affected_rows' => $affectedRows
        ];
    }

    /**
     * اجرای DELETE
     */
    public function delete($table, $where, $whereParams = []) {
        $query = "DELETE FROM `$table` WHERE $where";
        $types = $this->detectParamTypes($whereParams);

        $stmt = $this->prepare($query);

        if (!empty($whereParams)) {
            $stmt->bind_param($types, ...$whereParams);
        }

        $success = $stmt->execute();
        $affectedRows = $stmt->affected_rows;

        $stmt->close();

        if ($success) {
            logWarning("Record deleted", [
                'table' => $table,
                'affected_rows' => $affectedRows
            ]);
        }

        return [
            'success' => $success,
            'affected_rows' => $affectedRows
        ];
    }

    /**
     * شمارش رکوردها
     */
    public function count($table, $where = '1=1', $params = []) {
        $query = "SELECT COUNT(*) as total FROM `$table` WHERE $where";
        $result = $this->selectOne($query, $params);
        return $result ? intval($result['total']) : 0;
    }

    /**
     * چک کردن وجود رکورد
     */
    public function exists($table, $where, $params = []) {
        return $this->count($table, $where, $params) > 0;
    }

    /**
     * شروع Transaction
     */
    public function beginTransaction() {
        $this->connection->begin_transaction();
        $this->transactionStarted = true;
        logInfo('Transaction started');
    }

    /**
     * Commit کردن Transaction
     */
    public function commit() {
        if ($this->transactionStarted) {
            $this->connection->commit();
            $this->transactionStarted = false;
            logInfo('Transaction committed');
        }
    }

    /**
     * Rollback کردن Transaction
     */
    public function rollback() {
        if ($this->transactionStarted) {
            $this->connection->rollback();
            $this->transactionStarted = false;
            logWarning('Transaction rolled back');
        }
    }

    /**
     * دریافت Last Insert ID
     */
    public function lastInsertId() {
        return $this->connection->insert_id;
    }

    /**
     * Escape کردن String
     */
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }

    /**
     * تشخیص خودکار نوع پارامترها
     */
    private function detectParamTypes($params) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b'; // blob
            }
        }
        return $types;
    }

    /**
     * دریافت تعداد کل Query ها
     */
    public function getQueryCount() {
        return $this->queryCount;
    }

    /**
     * دریافت آخرین Query
     */
    public function getLastQuery() {
        return $this->lastQuery;
    }

    /**
     * بستن اتصال
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
            logInfo('Database connection closed', [
                'query_count' => $this->queryCount
            ]);
        }
    }

    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * جلوگیری از Clone
     */
    private function __clone() {}

    /**
     * جلوگیری از Unserialize
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>
