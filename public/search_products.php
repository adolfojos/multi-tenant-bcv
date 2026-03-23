<?php
// public/search_products.php
require_once '../includes/Middleware.php';
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();

$database = new Database();
$db = $database->getConnection();
$tenant_id = $_SESSION['tenant_id'] ?? 1;

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$limit = 50; 

try {
    if (empty($term)) {
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.tenant_id = :tid 
                ORDER BY p.id DESC LIMIT :limit";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':tid', $tenant_id, PDO::PARAM_INT);
    } else {
        // SOLUCIÓN: Usar identificadores únicos (:term1 y :term2)
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.tenant_id = :tid 
                AND (p.name LIKE :term1 OR p.description LIKE :term2) 
                ORDER BY p.name ASC LIMIT :limit";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':tid', $tenant_id, PDO::PARAM_INT);
        $stmt->bindValue(':term1', '%' . $term . '%', PDO::PARAM_STR);
        $stmt->bindValue(':term2', '%' . $term . '%', PDO::PARAM_STR);
    }
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $products]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    // TIP: Imprimir el $e->getMessage() te ayudará a ver el error real si falla en el futuro
    echo json_encode(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);
}