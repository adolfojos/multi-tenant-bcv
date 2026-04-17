<?php
// Archivo: ./controllers/delete_sale.php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Sale.php';

if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();
Middleware::onlyAdmin(); // Solo administradores pueden borrar definitivamente

header('Content-Type: application/json');

// Validar que sea una petición POST y traiga el ID
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    echo json_encode(["success" => false, "message" => "Solicitud no válida."]);
    exit;
}

$db = (new Database())->getConnection();
$tenant_id = $_SESSION['tenant_id'] ?? 1;
$user_id = $_SESSION['user_id'] ?? 1;
$sale_id = (int)$_POST['id'];

$saleObj = new Sale($db, $tenant_id, $user_id);
$result = $saleObj->deleteSale($sale_id);

if ($result['status'] === 'success') {
    echo json_encode(["success" => true, "message" => $result['message']]);
} else {
    echo json_encode(["success" => false, "message" => $result['message']]);
}