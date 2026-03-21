<?php
// config/db.php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Detectar si estamos en localhost
        if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
            // CREDENCIALES LOCALES
            $this->host     = "localhost";
            $this->db_name  = "mtb_db";
            $this->username = "root";
            $this->password = "";
        } else {
            // CREDENCIALES DE PRODUCCIÓN (Cambia estos datos por los de tu hosting)
            $this->host     = "sql312.ezyro.com"; 
            $this->db_name  = "ezyro_41444378_mtb_db";
            $this->username = "	ezyro_41444378";
            $this->password = "9cde61e98ccf4bc";
        }
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            // Opciones recomendadas para PDO
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            // En producción, es mejor no mostrar el mensaje de error real al usuario por seguridad
            error_log("Error de conexión: " . $exception->getMessage());
            die("Lo sentimos, hubo un problema con la conexión a la base de datos.");
        }
        return $this->conn;
    }
}
?>