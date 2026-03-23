<?php
// public/pos.php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Product.php';
require_once '../includes/ExchangeRate.php';
require_once '../includes/helpers.php';
// Simulamos clases para evitar errores en pruebas si no existen
if (!class_exists('Middleware')) { class Middleware { public static function checkAuth(){} public static function onlyAdmin(){} } }
if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();
Middleware::onlyAdmin();

$database = new Database();
$db = $database->getConnection();

$tenant_id = $_SESSION['tenant_id'] ?? 1;
$tenant_name = $_SESSION['tenant_name'] ?? 'Mi Negocio';
$pageTitle = "POS - " . $tenant_name;
$pagina_actual = 'pos';
// Gestión de Tasa BCV
$rateObj = new ExchangeRate($db);
$bcvRate = $rateObj->getSystemRate();

// --- PAGINACIÓN ---
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Query Principal
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.tenant_id = :tid 
        ORDER BY p.id DESC LIMIT :offset, :limit";
$stmt = $db->prepare($sql);
$stmt->bindValue(':tid', $tenant_id, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// KPIs
$kpiQueries = [
    'sales' => "SELECT COALESCE(SUM(total_amount_usd), 0) FROM sales WHERE tenant_id = :tid AND DATE(created_at) = CURDATE()",
    'low_stock' => "SELECT COUNT(*) FROM products WHERE tenant_id = :tid AND stock < 5",
    'inventory' => "SELECT COALESCE(SUM(price_base_usd * stock), 0) FROM products WHERE tenant_id = :tid"
];

$stmts = [];
foreach($kpiQueries as $key => $query){
    try {
        $s = $db->prepare($query);
        $s->bindValue(':tid', $tenant_id, PDO::PARAM_INT);
        $s->execute();
        $$key = $s->fetchColumn(); 
    } catch (Exception $e) {
        $$key = 0;
    }
}

$headerConfig = [
    'title'  => 'Punto de Venta',
    'icon'   => 'fas fa-cash-register',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate,
    'button' => [
        'text'   => ' Ventas de Hoy: <span class="text-success fw-bold"> $' . number_format($sales ?? 0, 2) . '</span> / <span class="text-primary fw-bold">Bs.' . number_format(($sales * $bcvRate) ?? 0, 2) . '</span>',
        'icon'   => 'fas fa-coins me-1',
       
        'class'  => 'btn btn-outline-primary mb-2 btn-sm text-start',
    ]
];