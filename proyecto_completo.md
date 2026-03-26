## Archivo: ./config/db.php
```php
<?php
// config/db.php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Detectar si estamos en localhost
        if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
            // CREDENCIALES LOCALES
            $this->host     = "localhost";
            $this->db_name  = "mtb_db";
            $this->username = "root";
            $this->password = "";
        } else {
            // CREDENCIALES DE PRODUCCIÓN (Cambia estos datos por los de tu hosting)
            $this->host     = "sql312.ezyro.com"; 
            $this->db_name  = "ezyro_41444378_mtb_db";
            $this->username = "ezyro_41444378";
            $this->password = "9cde61e98ccf4bc";
        }
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            // Opciones recomendadas para PDO
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            // En producción, es mejor no mostrar el mensaje de error real al usuario por seguridad
            error_log("Error de conexión: " . $exception->getMessage());
            die("Lo sentimos, hubo un problema con la conexión a la base de datos.");
        }
        return $this->conn;
    }
}
?>```

## Archivo: ./controllers/AdminController.php
```php
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
];```

## Archivo: ./controllers/AuthController.php
```php
<?php
require_once '../config/db.php';
require_once '../includes/Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? ''; 

    $result = $auth->login($username, $password);

    if ($result === "OK") {
        if ($role === 'admin') {
            header("Location: admin.php"); // Redirigimos al nuevo admin estilizado
        } else {
            header("Location: dashboard.php");
        }
        exit;
    } elseif ($result === "SUSPENDED") {
        $error = "🚫 Cuenta suspendida. Contacte al proveedor.";
    } elseif ($result === "EXPIRED") {
        $error = "⚠️ Su licencia ha caducado. Por favor renueve.";
    } else {
        $error = "Credenciales incorrectas.";
    }
}
// Variables de apoyo para el layout

?>```

## Archivo: ./controllers/cancel_sale.php
```php
<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Sale.php';

if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();
Middleware::onlyAdmin(); // Solo administradores pueden anular

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
$result = $saleObj->cancelSale($sale_id);

if ($result['status'] === 'success') {
    echo json_encode(["success" => true, "message" => $result['message']]);
} else {
    echo json_encode(["success" => false, "message" => $result['message']]);
}```

## Archivo: ./controllers/CategoryController.php
```php
<?php
// 1. Cargas de archivos (Asegúrate de que las rutas sean correctas)
require_once '../config/db.php';
require_once '../includes/Category.php';
// Importante: Asegúrate de cargar la clase Middleware si no tiene autoload
require_once '../includes/Middleware.php'; 
require_once '../includes/ExchangeRate.php';
require_once '../includes/helpers.php';

// 2. Manejo de Sesión y Seguridad
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validación de acceso
Middleware::checkAuth();
Middleware::onlyAdmin();

// 3. Conexión y Contexto
try {
    $database = new Database();
    $db = $database->getConnection();

    // Validamos que existan los datos de sesión para evitar errores de índice
    $tenant_id   = $_SESSION['tenant_id'] ?? 1;
    $tenant_name = $_SESSION['tenant_name'] ?? 'Mi Negocio';

    // 4. Lógica de Negocio
    $catObj = new Category($db, $tenant_id);
    $categories = $catObj->getAll();

    // 5. Variables de Vista
    $pageTitle     = "Categorías - " . htmlspecialchars($tenant_name);
    $current_page  = "Categorías";
    $pagina_actual = basename($_SERVER['PHP_SELF']);

} catch (Exception $e) {
    // Manejo de errores básico
    die("Error en el controlador: " . $e->getMessage());
}
// Gestión de Tasa BCV 
try {
    $rateObj = new ExchangeRate($db);
    $bcvRate = $rateObj->getSystemRate();
} catch (Exception $e) {
    $bcvRate = 36.00; // Fallback
}

$headerConfig = [
    'title'  => 'Categorías',
    'icon'   => 'fas fa-tags',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate,
    'button' => [
        'text'   => 'Nueva Categoría',
        'icon'   => 'fas fa-plus me-1',
        'target' => '#modalInsert',
        'class'  => 'btn btn-outline-primary mb-2 btn-sm text-start'
    ]
];
?>```

## Archivo: ./controllers/ConfigController.php
```php
<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/ExchangeRate.php';
require_once '../includes/helpers.php';
Middleware::checkAuth();
Middleware::onlyAdmin();
$database = new Database();
$db = $database->getConnection();

$tenant_id = $_SESSION['tenant_id']; // Asegúrate de tener el ID del tenant en sesión

// 1. Obtener datos del negocio
$stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt->execute([$tenant_id]);
$tenant_data = $stmt->fetch(PDO::FETCH_ASSOC);

$tenant_name = $tenant_data['business_name'] ?? 'Mi Negocio';

// 2. Obtener la tasa BCV real de system_settings
$bcvStmt = $db->query("SELECT bcv_rate FROM system_settings LIMIT 1");
$bcvRow = $bcvStmt->fetch(PDO::FETCH_ASSOC);
$bcvRate = $bcvRow ? $bcvRow['bcv_rate'] : 0.00; // Extraído de la BD

$pageTitle = "Configuración - " . $tenant_name;
$current_page = "Configuración";

// 3. Configurar el header (Añadí form="formConfig" al botón para que envíe el formulario)
$headerConfig = [
    'title'  => 'Configuración',
    'icon'   => 'fas fa-cogs',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate,
    'button' => [
        'text'   => 'Guardar Cambios',
        'icon'   => 'fas fa-save me-1',
        'class'  => 'btn btn-outline-success mb-2 btn-sm text-start',
        // Esto es lo que activará el comportamiento de envío de formulario:
        'attributes' => 'type="submit" form="formConfig"'
    ]
];
?>```

## Archivo: ./controllers/DashboardController.php
```php
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
?>```

## Archivo: ./controllers/get_sale_details.php
```php
<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Sale.php';

// Verificación de sesión y seguridad
if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();

$db = (new Database())->getConnection();
$tenant_id = $_SESSION['tenant_id'] ?? 1;
$user_id = $_SESSION['user_id'] ?? 1;

// Capturar y validar el ID de la venta
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sale_id <= 0) {
    echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>ID de venta no válido.</div>';
    exit;
}

$saleObj = new Sale($db, $tenant_id, $user_id);

try {
    // Necesitamos que tu clase Sale tenga estos métodos (te dejo el ejemplo abajo)
    $details = $saleObj->getSaleItems($sale_id); // Tu método original
    $saleHeader = $saleObj->getSaleHeader($sale_id); // Tu método original
} catch (Exception $e) {
    echo '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Error al consultar los detalles de la base de datos.</div>';
    exit;
}

if (empty($details)) {
    echo '<div class="alert alert-info text-center py-4"><i class="fas fa-box-open fa-2x mb-2 d-block opacity-50"></i>No se encontraron productos registrados para esta venta.</div>';
    exit;
}
?>

<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped align-middle text-center mb-0">
        <thead class="table-light">
            <tr>
                <th class="text-start">Producto</th>
                <th>Cant.</th>
                <th>Precio Unit. (USD)</th>
                <th>Subtotal (USD)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $d): ?>
            <tr>
                <td class="text-start fw-medium"><?= htmlspecialchars($d['product_name'] ?? 'Producto Desconocido') ?></td>
                <td><span class="badge text-bg-secondary rounded-pill"><?= $d['quantity'] ?></span></td>

                <td>$ <?= number_format($d['price_at_moment_usd'], 2) ?></td>
    <td class="fw-bold">$ <?= number_format($d['price_at_moment_usd'] * $d['quantity'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <?php if (!empty($saleHeader)): ?>
        <tfoot class="table-light">
            <tr>
                <td colspan="3" class="text-end fw-bold">Total Pagado:</td>
                <td class="fw-bold text-success fs-5">$ <?= number_format($saleHeader['total_amount_usd'] ?? 0, 2) ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>

<div class="d-flex justify-content-between align-items-center mt-3 border-top pt-3">
    <small class="text-muted">
        <i class="fas fa-user-tag me-1"></i> Cajero: <?= htmlspecialchars($saleHeader['username'] ?? 'N/A') ?>
    </small>
    <button class="btn btn-sm btn-outline-primary" onclick="printTicket(<?= $sale_id ?>)">
        <i class="fas fa-print me-1"></i> Imprimir Recibo
    </button>
</div>```

## Archivo: ./controllers/PosController.php
```php
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
];```

## Archivo: ./controllers/SaleController.php
```php
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
?>```

## Archivo: ./controllers/SalesHistoryController.php
```php
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
    
    ];```

## Archivo: ./controllers/UserController.php
```php
<?php
require_once '../config/db.php';
require_once '../includes/Middleware.php';
require_once '../includes/ExchangeRate.php';

require_once '../includes/User.php';
require_once '../includes/helpers.php';

// Seguridad: Verificar sesión y permisos
Middleware::checkAuth();
Middleware::onlyAdmin();

// Inicialización de Base de Datos y Modelo
$db = (new Database())->getConnection();
$userObj = new User($db, $_SESSION['tenant_id'] ?? null); // Evitar error si tenant_id no existe
$users = $userObj->getAll() ?: []; // Si devuelve false o null, inicializa como array vacío

// Variables para el layout
$tenant_name = $_SESSION['tenant_name'] ?? 'Mi Negocio';
$pageTitle = "Usuarios - " . $tenant_name;
$current_page = "Usuarios";
$pagina_actual = basename($_SERVER['PHP_SELF']);

// Conteo de usuarios
$totalUsers = count($users);

// Gestión de Tasa BCV 
try {
    $rateObj = new ExchangeRate($db);
    $bcvRate = $rateObj->getSystemRate();
} catch (Exception $e) {
    $bcvRate = 36.00; // Fallback
}
// Contar administradores de forma eficiente
$adminCount = count(array_filter($users, function($u) {
    return ($u['role'] ?? '') === 'admin';
}));

$headerConfig = [
    'title'  => 'Gestión de Usuarios',
    'icon'   => 'fas fa-users-cog',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate,
    'button' => [
        'text'   => 'Nuevo Usuario',
        'icon'   => 'fas fa-user-plus me-1',
        'target' => '#modalUser',
        'class'  => 'btn btn-outline-success mb-2 btn-sm text-start'
    ]
];
?>
```

## Archivo: ./includes/Auth.php
```php
<?php
class Auth {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        // JOIN crítico: Trae datos del usuario Y de su tienda
        $query = "SELECT 
                    u.id, u.username, u.password, u.tenant_id, u.role, 
                    t.status AS tenant_status, 
                    t.expiration_date,
                    t.business_name
                  FROM " . $this->table . " u
                  JOIN tenants t ON u.tenant_id = t.id
                  WHERE u.username = :username 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($password, $row['password'])) {
                
                // VALIDACIONES DE NEGOCIO
                if ($row['tenant_status'] !== 'active') {
                    return "SUSPENDED";
                }

                $today = date('Y-m-d');
                if ($row['expiration_date'] < $today) {
                    return "EXPIRED";
                }

                // INICIO DE SESIÓN EXITOSO
                if (session_status() === PHP_SESSION_NONE) session_start();
                
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['tenant_id'] = $row['tenant_id']; // ID CRÍTICO
                $_SESSION['tenant_name'] = $row['business_name'];
                $_SESSION['expiration_date'] = $row['expiration_date'];
                $_SESSION['role'] = $row['role']; // 'admin' o 'seller'

                return "OK";
            }
        }
        return "INVALID";
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header("Location: login.php");
    }
    
}
?>```

## Archivo: ./includes/Category.php
```php
<?php
class Category {
    private $conn;
    private $tenant_id;

    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    public function getAll() {
        $sql = "SELECT * FROM categories WHERE tenant_id = :tid ORDER BY name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($name) {
        $sql = "INSERT INTO categories (tenant_id, name) VALUES (:tid, :n)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':tid' => $this->tenant_id, ':n' => $name]);
    }

    public function update($id, $name) {
        $sql = "UPDATE categories SET name = :n WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':n' => $name, ':id' => $id, ':tid' => $this->tenant_id]);
    }

    public function delete($id) {
        // OJO: Podrías validar primero si hay productos usando esta categoría
        $sql = "DELETE FROM categories WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':tid' => $this->tenant_id]);
    }
}```

## Archivo: ./includes/ExchangeRate.php
```php
<?php
// include/ExchangeRate.php

class ExchangeRate {
    private $conn;
    private $table = "system_settings"; // Usamos tu tabla original

    public function __construct($db) {
        $this->conn = $db;
    }

    // --- Lógica Principal ---

    public function getRate() {
        // 1. Consultar la tasa y la última actualización en la misma consulta
        // Asumimos que la configuración principal está siempre en id = 1
        $query = "SELECT bcv_rate, last_update FROM " . $this->table . " WHERE id = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Valores por defecto si la tabla está vacía
        $currentRate = ($row) ? (float)$row['bcv_rate'] : 0.00;
        $lastUpdate  = ($row) ? $row['last_update'] : null;

        // 2. Calcular cuánto tiempo ha pasado (en horas)
        // Si no hay fecha (null), ponemos 999 para forzar la actualización
        $hoursDiff = $lastUpdate ? (time() - strtotime($lastUpdate)) / 3600 : 999;

        // 3. Decidir si actualizamos:
        // Si la tasa es 0 (error previo o inicio) O si pasó más de 1 hora
        if ($currentRate <= 0 || $hoursDiff > 1) {
            
            $newRate = $this->fetchFromBCV();

            // Solo actualizamos la BD si el BCV nos dio un número válido
            if ($newRate > 0) {
                $this->updateRate($newRate);
                return $newRate;
            }
        }

        // Si no se actualizó (BCV caído o caché aún válida), devolvemos lo que hay en BD
        // Si es 0, devolvemos 1.00 para evitar división por cero en tu sistema
        return ($currentRate > 0) ? $currentRate : 1.00;
    }

    // Actualiza la tasa y la hora en tu tabla existente
    public function updateRate($rate) {
        // Usamos NOW() o date de PHP. Usaré date de PHP para consistencia.
        $now = date('Y-m-d H:i:s');

        // Primero intentamos actualizar el registro existente
        $query = "UPDATE " . $this->table . " SET bcv_rate = :rate, last_update = :last_update WHERE id = 1";
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([':rate' => $rate, ':last_update' => $now]);

        // Si rowCount es 0, podría ser que no existe el ID 1. Lo insertamos por seguridad.
        if ($stmt->rowCount() == 0) {
            // Verificamos si realmente no existe (rowCount puede ser 0 si el valor es idéntico)
            $check = $this->conn->query("SELECT count(*) FROM " . $this->table . " WHERE id = 1")->fetchColumn();
            if ($check == 0) {
                 $insertQuery = "INSERT INTO " . $this->table . " (id, bcv_rate, last_update) VALUES (1, :rate, :last_update)";
                 $insertStmt = $this->conn->prepare($insertQuery);
                 return $insertStmt->execute([':rate' => $rate, ':last_update' => $now]);
            }
        }
        
        return $result;
    }

    // --- Scraping del BCV (Sin cambios, funciona igual) ---
    private function fetchFromBCV() {
        $url = 'https://www.bcv.org.ve/';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Vital para BCV
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // Vital para BCV
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); 

        $html = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$html || !empty($error)) {
            return 0;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//div[@id="dolar"]//strong');

        if ($nodes->length > 0) {
            $rateText = trim($nodes->item(0)->nodeValue);
            $rateText = str_replace(',', '.', $rateText);
            $rateText = preg_replace('/[^0-9.]/', '', $rateText);
            return (float) $rateText;
        }

        return 0;
    }

    // --- Compatibilidad con código legado ---
    public function getSystemRate() {
        return $this->getRate();
    }
}
?>```

## Archivo: ./includes/helpers.php
```php
<?php
function render_content_header($config)
{
    // Valores por defecto para evitar errores
    $title       = $config['title'] ?? 'Panel';
    $icon        = $config['icon'] ?? 'fas fa-home';
    $tenant      = $config['tenant'] ?? 'Sistema POS';
    $bcv         = $config['bcv'] ?? 0;

    // Configuración del botón (opcional)
    $button      = $config['button'] ?? null;

    ob_start(); // Iniciamos buffer para devolver el HTML como string
?>
    <div class="app-content-header py-3 border-bottom mb-4">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="<?= htmlspecialchars($icon) ?> text-primary me-2"></i>
                        <?= htmlspecialchars($title) ?>
                    </h3>
                    <small class="text-secondary"><?= htmlspecialchars($tenant) ?></small>
                </div>

                <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">

                    <span class="btn btn-outline-secondary mb-2 btn-sm text-start">
                        <i class="fas fa-coins me-1"></i> BCV: Bs. <?= number_format($bcv, 2) ?>
                    </span>
                    <?php if ($button): ?>
                        <button
                            class="<?= $button['class'] ?? 'btn btn-primary' ?>"

                            <?php if (isset($button['attributes'])): ?>
                            <?= $button['attributes'] ?>
                            <?php else: ?>
                            onclick="openModal()"
                            data-bs-toggle="modal"
                            data-bs-target="<?= $button['target'] ?? '#modalDefault' ?>"
                            <?php endif; ?>>
                            <i class="<?= $button['icon'] ?? 'fas fa-plus' ?> me-1"></i>
                            <?= $button['text'] ?? 'Agregar' ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php
    return ob_get_clean();
}
```

## Archivo: ./includes/License.php
```php
<?php
class License {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Registrar una nueva tienda (Tenant)
    public function registerTenant($name, $rif, $months) {
        $expiration = date('Y-m-d', strtotime("+$months months"));
        $license_key = bin2hex(random_bytes(8)); // Genera un código aleatorio

        $query = "INSERT INTO tenants (business_name, rif, license_key, expiration_date) 
                  VALUES (:name, :rif, :key, :exp)";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':name' => $name,
            ':rif' => $rif,
            ':key' => strtoupper($license_key),
            ':exp' => $expiration
        ]);
    }

    // Renovar licencia
    public function renew($tenant_id, $months) {
        $query = "UPDATE tenants 
                  SET expiration_date = DATE_ADD(expiration_date, INTERVAL :months MONTH),
                      status = 'active'
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':months' => $months, ':id' => $tenant_id]);
    }
}```

