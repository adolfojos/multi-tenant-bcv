<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Credit.php';
require_once '../includes/ExchangeRate.php';
require_once '../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();
// Si quieres que los vendedores también registren abonos, quita el onlyAdmin()
// Middleware::onlyAdmin(); 

$database = new Database();
$db = $database->getConnection();

$tenant_id = $_SESSION['tenant_id'] ?? 1;
$tenant_name = $_SESSION['tenant_name'] ?? 'Mi Negocio';
$current_page = "Cuentas por Cobrar";
$pageTitle = "Créditos - " . $tenant_name;
$pagina_actual = basename($_SERVER['PHP_SELF']);

// Tasa BCV
try {
    $rateObj = new ExchangeRate($db);
    $bcvRate = $rateObj->getSystemRate();
} catch (Exception $e) {
    $bcvRate = 36.00;
}

// Obtener datos
$creditObj = new Credit($db, $tenant_id);
$credits = $creditObj->getPending();

$total_deuda_usd = 0;
foreach($credits as $c) {
    if($c['status'] == 'pending') {
        $total_deuda_usd += $c['balance_usd'];
    }
}

$headerConfig = [
    'title'  => 'Cuentas por Cobrar',
    'colorico'  => 'danger',
    'icon'   => 'bi bi-calendar-check',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate
];
?>