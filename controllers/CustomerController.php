<?php
require_once '../config/db.php';
require_once '../includes/Middleware.php';
require_once '../includes/Customer.php';
require_once '../includes/ExchangeRate.php';
require_once '../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

Middleware::checkAuth();
// Si los vendedores también pueden ver clientes, puedes omitir onlyAdmin()
// Middleware::onlyAdmin(); 

$database = new Database();
$db = $database->getConnection();
$tenant_id = $_SESSION['tenant_id'] ?? 1;
$tenant_name = $_SESSION['tenant_name'] ?? 'Mi Negocio';

$pageTitle = "Clientes - " . $tenant_name;
$current_page = "Clientes";
$pagina_actual = basename($_SERVER['PHP_SELF']);

// Obtener clientes
$customerObj = new Customer($db, $tenant_id);
$customers = $customerObj->getAll();

// Obtener Tasa BCV para el header
try {
    $rateObj = new ExchangeRate($db);
    $bcvRate = $rateObj->getSystemRate();
} catch (Exception $e) {
    $bcvRate = 36.00;
}

$headerConfig = [
    'title'  => 'Directorio de Clientes',
    'icon'   => 'fas fa-address-book',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate,
    'button' => [
        'text'   => 'Nuevo Cliente',
        'icon'   => 'fas fa-user-plus me-1',
        'target' => '#modalCustomerForm',
        'class'  => 'btn btn-outline-primary mb-2 btn-sm text-start'
    ]
];
?>