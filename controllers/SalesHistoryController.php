<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Sale.php';
require_once '../includes/ExchangeRate.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();
Middleware::onlyAdmin();

$db = (new Database())->getConnection();
$tenant_id = $_SESSION['tenant_id'] ?? 1;
$tenant_name = $_SESSION['tenant_name'] ?? 'Mi Negocio';
$user_id = $_SESSION['user_id'] ?? 1;
$current_page = "Historial de Ventas";
$saleObj = new Sale($db, $tenant_id, $user_id);
$filter = $_GET['filter'] ?? 'today'; 

try {
    $sales = $saleObj->getHistory($filter);
} catch (Exception $e) {
    $sales = [];
}

// Variables para las tarjetas de métricas
$totalDiaUsd = 0;
$totalDiaBs = 0;
$ticketsEfectivo = 0;
$ticketsPunto = 0;
$ticketsPMovil = 0;
// Gestión de Tasa BCV 
try {
    $rateObj = new ExchangeRate($db);
    $bcvRate = $rateObj->getSystemRate();
} catch (Exception $e) {
    $bcvRate = 36.00; // Fallback
}
foreach($sales as $s) {
    // Sumatorias
    $totalDiaUsd += $s['total_amount_usd'] ?? 0;
    $totalDiaBs += $s['total_amount_bs'] ?? 0;

    // Conteo por método de pago (Ajusta los strings según tu BD)
    $method = strtolower($s['payment_method'] ?? '');
    
    if (strpos($method, 'efectivo') !== false || $method === 'cash') {
        $ticketsEfectivo++;
    } elseif (strpos($method, 'punto') !== false || strpos($method, 'tarjeta') !== false) {
        $ticketsPunto++;
    } elseif (strpos($method, 'movil') !== false || strpos($method, 'transferencia') !== false) {
        $ticketsPMovil++;
    }
}

$headerConfig = [
    'title'  => 'Historial de Ventas',
    'icon'   => 'fas fa-history',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate,
    
    ];