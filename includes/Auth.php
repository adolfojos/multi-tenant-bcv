<?php
class Auth {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        // JOIN crítico: Trae datos del usuario Y de su tienda
        $query = "SELECT 
                    u.id, u.username, u.password, u.tenant_id, u.role, 
                    t.status AS tenant_status, 
                    t.expiration_date,
                    t.business_name
                  FROM " . $this->table . " u
                  JOIN tenants t ON u.tenant_id = t.id
                  WHERE u.username = :username 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($password, $row['password'])) {
                
                // VALIDACIONES DE NEGOCIO
                if ($row['tenant_status'] !== 'active') {
                    return "SUSPENDED";
                }

                $today = date('Y-m-d');
                if ($row['expiration_date'] < $today) {
                    return "EXPIRED";
                }

                // INICIO DE SESIÓN EXITOSO
                if (session_status() === PHP_SESSION_NONE) session_start();
                
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['tenant_id'] = $row['tenant_id']; // ID CRÍTICO
                $_SESSION['tenant_name'] = $row['business_name'];
                $_SESSION['expiration_date'] = $row['expiration_date'];
                $_SESSION['role'] = $row['role']; // 'admin' o 'seller'

                return "OK";
            }
        }
        return "INVALID";
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header("Location: login.php");
    }
    
}
?> 
