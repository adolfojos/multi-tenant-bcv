<?php
// config/db.php
class Database {
    private $host = "localhost";
    private $db_name = "mtb_db";// Cambiar según tu configuración
    private $username = "root"; // Cambiar según tu configuración
    private $password = "";     // Cambiar según tu configuración
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8mb4");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>