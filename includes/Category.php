<?php
class Category {
    private $conn;
    private $tenant_id;

    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    public function getAll() {
        // Hacemos un LEFT JOIN con products para contar cuántos tiene cada categoría
        $sql = "SELECT c.*, COUNT(p.id) as product_count 
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id AND p.tenant_id = c.tenant_id
                WHERE c.tenant_id = :tid 
                GROUP BY c.id
                ORDER BY c.name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($name, $description = null) {
        $sql = "INSERT INTO categories (tenant_id, name, description) VALUES (:tid, :n, :desc)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':tid' => $this->tenant_id, 
            ':n' => $name,
            ':desc' => $description
        ]);
    }

    public function update($id, $name, $description = null) {
        $sql = "UPDATE categories SET name = :n, description = :desc WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':n' => $name, 
            ':desc' => $description,
            ':id' => $id, 
            ':tid' => $this->tenant_id
        ]);
    }

    public function delete($id) {
        $sql = "DELETE FROM categories WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':tid' => $this->tenant_id]);
    }
}
?>