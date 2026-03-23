<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Product.php';
require_once '../includes/ExchangeRate.php';
require_once '../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
 Middleware::checkAuth();
 Middleware::onlyAdmin(); 

$database = new Database();
$db = $database->getConnection();
$tenant_id = $_SESSION['tenant_id'] ?? 1;
$tenant_name = $_SESSION['tenant_name'] ?? 'Mi Negocio';
$pageTitle = "Inventario - " . $tenant_name;
$current_page = "Inventario";
$pagina_actual = basename($_SERVER['PHP_SELF']);
$productObj = new Product($db, $tenant_id);

// Gestión de Tasa BCV 
try {
    $rateObj = new ExchangeRate($db);
    $bcvRate = $rateObj->getSystemRate();
} catch (Exception $e) {
    $bcvRate = 36.00; // Fallback
}

// --- LÓGICA DE PAGINACIÓN ---
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Obtenemos productos
try {
    $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.tenant_id = :tid 
                          ORDER BY p.id DESC");
    $stmt->bindValue(':tid', $tenant_id, PDO::PARAM_INT);
 
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conteo total
    $total_rows = $db->query("SELECT COUNT(*) FROM products WHERE tenant_id = $tenant_id")->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // KPIs
    $stmtToday = $db->prepare("SELECT SUM(total_amount_usd) as total FROM sales WHERE tenant_id = :tid AND DATE(created_at) = CURDATE()");
    $stmtToday->execute([':tid' => $tenant_id]);
    $salesToday = $stmtToday->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmtLow = $db->prepare("SELECT COUNT(*) as total FROM products WHERE tenant_id = :tid AND stock < 5");
    $stmtLow->execute([':tid' => $tenant_id]);
    $lowStockCount = $stmtLow->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmtInv = $db->prepare("SELECT SUM(price_base_usd * stock) as total FROM products WHERE tenant_id = :tid");
    $stmtInv->execute([':tid' => $tenant_id]);
    $inventoryValue = $stmtInv->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $products = []; $total_rows = 0; $total_pages = 1;
    $salesToday = 0; $lowStockCount = 0; $inventoryValue = 0;
}
// Obtenemos las categorías para llenar los <select> en los modales
try {
    $stmtCat = $db->prepare("SELECT id, name FROM categories WHERE tenant_id = :tid ORDER BY name ASC");
    $stmtCat->execute([':tid' => $tenant_id]);
    $categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

$headerConfig = [
    'title'  => 'Inventario',
    'icon'   => 'fas fa-box',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate,
    'button' => [
        'text'   => 'Nuevo Producto',
        'icon'   => 'fas fa-plus me-1',
        'target' => '#modalInsert',
        'class'  => 'btn btn-outline-primary mb-2 btn-sm text-start'
    ]
];