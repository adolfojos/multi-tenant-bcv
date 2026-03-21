<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Sale.php';
require_once '../includes/ExchangeRate.php';
require_once '../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth(); // Asegúrate de tener activa la sesión
Middleware::onlyAdmin();

$db = (new Database())->getConnection();

// Asumiendo que guardas tenant_id, tenant_name y user_id en la sesión
$tenant_id = $_SESSION['tenant_id'] ?? 1; 
$tenant_name = $_SESSION['tenant_name'] ?? 'Mi Negocio';
$user_id = $_SESSION['user_id'] ?? 1;

$saleModel = new Sale($db, $tenant_id, $user_id);

// --- LÓGICA DE FILTROS ---
$period = $_GET['period'] ?? 'day'; 
$today = date('Y-m-d');

switch ($period) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        break;
    case 'day':
    default:
        $startDate = $today;
        $endDate = $today;
        break;
}

// Simulamos la respuesta de la DB si las tablas no existen en el entorno de prueba
try {
    $stats = $saleModel->getCashFlowStats($startDate, $endDate);
    $chartData = $saleModel->getSalesChartData($startDate, $endDate);
} catch (Exception $e) {
    $stats = [];
    $chartData = [];
}

// Totales Generales para las tarjetas
$grandTotalUsd = 0;
$grandTotalBs = 0;
if (!empty($stats)) {
    foreach($stats as $s) {
        $grandTotalUsd += $s['total_usd'];
        $grandTotalBs += $s['total_bs'];
    }
}

$pageTitle = "Flujo de Caja - " . $tenant_name;
$current_page = "Flujo de Caja ";
$pagina_actual = basename($_SERVER['PHP_SELF']);

// Gestión de Tasa BCV 
try {
    $rateObj = new ExchangeRate($db);
    $bcvRate = $rateObj->getSystemRate();
} catch (Exception $e) {
    $bcvRate = 36.00; // Fallback
}

$headerConfig = [
    'title'  => 'Flujo de Caja',
    'icon'   => 'fas fa-wallet',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate,
    'button' => [
        'text'   => 'Exportar Datos',
        'icon'   => 'fas fa-download me-1',
        'target' => '#modalExport',
        'class'  => 'btn btn-outline-secondary mb-2 btn-sm text-start'
    ]
];
?>