## Archivo: ./includes/Middleware.php
```php
<?php
class Middleware {
    public static function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Verificar si hay sesión de usuario y de tienda
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
            header("Location: login.php");
            exit();
        }

        // 2. Verificar vencimiento de licencia (Opcional: Capa extra de seguridad)
        if (isset($_SESSION['expiration_date'])) {
            $today = date('Y-m-d');
            if ($_SESSION['expiration_date'] < $today) {
                session_destroy();
                header("Location: login.php?error=expired_session");
                exit();
            }
        }
    }
       // Nueva función para restringir páginas solo a Administradores
    public static function onlyAdmin() {
        self::checkAuth();
        if ($_SESSION['role'] !== 'admin') {
            // Si es vendedor y trata de entrar a Admin, lo mandamos al POS
            header("Location: pos.php?error=unauthorized");
            exit;
        }
    }
}
?>```

## Archivo: ./includes/Product.php
```php
<?php
class Product {
    private $conn;
    private $table = "products";
    private $tenant_id;

    // Constructor exige el tenant_id
    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    public function readAll() {
        $query = "SELECT p.*, c.name as category_name 
                  FROM " . $this->table . " p
                  LEFT JOIN categories c ON p.category_id = c.id
                  WHERE p.tenant_id = :tenant_id 
                  ORDER BY p.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':tenant_id', $this->tenant_id);
        $stmt->execute();
        return $stmt;
    }

    public function create($name, $sku, $barcode, $brand, $catId, $desc, $price, $margin, $stock) {
        $query = "INSERT INTO " . $this->table . " 
                  (name, sku, barcode, brand, category_id, description, price_base_usd, profit_margin, stock, image, tenant_id)
              VALUES (:name, :sku, :barcode, :brand, :cat, :description, :price, :margin, :stock, :image, :tenant_id)";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpieza básica
        $name = htmlspecialchars(strip_tags($name));
        $sku = htmlspecialchars(strip_tags($sku));
        $barcode = htmlspecialchars(strip_tags($barcode));
        $brand = htmlspecialchars(strip_tags($brand));
        $desc = htmlspecialchars(strip_tags($desc));

        return $stmt->execute([
            ':name' => $name,
            ':sku' => $sku,
            ':barcode' => $barcode,
            ':brand' => $brand,
            ':cat' => $catId,
            ':description' => $desc, 
            ':price' => $price,
            ':margin' => $margin,
            ':stock' => $stock,
            ':image' => "", // Default image value
            ':tenant_id' => $this->tenant_id // Aislamiento
        ]);
    }
}
?>```

## Archivo: ./includes/Report.php
```php
<?php
class Report {
    private $conn;
    private $tenant_id;

    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    public function getDailySales() {
        $sql = "SELECT s.*, u.username 
                FROM sales s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.tenant_id = :tid AND DATE(s.created_at) = CURDATE()
                ORDER BY s.created_at ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInventoryStatus() {
        $sql = "SELECT name, stock, price_base_usd, (price_base_usd * (1 + profit_margin/100)) as p_venta 
                FROM products WHERE tenant_id = :tid ORDER BY stock ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}```

## Archivo: ./includes/Sale.php
```php
<?php
class Sale {
    private $conn;
    private $tenant_id;
    private $user_id;

    public function __construct($db, $tenant_id, $user_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
        $this->user_id = $user_id;
    }

    /**
     * Procesa la venta: Valida stock, calcula totales y registra en BD
     */
    public function createSale($cartItems, $payment_method, $current_exchange_rate) {
        try {
            $this->conn->beginTransaction();

            $total_usd = 0;
            
            foreach ($cartItems as $item) {
                $sqlProd = "SELECT price_base_usd, profit_margin, stock 
                            FROM products 
                            WHERE id = :id AND tenant_id = :tenant_id 
                            FOR UPDATE"; 
                
                $stmt = $this->conn->prepare($sqlProd);
                $stmt->execute([':id' => $item['id'], ':tenant_id' => $this->tenant_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new Exception("Producto ID {$item['id']} no encontrado o no pertenece a su tienda.");
                }
                if ($product['stock'] < $item['qty']) {
                    throw new Exception("Stock insuficiente para el producto ID {$item['id']}.");
                }

                $unit_price = $product['price_base_usd'] * (1 + ($product['profit_margin'] / 100));
                $total_usd += ($unit_price * $item['qty']);
            }

            // Redondeamos el total USD a 2 decimales para limpiar micro-decimales de los márgenes
            $total_usd = round($total_usd, 2); 

            // Calculamos el total en Bs y lo redondeamos también a 2 decimales
            $total_bs = round($total_usd * $current_exchange_rate, 2);

            $sqlHead = "INSERT INTO sales 
                        (tenant_id, user_id, total_amount_usd, total_amount_bs, exchange_rate, payment_method, created_at) 
                        VALUES (:tid, :uid, :tusd, :tbs, :rate, :method, NOW())";
            
            $stmtHead = $this->conn->prepare($sqlHead);
            $stmtHead->execute([
                ':tid' => $this->tenant_id,
                ':uid' => $this->user_id,
                ':tusd' => $total_usd,
                ':tbs' => $total_bs,
                ':rate' => $current_exchange_rate,
                ':method' => $payment_method
            ]);
            
            $sale_id = $this->conn->lastInsertId();

            $sqlDetail = "INSERT INTO sale_items (sale_id, product_id, quantity, price_at_moment_usd) VALUES (?, ?, ?, ?)";
            $sqlStock  = "UPDATE products SET stock = stock - ? WHERE id = ? AND tenant_id = ?";

            foreach ($cartItems as $item) {
                $stmtP = $this->conn->prepare("SELECT price_base_usd, profit_margin FROM products WHERE id = ?");
                $stmtP->execute([$item['id']]);
                $p = $stmtP->fetch(PDO::FETCH_ASSOC);
                $finalPrice = $p['price_base_usd'] * (1 + ($p['profit_margin'] / 100));

                $stmtD = $this->conn->prepare($sqlDetail);
                $stmtD->execute([$sale_id, $item['id'], $item['qty'], $finalPrice]);

                $stmtS = $this->conn->prepare($sqlStock);
                $stmtS->execute([$item['qty'], $item['id'], $this->tenant_id]);
            }

            $this->conn->commit();

            return [
                "status" => "success", 
                "message" => "Venta registrada exitosamente", 
                "sale_id" => $sale_id,
                "total_usd" => $total_usd
            ];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // --- MÉTODOS DE LECTURA (REPORTES) ---

    public function getHistory($filter = 'today') {
        $sql = "SELECT s.*, u.username 
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.tenant_id = :tid";

        // Filtros actualizados según la nueva interfaz
        if ($filter == 'today') {
            $sql .= " AND DATE(s.created_at) = CURDATE()";
        } elseif ($filter == '7days') {
            $sql .= " AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        } elseif ($filter == '30days') {
            $sql .= " AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($filter == 'month') {
            $sql .= " AND MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())";
        }
        // Si es 'all', no se aplica ningún filtro de fecha.

        $sql .= " ORDER BY s.id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSaleHeader($sale_id) {
        $sql = "SELECT s.*, t.business_name, t.rif,t.ticket_footer, u.username 
                FROM sales s
                JOIN tenants t ON s.tenant_id = t.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.id = :id AND s.tenant_id = :tid"; 
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $sale_id, ':tid' => $this->tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSaleItems($sale_id) {
        $sql = "SELECT si.*, p.name as product_name 
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                LEFT JOIN products p ON si.product_id = p.id
                WHERE si.sale_id = :id AND s.tenant_id = :tid";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $sale_id, ':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCashFlowStats($startDate, $endDate) {
        $sql = "SELECT 
                    payment_method, 
                    SUM(total_amount_usd) as total_usd, 
                    SUM(total_amount_bs) as total_bs,
                    COUNT(id) as total_transactions
                FROM sales 
                WHERE tenant_id = :tid 
                AND created_at BETWEEN :start AND :end 
                GROUP BY payment_method";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':tid' => $this->tenant_id,
            ':start' => $startDate . " 00:00:00",
            ':end' => $endDate . " 23:59:59"
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSalesChartData($startDate, $endDate) {
        $sql = "SELECT DATE(created_at) as sale_date, SUM(total_amount_usd) as total 
                FROM sales 
                WHERE tenant_id = :tid 
                AND created_at BETWEEN :start AND :end 
                GROUP BY DATE(created_at) 
                ORDER BY sale_date ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':tid' => $this->tenant_id,
            ':start' => $startDate . " 00:00:00",
            ':end' => $endDate . " 23:59:59"
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Anula una venta, devuelve el stock a los productos y actualiza el estado.
     */
    public function cancelSale($sale_id) {
        try {
            $this->conn->beginTransaction();

            // 1. Verificar existencia y estado actual de la venta
            $stmt = $this->conn->prepare("SELECT status FROM sales WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $stmt->execute([$sale_id, $this->tenant_id]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) throw new Exception("Venta no encontrada.");
            if ($sale['status'] === 'anulada') throw new Exception("Esta venta ya fue anulada anteriormente.");

            // 2. Obtener los productos y cantidades de la venta usando tu método existente
            $items = $this->getSaleItems($sale_id);

            // 3. Devolver el stock a la tabla products
            $sqlStock = "UPDATE products SET stock = stock + ? WHERE id = ? AND tenant_id = ?";
            $stmtStock = $this->conn->prepare($sqlStock);

            foreach ($items as $item) {
                $stmtStock->execute([$item['quantity'], $item['product_id'], $this->tenant_id]);
            }

            // 4. Cambiar el estado de la venta
            $stmtUpdate = $this->conn->prepare("UPDATE sales SET status = 'anulada' WHERE id = ? AND tenant_id = ?");
            $stmtUpdate->execute([$sale_id, $this->tenant_id]);

            $this->conn->commit();
            return ["status" => "success", "message" => "Venta anulada y stock restaurado correctamente."];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}
?>```

## Archivo: ./includes/User.php
```php
<?php
class User {
    private $conn;
    private $tenant_id;

    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    public function getAll() {
        $sql = "SELECT id, username, role, created_at FROM users WHERE tenant_id = :tid ORDER BY role ASC, username ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($username, $password, $role) {
        // Verificar duplicados
        $sqlCheck = "SELECT COUNT(*) FROM users WHERE username = :u AND tenant_id = :tid";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([':u' => $username, ':tid' => $this->tenant_id]);
        if($stmtCheck->fetchColumn() > 0) return ['status' => false, 'message' => 'El usuario ya existe'];

        // Hash seguro
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (tenant_id, username, password, role) VALUES (:tid, :u, :p, :r)";
        $stmt = $this->conn->prepare($sql);
        if($stmt->execute([':tid' => $this->tenant_id, ':u' => $username, ':p' => $hash, ':r' => $role])){
            return ['status' => true];
        }
        return ['status' => false, 'message' => 'Error al insertar en BD'];
    }

    public function update($id, $username, $role, $password = null) {
        $params = [':u' => $username, ':r' => $role, ':id' => $id, ':tid' => $this->tenant_id];
        $sql = "UPDATE users SET username = :u, role = :r";

        // Solo actualizamos password si enviaron uno nuevo
        if (!empty($password)) {
            $sql .= ", password = :p";
            $params[':p'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = :id AND tenant_id = :tid";
        
        $stmt = $this->conn->prepare($sql);
        if($stmt->execute($params)) return ['status' => true];
        return ['status' => false, 'message' => 'Error al actualizar'];
    }

    public function delete($id) {
        // Evitar suicidio digital (Borrar tu propio usuario)
        if($id == $_SESSION['user_id']) {
            return ['status' => false, 'message' => 'No puedes eliminar tu propia cuenta mientras la usas.'];
        }

        $sql = "DELETE FROM users WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        if($stmt->execute([':id' => $id, ':tid' => $this->tenant_id])) return ['status' => true];
        return ['status' => false, 'message' => 'Error al eliminar'];
    }
}```

