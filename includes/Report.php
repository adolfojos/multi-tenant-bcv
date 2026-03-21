<?php
class Report {
    private $conn;
    private $tenant_id;

    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    public function getDailySales() {
        $sql = "SELECT s.*, u.username 
                FROM sales s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.tenant_id = :tid AND DATE(s.created_at) = CURDATE()
                ORDER BY s.created_at ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInventoryStatus() {
        $sql = "SELECT name, stock, price_base_usd, (price_base_usd * (1 + profit_margin/100)) as p_venta 
                FROM products WHERE tenant_id = :tid ORDER BY stock ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}