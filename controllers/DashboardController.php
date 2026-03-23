<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/ExchangeRate.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();
Middleware::onlyAdmin();

$db = (new Database())->getConnection();

// Datos de sesión con valores por defecto para pruebas
$tenant_id = $_SESSION['tenant_id'] ?? 1;
$tenant_name = $_SESSION['tenant_name'] ?? 'Mi Negocio';
$user_id = $_SESSION['user_id'] ?? 1;
$user_name = $_SESSION['username'] ?? 'Vendedor';
$pagina_actual = basename($_SERVER['PHP_SELF']);
// Gestión de Tasa BCV
try {
    $rateObj = new ExchangeRate($db);
    $bcvRate = $rateObj->getSystemRate();
} catch (Exception $e) {
    $bcvRate = 36.00;
}

// --- ESTADÍSTICAS PERSONALES DEL VENDEDOR (SOLO HOY) ---
try {
    // Cuánto ha vendido este usuario específico hoy
    $sqlMySales = "SELECT SUM(total_amount_usd) as total FROM sales 
                   WHERE user_id = :uid AND DATE(created_at) = CURDATE()";
    $stmt = $db->prepare($sqlMySales);
    $stmt->execute([':uid' => $user_id]);
    $mySalesToday = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Conteo de sus facturas hoy
    $sqlCount = "SELECT COUNT(*) as total FROM sales 
                 WHERE user_id = :uid AND DATE(created_at) = CURDATE()";
    $stmtCount = $db->prepare($sqlCount);
    $stmtCount->execute([':uid' => $user_id]);
    $myInvoices = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $mySalesToday = 0;
    $myInvoices = 0;
}


// --- DATOS PARA EL GRÁFICO (ÚLTIMOS 7 DÍAS) ---
$chartDates = [];
$chartSales = [];

try {
    // Obtenemos las ventas agrupadas por fecha de los últimos 7 días
    $sqlChart = "SELECT DATE(created_at) as sale_date, SUM(total_amount_usd) as total 
                 FROM sales 
                 WHERE user_id = :uid 
                 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY DATE(created_at) ASC";
    
    $stmtChart = $db->prepare($sqlChart);
    $stmtChart->execute([':uid' => $user_id]);
    $chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);

    // Creamos un arreglo con los últimos 7 días exactos para asegurar continuidad
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chartDates[] = $date;
        
        $dailyTotal = 0;
        foreach($chartData as $row) {
            if ($row['sale_date'] === $date) {
                $dailyTotal = $row['total'];
                break;
            }
        }
        $chartSales[] = (float)$dailyTotal;
    }
} catch (Exception $e) {
    // Valores por defecto si falla la base de datos
    $chartDates = [date('Y-m-d')];
    $chartSales = [0];
}

// Convertimos a JSON para que Javascript pueda leerlos fácilmente
$jsChartDates = json_encode($chartDates);
$jsChartSales = json_encode($chartSales);

// --- DATOS PARA LOS SPARKLINES (ÚLTIMOS 10 DÍAS) ---
$sparklineSalesUSD = [];
$sparklineInvoices = [];
$sparklineAvgTicket = [];

try {
    // Consultamos totales, conteo y promedio de los últimos 10 días
    $sqlSpark = "SELECT DATE(created_at) as sale_date, 
                        SUM(total_amount_usd) as total_usd,
                        COUNT(id) as total_invoices,
                        AVG(total_amount_usd) as avg_ticket
                 FROM sales 
                 WHERE user_id = :uid 
                 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 9 DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY DATE(created_at) ASC";
    
    $stmtSpark = $db->prepare($sqlSpark);
    $stmtSpark->execute([':uid' => $user_id]);
    $sparkData = $stmtSpark->fetchAll(PDO::FETCH_ASSOC);

    // Rellenamos los 10 días para asegurar que el gráfico no tenga huecos
    for ($i = 9; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        
        $d_usd = 0;
        $d_inv = 0;
        $d_avg = 0;
        
        foreach($sparkData as $row) {
            if ($row['sale_date'] === $date) {
                $d_usd = $row['total_usd'];
                $d_inv = $row['total_invoices'];
                $d_avg = $row['avg_ticket'];
                break;
            }
        }
        $sparklineSalesUSD[] = (float)$d_usd;
        $sparklineInvoices[] = (int)$d_inv;
        $sparklineAvgTicket[] = (float)$d_avg;
    }
} catch (Exception $e) {
    // Valores por defecto si falla
    $sparklineSalesUSD = array_fill(0, 10, 0);
    $sparklineInvoices = array_fill(0, 10, 0);
    $sparklineAvgTicket = array_fill(0, 10, 0);
}

// Convertimos a JSON para el Javascript
$jsSparkSales = json_encode($sparklineSalesUSD);
$jsSparkInvoices = json_encode($sparklineInvoices);
$jsSparkAvg = json_encode($sparklineAvgTicket);

// Variables de apoyo para el layout
$pageTitle = "Dashboard - " . ($tenant_name ?? 'Mi Negocio');
$current_page = "dashboard";
$date= date('d M Y');
// Asegurarnos de que las variables existan para evitar warnings si el controlador falla
$mySalesToday = $mySalesToday ?? 0;
$myInvoices = $myInvoices ?? 0;
$bcvRate = $bcvRate ?? 36.50; // Fallback
$jsChartSales = $jsChartSales ?? '[]';
$jsChartDates = $jsChartDates ?? '[]';

$headerConfig = [
    'title'  => 'Resumen del Día',
    'icon'   => 'fas fa-chart-pie',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate,
    'button' => [
        'text'   => $date,
        'icon'   => 'fas fa-coins me-1',
       
        'class'  => 'btn btn-outline-info mb-2 btn-sm text-start',
    ]
];
?>