## Archivo: ./index.php
```php
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MultiPOS | Gestión Unificada para tu Negocio</title>
    
    <!-- Metadatos SEO -->
    <meta name="description" content="La solución integral para TPV, inventarios y gestión de mesas. Prueba MultiPOS gratis por 30 días.">
    <meta name="keywords" content="POS, Punto de Venta, Software de Ventas, Inventario, Gestión de Restaurantes">
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AdminLTE 4 (Bootstrap 5.3 Framework) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- Estilos Personalizados -->
    <style>
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --secondary: #7C3AED;
            --text-main: #111827;
            --text-muted: #6B7280;
            --bg-light: #f8fafc;
        }

        body {
            font-family: 'Inter', sans-serif;
            scroll-behavior: smooth;
            color: var(--text-main);
        }

        /* Navbar - Glassmorphism */
        .navbar-landing {
            background: rgba(255, 255, 255, 0.90) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        /* Botones y Textos Gradient */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            padding: 12px 28px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
            color: white;
        }
        .text-gradient {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f3e8ff 100%);
            position: relative;
            overflow: hidden;
            padding: 80px 0;
        }
        .floating-mockup {
            animation: float 6s ease-in-out infinite;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border-radius: 12px;
            border: 4px solid white;
            background-color: #e2e8f0;
            width: 100%;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }

        /* Tarjetas Generales */
        .hover-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,0,0,0.05) !important;
            background: white;
        }
        .hover-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
            border-color: rgba(79, 70, 229, 0.2) !important;
        }
        .icon-box { transition: transform 0.3s ease; }
        .hover-card:hover .icon-box { transform: scale(1.1) rotate(5deg); }

        /* Pasos (How it works) */
        .step-number {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            margin: 0 auto 1rem auto;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
        }
        .step-connector {
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: dashed 2px #cbd5e1;
            z-index: -1;
        }
        @media (max-width: 767px) { .step-connector { display: none; } }

        /* Comparativa */
        .comparison-bad { background-color: var(--bg-light); border: 1px solid #e2e8f0; }
        .comparison-good {
            background: white;
            border: 2px solid transparent;
            background-clip: padding-box;
            position: relative;
            border-radius: 1rem;
        }
        .comparison-good::before {
            content: ''; position: absolute; top: -2px; right: -2px; bottom: -2px; left: -2px;
            z-index: -1; border-radius: 1.1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        /* Tags Módulos */
        .text-purple { color: var(--secondary); }
        .module-tag {
            font-weight: 500; font-size: 0.95rem; color: var(--text-muted);
            transition: all 0.3s ease; cursor: default;
        }
        .module-tag:hover {
            background-color: #f3e8ff !important; border-color: var(--secondary) !important; color: var(--secondary);
            transform: translateY(-3px) scale(1.02); box-shadow: 0 10px 15px -3px rgba(124, 58, 237, 0.1);
        }

        /* Testimonios */
        .testimonial-avatar { width: 60px; height: 60px; object-fit: cover; border-radius: 50%; }
        .stars { color: #fbbf24; }

        /* Precios */
        .pricing-card-pro { border: 2px solid var(--primary); transform: scale(1.05); z-index: 10; }
        @media (max-width: 991px) { .pricing-card-pro { transform: scale(1); margin-top: 20px; margin-bottom: 20px; } }
        .badge-pulse { animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(79, 70, 229, 0); }
            100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); }
        }

        /* FAQ Accordion */
        .accordion-button:not(.collapsed) {
            background-color: #f3e8ff; color: var(--primary); font-weight: 600; box-shadow: none;
        }
        .accordion-button:focus { border-color: var(--primary); box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25); }

        /* Final CTA */
        .final-cta {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; border-radius: 24px;
        }
    </style>
</head>
        <body class="layout-fixed">
        <nav class="navbar navbar-expand-lg navbar-light sticky-top navbar-landing py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <div class="bg-primary bg-gradient rounded p-2 me-2 d-flex align-items-center justify-content-center text-white shadow-sm">
                <i class="bi bi-box-seam-fill fs-5"></i>
            </div>
            <span class="fw-bold fs-4" style="letter-spacing: -0.5px;">MultiPOS</span>
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <i class="bi bi-list fs-1 text-primary"></i>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center fw-medium">
                <li class="nav-item"><a class="nav-link px-3" href="#features">Características</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#hardware">Equipos</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#solutions">Módulos</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#pricing">Precios</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#faq">FAQ</a></li>
                <li class="nav-item ms-lg-3 mt-3 mt-lg-0 mb-2 mb-lg-0">
                    <a class="btn btn-light border px-4 me-lg-2 w-100" href="public/login.php">Ingresar</a>
                </li>
                <li class="nav-item w-100 w-lg-auto">
                    <a class="btn btn-primary-custom w-100" href="public/registro.php">Probar Gratis</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
        <main class="container-fluid p-0">
            <section class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-75 py-5">
                <div class="col-lg-6 text-center text-lg-start mb-5 mb-lg-0 pe-lg-5">
                    <span class="badge rounded-pill bg-white text-primary border border-primary px-3 py-2 mb-4 shadow-sm fw-medium">
                        <i class="bi bi-stars me-1 text-warning"></i> La solución todo en uno para tu negocio
                    </span>
                    <h1 class="display-4 fw-bold mb-4" style="line-height: 1.2; letter-spacing: -1px;">
                        Más que un Punto de Venta, <br>
                        es el <span class="text-gradient">Centro de Control</span>
                    </h1>
                    <p class="lead text-muted mb-5 fs-5">Desde TPV y gestión de mesas hasta inventario y logística de pedidos. MultiPOS unifica todas tus operaciones en una plataforma ágil y moderna.</p>
                    
                    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center justify-content-lg-start">
                        <a href="registro.php" class="btn btn-primary-custom btn-lg px-4 d-flex align-items-center justify-content-center gap-2">
                            Empezar prueba de 30 días <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="#demo" class="btn btn-outline-secondary btn-lg px-4 d-flex align-items-center justify-content-center">
                            Ver demostración
                        </a>
                    </div>
                    <p class="small text-muted mt-3 mb-0"><i class="bi bi-shield-check text-success me-1"></i> Sin tarjeta de crédito requerida. Cancela cuando quieras.</p>
                </div>
                
                <div class="col-lg-6 position-relative">
                    <div class="floating-mockup">
                    <!-- REEMPLAZA ESTA URL CON LA CAPTURA DE TU SOFTWARE -->
                    <img src="assets/img/mi-dashboard.png" 
                         alt="Dashboard MultiPOS" 
                         class="mockup-image">
                </div>
                    <div class="position-absolute top-50 start-50 translate-middle w-100 h-100" style="background: radial-gradient(circle, rgba(124,58,237,0.1) 0%, rgba(255,255,255,0) 70%); z-index: -1;"></div>
                </div>
            </div>
        </div>
    </section>
            <section class="py-5 bg-white border-bottom">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold display-6">Empieza a vender en minutos</h2>
                <p class="text-muted fs-5">Olvídate de implementaciones de meses. MultiPOS está listo para usarse.</p>
            </div>
            
            <div class="row position-relative text-center">
                <!-- Linea conectora para escritorio -->
                <div class="step-connector"></div>
                
                <div class="col-md-4 mb-4 mb-md-0 position-relative">
                    <div class="step-number">1</div>
                    <div class="card border-0 bg-transparent">
                        <div class="card-body">
                            <div class="mb-3"><i class="bi bi-person-plus text-primary fs-1"></i></div>
                            <h5 class="fw-bold">Crea tu cuenta</h5>
                            <p class="text-muted small">Regístrate en menos de 2 minutos sin ingresar tarjetas ni contratos a largo plazo.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4 mb-md-0 position-relative">
                    <div class="step-number">2</div>
                    <div class="card border-0 bg-transparent">
                        <div class="card-body">
                            <div class="mb-3"><i class="bi bi-cloud-arrow-up text-primary fs-1"></i></div>
                            <h5 class="fw-bold">Sube tu inventario</h5>
                            <p class="text-muted small">Importa tus productos desde Excel o créalos rápidamente en nuestro panel intuitivo.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 position-relative">
                    <div class="step-number">3</div>
                    <div class="card border-0 bg-transparent">
                        <div class="card-body">
                            <div class="mb-3"><i class="bi bi-shop text-primary fs-1"></i></div>
                            <h5 class="fw-bold">Comienza a operar</h5>
                            <p class="text-muted small">Abre tu caja, atiende mesas o procesa pedidos de inmediato. Todo sincronizado.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
            <section id="features" class="py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5">
                <span class="text-primary fw-bold text-uppercase tracking-wider small">Por qué elegirnos</span>
                <h2 class="fw-bold display-5 mt-2 mb-3">Herramientas diseñadas para crecer</h2>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 hover-card">
                        <div class="icon-box rounded-circle d-flex align-items-center justify-content-center mb-4" style="width: 56px; height: 56px; background-color: #f3e8ff; color: #7c3aed;">
                            <i class="bi bi-grid-1x2-fill fs-4"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Operación Unificada</h5>
                        <p class="text-muted small mb-0">Gestiona ventas, mesas, estacionamiento y pedidos desde una sola plataforma intuitiva.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 hover-card">
                        <div class="icon-box rounded-circle d-flex align-items-center justify-content-center mb-4" style="width: 56px; height: 56px; background-color: #eff6ff; color: #4F46E5;">
                            <i class="bi bi-box-seam-fill fs-4"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Inventario Inteligente</h5>
                        <p class="text-muted small mb-0">Tu stock se actualiza automáticamente en tiempo real con cada venta o devolución.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 hover-card">
                        <div class="icon-box rounded-circle d-flex align-items-center justify-content-center mb-4" style="width: 56px; height: 56px; background-color: #fff1f2; color: #e11d48;">
                            <i class="bi bi-truck-front-fill fs-4"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Logística Simple</h5>
                        <p class="text-muted small mb-0">Genera links de seguimiento para mensajeros con info del pedido y confirmación.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 hover-card">
                        <div class="icon-box rounded-circle d-flex align-items-center justify-content-center mb-4" style="width: 56px; height: 56px; background-color: #f0fdf4; color: #16a34a;">
                            <i class="bi bi-bar-chart-fill fs-4"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Análisis Detallado</h5>
                        <p class="text-muted small mb-0">Toma decisiones con reportes detallados de ventas, métricas y rendimiento general.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
            <section class="py-5 bg-white border-bottom border-top">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold display-5">Del Caos a la Claridad</h2>
                <p class="text-muted fs-5">Deja atrás las herramientas dispersas y toma el control real.</p>
            </div>
            <!-- (Contenido de comparativa igual al anterior...) -->
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <div class="p-4 p-lg-5 rounded-4 h-100 comparison-bad">
                        <h4 class="text-center fw-bold text-muted mb-4"><i class="bi bi-x-circle me-2"></i> El Modo Antiguo</h4>
                        <div class="bg-white p-3 rounded-3 border-start border-warning border-4 mb-3 shadow-sm opacity-75 grayscale">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-bold">Ventas en Cuaderno</span><i class="bi bi-journal-text text-muted"></i>
                            </div>
                            <div class="fs-4 fw-bold mt-1">$1.2M</div>
                            <div class="small text-muted">Cierre Manual (2 horas)</div>
                        </div>
                        <div class="bg-white p-3 rounded-3 border-start border-danger border-4 mb-3 shadow-sm opacity-75">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-bold">Inventario en Excel</span><i class="bi bi-file-earmark-spreadsheet text-muted"></i>
                            </div>
                            <div class="fs-4 fw-bold mt-1">1,280</div>
                            <div class="small text-danger"><i class="bi bi-exclamation-triangle"></i> Desactualizado</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 d-none d-lg-flex justify-content-center">
                    <div class="bg-white rounded-circle shadow-sm d-flex justify-content-center align-items-center z-1" style="width: 60px; height: 60px;">
                        <i class="bi bi-arrow-right fs-3 text-primary"></i>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="p-4 p-lg-5 comparison-good shadow-lg bg-white h-100">
                        <h4 class="text-center fw-bold mb-4 text-gradient"><i class="bi bi-check-circle-fill me-2 text-success"></i> El Futuro con MultiPOS</h4>
                        <div class="card border border-light bg-light mb-4 rounded-4 shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="fw-bold mb-0">Cierre Diario Automático</h6>
                                        <small class="text-muted">Actualizado hace 1 min</small>
                                    </div>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-1">Online</span>
                                </div>
                                <div class="bg-white p-3 rounded-3 shadow-sm mb-3 d-flex justify-content-between align-items-center border border-light">
                                    <span class="fw-bold text-muted"><i class="bi bi-wallet2 text-primary me-2"></i> Ventas Totales</span>
                                    <span class="fw-bold fs-5 text-dark">$1.250.000</span>
                                </div>
                            </div>
                        </div>
                        <ul class="list-unstyled small fw-medium text-dark">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i> Sincronizado en cualquier dispositivo.</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i> Stock automatizado y cierres precisos.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
            <section id="hardware" class="py-5 bg-light">
        <div class="container py-5 text-center">
            <h2 class="fw-bold mb-4">Compatible con tus equipos actuales</h2>
            <p class="text-muted fs-5 mb-5 mx-auto" style="max-width: 600px;">No necesitas invertir en equipos costosos. MultiPOS funciona desde el navegador y se conecta con el hardware estándar del mercado.</p>
            
            <div class="row justify-content-center g-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 bg-white shadow-sm p-4 h-100 rounded-4">
                        <i class="bi bi-pc-display fs-1 text-primary mb-3"></i>
                        <h6 class="fw-bold">PC, Mac y Tablets</h6>
                        <span class="small text-muted">Cualquier dispositivo web</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 bg-white shadow-sm p-4 h-100 rounded-4">
                        <i class="bi bi-printer fs-1 text-primary mb-3"></i>
                        <h6 class="fw-bold">Impresoras Térmicas</h6>
                        <span class="small text-muted">USB, Bluetooth o Red</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 bg-white shadow-sm p-4 h-100 rounded-4">
                        <i class="bi bi-upc-scan fs-1 text-primary mb-3"></i>
                        <h6 class="fw-bold">Lectores de Barras</h6>
                        <span class="small text-muted">Inalámbricos y USB</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 bg-white shadow-sm p-4 h-100 rounded-4">
                        <i class="bi bi-safe fs-1 text-primary mb-3"></i>
                        <h6 class="fw-bold">Cajones de Dinero</h6>
                        <span class="small text-muted">Conexión vía impresora</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
            <section id="solutions" class="py-5 bg-white border-bottom">
        <div class="container py-5 text-center">
            <h2 class="fw-bold display-5 mb-3">Un Sistema Modular</h2>
            <p class="text-muted fs-5 mb-5 mx-auto" style="max-width: 700px;">Activa solo las herramientas que tu negocio necesita en este momento.</p>

            <div class="d-flex flex-wrap justify-content-center gap-3 gap-md-4 mx-auto mt-4" style="max-width: 1000px;">
                <!-- Etiquetas de módulos -->
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-shop text-purple me-2"></i> TPV para Tiendas</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-egg-fried text-purple me-2"></i> TPV Restaurantes</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-p-square text-purple me-2"></i> Parqueaderos</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-truck text-purple me-2"></i> Entregas / Delivery</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-box-seam text-purple me-2"></i> Control de Stock</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-people text-purple me-2"></i> CRM Clientes</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-bar-chart-line text-purple me-2"></i> Informes Avanzados</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-file-earmark-text text-purple me-2"></i> Cotizaciones</div>
            </div>
        </div>
    </section>
            <section class="py-5" style="background-color: #f3e8ff;">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Lo que dicen nuestros clientes</h2>
                <p class="text-muted">Únete a cientos de negocios que ya optimizaron sus procesos.</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="stars mb-3">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p class="fst-italic text-muted">"Antes perdía horas cruzando ventas e inventario en Excel. Con MultiPOS, cierro caja en 2 minutos y sé exactamente qué me falta comprar."</p>
                        <div class="d-flex align-items-center mt-auto pt-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width:50px; height:50px;">CM</div>
                            <div>
                                <h6 class="fw-bold mb-0">Carlos Mendoza</h6>
                                <span class="small text-muted">Dueño de Minimarket</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="stars mb-3">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p class="fst-italic text-muted">"El módulo de restaurantes es increíble. Los meseros toman el pedido en su celular y sale directo en la cocina. Nos salvó los fines de semana."</p>
                        <div class="d-flex align-items-center mt-auto pt-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width:50px; height:50px;">AR</div>
                            <div>
                                <h6 class="fw-bold mb-0">Ana Rodríguez</h6>
                                <span class="small text-muted">Gerente de Pizzería</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="stars mb-3">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                        </div>
                        <p class="fst-italic text-muted">"Tengo 3 tiendas de ropa y ahora puedo ver las ventas de las 3 en tiempo real desde mi casa. La mejor inversión para mi negocio."</p>
                        <div class="d-flex align-items-center mt-auto pt-3">
                            <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width:50px; height:50px;">JV</div>
                            <div>
                                <h6 class="fw-bold mb-0">Jorge Vargas</h6>
                                <span class="small text-muted">Boutiques JV</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
            <section id="pricing" class="py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold display-5">Planes transparentes</h2>
                <p class="text-muted fs-5">Empieza gratis, escala cuando estés listo.</p>
            </div>

            <div class="row g-4 justify-content-center align-items-stretch">
                <!-- PLAN BÁSICO -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 rounded-4 p-4 hover-card">
                        <div class="card-body d-flex flex-column">
                            <h4 class="fw-bold">Básico</h4>
                            <p class="text-muted small">Ideal para emprendedores y tiendas pequeñas.</p>
                            <div class="my-4">
                                <span class="display-4 fw-bold text-dark">$0</span><span class="text-muted fw-medium">/mes</span>
                            </div>
                            <ul class="list-unstyled mb-5 flex-grow-1">
                                <li class="mb-3 d-flex"><i class="bi bi-check2 text-success me-2 fs-5"></i> 1 Punto de Venta (TPV)</li>
                                <li class="mb-3 d-flex"><i class="bi bi-check2 text-success me-2 fs-5"></i> Hasta 100 productos</li>
                                <li class="mb-3 d-flex"><i class="bi bi-check2 text-success me-2 fs-5"></i> Reportes básicos</li>
                            </ul>
                            <div class="d-grid mt-auto"><a href="registro.php?plan=basico" class="btn btn-light border rounded-pill py-2 fw-semibold">Empezar gratis</a></div>
                        </div>
                    </div>
                </div>

                <!-- PLAN PRO -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-lg rounded-4 p-4 position-relative pricing-card-pro bg-white">
                        <div class="position-absolute top-0 start-50 translate-middle">
                            <span class="badge rounded-pill bg-primary px-4 py-2 badge-pulse fw-bold shadow-sm"><i class="bi bi-star-fill text-warning me-1"></i> RECOMENDADO</span>
                        </div>
                        <div class="card-body d-flex flex-column mt-2">
                            <h4 class="fw-bold text-primary">Profesional</h4>
                            <p class="text-muted small">Para negocios con alta rotación y personal.</p>
                            <div class="my-4">
                                <span class="display-4 fw-bold text-dark">$29</span><span class="text-muted fw-medium">/mes</span>
                            </div>
                            <ul class="list-unstyled mb-5 flex-grow-1">
                                <li class="mb-3 d-flex fw-medium"><i class="bi bi-check-circle-fill text-primary me-2 fs-5"></i> TPVs y Productos Ilimitados</li>
                                <li class="mb-3 d-flex fw-medium"><i class="bi bi-check-circle-fill text-primary me-2 fs-5"></i> Inventario Avanzado</li>
                                <li class="mb-3 d-flex fw-medium"><i class="bi bi-check-circle-fill text-primary me-2 fs-5"></i> Módulos de Mesas/Delivery</li>
                                <li class="mb-3 d-flex fw-medium"><i class="bi bi-check-circle-fill text-primary me-2 fs-5"></i> Soporte Prioritario</li>
                            </ul>
                            <div class="d-grid mt-auto"><a href="registro.php?plan=pro" class="btn btn-primary-custom rounded-pill py-3 fw-bold fs-6">Prueba Gratis 30 Días</a></div>
                        </div>
                    </div>
                </div>

                <!-- PLAN PREMIUM -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 rounded-4 p-4 hover-card">
                        <div class="card-body d-flex flex-column">
                            <h4 class="fw-bold">Premium</h4>
                            <p class="text-muted small">Cadenas de tiendas y franquicias.</p>
                            <div class="my-4">
                                <span class="display-4 fw-bold text-dark">$59</span><span class="text-muted fw-medium">/mes</span>
                            </div>
                            <ul class="list-unstyled mb-5 flex-grow-1">
                                <li class="mb-3 d-flex"><i class="bi bi-check2 text-success me-2 fs-5"></i> Todo lo del Plan Pro</li>
                                <li class="mb-3 d-flex"><i class="bi bi-check2 text-success me-2 fs-5"></i> Multi-sucursal</li>
                                <li class="mb-3 d-flex"><i class="bi bi-check2 text-success me-2 fs-5"></i> API para integraciones</li>
                            </ul>
                            <div class="d-grid mt-auto"><a href="registro.php?plan=premium" class="btn btn-light border rounded-pill py-2 fw-semibold">Contactar Ventas</a></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section id="faq" class="py-5 bg-white border-top">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Preguntas Frecuentes</h2>
                <p class="text-muted">Resolvemos tus dudas antes de empezar.</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item border-0 mb-3 shadow-sm rounded">
                            <h2 class="accordion-header">
                                <button class="accordion-button rounded" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    ¿Puedo seguir usando el sistema si se corta el internet?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Sí. MultiPOS cuenta con un modo offline de contingencia que te permite seguir registrando ventas. Una vez que la conexión a internet se restablezca, todos los datos y el inventario se sincronizarán automáticamente con la nube.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm rounded">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    ¿Necesito comprar equipos especiales?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    No. MultiPOS funciona desde cualquier navegador web moderno (Chrome, Firefox, Safari). Puedes usar la computadora, tablet o teléfono que ya tienes. Además, somos compatibles con el 95% de impresoras térmicas y lectores de barras estándar USB o Bluetooth.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm rounded">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    ¿Qué pasa cuando terminan mis 30 días de prueba?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Antes de finalizar la prueba te enviaremos un aviso. Si decides no contratar un plan de pago, tu cuenta pasará automáticamente al Plan Básico (gratuito) con sus respectivas limitaciones, pero no perderás tu información. No cobramos automáticamente porque no pedimos tarjeta de crédito para el registro.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm rounded">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    Tengo un catálogo muy grande, ¿es difícil migrar a MultiPOS?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    En absoluto. Contamos con una herramienta de importación masiva mediante plantillas de Excel (.xlsx o .csv). Puedes subir miles de productos, con sus códigos de barras y precios, en cuestión de segundos. Si necesitas ayuda, nuestro equipo de soporte lo hace por ti.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
            <section class="py-5 bg-white mb-4">
        <div class="container">
            <div class="final-cta p-5 text-center shadow-lg position-relative overflow-hidden">
                <!-- Círculos decorativos de fondo -->
                <div class="position-absolute top-0 start-0 translate-middle rounded-circle bg-white opacity-10" style="width: 300px; height: 300px;"></div>
                <div class="position-absolute bottom-0 end-0 translate-middle-x rounded-circle bg-white opacity-10" style="width: 200px; height: 200px;"></div>
                
                <div class="position-relative z-1">
                    <h2 class="display-5 fw-bold mb-3">Toma el control de tu negocio hoy</h2>
                    <p class="fs-5 mb-4 text-white-50 mx-auto" style="max-width: 600px;">Únete a los emprendedores que ya están simplificando sus ventas, controlando su inventario y creciendo sin límites.</p>
                    <a href="registro.php" class="btn btn-light btn-lg px-5 py-3 rounded-pill fw-bold text-primary shadow">
                        Crear cuenta gratis ahora
                    </a>
                    <p class="mt-3 small text-white-50"><i class="bi bi-clock me-1"></i> Configuración en menos de 5 minutos.</p>
                </div>
            </div>
        </div>
    </section>
        </main>
        <footer class="bg-dark text-white pt-5 pb-4 border-top border-secondary">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4 pe-lg-5">
                <h5 class="fw-bold mb-3 d-flex align-items-center">
                    <div class="bg-primary rounded p-1 me-2 d-inline-flex">
                        <i class="bi bi-box-seam-fill text-white fs-6"></i>
                    </div>
                    MultiPOS
                </h5>
                <p class="text-secondary small mb-4">Transformando la gestión comercial con tecnología intuitiva y potente. Control total de tu negocio en la palma de tu mano.</p>
                <div class="d-flex gap-3">
                    <a href="#" class="btn btn-outline-secondary border-0 btn-sm rounded-circle"><i class="bi bi-facebook fs-5"></i></a>
                    <a href="#" class="btn btn-outline-secondary border-0 btn-sm rounded-circle"><i class="bi bi-instagram fs-5"></i></a>
                    <a href="#" class="btn btn-outline-secondary border-0 btn-sm rounded-circle"><i class="bi bi-linkedin fs-5"></i></a>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <h6 class="fw-bold mb-3 text-uppercase small text-white-50">Producto</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="#features" class="text-secondary text-decoration-none">Características</a></li>
                    <li><a href="#hardware" class="text-secondary text-decoration-none">Compatibilidad</a></li>
                    <li><a href="#solutions" class="text-secondary text-decoration-none">Módulos</a></li>
                    <li><a href="#pricing" class="text-secondary text-decoration-none">Precios</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-3 col-lg-3">
                <h6 class="fw-bold mb-3 text-uppercase small text-white-50">Soporte</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="#faq" class="text-secondary text-decoration-none">Preguntas Frecuentes</a></li>
                    <li><a href="#" class="text-secondary text-decoration-none">Documentación API</a></li>
                    <li><a href="https://wa.me/573148900155" target="_blank" class="text-success text-decoration-none fw-medium"><i class="bi bi-whatsapp me-1"></i> WhatsApp</a></li>
                </ul>
            </div>
            <div class="col-md-6 col-lg-3">
                <h6 class="fw-bold mb-3 text-uppercase small text-white-50">Legal</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="#" class="text-secondary text-decoration-none">Términos de Servicio</a></li>
                    <li><a href="#" class="text-secondary text-decoration-none">Política de Privacidad</a></li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary mt-5 mb-4 opacity-25">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="text-secondary mb-0 small">&copy; 2026 MultiPOS. Todos los derechos reservados.</p>
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                <p class="text-secondary mb-0 small">Hecho con <i class="bi bi-heart-fill text-danger mx-1"></i> para emprendedores.</p>
            </div>
        </div>
    </div>
</footer>
 
<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>

<!-- Glassmorphism Navbar Script -->
<script>
    window.addEventListener('scroll', function() {
        const nav = document.querySelector('.navbar-landing');
        if (window.scrollY > 50) {
            nav.classList.add('shadow-sm');
            nav.style.paddingTop = '10px';
            nav.style.paddingBottom = '10px';
        } else {
            nav.classList.remove('shadow-sm');
            nav.style.paddingTop = '16px';
            nav.style.paddingBottom = '16px';
        }
    });
</script>

</body>
</html>```

