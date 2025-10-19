<?php
/**
 * Database Configuration - SQLite Version
 * SmartEdu Hub - HTML/PHP Version with SQLite
 */

class Database {
    private $db_file = "smartedu_hub.db";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            // إنشاء قاعدة بيانات SQLite
            $db_path = __DIR__ . '/../' . $this->db_file;
            $this->conn = new PDO("sqlite:" . $db_path);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // تمكين الـ Foreign Keys في SQLite
            $this->conn->exec("PRAGMA foreign_keys = ON");
            
            echo "<!-- اتصال ناجح بقاعدة بيانات SQLite -->";
            
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        
        return $this->conn;
    }
}
?>