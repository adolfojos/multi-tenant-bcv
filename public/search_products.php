<?php
// search_products.php
require_once '../includes/Middleware.php';
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();

$database = new Database();
$db = $database->getConnection();
$tenant_id = $_SESSION['tenant_id'] ?? 1;

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$limit = 50; // Límite de resultados para mantener la interfaz rápida

try {
    if (empty($term)) {
        // Si el buscador está vacío, traemos los últimos productos (comportamiento normal)
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.tenant_id = :tid 
                ORDER BY p.id DESC LIMIT :limit";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':tid', $tenant_id, PDO::PARAM_INT);
    } else {
        // Búsqueda real por nombre o descripción
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.tenant_id = :tid 
                AND (p.name LIKE :term OR p.description LIKE :term) 
                ORDER BY p.name ASC LIMIT :limit";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':tid', $tenant_id, PDO::PARAM_INT);
        $stmt->bindValue(':term', '%' . $term . '%', PDO::PARAM_STR);
    }
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $products]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error al buscar productos']);
}