## Archivo: ./public/actions_category.php
```php
<?php
require_once '../config/db.php';
require_once '../includes/Category.php';
require_once '../includes/Middleware.php'; // Importante para la seguridad

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Forzar respuesta JSON
header('Content-Type: application/json');

// 2. Seguridad: Solo admins autenticados pueden ejecutar estas acciones
try {
    Middleware::checkAuth();
    Middleware::onlyAdmin();

    $database = new Database();
    $db = $database->getConnection();
    
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    if (!$tenant_id) {
        throw new Exception("Sesión de comercio no válida.");
    }

    $catObj = new Category($db, $tenant_id);
    $action = $_POST['action'] ?? '';
    $id     = $_POST['id'] ?? null;
    $name   = trim($_POST['name'] ?? '');

    $response = ['status' => false, 'message' => 'Acción no reconocida'];

    // 3. Lógica de acciones
    switch ($action) {
        case 'create':
            if (empty($name)) throw new Exception("El nombre es obligatorio.");
            if ($catObj->create($name)) {
                $response = ['status' => true, 'message' => 'Creado con éxito'];
            }
            break;

        case 'update':
            if (empty($id) || empty($name)) throw new Exception("Datos insuficientes para actualizar.");
            if ($catObj->update($id, $name)) {
                $response = ['status' => true, 'message' => 'Actualizado con éxito'];
            }
            break;

        case 'delete':
            if (empty($id)) throw new Exception("ID no proporcionado.");
            if ($catObj->delete($id)) {
                $response = ['status' => true, 'message' => 'Eliminado con éxito'];
            }
            break;
    }

    echo json_encode($response);

} catch (Exception $e) {
    // Captura cualquier error de la DB o de validación
    http_response_code(400); // Opcional: indica un error de solicitud
    echo json_encode([
        'status' => false, 
        'message' => $e->getMessage()
    ]);
}```

## Archivo: ./public/actions_config.php
```php
<?php
session_start();
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/ExchangeRate.php';
require_once '../includes/helpers.php';
Middleware::checkAuth();
Middleware::onlyAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id = $_SESSION['tenant_id'];
$database = new Database();
$db = $database->getConnection();
    // Sanitizar entradas
    $business_name = trim($_POST['business_name'] ?? '');
    $rif = trim($_POST['rif'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $currency = $_POST['currency'] ?? 'USD';
    $ticket_footer = trim($_POST['ticket_footer'] ?? '');
    
    // Checkboxes (si no vienen en el POST, asumen valor 0 o falso)
    $show_logo = isset($_POST['show_logo']) ? 1 : 0;
    $compact_tables = isset($_POST['compact_tables']) ? 1 : 0;
    $theme = isset($_POST['dark_mode']) ? 'dark' : 'light';

    // Actualizar Base de Datos
    $sql = "UPDATE tenants SET 
            business_name = ?, rif = ?, address = ?, phone = ?, 
            currency = ?, ticket_footer = ?, show_logo = ?, 
            theme = ?, compact_tables = ? 
            WHERE id = ?";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $business_name, $rif, $address, $phone, 
        $currency, $ticket_footer, $show_logo, 
        $theme, $compact_tables, $tenant_id
    ]);

    // Actualizar nombre en sesión si cambió
    $_SESSION['tenant_name'] = $business_name;
    $_SESSION['theme'] = $theme; // Útil para aplicar el modo oscuro desde PHP en tu layout

    header("Location: configuration.php?success=1");
    exit;
}```

## Archivo: ./public/actions_critical.php
```php
<?php
session_start();
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/ExchangeRate.php';
require_once '../includes/helpers.php';
Middleware::checkAuth();
Middleware::onlyAdmin();

// Verificar que la petición sea POST y traiga una acción
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['critical_action'])) {
    $database = new Database();
    $db = $database->getConnection();
    $tenant_id = $_SESSION['tenant_id'];
    $action = $_POST['critical_action'];

    try {
        switch ($action) {
            case 'purge_sales':
                // Acción: Purgar Ventas del Mes
                // Elimina únicamente las ventas del mes en curso y año en curso para este tenant.
                $sql = "DELETE FROM sales 
                        WHERE tenant_id = ? 
                        AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                        AND YEAR(created_at) = YEAR(CURRENT_DATE())";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([$tenant_id]);
                
                $msg = "Las ventas de este mes han sido purgadas correctamente.";
                break;

            case 'reset_correlative':
                // Acción: Reiniciar Correlativo (Borrar todo el historial)
                // Elimina absolutamente todas las ventas de este tenant.
                // Nota: No usamos ALTER TABLE AUTO_INCREMENT = 1 porque rompería las ventas de los otros tenants.
                $sql = "DELETE FROM sales WHERE tenant_id = ?";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([$tenant_id]);
                
                $msg = "Historial de ventas borrado. El sistema está limpio para empezar de cero.";
                break;

            default:
                throw new Exception("Acción de seguridad no reconocida.");
        }

        // Redirigir de vuelta a configuración con un mensaje de éxito
        header("Location: configuration.php?success=" . urlencode($msg));
        exit;

    } catch (Exception $e) {
        // Redirigir de vuelta con el mensaje de error
        header("Location: configuration.php?error=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Si entran directamente a la URL sin POST, los devolvemos
    header("Location: configuration.php");
    exit;
}```

## Archivo: ./public/actions_product.php
```php
<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';

// 1. Seguridad: Verificar que el usuario esté logueado
if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();
Middleware::onlyAdmin(); // Recomendable si solo los admins manejan inventario

$database = new Database();
$db = $database->getConnection();

// 2. Determinar la acción
$action = $_REQUEST['action'] ?? '';
$tenant_id = $_SESSION['tenant_id'];

try {
    switch ($action) {
        case 'create':
            // Recibir y limpiar datos obligatorios
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) throw new Exception("El nombre del producto es obligatorio.");

            // Datos opcionales y casteos seguros
            $sku = trim($_POST['sku'] ?? '');
            $barcode = trim($_POST['barcode'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $catId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 1;
            
            $price = floatval($_POST['price'] ?? 0);
            $margin = floatval($_POST['margin'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            
            $image = trim($_POST['image'] ?? '');
            $description = trim($_POST['description'] ?? '');

            $sql = "INSERT INTO products 
                    (tenant_id, category_id, name, sku, barcode, brand, description, image, price_base_usd, profit_margin, stock, created_at) 
                    VALUES 
                    (:tid, :catid, :name, :sku, :barcode, :brand, :desc, :img, :price, :margin, :stock, NOW())";
            
            $stmt = $db->prepare($sql);
            $res = $stmt->execute([
                ':tid' => $tenant_id,
                ':catid' => $catId,
                ':name' => $name,
                ':sku' => $sku,
                ':barcode' => $barcode,
                ':brand' => $brand,
                ':desc' => $description,
                ':img' => $image,
                ':price' => $price,
                ':margin' => $margin,
                ':stock' => $stock
            ]);

            if ($res) {
                header("Location: admin.php?msg=created"); 
                exit;
            } else {
                throw new Exception("No se pudo crear el producto en la base de datos.");
            }
            break;

        case 'update':
            // Validar ID
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) throw new Exception("ID de producto inválido para actualizar.");

            $name = trim($_POST['name'] ?? '');
            if (empty($name)) throw new Exception("El nombre del producto es obligatorio.");

            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 1;
            $sku = trim($_POST['sku'] ?? '');
            $barcode = trim($_POST['barcode'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $margin = floatval($_POST['margin'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            
            $image = trim($_POST['image'] ?? '');
            $description = trim($_POST['description'] ?? '');

            $sql = "UPDATE products 
                    SET name = :name,
                        category_id = :category_id,
                        sku = :sku,
                        barcode = :barcode,
                        brand = :brand,
                        description = :desc,
                        image = :img,
                        price_base_usd = :price, 
                        profit_margin = :margin, 
                        stock = :stock 
                    WHERE id = :id AND tenant_id = :tid";
            
            $stmt = $db->prepare($sql);
            $res = $stmt->execute([
                ':name' => $name,
                ':category_id' => $category_id,
                ':sku' => $sku,
                ':barcode' => $barcode,
                ':brand' => $brand,
                ':desc' => $description,
                ':img' => $image,
                ':price' => $price,
                ':margin' => $margin,
                ':stock' => $stock,
                ':id' => $id,
                ':tid' => $tenant_id
            ]);

            if ($res) {
                header("Location: admin.php?msg=updated");
                exit;
            } else {
                throw new Exception("Error al ejecutar la actualización.");
            }
            break;

        case 'delete':
            // Tomamos el ID de GET o POST de forma segura
            $id = !empty($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
            
            if ($id <= 0) throw new Exception("ID no proporcionado para eliminar.");

            $sql = "DELETE FROM products WHERE id = :id AND tenant_id = :tid";
            $stmt = $db->prepare($sql);
            $res = $stmt->execute([
                ':id' => $id,
                ':tid' => $tenant_id
            ]);

            if ($res) {
                header("Location: admin.php?msg=deleted");
                exit;
            } else {
                throw new Exception("Error al intentar eliminar el producto.");
            }
            break;

        default:
            header("Location: admin.php");
            exit;
    }
} catch (Exception $e) {
    // Redirigir con el mensaje de error para que se muestre en el alert de Bootstrap
    header("Location: admin.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>```

## Archivo: ./public/actions_rate.php
```php
<?php
// public/actions_rate.php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/ExchangeRate.php';

// 1. Forzar respuesta JSON estricta
header('Content-Type: application/json');

try {
    // 2. Seguridad: Verificar sesión y permisos
    Middleware::checkAuth();
    Middleware::onlyAdmin();

    $database = new Database();
    $db = $database->getConnection();
    
    $rateObj = new ExchangeRate($db);
    
    // 3. Capturar la nueva tasa
    $newRate = $_POST['rate'] ?? null;

    if (!$newRate || !is_numeric($newRate) || $newRate <= 0) {
        throw new Exception("El valor de la tasa debe ser un número mayor a 0.");
    }

    // 4. Actualizar la tasa usando el método de tu clase ExchangeRate
    // (Asegúrate de que la clase ExchangeRate tenga el método updateRate, el cual sí está en tu código base)
    $result = $rateObj->updateRate((float)$newRate);

    if ($result) {
        echo json_encode([
            'status' => true, 
            'message' => 'Tasa BCV actualizada exitosamente'
        ]);
    } else {
        throw new Exception("No se pudo guardar la tasa en la base de datos.");
    }

} catch (Exception $e) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'status' => false, 
        'message' => $e->getMessage()
    ]);
}
?>```

## Archivo: ./public/actions_user.php
```php
<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/User.php';

session_start();
Middleware::onlyAdmin(); // Solo admins tocan esto

header('Content-Type: application/json');

$db = (new Database())->getConnection();
$userObj = new User($db, $_SESSION['tenant_id']);
$action = $_POST['action'] ?? '';

try {
    if ($action === 'create') {
        $res = $userObj->create($_POST['username'], $_POST['password'], $_POST['role']);
        echo json_encode($res);
    } 
    elseif ($action === 'update') {
        // Si password viene vacío, se envía null
        $pass = !empty($_POST['password']) ? $_POST['password'] : null;
        $res = $userObj->update($_POST['id'], $_POST['username'], $_POST['role'], $pass);
        echo json_encode($res);
    } 
    elseif ($action === 'delete') { // GET request normalmente para delete simple
        $id = $_POST['id'];
        $res = $userObj->delete($id);
        echo json_encode($res);
    } 
    else {
        echo json_encode(['status' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}```

## Archivo: ./public/admin.php
```php
<?php
require_once '../controllers/AdminController.php';
include 'layouts/head.php';
// Agregamos el CSS de DataTables (si prefieres, muévelo a layouts/head.php)
echo '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">';
include 'layouts/navbar.php';
include 'layouts/sidebar.php';
?>
<main class="app-main">
    <?= render_content_header($headerConfig) ?>
     <div class="app-content">
        <div class="container-fluid">
            
            <?php 
            if(isset($_GET['msg'])): 
                $alerts = [
                    'created' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Producto añadido correctamente.'],
                    'updated' => ['class' => 'info', 'icon' => 'edit', 'text' => 'Producto actualizado con éxito.'],
                    'deleted' => ['class' => 'warning', 'icon' => 'trash', 'text' => 'Producto eliminado del inventario.']
                ];
                $m = $alerts[$_GET['msg']] ?? null;
                if($m):
            ?>
                <div class="alert alert-<?= $m['class'] ?> alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-<?= $m['icon'] ?> me-2"></i> <?= $m['text'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; endif; ?>

            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <strong>Error:</strong> <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end gap-2 mb-3">
                <a href="generate_pdf.php?type=sales" target="_blank" class="btn btn-sm btn-outline-info">
                    <i class="fas fa-file-pdf me-1"></i> Cierre del Día
                </a>
                <a href="generate_pdf.php?type=inventory" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-boxes me-1"></i> Reporte Inventario
                </a>
            </div>

            <div class="row">
                <div class="col-12 col-sm-6 col-md-4 mb-3">
                    <div class="info-box shadow-sm h-100">
                        <span class="info-box-icon text-bg-success"><i class="fas fa-cash-register"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-uppercase small fw-bold text-secondary">Ventas de Hoy</span>
                            <span class="info-box-number text-success fs-4 mb-0">$<?= number_format($salesToday, 2) ?></span>
                            <span class="progress-description text-muted small">≈ Bs. <?= number_format($salesToday * $bcvRate, 2) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4 mb-3">
                    <div class="info-box shadow-sm h-100">
                        <span class="info-box-icon <?= $lowStockCount > 0 ? 'text-bg-danger' : 'text-bg-info' ?>">
                            <i class="fas fa-exclamation-triangle"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-uppercase small fw-bold text-secondary">Bajo Stock (< 5)</span>
                            <span class="info-box-number <?= $lowStockCount > 0 ? 'text-danger' : 'text-info' ?> fs-4 mb-0"><?= $lowStockCount ?></span>
                            <span class="progress-description <?= $lowStockCount > 0 ? 'text-danger opacity-75' : 'text-muted' ?> small">
                                <?= $lowStockCount > 0 ? '¡Necesitas reponer!' : 'Stock saludable' ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4 mb-3">
                    <div class="info-box shadow-sm h-100">
                        <span class="info-box-icon text-bg-warning"><i class="fas fa-cubes text-white"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-uppercase small fw-bold text-secondary">Capital Inventario</span>
                            <span class="info-box-number text-warning fs-4 mb-0">$<?= number_format($inventoryValue, 2) ?></span>
                            <span class="progress-description text-muted small">Valor a costo base</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="productosTable" class="table table-hover table-striped align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Producto</th>
                                    <th>SKU</th> 
                                    <th>Marca</th> 
                                    <th>Categoría</th>
                                    <th>Stock</th>
                                    <th>Costo ($)</th>
                                    <th>Costo (Bs)</th>
                                    <th>Precio ($)</th>
                                    <th>Precio (Bs)</th>
                                    <th>Ganancia</th>
                                    <th class="text-end pe-3">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="inventoryBody">
                                <?php if(!empty($products)): ?>
                                    <?php foreach($products as $p): 
                                        $costo_usd = $p['price_base_usd'];
                                        $precio_usd = $costo_usd * (1 + ($p['profit_margin'] / 100));
                                        $ganancia_usd = $precio_usd - $costo_usd;
                                        $ganancia_bs = $ganancia_usd * $bcvRate;
                                        $p_json = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center">
                                                <?php if(!empty($p['image'])): ?>
                                                    <img src="uploads/<?= htmlspecialchars($p['image']) ?>" class="rounded object-fit-contain me-3 border" style="width: 45px; height: 45px;" alt="img">
                                                <?php else: ?>
                                                    <div class="bg-secondary bg-opacity-25 rounded d-flex align-items-center justify-content-center me-3 border" style="width: 45px; height: 45px;">
                                                        <i class="fas fa-box text-secondary"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong class="d-block"><?= htmlspecialchars($p['name']) ?></strong>
                                                    <?php if(!empty($p['description'])): ?>
                                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;"><?= htmlspecialchars($p['description']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td class="small text-muted font-monospace"><?= !empty($p['sku']) ? htmlspecialchars($p['sku']) : '-' ?></td>
                                        <td><span class="badge text-bg-light border"><?= !empty($p['brand']) ? htmlspecialchars($p['brand']) : 'N/A' ?></span></td>

                                        <td>
                                            <span class="badge text-bg-secondary bg-opacity-75">
                                                <?= htmlspecialchars($p['category_name'] ?? 'General') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill <?= $p['stock'] < 5 ? 'text-bg-danger' : 'text-bg-success' ?>">
                                                <?= $p['stock'] ?> ud
                                            </span>
                                        </td>
                                        
                                        <td class="text-success fw-bold">$<?= number_format($costo_usd, 2) ?></td>
                                        <td class="fw-bold">Bs. <?= number_format($costo_usd * $bcvRate, 2) ?></td>
                                        <td class="text-success fw-bold">$<?= number_format($precio_usd, 2) ?></td>
                                        <td class="fw-bold">Bs. <?= number_format($precio_usd * $bcvRate, 2) ?></td>
                                        
                                        <td class="text-nowrap">
                                            <span style="color: #0d47a1;" class="fw-bold me-1">$<?= number_format($ganancia_usd, 2) ?></span>
                                            <span class="badge rounded-pill px-2 py-1" style="background-color: #d1fae5; color: #0f766e; font-size: 0.85rem; font-weight: 600;">
                                                <?= number_format($ganancia_bs, 2) ?> bs
                                            </span>
                                        </td>

                                        <td class="text-end pe-3">
                                            <div class="btn-group">                                        
                                                <button class="btn btn-sm btn-outline-info me-1" onclick='viewProduct(<?= $p_json ?>)' title="Ver Detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning me-1" onclick='editProduct(<?= $p_json ?>)' title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger me-1" onclick='confirmDelete(<?= $p["id"] ?>, "<?= addslashes($p["name"]) ?>")' title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div> </div> </div> </div> </main>

<?php
include 'layouts/footer.php'; 
include 'layouts/modals/modals_admin.php';
?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script src="js/admin.js"></script>
</body>
</html>```

