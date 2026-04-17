<?php
//Archivo: ./includes/User.php
class User {
    private $conn;
    private $tenant_id;

    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    public function getAll() {
        $sql = "SELECT id, username, full_name, role, created_at FROM users WHERE tenant_id = :tid ORDER BY role ASC, username ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    
    public function create($username,$password, $full_name, $role) {
        // Verificar duplicados
        $sqlCheck = "SELECT COUNT(*) FROM users WHERE username = :u AND tenant_id = :tid";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([':u' => $username, ':tid' => $this->tenant_id]);
        if($stmtCheck->fetchColumn() > 0) return ['status' => false, 'message' => 'El usuario ya existe'];

        // Hash seguro
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (tenant_id, username, password, full_name, role) VALUES (:tid, :u, :p, :fn, :r)";
        $stmt = $this->conn->prepare($sql);
        if($stmt->execute([':tid' => $this->tenant_id, ':u' => $username, ':p' => $hash, ':fn' => $full_name, ':r' => $role])){
            return ['status' => true];
        }
        return ['status' => false, 'message' => 'Error al insertar en BD'];
    }

    public function update($id, $username, $full_name, $role, $password = null) {
        $params = [':u' => $username, ':fn' => $full_name, ':r' => $role, ':id' => $id, ':tid' => $this->tenant_id];
        $sql = "UPDATE users SET username = :u, full_name = :fn, role = :r";

        // Solo actualizamos password si enviaron uno nuevo
        if (!empty($password)) {
            $sql .= ", password = :p";
            $params[':p'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = :id AND tenant_id = :tid";
        
        $stmt = $this->conn->prepare($sql);
        if($stmt->execute($params)) return ['status' => true];
        return ['status' => false, 'message' => 'Error al actualizar'];
    }
   
    public function delete($id)
    {
        if ($id == $_SESSION['user_id']) {
            return ['status' => false, 'message' => 'No puedes eliminar tu propia cuenta.'];
        }

        $sql = "DELETE FROM users WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);

        if ($stmt->execute([':id' => $id, ':tid' => $this->tenant_id])) {
            return ['status' => true, 'message' => 'Usuario borrado con éxito.'];
        }
        return ['status' => false, 'message' => 'Error al eliminar de la base de datos.'];
    }
    
}