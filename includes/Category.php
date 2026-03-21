<?php
class Category {
    private $conn;
    private $tenant_id;

    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    public function getAll() {
        $sql = "SELECT * FROM categories WHERE tenant_id = :tid ORDER BY name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($name) {
        $sql = "INSERT INTO categories (tenant_id, name) VALUES (:tid, :n)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':tid' => $this->tenant_id, ':n' => $name]);
    }

    public function update($id, $name) {
        $sql = "UPDATE categories SET name = :n WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':n' => $name, ':id' => $id, ':tid' => $this->tenant_id]);
    }

    public function delete($id) {
        // OJO: Podrías validar primero si hay productos usando esta categoría
        $sql = "DELETE FROM categories WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':tid' => $this->tenant_id]);
    }
}