## Archivo: ./public/categories.php
```php
<?php
require_once '../controllers/CategoryController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
<main class="app-main">
    <?= render_content_header($headerConfig) ?>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-8">
                    <div class="card card-outline card-primary shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title">Listado de Categorías</h3>
                        </div>
                        
                        <div class="card-body p-0">
                            <?php if(empty($categories)): ?>
                                <div class="p-5 text-center text-secondary">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-muted"></i>
                                    <p class="mb-0">No hay categorías registradas en el sistema.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="ps-4" style="width: 50px;">#</th>
                                                <th>Nombre</th>
                                                <th class="text-end pe-4" style="width: 150px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($categories as $c): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <i class="fas fa-tag text-secondary small"></i>
                                                </td>
                                                <td>
                                                    <span class="fw-bold"><?= htmlspecialchars($c['name']) ?></span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="btn-group">
                                                        <!-- Pasamos el array completo de forma segura -->
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick='openModal(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' 
                                                                title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" 
                                                                onclick="confirmDelete(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['name'])) ?>')" 
                                                                title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
include 'layouts/footer.php'; 
include 'layouts/modals/modals_category.php';
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/categories.js"></script>
</body>
</html>```

## Archivo: ./public/configuration.php
```php
<?php
require_once '../controllers/ConfigController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
    <main class="app-main">
        <?= render_content_header($headerConfig) ?>
        <div class="app-content">
            <div class="container-fluid">
                <form id="formConfig" action="actions_config.php" method="POST">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card card-outline card-primary mb-4 shadow-sm">
                                <div class="card-header">
                                    <h3 class="card-title fw-bold">Perfil del Negocio</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Nombre de la Empresa</label>
                                            <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($tenant_data['business_name'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">RIF / Identificación Fiscal</label>
                                            <input type="text" name="rif" class="form-control" placeholder="J-12345678-0" value="<?= htmlspecialchars($tenant_data['rif'] ?? '') ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-bold">Dirección Comercial</label>
                                            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($tenant_data['address'] ?? '') ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Teléfono de Contacto</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                                <input type="text" name="phone" class="form-control" placeholder="+58..." value="<?= htmlspecialchars($tenant_data['phone'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Moneda Principal</label>
                                            <select class="form-select" name="currency">
                                                <option value="USD" <?= ($tenant_data['currency'] == 'USD') ? 'selected' : '' ?>>Dólares (USD)</option>
                                                <option value="VES" <?= ($tenant_data['currency'] == 'VES') ? 'selected' : '' ?>>Bolívares (VES)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-outline card-info mb-4 shadow-sm">
                                <div class="card-header">
                                    <h3 class="card-title fw-bold">Personalización de Tickets</h3>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Mensaje al pie del ticket</label>
                                        <textarea name="ticket_footer" class="form-control"><?= htmlspecialchars($tenant_data['ticket_footer'] ?? '') ?></textarea>
                                        <div class="form-text">Este texto aparecerá al final de cada recibo impreso.</div>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="showLogo" name="show_logo" <?= !empty($tenant_data['show_logo']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="showLogo">Mostrar logo en el encabezado</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card mb-4 shadow-sm">
                                <div class="card-header">
                                    <h3 class="card-title fw-bold">Interfaz</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="dark_mode" id="darkModeSwitch" <?= ($tenant_data['theme'] === 'dark') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="darkModeSwitch">Modo Oscuro</label>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="compact_tables" id="compactTables" <?= !empty($tenant_data['compact_tables']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="compactTables">Tablas compactas</label>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-danger card-outline shadow-sm">
                                <div class="card-header">
                                    <h3 class="card-title fw-bold text-danger">
                                        <i class="fas fa-exclamation-triangle me-2"></i> Zona Crítica
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <p class="small text-secondary mb-3">Las siguientes acciones no se pueden deshacer. Por favor, proceda con precaución.</p>
                                    

                                    <button type="button" class="btn btn-outline-danger w-100 mb-2 btn-sm text-start" onclick="confirmAction('purgar las ventas del mes', 'purge_sales')">
                                        <i class="fas fa-eraser me-2"></i> Purgar Ventas del Mes
                                    </button>
                                    
                                    <button type="button" class="btn btn-outline-danger w-100 btn-sm text-start" onclick="confirmAction('reiniciar el correlativo de facturas', 'reset_correlative')">
                                        <i class="fas fa-redo-alt me-2"></i> Reiniciar Correlativo
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
<?php include 'layouts/footer.php'; ?>
<script src="js/config.js"></script>
</body>
</html>```

## Archivo: ./public/dashboard.php
```php
<?php
require_once '../controllers/DashboardController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>

<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include 'layouts/head.php'; ?>

        <main class="app-main">
            <?= render_content_header($headerConfig) ?>
            <div class="app-content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-lg-3 col-6">
                            <div class="small-box text-bg-info shadow-sm">
                                <div class="inner">
                                    <h3>$<?= number_format($mySalesToday, 2) ?></h3>
                                    <p class="mb-0">Mis Ventas Hoy (USD)</p>
                                </div>
                                <div class="small-box-icon"><i class="fas fa-dollar-sign"></i></div>
                                <a href="sales_history.php?filter=today" class="small-box-footer link-light link-underline-opacity-0">
                                    Ver historial <i class="fas fa-arrow-circle-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box text-bg-success shadow-sm">
                                <div class="inner">
                                    <h3><?= $myInvoices ?></h3>
                                    <p class="mb-0">Mis Facturas Hoy</p>
                                </div>
                                <div class="small-box-icon"><i class="fas fa-file-invoice"></i></div>
                                <a href="sales_history.php?filter=today" class="small-box-footer link-light link-underline-opacity-0">
                                    Ver tickets <i class="fas fa-arrow-circle-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box text-bg-warning shadow-sm">
                                <div class="inner text-white">
                                    <h3 class="text-white">Bs <?= number_format($bcvRate, 2) ?></h3>
                                    <p class="mb-0 text-white">Tasa BCV del Día</p>
                                </div>
                                <div class="small-box-icon"><i class="fas fa-coins text-white"></i></div>
                                <a href="pos.php" class="small-box-footer text-white link-underline-opacity-0">
                                    Ir a caja <i class="fas fa-arrow-circle-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box text-bg-danger shadow-sm">
                                <div class="inner">
                                    <h3>Bs <?= number_format($mySalesToday * $bcvRate, 2) ?></h3>
                                    <p class="mb-0">Mis Ventas Hoy (Bs)</p>
                                </div>
                                <div class="small-box-icon"><i class="fas fa-wallet"></i></div>
                                <a href="sales_history.php" class="small-box-footer link-light link-underline-opacity-0">
                                    Cuadre de caja <i class="fas fa-arrow-circle-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-lg-8">
                            <div class="card card-outline card-primary shadow-sm mb-4">
                                <div class="card-header border-0">
                                    <h3 class="card-title fw-bold">Rendimiento de Ventas (USD)</h3>
                                </div>
                                <div class="card-body">
                                    <div id="revenue-chart" style="min-height: 300px;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card card-outline card-info shadow-sm mb-4 h-100">
                                <div class="card-header border-0">
                                    <h3 class="card-title fw-bold">Accesos Rápidos</h3>
                                </div>
                                <div class="card-body d-flex flex-column gap-2">
                                    <a href="pos.php" class="btn btn-outline-success btn-lg text-start fw-bold shadow-sm"><i class="fas fa-cash-register me-2"></i> Abrir Punto de Venta</a>
                                    <a href="admin.php" class="btn btn-outline-primary text-start fw-bold"><i class="fas fa-box me-2"></i> Inventario</a>
                                    <a href="sales.php" class="btn btn-outline-warning text-start fw-bold"><i class="fas fa-chart-line me-2"></i> Reportes</a>
                                    <a href="users.php" class="btn btn-outline-info text-start fw-bold"><i class="fas fa-users me-2"></i> Usuarios</a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>
    <?php include 'layouts/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"></script>
    <script>
        window.APP_JS_CHARTSALE = <?= $jsChartSales?>;
        window.APP_JS_CHARTDATES = <?= $jsChartDates?>;
    </script>
    <script src="js/dashboard.js"></script>
</body>
</html>```

## Archivo: ./public/generate_pdf.php
```php
<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Report.php';

Middleware::onlyAdmin(); // Solo el jefe descarga reportes

$db = (new Database())->getConnection();
$reportObj = new Report($db, $_SESSION['tenant_id']);
$type = $_GET['type'] ?? 'sales';

if ($type == 'sales') {
    $data = $reportObj->getDailySales();
    $title = "Cierre de Caja - " . date('d/m/Y');
} else {
    $data = $reportObj->getInventoryStatus();
    $title = "Reporte de Inventario - " . date('d/m/Y');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #444; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f2f2f2; border: 1px solid #ddd; padding: 8px; text-align: left; }
        td { border: 1px solid #ddd; padding: 8px; }
        .total-box { text-align: right; font-size: 16px; margin-top: 20px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="no-print" style="background:#fff3cd; padding:15px; text-align:center; border:1px solid #ffeeba;">
    <strong>Vista Previa del Reporte</strong><br>
    Presione <button onclick="window.print()">CTRL + P</button> y seleccione "Guardar como PDF".
    <a href="admin.php">Volver</a>
</div>

<div class="header">
    <h1><?= strtoupper($_SESSION['tenant_name']) ?></h1>
    <h2><?= $title ?></h2>
    <p>Generado por: <?= $_SESSION['username'] ?> | Fecha: <?= date('d/m/Y H:i:s') ?></p>
</div>

<table>
    <thead>
        <?php if($type == 'sales'): ?>
            <tr>
                <th>ID</th>
                <th>Hora</th>
                <th>Usuario</th>
                <th>Método</th>
                <th>Total USD</th>
            </tr>
        <?php else: ?>
            <tr>
                <th>Producto</th>
                <th>Stock</th>
                <th>Costo ($)</th>
                <th>P. Venta ($)</th>
            </tr>
        <?php endif; ?>
    </thead>
    <tbody>
        <?php 
        $grandTotal = 0;
        foreach($data as $row): 
            if($type == 'sales') $grandTotal += $row['total_amount_usd'];
        ?>
            <tr>
                <?php if($type == 'sales'): ?>
                    <td><?= $row['id'] ?></td>
                    <td><?= date('H:i', strtotime($row['created_at'])) ?></td>
                    <td><?= $row['username'] ?></td>
                    <td><?= $row['payment_method'] ?></td>
                    <td>$<?= number_format($row['total_amount_usd'], 2) ?></td>
                <?php else: ?>
                    <td><?= $row['name'] ?></td>
                    <td><?= $row['stock'] ?></td>
                    <td>$<?= number_format($row['price_base_usd'], 2) ?></td>
                    <td>$<?= number_format($row['p_venta'], 2) ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if($type == 'sales'): ?>
<div class="total-box">
    <strong>TOTAL DEL DÍA: $<?= number_format($grandTotal, 2) ?></strong>
</div>
<?php endif; ?>

<script>
    // window.print(); // Descomentar para abrir diálogo de impresión automáticamente
</script>
</body>
</html>```

## Archivo: ./public/layouts/footer.php
```php
<footer class="app-footer">
            <div class="float-end d-none d-sm-inline">
                Anything you want
            </div>
            <strong>
                Copyright &copy; <?= date('Y') ?>&nbsp;<a href="#" class="text-decoration-none">Sistema POS v2.0</a>.
            </strong> 
            All rights reserved.
        </footer>
    </div> <!-- Cierre del wrapper principal -->

    <!-- 1. OverlayScrollbars (Cargar antes para que el layout lo use) -->
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>

    <!-- 2. Bootstrap 5.3.3 BUNDLE (Incluye Popper.js, no necesitas más de Bootstrap) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

    <!-- 3. AdminLTE 4 (Solo una vez) -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>

    <!-- 4. Plugins adicionales (Gráficos, Mapas, Sortable) -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js" integrity="sha256-+vh8GkaU7C9/wbSLIcwq82tQ2wTf44aOHA8HlBMwRI8=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/js/jsvectormap.min.js" integrity="sha256-/t1nN2956BT869E6H4V1dnt0X5pAQHPytli+1nTZm2Y=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/maps/world.js" integrity="sha256-XPpPaZlU8S/HWf7FZLAncLg2SAkP8ScUTII89x9D3lY=" crossorigin="anonymous"></script>
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>```

## Archivo: ./public/layouts/footer_landing.php
```php
<footer class="bg-dark text-white pt-5 pb-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-box-seam me-2"></i>MultiPOS</h5>
                    <p class="text-secondary small">Transformando la gestión comercial con tecnología intuitiva y potente. Control total de tu negocio en la palma de tu mano.</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white fs-5"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-white fs-5"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="text-white fs-5"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                
                <div class="col-md-2 mb-4">
                    <h6 class="fw-bold">Producto</h6>
                    <ul class="list-unstyled small">
                        <li><a href="#" class="text-secondary text-decoration-none">Funciones</a></li>
                        <li><a href="#" class="text-secondary text-decoration-none">Actualizaciones</a></li>
                        <li><a href="#" class="text-secondary text-decoration-none">Seguridad</a></li>
                    </ul>
                </div>

                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold">Soporte</h6>
                    <ul class="list-unstyled small">
                        <li><a href="#" class="text-secondary text-decoration-none">Centro de Ayuda</a></li>
                        <li><a href="#" class="text-secondary text-decoration-none">Documentación API</a></li>
                        <li><a href="https://wa.me/573148900155" class="text-secondary text-decoration-none">Contacto WhatsApp</a></li>
                    </ul>
                </div>

                <div class="col-md-3 mb-4 text-md-end">
                    <h6 class="fw-bold">Legal</h6>
                    <ul class="list-unstyled small">
                        <li><a href="#" class="text-secondary text-decoration-none">Términos de Servicio</a></li>
                        <li><a href="#" class="text-secondary text-decoration-none">Política de Privacidad</a></li>
                    </ul>
                </div>
            </div>
            
            <hr class="border-secondary mt-4">
            
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="text-secondary mb-0 small">&copy; <?php echo date("Y"); ?> MultiPOS. Todos los derechos reservados.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="text-secondary mb-0 small">Hecho con <i class="bi bi-heart-fill text-danger"></i> para emprendedores.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts de Bootstrap y AdminLTE 4 -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>
    
    <!-- Script para efectos de scroll en el Navbar -->
    <script>
        window.addEventListener('scroll', function() {
            const nav = document.querySelector('.navbar-landing');
            if (window.scrollY > 50) {
                nav.classList.add('shadow-sm');
            } else {
                nav.classList.remove('shadow-sm');
            }
        });
    </script>
</body>
</html>```

## Archivo: ./public/layouts/get_sale_details.php
```php
```

