<?php
$host = "sql300.infinityfree.com";
$port = "3306";
$db_name = "if0_40155395_smartedu";
$username = "if0_40155395";
$password = "Ladj14013"; // أو كلمة المرور التي ستختارها

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db_name};charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات");
}

class Database {
    private $host = "sql300.infinityfree.com";
    private $port = "3306";
    private $db_name = "if0_40155395_smartedu";
    private $username = "if0_40155395";
    private $password = "Ladj14013";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=sql100.infinityfree.com" . $this->host . ";port=" . $this->port . ";dbname=if0_40155395_smartedu" . $this->db_name . ";charset=utf8mb4",
                $this->username, $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        return $this->conn;
    }
}

function getDB() {
    global $pdo;
    return $pdo;
}
?>