<?php
class Customer {
    private $conn;
    private $tenant_id;

    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    public function getAll() {
        $sql = "SELECT * FROM customers WHERE tenant_id = :tid ORDER BY name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($name, $document, $phone) {
        $sql = "INSERT INTO customers (tenant_id, name, document, phone) VALUES (:tid, :n, :d, :p)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':tid' => $this->tenant_id,
            ':n' => $name,
            ':d' => $document,
            ':p' => $phone
        ]);
    }

    public function update($id, $name, $document, $phone) {
        $sql = "UPDATE customers SET name = :n, document = :d, phone = :p WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':n' => $name,
            ':d' => $document,
            ':p' => $phone,
            ':id' => $id,
            ':tid' => $this->tenant_id
        ]);
    }

    public function delete($id) {
        // Validar si el cliente tiene créditos o ventas asociadas antes de borrar (Opcional pero recomendado)
        $sql = "DELETE FROM customers WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':tid' => $this->tenant_id]);
    }
}
?>