## Archivo: ./public/layouts/head.php
```php
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title><?= isset($pageTitle) ? $pageTitle : 'Mi Negocio' ?></title>

    <!-- Meta Tags de Color y Tema -->
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />

    <!-- Script de Tema (Ejecutar lo antes posible para evitar parpadeo blanco) -->
    <script>
        const theme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-bs-theme', theme);
    </script>

    <!-- 1. Tipografías -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q=" crossorigin="anonymous" />

    <!-- 2. Plugins de Terceros (CSS) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css" integrity="sha256-4MX+61mt9NVvvuPjUWdUdyfZfxSB1/Rf9WtqRHgG5S0=" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css" integrity="sha256-+uGLJmmTKOqBr+2E6KDYs/NRsHxSkONXFHUL0fy2O/4=" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- 3. AdminLTE (Tu estilo principal al final para que pueda sobreescribir si es necesario) -->
    <link rel="preload" href="./css/adminlte.css" as="style" />
    <link rel="stylesheet" href="./css/adminlte.css" />
    <link rel="stylesheet" href="./css/custom.css" />
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-mini sidebar-collapse bg-body-tertiary">
    <div class="app-wrapper">```

## Archivo: ./public/layouts/header.php
```php

        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <h3 class="mb-0"><i class="fas fa-box text-primary me-2"></i> <?= htmlspecialchars($current_page) ?></h3>
                        <small class="text-secondary"><?= htmlspecialchars($tenant_name) ?></small>
                    </div>
                    <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
                        <span class="badge bg-dark border border-warning text-warning px-3 py-2 me-2" title="Tasa de cambio oficial">
                            <i class="fas fa-coins me-1" aria-hidden="true"></i> BCV: Bs. <?= number_format($bcvRate, 2) ?>
                        </span>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalInsert">
                            <i class="fas fa-plus me-1"></i> Nuevo Producto
                        </button>
                    </div>
                </div>
            </div>
        </div>```

## Archivo: ./public/layouts/header_landing.php
```php
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MultiPOS | Gestión Unificada para tu Negocio</title>
    
    <!-- Metadatos SEO -->
    <meta name="description" content="La solución integral para TPV, inventarios y gestión de mesas. Prueba MultiPOS gratis por 30 días.">
    <meta name="keywords" content="POS, Punto de Venta, Software de Ventas, Inventario, Gestión de Restaurantes">
    
    <!-- Google Fonts: Inter (la que usa el código de Next.js) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <!-- AdminLTE 4 (Bootstrap 5.3 Framework) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            scroll-behavior: smooth;
        }
        .navbar-landing {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #eee;
        }
        .hero-gradient {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        }
        .btn-primary-custom {
            background-color: #4F46E5;
            border-color: #4F46E5;
            padding: 12px 28px;
            font-weight: 600;
        }
        .btn-primary-custom:hover {
            background-color: #4338CA;
            transform: translateY(-2px);
            transition: all 0.3s;
        }
    </style>
</head>
<body class="layout-fixed">```

## Archivo: ./public/layouts/modals/modals_admin.php
```php
<div class="modal fade" id="modalInsert" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"> <form action="actions_product.php" method="POST" class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Añadir Nuevo Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="create">
                
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold">Nombre del Producto <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-box"></i></span>
                            <input type="text" name="name" class="form-control" placeholder="Nombre descriptivo" required>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Categoría <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="" disabled selected>Seleccione...</option>
                            <?php foreach($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Descripción</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Breve descripción del producto..."></textarea>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">SKU</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-barcode"></i></span>
                            <input type="text" class="form-control" name="sku" placeholder="MTB-001">
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Código de Barras</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-qrcode"></i></span>
                            <input type="text" class="form-control" name="barcode" placeholder="750123...">
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Marca</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-tag"></i></span>
                            <input type="text" class="form-control" name="brand" placeholder="Shimano">
                        </div>
                    </div>

                    <hr class="my-3 opacity-25">

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Costo Base ($) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">$</span>
                            <input type="number" step="0.01" name="price" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">% Margen <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="margin" class="form-control" value="30" required>
                            <span class="input-group-text bg-light">%</span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Stock Inicial <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-boxes"></i></span>
                            <input type="number" name="stock" class="form-control" required>
                        </div>
                    </div>

                    <div class="col-12 mb-0">
                        <label class="form-label fw-bold">URL Imagen</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-image"></i></span>
                            <input type="text" name="image" class="form-control" placeholder="https://ejemplo.com/foto.jpg">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Guardar Producto</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="actions_product.php" method="POST" class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Editar Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold">Nombre del Producto <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-box"></i></span>
                            <input type="text" name="name" id="edit_name" class="form-control" placeholder="Nombre descriptivo" required>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Categoría <span class="text-danger">*</span></label>
                        <select name="category_id" id="edit_category" class="form-select" required>
                            <option value="" disabled selected>Seleccione...</option>
                            <?php foreach($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Descripción</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2" placeholder="Breve descripción del producto..."></textarea>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">SKU</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-barcode"></i></span>
                            <input type="text" class="form-control" name="sku" id="edit_sku" placeholder="MTB-001">
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Código de Barras</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-qrcode"></i></span>
                            <input type="text" class="form-control" name="barcode" id="edit_barcode" placeholder="750123...">
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Marca</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-tag"></i></span>
                            <input type="text" class="form-control" name="brand" id="edit_brand" placeholder="Shimano">
                        </div>
                    </div>

                    <hr class="my-3 opacity-25">

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Costo Base ($) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">$</span>
                            <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">% Margen <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="margin" id="edit_margin" class="form-control" value="30" required>
                            <span class="input-group-text bg-light">%</span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Stock Inicial <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-boxes"></i></span>
                            <input type="number" name="stock" id="edit_stock" class="form-control" required>
                        </div>
                    </div>

                    <div class="col-12 mb-0">
                        <label class="form-label fw-bold">URL Imagen</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-image"></i></span>
                            <input type="text" name="image" id="edit_image" class="form-control" placeholder="https://ejemplo.com/foto.jpg">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Actualizar Producto</button>
            </div>
        </form>
    </div>
</div>


<div class="modal fade" id="modalView" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i> Detalles del Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewContent">
                </div>
            <div class="modal-footer bg-light p-2">
                 <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>



<div class="modal fade" id="modalConfirmDelete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title mx-auto">Confirmar Borrado</h5>
            </div>
            <div class="modal-body text-center py-4">
                <div class="text-danger mb-3">
                    <i class="fas fa-times-circle fa-4x animate__animated animate__pulse animate__infinite"></i>
                </div>
                <h5 class="fw-bold text-dark">¿Estás seguro?</h5>
                <p class="text-muted px-2" id="deleteProductName"></p>
                <small class="text-danger fw-bold uppercase">Esta acción es irreversible</small>
            </div>
            <div class="modal-footer justify-content-center border-0 pb-4">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                <a id="btnConfirmDelete" href="#" class="btn btn-danger px-4 shadow-sm">Sí, Eliminar</a>
            </div>
        </div>
    </div>
</div>```

## Archivo: ./public/layouts/modals/modals_category.php
```php
<div class="modal fade" id="modalCat" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formCat" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-tag me-2"></i> Categoría</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="action" value="create">
                <input type="hidden" name="id" id="catId">
                <div class="mb-3">
                    <label for="catName" class="form-label">Nombre de la Categoría <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="catName" class="form-control" placeholder="Ej: Lubricantes, Filtros..." required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="modalDelete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal-dark">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Eliminar Categoría</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="text-danger mb-3">
                    <i class="fas fa-trash-alt fa-3x"></i>
                </div>
                <p class="mb-1">¿Estás seguro de que deseas eliminar la categoría?</p>
                <h4 id="deleteCatName" class="fw-bold mb-3"></h4>
                <div class="alert alert-warning text-start small mb-0">
                    <i class="fas fa-info-circle me-1"></i> Los productos asociados podrían quedar sin categoría asignada. Esta acción no se puede deshacer.
                </div>
                <input type="hidden" id="deleteCatId">
            </div>
            <div class="modal-footer justify-content-center bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="executeDelete()"><i class="fas fa-trash me-1"></i> Sí, Eliminar</button>
            </div>
        </div>
    </div>
</div>
```

## Archivo: ./public/layouts/modals/modals_pos.php
```php

<div class="modal fade" id="modalBCV" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-coins me-2"></i> Actualizar Tasa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <label class="form-label text-muted small">Nueva Tasa (Bs/$)</label>
                <input type="number" step="0.01" id="newRateInput" class="form-control form-control-lg text-center fw-bold text-dark border-warning" value="<?= $bcvRate ?>">
            </div>
            <div class="modal-footer bg-light justify-content-center">
                <button type="button" class="btn btn-warning fw-bold w-100" onclick="saveRate()">Guardar Cambio</button>
            </div>
        </div>
    </div>
</div>



<div class="modal fade" id="modalClearCart" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                <h5 class="fw-bold">¿Limpiar carrito?</h5>
                <p class="text-muted mb-0">Se perderán los items seleccionados.</p>
            </div>
            <div class="modal-footer bg-light justify-content-center">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">No</button>
                <button type="button" class="btn btn-outline-danger px-4" onclick="executeClearCart()">Sí, limpiar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCheckout" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-cash-register me-2"></i> Procesar Venta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" id="btnCloseCheckout"></button>
            </div>
            
            <div id="checkoutStateConfirm">
                <div class="modal-body bg-light">
                    <ul class="list-group list-group-flush mb-3 shadow-sm rounded">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Total Items:</span>
                            <span class="badge bg-primary rounded-pill fs-6" id="checkoutItems">0</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Método:</span>
                            <span class="fw-bold text-success" id="checkoutMethod"></span>
                        </li>
                    </ul>
                    <div class="text-center p-4 rounded border shadow-sm">
                        <h1 class="text-success fw-bold mb-1" id="checkoutTotalBs">Bs 0.00</h1>
                        <span class="badge bg-secondary fs-6" id="checkoutTotalUsd">$0.00</span>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="button" class="btn btn-outline-success fw-bold px-4" onclick="executeSale()">
                        <i class="fas fa-check-circle me-1"></i> Confirmar Pago
                    </button>
                </div>
            </div>

            <div id="checkoutStateResult" style="display:none;" class="text-center py-5">
                <div id="checkoutSpinner">
                    <div class="spinner-border text-success" style="width: 3rem; height: 3rem;" role="status"></div>
                    <p class="mt-3 text-muted fw-bold">Procesando transacción...</p>
                </div>
                <div id="checkoutSuccess" style="display:none;">
                    <i class="fas fa-check-circle fa-5x text-success mb-3"></i>
                    <h3 class="fw-bold text-success">¡Venta Exitosa!</h3>
                    <p class="text-muted">Ticket #<span id="ticketId" class="fw-bold text-dark"></span> generado.</p>
                    <button class="btn btn-outline-success mt-3" data-bs-dismiss="modal" onclick="window.location.reload()"><i class="fas fa-redo me-1"></i> Nueva Venta</button>
                </div>
                <div id="checkoutError" style="display:none;">
                    <i class="fas fa-times-circle fa-5x text-danger mb-3"></i>
                    <h3 class="fw-bold text-danger">Error</h3>
                    <p class="text-muted" id="checkoutErrorMessage"></p>
                    <button class="btn btn-outline-secondary mt-3" onclick="resetCheckoutModal()">Intentar de nuevo</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMessage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i id="msgIcon" class="fas fa-info-circle fa-3x mb-3 text-warning"></i>
                <h5 id="msgText" class="mb-0 fw-bold"></h5>
            </div>
            <div class="modal-footer bg-light justify-content-center">
                 <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDefault" tabindex="-1" aria-labelledby="modalDefaultLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalDefaultLabel">
                    <i class="fas fa-chart-line me-2"></i>Resumen de Ventas - Hoy
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light">
                            <small class="text-muted d-block text-uppercase fw-bold">Total USD</small>
                            <h4 class="text-success fw-bold mb-0">$0.00</h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light">
                            <small class="text-muted d-block text-uppercase fw-bold">Total BS</small>
                            <h4 class="text-primary fw-bold mb-0">Bs. 0.00</h4>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="list-group list-group-flush small">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-money-bill-wave me-2 text-muted"></i>Efectivo</span>
                        <span class="fw-bold">$0.00</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-mobile-alt me-2 text-muted"></i>Pago Móvil</span>
                        <span class="fw-bold">$0.00</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-credit-card me-2 text-muted"></i>Punto de Venta</span>
                        <span class="fw-bold">$0.00</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cerrar Reporte</button>
            </div>
        </div>
    </div>
</div>```

## Archivo: ./public/layouts/modals/modals_users.php
```php
<div class="modal fade" id="modalUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-user-plus me-2"></i> Nuevo Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="saveUser(event)">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="userAction" value="create">
                    <input type="hidden" name="id" id="userId">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre de Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-at"></i></span>
                            <input type="text" name="username" id="username" class="form-control" required placeholder="ej: adolfo_dev">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre Completo</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Contraseña</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="••••••••">
                            <div id="pwHelp" class="form-text small" style="display:none;">Dejar en blanco para mantener actual.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Rol de Acceso</label>
                            <select name="role" id="role" class="form-select">
                                <option value="seller">Vendedor</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>```

## Archivo: ./public/layouts/navbar.php
```php
<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>
        
        <ul class="navbar-nav ms-auto">
            <li class="nav-item">
                <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                    <i data-lte-icon="maximize" class="fas fa-expand-arrows-alt"></i>
                    <i data-lte-icon="minimize" class="fas fa-compress-arrows-alt" style="display: none"></i>
                </a>
            </li>
            
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="bg-primary bg-opacity-25 text-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <span class="d-none d-md-inline ms-2">
                        <?= htmlspecialchars($_SESSION['username'] ?? 'Usuario') ?>
                    </span>
                </a>
                
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <li class="user-header text-bg-primary">
                        <i class="fas fa-user-circle fa-4x mb-2 text-light"></i>
                        <p>
                            <span class="fw-bold"><?= htmlspecialchars($_SESSION['username'] ?? 'Usuario') ?></span> 
                            - <?= htmlspecialchars($_SESSION['role'] ?? 'Administrador') ?>
                            
                            <small class="mt-1">
                                <i class="fas fa-store me-1"></i> 
                                <?= htmlspecialchars($tenant_name ?? 'Mi Tienda') ?>
                            </small>
                        </p>
                    </li>
                    <li class="user-footer">
                        <a href="configuration.php" class="btn btn-default btn-flat"><i class="fas fa-cog me-1"></i> Ajustes</a>
                        <a href="logout.php" class="btn btn-danger btn-flat float-end"><i class="fas fa-sign-out-alt me-1"></i> Cerrar Sesión</a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>```

## Archivo: ./public/layouts/navbar_landing.php
```php
<nav class="navbar navbar-expand-lg navbar-light sticky-top navbar-landing py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <i class="bi bi-box-seam-fill text-primary me-2 fs-3"></i>
            <span class="fw-bold fs-4">MultiPOS</span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link px-3" href="#features">Características</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" href="#solutions">Soluciones</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" href="#pricing">Precios</a>
                </li>
                <li class="nav-item ms-lg-3">
                    <a class="btn btn-outline-primary px-4 me-2" href="login.php">Iniciar Sesión</a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-primary-custom text-white shadow-sm" href="registro.php">Probar Gratis</a>
                </li>
            </ul>
        </div>
    </div>
</nav>```

## Archivo: ./public/layouts/sidebar.php
```php
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
  <div class="sidebar-brand">
    <a href="./admin.php" class="brand-link">
      <img src="./assets/img/AdminLTELogo.png" alt="Logo" class="brand-image opacity-75 shadow" />
      <span class="brand-text fw-light"><?= htmlspecialchars($tenant_name) ?></span>
    </a>
  </div>
  <div class="sidebar-wrapper d-flex flex-column">
    <nav class="mt-2 flex-grow-1">
      <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="Main navigation">
        
        <li class="nav-header text-primary fw-bold">
          <i class="bi bi-shop me-2"></i> ADMIN
        </li>
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo ($pagina_actual == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="nav-icon bi bi-circle"></i>
                <p>Dashboard</p>
             </a>
        </li>

        <li class="nav-item">
          <a href="pos.php" class="nav-link <?php echo ($pagina_actual == 'pos.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-cash-stack"></i>
            <p>Venta</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="admin.php" class="nav-link <?php echo ($pagina_actual == 'admin.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-box-seam"></i>
            <p>Inventario</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="categories.php" class="nav-link <?php echo ($pagina_actual == 'categories.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-tags"></i>
            <p>Categorías</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="sales.php" class="nav-link <?php echo ($pagina_actual == 'sales.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-wallet2"></i>
            <p>Flujo de Caja</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="sales_history.php" class="nav-link <?php echo ($pagina_actual == 'sales_history.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-clock-history"></i>
            <p>Historial</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="users.php" class="nav-link <?php echo ($pagina_actual == 'users.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-people"></i>
            <p>Usuarios</p>
          </a>
        </li>
      </ul>
    </nav>

    <div class="sidebar-footer border-top border-secondary pt-2 pb-2">
      <ul class="nav sidebar-menu flex-column">
        <li class="nav-item">
          <a href="configuration.php" class="nav-link <?php echo ($pagina_actual == 'configuration.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-gear"></i>
            <p>Configuración</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="logout.php" class="nav-link text-danger">
            <i class="nav-icon bi bi-box-arrow-right"></i>
            <p>Salir</p>
          </a>
        </li>
      </ul>
    </div>
  </div>
  </aside>```

