<?php
class Product {
    private $conn;
    private $table = "products";
    private $tenant_id;

    // Constructor exige el tenant_id
    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    public function readAll() {
        $query = "SELECT p.*, c.name as category_name 
                  FROM " . $this->table . " p
                  LEFT JOIN categories c ON p.category_id = c.id
                  WHERE p.tenant_id = :tenant_id 
                  ORDER BY p.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':tenant_id', $this->tenant_id);
        $stmt->execute();
        return $stmt;
    }

    public function create($name, $sku, $barcode, $brand, $catId, $desc, $price, $margin, $stock) {
        $query = "INSERT INTO " . $this->table . " 
                  (name, sku, barcode, brand, category_id, description, price_base_usd, profit_margin, stock, image, tenant_id)
              VALUES (:name, :sku, :barcode, :brand, :cat, :description, :price, :margin, :stock, :image, :tenant_id)";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpieza básica
        $name = htmlspecialchars(strip_tags($name));
        $sku = htmlspecialchars(strip_tags($sku));
        $barcode = htmlspecialchars(strip_tags($barcode));
        $brand = htmlspecialchars(strip_tags($brand));
        $desc = htmlspecialchars(strip_tags($desc));

        return $stmt->execute([
            ':name' => $name,
            ':sku' => $sku,
            ':barcode' => $barcode,
            ':brand' => $brand,
            ':cat' => $catId,
            ':description' => $desc, 
            ':price' => $price,
            ':margin' => $margin,
            ':stock' => $stock,
            ':image' => "", // Default image value
            ':tenant_id' => $this->tenant_id // Aislamiento
        ]);
    }
}
?>