## Archivo: ./public/login.php
```php
<?php
// 1. Cargas la lógica (El Controlador)
require_once '../controllers/AuthController.php';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Iniciar Sesión - Sistema POS</title>

  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
  <meta name="color-scheme" content="light dark" />
  <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
  <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
  <meta name="title" content="Iniciar Sesión - Sistema POS" />
  <meta name="author" content="ColorlibHQ" />
  <meta name="keywords" content="adminlte, pos, login" />
  <link rel="preload" href="css/adminlte.css" as="style" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="css/adminlte.css" />
  <link rel="stylesheet" href="./css/custom.css" />
</head>

<body class="login-page bg-body-secondary">
  
  <div class="login-box">
    <div class="card card-outline card-primary">
      <div class="card-header">
        <a href="#" class="link-dark text-center link-offset-2 link-opacity-100 link-opacity-50-hover">
          <h1 class="mb-0"><b>Sistema POS</b>v2.0</h1>
        </a>
      </div>
      <div class="card-body login-card-body">
        <p class="login-box-msg">Identifícate para iniciar sesión</p>
        <?php if ($error): ?>
          <div class="alert alert-danger" role="alert">
            <?= $error ?>
          </div>
        <?php endif; ?>
        <form  method="post">
          <div class="input-group mb-3">
            <div class="form-floating">
              <input id="username" name="username" type="text" class="form-control" placeholder="Usuario" required />
              <label for="username">Usuario</label>
            </div>
            <div class="input-group-text">
              <span class="bi bi-person-square"></span>
            </div>
          </div>

          <div class="input-group mb-3">
            <div class="form-floating">
              <input id="loginPassword" name="password" type="password" class="form-control" placeholder="Password" required />
              <label for="loginPassword">Contraseña</label>
            </div>
            <div class="input-group-text">
              <span class="bi bi-lock-fill"></span>
            </div>
          </div>

          <div class="input-group mb-3">
            <div class="form-floating">
              <select id="role" name="role" class="form-select" required>
                <option value="" selected disabled>Seleccione rol...</option>
                <option value="admin">Administrador</option>
                <option value="seller">Vendedor</option>
              </select>
              <label for="role">Tipo de Usuario</label>
            </div>
            <div class="input-group-text">
              <span class="bi bi-shield-lock"></span>
            </div>
          </div>

          <div class="row">
            <div class="col-8 d-inline-flex align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="flexCheckDefault" />
                <label class="form-check-label" for="flexCheckDefault"> Recordarme </label>
              </div>
            </div>
            <div class="col-4">
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Ingresar</button>
              </div>
            </div>
          </div>
        </form>

        <p class="mb-1 mt-3">
          <a href="forgot-password.html">Olvidé mi contraseña</a>
        </p>
        <p class="mb-0">
          <a href="register.html" class="text-center"> Registrar nueva cuenta </a>
        </p>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
  <script src="js/adminlte.js"></script>
  <script src="js/login.js"></script>
</body>
</html>```

## Archivo: ./public/logout.php
```php
<?php
require_once '../config/db.php';
require_once '../includes/Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->logout();
?>```

## Archivo: ./public/pos.php
```php
<?php
require_once '../controllers/PosController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
    <main class="app-main">
        <?= render_content_header($headerConfig) ?>
        <div class="app-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-3 shadow-sm">
                            <div class="card-body p-2">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" id="searchInput" class="form-control border-0 bg-light" placeholder="Buscar producto (Presiona F3)...">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3" id="productsGrid">
                            <?php foreach($products as $p): 
                                $price_usd = $p['price_base_usd'] * (1 + ($p['profit_margin'] / 100));
                                $price_bs  = $price_usd * $bcvRate;
                                $is_stock  = $p['stock'] > 0;
                                $has_image = !empty($p['image']); 
                                $img_url = $has_image ? htmlspecialchars($p['image']) : '';
                                $desc = !empty($p['description']) ? htmlspecialchars($p['description']) : 'Sin descripción';
                                $sku = !empty($p['sku']) ? htmlspecialchars($p['sku']) : 'N/A';
                                $categoria = !empty($p['category']) ? htmlspecialchars($p['category']) : 'N/A';
                                $brand = !empty($p['brand']) ? htmlspecialchars($p['brand']) : 'N/A';
                            ?>
                            <div class="col-6 col-md-4 col-xl-3 product-item" 
                                 data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>" 
                                 data-desc="<?= htmlspecialchars(strtolower($desc)) ?>" 
                                 data-sku="<?= htmlspecialchars(strtolower($sku)) ?>" 
                                 data-category="<?= htmlspecialchars(strtolower($categoria)) ?>" 
                                 data-brand="<?= htmlspecialchars(strtolower($brand)) ?>" 
                                 data-price="<?= $price_usd ?>">
                            <div class="card h-100 shadow-sm border" style="cursor: pointer; transition: transform 0.2s;">
                                <div onclick='addToCart(<?= $p['id'] ?>, <?= json_encode($p['name']) ?>, <?= $price_usd ?>, <?= $p['stock'] ?>)' class="d-flex flex-column h-100">
                                    <div class="text-center bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center" style="height: 120px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                        <?php if($has_image): ?>
                                            <img src="uploads/<?= $img_url ?>" class="img-fluid" style="max-height: 100%; object-fit: contain;" alt="<?= htmlspecialchars($p['name']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-box-open fa-3x text-secondary opacity-50"></i>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-body p-2 d-flex flex-column text-center">
                                        <h6 class="card-title text-truncate fw-bold mb-1 w-100" title="<?= htmlspecialchars($p['name']) ?>">
                                            <?= htmlspecialchars($p['name']) ?>
                                        </h6>
                                        <small class="text-muted text-truncate w-100 mb-1"><?= $desc ?></small>
            
                                        <div class="d-flex flex-wrap justify-content-center gap-1 mb-2" style="font-size: 0.70rem;">
                                            <span class="badge bg-light text-secondary border" title="SKU"><i class="fas fa-barcode me-1"></i><?= $sku ?></span>
                                            <span class="badge bg-light text-secondary border" title="Categoría"><i class="fas fa-tags me-1"></i><?= $categoria ?></span>
                                            <span class="badge bg-light text-secondary border" title="Marca"><i class="fas fa-industry me-1"></i><?= $brand ?></span>
                                        </div>
                                        <div class="mt-auto">
                                            <div class="text-success fw-bold fs-5">$<?= number_format($price_usd, 2) ?></div>
                                            <div class="text-muted small mb-2">Bs <?= number_format($price_bs, 2) ?></div>
                                            
                                            <?php if($is_stock): ?>
                                                <span class="badge text-bg-info rounded-pill">Stock: <?= $p['stock'] ?></span>
                                            <?php else: ?>
                                                <span class="badge text-bg-danger rounded-pill">Agotado</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-lg-4 mt-4 mt-lg-0">
                        <div class="card card-outline card-success shadow-sm sticky-top" style="top: 70px; z-index: 1000;" id="posPanel">
                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center py-2">
                                <h5 class="card-title mb-0 fw-bold"><i class="fas fa-shopping-cart me-2"></i>Ticket Actual</h5>
                                <span class="badge bg-light text-success fs-6 rounded-pill" id="itemCount">0</span>
                            </div>

                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                    <table class="table table-hover table-striped align-middle mb-0 small">
                                        <thead class="table sticky-top">
                                            <tr>
                                                <th width="15%" class="text-center">Cant</th>
                                                <th>Item</th>
                                                <th class="text-end">Total</th>
                                                <th width="10%"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="cartTableBody">
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted">
                                                    <i class="fas fa-cart-arrow-down fa-2x mb-2 opacity-50"></i><br>El carrito está vacío
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="card-footer bg-light">
                                <div class="d-flex justify-content-between mb-1 text-secondary">
                                    <span>Subtotal USD:</span>
                                    <span class="fw-bold text-dark" id="totalUsdDisplay">$0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                                    <span class="h5 text-success mb-0">Total BS:</span>
                                    <span class="h5 text-success fw-bold mb-0" id="totalBsDisplay">Bs 0.00</span>
                                </div>

                                <label class="small fw-bold text-secondary mb-1">Método de Pago</label>
                                <select class="form-select mb-3 border-success" id="paymentMethod">
                                    <option value="efectivo_bs">💵 Efectivo Bolívares</option>
                                    <option value="efectivo_usd">🇺🇸 Efectivo Divisa</option>
                                    <option value="pago_movil">📱 Pago Móvil</option>
                                    <option value="punto">💳 Punto de Venta</option>
                                </select>

                                <div class="row g-2">
                                    <div class="col-9">
                                        <button class="btn btn-outline-success w-100 fw-bold btn-lg shadow-sm" onclick="initiateCheckout()">
                                            <i class="fas fa-check-circle me-1"></i> COBRAR
                                        </button>
                                    </div>
                                    <div class="col-3">
                                        
                                        <button class="btn btn-outline-danger w-100 btn-lg shadow-sm" onclick="confirmClearCart()" title="Limpiar carrito">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> 
            </div>
        </div>
    </main>
    <?php
    include 'layouts/footer.php'; 
    include 'layouts/modals/modals_pos.php';
    ?>
<script>
        window.APP_BCV_RATE = <?= $bcvRate ?>;
</script>
<script src="js/pos.js"></script>
</body>
</html>```

## Archivo: ./public/process_sale.php
```php
<?php
// public/process_sale.php

// 1. INICIAR BUFFER DE SALIDA
// Esto captura cualquier HTML, espacio en blanco o Warning accidental antes de enviar el JSON.
ob_start();

// Configurar cabecera JSON inmediatamente
header('Content-Type: application/json');

try {
    // Iniciar sesión si no está iniciada
    if (session_status() === PHP_SESSION_NONE) session_start();

    // 2. VALIDACIÓN MANUAL DE SESIÓN (Para evitar redirecciones HTML)
    // En lugar de usar Middleware::checkAuth() que podría redirigir a HTML,
    // validamos aquí y lanzamos una excepción JSON si falla.
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
        throw new Exception("La sesión ha expirado. Por favor, recarga la página e inicia sesión nuevamente.");
    }

    // Incluir archivos necesarios
    // Usamos __DIR__ para asegurar rutas absolutas y evitar errores de "File not found"
    $baseDir = __DIR__ . '/../';
    
    if (!file_exists($baseDir . 'config/db.php')) throw new Exception("Error interno: Falta config/db.php");
    require_once $baseDir . 'config/db.php';
    
    if (!file_exists($baseDir . 'includes/Sale.php')) throw new Exception("Error interno: Falta includes/Sale.php");
    require_once $baseDir . 'includes/Sale.php';
    
    if (!file_exists($baseDir . 'includes/ExchangeRate.php')) throw new Exception("Error interno: Falta includes/ExchangeRate.php");
    require_once $baseDir . 'includes/ExchangeRate.php';

    // Validación de Método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido.");
    }

    // Validar datos de entrada
    if (!isset($_POST['cart']) || empty($_POST['cart'])) {
        throw new Exception("El carrito está vacío o los datos no llegaron correctamente.");
    }

    // 3. CONEXIÓN A BASE DE DATOS
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Error de conexión a la base de datos.");
    }

    // 4. OBTENER DATOS DE CONTEXTO
    $tenant_id = $_SESSION['tenant_id'];
    $user_id = $_SESSION['user_id'];
    
    // Obtener Tasa BCV
    $rateObj = new ExchangeRate($db);
    $current_rate = $rateObj->getSystemRate();

    // 5. PROCESAR VENTA
    $saleObj = new Sale($db, $tenant_id, $user_id);
    
    $cart = $_POST['cart']; 
    $payment_method = $_POST['payment_method'] ?? 'efectivo_bs';

    // Ejecutar la lógica de negocio
    $result = $saleObj->createSale($cart, $payment_method, $current_rate);

    // Si llegamos aquí, todo salió bien.
    // Limpiamos el buffer por si hubo algún warning silencioso de PHP
    ob_clean();
    echo json_encode($result);

} catch (Throwable $e) {
    // 6. CAPTURA DE ERRORES FATALES
    // Si algo falla (incluso un error de sintaxis en los includes), cae aquí.
    
    ob_clean(); // Borramos cualquier HTML de error generado por PHP
    
    // Devolvemos el error en formato JSON para que el Modal lo muestre
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}
?>```

## Archivo: ./public/sales.php
```php
<?php
require_once '../controllers/SaleController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
    <main class="app-main">
        <?= render_content_header($headerConfig) ?>
        <div class="app-content">
            <div class="container-fluid">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="text-uppercase small fw-bold text-secondary mb-0">Resumen Financiero</h5>
                    <div class="btn-group shadow-sm">
                        <a href="?period=day" class="btn btn-sm btn-outline-primary <?= $period=='day'?'active':'' ?>">Hoy</a>
                        <a href="?period=week" class="btn btn-sm btn-outline-primary <?= $period=='week'?'active':'' ?>">Semana</a>
                        <a href="?period=month" class="btn btn-sm btn-outline-primary <?= $period=='month'?'active':'' ?>">Mes</a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 col-12">
                        <div class="small-box text-bg-success shadow-sm">
                            <div class="inner">
                                <p class="mb-0 opacity-75">Ingresos Totales (USD)</p>
                                <h3>$ <?= number_format($grandTotalUsd, 2) ?></h3>
                            </div>
                            <div class="small-box-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="small-box-footer py-2">
                                <i class="fas fa-chart-line"></i> Datos del período actual
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-12">
                        <div class="small-box text-bg-warning shadow-sm">
                            <div class="inner text-white">
                                <p class="mb-0 opacity-75">Ingresos Totales (BS)</p>
                                <h3 class="text-white">Bs <?= number_format($grandTotalBs, 2) ?></h3>
                            </div>
                            <div class="small-box-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="small-box-footer py-2 text-white">
                                <i class="fas fa-exchange-alt"></i> Según tasa BCV configurada
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card card-outline card-primary shadow-sm h-100">
                            <div class="card-header border-0">
                                <div class="d-flex justify-content-between">
                                    <h3 class="card-title fw-bold">Ventas en el Tiempo (USD)</h3>
                                    <i class="fas fa-history text-muted"></i>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="position-relative mb-4" style="height: 300px;">
                                    <canvas id="salesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card card-outline card-info shadow-sm h-100">
                            <div class="card-header border-0">
                                <h3 class="card-title fw-bold">Por Método de Pago</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="ps-3 text-secondary small text-uppercase">Método</th>
                                                <th class="text-end pe-3 text-secondary small text-uppercase">Total USD</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($stats)): ?>
                                                <tr>
                                                    <td colspan="2" class="text-center text-muted py-5">
                                                        <i class="fas fa-receipt d-block mb-2 opacity-50"></i> Sin datos
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($stats as $row): ?>
                                                <tr>
                                                    <td class="ps-3 text-capitalize">
                                                        <span class="text-primary me-2">•</span>
                                                        <?= str_replace('_', ' ', $row['payment_method']) ?>
                                                    </td>
                                                    <td class="text-end pe-3 fw-bold text-success">
                                                        $ <?= number_format($row['total_usd'], 2) ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent text-center">
                                <small class="text-muted">Desglose de transacciones finalizadas</small>
                            </div>
                        </div>
                    </div>
                </div> </div> </div> </main>

<?php include 'layouts/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php
// Preparamos los datos en PHP
$labels = !empty($chartData) ? array_column($chartData, 'sale_date') : [];
$dataPoints = !empty($chartData) ? array_column($chartData, 'total') : [];
?>

<script>
    // Pasamos los datos a variables globales de JS
    const chartLabels = <?= json_encode($labels) ?>;
    const chartValues = <?= json_encode($dataPoints) ?>;
</script>
<script src="js/sales.js"></script>
</body>
</html>```

## Archivo: ./public/sales_history.php
```php
<?php
require_once '../controllers/SalesHistoryController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
    <main class="app-main">
        <?= render_content_header($headerConfig) ?>
        <div class="app-content">
            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm h-100 border-0 border-start border-4 border-success">
                            <div class="card-body">
                                <p class="mb-1 opacity-75"><i class="fas fa-dollar-sign me-1"></i> Recaudo Total en USD</p>
                                <h3 class="mb-0">$ <?= number_format($totalDiaUsd ?? 0, 2) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm h-100 border-0 border-start border-4 border-warning">
                            <div class="card-body">
                                <p class="mb-1 text-secondary"><i class="fas fa-money-bill-wave me-1"></i> Equivalencia en Bs</p>
                                <h3 class="mb-0">Bs. <?= number_format($totalDiaBs ?? 0, 2) ?></h3>
                                <small class="text-muted">Tasa referencial</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm h-100 border-0 border-start border-4 border-info">
                            <div class="card-body py-2">
                                <p class="mb-1 fw-bold text-muted small">TICKET POR MÉTODO DE PAGO</p>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small"><i class="fas fa-cash-register text-success me-1"></i> Efectivo:</span>
                                    <span class="fw-bold"><?= $ticketsEfectivo ?? 0 ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small"><i class="fas fa-credit-card text-primary me-1"></i> Punto/Tarjeta:</span>
                                    <span class="fw-bold"><?= $ticketsPunto ?? 0 ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small"><i class="fas fa-mobile-alt text-info me-1"></i> Pago Móvil:</span>
                                    <span class="fw-bold"><?= $ticketsPMovil ?? 0 ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-outline card-primary shadow-sm">
                    
                    <div class="card-header border-0 pb-0">
                        <div class="row gy-3 align-items-center">
                            <div class="col-12 col-xl-6">
                                <div class="btn-group shadow-sm w-100 w-md-auto overflow-auto">
                                    <a href="?filter=all" class="btn btn-sm btn-outline-primary <?= $filter=='all'?'active':'' ?>">Todos</a>
                                    <a href="?filter=today" class="btn btn-sm btn-outline-primary <?= $filter=='today'?'active':'' ?>">Hoy</a>
                                    <a href="?filter=7days" class="btn btn-sm btn-outline-primary <?= $filter=='7days'?'active':'' ?>">Últimos 7 días</a>
                                    <a href="?filter=30days" class="btn btn-sm btn-outline-primary <?= $filter=='30days'?'active':'' ?>">Últimos 30 días</a>
                                    <a href="?filter=custom" class="btn btn-sm btn-outline-primary <?= $filter=='custom'?'active':'' ?>"><i class="fas fa-calendar-alt"></i> Personalizado</a>
                                </div>
                            </div>
                            
                            <div class="col-12 col-xl-6 d-flex justify-content-xl-end gap-2">
                                <div class="input-group input-group-sm" style="max-width: 300px;">
                                    <span class="input-group-text bg-transparent text-muted"><i class="fas fa-search"></i></span>
                                    <input type="text" id="tableSearch" class="form-control" placeholder="Buscar ticket, cliente o cajero...">
                                </div>
                                <button class="btn btn-outline-sm btn-outline-success shadow-sm" id="btnExportExcel">
                                    <i class="fas fa-file-excel me-1"></i> Exportar a Excel
                                </button>
                            </div>
                        </div>
                    </div>

                    <hr class="mx-3 mt-3 mb-0">

                    <div class="card-body p-0 mt-2">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0 text-center">
                                <thead class="table-transparent">
                                    <tr>
                                        <th class="ps-4 text-start">Ticket</th>
                                        <th>Fecha / Hora</th>
                                        <th>Productos (Cant/Unidad)</th>
                                        <th>Total USD</th>
                                        <th>Total BS</th>
                                        <th class="text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="historyBody">
                                    <?php if(!empty($sales)): ?>
                                        <?php foreach($sales as $s): ?>
                                        <tr class="<?= $s['status'] === 'anulada' ? 'table-danger opacity-75' : '' ?>">
                                            <td class="ps-4 text-start fw-bold text-primary">
                                                #<?= $s['id'] ?>
                                                <?= $s['status'] === 'anulada' ? '<span class="badge bg-danger small">ANULADA</span>' : '' ?>
                                        </td>
                                            <td><small><?= date('d/m/Y', strtotime($s['created_at'])) ?><br><span class="text-muted"><?= date('h:i A', strtotime($s['created_at'])) ?></span></small></td>
                                            
                                            <td><small class="text-muted"><?= htmlspecialchars($s['products_summary'] ?? '3 Ítems') ?></small></td>
                                            
                                            <td class="fw-bold text-success">$ <?= number_format($s['total_amount_usd'], 2) ?></td>
                                            <td class="fw-bold">Bs. <?= number_format($s['total_amount_bs'] ?? 0, 2) ?></td>
                                            
                                            <td class="text-end pe-4">
                                                <div class="btn-group shadow-sm">
                                                    <a href="ticket.php?id=<?= $s['id'] ?>" target="_blank"  class="btn btn-sm btn-outline-secondary me-1" title="Ver Ticket">
                                                        <i class="fas fa-receipt text-secondary"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-info me-1" data-bs-toggle="modal" data-bs-target="#modalView" onclick="loadSaleDetails(<?= $s['id'] ?>)" title="Ver Detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="printTicket(<?= $s['id'] ?>)" title="Imprimir">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <?php if($s['status'] !== 'anulada'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger me-1" onclick="cancelSale(<?= $s['id'] ?>)" title="Anular Venta">
                                                        <i class="fas fa-times-circle"></i>
                                                    </button>
                                                     <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>No hay ventas registradas en este período.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="modalView" tabindex="-1" aria-labelledby="modalViewLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="modalViewLabel"><i class="fas fa-list text-primary me-2"></i>Detalles de la Venta <span id="modalTicketNumber" class="fw-bold"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalViewContent">
                    <div class="text-center py-4 text-muted">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando detalles...
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalConfirmAnular" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirmar Anulación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p class="fs-5 mb-0">¿Estás completamente seguro de que deseas <strong>ANULAR</strong> la venta <span id="spanTicketAnular" class="text-danger fw-bold"></span>?</p>
                <p class="text-muted small mt-2">Esta acción no se puede deshacer y revertirá el stock/totales.</p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarAnular">Sí, Anular Venta</button>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>
    <script src="js/sales_history.js"></script>
</body>
</html>```

## Archivo: ./public/search_products.php
```php
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
}```

## Archivo: ./public/ticket.php
```php
<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Sale.php';

Middleware::checkAuth();

if (!isset($_GET['id'])) die("ID Requerido");

$db = (new Database())->getConnection();
// Pasamos el tenant_id de sesión para asegurar que solo busque ventas propias
$saleObj = new Sale($db, $_SESSION['tenant_id'], $_SESSION['user_id']);

$sale = $saleObj->getSaleHeader($_GET['id']);
// echo "<pre>"; print_r($sale); echo "</pre>";  Debug: Ver datos de la venta
$items = $saleObj->getSaleItems($_GET['id']);

if (!$sale) {
    die("❌ Venta no encontrada o no tienes permiso para verla.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket #<?= $sale['id'] ?></title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; width: 300px; margin: 0 auto; color: #000; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .border-bottom { border-bottom: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; }
        .btn-print { display: block; width: 100%; padding: 10px; background: #000; color: #fff; text-decoration: none; text-align: center; margin-bottom: 10px; }
        @media print { .btn-print { display: none; } }
    </style>
</head>
<body>

<a href="#" onclick="window.print()" class="btn-print">🖨️ IMPRIMIR</a>

<div class="text-center">
    <h3 style="margin-bottom: 5px;"><?= strtoupper($sale['business_name']) ?></h3>
    <span>RIF: <?= $sale['rif'] ?></span><br>
    <span>Fecha: <?= date('d/m/Y h:i A', strtotime($sale['created_at'])) ?></span><br>
    <span>Ticket #: <?= str_pad($sale['id'], 6, "0", STR_PAD_LEFT) ?></span>
    <span>Dólar BCV: <?= $sale['exchange_rate'] ?></span><br>
</div>
<div class="text-center">
    <?php if (isset($sale['status']) && $sale['status'] === 'anulada'): ?>
        <div style="border: 2px solid #000; padding: 5px; margin-top: 5px; font-weight: bold; font-size: 16px;">
            *** VENTA ANULADA ***
        </div>
    <?php endif; ?>
</div>
<div class="border-bottom"></div>

<table>
    <thead>
        <tr>
            <th style="text-align:left">Cant</th>
            <th style="text-align:left">Desc</th>
            <th style="text-align:right">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($items as $item): ?>
        <tr>
            <td><?= $item['quantity'] ?></td>
            <td><?= substr($item['product_name'], 0, 30) ?></td>
            <td class="text-end">$<?= number_format($item['quantity'] * $item['price_at_moment_usd'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="border-bottom"></div>

<div class="text-end">
    <strong>TOTAL USD: $<?= number_format($sale['total_amount_usd'], 2) ?></strong><br>
    <strong>TOTAL BS: Bs <?= number_format($sale['total_amount_bs'], 2) ?></strong>
</div>

<div class="border-bottom"></div>

<div class="text-center">
    <small>Cajero: <?= $sale['username'] ?></small><br>
    <small><?= $sale['ticket_footer'] ?></small>
</div>

<script>
    // window.onload = function() { window.print(); } // Opcional: imprimir automático
</script>

</body>
</html>```

## Archivo: ./public/users.php
```php
<?php
require_once '../controllers/UserController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
    <main class="app-main">
        <?= render_content_header($headerConfig) ?>
        <div class="app-content">
            <div class="container-fluid">
                
                <div class="row mb-4">
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="info-box shadow-sm">
                            <span class="info-box-icon text-bg-primary shadow-sm">
                                <i class="fas fa-users"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text text-secondary small">Total Usuarios</span>
                                <span class="info-box-number h5 mb-0"><?= $totalUsers ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="info-box shadow-sm">
                            <span class="info-box-icon text-bg-warning shadow-sm">
                                <i class="fas fa-user-shield"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text text-secondary small">Administradores</span>
                                <span class="info-box-number h5 mb-0"><?= $adminCount ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-outline card-primary shadow-sm">
                    <div class="card-header border-0 p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title fw-bold"><i class="fas fa-list me-2"></i> Lista de Acceso</h3>
                            <div class="card-tools">
                                <div class="input-group input-group-sm" style="width: 250px;">
                                    <input type="text" id="userSearch" class="form-control" placeholder="Buscar usuario...">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-dark text-secondary small text-uppercase">
                                    <tr>
                                        <th class="ps-4">Usuario</th>
                                        <th>Nombre Completo</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th class="text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="userTableBody">
                                    <?php if(!empty($users)): ?>
                                        <?php foreach($users as $u): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-3 bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; border-radius: 50%;">
                                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                                    </div>
                                                    <span class="fw-bold"><?= htmlspecialchars($u['username']) ?></span>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($u['full_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php if($u['role'] == 'admin'): ?>
                                                    <span class="badge text-bg-warning"><i class="fas fa-shield-alt me-1"></i> Admin</span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-info"><i class="fas fa-user me-1"></i> Vendedor</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill bg-success-subtle text-success border border-success">Activo</span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-warning me-1" onclick='editUser(<?= json_encode($u) ?>)' title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $u['id'] ?>)" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="fas fa-users fa-3x mb-3 opacity-25"></i>
                                                <p>No se encontraron usuarios registrados.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
    <?php
    include 'layouts/footer.php'; 
    include 'layouts/modals/modals_users.php';
    ?>
<script src="js/users.js"></script>
</body>
</html>```

## Archivo: ./root/actions.php
```php
<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['is_superadmin'])) { header("Location: login.php"); exit; }

$database = new Database();
$conn = $database->getConnection();

$action = $_POST['action'] ?? '';

try {
    // 1. CREAR NUEVA TIENDA
    if ($action === 'create_tenant') {
        $name = $_POST['name'];
        $rif = $_POST['rif'];
        $user = $_POST['admin_user'];
        $pass = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);
        $months = (int)$_POST['months'];

        // Generar datos
        $expiration = date('Y-m-d', strtotime("+$months months"));
        $license = strtoupper(substr(md5(uniqid()), 0, 10));

        $conn->beginTransaction();

        // Insertar Tienda
        $sql = "INSERT INTO tenants (business_name, rif, license_key, expiration_date, status) VALUES (?, ?, ?, ?, 'active')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $rif, $license, $expiration]);
        $tenantId = $conn->lastInsertId();

        // Insertar Usuario Admin para esa tienda
        $sqlUser = "INSERT INTO users (username, password, tenant_id) VALUES (?, ?, ?)";
        $stmtUser = $conn->prepare($sqlUser);
        $stmtUser->execute([$user, $pass, $tenantId]);

        $conn->commit();
    }

    // 2. RENOVAR LICENCIA
    if ($action === 'renew') {
        $id = $_POST['id'];
        $months = (int)$_POST['months'];
        $sql = "UPDATE tenants SET expiration_date = DATE_ADD(expiration_date, INTERVAL ? MONTH), status='active' WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$months, $id]);
    }

    // 3. CAMBIAR ESTADO
    if ($action === 'toggle_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $sql = "UPDATE tenants SET status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$status, $id]);
    }

    // 4. ACTUALIZAR BCV
    if ($action === 'update_bcv') {
        $rate = $_POST['rate'];
        $sql = "UPDATE system_settings SET bcv_rate=?, last_update=NOW() WHERE id=1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$rate]);
    }

    header("Location: panel.php?msg=success");

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    die("Error: " . $e->getMessage());
}
?>```

## Archivo: ./root/login.php
```php
<?php
session_start();
// CONFIGURACIÓN DE ACCESO MAESTRO
define('MASTER_USER', 'root');     // Cambia esto
define('MASTER_PASS', 'root');     // Cambia esto por una contraseña fuerte

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    if ($user === MASTER_USER && $pass === MASTER_PASS) {
        $_SESSION['is_superadmin'] = true;
        header("Location: panel.php");
        exit;
    } else {
        $error = "Credenciales incorrectas.";
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Dueño SaaS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
    <style>
        /* Ajuste sutil para centrar perfectamente en pantallas altas */
        .login-page {
            align-items: center;
            display: flex;
            flex-direction: column;
            height: 100vh;
            justify-content: center;
        }
    </style>
</head>
<body class="login-page bg-body-secondary">

    <div class="login-box">
        <div class="login-logo">
            <a href="#" class="text-decoration-none fw-bold text-light">
                <i class="fas fa-crown text-warning me-2"></i><b>Super</b>Admin
            </a>
        </div>

        <div class="card card-outline card-warning shadow-lg">
            <div class="card-body login-card-body p-4">
                <p class="login-box-msg text-secondary text-center mb-3">Ingresa tus credenciales maestras</p>

                <?php if($error): ?>
                    <div class="alert alert-danger p-2 small text-center shadow-sm">
                        <i class="fas fa-exclamation-circle me-1"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input type="text" name="user" id="userInput" class="form-control" placeholder="Usuario Maestro" required autofocus>
                            <label for="userInput">Usuario Maestro</label>
                        </div>
                        <div class="input-group-text bg-dark border-secondary">
                            <i class="fas fa-user-shield text-muted"></i>
                        </div>
                    </div>

                    <div class="input-group mb-4">
                        <div class="form-floating">
                            <input type="password" name="pass" id="passInput" class="form-control" placeholder="Contraseña" required>
                            <label for="passInput">Contraseña</label>
                        </div>
                        <div class="input-group-text bg-dark border-secondary">
                            <i class="fas fa-lock text-muted"></i>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning w-100 fw-bold shadow-sm">
                                <i class="fas fa-sign-in-alt me-2"></i> Entrar al Panel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>
</body>
</html>```

## Archivo: ./root/logout.php
```php
<?php
session_start();
session_destroy();
header("Location: login.php");
?>```

## Archivo: ./root/panel.php
```php
<?php
session_start();
require_once '../config/db.php';

// Seguridad: Solo el dueño puede entrar
if (!isset($_SESSION['is_superadmin'])) { header("Location: login.php"); exit; }

$database = new Database();
$conn = $database->getConnection();

// Obtener Tasa BCV Actual
$bcvQuery = $conn->query("SELECT bcv_rate FROM system_settings WHERE id=1");
$bcv = $bcvQuery->fetch(PDO::FETCH_ASSOC);

// Obtener Listado de Tiendas (Tenants)
$tenants = $conn->query("SELECT *, DATEDIFF(expiration_date, NOW()) as days_left FROM tenants ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Dueño | AdminLTE 4</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body">

<div class="app-wrapper">
    
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list"></i></a>
                </li>
            </ul>
            
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3">
                    <span class="text-warning">BCV: <strong><?= number_format($bcv['bcv_rate'], 2) ?> Bs</strong></span>
                </li>
                <li class="nav-item me-3">
                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalBCV">
                        <i class="bi bi-currency-exchange"></i> Cambiar Tasa
                    </button>
                </li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar bg-body-tertiary shadow">
        <div class="sidebar-brand">
            <a href="#" class="brand-link">
                <span class="brand-text fw-light">🛠️ Gestión SaaS</span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="#" class="nav-link active">
                            <i class="nav-icon bi bi-shop"></i>
                            <p>Tiendas Registradas</p>
                        </a>
                    </li>
                    <li class="nav-header">SESIÓN</li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link text-danger">
                            <i class="nav-icon bi bi-box-arrow-right"></i>
                            <p>Salir del Panel</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0">Gestión de Clientes</h3>
                    </div>
                    <div class="col-sm-6 text-end">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNewTenant">
                            <i class="bi bi-plus-lg"></i> Nueva Tienda
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                <div class="card card-outline card-primary shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title">Listado de Tiendas</h3>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center">ID</th>
                                    <th>Negocio</th>
                                    <th>Licencia</th>
                                    <th>Estado</th>
                                    <th>Vencimiento</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($tenants as $t): ?>
                                <tr>
                                    <td class="text-center"><?= $t['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($t['business_name']) ?></strong><br>
                                        <small class="text-secondary">RIF: <?= htmlspecialchars($t['rif']) ?></small>
                                    </td>
                                    <td><span class="badge text-bg-dark border font-monospace"><?= $t['license_key'] ?></span></td>
                                    <td>
                                        <?php if($t['status'] == 'active'): ?>
                                            <span class="badge text-bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-danger">Suspendido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $t['expiration_date'] ?><br>
                                        <?php if($t['days_left'] < 0): ?>
                                            <span class="text-danger fw-bold small"><i class="bi bi-exclamation-triangle"></i> Vencido</span>
                                        <?php else: ?>
                                            <span class="text-info small"><?= $t['days_left'] ?> días restantes</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#renew<?= $t['id'] ?>" title="Renovar">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        
                                        <form action="actions.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <?php if($t['status']=='active'): ?>
                                                <input type="hidden" name="status" value="suspended">
                                                <button class="btn btn-sm btn-outline-danger" title="Suspender"><i class="bi bi-slash-circle"></i></button>
                                            <?php else: ?>
                                                <input type="hidden" name="status" value="active">
                                                <button class="btn btn-sm btn-outline-success" title="Activar"><i class="bi bi-check-circle"></i></button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="renew<?= $t['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <form action="actions.php" method="POST" class="modal-content border-primary">
                                            <div class="modal-header bg-primary text-white border-bottom-0">
                                                <h5 class="modal-title">Renovar Suscripción</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="renew">
                                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                                <p>Tienda seleccionada: <strong class="text-primary"><?= $t['business_name'] ?></strong></p>
                                                <div class="mb-3">
                                                    <label class="form-label">Tiempo a agregar:</label>
                                                    <select name="months" class="form-select">
                                                        <option value="1">1 Mes</option>
                                                        <option value="6">6 Meses</option>
                                                        <option value="12">1 Año</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-top-0">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-primary">Aplicar Renovación</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<div class="modal fade" id="modalNewTenant" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="actions.php" method="POST" class="modal-content border-success">
            <div class="modal-header bg-success text-white border-bottom-0">
                <h5 class="modal-title"><i class="bi bi-shop me-2"></i>Registrar Nuevo Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="create_tenant">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre del Negocio</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">RIF</label>
                        <input type="text" name="rif" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Usuario Admin (Para la tienda)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="admin_user" class="form-control" placeholder="ej: admin" required>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="text" name="admin_pass" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Duración Inicial de la Licencia</label>
                    <select name="months" class="form-select">
                        <option value="1">1 Mes</option>
                        <option value="12">1 Año</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Crear y Activar Tienda</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalBCV" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="actions.php" method="POST" class="modal-content border-warning">
            <div class="modal-header bg-warning text-dark border-bottom-0">
                <h5 class="modal-title"><i class="bi bi-currency-exchange me-2"></i>Actualizar Tasa Global</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="update_bcv">
                <div class="mb-3">
                    <label class="form-label">Nueva Tasa (Bs/USD):</label>
                    <input type="number" step="0.01" name="rate" class="form-control form-control-lg text-center" value="<?= $bcv['bcv_rate'] ?>" required>
                </div>
                <div class="alert alert-info py-2 mb-0 border-0">
                    <small><i class="bi bi-info-circle me-1"></i> Esto actualizará los precios en BS de todas las tiendas de forma automática.</small>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning fw-bold text-dark">Guardar Tasa</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>

<script>
    // Inicializar OverlayScrollbars
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== "undefined") {
            OverlayScrollbarsGlobal.OverlayScrollbars(document.querySelector(".sidebar-wrapper"), {
                scrollbars: {
                    theme: "os-theme-light",
                    autoHide: "leave",
                    clickScroll: true,
                },
            });
        }
    });
</script>
</body>
</html>```

