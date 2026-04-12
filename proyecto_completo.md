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
?> ```

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
    'icon'   => 'bi bi-box-seam-fill',
    'colorico' => 'primary',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate,
    'button' => [
        'text'   => 'Nuevo Producto',
        'icon'   => 'fas fa-plus me-1',
        'target' => '#modalInsert',
        'class'  => 'btn btn-outline-primary mb-2 btn-sm text-start'
    ]
]; ```

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

?> ```

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
} ```

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
    'icon'   => 'bi bi-tags-fill',
    'colorico' => 'info',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate,
    'button' => [
        'text'   => 'Nueva Categoría',
        'icon'   => 'fas fa-plus me-1',
        'target' => '#modalInsert',
        'class'  => 'btn btn-outline-primary mb-2 btn-sm text-start'
    ]
];
?> ```

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
?> ```

## Archivo: ./controllers/CreditController.php
 ```php
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
?> ```

## Archivo: ./controllers/CustomerController.php
 ```php
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
?> ```

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
?> ```

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

// --- RESUMEN DE VENTAS (HOY) PARA EL MODAL ---
$sqlResumen = "SELECT payment_method, SUM(total_amount_usd) as total_usd, SUM(total_amount_bs) as total_bs 
               FROM sales 
               WHERE tenant_id = :tid AND DATE(created_at) = CURDATE() 
               GROUP BY payment_method";
$stmtResumen = $db->prepare($sqlResumen);
$stmtResumen->bindValue(':tid', $tenant_id, PDO::PARAM_INT);
$stmtResumen->execute();
$ventasHoyMetodos = $stmtResumen->fetchAll(PDO::FETCH_ASSOC);

// Inicializamos el array con los métodos disponibles para evitar errores de variables no definidas
$resumenTotales = [
    'usd' => 0,
    'bs' => 0,
    'efectivo_bs' => 0,
    'efectivo_usd' => 0,
    'pago_movil' => 0,
    'punto' => 0,
    'transferencia' => 0,
    'credito' => 0
];

// Asignamos los valores devueltos por la consulta
foreach($ventasHoyMetodos as $v) {
    $resumenTotales['usd'] += $v['total_usd'];
    $resumenTotales['bs'] += $v['total_bs'];
    
    $metodo = $v['payment_method'];
    if(isset($resumenTotales[$metodo])) {
        $resumenTotales[$metodo] += $v['total_usd'];
    }
}
$headerConfig = [
    'title'     => 'Punto de Venta (POS)',
    'colorico'  => 'success',
    'icon'      => 'bi bi-cart-plus-fill',
    'tenant'    => $tenant_name,
    'bcv'       => $bcvRate,
    'button'    => [
        'text'   => ' Ventas de Hoy: <span class="text-success fw-bold"> $' . number_format($sales ?? 0, 2) . '</span> / <span class="text-primary fw-bold">Bs.' . number_format(($sales * $bcvRate) ?? 0, 2) . '</span>',
        'icon'   => 'fas fa-coins me-1',
        'class'  => 'btn btn-outline-primary mb-2 btn-sm text-start',
        'target' => '#modalDefault' // Aseguramos el enlace con el modal
    ]
]; ```

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
    'icon'   => 'bi bi-graph-up-arrow',
    'colorico' => 'success',
    'tenant' => $tenant_name,
    'bcv'    => $bcvRate,
    'button' => [
        'text'   => 'Exportar Datos',
        'icon'   => 'fas fa-download me-1',
        'target' => '#modalExport',
        'class'  => 'btn btn-outline-secondary mb-2 btn-sm text-start'
    ]
];
?> ```

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
// Variables para el layout
$tenant_name = $_SESSION['tenant_name'] ?? 'Mi Negocio';
$pageTitle = "Ventas - " . $tenant_name;
$current_page = "Ventas";
$pagina_actual = basename($_SERVER['PHP_SELF']);

// Variables para las tarjetas de métricas
$totalDiaUsd = 0;
$totalDiaBs = 0;
$ticketsEfectivo = 0;
$ticketsPunto = 0;
$ticketsPMovil = 0;
$ticketsCredito = 0;
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
    } elseif (strpos($method, 'credito') !== false) {
        $ticketsCredito++;
    }
}

$headerConfig = [
    'title'     => 'Historial de Ventas',
    'colorico'  => 'warning',
    'icon'      => 'bi bi-receipt',
    'tenant'    => $tenant_name,
    'bcv'       => $bcvRate,
    
    ];

   ```

## Archivo: ./controllers/UserController.php
 ```php
<?php
// Archivo: ./controllers/UserController.php

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
    'icon'   => 'bi bi-people-fill',
    'colorico' => 'primary',
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
?> 
 ```

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
        // Hacemos un LEFT JOIN con products para contar cuántos tiene cada categoría
        $sql = "SELECT c.*, COUNT(p.id) as product_count 
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id AND p.tenant_id = c.tenant_id
                WHERE c.tenant_id = :tid 
                GROUP BY c.id
                ORDER BY c.name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($name, $description = null) {
        $sql = "INSERT INTO categories (tenant_id, name, description) VALUES (:tid, :n, :desc)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':tid' => $this->tenant_id, 
            ':n' => $name,
            ':desc' => $description
        ]);
    }

    public function update($id, $name, $description = null) {
        $sql = "UPDATE categories SET name = :n, description = :desc WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':n' => $name, 
            ':desc' => $description,
            ':id' => $id, 
            ':tid' => $this->tenant_id
        ]);
    }

    public function delete($id) {
        $sql = "DELETE FROM categories WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':tid' => $this->tenant_id]);
    }
}
?> ```

## Archivo: ./includes/Credit.php
 ```php
<?php
class Credit {
    private $conn;
    private $tenant_id;

    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    // Obtener todos los créditos pendientes o con saldo
    public function getPending() {
        $sql = "SELECT c.*, cust.name as customer_name, cust.document, s.created_at as sale_date 
                FROM credits c
                JOIN customers cust ON c.customer_id = cust.id
                JOIN sales s ON c.sale_id = s.id
                WHERE c.tenant_id = :tid AND c.status != 'cancelled'
                ORDER BY c.status ASC, c.due_date ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener historial de pagos de un crédito específico
    public function getPayments($credit_id) {
        $sql = "SELECT cp.*, u.username 
                FROM credit_payments cp
                LEFT JOIN users u ON cp.user_id = u.id
                WHERE cp.credit_id = :cid AND cp.tenant_id = :tid
                ORDER BY cp.created_at DESC";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':cid' => $credit_id, ':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Registrar un nuevo abono
    public function addPayment($credit_id, $user_id, $amount_usd, $exchange_rate, $method) {
        try {
            $this->conn->beginTransaction();

            // 1. Verificar el saldo actual y bloquear la fila para evitar cobros dobles simultáneos
            $stmtCheck = $this->conn->prepare("SELECT balance_usd FROM credits WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $stmtCheck->execute([$credit_id, $this->tenant_id]);
            $credit = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$credit) {
                throw new Exception("Crédito no encontrado.");
            }

            if ($amount_usd <= 0) {
                throw new Exception("El monto debe ser mayor a cero.");
            }

            if ($amount_usd > $credit['balance_usd']) {
                throw new Exception("El abono ($" . number_format($amount_usd, 2) . ") supera el saldo pendiente ($" . number_format($credit['balance_usd'], 2) . ").");
            }

            $amount_bs = $amount_usd * $exchange_rate;

            // 2. Insertar el pago
            $sqlPay = "INSERT INTO credit_payments (tenant_id, credit_id, user_id, amount_usd, amount_bs, exchange_rate, payment_method) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmtPay = $this->conn->prepare($sqlPay);
            $stmtPay->execute([$this->tenant_id, $credit_id, $user_id, $amount_usd, $amount_bs, $exchange_rate, $method]);

            // 3. Actualizar el saldo del crédito
            $new_balance = $credit['balance_usd'] - $amount_usd;
            $new_status = ($new_balance <= 0.00) ? 'paid' : 'pending';

            $sqlUpdate = "UPDATE credits SET balance_usd = ?, status = ? WHERE id = ? AND tenant_id = ?";
            $stmtUpdate = $this->conn->prepare($sqlUpdate);
            $stmtUpdate->execute([$new_balance, $new_status, $credit_id, $this->tenant_id]);

            $this->conn->commit();
            return ["status" => true, "message" => "Abono registrado exitosamente."];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ["status" => false, "message" => $e->getMessage()];
        }
    }
}
?> ```

## Archivo: ./includes/Customer.php
 ```php
<?php
class Customer {
    private $conn;
    private $tenant_id;

    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    public function getAll() {
        $sql = "SELECT * FROM customers WHERE tenant_id = :tid ORDER BY name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($name, $document, $phone) {
        $sql = "INSERT INTO customers (tenant_id, name, document, phone) VALUES (:tid, :n, :d, :p)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':tid' => $this->tenant_id,
            ':n' => $name,
            ':d' => $document,
            ':p' => $phone
        ]);
    }

    public function update($id, $name, $document, $phone) {
        $sql = "UPDATE customers SET name = :n, document = :d, phone = :p WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':n' => $name,
            ':d' => $document,
            ':p' => $phone,
            ':id' => $id,
            ':tid' => $this->tenant_id
        ]);
    }

    public function delete($id) {
        // Validar si el cliente tiene créditos o ventas asociadas antes de borrar (Opcional pero recomendado)
        $sql = "DELETE FROM customers WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':tid' => $this->tenant_id]);
    }
}
?> ```

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
?> ```

## Archivo: ./includes/helpers.php
 ```php
<?php
function render_content_header($config)
{
    // Valores por defecto para evitar errores
    $title       = $config['title'] ?? 'Panel';
    $colorico    = $config['colorico'] ?? 'primary';
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
                        <i class="<?= htmlspecialchars($icon) ?> text-<?= $colorico ?> me-2"></i>
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

function render_modal($config, $bodyContent)
{
    // Valores por defecto
    $id          = $config['id'] ?? 'defaultModal';
    $formId      = $config['form_id'] ?? null; // Si tiene form_id, envuelve en <form>, si no, en <div>
    $title       = $config['title'] ?? 'Modal';
    $icon        = $config['icon'] ?? 'fas fa-info-circle';
    $bg_color    = $config['bg_color'] ?? 'primary';
    $submit_text = $config['submit_text'] ?? 'Guardar';
    $submit_id   = $config['submit_id'] ?? 'btnSubmit';
    $size        = $config['size'] ?? ''; // ej: 'modal-lg', 'modal-sm'
    $custom_btn  = $config['custom_buttons'] ?? ''; // Para botones extra en el footer

    ob_start();
?>
    <div class="modal fade" id="<?= htmlspecialchars($id) ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered <?= htmlspecialchars($size) ?>">
            <<?= $formId ? "form id=\"".htmlspecialchars($formId)."\"" : "div" ?> class="modal-content shadow">
                <div class="modal-header bg-<?= htmlspecialchars($bg_color) ?> text-white">
                    <h5 class="modal-title" id="<?= htmlspecialchars($id) ?>Title">
                        <i class="<?= htmlspecialchars($icon) ?> me-2"></i> <?= htmlspecialchars($title) ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <?= $bodyContent ?>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <?= $custom_btn ?>
                    <?php if ($formId): ?>
                        <button type="submit" class="btn btn-<?= htmlspecialchars($bg_color) ?> px-4 fw-bold" id="<?= htmlspecialchars($submit_id) ?>">
                            <?= htmlspecialchars($submit_text) ?>
                        </button>
                    <?php endif; ?>
                </<?= $formId ? "form" : "div" ?>>
            </div>
        </div>
    </div>
<?php
    return ob_get_clean();
} ```

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
} ```

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
?> ```

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
?> ```

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
} ```

## Archivo: ./includes/Sale.php
 ```php
<?php
class Sale
{
    private $conn;
    private $tenant_id;
    private $user_id;

    public function __construct($db, $tenant_id, $user_id)
    {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
        $this->user_id = $user_id;
    }

    /**
     * Procesa la venta: Valida stock, calcula totales y registra en BD
     */
    public function createSale($cartItems, $payment_method, $current_exchange_rate, $customer_id = null, $due_date = null)
    {
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

            // Redondeamos el total USD a 2 decimales
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

            // --- LÓGICA DE CRÉDITO ---
            if ($payment_method === 'credito') {
                if (!$customer_id) {
                    throw new Exception("Debe seleccionar un cliente para las ventas a crédito.");
                }

                $sqlCredit = "INSERT INTO credits (tenant_id, sale_id, customer_id, total_amount_usd, balance_usd, due_date) 
                              VALUES (?, ?, ?, ?, ?, ?)";
                $stmtCredit = $this->conn->prepare($sqlCredit);
                // Aseguramos que el due_date sea null si viene vacío para evitar errores de base de datos
                $db_due_date = !empty($due_date) ? $due_date : null;
                $stmtCredit->execute([$this->tenant_id, $sale_id, $customer_id, $total_usd, $total_usd, $db_due_date]);
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

    public function getHistory($filter = 'today')
    {
        // Añadimos subconsultas y JOINs para traer la cantidad de ítems y el cliente (si es a crédito)
        $sql = "SELECT s.*, u.username,
                       (SELECT SUM(quantity) FROM sale_items WHERE sale_id = s.id) as total_items,
                       c.name as customer_name
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN credits cr ON s.id = cr.sale_id
                LEFT JOIN customers c ON cr.customer_id = c.id
                WHERE s.tenant_id = :tid";

        if ($filter == 'today') {
            $sql .= " AND DATE(s.created_at) = CURDATE()";
        } elseif ($filter == '7days') {
            $sql .= " AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        } elseif ($filter == '30days') {
            $sql .= " AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($filter == 'month') {
            $sql .= " AND MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())";
        }

        $sql .= " ORDER BY s.id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSaleHeader($sale_id)
    {
        $sql = "SELECT s.*, t.business_name, t.rif,t.ticket_footer, u.username, 
                       c.name as customer_name, c.document as customer_doc 
                FROM sales s
                JOIN tenants t ON s.tenant_id = t.id
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN credits cr ON s.id = cr.sale_id
                LEFT JOIN customers c ON cr.customer_id = c.id
                WHERE s.id = :id AND s.tenant_id = :tid";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $sale_id, ':tid' => $this->tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSaleItems($sale_id)
    {
        $sql = "SELECT si.*, p.name as product_name 
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                LEFT JOIN products p ON si.product_id = p.id
                WHERE si.sale_id = :id AND s.tenant_id = :tid";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $sale_id, ':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCashFlowStats($startDate, $endDate)
    {
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

    public function getSalesChartData($startDate, $endDate)
    {
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

    public function cancelSale($sale_id)
    {
        try {
            $this->conn->beginTransaction();

            // 1. Verificar existencia y estado actual de la venta
            $stmt = $this->conn->prepare("SELECT status, payment_method FROM sales WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $stmt->execute([$sale_id, $this->tenant_id]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) throw new Exception("Venta no encontrada.");
            if ($sale['status'] === 'anulada') throw new Exception("Esta venta ya fue anulada anteriormente.");

            // 2. Obtener los productos y devolver stock
            $items = $this->getSaleItems($sale_id);
            $sqlStock = "UPDATE products SET stock = stock + ? WHERE id = ? AND tenant_id = ?";
            $stmtStock = $this->conn->prepare($sqlStock);

            foreach ($items as $item) {
                $stmtStock->execute([$item['quantity'], $item['product_id'], $this->tenant_id]);
            }

            // 3. Cambiar el estado de la venta
            $stmtUpdate = $this->conn->prepare("UPDATE sales SET status = 'anulada' WHERE id = ? AND tenant_id = ?");
            $stmtUpdate->execute([$sale_id, $this->tenant_id]);

            // 4. Si fue un crédito, anular también el crédito asociado
            if ($sale['payment_method'] === 'credito') {
                $stmtCred = $this->conn->prepare("UPDATE credits SET status = 'cancelled' WHERE sale_id = ? AND tenant_id = ?");
                $stmtCred->execute([$sale_id, $this->tenant_id]);
            }

            $this->conn->commit();
            return ["status" => "success", "message" => "Venta anulada y stock restaurado correctamente."];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}
 ```

## Archivo: ./includes/User.php
 ```php
<?php
//Archivo: ./includes/User.php
class User {
    private $conn;
    private $tenant_id;

    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    public function getAll() {
        $sql = "SELECT id, username, full_name, role, created_at FROM users WHERE tenant_id = :tid ORDER BY role ASC, username ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    
    public function create($username,$password, $full_name, $role) {
        // Verificar duplicados
        $sqlCheck = "SELECT COUNT(*) FROM users WHERE username = :u AND tenant_id = :tid";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([':u' => $username, ':tid' => $this->tenant_id]);
        if($stmtCheck->fetchColumn() > 0) return ['status' => false, 'message' => 'El usuario ya existe'];

        // Hash seguro
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (tenant_id, username, password, full_name, role) VALUES (:tid, :u, :p, :fn, :r)";
        $stmt = $this->conn->prepare($sql);
        if($stmt->execute([':tid' => $this->tenant_id, ':u' => $username, ':p' => $hash, ':fn' => $full_name, ':r' => $role])){
            return ['status' => true];
        }
        return ['status' => false, 'message' => 'Error al insertar en BD'];
    }

    public function update($id, $username, $full_name, $role, $password = null) {
        $params = [':u' => $username, ':fn' => $full_name, ':r' => $role, ':id' => $id, ':tid' => $this->tenant_id];
        $sql = "UPDATE users SET username = :u, full_name = :fn, role = :r";

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
   
    public function delete($id)
    {
        if ($id == $_SESSION['user_id']) {
            return ['status' => false, 'message' => 'No puedes eliminar tu propia cuenta.'];
        }

        $sql = "DELETE FROM users WHERE id = :id AND tenant_id = :tid";
        $stmt = $this->conn->prepare($sql);

        if ($stmt->execute([':id' => $id, ':tid' => $this->tenant_id])) {
            return ['status' => true, 'message' => 'Usuario borrado con éxito.'];
        }
        return ['status' => false, 'message' => 'Error al eliminar de la base de datos.'];
    }
} ```

## Archivo: ./index.php
 ```php
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MultiPOS | Gestión Unificada para tu Negocio</title>
    <link rel="manifest" href="public/manifest.json">


    
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
                            <li class="mb-2"><i class="bi bi-check-circle-fill  "></i> Sincronizado en cualquier dispositivo.</li>
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
</html> ```

## Archivo: ./public/actions/actions_category.php
 ```php
<?php
require_once '../../config/db.php';
require_once '../../includes/Category.php';
require_once '../../includes/Middleware.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

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
    $desc   = trim($_POST['description'] ?? ''); // Capturamos la descripción

    $response = ['status' => false, 'message' => 'Acción no reconocida'];

    switch ($action) {
        case 'create':
            if (empty($name)) throw new Exception("El nombre es obligatorio.");
            if ($catObj->create($name, $desc)) {
                $response = ['status' => true, 'message' => 'Creado con éxito'];
            }
            break;

        case 'update':
            if (empty($id) || empty($name)) throw new Exception("Datos insuficientes para actualizar.");
            if ($catObj->update($id, $name, $desc)) {
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
    http_response_code(400); 
    echo json_encode([
        'status' => false, 
        'message' => $e->getMessage()
    ]);
} ```

## Archivo: ./public/actions/actions_config.php
 ```php
<?php
session_start();
require_once '../../includes/Middleware.php';
require_once '../../config/db.php';
require_once '../../includes/ExchangeRate.php';
require_once '../../includes/helpers.php';
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
    
    header("Location: ../configuration.php?success=1");
    exit;
} ```

## Archivo: ./public/actions/actions_credit.php
 ```php
<?php
require_once '../../includes/Middleware.php';
require_once '../../config/db.php';
require_once '../../includes/Credit.php';
require_once '../../includes/ExchangeRate.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();

$database = new Database();
$db = $database->getConnection();

$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

$creditObj = new Credit($db, $tenant_id);

try {
    if ($action === 'add_payment') {
        $credit_id = (int)$_POST['credit_id'];
        $amount_usd = (float)$_POST['amount_usd'];
        $method = $_POST['payment_method'];

        // Obtener la tasa BCV actual del sistema
        $rateObj = new ExchangeRate($db);
        $current_rate = $rateObj->getSystemRate();

        $result = $creditObj->addPayment($credit_id, $user_id, $amount_usd, $current_rate, $method);
        echo json_encode($result);
    } 
    elseif ($action === 'get_history') {
        $credit_id = (int)$_POST['credit_id'];
        $history = $creditObj->getPayments($credit_id);
        echo json_encode(['status' => true, 'data' => $history]);
    } 
    else {
        throw new Exception('Acción no válida.');
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?> ```

## Archivo: ./public/actions/actions_critical.php
 ```php
<?php
session_start();
require_once '../../includes/Middleware.php';
require_once '../../config/db.php';
require_once '../../includes/ExchangeRate.php';
require_once '../../includes/helpers.php';
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
        header("Location: ../configuration.php?success=" . urlencode($msg));
        exit;

    } catch (Exception $e) {
        // Redirigir de vuelta con el mensaje de error
        header("Location: ../configuration.php?error=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Si entran directamente a la URL sin POST, los devolvemos
    header("Location: ../configuration.php");
    exit;
} ```

## Archivo: ./public/actions/actions_customer.php
 ```php
<?php
require_once '../../includes/Middleware.php';
require_once '../../config/db.php';
require_once '../../includes/Customer.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();

$database = new Database();
$db = $database->getConnection();
$tenant_id = $_SESSION['tenant_id'];

$action = $_POST['action'] ?? '';
$customerObj = new Customer($db, $tenant_id);

try {
    if ($action === 'search') {
        $term = $_POST['term'] ?? '';
        $sql = "SELECT id, name, document FROM customers 
                WHERE tenant_id = :tid 
                AND (name LIKE :term1 OR document LIKE :term2) 
                ORDER BY name ASC LIMIT 10";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':tid' => $tenant_id,
            ':term1' => "%$term%",
            ':term2' => "%$term%"
        ]);
        echo json_encode(['status' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    elseif ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $document = trim($_POST['document'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name)) throw new Exception("El nombre es obligatorio.");

        if($customerObj->create($name, $document, $phone)) {
            echo json_encode([
                'status' => true, 
                'message' => 'Cliente creado exitosamente',
                'customer' => ['id' => $db->lastInsertId(), 'name' => $name, 'document' => $document]
            ]);
        } else {
            throw new Exception("Error al guardar en la base de datos.");
        }
    } 
    elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $document = trim($_POST['document'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($id) || empty($name)) throw new Exception("Datos insuficientes.");

        if($customerObj->update($id, $name, $document, $phone)) {
            echo json_encode(['status' => true, 'message' => 'Cliente actualizado exitosamente']);
        } else {
            throw new Exception("Error al actualizar.");
        }
    }
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (empty($id)) throw new Exception("ID no válido.");

        // Aquí podrías envolver el borrado en un try-catch por si falla por Foreign Keys (ventas asociadas)
        try {
            $customerObj->delete($id);
            echo json_encode(['status' => true, 'message' => 'Cliente eliminado exitosamente']);
        } catch (PDOException $e) {
            // Error 23000 es violación de restricción de clave foránea
            if ($e->getCode() == '23000') {
                throw new Exception("No se puede eliminar el cliente porque tiene ventas o créditos asociados.");
            }
            throw $e;
        }
    }
    else {
        throw new Exception('Acción no válida.');
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?> ```

## Archivo: ./public/actions/actions_product.php
 ```php
<?php
require_once '../../includes/Middleware.php';
require_once '../../config/db.php';

// Asegurar que la respuesta sea JSON
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();
Middleware::onlyAdmin(); 

$database = new Database();
$db = $database->getConnection();

$action = $_REQUEST['action'] ?? '';
$tenant_id = $_SESSION['tenant_id'];

try {
    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) throw new Exception("El nombre del producto es obligatorio.");

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
                echo json_encode(['status' => true, 'message' => 'Producto creado con éxito.']);
                exit;
            } else {
                throw new Exception("No se pudo crear el producto en la base de datos.");
            }
            break;

        case 'update':
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
                echo json_encode(['status' => true, 'message' => 'Producto actualizado con éxito.']);
                exit;
            } else {
                throw new Exception("Error al ejecutar la actualización.");
            }
            break;

        case 'delete':
            $id = !empty($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
            if ($id <= 0) throw new Exception("ID no proporcionado para eliminar.");

            $sql = "DELETE FROM products WHERE id = :id AND tenant_id = :tid";
            $stmt = $db->prepare($sql);
            $res = $stmt->execute([
                ':id' => $id,
                ':tid' => $tenant_id
            ]);

            if ($res) {
                echo json_encode(['status' => true, 'message' => 'Producto eliminado correctamente.']);
                exit;
            } else {
                throw new Exception("Error al intentar eliminar el producto.");
            }
            break;

        default:
            throw new Exception("Acción no válida.");
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    exit;
}
?> ```

## Archivo: ./public/actions/actions_rate.php
 ```php
<?php
// public/actions_rate.php
require_once '../../includes/Middleware.php';
require_once '../../config/db.php';
require_once '../../includes/ExchangeRate.php';

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
?> ```

## Archivo: ./public/actions/actions_user.php
 ```php
<?php
//Archivo: ./public/actions/actions_user.php

require_once '../../includes/Middleware.php';
require_once '../../config/db.php';
require_once '../../includes/User.php';

session_start();
Middleware::onlyAdmin(); // Solo admins tocan esto

header('Content-Type: application/json');

$db = (new Database())->getConnection();
$userObj = new User($db, $_SESSION['tenant_id']);
$action = $_POST['action'] ?? '';

try {

    // En actions/actions_user.php
    if ($action === 'create') {
        $res = $userObj->create($_POST['username'], $_POST['full_name'], $_POST['password'], $_POST['role']);
        // Si el modelo devolvió true pero no traía mensaje, lo asignamos aquí
        if ($res['status']) $res['message'] = "El usuario ha sido registrado correctamente.";
        echo json_encode($res);
    } elseif ($action === 'update') {
        $pass = !empty($_POST['password']) ? $_POST['password'] : null;
        $res = $userObj->update($_POST['id'], $_POST['username'], $_POST['full_name'], $_POST['role'], $pass);
        if ($res['status']) $res['message'] = "Los datos se actualizaron con éxito.";
        echo json_encode($res);
    } elseif ($action === 'delete') {
        // 1. Validar que el ID exista y sea numérico
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id) {
            echo json_encode(['status' => false, 'message' => 'ID de usuario no válido.']);
            exit;
        }

        // 2. Intentar la eliminación
        try {
            $res = $userObj->delete($id);

            // 3. Personalizar el mensaje de éxito si el modelo no lo trae
            if ($res['status'] && !isset($res['message'])) {
                $res['message'] = "Usuario eliminado correctamente.";
            }

            echo json_encode($res);
        } catch (Exception $e) {
            // Log del error real para el desarrollador y mensaje genérico para el usuario
            error_log("Error en delete user: " . $e->getMessage());
            echo json_encode(['status' => false, 'message' => 'No se pudo eliminar el usuario debido a un error del servidor.']);
        }
    } else {
        echo json_encode(['status' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
} ```

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
                                        <?= $lowStockCount > 0 ? '¡Necesitas reponer!' : 'Stock saludable' ?></span>
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
                                <?php if (!empty($products)): ?>
                                    <?php foreach ($products as $p):
                                        $costo_usd = $p['price_base_usd'];
                                        $precio_usd = $costo_usd * (1 + ($p['profit_margin'] / 100));
                                        $ganancia_usd = $precio_usd - $costo_usd;
                                        $ganancia_bs = $ganancia_usd * $bcvRate;
                                        $p_json = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <tr>
                                            <td class="ps-3">
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($p['image'])): ?>
                                                        <img src="<?= htmlspecialchars($p['image']) ?>" class="rounded object-fit-contain me-3 border" style="width: 45px; height: 45px;" alt="img">
                                                    <?php else: ?>
                                                        <div class="bg-secondary bg-opacity-25 rounded d-flex align-items-center justify-content-center me-3 border" style="width: 45px; height: 45px;">
                                                            <i class="fas fa-box text-secondary"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong class="d-block"><?= htmlspecialchars($p['name']) ?></strong>
                                                        <?php if (!empty($p['description'])): ?>
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
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>')" title="Eliminar">
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
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include 'layouts/footer.php';
include 'layouts/modals/modals_admin.php';
?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/admin.js"></script>
</body>

</html> ```

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
                <div class="col-12 col-lg-10"> <div class="card card-outline card-primary shadow-sm">
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
                                                <th>Descripción</th>
                                                <th class="text-center">Productos</th>
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
                                                <td>
                                                    <span class="text-muted small"><?= !empty($c['description']) ? htmlspecialchars($c['description']) : '<i class="text-muted opacity-50">Sin descripción</i>' ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= $c['product_count'] > 0 ? 'text-bg-info' : 'text-bg-secondary opacity-50' ?> rounded-pill">
                                                        <?= $c['product_count'] ?> items
                                                    </span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="btn-group">
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/categories.js"></script>
</body>
</html> ```

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
            <form id="formConfig" action="actions/actions_config.php" method="POST">
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
                                        <input type="text" name="business_name" class="form-control" autocomplete="business_name" value="<?= htmlspecialchars($tenant_data['business_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">RIF / Identificación Fiscal</label>
                                        <input type="text" name="rif" class="form-control" autocomplete="rif" placeholder="J-12345678-0" value="<?= htmlspecialchars($tenant_data['rif'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Dirección Comercial</label>
                                        <textarea name="address" class="form-control" autocomplete="address" rows="2"><?= htmlspecialchars($tenant_data['address'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Teléfono de Contacto</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                            <input type="text" name="phone" class="form-control" autocomplete="phone" placeholder="+58..." value="<?= htmlspecialchars($tenant_data['phone'] ?? '') ?>">
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

</html> ```

## Archivo: ./public/credits.php
 ```php
<?php
require_once '../controllers/CreditController.php';
include 'layouts/head.php';
echo '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
<main class="app-main">
    <?= render_content_header($headerConfig) ?>
    <div class="app-content">
        <div class="container-fluid">
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="info-box shadow-sm text-bg-warning h-100">
                        <span class="info-box-icon"><i class="fas fa-file-invoice-dollar text-white"></i></span>
                        <div class="info-box-content text-white">
                            <span class="info-box-text small fw-bold text-uppercase">Total por Cobrar</span>
                            <span class="info-box-number fs-4 mb-0">$<?= number_format($total_deuda_usd, 2) ?></span>
                            <span class="progress-description small">≈ Bs. <?= number_format($total_deuda_usd * $bcvRate, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-warning shadow-sm">
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="creditsTable" class="table table-hover table-striped align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th># Venta</th>
                                    <th>Cliente</th>
                                    <th>Fecha Emisión</th>
                                    <th>Vencimiento</th>
                                    <th>Total Deuda</th>
                                    <th>Saldo Pendiente</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($credits as $c): 
                                    $is_overdue = ($c['due_date'] && $c['due_date'] < date('Y-m-d') && $c['status'] == 'pending');
                                ?>
                                <tr>
                                    <td class="fw-bold text-primary">#<?= $c['sale_id'] ?></td>
                                    <td>
                                        <strong class="d-block"><?= htmlspecialchars($c['customer_name']) ?></strong>
                                        <small class="text-muted"><?= htmlspecialchars($c['document']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($c['sale_date'])) ?></td>
                                    <td>
                                        <?php if($c['due_date']): ?>
                                            <span class="<?= $is_overdue ? 'text-danger fw-bold' : '' ?>">
                                                <?= date('d/m/Y', strtotime($c['due_date'])) ?>
                                                <?= $is_overdue ? '<i class="fas fa-exclamation-circle ms-1"></i>' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted">$<?= number_format($c['total_amount_usd'], 2) ?></td>
                                    <td class="fw-bold fs-6 text-danger">$<?= number_format($c['balance_usd'], 2) ?></td>
                                    <td>
                                        <?php if($c['status'] == 'pending'): ?>
                                            <span class="badge text-bg-warning">Pendiente</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-success">Pagado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button class="btn btn-sm btn-outline-info me-1" onclick="viewHistory(<?= $c['id'] ?>)" title="Ver Pagos">
                                            <i class="fas fa-list"></i>
                                        </button>
                                        <?php if($c['status'] == 'pending'): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="openPaymentModal(<?= $c['id'] ?>, <?= $c['balance_usd'] ?>, '<?= htmlspecialchars(addslashes($c['customer_name'])) ?>')" title="Registrar Abono">
                                            <i class="fas fa-hand-holding-usd"></i> Abonar
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<div class="modal fade" id="modalPayment" tabindex="-1" >
    <div class="modal-dialog modal-dialog-centered">
        <form id="formPayment" class="modal-content shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i> Registrar Abono</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="add_payment">
                <input type="hidden" name="credit_id" id="pay_credit_id">
                
                <div class="alert alert-info py-2">
                    Cliente: <strong id="pay_customer_name"></strong><br>
                    Deuda Actual: <strong class="text-danger" id="pay_balance_display"></strong>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Monto a Abonar (USD) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-bold">$</span>
                        <input type="number" step="0.01" name="amount_usd" id="pay_amount" class="form-control form-control-lg" required>
                    </div>
                    <small class="text-muted" id="pay_bs_conversion">Equivale a: Bs 0.00</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Método de Pago</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="efectivo_bs">Efectivo Bolívares</option>
                        <option value="efectivo_usd">Efectivo Divisa</option>
                        <option value="pago_movil">Pago Móvil</option>
                        <option value="punto">Punto de Venta</option>
                        <option value="transferencia">Transferencia</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success px-4" id="btnSubmitPayment">Confirmar Pago</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalHistory" tabindex="-1" >
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i> Historial de Pagos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-striped mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Monto USD</th>
                            <th>Monto BS</th>
                            <th>Método</th>
                            <th>Cajero</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        </tbody>
                </table>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php 
    include 'layouts/footer.php'; 
    include 'layouts/modals/modals_credits.php';
?> 
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    window.APP_BCVRATE = <?= $bcvRate ?>;
</script>
<script src="js/credits.js"></script>
</body>
</html> ```

## Archivo: ./public/css/adminlte.css
 ```css
@charset "UTF-8";
/*!
 *   AdminLTE v4.0.0-rc3
 *   Author: Colorlib
 *   Website: AdminLTE.io <https://adminlte.io>
 *   License: Open source - MIT <https://opensource.org/licenses/MIT>
 */
:root,
[data-bs-theme=light] {
  --bs-blue: #1877F2;
  --bs-indigo: #6610f2;
  --bs-purple: #6f42c1;
  --bs-pink: #d63384;
  --bs-red: #dc3545;
  --bs-orange: #fd7e14;
  --bs-yellow: #ffc107;
  --bs-green: #198754;
  --bs-teal: #20c997;
  --bs-cyan: #0dcaf0;
  --bs-black: #000;
  --bs-white: #fff;
  --bs-gray: #6c757d;
  --bs-gray-dark: #343a40;
  --bs-gray-100: #f8f9fa;
  --bs-gray-200: #e9ecef;
  --bs-gray-300: #dee2e6;
  --bs-gray-400: #ced4da;
  --bs-gray-500: #adb5bd;
  --bs-gray-600: #6c757d;
  --bs-gray-700: #495057;
  --bs-gray-800: #343a40;
  --bs-gray-900: #212529;
  --bs-primary: #1877F2;
  --bs-secondary: #6c757d;
  --bs-success: #198754;
  --bs-info: #0dcaf0;
  --bs-warning: #ffc107;
  --bs-danger: #dc3545;
  --bs-light: #f8f9fa;
  --bs-dark: #212529;
  --bs-primary-rgb: 24, 110, 253;
  --bs-secondary-rgb: 108, 117, 125;
  --bs-success-rgb: 25, 135, 84;
  --bs-info-rgb: 13, 202, 240;
  --bs-warning-rgb: 255, 193, 7;
  --bs-danger-rgb: 220, 53, 69;
  --bs-light-rgb: 248, 249, 250;
  --bs-dark-rgb: 33, 37, 41;
  --bs-primary-text-emphasis: rgb(5.2, 44, 101.2);
  --bs-secondary-text-emphasis: rgb(43.2, 46.8, 50);
  --bs-success-text-emphasis: rgb(10, 54, 33.6);
  --bs-info-text-emphasis: rgb(5.2, 80.8, 96);
  --bs-warning-text-emphasis: rgb(102, 77.2, 2.8);
  --bs-danger-text-emphasis: rgb(88, 21.2, 27.6);
  --bs-light-text-emphasis: #495057;
  --bs-dark-text-emphasis: #495057;
  --bs-primary-bg-subtle: rgb(206.6, 226, 254.6);
  --bs-secondary-bg-subtle: rgb(225.6, 227.4, 229);
  --bs-success-bg-subtle: rgb(209, 231, 220.8);
  --bs-info-bg-subtle: rgb(206.6, 244.4, 252);
  --bs-warning-bg-subtle: rgb(255, 242.6, 205.4);
  --bs-danger-bg-subtle: rgb(248, 214.6, 217.8);
  --bs-light-bg-subtle: rgb(251.5, 252, 252.5);
  --bs-dark-bg-subtle: #ced4da;
  --bs-primary-border-subtle: rgb(158.2, 197, 254.2);
  --bs-secondary-border-subtle: rgb(196.2, 199.8, 203);
  --bs-success-border-subtle: rgb(163, 207, 186.6);
  --bs-info-border-subtle: rgb(158.2, 233.8, 249);
  --bs-warning-border-subtle: rgb(255, 230.2, 155.8);
  --bs-danger-border-subtle: rgb(241, 174.2, 180.6);
  --bs-light-border-subtle: #e9ecef;
  --bs-dark-border-subtle: #adb5bd;
  --bs-white-rgb: 255, 255, 255;
  --bs-black-rgb: 0, 0, 0;
  --bs-font-sans-serif: "Source Sans 3", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
  --bs-font-monospace: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  --bs-gradient: linear-gradient(180deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0));
  --bs-body-font-family: var(--bs-font-sans-serif);
  --bs-body-font-size: 1rem;
  --bs-body-font-weight: 400;
  --bs-body-line-height: 1.5;
  --bs-body-color: #212529;
  --bs-body-color-rgb: 33, 37, 41;
  --bs-body-bg: #fff;
  --bs-body-bg-rgb: 255, 255, 255;
  --bs-emphasis-color: #000;
  --bs-emphasis-color-rgb: 0, 0, 0;
  --bs-secondary-color: rgba(33, 37, 41, 0.75);
  --bs-secondary-color-rgb: 33, 37, 41;
  --bs-secondary-bg: #e9ecef;
  --bs-secondary-bg-rgb: 233, 236, 239;
  --bs-tertiary-color: rgba(33, 37, 41, 0.5);
  --bs-tertiary-color-rgb: 33, 37, 41;
  --bs-tertiary-bg: #f8f9fa;
  --bs-tertiary-bg-rgb: 248, 249, 250;
  --bs-heading-color: inherit;
  --bs-link-color: #0d6efd;
  --bs-link-color-rgb: 13, 110, 253;
  --bs-link-decoration: underline;
  --bs-link-hover-color: rgb(10.4, 88, 202.4);
  --bs-link-hover-color-rgb: 10, 88, 202;
  --bs-code-color: #d63384;
  --bs-highlight-color: #212529;
  --bs-highlight-bg: rgb(255, 242.6, 205.4);
  --bs-border-width: 1px;
  --bs-border-style: solid;
  --bs-border-color: #dee2e6;
  --bs-border-color-translucent: rgba(0, 0, 0, 0.175);
  --bs-border-radius: 0.375rem;
  --bs-border-radius-sm: 0.25rem;
  --bs-border-radius-lg: 0.5rem;
  --bs-border-radius-xl: 1rem;
  --bs-border-radius-xxl: 2rem;
  --bs-border-radius-2xl: var(--bs-border-radius-xxl);
  --bs-border-radius-pill: 50rem;
  --bs-box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
  --bs-box-shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  --bs-box-shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
  --bs-box-shadow-inset: inset 0 1px 2px rgba(0, 0, 0, 0.075);
  --bs-focus-ring-width: 0.25rem;
  --bs-focus-ring-opacity: 0.25;
  --bs-focus-ring-color: rgba(13, 110, 253, 0.25);
  --bs-form-valid-color: #198754;
  --bs-form-valid-border-color: #198754;
  --bs-form-invalid-color: #dc3545;
  --bs-form-invalid-border-color: #dc3545;
}

[data-bs-theme=dark] {
  color-scheme: dark;
  --bs-body-color: #dee2e6;
  --bs-body-color-rgb: 222, 226, 230;
  --bs-body-bg: #212529;
  --bs-body-bg-rgb: 33, 37, 41;
  --bs-emphasis-color: #fff;
  --bs-emphasis-color-rgb: 255, 255, 255;
  --bs-secondary-color: rgba(222, 226, 230, 0.75);
  --bs-secondary-color-rgb: 222, 226, 230;
  --bs-secondary-bg: #343a40;
  --bs-secondary-bg-rgb: 52, 58, 64;
  --bs-tertiary-color: rgba(222, 226, 230, 0.5);
  --bs-tertiary-color-rgb: 222, 226, 230;
  --bs-tertiary-bg: rgb(42.5, 47.5, 52.5);
  --bs-tertiary-bg-rgb: 28, 28, 29;
  --bs-primary-text-emphasis: rgb(109.8, 168, 253.8);
  --bs-secondary-text-emphasis: rgb(166.8, 172.2, 177);
  --bs-success-text-emphasis: rgb(117, 183, 152.4);
  --bs-info-text-emphasis: rgb(109.8, 223.2, 246);
  --bs-warning-text-emphasis: rgb(255, 217.8, 106.2);
  --bs-danger-text-emphasis: rgb(234, 133.8, 143.4);
  --bs-light-text-emphasis: #f8f9fa;
  --bs-dark-text-emphasis: #dee2e6;
  --bs-primary-bg-subtle: rgb(2.6, 22, 50.6);
  --bs-secondary-bg-subtle: rgb(21.6, 23.4, 25);
  --bs-success-bg-subtle: rgb(5, 27, 16.8);
  --bs-info-bg-subtle: rgb(2.6, 40.4, 48);
  --bs-warning-bg-subtle: rgb(51, 38.6, 1.4);
  --bs-danger-bg-subtle: rgb(44, 10.6, 13.8);
  --bs-light-bg-subtle: #343a40;
  --bs-dark-bg-subtle: #1a1d20;
  --bs-primary-border-subtle: rgb(7.8, 66, 151.8);
  --bs-secondary-border-subtle: rgb(64.8, 70.2, 75);
  --bs-success-border-subtle: rgb(15, 81, 50.4);
  --bs-info-border-subtle: rgb(7.8, 121.2, 144);
  --bs-warning-border-subtle: rgb(153, 115.8, 4.2);
  --bs-danger-border-subtle: rgb(132, 31.8, 41.4);
  --bs-light-border-subtle: #495057;
  --bs-dark-border-subtle: #343a40;
  --bs-heading-color: inherit;
  --bs-link-color: rgb(109.8, 168, 253.8);
  --bs-link-hover-color: rgb(138.84, 185.4, 254.04);
  --bs-link-color-rgb: 110, 168, 254;
  --bs-link-hover-color-rgb: 139, 185, 254;
  --bs-code-color: rgb(230.4, 132.6, 181.2);
  --bs-highlight-color: #dee2e6;
  --bs-highlight-bg: rgb(102, 77.2, 2.8);
  --bs-border-color: #495057;
  --bs-border-color-translucent: rgba(255, 255, 255, 0.15);
  --bs-form-valid-color: rgb(117, 183, 152.4);
  --bs-form-valid-border-color: rgb(117, 183, 152.4);
  --bs-form-invalid-color: rgb(234, 133.8, 143.4);
  --bs-form-invalid-border-color: rgb(234, 133.8, 143.4);
}

*,
*::before,
*::after {
  box-sizing: border-box;
}

@media (prefers-reduced-motion: no-preference) {
  :root {
    scroll-behavior: smooth;
  }
}

body {
  margin: 0;
  font-family: var(--bs-body-font-family);
  font-size: var(--bs-body-font-size);
  font-weight: var(--bs-body-font-weight);
  line-height: var(--bs-body-line-height);
  color: var(--bs-body-color);
  text-align: var(--bs-body-text-align);
  background-color: var(--bs-body-bg);
  -webkit-text-size-adjust: 100%;
  -webkit-tap-highlight-color: rgba(0, 0, 0, 0);
}

hr {
  margin: 1rem 0;
  color: inherit;
  border: 0;
  border-top: var(--bs-border-width) solid;
  opacity: 0.25;
}

h6, .h6, h5, .h5, h4, .h4, h3, .h3, h2, .h2, h1, .h1 {
  margin-top: 0;
  margin-bottom: 0.5rem;
  font-weight: 500;
  line-height: 1.2;
  color: var(--bs-heading-color);
}

h1, .h1 {
  font-size: calc(1.375rem + 1.5vw);
}
@media (min-width: 1200px) {
  h1, .h1 {
    font-size: 2.5rem;
  }
}

h2, .h2 {
  font-size: calc(1.325rem + 0.9vw);
}
@media (min-width: 1200px) {
  h2, .h2 {
    font-size: 2rem;
  }
}

h3, .h3 {
  font-size: calc(1.3rem + 0.6vw);
}
@media (min-width: 1200px) {
  h3, .h3 {
    font-size: 1.75rem;
  }
}

h4, .h4 {
  font-size: calc(1.275rem + 0.3vw);
}
@media (min-width: 1200px) {
  h4, .h4 {
    font-size: 1.5rem;
  }
}

h5, .h5 {
  font-size: 1.25rem;
}

h6, .h6 {
  font-size: 1rem;
}

p {
  margin-top: 0;
  margin-bottom: 1rem;
}

abbr[title] {
  -webkit-text-decoration: underline dotted;
  text-decoration: underline dotted;
  cursor: help;
  -webkit-text-decoration-skip-ink: none;
  text-decoration-skip-ink: none;
}

address {
  margin-bottom: 1rem;
  font-style: normal;
  line-height: inherit;
}

ol,
ul {
  padding-left: 2rem;
}

ol,
ul,
dl {
  margin-top: 0;
  margin-bottom: 1rem;
}

ol ol,
ul ul,
ol ul,
ul ol {
  margin-bottom: 0;
}

dt {
  font-weight: 700;
}

dd {
  margin-bottom: 0.5rem;
  margin-left: 0;
}

blockquote {
  margin: 0 0 1rem;
}

b,
strong {
  font-weight: bolder;
}

small, .small {
  font-size: 0.875em;
}

mark, .mark {
  padding: 0.1875em;
  color: var(--bs-highlight-color);
  background-color: var(--bs-highlight-bg);
}

sub,
sup {
  position: relative;
  font-size: 0.75em;
  line-height: 0;
  vertical-align: baseline;
}

sub {
  bottom: -0.25em;
}

sup {
  top: -0.5em;
}

a {
  color: rgba(var(--bs-link-color-rgb), var(--bs-link-opacity, 1));
  text-decoration: underline;
}
a:hover {
  --bs-link-color-rgb: var(--bs-link-hover-color-rgb);
}

a:not([href]):not([class]), a:not([href]):not([class]):hover {
  color: inherit;
  text-decoration: none;
}

pre,
code,
kbd,
samp {
  font-family: var(--bs-font-monospace);
  font-size: 1em;
}

pre {
  display: block;
  margin-top: 0;
  margin-bottom: 1rem;
  overflow: auto;
  font-size: 0.875em;
}
pre code {
  font-size: inherit;
  color: inherit;
  word-break: normal;
}

code {
  font-size: 0.875em;
  color: var(--bs-code-color);
  word-wrap: break-word;
}
a > code {
  color: inherit;
}

kbd {
  padding: 0.1875rem 0.375rem;
  font-size: 0.875em;
  color: var(--bs-body-bg);
  background-color: var(--bs-body-color);
  border-radius: 0.25rem;
}
kbd kbd {
  padding: 0;
  font-size: 1em;
}

figure {
  margin: 0 0 1rem;
}

img,
svg {
  vertical-align: middle;
}

table {
  caption-side: bottom;
  border-collapse: collapse;
}

caption {
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
  color: var(--bs-secondary-color);
  text-align: left;
}

th {
  text-align: inherit;
  text-align: -webkit-match-parent;
}

thead,
tbody,
tfoot,
tr,
td,
th {
  border-color: inherit;
  border-style: solid;
  border-width: 0;
}

label {
  display: inline-block;
}

button {
  border-radius: 0;
}

button:focus:not(:focus-visible) {
  outline: 0;
}

input,
button,
select,
optgroup,
textarea {
  margin: 0;
  font-family: inherit;
  font-size: inherit;
  line-height: inherit;
}

button,
select {
  text-transform: none;
}

[role=button] {
  cursor: pointer;
}

select {
  word-wrap: normal;
}
select:disabled {
  opacity: 1;
}

[list]:not([type=date]):not([type=datetime-local]):not([type=month]):not([type=week]):not([type=time])::-webkit-calendar-picker-indicator {
  display: none !important;
}

button,
[type=button],
[type=reset],
[type=submit] {
  -webkit-appearance: button;
}
button:not(:disabled),
[type=button]:not(:disabled),
[type=reset]:not(:disabled),
[type=submit]:not(:disabled) {
  cursor: pointer;
}

::-moz-focus-inner {
  padding: 0;
  border-style: none;
}

textarea {
  resize: vertical;
}

fieldset {
  min-width: 0;
  padding: 0;
  margin: 0;
  border: 0;
}

legend {
  float: left;
  width: 100%;
  padding: 0;
  margin-bottom: 0.5rem;
  line-height: inherit;
  font-size: calc(1.275rem + 0.3vw);
}
@media (min-width: 1200px) {
  legend {
    font-size: 1.5rem;
  }
}
legend + * {
  clear: left;
}

::-webkit-datetime-edit-fields-wrapper,
::-webkit-datetime-edit-text,
::-webkit-datetime-edit-minute,
::-webkit-datetime-edit-hour-field,
::-webkit-datetime-edit-day-field,
::-webkit-datetime-edit-month-field,
::-webkit-datetime-edit-year-field {
  padding: 0;
}

::-webkit-inner-spin-button {
  height: auto;
}

[type=search] {
  -webkit-appearance: textfield;
  outline-offset: -2px;
}

/* rtl:raw:
[type="tel"],
[type="url"],
[type="email"],
[type="number"] {
  direction: ltr;
}
*/
::-webkit-search-decoration {
  -webkit-appearance: none;
}

::-webkit-color-swatch-wrapper {
  padding: 0;
}

::file-selector-button {
  font: inherit;
  -webkit-appearance: button;
}

output {
  display: inline-block;
}

iframe {
  border: 0;
}

summary {
  display: list-item;
  cursor: pointer;
}

progress {
  vertical-align: baseline;
}

[hidden] {
  display: none !important;
}

.lead {
  font-size: 1.25rem;
  font-weight: 300;
}

.display-1 {
  font-weight: 300;
  line-height: 1.2;
  font-size: calc(1.625rem + 4.5vw);
}
@media (min-width: 1200px) {
  .display-1 {
    font-size: 5rem;
  }
}

.display-2 {
  font-weight: 300;
  line-height: 1.2;
  font-size: calc(1.575rem + 3.9vw);
}
@media (min-width: 1200px) {
  .display-2 {
    font-size: 4.5rem;
  }
}

.display-3 {
  font-weight: 300;
  line-height: 1.2;
  font-size: calc(1.525rem + 3.3vw);
}
@media (min-width: 1200px) {
  .display-3 {
    font-size: 4rem;
  }
}

.display-4 {
  font-weight: 300;
  line-height: 1.2;
  font-size: calc(1.475rem + 2.7vw);
}
@media (min-width: 1200px) {
  .display-4 {
    font-size: 3.5rem;
  }
}

.display-5 {
  font-weight: 300;
  line-height: 1.2;
  font-size: calc(1.425rem + 2.1vw);
}
@media (min-width: 1200px) {
  .display-5 {
    font-size: 3rem;
  }
}

.display-6 {
  font-weight: 300;
  line-height: 1.2;
  font-size: calc(1.375rem + 1.5vw);
}
@media (min-width: 1200px) {
  .display-6 {
    font-size: 2.5rem;
  }
}

.list-unstyled {
  padding-left: 0;
  list-style: none;
}

.list-inline {
  padding-left: 0;
  list-style: none;
}

.list-inline-item {
  display: inline-block;
}
.list-inline-item:not(:last-child) {
  margin-right: 0.5rem;
}

.initialism {
  font-size: 0.875em;
  text-transform: uppercase;
}

.blockquote {
  margin-bottom: 1rem;
  font-size: 1.25rem;
}
.blockquote > :last-child {
  margin-bottom: 0;
}

.blockquote-footer {
  margin-top: -1rem;
  margin-bottom: 1rem;
  font-size: 0.875em;
  color: #6c757d;
}
.blockquote-footer::before {
  content: "— ";
}

.img-fluid {
  max-width: 100%;
  height: auto;
}

.img-thumbnail {
  padding: 0.25rem;
  background-color: var(--bs-body-bg);
  border: var(--bs-border-width) solid var(--bs-border-color);
  border-radius: var(--bs-border-radius);
  box-shadow: var(--bs-box-shadow-sm);
  max-width: 100%;
  height: auto;
}

.figure {
  display: inline-block;
}

.figure-img {
  margin-bottom: 0.5rem;
  line-height: 1;
}

.figure-caption {
  font-size: 0.875em;
  color: var(--bs-secondary-color);
}

.container,
.container-fluid,
.container-xxl,
.container-xl,
.container-lg,
.container-md,
.container-sm {
  --bs-gutter-x: 1.5rem;
  --bs-gutter-y: 0;
  width: 100%;
  padding-right: calc(var(--bs-gutter-x) * 0.5);
  padding-left: calc(var(--bs-gutter-x) * 0.5);
  margin-right: auto;
  margin-left: auto;
}

@media (min-width: 576px) {
  .container-sm, .container {
    max-width: 540px;
  }
}
@media (min-width: 768px) {
  .container-md, .container-sm, .container {
    max-width: 720px;
  }
}
@media (min-width: 992px) {
  .container-lg, .container-md, .container-sm, .container {
    max-width: 960px;
  }
}
@media (min-width: 1200px) {
  .container-xl, .container-lg, .container-md, .container-sm, .container {
    max-width: 1140px;
  }
}
@media (min-width: 1400px) {
  .container-xxl, .container-xl, .container-lg, .container-md, .container-sm, .container {
    max-width: 1320px;
  }
}
:root {
  --bs-breakpoint-xs: 0;
  --bs-breakpoint-sm: 576px;
  --bs-breakpoint-md: 768px;
  --bs-breakpoint-lg: 992px;
  --bs-breakpoint-xl: 1200px;
  --bs-breakpoint-xxl: 1400px;
}

.row {
  --bs-gutter-x: 1.5rem;
  --bs-gutter-y: 0;
  display: flex;
  flex-wrap: wrap;
  margin-top: calc(-1 * var(--bs-gutter-y));
  margin-right: calc(-0.5 * var(--bs-gutter-x));
  margin-left: calc(-0.5 * var(--bs-gutter-x));
}
.row > * {
  flex-shrink: 0;
  width: 100%;
  max-width: 100%;
  padding-right: calc(var(--bs-gutter-x) * 0.5);
  padding-left: calc(var(--bs-gutter-x) * 0.5);
  margin-top: var(--bs-gutter-y);
}

.col {
  flex: 1 0 0;
}

.row-cols-auto > * {
  flex: 0 0 auto;
  width: auto;
}

.row-cols-1 > * {
  flex: 0 0 auto;
  width: 100%;
}

.row-cols-2 > * {
  flex: 0 0 auto;
  width: 50%;
}

.row-cols-3 > * {
  flex: 0 0 auto;
  width: 33.33333333%;
}

.row-cols-4 > * {
  flex: 0 0 auto;
  width: 25%;
}

.row-cols-5 > * {
  flex: 0 0 auto;
  width: 20%;
}

.row-cols-6 > * {
  flex: 0 0 auto;
  width: 16.66666667%;
}

.col-auto {
  flex: 0 0 auto;
  width: auto;
}

.col-1 {
  flex: 0 0 auto;
  width: 8.33333333%;
}

.col-2 {
  flex: 0 0 auto;
  width: 16.66666667%;
}

.col-3 {
  flex: 0 0 auto;
  width: 25%;
}

.col-4 {
  flex: 0 0 auto;
  width: 33.33333333%;
}

.col-5 {
  flex: 0 0 auto;
  width: 41.66666667%;
}

.col-6 {
  flex: 0 0 auto;
  width: 50%;
}

.col-7 {
  flex: 0 0 auto;
  width: 58.33333333%;
}

.col-8 {
  flex: 0 0 auto;
  width: 66.66666667%;
}

.col-9 {
  flex: 0 0 auto;
  width: 75%;
}

.col-10 {
  flex: 0 0 auto;
  width: 83.33333333%;
}

.col-11 {
  flex: 0 0 auto;
  width: 91.66666667%;
}

.col-12 {
  flex: 0 0 auto;
  width: 100%;
}

.offset-1 {
  margin-left: 8.33333333%;
}

.offset-2 {
  margin-left: 16.66666667%;
}

.offset-3 {
  margin-left: 25%;
}

.offset-4 {
  margin-left: 33.33333333%;
}

.offset-5 {
  margin-left: 41.66666667%;
}

.offset-6 {
  margin-left: 50%;
}

.offset-7 {
  margin-left: 58.33333333%;
}

.offset-8 {
  margin-left: 66.66666667%;
}

.offset-9 {
  margin-left: 75%;
}

.offset-10 {
  margin-left: 83.33333333%;
}

.offset-11 {
  margin-left: 91.66666667%;
}

.g-0,
.gx-0 {
  --bs-gutter-x: 0;
}

.g-0,
.gy-0 {
  --bs-gutter-y: 0;
}

.g-1,
.gx-1 {
  --bs-gutter-x: 0.25rem;
}

.g-1,
.gy-1 {
  --bs-gutter-y: 0.25rem;
}

.g-2,
.gx-2 {
  --bs-gutter-x: 0.5rem;
}

.g-2,
.gy-2 {
  --bs-gutter-y: 0.5rem;
}

.g-3,
.gx-3 {
  --bs-gutter-x: 1rem;
}

.g-3,
.gy-3 {
  --bs-gutter-y: 1rem;
}

.g-4,
.gx-4 {
  --bs-gutter-x: 1.5rem;
}

.g-4,
.gy-4 {
  --bs-gutter-y: 1.5rem;
}

.g-5,
.gx-5 {
  --bs-gutter-x: 3rem;
}

.g-5,
.gy-5 {
  --bs-gutter-y: 3rem;
}

@media (min-width: 576px) {
  .col-sm {
    flex: 1 0 0;
  }
  .row-cols-sm-auto > * {
    flex: 0 0 auto;
    width: auto;
  }
  .row-cols-sm-1 > * {
    flex: 0 0 auto;
    width: 100%;
  }
  .row-cols-sm-2 > * {
    flex: 0 0 auto;
    width: 50%;
  }
  .row-cols-sm-3 > * {
    flex: 0 0 auto;
    width: 33.33333333%;
  }
  .row-cols-sm-4 > * {
    flex: 0 0 auto;
    width: 25%;
  }
  .row-cols-sm-5 > * {
    flex: 0 0 auto;
    width: 20%;
  }
  .row-cols-sm-6 > * {
    flex: 0 0 auto;
    width: 16.66666667%;
  }
  .col-sm-auto {
    flex: 0 0 auto;
    width: auto;
  }
  .col-sm-1 {
    flex: 0 0 auto;
    width: 8.33333333%;
  }
  .col-sm-2 {
    flex: 0 0 auto;
    width: 16.66666667%;
  }
  .col-sm-3 {
    flex: 0 0 auto;
    width: 25%;
  }
  .col-sm-4 {
    flex: 0 0 auto;
    width: 33.33333333%;
  }
  .col-sm-5 {
    flex: 0 0 auto;
    width: 41.66666667%;
  }
  .col-sm-6 {
    flex: 0 0 auto;
    width: 50%;
  }
  .col-sm-7 {
    flex: 0 0 auto;
    width: 58.33333333%;
  }
  .col-sm-8 {
    flex: 0 0 auto;
    width: 66.66666667%;
  }
  .col-sm-9 {
    flex: 0 0 auto;
    width: 75%;
  }
  .col-sm-10 {
    flex: 0 0 auto;
    width: 83.33333333%;
  }
  .col-sm-11 {
    flex: 0 0 auto;
    width: 91.66666667%;
  }
  .col-sm-12 {
    flex: 0 0 auto;
    width: 100%;
  }
  .offset-sm-0 {
    margin-left: 0;
  }
  .offset-sm-1 {
    margin-left: 8.33333333%;
  }
  .offset-sm-2 {
    margin-left: 16.66666667%;
  }
  .offset-sm-3 {
    margin-left: 25%;
  }
  .offset-sm-4 {
    margin-left: 33.33333333%;
  }
  .offset-sm-5 {
    margin-left: 41.66666667%;
  }
  .offset-sm-6 {
    margin-left: 50%;
  }
  .offset-sm-7 {
    margin-left: 58.33333333%;
  }
  .offset-sm-8 {
    margin-left: 66.66666667%;
  }
  .offset-sm-9 {
    margin-left: 75%;
  }
  .offset-sm-10 {
    margin-left: 83.33333333%;
  }
  .offset-sm-11 {
    margin-left: 91.66666667%;
  }
  .g-sm-0,
  .gx-sm-0 {
    --bs-gutter-x: 0;
  }
  .g-sm-0,
  .gy-sm-0 {
    --bs-gutter-y: 0;
  }
  .g-sm-1,
  .gx-sm-1 {
    --bs-gutter-x: 0.25rem;
  }
  .g-sm-1,
  .gy-sm-1 {
    --bs-gutter-y: 0.25rem;
  }
  .g-sm-2,
  .gx-sm-2 {
    --bs-gutter-x: 0.5rem;
  }
  .g-sm-2,
  .gy-sm-2 {
    --bs-gutter-y: 0.5rem;
  }
  .g-sm-3,
  .gx-sm-3 {
    --bs-gutter-x: 1rem;
  }
  .g-sm-3,
  .gy-sm-3 {
    --bs-gutter-y: 1rem;
  }
  .g-sm-4,
  .gx-sm-4 {
    --bs-gutter-x: 1.5rem;
  }
  .g-sm-4,
  .gy-sm-4 {
    --bs-gutter-y: 1.5rem;
  }
  .g-sm-5,
  .gx-sm-5 {
    --bs-gutter-x: 3rem;
  }
  .g-sm-5,
  .gy-sm-5 {
    --bs-gutter-y: 3rem;
  }
}
@media (min-width: 768px) {
  .col-md {
    flex: 1 0 0;
  }
  .row-cols-md-auto > * {
    flex: 0 0 auto;
    width: auto;
  }
  .row-cols-md-1 > * {
    flex: 0 0 auto;
    width: 100%;
  }
  .row-cols-md-2 > * {
    flex: 0 0 auto;
    width: 50%;
  }
  .row-cols-md-3 > * {
    flex: 0 0 auto;
    width: 33.33333333%;
  }
  .row-cols-md-4 > * {
    flex: 0 0 auto;
    width: 25%;
  }
  .row-cols-md-5 > * {
    flex: 0 0 auto;
    width: 20%;
  }
  .row-cols-md-6 > * {
    flex: 0 0 auto;
    width: 16.66666667%;
  }
  .col-md-auto {
    flex: 0 0 auto;
    width: auto;
  }
  .col-md-1 {
    flex: 0 0 auto;
    width: 8.33333333%;
  }
  .col-md-2 {
    flex: 0 0 auto;
    width: 16.66666667%;
  }
  .col-md-3 {
    flex: 0 0 auto;
    width: 25%;
  }
  .col-md-4 {
    flex: 0 0 auto;
    width: 33.33333333%;
  }
  .col-md-5 {
    flex: 0 0 auto;
    width: 41.66666667%;
  }
  .col-md-6 {
    flex: 0 0 auto;
    width: 50%;
  }
  .col-md-7 {
    flex: 0 0 auto;
    width: 58.33333333%;
  }
  .col-md-8 {
    flex: 0 0 auto;
    width: 66.66666667%;
  }
  .col-md-9 {
    flex: 0 0 auto;
    width: 75%;
  }
  .col-md-10 {
    flex: 0 0 auto;
    width: 83.33333333%;
  }
  .col-md-11 {
    flex: 0 0 auto;
    width: 91.66666667%;
  }
  .col-md-12 {
    flex: 0 0 auto;
    width: 100%;
  }
  .offset-md-0 {
    margin-left: 0;
  }
  .offset-md-1 {
    margin-left: 8.33333333%;
  }
  .offset-md-2 {
    margin-left: 16.66666667%;
  }
  .offset-md-3 {
    margin-left: 25%;
  }
  .offset-md-4 {
    margin-left: 33.33333333%;
  }
  .offset-md-5 {
    margin-left: 41.66666667%;
  }
  .offset-md-6 {
    margin-left: 50%;
  }
  .offset-md-7 {
    margin-left: 58.33333333%;
  }
  .offset-md-8 {
    margin-left: 66.66666667%;
  }
  .offset-md-9 {
    margin-left: 75%;
  }
  .offset-md-10 {
    margin-left: 83.33333333%;
  }
  .offset-md-11 {
    margin-left: 91.66666667%;
  }
  .g-md-0,
  .gx-md-0 {
    --bs-gutter-x: 0;
  }
  .g-md-0,
  .gy-md-0 {
    --bs-gutter-y: 0;
  }
  .g-md-1,
  .gx-md-1 {
    --bs-gutter-x: 0.25rem;
  }
  .g-md-1,
  .gy-md-1 {
    --bs-gutter-y: 0.25rem;
  }
  .g-md-2,
  .gx-md-2 {
    --bs-gutter-x: 0.5rem;
  }
  .g-md-2,
  .gy-md-2 {
    --bs-gutter-y: 0.5rem;
  }
  .g-md-3,
  .gx-md-3 {
    --bs-gutter-x: 1rem;
  }
  .g-md-3,
  .gy-md-3 {
    --bs-gutter-y: 1rem;
  }
  .g-md-4,
  .gx-md-4 {
    --bs-gutter-x: 1.5rem;
  }
  .g-md-4,
  .gy-md-4 {
    --bs-gutter-y: 1.5rem;
  }
  .g-md-5,
  .gx-md-5 {
    --bs-gutter-x: 3rem;
  }
  .g-md-5,
  .gy-md-5 {
    --bs-gutter-y: 3rem;
  }
}
@media (min-width: 992px) {
  .col-lg {
    flex: 1 0 0;
  }
  .row-cols-lg-auto > * {
    flex: 0 0 auto;
    width: auto;
  }
  .row-cols-lg-1 > * {
    flex: 0 0 auto;
    width: 100%;
  }
  .row-cols-lg-2 > * {
    flex: 0 0 auto;
    width: 50%;
  }
  .row-cols-lg-3 > * {
    flex: 0 0 auto;
    width: 33.33333333%;
  }
  .row-cols-lg-4 > * {
    flex: 0 0 auto;
    width: 25%;
  }
  .row-cols-lg-5 > * {
    flex: 0 0 auto;
    width: 20%;
  }
  .row-cols-lg-6 > * {
    flex: 0 0 auto;
    width: 16.66666667%;
  }
  .col-lg-auto {
    flex: 0 0 auto;
    width: auto;
  }
  .col-lg-1 {
    flex: 0 0 auto;
    width: 8.33333333%;
  }
  .col-lg-2 {
    flex: 0 0 auto;
    width: 16.66666667%;
  }
  .col-lg-3 {
    flex: 0 0 auto;
    width: 25%;
  }
  .col-lg-4 {
    flex: 0 0 auto;
    width: 33.33333333%;
  }
  .col-lg-5 {
    flex: 0 0 auto;
    width: 41.66666667%;
  }
  .col-lg-6 {
    flex: 0 0 auto;
    width: 50%;
  }
  .col-lg-7 {
    flex: 0 0 auto;
    width: 58.33333333%;
  }
  .col-lg-8 {
    flex: 0 0 auto;
    width: 66.66666667%;
  }
  .col-lg-9 {
    flex: 0 0 auto;
    width: 75%;
  }
  .col-lg-10 {
    flex: 0 0 auto;
    width: 83.33333333%;
  }
  .col-lg-11 {
    flex: 0 0 auto;
    width: 91.66666667%;
  }
  .col-lg-12 {
    flex: 0 0 auto;
    width: 100%;
  }
  .offset-lg-0 {
    margin-left: 0;
  }
  .offset-lg-1 {
    margin-left: 8.33333333%;
  }
  .offset-lg-2 {
    margin-left: 16.66666667%;
  }
  .offset-lg-3 {
    margin-left: 25%;
  }
  .offset-lg-4 {
    margin-left: 33.33333333%;
  }
  .offset-lg-5 {
    margin-left: 41.66666667%;
  }
  .offset-lg-6 {
    margin-left: 50%;
  }
  .offset-lg-7 {
    margin-left: 58.33333333%;
  }
  .offset-lg-8 {
    margin-left: 66.66666667%;
  }
  .offset-lg-9 {
    margin-left: 75%;
  }
  .offset-lg-10 {
    margin-left: 83.33333333%;
  }
  .offset-lg-11 {
    margin-left: 91.66666667%;
  }
  .g-lg-0,
  .gx-lg-0 {
    --bs-gutter-x: 0;
  }
  .g-lg-0,
  .gy-lg-0 {
    --bs-gutter-y: 0;
  }
  .g-lg-1,
  .gx-lg-1 {
    --bs-gutter-x: 0.25rem;
  }
  .g-lg-1,
  .gy-lg-1 {
    --bs-gutter-y: 0.25rem;
  }
  .g-lg-2,
  .gx-lg-2 {
    --bs-gutter-x: 0.5rem;
  }
  .g-lg-2,
  .gy-lg-2 {
    --bs-gutter-y: 0.5rem;
  }
  .g-lg-3,
  .gx-lg-3 {
    --bs-gutter-x: 1rem;
  }
  .g-lg-3,
  .gy-lg-3 {
    --bs-gutter-y: 1rem;
  }
  .g-lg-4,
  .gx-lg-4 {
    --bs-gutter-x: 1.5rem;
  }
  .g-lg-4,
  .gy-lg-4 {
    --bs-gutter-y: 1.5rem;
  }
  .g-lg-5,
  .gx-lg-5 {
    --bs-gutter-x: 3rem;
  }
  .g-lg-5,
  .gy-lg-5 {
    --bs-gutter-y: 3rem;
  }
}
@media (min-width: 1200px) {
  .col-xl {
    flex: 1 0 0;
  }
  .row-cols-xl-auto > * {
    flex: 0 0 auto;
    width: auto;
  }
  .row-cols-xl-1 > * {
    flex: 0 0 auto;
    width: 100%;
  }
  .row-cols-xl-2 > * {
    flex: 0 0 auto;
    width: 50%;
  }
  .row-cols-xl-3 > * {
    flex: 0 0 auto;
    width: 33.33333333%;
  }
  .row-cols-xl-4 > * {
    flex: 0 0 auto;
    width: 25%;
  }
  .row-cols-xl-5 > * {
    flex: 0 0 auto;
    width: 20%;
  }
  .row-cols-xl-6 > * {
    flex: 0 0 auto;
    width: 16.66666667%;
  }
  .col-xl-auto {
    flex: 0 0 auto;
    width: auto;
  }
  .col-xl-1 {
    flex: 0 0 auto;
    width: 8.33333333%;
  }
  .col-xl-2 {
    flex: 0 0 auto;
    width: 16.66666667%;
  }
  .col-xl-3 {
    flex: 0 0 auto;
    width: 25%;
  }
  .col-xl-4 {
    flex: 0 0 auto;
    width: 33.33333333%;
  }
  .col-xl-5 {
    flex: 0 0 auto;
    width: 41.66666667%;
  }
  .col-xl-6 {
    flex: 0 0 auto;
    width: 50%;
  }
  .col-xl-7 {
    flex: 0 0 auto;
    width: 58.33333333%;
  }
  .col-xl-8 {
    flex: 0 0 auto;
    width: 66.66666667%;
  }
  .col-xl-9 {
    flex: 0 0 auto;
    width: 75%;
  }
  .col-xl-10 {
    flex: 0 0 auto;
    width: 83.33333333%;
  }
  .col-xl-11 {
    flex: 0 0 auto;
    width: 91.66666667%;
  }
  .col-xl-12 {
    flex: 0 0 auto;
    width: 100%;
  }
  .offset-xl-0 {
    margin-left: 0;
  }
  .offset-xl-1 {
    margin-left: 8.33333333%;
  }
  .offset-xl-2 {
    margin-left: 16.66666667%;
  }
  .offset-xl-3 {
    margin-left: 25%;
  }
  .offset-xl-4 {
    margin-left: 33.33333333%;
  }
  .offset-xl-5 {
    margin-left: 41.66666667%;
  }
  .offset-xl-6 {
    margin-left: 50%;
  }
  .offset-xl-7 {
    margin-left: 58.33333333%;
  }
  .offset-xl-8 {
    margin-left: 66.66666667%;
  }
  .offset-xl-9 {
    margin-left: 75%;
  }
  .offset-xl-10 {
    margin-left: 83.33333333%;
  }
  .offset-xl-11 {
    margin-left: 91.66666667%;
  }
  .g-xl-0,
  .gx-xl-0 {
    --bs-gutter-x: 0;
  }
  .g-xl-0,
  .gy-xl-0 {
    --bs-gutter-y: 0;
  }
  .g-xl-1,
  .gx-xl-1 {
    --bs-gutter-x: 0.25rem;
  }
  .g-xl-1,
  .gy-xl-1 {
    --bs-gutter-y: 0.25rem;
  }
  .g-xl-2,
  .gx-xl-2 {
    --bs-gutter-x: 0.5rem;
  }
  .g-xl-2,
  .gy-xl-2 {
    --bs-gutter-y: 0.5rem;
  }
  .g-xl-3,
  .gx-xl-3 {
    --bs-gutter-x: 1rem;
  }
  .g-xl-3,
  .gy-xl-3 {
    --bs-gutter-y: 1rem;
  }
  .g-xl-4,
  .gx-xl-4 {
    --bs-gutter-x: 1.5rem;
  }
  .g-xl-4,
  .gy-xl-4 {
    --bs-gutter-y: 1.5rem;
  }
  .g-xl-5,
  .gx-xl-5 {
    --bs-gutter-x: 3rem;
  }
  .g-xl-5,
  .gy-xl-5 {
    --bs-gutter-y: 3rem;
  }
}
@media (min-width: 1400px) {
  .col-xxl {
    flex: 1 0 0;
  }
  .row-cols-xxl-auto > * {
    flex: 0 0 auto;
    width: auto;
  }
  .row-cols-xxl-1 > * {
    flex: 0 0 auto;
    width: 100%;
  }
  .row-cols-xxl-2 > * {
    flex: 0 0 auto;
    width: 50%;
  }
  .row-cols-xxl-3 > * {
    flex: 0 0 auto;
    width: 33.33333333%;
  }
  .row-cols-xxl-4 > * {
    flex: 0 0 auto;
    width: 25%;
  }
  .row-cols-xxl-5 > * {
    flex: 0 0 auto;
    width: 20%;
  }
  .row-cols-xxl-6 > * {
    flex: 0 0 auto;
    width: 16.66666667%;
  }
  .col-xxl-auto {
    flex: 0 0 auto;
    width: auto;
  }
  .col-xxl-1 {
    flex: 0 0 auto;
    width: 8.33333333%;
  }
  .col-xxl-2 {
    flex: 0 0 auto;
    width: 16.66666667%;
  }
  .col-xxl-3 {
    flex: 0 0 auto;
    width: 25%;
  }
  .col-xxl-4 {
    flex: 0 0 auto;
    width: 33.33333333%;
  }
  .col-xxl-5 {
    flex: 0 0 auto;
    width: 41.66666667%;
  }
  .col-xxl-6 {
    flex: 0 0 auto;
    width: 50%;
  }
  .col-xxl-7 {
    flex: 0 0 auto;
    width: 58.33333333%;
  }
  .col-xxl-8 {
    flex: 0 0 auto;
    width: 66.66666667%;
  }
  .col-xxl-9 {
    flex: 0 0 auto;
    width: 75%;
  }
  .col-xxl-10 {
    flex: 0 0 auto;
    width: 83.33333333%;
  }
  .col-xxl-11 {
    flex: 0 0 auto;
    width: 91.66666667%;
  }
  .col-xxl-12 {
    flex: 0 0 auto;
    width: 100%;
  }
  .offset-xxl-0 {
    margin-left: 0;
  }
  .offset-xxl-1 {
    margin-left: 8.33333333%;
  }
  .offset-xxl-2 {
    margin-left: 16.66666667%;
  }
  .offset-xxl-3 {
    margin-left: 25%;
  }
  .offset-xxl-4 {
    margin-left: 33.33333333%;
  }
  .offset-xxl-5 {
    margin-left: 41.66666667%;
  }
  .offset-xxl-6 {
    margin-left: 50%;
  }
  .offset-xxl-7 {
    margin-left: 58.33333333%;
  }
  .offset-xxl-8 {
    margin-left: 66.66666667%;
  }
  .offset-xxl-9 {
    margin-left: 75%;
  }
  .offset-xxl-10 {
    margin-left: 83.33333333%;
  }
  .offset-xxl-11 {
    margin-left: 91.66666667%;
  }
  .g-xxl-0,
  .gx-xxl-0 {
    --bs-gutter-x: 0;
  }
  .g-xxl-0,
  .gy-xxl-0 {
    --bs-gutter-y: 0;
  }
  .g-xxl-1,
  .gx-xxl-1 {
    --bs-gutter-x: 0.25rem;
  }
  .g-xxl-1,
  .gy-xxl-1 {
    --bs-gutter-y: 0.25rem;
  }
  .g-xxl-2,
  .gx-xxl-2 {
    --bs-gutter-x: 0.5rem;
  }
  .g-xxl-2,
  .gy-xxl-2 {
    --bs-gutter-y: 0.5rem;
  }
  .g-xxl-3,
  .gx-xxl-3 {
    --bs-gutter-x: 1rem;
  }
  .g-xxl-3,
  .gy-xxl-3 {
    --bs-gutter-y: 1rem;
  }
  .g-xxl-4,
  .gx-xxl-4 {
    --bs-gutter-x: 1.5rem;
  }
  .g-xxl-4,
  .gy-xxl-4 {
    --bs-gutter-y: 1.5rem;
  }
  .g-xxl-5,
  .gx-xxl-5 {
    --bs-gutter-x: 3rem;
  }
  .g-xxl-5,
  .gy-xxl-5 {
    --bs-gutter-y: 3rem;
  }
}
.table {
  --bs-table-color-type: initial;
  --bs-table-bg-type: initial;
  --bs-table-color-state: initial;
  --bs-table-bg-state: initial;
  --bs-table-color: var(--bs-emphasis-color);
  --bs-table-bg: var(--bs-body-bg);
  --bs-table-border-color: var(--bs-border-color);
  --bs-table-accent-bg: transparent;
  --bs-table-striped-color: var(--bs-emphasis-color);
  --bs-table-striped-bg: rgba(var(--bs-emphasis-color-rgb), 0.05);
  --bs-table-active-color: var(--bs-emphasis-color);
  --bs-table-active-bg: rgba(var(--bs-emphasis-color-rgb), 0.1);
  --bs-table-hover-color: var(--bs-emphasis-color);
  --bs-table-hover-bg: rgba(var(--bs-emphasis-color-rgb), 0.075);
  width: 100%;
  margin-bottom: 1rem;
  vertical-align: top;
  border-color: var(--bs-table-border-color);
}
.table > :not(caption) > * > * {
  padding: 0.5rem 0.5rem;
  color: var(--bs-table-color-state, var(--bs-table-color-type, var(--bs-table-color)));
  background-color: var(--bs-table-bg);
  border-bottom-width: var(--bs-border-width);
  box-shadow: inset 0 0 0 9999px var(--bs-table-bg-state, var(--bs-table-bg-type, var(--bs-table-accent-bg)));
}
.table > tbody {
  vertical-align: inherit;
}
.table > thead {
  vertical-align: bottom;
}

.table-group-divider {
  border-top: calc(var(--bs-border-width) * 2) solid currentcolor;
}

.caption-top {
  caption-side: top;
}

.table-sm > :not(caption) > * > * {
  padding: 0.25rem 0.25rem;
}

.table-bordered > :not(caption) > * {
  border-width: var(--bs-border-width) 0;
}
.table-bordered > :not(caption) > * > * {
  border-width: 0 var(--bs-border-width);
}

.table-borderless > :not(caption) > * > * {
  border-bottom-width: 0;
}
.table-borderless > :not(:first-child) {
  border-top-width: 0;
}

.table-striped > tbody > tr:nth-of-type(odd) > * {
  --bs-table-color-type: var(--bs-table-striped-color);
  --bs-table-bg-type: var(--bs-table-striped-bg);
}

.table-striped-columns > :not(caption) > tr > :nth-child(even) {
  --bs-table-color-type: var(--bs-table-striped-color);
  --bs-table-bg-type: var(--bs-table-striped-bg);
}

.table-active {
  --bs-table-color-state: var(--bs-table-active-color);
  --bs-table-bg-state: var(--bs-table-active-bg);
}

.table-hover > tbody > tr:hover > * {
  --bs-table-color-state: var(--bs-table-hover-color);
  --bs-table-bg-state: var(--bs-table-hover-bg);
}

.table-primary {
  --bs-table-color: #000;
  --bs-table-bg: rgb(206.6, 226, 254.6);
  --bs-table-border-color: rgb(165.28, 180.8, 203.68);
  --bs-table-striped-bg: rgb(196.27, 214.7, 241.87);
  --bs-table-striped-color: #000;
  --bs-table-active-bg: rgb(185.94, 203.4, 229.14);
  --bs-table-active-color: #000;
  --bs-table-hover-bg: rgb(191.105, 209.05, 235.505);
  --bs-table-hover-color: #000;
  color: var(--bs-table-color);
  border-color: var(--bs-table-border-color);
}

.table-secondary {
  --bs-table-color: #000;
  --bs-table-bg: rgb(225.6, 227.4, 229);
  --bs-table-border-color: rgb(180.48, 181.92, 183.2);
  --bs-table-striped-bg: rgb(214.32, 216.03, 217.55);
  --bs-table-striped-color: #000;
  --bs-table-active-bg: rgb(203.04, 204.66, 206.1);
  --bs-table-active-color: #000;
  --bs-table-hover-bg: rgb(208.68, 210.345, 211.825);
  --bs-table-hover-color: #000;
  color: var(--bs-table-color);
  border-color: var(--bs-table-border-color);
}

.table-success {
  --bs-table-color: #000;
  --bs-table-bg: rgb(209, 231, 220.8);
  --bs-table-border-color: rgb(167.2, 184.8, 176.64);
  --bs-table-striped-bg: rgb(198.55, 219.45, 209.76);
  --bs-table-striped-color: #000;
  --bs-table-active-bg: rgb(188.1, 207.9, 198.72);
  --bs-table-active-color: #000;
  --bs-table-hover-bg: rgb(193.325, 213.675, 204.24);
  --bs-table-hover-color: #000;
  color: var(--bs-table-color);
  border-color: var(--bs-table-border-color);
}

.table-info {
  --bs-table-color: #000;
  --bs-table-bg: rgb(206.6, 244.4, 252);
  --bs-table-border-color: rgb(165.28, 195.52, 201.6);
  --bs-table-striped-bg: rgb(196.27, 232.18, 239.4);
  --bs-table-striped-color: #000;
  --bs-table-active-bg: rgb(185.94, 219.96, 226.8);
  --bs-table-active-color: #000;
  --bs-table-hover-bg: rgb(191.105, 226.07, 233.1);
  --bs-table-hover-color: #000;
  color: var(--bs-table-color);
  border-color: var(--bs-table-border-color);
}

.table-warning {
  --bs-table-color: #000;
  --bs-table-bg: rgb(255, 242.6, 205.4);
  --bs-table-border-color: rgb(204, 194.08, 164.32);
  --bs-table-striped-bg: rgb(242.25, 230.47, 195.13);
  --bs-table-striped-color: #000;
  --bs-table-active-bg: rgb(229.5, 218.34, 184.86);
  --bs-table-active-color: #000;
  --bs-table-hover-bg: rgb(235.875, 224.405, 189.995);
  --bs-table-hover-color: #000;
  color: var(--bs-table-color);
  border-color: var(--bs-table-border-color);
}

.table-danger {
  --bs-table-color: #000;
  --bs-table-bg: rgb(248, 214.6, 217.8);
  --bs-table-border-color: rgb(198.4, 171.68, 174.24);
  --bs-table-striped-bg: rgb(235.6, 203.87, 206.91);
  --bs-table-striped-color: #000;
  --bs-table-active-bg: rgb(223.2, 193.14, 196.02);
  --bs-table-active-color: #000;
  --bs-table-hover-bg: rgb(229.4, 198.505, 201.465);
  --bs-table-hover-color: #000;
  color: var(--bs-table-color);
  border-color: var(--bs-table-border-color);
}

.table-light {
  --bs-table-color: #000;
  --bs-table-bg: #f8f9fa;
  --bs-table-border-color: rgb(198.4, 199.2, 200);
  --bs-table-striped-bg: rgb(235.6, 236.55, 237.5);
  --bs-table-striped-color: #000;
  --bs-table-active-bg: rgb(223.2, 224.1, 225);
  --bs-table-active-color: #000;
  --bs-table-hover-bg: rgb(229.4, 230.325, 231.25);
  --bs-table-hover-color: #000;
  color: var(--bs-table-color);
  border-color: var(--bs-table-border-color);
}

.table-dark {
  --bs-table-color: #fff;
  --bs-table-bg: #212529;
  --bs-table-border-color: rgb(77.4, 80.6, 83.8);
  --bs-table-striped-bg: rgb(44.1, 47.9, 51.7);
  --bs-table-striped-color: #fff;
  --bs-table-active-bg: rgb(55.2, 58.8, 62.4);
  --bs-table-active-color: #fff;
  --bs-table-hover-bg: rgb(49.65, 53.35, 57.05);
  --bs-table-hover-color: #fff;
  color: var(--bs-table-color);
  border-color: var(--bs-table-border-color);
}

.table-responsive {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

@media (max-width: 575.98px) {
  .table-responsive-sm {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
}
@media (max-width: 767.98px) {
  .table-responsive-md {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
}
@media (max-width: 991.98px) {
  .table-responsive-lg {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
}
@media (max-width: 1199.98px) {
  .table-responsive-xl {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
}
@media (max-width: 1399.98px) {
  .table-responsive-xxl {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
}
.form-label {
  margin-bottom: 0.5rem;
}

.col-form-label {
  padding-top: calc(0.375rem + var(--bs-border-width));
  padding-bottom: calc(0.375rem + var(--bs-border-width));
  margin-bottom: 0;
  font-size: inherit;
  line-height: 1.5;
}

.col-form-label-lg {
  padding-top: calc(0.5rem + var(--bs-border-width));
  padding-bottom: calc(0.5rem + var(--bs-border-width));
  font-size: 1.25rem;
}

.col-form-label-sm {
  padding-top: calc(0.25rem + var(--bs-border-width));
  padding-bottom: calc(0.25rem + var(--bs-border-width));
  font-size: 0.875rem;
}

.form-text {
  margin-top: 0.25rem;
  font-size: 0.875em;
  color: var(--bs-secondary-color);
}

.form-control {
  display: block;
  width: 100%;
  padding: 0.375rem 0.75rem;
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.5;
  color: var(--bs-body-color);
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-color: var(--bs-body-bg);
  background-clip: padding-box;
  border: var(--bs-border-width) solid var(--bs-border-color);
  border-radius: var(--bs-border-radius);
  box-shadow: var(--bs-box-shadow-inset);
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .form-control {
    transition: none;
  }
}
.form-control[type=file] {
  overflow: hidden;
}
.form-control[type=file]:not(:disabled):not([readonly]) {
  cursor: pointer;
}
.form-control:focus {
  color: var(--bs-body-color);
  background-color: var(--bs-body-bg);
  border-color: rgb(134, 182.5, 254);
  outline: 0;
  box-shadow: var(--bs-box-shadow-inset), 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
.form-control::-webkit-date-and-time-value {
  min-width: 85px;
  height: 1.5em;
  margin: 0;
}
.form-control::-webkit-datetime-edit {
  display: block;
  padding: 0;
}
.form-control::-moz-placeholder {
  color: var(--bs-secondary-color);
  opacity: 1;
}
.form-control::placeholder {
  color: var(--bs-secondary-color);
  opacity: 1;
}
.form-control:disabled {
  background-color: var(--bs-secondary-bg);
  opacity: 1;
}
.form-control::file-selector-button {
  padding: 0.375rem 0.75rem;
  margin: -0.375rem -0.75rem;
  margin-inline-end: 0.75rem;
  color: var(--bs-body-color);
  background-color: var(--bs-tertiary-bg);
  pointer-events: none;
  border-color: inherit;
  border-style: solid;
  border-width: 0;
  border-inline-end-width: var(--bs-border-width);
  border-radius: 0;
  transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .form-control::file-selector-button {
    transition: none;
  }
}
.form-control:hover:not(:disabled):not([readonly])::file-selector-button {
  background-color: var(--bs-secondary-bg);
}

.form-control-plaintext {
  display: block;
  width: 100%;
  padding: 0.375rem 0;
  margin-bottom: 0;
  line-height: 1.5;
  color: var(--bs-body-color);
  background-color: transparent;
  border: solid transparent;
  border-width: var(--bs-border-width) 0;
}
.form-control-plaintext:focus {
  outline: 0;
}
.form-control-plaintext.form-control-sm, .form-control-plaintext.form-control-lg {
  padding-right: 0;
  padding-left: 0;
}

.form-control-sm {
  min-height: calc(1.5em + 0.5rem + calc(var(--bs-border-width) * 2));
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
  border-radius: var(--bs-border-radius-sm);
}
.form-control-sm::file-selector-button {
  padding: 0.25rem 0.5rem;
  margin: -0.25rem -0.5rem;
  margin-inline-end: 0.5rem;
}

.form-control-lg {
  min-height: calc(1.5em + 1rem + calc(var(--bs-border-width) * 2));
  padding: 0.5rem 1rem;
  font-size: 1.25rem;
  border-radius: var(--bs-border-radius-lg);
}
.form-control-lg::file-selector-button {
  padding: 0.5rem 1rem;
  margin: -0.5rem -1rem;
  margin-inline-end: 1rem;
}

textarea.form-control {
  min-height: calc(1.5em + 0.75rem + calc(var(--bs-border-width) * 2));
}
textarea.form-control-sm {
  min-height: calc(1.5em + 0.5rem + calc(var(--bs-border-width) * 2));
}
textarea.form-control-lg {
  min-height: calc(1.5em + 1rem + calc(var(--bs-border-width) * 2));
}

.form-control-color {
  width: 3rem;
  height: calc(1.5em + 0.75rem + calc(var(--bs-border-width) * 2));
  padding: 0.375rem;
}
.form-control-color:not(:disabled):not([readonly]) {
  cursor: pointer;
}
.form-control-color::-moz-color-swatch {
  border: 0 !important;
  border-radius: var(--bs-border-radius);
}
.form-control-color::-webkit-color-swatch {
  border: 0 !important;
  border-radius: var(--bs-border-radius);
}
.form-control-color.form-control-sm {
  height: calc(1.5em + 0.5rem + calc(var(--bs-border-width) * 2));
}
.form-control-color.form-control-lg {
  height: calc(1.5em + 1rem + calc(var(--bs-border-width) * 2));
}

.form-select {
  --bs-form-select-bg-img: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
  display: block;
  width: 100%;
  padding: 0.375rem 2.25rem 0.375rem 0.75rem;
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.5;
  color: var(--bs-body-color);
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-color: var(--bs-body-bg);
  background-image: var(--bs-form-select-bg-img), var(--bs-form-select-bg-icon, none);
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  background-size: 16px 12px;
  border: var(--bs-border-width) solid var(--bs-border-color);
  border-radius: var(--bs-border-radius);
  box-shadow: var(--bs-box-shadow-inset);
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .form-select {
    transition: none;
  }
}
.form-select:focus {
  border-color: rgb(134, 182.5, 254);
  outline: 0;
  box-shadow: var(--bs-box-shadow-inset), 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
.form-select[multiple], .form-select[size]:not([size="1"]) {
  padding-right: 0.75rem;
  background-image: none;
}
.form-select:disabled {
  background-color: var(--bs-secondary-bg);
}
.form-select:-moz-focusring {
  color: transparent;
  text-shadow: 0 0 0 var(--bs-body-color);
}

.form-select-sm {
  padding-top: 0.25rem;
  padding-bottom: 0.25rem;
  padding-left: 0.5rem;
  font-size: 0.875rem;
  border-radius: var(--bs-border-radius-sm);
}

.form-select-lg {
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
  padding-left: 1rem;
  font-size: 1.25rem;
  border-radius: var(--bs-border-radius-lg);
}

[data-bs-theme=dark] .form-select {
  --bs-form-select-bg-img: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23dee2e6' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
}

.form-check {
  display: block;
  min-height: 1.5rem;
  padding-left: 1.5em;
  margin-bottom: 0.125rem;
}
.form-check .form-check-input {
  float: left;
  margin-left: -1.5em;
}

.form-check-reverse {
  padding-right: 1.5em;
  padding-left: 0;
  text-align: right;
}
.form-check-reverse .form-check-input {
  float: right;
  margin-right: -1.5em;
  margin-left: 0;
}

.form-check-input {
  --bs-form-check-bg: var(--bs-body-bg);
  flex-shrink: 0;
  width: 1em;
  height: 1em;
  margin-top: 0.25em;
  vertical-align: top;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-color: var(--bs-form-check-bg);
  background-image: var(--bs-form-check-bg-image);
  background-repeat: no-repeat;
  background-position: center;
  background-size: contain;
  border: var(--bs-border-width) solid var(--bs-border-color);
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
.form-check-input[type=checkbox] {
  border-radius: 0.25em;
}
.form-check-input[type=radio] {
  border-radius: 50%;
}
.form-check-input:active {
  filter: brightness(90%);
}
.form-check-input:focus {
  border-color: rgb(134, 182.5, 254);
  outline: 0;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
.form-check-input:checked {
  background-color: #0d6efd;
  border-color: #0d6efd;
}
.form-check-input:checked[type=checkbox] {
  --bs-form-check-bg-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='m6 10 3 3 6-6'/%3e%3c/svg%3e");
}
.form-check-input:checked[type=radio] {
  --bs-form-check-bg-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='2' fill='%23fff'/%3e%3c/svg%3e");
}
.form-check-input[type=checkbox]:indeterminate {
  background-color: #0d6efd;
  border-color: #0d6efd;
  --bs-form-check-bg-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10h8'/%3e%3c/svg%3e");
}
.form-check-input:disabled {
  pointer-events: none;
  filter: none;
  opacity: 0.5;
}
.form-check-input[disabled] ~ .form-check-label, .form-check-input:disabled ~ .form-check-label {
  cursor: default;
  opacity: 0.5;
}

.form-switch {
  padding-left: 2.5em;
}
.form-switch .form-check-input {
  --bs-form-switch-bg: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%280, 0, 0, 0.25%29'/%3e%3c/svg%3e");
  width: 2em;
  margin-left: -2.5em;
  background-image: var(--bs-form-switch-bg);
  background-position: left center;
  border-radius: 2em;
  transition: background-position 0.15s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .form-switch .form-check-input {
    transition: none;
  }
}
.form-switch .form-check-input:focus {
  --bs-form-switch-bg: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgb%28134, 182.5, 254%29'/%3e%3c/svg%3e");
}
.form-switch .form-check-input:checked {
  background-position: right center;
  --bs-form-switch-bg: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e");
}
.form-switch.form-check-reverse {
  padding-right: 2.5em;
  padding-left: 0;
}
.form-switch.form-check-reverse .form-check-input {
  margin-right: -2.5em;
  margin-left: 0;
}

.form-check-inline {
  display: inline-block;
  margin-right: 1rem;
}

.btn-check {
  position: absolute;
  clip: rect(0, 0, 0, 0);
  pointer-events: none;
}
.btn-check[disabled] + .btn, .btn-check:disabled + .btn {
  pointer-events: none;
  filter: none;
  opacity: 0.65;
}

[data-bs-theme=dark] .form-switch .form-check-input:not(:checked):not(:focus) {
  --bs-form-switch-bg: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%28255, 255, 255, 0.25%29'/%3e%3c/svg%3e");
}

.form-range {
  width: 100%;
  height: 1.5rem;
  padding: 0;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-color: transparent;
}
.form-range:focus {
  outline: 0;
}
.form-range:focus::-webkit-slider-thumb {
  box-shadow: 0 0 0 1px #fff, 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
.form-range:focus::-moz-range-thumb {
  box-shadow: 0 0 0 1px #fff, 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
.form-range::-moz-focus-outer {
  border: 0;
}
.form-range::-webkit-slider-thumb {
  width: 1rem;
  height: 1rem;
  margin-top: -0.25rem;
  -webkit-appearance: none;
  appearance: none;
  background-color: #0d6efd;
  border: 0;
  border-radius: 1rem;
  box-shadow: 0 0.1rem 0.25rem rgba(0, 0, 0, 0.1);
  -webkit-transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .form-range::-webkit-slider-thumb {
    -webkit-transition: none;
    transition: none;
  }
}
.form-range::-webkit-slider-thumb:active {
  background-color: rgb(182.4, 211.5, 254.4);
}
.form-range::-webkit-slider-runnable-track {
  width: 100%;
  height: 0.5rem;
  color: transparent;
  cursor: pointer;
  background-color: var(--bs-secondary-bg);
  border-color: transparent;
  border-radius: 1rem;
  box-shadow: var(--bs-box-shadow-inset);
}
.form-range::-moz-range-thumb {
  width: 1rem;
  height: 1rem;
  -moz-appearance: none;
  appearance: none;
  background-color: #0d6efd;
  border: 0;
  border-radius: 1rem;
  box-shadow: 0 0.1rem 0.25rem rgba(0, 0, 0, 0.1);
  -moz-transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .form-range::-moz-range-thumb {
    -moz-transition: none;
    transition: none;
  }
}
.form-range::-moz-range-thumb:active {
  background-color: rgb(182.4, 211.5, 254.4);
}
.form-range::-moz-range-track {
  width: 100%;
  height: 0.5rem;
  color: transparent;
  cursor: pointer;
  background-color: var(--bs-secondary-bg);
  border-color: transparent;
  border-radius: 1rem;
  box-shadow: var(--bs-box-shadow-inset);
}
.form-range:disabled {
  pointer-events: none;
}
.form-range:disabled::-webkit-slider-thumb {
  background-color: var(--bs-secondary-color);
}
.form-range:disabled::-moz-range-thumb {
  background-color: var(--bs-secondary-color);
}

.form-floating {
  position: relative;
}
.form-floating > .form-control,
.form-floating > .form-control-plaintext,
.form-floating > .form-select {
  height: calc(3.5rem + calc(var(--bs-border-width) * 2));
  min-height: calc(3.5rem + calc(var(--bs-border-width) * 2));
  line-height: 1.25;
}
.form-floating > label {
  position: absolute;
  top: 0;
  left: 0;
  z-index: 2;
  max-width: 100%;
  height: 100%;
  padding: 1rem 0.75rem;
  overflow: hidden;
  color: rgba(var(--bs-body-color-rgb), 0.65);
  text-align: start;
  text-overflow: ellipsis;
  white-space: nowrap;
  pointer-events: none;
  border: var(--bs-border-width) solid transparent;
  transform-origin: 0 0;
  transition: opacity 0.1s ease-in-out, transform 0.1s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .form-floating > label {
    transition: none;
  }
}
.form-floating > .form-control,
.form-floating > .form-control-plaintext {
  padding: 1rem 0.75rem;
}
.form-floating > .form-control::-moz-placeholder, .form-floating > .form-control-plaintext::-moz-placeholder {
  color: transparent;
}
.form-floating > .form-control::placeholder,
.form-floating > .form-control-plaintext::placeholder {
  color: transparent;
}
.form-floating > .form-control:not(:-moz-placeholder), .form-floating > .form-control-plaintext:not(:-moz-placeholder) {
  padding-top: 1.625rem;
  padding-bottom: 0.625rem;
}
.form-floating > .form-control:focus, .form-floating > .form-control:not(:placeholder-shown),
.form-floating > .form-control-plaintext:focus,
.form-floating > .form-control-plaintext:not(:placeholder-shown) {
  padding-top: 1.625rem;
  padding-bottom: 0.625rem;
}
.form-floating > .form-control:-webkit-autofill,
.form-floating > .form-control-plaintext:-webkit-autofill {
  padding-top: 1.625rem;
  padding-bottom: 0.625rem;
}
.form-floating > .form-select {
  padding-top: 1.625rem;
  padding-bottom: 0.625rem;
  padding-left: 0.75rem;
}
.form-floating > .form-control:not(:-moz-placeholder) ~ label {
  transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
}
.form-floating > .form-control:focus ~ label,
.form-floating > .form-control:not(:placeholder-shown) ~ label,
.form-floating > .form-control-plaintext ~ label,
.form-floating > .form-select ~ label {
  transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
}
.form-floating > .form-control:-webkit-autofill ~ label {
  transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
}
.form-floating > textarea:not(:-moz-placeholder) ~ label::after {
  position: absolute;
  inset: 1rem 0.375rem;
  z-index: -1;
  height: 1.5em;
  content: "";
  background-color: var(--bs-body-bg);
  border-radius: var(--bs-border-radius);
}
.form-floating > textarea:focus ~ label::after,
.form-floating > textarea:not(:placeholder-shown) ~ label::after {
  position: absolute;
  inset: 1rem 0.375rem;
  z-index: -1;
  height: 1.5em;
  content: "";
  background-color: var(--bs-body-bg);
  border-radius: var(--bs-border-radius);
}
.form-floating > textarea:disabled ~ label::after {
  background-color: var(--bs-secondary-bg);
}
.form-floating > .form-control-plaintext ~ label {
  border-width: var(--bs-border-width) 0;
}
.form-floating > :disabled ~ label,
.form-floating > .form-control:disabled ~ label {
  color: #6c757d;
}

.input-group {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: stretch;
  width: 100%;
}
.input-group > .form-control,
.input-group > .form-select,
.input-group > .form-floating {
  position: relative;
  flex: 1 1 auto;
  width: 1%;
  min-width: 0;
}
.input-group > .form-control:focus,
.input-group > .form-select:focus,
.input-group > .form-floating:focus-within {
  z-index: 5;
}
.input-group .btn {
  position: relative;
  z-index: 2;
}
.input-group .btn:focus {
  z-index: 5;
}

.input-group-text {
  display: flex;
  align-items: center;
  padding: 0.375rem 0.75rem;
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.5;
  color: var(--bs-body-color);
  text-align: center;
  white-space: nowrap;
  background-color: var(--bs-tertiary-bg);
  border: var(--bs-border-width) solid var(--bs-border-color);
  border-radius: var(--bs-border-radius);
}

.input-group-lg > .form-control,
.input-group-lg > .form-select,
.input-group-lg > .input-group-text,
.input-group-lg > .btn {
  padding: 0.5rem 1rem;
  font-size: 1.25rem;
  border-radius: var(--bs-border-radius-lg);
}

.input-group-sm > .form-control,
.input-group-sm > .form-select,
.input-group-sm > .input-group-text,
.input-group-sm > .btn {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
  border-radius: var(--bs-border-radius-sm);
}

.input-group-lg > .form-select,
.input-group-sm > .form-select {
  padding-right: 3rem;
}

.input-group:not(.has-validation) > :not(:last-child):not(.dropdown-toggle):not(.dropdown-menu):not(.form-floating),
.input-group:not(.has-validation) > .dropdown-toggle:nth-last-child(n+3),
.input-group:not(.has-validation) > .form-floating:not(:last-child) > .form-control,
.input-group:not(.has-validation) > .form-floating:not(:last-child) > .form-select {
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}
.input-group.has-validation > :nth-last-child(n+3):not(.dropdown-toggle):not(.dropdown-menu):not(.form-floating),
.input-group.has-validation > .dropdown-toggle:nth-last-child(n+4),
.input-group.has-validation > .form-floating:nth-last-child(n+3) > .form-control,
.input-group.has-validation > .form-floating:nth-last-child(n+3) > .form-select {
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}
.input-group > :not(:first-child):not(.dropdown-menu):not(.valid-tooltip):not(.valid-feedback):not(.invalid-tooltip):not(.invalid-feedback) {
  margin-left: calc(-1 * var(--bs-border-width));
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
}
.input-group > .form-floating:not(:first-child) > .form-control,
.input-group > .form-floating:not(:first-child) > .form-select {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
}

.valid-feedback {
  display: none;
  width: 100%;
  margin-top: 0.25rem;
  font-size: 0.875em;
  color: var(--bs-form-valid-color);
}

.valid-tooltip {
  position: absolute;
  top: 100%;
  z-index: 5;
  display: none;
  max-width: 100%;
  padding: 0.25rem 0.5rem;
  margin-top: 0.1rem;
  font-size: 0.875rem;
  color: #fff;
  background-color: var(--bs-success);
  border-radius: var(--bs-border-radius);
}

.was-validated :valid ~ .valid-feedback,
.was-validated :valid ~ .valid-tooltip,
.is-valid ~ .valid-feedback,
.is-valid ~ .valid-tooltip {
  display: block;
}

.was-validated .form-control:valid, .form-control.is-valid {
  border-color: var(--bs-form-valid-border-color);
  padding-right: calc(1.5em + 0.75rem);
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right calc(0.375em + 0.1875rem) center;
  background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}
.was-validated .form-control:valid:focus, .form-control.is-valid:focus {
  border-color: var(--bs-form-valid-border-color);
  box-shadow: var(--bs-box-shadow-inset), 0 0 0 0.25rem rgba(var(--bs-success-rgb), 0.25);
}

.was-validated textarea.form-control:valid, textarea.form-control.is-valid {
  padding-right: calc(1.5em + 0.75rem);
  background-position: top calc(0.375em + 0.1875rem) right calc(0.375em + 0.1875rem);
}

.was-validated .form-select:valid, .form-select.is-valid {
  border-color: var(--bs-form-valid-border-color);
}
.was-validated .form-select:valid:not([multiple]):not([size]), .was-validated .form-select:valid:not([multiple])[size="1"], .form-select.is-valid:not([multiple]):not([size]), .form-select.is-valid:not([multiple])[size="1"] {
  --bs-form-select-bg-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1'/%3e%3c/svg%3e");
  padding-right: 4.125rem;
  background-position: right 0.75rem center, center right 2.25rem;
  background-size: 16px 12px, calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}
.was-validated .form-select:valid:focus, .form-select.is-valid:focus {
  border-color: var(--bs-form-valid-border-color);
  box-shadow: var(--bs-box-shadow-inset), 0 0 0 0.25rem rgba(var(--bs-success-rgb), 0.25);
}

.was-validated .form-control-color:valid, .form-control-color.is-valid {
  width: calc(3rem + calc(1.5em + 0.75rem));
}

.was-validated .form-check-input:valid, .form-check-input.is-valid {
  border-color: var(--bs-form-valid-border-color);
}
.was-validated .form-check-input:valid:checked, .form-check-input.is-valid:checked {
  background-color: var(--bs-form-valid-color);
}
.was-validated .form-check-input:valid:focus, .form-check-input.is-valid:focus {
  box-shadow: 0 0 0 0.25rem rgba(var(--bs-success-rgb), 0.25);
}
.was-validated .form-check-input:valid ~ .form-check-label, .form-check-input.is-valid ~ .form-check-label {
  color: var(--bs-form-valid-color);
}

.form-check-inline .form-check-input ~ .valid-feedback {
  margin-left: 0.5em;
}

.was-validated .input-group > .form-control:not(:focus):valid, .input-group > .form-control:not(:focus).is-valid,
.was-validated .input-group > .form-select:not(:focus):valid,
.input-group > .form-select:not(:focus).is-valid,
.was-validated .input-group > .form-floating:not(:focus-within):valid,
.input-group > .form-floating:not(:focus-within).is-valid {
  z-index: 3;
}

.invalid-feedback {
  display: none;
  width: 100%;
  margin-top: 0.25rem;
  font-size: 0.875em;
  color: var(--bs-form-invalid-color);
}

.invalid-tooltip {
  position: absolute;
  top: 100%;
  z-index: 5;
  display: none;
  max-width: 100%;
  padding: 0.25rem 0.5rem;
  margin-top: 0.1rem;
  font-size: 0.875rem;
  color: #fff;
  background-color: var(--bs-danger);
  border-radius: var(--bs-border-radius);
}

.was-validated :invalid ~ .invalid-feedback,
.was-validated :invalid ~ .invalid-tooltip,
.is-invalid ~ .invalid-feedback,
.is-invalid ~ .invalid-tooltip {
  display: block;
}

.was-validated .form-control:invalid, .form-control.is-invalid {
  border-color: var(--bs-form-invalid-border-color);
  padding-right: calc(1.5em + 0.75rem);
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right calc(0.375em + 0.1875rem) center;
  background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}
.was-validated .form-control:invalid:focus, .form-control.is-invalid:focus {
  border-color: var(--bs-form-invalid-border-color);
  box-shadow: var(--bs-box-shadow-inset), 0 0 0 0.25rem rgba(var(--bs-danger-rgb), 0.25);
}

.was-validated textarea.form-control:invalid, textarea.form-control.is-invalid {
  padding-right: calc(1.5em + 0.75rem);
  background-position: top calc(0.375em + 0.1875rem) right calc(0.375em + 0.1875rem);
}

.was-validated .form-select:invalid, .form-select.is-invalid {
  border-color: var(--bs-form-invalid-border-color);
}
.was-validated .form-select:invalid:not([multiple]):not([size]), .was-validated .form-select:invalid:not([multiple])[size="1"], .form-select.is-invalid:not([multiple]):not([size]), .form-select.is-invalid:not([multiple])[size="1"] {
  --bs-form-select-bg-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
  padding-right: 4.125rem;
  background-position: right 0.75rem center, center right 2.25rem;
  background-size: 16px 12px, calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}
.was-validated .form-select:invalid:focus, .form-select.is-invalid:focus {
  border-color: var(--bs-form-invalid-border-color);
  box-shadow: var(--bs-box-shadow-inset), 0 0 0 0.25rem rgba(var(--bs-danger-rgb), 0.25);
}

.was-validated .form-control-color:invalid, .form-control-color.is-invalid {
  width: calc(3rem + calc(1.5em + 0.75rem));
}

.was-validated .form-check-input:invalid, .form-check-input.is-invalid {
  border-color: var(--bs-form-invalid-border-color);
}
.was-validated .form-check-input:invalid:checked, .form-check-input.is-invalid:checked {
  background-color: var(--bs-form-invalid-color);
}
.was-validated .form-check-input:invalid:focus, .form-check-input.is-invalid:focus {
  box-shadow: 0 0 0 0.25rem rgba(var(--bs-danger-rgb), 0.25);
}
.was-validated .form-check-input:invalid ~ .form-check-label, .form-check-input.is-invalid ~ .form-check-label {
  color: var(--bs-form-invalid-color);
}

.form-check-inline .form-check-input ~ .invalid-feedback {
  margin-left: 0.5em;
}

.was-validated .input-group > .form-control:not(:focus):invalid, .input-group > .form-control:not(:focus).is-invalid,
.was-validated .input-group > .form-select:not(:focus):invalid,
.input-group > .form-select:not(:focus).is-invalid,
.was-validated .input-group > .form-floating:not(:focus-within):invalid,
.input-group > .form-floating:not(:focus-within).is-invalid {
  z-index: 4;
}

.btn {
  --bs-btn-padding-x: 0.75rem;
  --bs-btn-padding-y: 0.375rem;
  --bs-btn-font-family: ;
  --bs-btn-font-size: 1rem;
  --bs-btn-font-weight: 400;
  --bs-btn-line-height: 1.5;
  --bs-btn-color: var(--bs-body-color);
  --bs-btn-bg: transparent;
  --bs-btn-border-width: var(--bs-border-width);
  --bs-btn-border-color: transparent;
  --bs-btn-border-radius: var(--bs-border-radius);
  --bs-btn-hover-border-color: transparent;
  --bs-btn-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.15), 0 1px 1px rgba(0, 0, 0, 0.075);
  --bs-btn-disabled-opacity: 0.65;
  --bs-btn-focus-box-shadow: 0 0 0 0.25rem rgba(var(--bs-btn-focus-shadow-rgb), .5);
  display: inline-block;
  padding: var(--bs-btn-padding-y) var(--bs-btn-padding-x);
  font-family: var(--bs-btn-font-family);
  font-size: var(--bs-btn-font-size);
  font-weight: var(--bs-btn-font-weight);
  line-height: var(--bs-btn-line-height);
  color: var(--bs-btn-color);
  text-align: center;
  text-decoration: none;
  vertical-align: middle;
  cursor: pointer;
  -webkit-user-select: none;
  -moz-user-select: none;
  user-select: none;
  border: var(--bs-btn-border-width) solid var(--bs-btn-border-color);
  border-radius: var(--bs-btn-border-radius);
  background-color: var(--bs-btn-bg);
  box-shadow: var(--bs-btn-box-shadow);
  transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .btn {
    transition: none;
  }
}
.btn:hover {
  color: var(--bs-btn-hover-color);
  background-color: var(--bs-btn-hover-bg);
  border-color: var(--bs-btn-hover-border-color);
}
.btn-check + .btn:hover {
  color: var(--bs-btn-color);
  background-color: var(--bs-btn-bg);
  border-color: var(--bs-btn-border-color);
}
.btn:focus-visible {
  color: var(--bs-btn-hover-color);
  background-color: var(--bs-btn-hover-bg);
  border-color: var(--bs-btn-hover-border-color);
  outline: 0;
  box-shadow: var(--bs-btn-box-shadow), var(--bs-btn-focus-box-shadow);
}
.btn-check:focus-visible + .btn {
  border-color: var(--bs-btn-hover-border-color);
  outline: 0;
  box-shadow: var(--bs-btn-box-shadow), var(--bs-btn-focus-box-shadow);
}
.btn-check:checked + .btn, :not(.btn-check) + .btn:active, .btn:first-child:active, .btn.active, .btn.show {
  color: var(--bs-btn-active-color);
  background-color: var(--bs-btn-active-bg);
  border-color: var(--bs-btn-active-border-color);
  box-shadow: var(--bs-btn-active-shadow);
}
.btn-check:checked + .btn:focus-visible, :not(.btn-check) + .btn:active:focus-visible, .btn:first-child:active:focus-visible, .btn.active:focus-visible, .btn.show:focus-visible {
  box-shadow: var(--bs-btn-active-shadow), var(--bs-btn-focus-box-shadow);
}
.btn-check:checked:focus-visible + .btn {
  box-shadow: var(--bs-btn-active-shadow), var(--bs-btn-focus-box-shadow);
}
.btn:disabled, .btn.disabled, fieldset:disabled .btn {
  color: var(--bs-btn-disabled-color);
  pointer-events: none;
  background-color: var(--bs-btn-disabled-bg);
  border-color: var(--bs-btn-disabled-border-color);
  opacity: var(--bs-btn-disabled-opacity);
  box-shadow: none;
}

.btn-primary {
  --bs-btn-color: #fff;
  --bs-btn-bg: #0d6efd;
  --bs-btn-border-color: #0d6efd;
  --bs-btn-hover-color: #fff;
  --bs-btn-hover-bg: rgb(11.05, 93.5, 215.05);
  --bs-btn-hover-border-color: rgb(10.4, 88, 202.4);
  --bs-btn-focus-shadow-rgb: 49, 132, 253;
  --bs-btn-active-color: #fff;
  --bs-btn-active-bg: rgb(10.4, 88, 202.4);
  --bs-btn-active-border-color: rgb(9.75, 82.5, 189.75);
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #fff;
  --bs-btn-disabled-bg: #0d6efd;
  --bs-btn-disabled-border-color: #0d6efd;
}

.btn-secondary {
  --bs-btn-color: #fff;
  --bs-btn-bg: #6c757d;
  --bs-btn-border-color: #6c757d;
  --bs-btn-hover-color: #fff;
  --bs-btn-hover-bg: rgb(91.8, 99.45, 106.25);
  --bs-btn-hover-border-color: rgb(86.4, 93.6, 100);
  --bs-btn-focus-shadow-rgb: 130, 138, 145;
  --bs-btn-active-color: #fff;
  --bs-btn-active-bg: rgb(86.4, 93.6, 100);
  --bs-btn-active-border-color: rgb(81, 87.75, 93.75);
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #fff;
  --bs-btn-disabled-bg: #6c757d;
  --bs-btn-disabled-border-color: #6c757d;
}

.btn-success {
  --bs-btn-color: #fff;
  --bs-btn-bg: #198754;
  --bs-btn-border-color: #198754;
  --bs-btn-hover-color: #fff;
  --bs-btn-hover-bg: rgb(21.25, 114.75, 71.4);
  --bs-btn-hover-border-color: rgb(20, 108, 67.2);
  --bs-btn-focus-shadow-rgb: 60, 153, 110;
  --bs-btn-active-color: #fff;
  --bs-btn-active-bg: rgb(20, 108, 67.2);
  --bs-btn-active-border-color: rgb(18.75, 101.25, 63);
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #fff;
  --bs-btn-disabled-bg: #198754;
  --bs-btn-disabled-border-color: #198754;
}

.btn-info {
  --bs-btn-color: #000;
  --bs-btn-bg: #0dcaf0;
  --bs-btn-border-color: #0dcaf0;
  --bs-btn-hover-color: #000;
  --bs-btn-hover-bg: rgb(49.3, 209.95, 242.25);
  --bs-btn-hover-border-color: rgb(37.2, 207.3, 241.5);
  --bs-btn-focus-shadow-rgb: 11, 172, 204;
  --bs-btn-active-color: #000;
  --bs-btn-active-bg: rgb(61.4, 212.6, 243);
  --bs-btn-active-border-color: rgb(37.2, 207.3, 241.5);
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #000;
  --bs-btn-disabled-bg: #0dcaf0;
  --bs-btn-disabled-border-color: #0dcaf0;
}

.btn-warning {
  --bs-btn-color: #000;
  --bs-btn-bg: #ffc107;
  --bs-btn-border-color: #ffc107;
  --bs-btn-hover-color: #000;
  --bs-btn-hover-bg: rgb(255, 202.3, 44.2);
  --bs-btn-hover-border-color: rgb(255, 199.2, 31.8);
  --bs-btn-focus-shadow-rgb: 217, 164, 6;
  --bs-btn-active-color: #000;
  --bs-btn-active-bg: rgb(255, 205.4, 56.6);
  --bs-btn-active-border-color: rgb(255, 199.2, 31.8);
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #000;
  --bs-btn-disabled-bg: #ffc107;
  --bs-btn-disabled-border-color: #ffc107;
}

.btn-danger {
  --bs-btn-color: #fff;
  --bs-btn-bg: #dc3545;
  --bs-btn-border-color: #dc3545;
  --bs-btn-hover-color: #fff;
  --bs-btn-hover-bg: rgb(187, 45.05, 58.65);
  --bs-btn-hover-border-color: rgb(176, 42.4, 55.2);
  --bs-btn-focus-shadow-rgb: 225, 83, 97;
  --bs-btn-active-color: #fff;
  --bs-btn-active-bg: rgb(176, 42.4, 55.2);
  --bs-btn-active-border-color: rgb(165, 39.75, 51.75);
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #fff;
  --bs-btn-disabled-bg: #dc3545;
  --bs-btn-disabled-border-color: #dc3545;
}

.btn-light {
  --bs-btn-color: #000;
  --bs-btn-bg: #f8f9fa;
  --bs-btn-border-color: #f8f9fa;
  --bs-btn-hover-color: #000;
  --bs-btn-hover-bg: rgb(210.8, 211.65, 212.5);
  --bs-btn-hover-border-color: rgb(198.4, 199.2, 200);
  --bs-btn-focus-shadow-rgb: 211, 212, 213;
  --bs-btn-active-color: #000;
  --bs-btn-active-bg: rgb(198.4, 199.2, 200);
  --bs-btn-active-border-color: rgb(186, 186.75, 187.5);
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #000;
  --bs-btn-disabled-bg: #f8f9fa;
  --bs-btn-disabled-border-color: #f8f9fa;
}

.btn-dark {
  --bs-btn-color: #fff;
  --bs-btn-bg: #212529;
  --bs-btn-border-color: #212529;
  --bs-btn-hover-color: #fff;
  --bs-btn-hover-bg: rgb(66.3, 69.7, 73.1);
  --bs-btn-hover-border-color: rgb(55.2, 58.8, 62.4);
  --bs-btn-focus-shadow-rgb: 66, 70, 73;
  --bs-btn-active-color: #fff;
  --bs-btn-active-bg: rgb(77.4, 80.6, 83.8);
  --bs-btn-active-border-color: rgb(55.2, 58.8, 62.4);
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #fff;
  --bs-btn-disabled-bg: #212529;
  --bs-btn-disabled-border-color: #212529;
}

.btn-outline-primary {
  --bs-btn-color: #0d6efd;
  --bs-btn-border-color: #0d6efd;
  --bs-btn-hover-color: #fff;
  --bs-btn-hover-bg: #0d6efd;
  --bs-btn-hover-border-color: #0d6efd;
  --bs-btn-focus-shadow-rgb: 13, 110, 253;
  --bs-btn-active-color: #fff;
  --bs-btn-active-bg: #0d6efd;
  --bs-btn-active-border-color: #0d6efd;
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #0d6efd;
  --bs-btn-disabled-bg: transparent;
  --bs-btn-disabled-border-color: #0d6efd;
  --bs-gradient: none;
}

.btn-outline-secondary {
  --bs-btn-color: #6c757d;
  --bs-btn-border-color: #6c757d;
  --bs-btn-hover-color: #fff;
  --bs-btn-hover-bg: #6c757d;
  --bs-btn-hover-border-color: #6c757d;
  --bs-btn-focus-shadow-rgb: 108, 117, 125;
  --bs-btn-active-color: #fff;
  --bs-btn-active-bg: #6c757d;
  --bs-btn-active-border-color: #6c757d;
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #6c757d;
  --bs-btn-disabled-bg: transparent;
  --bs-btn-disabled-border-color: #6c757d;
  --bs-gradient: none;
}

.btn-outline-success {
  --bs-btn-color: #198754;
  --bs-btn-border-color: #198754;
  --bs-btn-hover-color: #fff;
  --bs-btn-hover-bg: #198754;
  --bs-btn-hover-border-color: #198754;
  --bs-btn-focus-shadow-rgb: 25, 135, 84;
  --bs-btn-active-color: #fff;
  --bs-btn-active-bg: #198754;
  --bs-btn-active-border-color: #198754;
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #198754;
  --bs-btn-disabled-bg: transparent;
  --bs-btn-disabled-border-color: #198754;
  --bs-gradient: none;
}

.btn-outline-info {
  --bs-btn-color: #0dcaf0;
  --bs-btn-border-color: #0dcaf0;
  --bs-btn-hover-color: #000;
  --bs-btn-hover-bg: #0dcaf0;
  --bs-btn-hover-border-color: #0dcaf0;
  --bs-btn-focus-shadow-rgb: 13, 202, 240;
  --bs-btn-active-color: #000;
  --bs-btn-active-bg: #0dcaf0;
  --bs-btn-active-border-color: #0dcaf0;
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #0dcaf0;
  --bs-btn-disabled-bg: transparent;
  --bs-btn-disabled-border-color: #0dcaf0;
  --bs-gradient: none;
}

.btn-outline-warning {
  --bs-btn-color: #ffc107;
  --bs-btn-border-color: #ffc107;
  --bs-btn-hover-color: #000;
  --bs-btn-hover-bg: #ffc107;
  --bs-btn-hover-border-color: #ffc107;
  --bs-btn-focus-shadow-rgb: 255, 193, 7;
  --bs-btn-active-color: #000;
  --bs-btn-active-bg: #ffc107;
  --bs-btn-active-border-color: #ffc107;
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #ffc107;
  --bs-btn-disabled-bg: transparent;
  --bs-btn-disabled-border-color: #ffc107;
  --bs-gradient: none;
}

.btn-outline-danger {
  --bs-btn-color: #dc3545;
  --bs-btn-border-color: #dc3545;
  --bs-btn-hover-color: #fff;
  --bs-btn-hover-bg: #dc3545;
  --bs-btn-hover-border-color: #dc3545;
  --bs-btn-focus-shadow-rgb: 220, 53, 69;
  --bs-btn-active-color: #fff;
  --bs-btn-active-bg: #dc3545;
  --bs-btn-active-border-color: #dc3545;
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #dc3545;
  --bs-btn-disabled-bg: transparent;
  --bs-btn-disabled-border-color: #dc3545;
  --bs-gradient: none;
}

.btn-outline-light {
  --bs-btn-color: #f8f9fa;
  --bs-btn-border-color: #f8f9fa;
  --bs-btn-hover-color: #000;
  --bs-btn-hover-bg: #f8f9fa;
  --bs-btn-hover-border-color: #f8f9fa;
  --bs-btn-focus-shadow-rgb: 248, 249, 250;
  --bs-btn-active-color: #000;
  --bs-btn-active-bg: #f8f9fa;
  --bs-btn-active-border-color: #f8f9fa;
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #f8f9fa;
  --bs-btn-disabled-bg: transparent;
  --bs-btn-disabled-border-color: #f8f9fa;
  --bs-gradient: none;
}

.btn-outline-dark {
  --bs-btn-color: #212529;
  --bs-btn-border-color: #212529;
  --bs-btn-hover-color: #fff;
  --bs-btn-hover-bg: #212529;
  --bs-btn-hover-border-color: #212529;
  --bs-btn-focus-shadow-rgb: 33, 37, 41;
  --bs-btn-active-color: #fff;
  --bs-btn-active-bg: #212529;
  --bs-btn-active-border-color: #212529;
  --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
  --bs-btn-disabled-color: #212529;
  --bs-btn-disabled-bg: transparent;
  --bs-btn-disabled-border-color: #212529;
  --bs-gradient: none;
}

.btn-link {
  --bs-btn-font-weight: 400;
  --bs-btn-color: var(--bs-link-color);
  --bs-btn-bg: transparent;
  --bs-btn-border-color: transparent;
  --bs-btn-hover-color: var(--bs-link-hover-color);
  --bs-btn-hover-border-color: transparent;
  --bs-btn-active-color: var(--bs-link-hover-color);
  --bs-btn-active-border-color: transparent;
  --bs-btn-disabled-color: #6c757d;
  --bs-btn-disabled-border-color: transparent;
  --bs-btn-box-shadow: 0 0 0 #000;
  --bs-btn-focus-shadow-rgb: 49, 132, 253;
  text-decoration: underline;
}
.btn-link:focus-visible {
  color: var(--bs-btn-color);
}
.btn-link:hover {
  color: var(--bs-btn-hover-color);
}

.btn-lg, .btn-group-lg > .btn {
  --bs-btn-padding-y: 0.5rem;
  --bs-btn-padding-x: 1rem;
  --bs-btn-font-size: 1.25rem;
  --bs-btn-border-radius: var(--bs-border-radius-lg);
}

.btn-sm, .btn-group-sm > .btn {
  --bs-btn-padding-y: 0.25rem;
  --bs-btn-padding-x: 0.5rem;
  --bs-btn-font-size: 0.875rem;
  --bs-btn-border-radius: var(--bs-border-radius-sm);
}

.fade {
  transition: opacity 0.15s linear;
}
@media (prefers-reduced-motion: reduce) {
  .fade {
    transition: none;
  }
}
.fade:not(.show) {
  opacity: 0;
}

.collapse:not(.show) {
  display: none;
}

.collapsing {
  height: 0;
  overflow: hidden;
  transition: height 0.35s ease;
}
@media (prefers-reduced-motion: reduce) {
  .collapsing {
    transition: none;
  }
}
.collapsing.collapse-horizontal {
  width: 0;
  height: auto;
  transition: width 0.35s ease;
}
@media (prefers-reduced-motion: reduce) {
  .collapsing.collapse-horizontal {
    transition: none;
  }
}

.dropup,
.dropend,
.dropdown,
.dropstart,
.dropup-center,
.dropdown-center {
  position: relative;
}

.dropdown-toggle {
  white-space: nowrap;
}
.dropdown-toggle::after {
  display: inline-block;
  margin-left: 0.255em;
  vertical-align: 0.255em;
  content: "";
  border-top: 0.3em solid;
  border-right: 0.3em solid transparent;
  border-bottom: 0;
  border-left: 0.3em solid transparent;
}
.dropdown-toggle:empty::after {
  margin-left: 0;
}

.dropdown-menu {
  --bs-dropdown-zindex: 1000;
  --bs-dropdown-min-width: 10rem;
  --bs-dropdown-padding-x: 0;
  --bs-dropdown-padding-y: 0.5rem;
  --bs-dropdown-spacer: 0.125rem;
  --bs-dropdown-font-size: 1rem;
  --bs-dropdown-color: var(--bs-body-color);
  --bs-dropdown-bg: var(--bs-body-bg);
  --bs-dropdown-border-color: var(--bs-border-color-translucent);
  --bs-dropdown-border-radius: var(--bs-border-radius);
  --bs-dropdown-border-width: var(--bs-border-width);
  --bs-dropdown-inner-border-radius: calc(var(--bs-border-radius) - var(--bs-border-width));
  --bs-dropdown-divider-bg: var(--bs-border-color-translucent);
  --bs-dropdown-divider-margin-y: 0.5rem;
  --bs-dropdown-box-shadow: var(--bs-box-shadow);
  --bs-dropdown-link-color: var(--bs-body-color);
  --bs-dropdown-link-hover-color: var(--bs-body-color);
  --bs-dropdown-link-hover-bg: var(--bs-tertiary-bg);
  --bs-dropdown-link-active-color: #fff;
  --bs-dropdown-link-active-bg: #0d6efd;
  --bs-dropdown-link-disabled-color: var(--bs-tertiary-color);
  --bs-dropdown-item-padding-x: 1rem;
  --bs-dropdown-item-padding-y: 0.25rem;
  --bs-dropdown-header-color: #6c757d;
  --bs-dropdown-header-padding-x: 1rem;
  --bs-dropdown-header-padding-y: 0.5rem;
  position: absolute;
  z-index: var(--bs-dropdown-zindex);
  display: none;
  min-width: var(--bs-dropdown-min-width);
  padding: var(--bs-dropdown-padding-y) var(--bs-dropdown-padding-x);
  margin: 0;
  font-size: var(--bs-dropdown-font-size);
  color: var(--bs-dropdown-color);
  text-align: left;
  list-style: none;
  background-color: var(--bs-dropdown-bg);
  background-clip: padding-box;
  border: var(--bs-dropdown-border-width) solid var(--bs-dropdown-border-color);
  border-radius: var(--bs-dropdown-border-radius);
  box-shadow: var(--bs-dropdown-box-shadow);
}
.dropdown-menu[data-bs-popper] {
  top: 100%;
  left: 0;
  margin-top: var(--bs-dropdown-spacer);
}

.dropdown-menu-start {
  --bs-position: start;
}
.dropdown-menu-start[data-bs-popper] {
  right: auto;
  left: 0;
}

.dropdown-menu-end {
  --bs-position: end;
}
.dropdown-menu-end[data-bs-popper] {
  right: 0;
  left: auto;
}

@media (min-width: 576px) {
  .dropdown-menu-sm-start {
    --bs-position: start;
  }
  .dropdown-menu-sm-start[data-bs-popper] {
    right: auto;
    left: 0;
  }
  .dropdown-menu-sm-end {
    --bs-position: end;
  }
  .dropdown-menu-sm-end[data-bs-popper] {
    right: 0;
    left: auto;
  }
}
@media (min-width: 768px) {
  .dropdown-menu-md-start {
    --bs-position: start;
  }
  .dropdown-menu-md-start[data-bs-popper] {
    right: auto;
    left: 0;
  }
  .dropdown-menu-md-end {
    --bs-position: end;
  }
  .dropdown-menu-md-end[data-bs-popper] {
    right: 0;
    left: auto;
  }
}
@media (min-width: 992px) {
  .dropdown-menu-lg-start {
    --bs-position: start;
  }
  .dropdown-menu-lg-start[data-bs-popper] {
    right: auto;
    left: 0;
  }
  .dropdown-menu-lg-end {
    --bs-position: end;
  }
  .dropdown-menu-lg-end[data-bs-popper] {
    right: 0;
    left: auto;
  }
}
@media (min-width: 1200px) {
  .dropdown-menu-xl-start {
    --bs-position: start;
  }
  .dropdown-menu-xl-start[data-bs-popper] {
    right: auto;
    left: 0;
  }
  .dropdown-menu-xl-end {
    --bs-position: end;
  }
  .dropdown-menu-xl-end[data-bs-popper] {
    right: 0;
    left: auto;
  }
}
@media (min-width: 1400px) {
  .dropdown-menu-xxl-start {
    --bs-position: start;
  }
  .dropdown-menu-xxl-start[data-bs-popper] {
    right: auto;
    left: 0;
  }
  .dropdown-menu-xxl-end {
    --bs-position: end;
  }
  .dropdown-menu-xxl-end[data-bs-popper] {
    right: 0;
    left: auto;
  }
}
.dropup .dropdown-menu[data-bs-popper] {
  top: auto;
  bottom: 100%;
  margin-top: 0;
  margin-bottom: var(--bs-dropdown-spacer);
}
.dropup .dropdown-toggle::after {
  display: inline-block;
  margin-left: 0.255em;
  vertical-align: 0.255em;
  content: "";
  border-top: 0;
  border-right: 0.3em solid transparent;
  border-bottom: 0.3em solid;
  border-left: 0.3em solid transparent;
}
.dropup .dropdown-toggle:empty::after {
  margin-left: 0;
}

.dropend .dropdown-menu[data-bs-popper] {
  top: 0;
  right: auto;
  left: 100%;
  margin-top: 0;
  margin-left: var(--bs-dropdown-spacer);
}
.dropend .dropdown-toggle::after {
  display: inline-block;
  margin-left: 0.255em;
  vertical-align: 0.255em;
  content: "";
  border-top: 0.3em solid transparent;
  border-right: 0;
  border-bottom: 0.3em solid transparent;
  border-left: 0.3em solid;
}
.dropend .dropdown-toggle:empty::after {
  margin-left: 0;
}
.dropend .dropdown-toggle::after {
  vertical-align: 0;
}

.dropstart .dropdown-menu[data-bs-popper] {
  top: 0;
  right: 100%;
  left: auto;
  margin-top: 0;
  margin-right: var(--bs-dropdown-spacer);
}
.dropstart .dropdown-toggle::after {
  display: inline-block;
  margin-left: 0.255em;
  vertical-align: 0.255em;
  content: "";
}
.dropstart .dropdown-toggle::after {
  display: none;
}
.dropstart .dropdown-toggle::before {
  display: inline-block;
  margin-right: 0.255em;
  vertical-align: 0.255em;
  content: "";
  border-top: 0.3em solid transparent;
  border-right: 0.3em solid;
  border-bottom: 0.3em solid transparent;
}
.dropstart .dropdown-toggle:empty::after {
  margin-left: 0;
}
.dropstart .dropdown-toggle::before {
  vertical-align: 0;
}

.dropdown-divider {
  height: 0;
  margin: var(--bs-dropdown-divider-margin-y) 0;
  overflow: hidden;
  border-top: 1px solid var(--bs-dropdown-divider-bg);
  opacity: 1;
}

.dropdown-item {
  display: block;
  width: 100%;
  padding: var(--bs-dropdown-item-padding-y) var(--bs-dropdown-item-padding-x);
  clear: both;
  font-weight: 400;
  color: var(--bs-dropdown-link-color);
  text-align: inherit;
  text-decoration: none;
  white-space: nowrap;
  background-color: transparent;
  border: 0;
  border-radius: var(--bs-dropdown-item-border-radius, 0);
}
.dropdown-item:hover, .dropdown-item:focus {
  color: var(--bs-dropdown-link-hover-color);
  background-color: var(--bs-dropdown-link-hover-bg);
}
.dropdown-item.active, .dropdown-item:active {
  color: var(--bs-dropdown-link-active-color);
  text-decoration: none;
  background-color: var(--bs-dropdown-link-active-bg);
}
.dropdown-item.disabled, .dropdown-item:disabled {
  color: var(--bs-dropdown-link-disabled-color);
  pointer-events: none;
  background-color: transparent;
}

.dropdown-menu.show {
  display: block;
}

.dropdown-header {
  display: block;
  padding: var(--bs-dropdown-header-padding-y) var(--bs-dropdown-header-padding-x);
  margin-bottom: 0;
  font-size: 0.875rem;
  color: var(--bs-dropdown-header-color);
  white-space: nowrap;
}

.dropdown-item-text {
  display: block;
  padding: var(--bs-dropdown-item-padding-y) var(--bs-dropdown-item-padding-x);
  color: var(--bs-dropdown-link-color);
}

.dropdown-menu-dark {
  --bs-dropdown-color: #dee2e6;
  --bs-dropdown-bg: #343a40;
  --bs-dropdown-border-color: var(--bs-border-color-translucent);
  --bs-dropdown-box-shadow: ;
  --bs-dropdown-link-color: #dee2e6;
  --bs-dropdown-link-hover-color: #fff;
  --bs-dropdown-divider-bg: var(--bs-border-color-translucent);
  --bs-dropdown-link-hover-bg: rgba(255, 255, 255, 0.15);
  --bs-dropdown-link-active-color: #fff;
  --bs-dropdown-link-active-bg: #0d6efd;
  --bs-dropdown-link-disabled-color: #adb5bd;
  --bs-dropdown-header-color: #adb5bd;
}

.btn-group,
.btn-group-vertical {
  position: relative;
  display: inline-flex;
  vertical-align: middle;
}
.btn-group > .btn,
.btn-group-vertical > .btn {
  position: relative;
  flex: 1 1 auto;
}
.btn-group > .btn-check:checked + .btn,
.btn-group > .btn-check:focus + .btn,
.btn-group > .btn:hover,
.btn-group > .btn:focus,
.btn-group > .btn:active,
.btn-group > .btn.active,
.btn-group-vertical > .btn-check:checked + .btn,
.btn-group-vertical > .btn-check:focus + .btn,
.btn-group-vertical > .btn:hover,
.btn-group-vertical > .btn:focus,
.btn-group-vertical > .btn:active,
.btn-group-vertical > .btn.active {
  z-index: 1;
}

.btn-toolbar {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-start;
}
.btn-toolbar .input-group {
  width: auto;
}

.btn-group {
  border-radius: var(--bs-border-radius);
}
.btn-group > :not(.btn-check:first-child) + .btn,
.btn-group > .btn-group:not(:first-child) {
  margin-left: calc(-1 * var(--bs-border-width));
}
.btn-group > .btn:not(:last-child):not(.dropdown-toggle),
.btn-group > .btn.dropdown-toggle-split:first-child,
.btn-group > .btn-group:not(:last-child) > .btn {
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}
.btn-group > .btn:nth-child(n+3),
.btn-group > :not(.btn-check) + .btn,
.btn-group > .btn-group:not(:first-child) > .btn {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
}

.dropdown-toggle-split {
  padding-right: 0.5625rem;
  padding-left: 0.5625rem;
}
.dropdown-toggle-split::after, .dropup .dropdown-toggle-split::after, .dropend .dropdown-toggle-split::after {
  margin-left: 0;
}
.dropstart .dropdown-toggle-split::before {
  margin-right: 0;
}

.btn-sm + .dropdown-toggle-split, .btn-group-sm > .btn + .dropdown-toggle-split {
  padding-right: 0.375rem;
  padding-left: 0.375rem;
}

.btn-lg + .dropdown-toggle-split, .btn-group-lg > .btn + .dropdown-toggle-split {
  padding-right: 0.75rem;
  padding-left: 0.75rem;
}

.btn-group.show .dropdown-toggle {
  box-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
}
.btn-group.show .dropdown-toggle.btn-link {
  box-shadow: none;
}

.btn-group-vertical {
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
}
.btn-group-vertical > .btn,
.btn-group-vertical > .btn-group {
  width: 100%;
}
.btn-group-vertical > .btn:not(:first-child),
.btn-group-vertical > .btn-group:not(:first-child) {
  margin-top: calc(-1 * var(--bs-border-width));
}
.btn-group-vertical > .btn:not(:last-child):not(.dropdown-toggle),
.btn-group-vertical > .btn-group:not(:last-child) > .btn {
  border-bottom-right-radius: 0;
  border-bottom-left-radius: 0;
}
.btn-group-vertical > .btn:nth-child(n+3),
.btn-group-vertical > :not(.btn-check) + .btn,
.btn-group-vertical > .btn-group:not(:first-child) > .btn {
  border-top-left-radius: 0;
  border-top-right-radius: 0;
}

.nav {
  --bs-nav-link-padding-x: 1rem;
  --bs-nav-link-padding-y: 0.5rem;
  --bs-nav-link-font-weight: ;
  --bs-nav-link-color: var(--bs-link-color);
  --bs-nav-link-hover-color: var(--bs-link-hover-color);
  --bs-nav-link-disabled-color: var(--bs-secondary-color);
  display: flex;
  flex-wrap: wrap;
  padding-left: 0;
  margin-bottom: 0;
  list-style: none;
}

.nav-link {
  display: block;
  padding: var(--bs-nav-link-padding-y) var(--bs-nav-link-padding-x);
  font-size: var(--bs-nav-link-font-size);
  font-weight: var(--bs-nav-link-font-weight);
  color: var(--bs-nav-link-color);
  text-decoration: none;
  background: none;
  border: 0;
  transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .nav-link {
    transition: none;
  }
}
.nav-link:hover, .nav-link:focus {
  color: var(--bs-nav-link-hover-color);
}
.nav-link:focus-visible {
  outline: 0;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
.nav-link.disabled, .nav-link:disabled {
  color: var(--bs-nav-link-disabled-color);
  pointer-events: none;
  cursor: default;
}

.nav-tabs {
  --bs-nav-tabs-border-width: var(--bs-border-width);
  --bs-nav-tabs-border-color: var(--bs-border-color);
  --bs-nav-tabs-border-radius: var(--bs-border-radius);
  --bs-nav-tabs-link-hover-border-color: var(--bs-secondary-bg) var(--bs-secondary-bg) var(--bs-border-color);
  --bs-nav-tabs-link-active-color: var(--bs-emphasis-color);
  --bs-nav-tabs-link-active-bg: var(--bs-body-bg);
  --bs-nav-tabs-link-active-border-color: var(--bs-border-color) var(--bs-border-color) var(--bs-body-bg);
  border-bottom: var(--bs-nav-tabs-border-width) solid var(--bs-nav-tabs-border-color);
}
.nav-tabs .nav-link {
  margin-bottom: calc(-1 * var(--bs-nav-tabs-border-width));
  border: var(--bs-nav-tabs-border-width) solid transparent;
  border-top-left-radius: var(--bs-nav-tabs-border-radius);
  border-top-right-radius: var(--bs-nav-tabs-border-radius);
}
.nav-tabs .nav-link:hover, .nav-tabs .nav-link:focus {
  isolation: isolate;
  border-color: var(--bs-nav-tabs-link-hover-border-color);
}
.nav-tabs .nav-link.active,
.nav-tabs .nav-item.show .nav-link {
  color: var(--bs-nav-tabs-link-active-color);
  background-color: var(--bs-nav-tabs-link-active-bg);
  border-color: var(--bs-nav-tabs-link-active-border-color);
}
.nav-tabs .dropdown-menu {
  margin-top: calc(-1 * var(--bs-nav-tabs-border-width));
  border-top-left-radius: 0;
  border-top-right-radius: 0;
}

.nav-pills {
  --bs-nav-pills-border-radius: var(--bs-border-radius);
  --bs-nav-pills-link-active-color: #fff;
  --bs-nav-pills-link-active-bg: #0d6efd;
}
.nav-pills .nav-link {
  border-radius: var(--bs-nav-pills-border-radius);
}
.nav-pills .nav-link.active,
.nav-pills .show > .nav-link {
  color: var(--bs-nav-pills-link-active-color);
  background-color: var(--bs-nav-pills-link-active-bg);
}

.nav-underline {
  --bs-nav-underline-gap: 1rem;
  --bs-nav-underline-border-width: 0.125rem;
  --bs-nav-underline-link-active-color: var(--bs-emphasis-color);
  gap: var(--bs-nav-underline-gap);
}
.nav-underline .nav-link {
  padding-right: 0;
  padding-left: 0;
  border-bottom: var(--bs-nav-underline-border-width) solid transparent;
}
.nav-underline .nav-link:hover, .nav-underline .nav-link:focus {
  border-bottom-color: currentcolor;
}
.nav-underline .nav-link.active,
.nav-underline .show > .nav-link {
  font-weight: 700;
  color: var(--bs-nav-underline-link-active-color);
  border-bottom-color: currentcolor;
}

.nav-fill > .nav-link,
.nav-fill .nav-item {
  flex: 1 1 auto;
  text-align: center;
}

.nav-justified > .nav-link,
.nav-justified .nav-item {
  flex-grow: 1;
  flex-basis: 0;
  text-align: center;
}

.nav-fill .nav-item .nav-link,
.nav-justified .nav-item .nav-link {
  width: 100%;
}

.tab-content > .tab-pane {
  display: none;
}
.tab-content > .active {
  display: block;
}

.navbar {
  --bs-navbar-padding-x: 0;
  --bs-navbar-padding-y: 0.5rem;
  --bs-navbar-color: rgba(var(--bs-emphasis-color-rgb), 0.65);
  --bs-navbar-hover-color: rgba(var(--bs-emphasis-color-rgb), 0.8);
  --bs-navbar-disabled-color: rgba(var(--bs-emphasis-color-rgb), 0.3);
  --bs-navbar-active-color: rgba(var(--bs-emphasis-color-rgb), 1);
  --bs-navbar-brand-padding-y: 0.3125rem;
  --bs-navbar-brand-margin-end: 1rem;
  --bs-navbar-brand-font-size: 1.25rem;
  --bs-navbar-brand-color: rgba(var(--bs-emphasis-color-rgb), 1);
  --bs-navbar-brand-hover-color: rgba(var(--bs-emphasis-color-rgb), 1);
  --bs-navbar-nav-link-padding-x: 1rem;
  --bs-navbar-toggler-padding-y: 0.25rem;
  --bs-navbar-toggler-padding-x: 0.75rem;
  --bs-navbar-toggler-font-size: 1.25rem;
  --bs-navbar-toggler-icon-bg: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2833, 37, 41, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
  --bs-navbar-toggler-border-color: rgba(var(--bs-emphasis-color-rgb), 0.15);
  --bs-navbar-toggler-border-radius: var(--bs-border-radius);
  --bs-navbar-toggler-focus-width: 0.25rem;
  --bs-navbar-toggler-transition: box-shadow 0.15s ease-in-out;
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  padding: var(--bs-navbar-padding-y) var(--bs-navbar-padding-x);
}
.navbar > .container,
.navbar > .container-fluid,
.navbar > .container-sm,
.navbar > .container-md,
.navbar > .container-lg,
.navbar > .container-xl,
.navbar > .container-xxl {
  display: flex;
  flex-wrap: inherit;
  align-items: center;
  justify-content: space-between;
}
.navbar-brand {
  padding-top: var(--bs-navbar-brand-padding-y);
  padding-bottom: var(--bs-navbar-brand-padding-y);
  margin-right: var(--bs-navbar-brand-margin-end);
  font-size: var(--bs-navbar-brand-font-size);
  color: var(--bs-navbar-brand-color);
  text-decoration: none;
  white-space: nowrap;
}
.navbar-brand:hover, .navbar-brand:focus {
  color: var(--bs-navbar-brand-hover-color);
}

.navbar-nav {
  --bs-nav-link-padding-x: 0;
  --bs-nav-link-padding-y: 0.5rem;
  --bs-nav-link-font-weight: ;
  --bs-nav-link-color: var(--bs-navbar-color);
  --bs-nav-link-hover-color: var(--bs-navbar-hover-color);
  --bs-nav-link-disabled-color: var(--bs-navbar-disabled-color);
  display: flex;
  flex-direction: column;
  padding-left: 0;
  margin-bottom: 0;
  list-style: none;
}
.navbar-nav .nav-link.active, .navbar-nav .nav-link.show {
  color: var(--bs-navbar-active-color);
}
.navbar-nav .dropdown-menu {
  position: static;
}

.navbar-text {
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
  color: var(--bs-navbar-color);
}
.navbar-text a,
.navbar-text a:hover,
.navbar-text a:focus {
  color: var(--bs-navbar-active-color);
}

.navbar-collapse {
  flex-grow: 1;
  flex-basis: 100%;
  align-items: center;
}

.navbar-toggler {
  padding: var(--bs-navbar-toggler-padding-y) var(--bs-navbar-toggler-padding-x);
  font-size: var(--bs-navbar-toggler-font-size);
  line-height: 1;
  color: var(--bs-navbar-color);
  background-color: transparent;
  border: var(--bs-border-width) solid var(--bs-navbar-toggler-border-color);
  border-radius: var(--bs-navbar-toggler-border-radius);
  transition: var(--bs-navbar-toggler-transition);
}
@media (prefers-reduced-motion: reduce) {
  .navbar-toggler {
    transition: none;
  }
}
.navbar-toggler:hover {
  text-decoration: none;
}
.navbar-toggler:focus {
  text-decoration: none;
  outline: 0;
  box-shadow: 0 0 0 var(--bs-navbar-toggler-focus-width);
}

.navbar-toggler-icon {
  display: inline-block;
  width: 1.5em;
  height: 1.5em;
  vertical-align: middle;
  background-image: var(--bs-navbar-toggler-icon-bg);
  background-repeat: no-repeat;
  background-position: center;
  background-size: 100%;
}

.navbar-nav-scroll {
  max-height: var(--bs-scroll-height, 75vh);
  overflow-y: auto;
}

@media (min-width: 576px) {
  .navbar-expand-sm {
    flex-wrap: nowrap;
    justify-content: flex-start;
  }
  .navbar-expand-sm .navbar-nav {
    flex-direction: row;
  }
  .navbar-expand-sm .navbar-nav .dropdown-menu {
    position: absolute;
  }
  .navbar-expand-sm .navbar-nav .nav-link {
    padding-right: var(--bs-navbar-nav-link-padding-x);
    padding-left: var(--bs-navbar-nav-link-padding-x);
  }
  .navbar-expand-sm .navbar-nav-scroll {
    overflow: visible;
  }
  .navbar-expand-sm .navbar-collapse {
    display: flex !important;
    flex-basis: auto;
  }
  .navbar-expand-sm .navbar-toggler {
    display: none;
  }
  .navbar-expand-sm .offcanvas {
    position: static;
    z-index: auto;
    flex-grow: 1;
    width: auto !important;
    height: auto !important;
    visibility: visible !important;
    background-color: transparent !important;
    border: 0 !important;
    transform: none !important;
    box-shadow: none;
    transition: none;
  }
  .navbar-expand-sm .offcanvas .offcanvas-header {
    display: none;
  }
  .navbar-expand-sm .offcanvas .offcanvas-body {
    display: flex;
    flex-grow: 0;
    padding: 0;
    overflow-y: visible;
  }
}
@media (min-width: 768px) {
  .navbar-expand-md {
    flex-wrap: nowrap;
    justify-content: flex-start;
  }
  .navbar-expand-md .navbar-nav {
    flex-direction: row;
  }
  .navbar-expand-md .navbar-nav .dropdown-menu {
    position: absolute;
  }
  .navbar-expand-md .navbar-nav .nav-link {
    padding-right: var(--bs-navbar-nav-link-padding-x);
    padding-left: var(--bs-navbar-nav-link-padding-x);
  }
  .navbar-expand-md .navbar-nav-scroll {
    overflow: visible;
  }
  .navbar-expand-md .navbar-collapse {
    display: flex !important;
    flex-basis: auto;
  }
  .navbar-expand-md .navbar-toggler {
    display: none;
  }
  .navbar-expand-md .offcanvas {
    position: static;
    z-index: auto;
    flex-grow: 1;
    width: auto !important;
    height: auto !important;
    visibility: visible !important;
    background-color: transparent !important;
    border: 0 !important;
    transform: none !important;
    box-shadow: none;
    transition: none;
  }
  .navbar-expand-md .offcanvas .offcanvas-header {
    display: none;
  }
  .navbar-expand-md .offcanvas .offcanvas-body {
    display: flex;
    flex-grow: 0;
    padding: 0;
    overflow-y: visible;
  }
}
@media (min-width: 992px) {
  .navbar-expand-lg {
    flex-wrap: nowrap;
    justify-content: flex-start;
  }
  .navbar-expand-lg .navbar-nav {
    flex-direction: row;
  }
  .navbar-expand-lg .navbar-nav .dropdown-menu {
    position: absolute;
  }
  .navbar-expand-lg .navbar-nav .nav-link {
    padding-right: var(--bs-navbar-nav-link-padding-x);
    padding-left: var(--bs-navbar-nav-link-padding-x);
  }
  .navbar-expand-lg .navbar-nav-scroll {
    overflow: visible;
  }
  .navbar-expand-lg .navbar-collapse {
    display: flex !important;
    flex-basis: auto;
  }
  .navbar-expand-lg .navbar-toggler {
    display: none;
  }
  .navbar-expand-lg .offcanvas {
    position: static;
    z-index: auto;
    flex-grow: 1;
    width: auto !important;
    height: auto !important;
    visibility: visible !important;
    background-color: transparent !important;
    border: 0 !important;
    transform: none !important;
    box-shadow: none;
    transition: none;
  }
  .navbar-expand-lg .offcanvas .offcanvas-header {
    display: none;
  }
  .navbar-expand-lg .offcanvas .offcanvas-body {
    display: flex;
    flex-grow: 0;
    padding: 0;
    overflow-y: visible;
  }
}
@media (min-width: 1200px) {
  .navbar-expand-xl {
    flex-wrap: nowrap;
    justify-content: flex-start;
  }
  .navbar-expand-xl .navbar-nav {
    flex-direction: row;
  }
  .navbar-expand-xl .navbar-nav .dropdown-menu {
    position: absolute;
  }
  .navbar-expand-xl .navbar-nav .nav-link {
    padding-right: var(--bs-navbar-nav-link-padding-x);
    padding-left: var(--bs-navbar-nav-link-padding-x);
  }
  .navbar-expand-xl .navbar-nav-scroll {
    overflow: visible;
  }
  .navbar-expand-xl .navbar-collapse {
    display: flex !important;
    flex-basis: auto;
  }
  .navbar-expand-xl .navbar-toggler {
    display: none;
  }
  .navbar-expand-xl .offcanvas {
    position: static;
    z-index: auto;
    flex-grow: 1;
    width: auto !important;
    height: auto !important;
    visibility: visible !important;
    background-color: transparent !important;
    border: 0 !important;
    transform: none !important;
    box-shadow: none;
    transition: none;
  }
  .navbar-expand-xl .offcanvas .offcanvas-header {
    display: none;
  }
  .navbar-expand-xl .offcanvas .offcanvas-body {
    display: flex;
    flex-grow: 0;
    padding: 0;
    overflow-y: visible;
  }
}
@media (min-width: 1400px) {
  .navbar-expand-xxl {
    flex-wrap: nowrap;
    justify-content: flex-start;
  }
  .navbar-expand-xxl .navbar-nav {
    flex-direction: row;
  }
  .navbar-expand-xxl .navbar-nav .dropdown-menu {
    position: absolute;
  }
  .navbar-expand-xxl .navbar-nav .nav-link {
    padding-right: var(--bs-navbar-nav-link-padding-x);
    padding-left: var(--bs-navbar-nav-link-padding-x);
  }
  .navbar-expand-xxl .navbar-nav-scroll {
    overflow: visible;
  }
  .navbar-expand-xxl .navbar-collapse {
    display: flex !important;
    flex-basis: auto;
  }
  .navbar-expand-xxl .navbar-toggler {
    display: none;
  }
  .navbar-expand-xxl .offcanvas {
    position: static;
    z-index: auto;
    flex-grow: 1;
    width: auto !important;
    height: auto !important;
    visibility: visible !important;
    background-color: transparent !important;
    border: 0 !important;
    transform: none !important;
    box-shadow: none;
    transition: none;
  }
  .navbar-expand-xxl .offcanvas .offcanvas-header {
    display: none;
  }
  .navbar-expand-xxl .offcanvas .offcanvas-body {
    display: flex;
    flex-grow: 0;
    padding: 0;
    overflow-y: visible;
  }
}
.navbar-expand {
  flex-wrap: nowrap;
  justify-content: flex-start;
}
.navbar-expand .navbar-nav {
  flex-direction: row;
}
.navbar-expand .navbar-nav .dropdown-menu {
  position: absolute;
}
.navbar-expand .navbar-nav .nav-link {
  padding-right: var(--bs-navbar-nav-link-padding-x);
  padding-left: var(--bs-navbar-nav-link-padding-x);
}
.navbar-expand .navbar-nav-scroll {
  overflow: visible;
}
.navbar-expand .navbar-collapse {
  display: flex !important;
  flex-basis: auto;
}
.navbar-expand .navbar-toggler {
  display: none;
}
.navbar-expand .offcanvas {
  position: static;
  z-index: auto;
  flex-grow: 1;
  width: auto !important;
  height: auto !important;
  visibility: visible !important;
  background-color: transparent !important;
  border: 0 !important;
  transform: none !important;
  box-shadow: none;
  transition: none;
}
.navbar-expand .offcanvas .offcanvas-header {
  display: none;
}
.navbar-expand .offcanvas .offcanvas-body {
  display: flex;
  flex-grow: 0;
  padding: 0;
  overflow-y: visible;
}

.navbar-dark,
.navbar[data-bs-theme=dark] {
  --bs-navbar-color: rgba(255, 255, 255, 0.55);
  --bs-navbar-hover-color: rgba(255, 255, 255, 0.75);
  --bs-navbar-disabled-color: rgba(255, 255, 255, 0.25);
  --bs-navbar-active-color: #fff;
  --bs-navbar-brand-color: #fff;
  --bs-navbar-brand-hover-color: #fff;
  --bs-navbar-toggler-border-color: rgba(255, 255, 255, 0.1);
  --bs-navbar-toggler-icon-bg: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.55%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
}

[data-bs-theme=dark] .navbar-toggler-icon {
  --bs-navbar-toggler-icon-bg: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.55%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
}

.card {
  --bs-card-spacer-y: 1rem;
  --bs-card-spacer-x: 1rem;
  --bs-card-title-spacer-y: 0.5rem;
  --bs-card-title-color: ;
  --bs-card-subtitle-color: ;
  --bs-card-border-width: var(--bs-border-width);
  --bs-card-border-color: var(--bs-border-color-translucent);
  --bs-card-border-radius: var(--bs-border-radius);
  --bs-card-box-shadow: ;
  --bs-card-inner-border-radius: calc(var(--bs-border-radius) - (var(--bs-border-width)));
  --bs-card-cap-padding-y: 0.5rem;
  --bs-card-cap-padding-x: 1rem;
  --bs-card-cap-bg: rgba(var(--bs-body-color-rgb), 0.03);
  --bs-card-cap-color: ;
  --bs-card-height: ;
  --bs-card-color: ;
  --bs-card-bg: var(--bs-body-bg);
  --bs-card-img-overlay-padding: 1rem;
  --bs-card-group-margin: 0.75rem;
  position: relative;
  display: flex;
  flex-direction: column;
  min-width: 0;
  height: var(--bs-card-height);
  color: var(--bs-body-color);
  word-wrap: break-word;
  background-color: var(--bs-card-bg);
  background-clip: border-box;
  border: var(--bs-card-border-width) solid var(--bs-card-border-color);
  border-radius: var(--bs-card-border-radius);
  box-shadow: var(--bs-card-box-shadow);
}
.card > hr {
  margin-right: 0;
  margin-left: 0;
}
.card > .list-group {
  border-top: inherit;
  border-bottom: inherit;
}
.card > .list-group:first-child {
  border-top-width: 0;
  border-top-left-radius: var(--bs-card-inner-border-radius);
  border-top-right-radius: var(--bs-card-inner-border-radius);
}
.card > .list-group:last-child {
  border-bottom-width: 0;
  border-bottom-right-radius: var(--bs-card-inner-border-radius);
  border-bottom-left-radius: var(--bs-card-inner-border-radius);
}
.card > .card-header + .list-group,
.card > .list-group + .card-footer {
  border-top: 0;
}

.card-body {
  flex: 1 1 auto;
  padding: var(--bs-card-spacer-y) var(--bs-card-spacer-x);
  color: var(--bs-card-color);
}

.card-title {
  margin-bottom: var(--bs-card-title-spacer-y);
  color: var(--bs-card-title-color);
}

.card-subtitle {
  margin-top: calc(-0.5 * var(--bs-card-title-spacer-y));
  margin-bottom: 0;
  color: var(--bs-card-subtitle-color);
}

.card-text:last-child {
  margin-bottom: 0;
}

.card-link + .card-link {
  margin-left: var(--bs-card-spacer-x);
}

.card-header {
  padding: var(--bs-card-cap-padding-y) var(--bs-card-cap-padding-x);
  margin-bottom: 0;
  color: var(--bs-card-cap-color);
  background-color: var(--bs-card-cap-bg);
  border-bottom: var(--bs-card-border-width) solid var(--bs-card-border-color);
}
.card-header:first-child {
  border-radius: var(--bs-card-inner-border-radius) var(--bs-card-inner-border-radius) 0 0;
}

.card-footer {
  padding: var(--bs-card-cap-padding-y) var(--bs-card-cap-padding-x);
  color: var(--bs-card-cap-color);
  background-color: var(--bs-card-cap-bg);
  border-top: var(--bs-card-border-width) solid var(--bs-card-border-color);
}
.card-footer:last-child {
  border-radius: 0 0 var(--bs-card-inner-border-radius) var(--bs-card-inner-border-radius);
}

.card-header-tabs {
  margin-right: calc(-0.5 * var(--bs-card-cap-padding-x));
  margin-bottom: calc(-1 * var(--bs-card-cap-padding-y));
  margin-left: calc(-0.5 * var(--bs-card-cap-padding-x));
  border-bottom: 0;
}
.card-header-tabs .nav-link.active {
  background-color: var(--bs-card-bg);
  border-bottom-color: var(--bs-card-bg);
}

.card-header-pills {
  margin-right: calc(-0.5 * var(--bs-card-cap-padding-x));
  margin-left: calc(-0.5 * var(--bs-card-cap-padding-x));
}

.card-img-overlay {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  padding: var(--bs-card-img-overlay-padding);
  border-radius: var(--bs-card-inner-border-radius);
}

.card-img,
.card-img-top,
.card-img-bottom {
  width: 100%;
}

.card-img,
.card-img-top {
  border-top-left-radius: var(--bs-card-inner-border-radius);
  border-top-right-radius: var(--bs-card-inner-border-radius);
}

.card-img,
.card-img-bottom {
  border-bottom-right-radius: var(--bs-card-inner-border-radius);
  border-bottom-left-radius: var(--bs-card-inner-border-radius);
}

.card-group > .card {
  margin-bottom: var(--bs-card-group-margin);
}
@media (min-width: 576px) {
  .card-group {
    display: flex;
    flex-flow: row wrap;
  }
  .card-group > .card {
    flex: 1 0 0;
    margin-bottom: 0;
  }
  .card-group > .card + .card {
    margin-left: 0;
    border-left: 0;
  }
  .card-group > .card:not(:last-child) {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
  }
  .card-group > .card:not(:last-child) > .card-img-top,
  .card-group > .card:not(:last-child) > .card-header {
    border-top-right-radius: 0;
  }
  .card-group > .card:not(:last-child) > .card-img-bottom,
  .card-group > .card:not(:last-child) > .card-footer {
    border-bottom-right-radius: 0;
  }
  .card-group > .card:not(:first-child) {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
  }
  .card-group > .card:not(:first-child) > .card-img-top,
  .card-group > .card:not(:first-child) > .card-header {
    border-top-left-radius: 0;
  }
  .card-group > .card:not(:first-child) > .card-img-bottom,
  .card-group > .card:not(:first-child) > .card-footer {
    border-bottom-left-radius: 0;
  }
}

.accordion {
  --bs-accordion-color: var(--bs-body-color);
  --bs-accordion-bg: var(--bs-body-bg);
  --bs-accordion-transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out, border-radius 0.15s ease;
  --bs-accordion-border-color: var(--bs-border-color);
  --bs-accordion-border-width: var(--bs-border-width);
  --bs-accordion-border-radius: var(--bs-border-radius);
  --bs-accordion-inner-border-radius: calc(var(--bs-border-radius) - (var(--bs-border-width)));
  --bs-accordion-btn-padding-x: 1.25rem;
  --bs-accordion-btn-padding-y: 1rem;
  --bs-accordion-btn-color: var(--bs-body-color);
  --bs-accordion-btn-bg: var(--bs-accordion-bg);
  --bs-accordion-btn-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%23212529' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
  --bs-accordion-btn-icon-width: 1.25rem;
  --bs-accordion-btn-icon-transform: rotate(-180deg);
  --bs-accordion-btn-icon-transition: transform 0.2s ease-in-out;
  --bs-accordion-btn-active-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='rgb%285.2, 44, 101.2%29' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
  --bs-accordion-btn-focus-box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
  --bs-accordion-body-padding-x: 1.25rem;
  --bs-accordion-body-padding-y: 1rem;
  --bs-accordion-active-color: var(--bs-primary-text-emphasis);
  --bs-accordion-active-bg: var(--bs-primary-bg-subtle);
}

.accordion-button {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
  padding: var(--bs-accordion-btn-padding-y) var(--bs-accordion-btn-padding-x);
  font-size: 1rem;
  color: var(--bs-accordion-btn-color);
  text-align: left;
  background-color: var(--bs-accordion-btn-bg);
  border: 0;
  border-radius: 0;
  overflow-anchor: none;
  transition: var(--bs-accordion-transition);
}
@media (prefers-reduced-motion: reduce) {
  .accordion-button {
    transition: none;
  }
}
.accordion-button:not(.collapsed) {
  color: var(--bs-accordion-active-color);
  background-color: var(--bs-accordion-active-bg);
  box-shadow: inset 0 calc(-1 * var(--bs-accordion-border-width)) 0 var(--bs-accordion-border-color);
}
.accordion-button:not(.collapsed)::after {
  background-image: var(--bs-accordion-btn-active-icon);
  transform: var(--bs-accordion-btn-icon-transform);
}
.accordion-button::after {
  flex-shrink: 0;
  width: var(--bs-accordion-btn-icon-width);
  height: var(--bs-accordion-btn-icon-width);
  margin-left: auto;
  content: "";
  background-image: var(--bs-accordion-btn-icon);
  background-repeat: no-repeat;
  background-size: var(--bs-accordion-btn-icon-width);
  transition: var(--bs-accordion-btn-icon-transition);
}
@media (prefers-reduced-motion: reduce) {
  .accordion-button::after {
    transition: none;
  }
}
.accordion-button:hover {
  z-index: 2;
}
.accordion-button:focus {
  z-index: 3;
  outline: 0;
  box-shadow: var(--bs-accordion-btn-focus-box-shadow);
}

.accordion-header {
  margin-bottom: 0;
}

.accordion-item {
  color: var(--bs-accordion-color);
  background-color: var(--bs-accordion-bg);
  border: var(--bs-accordion-border-width) solid var(--bs-accordion-border-color);
}
.accordion-item:first-of-type {
  border-top-left-radius: var(--bs-accordion-border-radius);
  border-top-right-radius: var(--bs-accordion-border-radius);
}
.accordion-item:first-of-type > .accordion-header .accordion-button {
  border-top-left-radius: var(--bs-accordion-inner-border-radius);
  border-top-right-radius: var(--bs-accordion-inner-border-radius);
}
.accordion-item:not(:first-of-type) {
  border-top: 0;
}
.accordion-item:last-of-type {
  border-bottom-right-radius: var(--bs-accordion-border-radius);
  border-bottom-left-radius: var(--bs-accordion-border-radius);
}
.accordion-item:last-of-type > .accordion-header .accordion-button.collapsed {
  border-bottom-right-radius: var(--bs-accordion-inner-border-radius);
  border-bottom-left-radius: var(--bs-accordion-inner-border-radius);
}
.accordion-item:last-of-type > .accordion-collapse {
  border-bottom-right-radius: var(--bs-accordion-border-radius);
  border-bottom-left-radius: var(--bs-accordion-border-radius);
}

.accordion-body {
  padding: var(--bs-accordion-body-padding-y) var(--bs-accordion-body-padding-x);
}

.accordion-flush > .accordion-item {
  border-right: 0;
  border-left: 0;
  border-radius: 0;
}
.accordion-flush > .accordion-item:first-child {
  border-top: 0;
}
.accordion-flush > .accordion-item:last-child {
  border-bottom: 0;
}
.accordion-flush > .accordion-item > .accordion-collapse,
.accordion-flush > .accordion-item > .accordion-header .accordion-button,
.accordion-flush > .accordion-item > .accordion-header .accordion-button.collapsed {
  border-radius: 0;
}

[data-bs-theme=dark] .accordion-button::after {
  --bs-accordion-btn-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='rgb%28109.8, 168, 253.8%29'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708'/%3e%3c/svg%3e");
  --bs-accordion-btn-active-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='rgb%28109.8, 168, 253.8%29'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708'/%3e%3c/svg%3e");
}

.breadcrumb {
  --bs-breadcrumb-padding-x: 0;
  --bs-breadcrumb-padding-y: 0;
  --bs-breadcrumb-margin-bottom: 1rem;
  --bs-breadcrumb-bg: ;
  --bs-breadcrumb-border-radius: ;
  --bs-breadcrumb-divider-color: var(--bs-secondary-color);
  --bs-breadcrumb-item-padding-x: 0.5rem;
  --bs-breadcrumb-item-active-color: var(--bs-secondary-color);
  display: flex;
  flex-wrap: wrap;
  padding: var(--bs-breadcrumb-padding-y) var(--bs-breadcrumb-padding-x);
  margin-bottom: var(--bs-breadcrumb-margin-bottom);
  font-size: var(--bs-breadcrumb-font-size);
  list-style: none;
  background-color: var(--bs-breadcrumb-bg);
  border-radius: var(--bs-breadcrumb-border-radius);
}

.breadcrumb-item + .breadcrumb-item {
  padding-left: var(--bs-breadcrumb-item-padding-x);
}
.breadcrumb-item + .breadcrumb-item::before {
  float: left;
  padding-right: var(--bs-breadcrumb-item-padding-x);
  color: var(--bs-breadcrumb-divider-color);
  content: var(--bs-breadcrumb-divider, "/") /* rtl: var(--bs-breadcrumb-divider, "/") */;
}
.breadcrumb-item.active {
  color: var(--bs-breadcrumb-item-active-color);
}

.pagination {
  --bs-pagination-padding-x: 0.75rem;
  --bs-pagination-padding-y: 0.375rem;
  --bs-pagination-font-size: 1rem;
  --bs-pagination-color: var(--bs-link-color);
  --bs-pagination-bg: var(--bs-body-bg);
  --bs-pagination-border-width: var(--bs-border-width);
  --bs-pagination-border-color: var(--bs-border-color);
  --bs-pagination-border-radius: var(--bs-border-radius);
  --bs-pagination-hover-color: var(--bs-link-hover-color);
  --bs-pagination-hover-bg: var(--bs-tertiary-bg);
  --bs-pagination-hover-border-color: var(--bs-border-color);
  --bs-pagination-focus-color: var(--bs-link-hover-color);
  --bs-pagination-focus-bg: var(--bs-secondary-bg);
  --bs-pagination-focus-box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
  --bs-pagination-active-color: #fff;
  --bs-pagination-active-bg: #0d6efd;
  --bs-pagination-active-border-color: #0d6efd;
  --bs-pagination-disabled-color: var(--bs-secondary-color);
  --bs-pagination-disabled-bg: var(--bs-secondary-bg);
  --bs-pagination-disabled-border-color: var(--bs-border-color);
  display: flex;
  padding-left: 0;
  list-style: none;
}

.page-link {
  position: relative;
  display: block;
  padding: var(--bs-pagination-padding-y) var(--bs-pagination-padding-x);
  font-size: var(--bs-pagination-font-size);
  color: var(--bs-pagination-color);
  text-decoration: none;
  background-color: var(--bs-pagination-bg);
  border: var(--bs-pagination-border-width) solid var(--bs-pagination-border-color);
  transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .page-link {
    transition: none;
  }
}
.page-link:hover {
  z-index: 2;
  color: var(--bs-pagination-hover-color);
  background-color: var(--bs-pagination-hover-bg);
  border-color: var(--bs-pagination-hover-border-color);
}
.page-link:focus {
  z-index: 3;
  color: var(--bs-pagination-focus-color);
  background-color: var(--bs-pagination-focus-bg);
  outline: 0;
  box-shadow: var(--bs-pagination-focus-box-shadow);
}
.page-link.active, .active > .page-link {
  z-index: 3;
  color: var(--bs-pagination-active-color);
  background-color: var(--bs-pagination-active-bg);
  border-color: var(--bs-pagination-active-border-color);
}
.page-link.disabled, .disabled > .page-link {
  color: var(--bs-pagination-disabled-color);
  pointer-events: none;
  background-color: var(--bs-pagination-disabled-bg);
  border-color: var(--bs-pagination-disabled-border-color);
}

.page-item:not(:first-child) .page-link {
  margin-left: calc(var(--bs-border-width) * -1);
}
.page-item .page-link {
  border-radius: var(--bs-pagination-border-radius);
}

.pagination-lg {
  --bs-pagination-padding-x: 1.5rem;
  --bs-pagination-padding-y: 0.75rem;
  --bs-pagination-font-size: 1.25rem;
  --bs-pagination-border-radius: var(--bs-border-radius-lg);
}

.pagination-sm {
  --bs-pagination-padding-x: 0.5rem;
  --bs-pagination-padding-y: 0.25rem;
  --bs-pagination-font-size: 0.875rem;
  --bs-pagination-border-radius: var(--bs-border-radius-sm);
}

.badge {
  --bs-badge-padding-x: 0.65em;
  --bs-badge-padding-y: 0.35em;
  --bs-badge-font-size: 0.75em;
  --bs-badge-font-weight: 700;
  --bs-badge-color: #fff;
  --bs-badge-border-radius: var(--bs-border-radius);
  display: inline-block;
  padding: var(--bs-badge-padding-y) var(--bs-badge-padding-x);
  font-size: var(--bs-badge-font-size);
  font-weight: var(--bs-badge-font-weight);
  line-height: 1;
  color: var(--bs-badge-color);
  text-align: center;
  white-space: nowrap;
  vertical-align: baseline;
  border-radius: var(--bs-badge-border-radius);
}
.badge:empty {
  display: none;
}

.btn .badge {
  position: relative;
  top: -1px;
}

.alert {
  --bs-alert-bg: transparent;
  --bs-alert-padding-x: 1rem;
  --bs-alert-padding-y: 1rem;
  --bs-alert-margin-bottom: 1rem;
  --bs-alert-color: inherit;
  --bs-alert-border-color: transparent;
  --bs-alert-border: var(--bs-border-width) solid var(--bs-alert-border-color);
  --bs-alert-border-radius: var(--bs-border-radius);
  --bs-alert-link-color: inherit;
  position: relative;
  padding: var(--bs-alert-padding-y) var(--bs-alert-padding-x);
  margin-bottom: var(--bs-alert-margin-bottom);
  color: var(--bs-alert-color);
  background-color: var(--bs-alert-bg);
  border: var(--bs-alert-border);
  border-radius: var(--bs-alert-border-radius);
}

.alert-heading {
  color: inherit;
}

.alert-link {
  font-weight: 700;
  color: var(--bs-alert-link-color);
}

.alert-dismissible {
  padding-right: 3rem;
}
.alert-dismissible .btn-close {
  position: absolute;
  top: 0;
  right: 0;
  z-index: 2;
  padding: 1.25rem 1rem;
}

.alert-primary {
  --bs-alert-color: var(--bs-primary-text-emphasis);
  --bs-alert-bg: var(--bs-primary-bg-subtle);
  --bs-alert-border-color: var(--bs-primary-border-subtle);
  --bs-alert-link-color: var(--bs-primary-text-emphasis);
}

.alert-secondary {
  --bs-alert-color: var(--bs-secondary-text-emphasis);
  --bs-alert-bg: var(--bs-secondary-bg-subtle);
  --bs-alert-border-color: var(--bs-secondary-border-subtle);
  --bs-alert-link-color: var(--bs-secondary-text-emphasis);
}

.alert-success {
  --bs-alert-color: var(--bs-success-text-emphasis);
  --bs-alert-bg: var(--bs-success-bg-subtle);
  --bs-alert-border-color: var(--bs-success-border-subtle);
  --bs-alert-link-color: var(--bs-success-text-emphasis);
}

.alert-info {
  --bs-alert-color: var(--bs-info-text-emphasis);
  --bs-alert-bg: var(--bs-info-bg-subtle);
  --bs-alert-border-color: var(--bs-info-border-subtle);
  --bs-alert-link-color: var(--bs-info-text-emphasis);
}

.alert-warning {
  --bs-alert-color: var(--bs-warning-text-emphasis);
  --bs-alert-bg: var(--bs-warning-bg-subtle);
  --bs-alert-border-color: var(--bs-warning-border-subtle);
  --bs-alert-link-color: var(--bs-warning-text-emphasis);
}

.alert-danger {
  --bs-alert-color: var(--bs-danger-text-emphasis);
  --bs-alert-bg: var(--bs-danger-bg-subtle);
  --bs-alert-border-color: var(--bs-danger-border-subtle);
  --bs-alert-link-color: var(--bs-danger-text-emphasis);
}

.alert-light {
  --bs-alert-color: var(--bs-light-text-emphasis);
  --bs-alert-bg: var(--bs-light-bg-subtle);
  --bs-alert-border-color: var(--bs-light-border-subtle);
  --bs-alert-link-color: var(--bs-light-text-emphasis);
}

.alert-dark {
  --bs-alert-color: var(--bs-dark-text-emphasis);
  --bs-alert-bg: var(--bs-dark-bg-subtle);
  --bs-alert-border-color: var(--bs-dark-border-subtle);
  --bs-alert-link-color: var(--bs-dark-text-emphasis);
}

@keyframes progress-bar-stripes {
  0% {
    background-position-x: var(--bs-progress-height);
  }
}
.progress,
.progress-stacked {
  --bs-progress-height: 1rem;
  --bs-progress-font-size: 0.75rem;
  --bs-progress-bg: var(--bs-secondary-bg);
  --bs-progress-border-radius: var(--bs-border-radius);
  --bs-progress-box-shadow: var(--bs-box-shadow-inset);
  --bs-progress-bar-color: #fff;
  --bs-progress-bar-bg: #0d6efd;
  --bs-progress-bar-transition: width 0.6s ease;
  display: flex;
  height: var(--bs-progress-height);
  overflow: hidden;
  font-size: var(--bs-progress-font-size);
  background-color: var(--bs-progress-bg);
  border-radius: var(--bs-progress-border-radius);
  box-shadow: var(--bs-progress-box-shadow);
}

.progress-bar {
  display: flex;
  flex-direction: column;
  justify-content: center;
  overflow: hidden;
  color: var(--bs-progress-bar-color);
  text-align: center;
  white-space: nowrap;
  background-color: var(--bs-progress-bar-bg);
  transition: var(--bs-progress-bar-transition);
}
@media (prefers-reduced-motion: reduce) {
  .progress-bar {
    transition: none;
  }
}

.progress-bar-striped {
  background-image: linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent);
  background-size: var(--bs-progress-height) var(--bs-progress-height);
}

.progress-stacked > .progress {
  overflow: visible;
}

.progress-stacked > .progress > .progress-bar {
  width: 100%;
}

.progress-bar-animated {
  animation: 1s linear infinite progress-bar-stripes;
}
@media (prefers-reduced-motion: reduce) {
  .progress-bar-animated {
    animation: none;
  }
}

.list-group {
  --bs-list-group-color: var(--bs-body-color);
  --bs-list-group-bg: var(--bs-body-bg);
  --bs-list-group-border-color: var(--bs-border-color);
  --bs-list-group-border-width: var(--bs-border-width);
  --bs-list-group-border-radius: var(--bs-border-radius);
  --bs-list-group-item-padding-x: 1rem;
  --bs-list-group-item-padding-y: 0.5rem;
  --bs-list-group-action-color: var(--bs-secondary-color);
  --bs-list-group-action-hover-color: var(--bs-emphasis-color);
  --bs-list-group-action-hover-bg: var(--bs-tertiary-bg);
  --bs-list-group-action-active-color: var(--bs-body-color);
  --bs-list-group-action-active-bg: var(--bs-secondary-bg);
  --bs-list-group-disabled-color: var(--bs-secondary-color);
  --bs-list-group-disabled-bg: var(--bs-body-bg);
  --bs-list-group-active-color: #fff;
  --bs-list-group-active-bg: #0d6efd;
  --bs-list-group-active-border-color: #0d6efd;
  display: flex;
  flex-direction: column;
  padding-left: 0;
  margin-bottom: 0;
  border-radius: var(--bs-list-group-border-radius);
}

.list-group-numbered {
  list-style-type: none;
  counter-reset: section;
}
.list-group-numbered > .list-group-item::before {
  content: counters(section, ".") ". ";
  counter-increment: section;
}

.list-group-item {
  position: relative;
  display: block;
  padding: var(--bs-list-group-item-padding-y) var(--bs-list-group-item-padding-x);
  color: var(--bs-list-group-color);
  text-decoration: none;
  background-color: var(--bs-list-group-bg);
  border: var(--bs-list-group-border-width) solid var(--bs-list-group-border-color);
}
.list-group-item:first-child {
  border-top-left-radius: inherit;
  border-top-right-radius: inherit;
}
.list-group-item:last-child {
  border-bottom-right-radius: inherit;
  border-bottom-left-radius: inherit;
}
.list-group-item.disabled, .list-group-item:disabled {
  color: var(--bs-list-group-disabled-color);
  pointer-events: none;
  background-color: var(--bs-list-group-disabled-bg);
}
.list-group-item.active {
  z-index: 2;
  color: var(--bs-list-group-active-color);
  background-color: var(--bs-list-group-active-bg);
  border-color: var(--bs-list-group-active-border-color);
}
.list-group-item + .list-group-item {
  border-top-width: 0;
}
.list-group-item + .list-group-item.active {
  margin-top: calc(-1 * var(--bs-list-group-border-width));
  border-top-width: var(--bs-list-group-border-width);
}

.list-group-item-action {
  width: 100%;
  color: var(--bs-list-group-action-color);
  text-align: inherit;
}
.list-group-item-action:not(.active):hover, .list-group-item-action:not(.active):focus {
  z-index: 1;
  color: var(--bs-list-group-action-hover-color);
  text-decoration: none;
  background-color: var(--bs-list-group-action-hover-bg);
}
.list-group-item-action:not(.active):active {
  color: var(--bs-list-group-action-active-color);
  background-color: var(--bs-list-group-action-active-bg);
}

.list-group-horizontal {
  flex-direction: row;
}
.list-group-horizontal > .list-group-item:first-child:not(:last-child) {
  border-bottom-left-radius: var(--bs-list-group-border-radius);
  border-top-right-radius: 0;
}
.list-group-horizontal > .list-group-item:last-child:not(:first-child) {
  border-top-right-radius: var(--bs-list-group-border-radius);
  border-bottom-left-radius: 0;
}
.list-group-horizontal > .list-group-item.active {
  margin-top: 0;
}
.list-group-horizontal > .list-group-item + .list-group-item {
  border-top-width: var(--bs-list-group-border-width);
  border-left-width: 0;
}
.list-group-horizontal > .list-group-item + .list-group-item.active {
  margin-left: calc(-1 * var(--bs-list-group-border-width));
  border-left-width: var(--bs-list-group-border-width);
}

@media (min-width: 576px) {
  .list-group-horizontal-sm {
    flex-direction: row;
  }
  .list-group-horizontal-sm > .list-group-item:first-child:not(:last-child) {
    border-bottom-left-radius: var(--bs-list-group-border-radius);
    border-top-right-radius: 0;
  }
  .list-group-horizontal-sm > .list-group-item:last-child:not(:first-child) {
    border-top-right-radius: var(--bs-list-group-border-radius);
    border-bottom-left-radius: 0;
  }
  .list-group-horizontal-sm > .list-group-item.active {
    margin-top: 0;
  }
  .list-group-horizontal-sm > .list-group-item + .list-group-item {
    border-top-width: var(--bs-list-group-border-width);
    border-left-width: 0;
  }
  .list-group-horizontal-sm > .list-group-item + .list-group-item.active {
    margin-left: calc(-1 * var(--bs-list-group-border-width));
    border-left-width: var(--bs-list-group-border-width);
  }
}
@media (min-width: 768px) {
  .list-group-horizontal-md {
    flex-direction: row;
  }
  .list-group-horizontal-md > .list-group-item:first-child:not(:last-child) {
    border-bottom-left-radius: var(--bs-list-group-border-radius);
    border-top-right-radius: 0;
  }
  .list-group-horizontal-md > .list-group-item:last-child:not(:first-child) {
    border-top-right-radius: var(--bs-list-group-border-radius);
    border-bottom-left-radius: 0;
  }
  .list-group-horizontal-md > .list-group-item.active {
    margin-top: 0;
  }
  .list-group-horizontal-md > .list-group-item + .list-group-item {
    border-top-width: var(--bs-list-group-border-width);
    border-left-width: 0;
  }
  .list-group-horizontal-md > .list-group-item + .list-group-item.active {
    margin-left: calc(-1 * var(--bs-list-group-border-width));
    border-left-width: var(--bs-list-group-border-width);
  }
}
@media (min-width: 992px) {
  .list-group-horizontal-lg {
    flex-direction: row;
  }
  .list-group-horizontal-lg > .list-group-item:first-child:not(:last-child) {
    border-bottom-left-radius: var(--bs-list-group-border-radius);
    border-top-right-radius: 0;
  }
  .list-group-horizontal-lg > .list-group-item:last-child:not(:first-child) {
    border-top-right-radius: var(--bs-list-group-border-radius);
    border-bottom-left-radius: 0;
  }
  .list-group-horizontal-lg > .list-group-item.active {
    margin-top: 0;
  }
  .list-group-horizontal-lg > .list-group-item + .list-group-item {
    border-top-width: var(--bs-list-group-border-width);
    border-left-width: 0;
  }
  .list-group-horizontal-lg > .list-group-item + .list-group-item.active {
    margin-left: calc(-1 * var(--bs-list-group-border-width));
    border-left-width: var(--bs-list-group-border-width);
  }
}
@media (min-width: 1200px) {
  .list-group-horizontal-xl {
    flex-direction: row;
  }
  .list-group-horizontal-xl > .list-group-item:first-child:not(:last-child) {
    border-bottom-left-radius: var(--bs-list-group-border-radius);
    border-top-right-radius: 0;
  }
  .list-group-horizontal-xl > .list-group-item:last-child:not(:first-child) {
    border-top-right-radius: var(--bs-list-group-border-radius);
    border-bottom-left-radius: 0;
  }
  .list-group-horizontal-xl > .list-group-item.active {
    margin-top: 0;
  }
  .list-group-horizontal-xl > .list-group-item + .list-group-item {
    border-top-width: var(--bs-list-group-border-width);
    border-left-width: 0;
  }
  .list-group-horizontal-xl > .list-group-item + .list-group-item.active {
    margin-left: calc(-1 * var(--bs-list-group-border-width));
    border-left-width: var(--bs-list-group-border-width);
  }
}
@media (min-width: 1400px) {
  .list-group-horizontal-xxl {
    flex-direction: row;
  }
  .list-group-horizontal-xxl > .list-group-item:first-child:not(:last-child) {
    border-bottom-left-radius: var(--bs-list-group-border-radius);
    border-top-right-radius: 0;
  }
  .list-group-horizontal-xxl > .list-group-item:last-child:not(:first-child) {
    border-top-right-radius: var(--bs-list-group-border-radius);
    border-bottom-left-radius: 0;
  }
  .list-group-horizontal-xxl > .list-group-item.active {
    margin-top: 0;
  }
  .list-group-horizontal-xxl > .list-group-item + .list-group-item {
    border-top-width: var(--bs-list-group-border-width);
    border-left-width: 0;
  }
  .list-group-horizontal-xxl > .list-group-item + .list-group-item.active {
    margin-left: calc(-1 * var(--bs-list-group-border-width));
    border-left-width: var(--bs-list-group-border-width);
  }
}
.list-group-flush {
  border-radius: 0;
}
.list-group-flush > .list-group-item {
  border-width: 0 0 var(--bs-list-group-border-width);
}
.list-group-flush > .list-group-item:last-child {
  border-bottom-width: 0;
}

.list-group-item-primary {
  --bs-list-group-color: var(--bs-primary-text-emphasis);
  --bs-list-group-bg: var(--bs-primary-bg-subtle);
  --bs-list-group-border-color: var(--bs-primary-border-subtle);
  --bs-list-group-action-hover-color: var(--bs-emphasis-color);
  --bs-list-group-action-hover-bg: var(--bs-primary-border-subtle);
  --bs-list-group-action-active-color: var(--bs-emphasis-color);
  --bs-list-group-action-active-bg: var(--bs-primary-border-subtle);
  --bs-list-group-active-color: var(--bs-primary-bg-subtle);
  --bs-list-group-active-bg: var(--bs-primary-text-emphasis);
  --bs-list-group-active-border-color: var(--bs-primary-text-emphasis);
}

.list-group-item-secondary {
  --bs-list-group-color: var(--bs-secondary-text-emphasis);
  --bs-list-group-bg: var(--bs-secondary-bg-subtle);
  --bs-list-group-border-color: var(--bs-secondary-border-subtle);
  --bs-list-group-action-hover-color: var(--bs-emphasis-color);
  --bs-list-group-action-hover-bg: var(--bs-secondary-border-subtle);
  --bs-list-group-action-active-color: var(--bs-emphasis-color);
  --bs-list-group-action-active-bg: var(--bs-secondary-border-subtle);
  --bs-list-group-active-color: var(--bs-secondary-bg-subtle);
  --bs-list-group-active-bg: var(--bs-secondary-text-emphasis);
  --bs-list-group-active-border-color: var(--bs-secondary-text-emphasis);
}

.list-group-item-success {
  --bs-list-group-color: var(--bs-success-text-emphasis);
  --bs-list-group-bg: var(--bs-success-bg-subtle);
  --bs-list-group-border-color: var(--bs-success-border-subtle);
  --bs-list-group-action-hover-color: var(--bs-emphasis-color);
  --bs-list-group-action-hover-bg: var(--bs-success-border-subtle);
  --bs-list-group-action-active-color: var(--bs-emphasis-color);
  --bs-list-group-action-active-bg: var(--bs-success-border-subtle);
  --bs-list-group-active-color: var(--bs-success-bg-subtle);
  --bs-list-group-active-bg: var(--bs-success-text-emphasis);
  --bs-list-group-active-border-color: var(--bs-success-text-emphasis);
}

.list-group-item-info {
  --bs-list-group-color: var(--bs-info-text-emphasis);
  --bs-list-group-bg: var(--bs-info-bg-subtle);
  --bs-list-group-border-color: var(--bs-info-border-subtle);
  --bs-list-group-action-hover-color: var(--bs-emphasis-color);
  --bs-list-group-action-hover-bg: var(--bs-info-border-subtle);
  --bs-list-group-action-active-color: var(--bs-emphasis-color);
  --bs-list-group-action-active-bg: var(--bs-info-border-subtle);
  --bs-list-group-active-color: var(--bs-info-bg-subtle);
  --bs-list-group-active-bg: var(--bs-info-text-emphasis);
  --bs-list-group-active-border-color: var(--bs-info-text-emphasis);
}

.list-group-item-warning {
  --bs-list-group-color: var(--bs-warning-text-emphasis);
  --bs-list-group-bg: var(--bs-warning-bg-subtle);
  --bs-list-group-border-color: var(--bs-warning-border-subtle);
  --bs-list-group-action-hover-color: var(--bs-emphasis-color);
  --bs-list-group-action-hover-bg: var(--bs-warning-border-subtle);
  --bs-list-group-action-active-color: var(--bs-emphasis-color);
  --bs-list-group-action-active-bg: var(--bs-warning-border-subtle);
  --bs-list-group-active-color: var(--bs-warning-bg-subtle);
  --bs-list-group-active-bg: var(--bs-warning-text-emphasis);
  --bs-list-group-active-border-color: var(--bs-warning-text-emphasis);
}

.list-group-item-danger {
  --bs-list-group-color: var(--bs-danger-text-emphasis);
  --bs-list-group-bg: var(--bs-danger-bg-subtle);
  --bs-list-group-border-color: var(--bs-danger-border-subtle);
  --bs-list-group-action-hover-color: var(--bs-emphasis-color);
  --bs-list-group-action-hover-bg: var(--bs-danger-border-subtle);
  --bs-list-group-action-active-color: var(--bs-emphasis-color);
  --bs-list-group-action-active-bg: var(--bs-danger-border-subtle);
  --bs-list-group-active-color: var(--bs-danger-bg-subtle);
  --bs-list-group-active-bg: var(--bs-danger-text-emphasis);
  --bs-list-group-active-border-color: var(--bs-danger-text-emphasis);
}

.list-group-item-light {
  --bs-list-group-color: var(--bs-light-text-emphasis);
  --bs-list-group-bg: var(--bs-light-bg-subtle);
  --bs-list-group-border-color: var(--bs-light-border-subtle);
  --bs-list-group-action-hover-color: var(--bs-emphasis-color);
  --bs-list-group-action-hover-bg: var(--bs-light-border-subtle);
  --bs-list-group-action-active-color: var(--bs-emphasis-color);
  --bs-list-group-action-active-bg: var(--bs-light-border-subtle);
  --bs-list-group-active-color: var(--bs-light-bg-subtle);
  --bs-list-group-active-bg: var(--bs-light-text-emphasis);
  --bs-list-group-active-border-color: var(--bs-light-text-emphasis);
}

.list-group-item-dark {
  --bs-list-group-color: var(--bs-dark-text-emphasis);
  --bs-list-group-bg: var(--bs-dark-bg-subtle);
  --bs-list-group-border-color: var(--bs-dark-border-subtle);
  --bs-list-group-action-hover-color: var(--bs-emphasis-color);
  --bs-list-group-action-hover-bg: var(--bs-dark-border-subtle);
  --bs-list-group-action-active-color: var(--bs-emphasis-color);
  --bs-list-group-action-active-bg: var(--bs-dark-border-subtle);
  --bs-list-group-active-color: var(--bs-dark-bg-subtle);
  --bs-list-group-active-bg: var(--bs-dark-text-emphasis);
  --bs-list-group-active-border-color: var(--bs-dark-text-emphasis);
}

.btn-close {
  --bs-btn-close-color: #000;
  --bs-btn-close-bg: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414'/%3e%3c/svg%3e");
  --bs-btn-close-opacity: 0.5;
  --bs-btn-close-hover-opacity: 0.75;
  --bs-btn-close-focus-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
  --bs-btn-close-focus-opacity: 1;
  --bs-btn-close-disabled-opacity: 0.25;
  box-sizing: content-box;
  width: 1em;
  height: 1em;
  padding: 0.25em 0.25em;
  color: var(--bs-btn-close-color);
  background: transparent var(--bs-btn-close-bg) center/1em auto no-repeat;
  filter: var(--bs-btn-close-filter);
  border: 0;
  border-radius: 0.375rem;
  opacity: var(--bs-btn-close-opacity);
}
.btn-close:hover {
  color: var(--bs-btn-close-color);
  text-decoration: none;
  opacity: var(--bs-btn-close-hover-opacity);
}
.btn-close:focus {
  outline: 0;
  box-shadow: var(--bs-btn-close-focus-shadow);
  opacity: var(--bs-btn-close-focus-opacity);
}
.btn-close:disabled, .btn-close.disabled {
  pointer-events: none;
  -webkit-user-select: none;
  -moz-user-select: none;
  user-select: none;
  opacity: var(--bs-btn-close-disabled-opacity);
}

.btn-close-white {
  --bs-btn-close-filter: invert(1) grayscale(100%) brightness(200%);
}

:root,
[data-bs-theme=light] {
  --bs-btn-close-filter: ;
}

[data-bs-theme=dark] {
  --bs-btn-close-filter: invert(1) grayscale(100%) brightness(200%);
}

.toast {
  --bs-toast-zindex: 1090;
  --bs-toast-padding-x: 0.75rem;
  --bs-toast-padding-y: 0.5rem;
  --bs-toast-spacing: 1.5rem;
  --bs-toast-max-width: 350px;
  --bs-toast-font-size: 0.875rem;
  --bs-toast-color: ;
  --bs-toast-bg: rgba(var(--bs-body-bg-rgb), 0.85);
  --bs-toast-border-width: var(--bs-border-width);
  --bs-toast-border-color: var(--bs-border-color-translucent);
  --bs-toast-border-radius: var(--bs-border-radius);
  --bs-toast-box-shadow: var(--bs-box-shadow);
  --bs-toast-header-color: var(--bs-secondary-color);
  --bs-toast-header-bg: rgba(var(--bs-body-bg-rgb), 0.85);
  --bs-toast-header-border-color: var(--bs-border-color-translucent);
  width: var(--bs-toast-max-width);
  max-width: 100%;
  font-size: var(--bs-toast-font-size);
  color: var(--bs-toast-color);
  pointer-events: auto;
  background-color: var(--bs-toast-bg);
  background-clip: padding-box;
  border: var(--bs-toast-border-width) solid var(--bs-toast-border-color);
  box-shadow: var(--bs-toast-box-shadow);
  border-radius: var(--bs-toast-border-radius);
}
.toast.showing {
  opacity: 0;
}
.toast:not(.show) {
  display: none;
}

.toast-container {
  --bs-toast-zindex: 1090;
  position: absolute;
  z-index: var(--bs-toast-zindex);
  width: -moz-max-content;
  width: max-content;
  max-width: 100%;
  pointer-events: none;
}
.toast-container > :not(:last-child) {
  margin-bottom: var(--bs-toast-spacing);
}

.toast-header {
  display: flex;
  align-items: center;
  padding: var(--bs-toast-padding-y) var(--bs-toast-padding-x);
  color: var(--bs-toast-header-color);
  background-color: var(--bs-toast-header-bg);
  background-clip: padding-box;
  border-bottom: var(--bs-toast-border-width) solid var(--bs-toast-header-border-color);
  border-top-left-radius: calc(var(--bs-toast-border-radius) - var(--bs-toast-border-width));
  border-top-right-radius: calc(var(--bs-toast-border-radius) - var(--bs-toast-border-width));
}
.toast-header .btn-close {
  margin-right: calc(-0.5 * var(--bs-toast-padding-x));
  margin-left: var(--bs-toast-padding-x);
}

.toast-body {
  padding: var(--bs-toast-padding-x);
  word-wrap: break-word;
}

.modal {
  --bs-modal-zindex: 1055;
  --bs-modal-width: 500px;
  --bs-modal-padding: 1rem;
  --bs-modal-margin: 0.5rem;
  --bs-modal-color: var(--bs-body-color);
  --bs-modal-bg: var(--bs-body-bg);
  --bs-modal-border-color: var(--bs-border-color-translucent);
  --bs-modal-border-width: var(--bs-border-width);
  --bs-modal-border-radius: var(--bs-border-radius-lg);
  --bs-modal-box-shadow: var(--bs-box-shadow-sm);
  --bs-modal-inner-border-radius: calc(var(--bs-border-radius-lg) - (var(--bs-border-width)));
  --bs-modal-header-padding-x: 1rem;
  --bs-modal-header-padding-y: 1rem;
  --bs-modal-header-padding: 1rem 1rem;
  --bs-modal-header-border-color: var(--bs-border-color);
  --bs-modal-header-border-width: var(--bs-border-width);
  --bs-modal-title-line-height: 1.5;
  --bs-modal-footer-gap: 0.5rem;
  --bs-modal-footer-bg: ;
  --bs-modal-footer-border-color: var(--bs-border-color);
  --bs-modal-footer-border-width: var(--bs-border-width);
  position: fixed;
  top: 0;
  left: 0;
  z-index: var(--bs-modal-zindex);
  display: none;
  width: 100%;
  height: 100%;
  overflow-x: hidden;
  overflow-y: auto;
  outline: 0;
}

.modal-dialog {
  position: relative;
  width: auto;
  margin: var(--bs-modal-margin);
  pointer-events: none;
}
.modal.fade .modal-dialog {
  transform: translate(0, -50px);
  transition: transform 0.3s ease-out;
}
@media (prefers-reduced-motion: reduce) {
  .modal.fade .modal-dialog {
    transition: none;
  }
}
.modal.show .modal-dialog {
  transform: none;
}
.modal.modal-static .modal-dialog {
  transform: scale(1.02);
}

.modal-dialog-scrollable {
  height: calc(100% - var(--bs-modal-margin) * 2);
}
.modal-dialog-scrollable .modal-content {
  max-height: 100%;
  overflow: hidden;
}
.modal-dialog-scrollable .modal-body {
  overflow-y: auto;
}

.modal-dialog-centered {
  display: flex;
  align-items: center;
  min-height: calc(100% - var(--bs-modal-margin) * 2);
}

.modal-content {
  position: relative;
  display: flex;
  flex-direction: column;
  width: 100%;
  color: var(--bs-modal-color);
  pointer-events: auto;
  background-color: var(--bs-modal-bg);
  background-clip: padding-box;
  border: var(--bs-modal-border-width) solid var(--bs-modal-border-color);
  border-radius: var(--bs-modal-border-radius);
  box-shadow: var(--bs-modal-box-shadow);
  outline: 0;
}

.modal-backdrop {
  --bs-backdrop-zindex: 1050;
  --bs-backdrop-bg: #000;
  --bs-backdrop-opacity: 0.5;
  position: fixed;
  top: 0;
  left: 0;
  z-index: var(--bs-backdrop-zindex);
  width: 100vw;
  height: 100vh;
  background-color: var(--bs-backdrop-bg);
}
.modal-backdrop.fade {
  opacity: 0;
}
.modal-backdrop.show {
  opacity: var(--bs-backdrop-opacity);
}

.modal-header {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  padding: var(--bs-modal-header-padding);
  border-bottom: var(--bs-modal-header-border-width) solid var(--bs-modal-header-border-color);
  border-top-left-radius: var(--bs-modal-inner-border-radius);
  border-top-right-radius: var(--bs-modal-inner-border-radius);
}
.modal-header .btn-close {
  padding: calc(var(--bs-modal-header-padding-y) * 0.5) calc(var(--bs-modal-header-padding-x) * 0.5);
  margin-top: calc(-0.5 * var(--bs-modal-header-padding-y));
  margin-right: calc(-0.5 * var(--bs-modal-header-padding-x));
  margin-bottom: calc(-0.5 * var(--bs-modal-header-padding-y));
  margin-left: auto;
}

.modal-title {
  margin-bottom: 0;
  line-height: var(--bs-modal-title-line-height);
}

.modal-body {
  position: relative;
  flex: 1 1 auto;
  padding: var(--bs-modal-padding);
}

.modal-footer {
  display: flex;
  flex-shrink: 0;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  padding: calc(var(--bs-modal-padding) - var(--bs-modal-footer-gap) * 0.5);
  background-color: var(--bs-modal-footer-bg);
  border-top: var(--bs-modal-footer-border-width) solid var(--bs-modal-footer-border-color);
  border-bottom-right-radius: var(--bs-modal-inner-border-radius);
  border-bottom-left-radius: var(--bs-modal-inner-border-radius);
}
.modal-footer > * {
  margin: calc(var(--bs-modal-footer-gap) * 0.5);
}

@media (min-width: 576px) {
  .modal {
    --bs-modal-margin: 1.75rem;
    --bs-modal-box-shadow: var(--bs-box-shadow);
  }
  .modal-dialog {
    max-width: var(--bs-modal-width);
    margin-right: auto;
    margin-left: auto;
  }
  .modal-sm {
    --bs-modal-width: 300px;
  }
}
@media (min-width: 992px) {
  .modal-lg,
  .modal-xl {
    --bs-modal-width: 800px;
  }
}
@media (min-width: 1200px) {
  .modal-xl {
    --bs-modal-width: 1140px;
  }
}
.modal-fullscreen {
  width: 100vw;
  max-width: none;
  height: 100%;
  margin: 0;
}
.modal-fullscreen .modal-content {
  height: 100%;
  border: 0;
  border-radius: 0;
}
.modal-fullscreen .modal-header,
.modal-fullscreen .modal-footer {
  border-radius: 0;
}
.modal-fullscreen .modal-body {
  overflow-y: auto;
}

@media (max-width: 575.98px) {
  .modal-fullscreen-sm-down {
    width: 100vw;
    max-width: none;
    height: 100%;
    margin: 0;
  }
  .modal-fullscreen-sm-down .modal-content {
    height: 100%;
    border: 0;
    border-radius: 0;
  }
  .modal-fullscreen-sm-down .modal-header,
  .modal-fullscreen-sm-down .modal-footer {
    border-radius: 0;
  }
  .modal-fullscreen-sm-down .modal-body {
    overflow-y: auto;
  }
}
@media (max-width: 767.98px) {
  .modal-fullscreen-md-down {
    width: 100vw;
    max-width: none;
    height: 100%;
    margin: 0;
  }
  .modal-fullscreen-md-down .modal-content {
    height: 100%;
    border: 0;
    border-radius: 0;
  }
  .modal-fullscreen-md-down .modal-header,
  .modal-fullscreen-md-down .modal-footer {
    border-radius: 0;
  }
  .modal-fullscreen-md-down .modal-body {
    overflow-y: auto;
  }
}
@media (max-width: 991.98px) {
  .modal-fullscreen-lg-down {
    width: 100vw;
    max-width: none;
    height: 100%;
    margin: 0;
  }
  .modal-fullscreen-lg-down .modal-content {
    height: 100%;
    border: 0;
    border-radius: 0;
  }
  .modal-fullscreen-lg-down .modal-header,
  .modal-fullscreen-lg-down .modal-footer {
    border-radius: 0;
  }
  .modal-fullscreen-lg-down .modal-body {
    overflow-y: auto;
  }
}
@media (max-width: 1199.98px) {
  .modal-fullscreen-xl-down {
    width: 100vw;
    max-width: none;
    height: 100%;
    margin: 0;
  }
  .modal-fullscreen-xl-down .modal-content {
    height: 100%;
    border: 0;
    border-radius: 0;
  }
  .modal-fullscreen-xl-down .modal-header,
  .modal-fullscreen-xl-down .modal-footer {
    border-radius: 0;
  }
  .modal-fullscreen-xl-down .modal-body {
    overflow-y: auto;
  }
}
@media (max-width: 1399.98px) {
  .modal-fullscreen-xxl-down {
    width: 100vw;
    max-width: none;
    height: 100%;
    margin: 0;
  }
  .modal-fullscreen-xxl-down .modal-content {
    height: 100%;
    border: 0;
    border-radius: 0;
  }
  .modal-fullscreen-xxl-down .modal-header,
  .modal-fullscreen-xxl-down .modal-footer {
    border-radius: 0;
  }
  .modal-fullscreen-xxl-down .modal-body {
    overflow-y: auto;
  }
}
.tooltip {
  --bs-tooltip-zindex: 1080;
  --bs-tooltip-max-width: 200px;
  --bs-tooltip-padding-x: 0.5rem;
  --bs-tooltip-padding-y: 0.25rem;
  --bs-tooltip-margin: ;
  --bs-tooltip-font-size: 0.875rem;
  --bs-tooltip-color: var(--bs-body-bg);
  --bs-tooltip-bg: var(--bs-emphasis-color);
  --bs-tooltip-border-radius: var(--bs-border-radius);
  --bs-tooltip-opacity: 0.9;
  --bs-tooltip-arrow-width: 0.8rem;
  --bs-tooltip-arrow-height: 0.4rem;
  z-index: var(--bs-tooltip-zindex);
  display: block;
  margin: var(--bs-tooltip-margin);
  font-family: var(--bs-font-sans-serif);
  font-style: normal;
  font-weight: 400;
  line-height: 1.5;
  text-align: left;
  text-align: start;
  text-decoration: none;
  text-shadow: none;
  text-transform: none;
  letter-spacing: normal;
  word-break: normal;
  white-space: normal;
  word-spacing: normal;
  line-break: auto;
  font-size: var(--bs-tooltip-font-size);
  word-wrap: break-word;
  opacity: 0;
}
.tooltip.show {
  opacity: var(--bs-tooltip-opacity);
}
.tooltip .tooltip-arrow {
  display: block;
  width: var(--bs-tooltip-arrow-width);
  height: var(--bs-tooltip-arrow-height);
}
.tooltip .tooltip-arrow::before {
  position: absolute;
  content: "";
  border-color: transparent;
  border-style: solid;
}

.bs-tooltip-top .tooltip-arrow, .bs-tooltip-auto[data-popper-placement^=top] .tooltip-arrow {
  bottom: calc(-1 * var(--bs-tooltip-arrow-height));
}
.bs-tooltip-top .tooltip-arrow::before, .bs-tooltip-auto[data-popper-placement^=top] .tooltip-arrow::before {
  top: -1px;
  border-width: var(--bs-tooltip-arrow-height) calc(var(--bs-tooltip-arrow-width) * 0.5) 0;
  border-top-color: var(--bs-tooltip-bg);
}

/* rtl:begin:ignore */
.bs-tooltip-end .tooltip-arrow, .bs-tooltip-auto[data-popper-placement^=right] .tooltip-arrow {
  left: calc(-1 * var(--bs-tooltip-arrow-height));
  width: var(--bs-tooltip-arrow-height);
  height: var(--bs-tooltip-arrow-width);
}
.bs-tooltip-end .tooltip-arrow::before, .bs-tooltip-auto[data-popper-placement^=right] .tooltip-arrow::before {
  right: -1px;
  border-width: calc(var(--bs-tooltip-arrow-width) * 0.5) var(--bs-tooltip-arrow-height) calc(var(--bs-tooltip-arrow-width) * 0.5) 0;
  border-right-color: var(--bs-tooltip-bg);
}

/* rtl:end:ignore */
.bs-tooltip-bottom .tooltip-arrow, .bs-tooltip-auto[data-popper-placement^=bottom] .tooltip-arrow {
  top: calc(-1 * var(--bs-tooltip-arrow-height));
}
.bs-tooltip-bottom .tooltip-arrow::before, .bs-tooltip-auto[data-popper-placement^=bottom] .tooltip-arrow::before {
  bottom: -1px;
  border-width: 0 calc(var(--bs-tooltip-arrow-width) * 0.5) var(--bs-tooltip-arrow-height);
  border-bottom-color: var(--bs-tooltip-bg);
}

/* rtl:begin:ignore */
.bs-tooltip-start .tooltip-arrow, .bs-tooltip-auto[data-popper-placement^=left] .tooltip-arrow {
  right: calc(-1 * var(--bs-tooltip-arrow-height));
  width: var(--bs-tooltip-arrow-height);
  height: var(--bs-tooltip-arrow-width);
}
.bs-tooltip-start .tooltip-arrow::before, .bs-tooltip-auto[data-popper-placement^=left] .tooltip-arrow::before {
  left: -1px;
  border-width: calc(var(--bs-tooltip-arrow-width) * 0.5) 0 calc(var(--bs-tooltip-arrow-width) * 0.5) var(--bs-tooltip-arrow-height);
  border-left-color: var(--bs-tooltip-bg);
}

/* rtl:end:ignore */
.tooltip-inner {
  max-width: var(--bs-tooltip-max-width);
  padding: var(--bs-tooltip-padding-y) var(--bs-tooltip-padding-x);
  color: var(--bs-tooltip-color);
  text-align: center;
  background-color: var(--bs-tooltip-bg);
  border-radius: var(--bs-tooltip-border-radius);
}

.popover {
  --bs-popover-zindex: 1070;
  --bs-popover-max-width: 276px;
  --bs-popover-font-size: 0.875rem;
  --bs-popover-bg: var(--bs-body-bg);
  --bs-popover-border-width: var(--bs-border-width);
  --bs-popover-border-color: var(--bs-border-color-translucent);
  --bs-popover-border-radius: var(--bs-border-radius-lg);
  --bs-popover-inner-border-radius: calc(var(--bs-border-radius-lg) - var(--bs-border-width));
  --bs-popover-box-shadow: var(--bs-box-shadow);
  --bs-popover-header-padding-x: 1rem;
  --bs-popover-header-padding-y: 0.5rem;
  --bs-popover-header-font-size: 1rem;
  --bs-popover-header-color: inherit;
  --bs-popover-header-bg: var(--bs-secondary-bg);
  --bs-popover-body-padding-x: 1rem;
  --bs-popover-body-padding-y: 1rem;
  --bs-popover-body-color: var(--bs-body-color);
  --bs-popover-arrow-width: 1rem;
  --bs-popover-arrow-height: 0.5rem;
  --bs-popover-arrow-border: var(--bs-popover-border-color);
  z-index: var(--bs-popover-zindex);
  display: block;
  max-width: var(--bs-popover-max-width);
  font-family: var(--bs-font-sans-serif);
  font-style: normal;
  font-weight: 400;
  line-height: 1.5;
  text-align: left;
  text-align: start;
  text-decoration: none;
  text-shadow: none;
  text-transform: none;
  letter-spacing: normal;
  word-break: normal;
  white-space: normal;
  word-spacing: normal;
  line-break: auto;
  font-size: var(--bs-popover-font-size);
  word-wrap: break-word;
  background-color: var(--bs-popover-bg);
  background-clip: padding-box;
  border: var(--bs-popover-border-width) solid var(--bs-popover-border-color);
  border-radius: var(--bs-popover-border-radius);
  box-shadow: var(--bs-popover-box-shadow);
}
.popover .popover-arrow {
  display: block;
  width: var(--bs-popover-arrow-width);
  height: var(--bs-popover-arrow-height);
}
.popover .popover-arrow::before, .popover .popover-arrow::after {
  position: absolute;
  display: block;
  content: "";
  border-color: transparent;
  border-style: solid;
  border-width: 0;
}

.bs-popover-top > .popover-arrow, .bs-popover-auto[data-popper-placement^=top] > .popover-arrow {
  bottom: calc(-1 * (var(--bs-popover-arrow-height)) - var(--bs-popover-border-width));
}
.bs-popover-top > .popover-arrow::before, .bs-popover-auto[data-popper-placement^=top] > .popover-arrow::before, .bs-popover-top > .popover-arrow::after, .bs-popover-auto[data-popper-placement^=top] > .popover-arrow::after {
  border-width: var(--bs-popover-arrow-height) calc(var(--bs-popover-arrow-width) * 0.5) 0;
}
.bs-popover-top > .popover-arrow::before, .bs-popover-auto[data-popper-placement^=top] > .popover-arrow::before {
  bottom: 0;
  border-top-color: var(--bs-popover-arrow-border);
}
.bs-popover-top > .popover-arrow::after, .bs-popover-auto[data-popper-placement^=top] > .popover-arrow::after {
  bottom: var(--bs-popover-border-width);
  border-top-color: var(--bs-popover-bg);
}

/* rtl:begin:ignore */
.bs-popover-end > .popover-arrow, .bs-popover-auto[data-popper-placement^=right] > .popover-arrow {
  left: calc(-1 * (var(--bs-popover-arrow-height)) - var(--bs-popover-border-width));
  width: var(--bs-popover-arrow-height);
  height: var(--bs-popover-arrow-width);
}
.bs-popover-end > .popover-arrow::before, .bs-popover-auto[data-popper-placement^=right] > .popover-arrow::before, .bs-popover-end > .popover-arrow::after, .bs-popover-auto[data-popper-placement^=right] > .popover-arrow::after {
  border-width: calc(var(--bs-popover-arrow-width) * 0.5) var(--bs-popover-arrow-height) calc(var(--bs-popover-arrow-width) * 0.5) 0;
}
.bs-popover-end > .popover-arrow::before, .bs-popover-auto[data-popper-placement^=right] > .popover-arrow::before {
  left: 0;
  border-right-color: var(--bs-popover-arrow-border);
}
.bs-popover-end > .popover-arrow::after, .bs-popover-auto[data-popper-placement^=right] > .popover-arrow::after {
  left: var(--bs-popover-border-width);
  border-right-color: var(--bs-popover-bg);
}

/* rtl:end:ignore */
.bs-popover-bottom > .popover-arrow, .bs-popover-auto[data-popper-placement^=bottom] > .popover-arrow {
  top: calc(-1 * (var(--bs-popover-arrow-height)) - var(--bs-popover-border-width));
}
.bs-popover-bottom > .popover-arrow::before, .bs-popover-auto[data-popper-placement^=bottom] > .popover-arrow::before, .bs-popover-bottom > .popover-arrow::after, .bs-popover-auto[data-popper-placement^=bottom] > .popover-arrow::after {
  border-width: 0 calc(var(--bs-popover-arrow-width) * 0.5) var(--bs-popover-arrow-height);
}
.bs-popover-bottom > .popover-arrow::before, .bs-popover-auto[data-popper-placement^=bottom] > .popover-arrow::before {
  top: 0;
  border-bottom-color: var(--bs-popover-arrow-border);
}
.bs-popover-bottom > .popover-arrow::after, .bs-popover-auto[data-popper-placement^=bottom] > .popover-arrow::after {
  top: var(--bs-popover-border-width);
  border-bottom-color: var(--bs-popover-bg);
}
.bs-popover-bottom .popover-header::before, .bs-popover-auto[data-popper-placement^=bottom] .popover-header::before {
  position: absolute;
  top: 0;
  left: 50%;
  display: block;
  width: var(--bs-popover-arrow-width);
  margin-left: calc(-0.5 * var(--bs-popover-arrow-width));
  content: "";
  border-bottom: var(--bs-popover-border-width) solid var(--bs-popover-header-bg);
}

/* rtl:begin:ignore */
.bs-popover-start > .popover-arrow, .bs-popover-auto[data-popper-placement^=left] > .popover-arrow {
  right: calc(-1 * (var(--bs-popover-arrow-height)) - var(--bs-popover-border-width));
  width: var(--bs-popover-arrow-height);
  height: var(--bs-popover-arrow-width);
}
.bs-popover-start > .popover-arrow::before, .bs-popover-auto[data-popper-placement^=left] > .popover-arrow::before, .bs-popover-start > .popover-arrow::after, .bs-popover-auto[data-popper-placement^=left] > .popover-arrow::after {
  border-width: calc(var(--bs-popover-arrow-width) * 0.5) 0 calc(var(--bs-popover-arrow-width) * 0.5) var(--bs-popover-arrow-height);
}
.bs-popover-start > .popover-arrow::before, .bs-popover-auto[data-popper-placement^=left] > .popover-arrow::before {
  right: 0;
  border-left-color: var(--bs-popover-arrow-border);
}
.bs-popover-start > .popover-arrow::after, .bs-popover-auto[data-popper-placement^=left] > .popover-arrow::after {
  right: var(--bs-popover-border-width);
  border-left-color: var(--bs-popover-bg);
}

/* rtl:end:ignore */
.popover-header {
  padding: var(--bs-popover-header-padding-y) var(--bs-popover-header-padding-x);
  margin-bottom: 0;
  font-size: var(--bs-popover-header-font-size);
  color: var(--bs-popover-header-color);
  background-color: var(--bs-popover-header-bg);
  border-bottom: var(--bs-popover-border-width) solid var(--bs-popover-border-color);
  border-top-left-radius: var(--bs-popover-inner-border-radius);
  border-top-right-radius: var(--bs-popover-inner-border-radius);
}
.popover-header:empty {
  display: none;
}

.popover-body {
  padding: var(--bs-popover-body-padding-y) var(--bs-popover-body-padding-x);
  color: var(--bs-popover-body-color);
}

.carousel {
  position: relative;
}

.carousel.pointer-event {
  touch-action: pan-y;
}

.carousel-inner {
  position: relative;
  width: 100%;
  overflow: hidden;
}
.carousel-inner::after {
  display: block;
  clear: both;
  content: "";
}

.carousel-item {
  position: relative;
  display: none;
  float: left;
  width: 100%;
  margin-right: -100%;
  backface-visibility: hidden;
  transition: transform 0.6s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .carousel-item {
    transition: none;
  }
}

.carousel-item.active,
.carousel-item-next,
.carousel-item-prev {
  display: block;
}

.carousel-item-next:not(.carousel-item-start),
.active.carousel-item-end {
  transform: translateX(100%);
}

.carousel-item-prev:not(.carousel-item-end),
.active.carousel-item-start {
  transform: translateX(-100%);
}

.carousel-fade .carousel-item {
  opacity: 0;
  transition-property: opacity;
  transform: none;
}
.carousel-fade .carousel-item.active,
.carousel-fade .carousel-item-next.carousel-item-start,
.carousel-fade .carousel-item-prev.carousel-item-end {
  z-index: 1;
  opacity: 1;
}
.carousel-fade .active.carousel-item-start,
.carousel-fade .active.carousel-item-end {
  z-index: 0;
  opacity: 0;
  transition: opacity 0s 0.6s;
}
@media (prefers-reduced-motion: reduce) {
  .carousel-fade .active.carousel-item-start,
  .carousel-fade .active.carousel-item-end {
    transition: none;
  }
}

.carousel-control-prev,
.carousel-control-next {
  position: absolute;
  top: 0;
  bottom: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 15%;
  padding: 0;
  color: #fff;
  text-align: center;
  background: none;
  filter: var(--bs-carousel-control-icon-filter);
  border: 0;
  opacity: 0.5;
  transition: opacity 0.15s ease;
}
@media (prefers-reduced-motion: reduce) {
  .carousel-control-prev,
  .carousel-control-next {
    transition: none;
  }
}
.carousel-control-prev:hover, .carousel-control-prev:focus,
.carousel-control-next:hover,
.carousel-control-next:focus {
  color: #fff;
  text-decoration: none;
  outline: 0;
  opacity: 0.9;
}

.carousel-control-prev {
  left: 0;
}

.carousel-control-next {
  right: 0;
}

.carousel-control-prev-icon,
.carousel-control-next-icon {
  display: inline-block;
  width: 2rem;
  height: 2rem;
  background-repeat: no-repeat;
  background-position: 50%;
  background-size: 100% 100%;
}

.carousel-control-prev-icon {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0'/%3e%3c/svg%3e") /*rtl:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708'/%3e%3c/svg%3e")*/;
}

.carousel-control-next-icon {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708'/%3e%3c/svg%3e") /*rtl:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0'/%3e%3c/svg%3e")*/;
}

.carousel-indicators {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 2;
  display: flex;
  justify-content: center;
  padding: 0;
  margin-right: 15%;
  margin-bottom: 1rem;
  margin-left: 15%;
}
.carousel-indicators [data-bs-target] {
  box-sizing: content-box;
  flex: 0 1 auto;
  width: 30px;
  height: 3px;
  padding: 0;
  margin-right: 3px;
  margin-left: 3px;
  text-indent: -999px;
  cursor: pointer;
  background-color: var(--bs-carousel-indicator-active-bg);
  background-clip: padding-box;
  border: 0;
  border-top: 10px solid transparent;
  border-bottom: 10px solid transparent;
  opacity: 0.5;
  transition: opacity 0.6s ease;
}
@media (prefers-reduced-motion: reduce) {
  .carousel-indicators [data-bs-target] {
    transition: none;
  }
}
.carousel-indicators .active {
  opacity: 1;
}

.carousel-caption {
  position: absolute;
  right: 15%;
  bottom: 1.25rem;
  left: 15%;
  padding-top: 1.25rem;
  padding-bottom: 1.25rem;
  color: var(--bs-carousel-caption-color);
  text-align: center;
}

.carousel-dark {
  --bs-carousel-indicator-active-bg: #000;
  --bs-carousel-caption-color: #000;
  --bs-carousel-control-icon-filter: invert(1) grayscale(100);
}

:root,
[data-bs-theme=light] {
  --bs-carousel-indicator-active-bg: #fff;
  --bs-carousel-caption-color: #fff;
  --bs-carousel-control-icon-filter: ;
}

[data-bs-theme=dark] {
  --bs-carousel-indicator-active-bg: #000;
  --bs-carousel-caption-color: #000;
  --bs-carousel-control-icon-filter: invert(1) grayscale(100);
}

.spinner-grow,
.spinner-border {
  display: inline-block;
  width: var(--bs-spinner-width);
  height: var(--bs-spinner-height);
  vertical-align: var(--bs-spinner-vertical-align);
  border-radius: 50%;
  animation: var(--bs-spinner-animation-speed) linear infinite var(--bs-spinner-animation-name);
}

@keyframes spinner-border {
  to {
    transform: rotate(360deg) /* rtl:ignore */;
  }
}
.spinner-border {
  --bs-spinner-width: 2rem;
  --bs-spinner-height: 2rem;
  --bs-spinner-vertical-align: -0.125em;
  --bs-spinner-border-width: 0.25em;
  --bs-spinner-animation-speed: 0.75s;
  --bs-spinner-animation-name: spinner-border;
  border: var(--bs-spinner-border-width) solid currentcolor;
  border-right-color: transparent;
}

.spinner-border-sm {
  --bs-spinner-width: 1rem;
  --bs-spinner-height: 1rem;
  --bs-spinner-border-width: 0.2em;
}

@keyframes spinner-grow {
  0% {
    transform: scale(0);
  }
  50% {
    opacity: 1;
    transform: none;
  }
}
.spinner-grow {
  --bs-spinner-width: 2rem;
  --bs-spinner-height: 2rem;
  --bs-spinner-vertical-align: -0.125em;
  --bs-spinner-animation-speed: 0.75s;
  --bs-spinner-animation-name: spinner-grow;
  background-color: currentcolor;
  opacity: 0;
}

.spinner-grow-sm {
  --bs-spinner-width: 1rem;
  --bs-spinner-height: 1rem;
}

@media (prefers-reduced-motion: reduce) {
  .spinner-border,
  .spinner-grow {
    --bs-spinner-animation-speed: 1.5s;
  }
}
.offcanvas, .offcanvas-xxl, .offcanvas-xl, .offcanvas-lg, .offcanvas-md, .offcanvas-sm {
  --bs-offcanvas-zindex: 1045;
  --bs-offcanvas-width: 400px;
  --bs-offcanvas-height: 30vh;
  --bs-offcanvas-padding-x: 1rem;
  --bs-offcanvas-padding-y: 1rem;
  --bs-offcanvas-color: var(--bs-body-color);
  --bs-offcanvas-bg: var(--bs-body-bg);
  --bs-offcanvas-border-width: var(--bs-border-width);
  --bs-offcanvas-border-color: var(--bs-border-color-translucent);
  --bs-offcanvas-box-shadow: var(--bs-box-shadow-sm);
  --bs-offcanvas-transition: transform 0.3s ease-in-out;
  --bs-offcanvas-title-line-height: 1.5;
}

@media (max-width: 575.98px) {
  .offcanvas-sm {
    position: fixed;
    bottom: 0;
    z-index: var(--bs-offcanvas-zindex);
    display: flex;
    flex-direction: column;
    max-width: 100%;
    color: var(--bs-offcanvas-color);
    visibility: hidden;
    background-color: var(--bs-offcanvas-bg);
    background-clip: padding-box;
    outline: 0;
    box-shadow: var(--bs-offcanvas-box-shadow);
    transition: var(--bs-offcanvas-transition);
  }
}
@media (max-width: 575.98px) and (prefers-reduced-motion: reduce) {
  .offcanvas-sm {
    transition: none;
  }
}
@media (max-width: 575.98px) {
  .offcanvas-sm.offcanvas-start {
    top: 0;
    left: 0;
    width: var(--bs-offcanvas-width);
    border-right: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateX(-100%);
  }
  .offcanvas-sm.offcanvas-end {
    top: 0;
    right: 0;
    width: var(--bs-offcanvas-width);
    border-left: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateX(100%);
  }
  .offcanvas-sm.offcanvas-top {
    top: 0;
    right: 0;
    left: 0;
    height: var(--bs-offcanvas-height);
    max-height: 100%;
    border-bottom: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateY(-100%);
  }
  .offcanvas-sm.offcanvas-bottom {
    right: 0;
    left: 0;
    height: var(--bs-offcanvas-height);
    max-height: 100%;
    border-top: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateY(100%);
  }
  .offcanvas-sm.showing, .offcanvas-sm.show:not(.hiding) {
    transform: none;
  }
  .offcanvas-sm.showing, .offcanvas-sm.hiding, .offcanvas-sm.show {
    visibility: visible;
  }
}
@media (min-width: 576px) {
  .offcanvas-sm {
    --bs-offcanvas-height: auto;
    --bs-offcanvas-border-width: 0;
    background-color: transparent !important;
  }
  .offcanvas-sm .offcanvas-header {
    display: none;
  }
  .offcanvas-sm .offcanvas-body {
    display: flex;
    flex-grow: 0;
    padding: 0;
    overflow-y: visible;
    background-color: transparent !important;
  }
}

@media (max-width: 767.98px) {
  .offcanvas-md {
    position: fixed;
    bottom: 0;
    z-index: var(--bs-offcanvas-zindex);
    display: flex;
    flex-direction: column;
    max-width: 100%;
    color: var(--bs-offcanvas-color);
    visibility: hidden;
    background-color: var(--bs-offcanvas-bg);
    background-clip: padding-box;
    outline: 0;
    box-shadow: var(--bs-offcanvas-box-shadow);
    transition: var(--bs-offcanvas-transition);
  }
}
@media (max-width: 767.98px) and (prefers-reduced-motion: reduce) {
  .offcanvas-md {
    transition: none;
  }
}
@media (max-width: 767.98px) {
  .offcanvas-md.offcanvas-start {
    top: 0;
    left: 0;
    width: var(--bs-offcanvas-width);
    border-right: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateX(-100%);
  }
  .offcanvas-md.offcanvas-end {
    top: 0;
    right: 0;
    width: var(--bs-offcanvas-width);
    border-left: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateX(100%);
  }
  .offcanvas-md.offcanvas-top {
    top: 0;
    right: 0;
    left: 0;
    height: var(--bs-offcanvas-height);
    max-height: 100%;
    border-bottom: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateY(-100%);
  }
  .offcanvas-md.offcanvas-bottom {
    right: 0;
    left: 0;
    height: var(--bs-offcanvas-height);
    max-height: 100%;
    border-top: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateY(100%);
  }
  .offcanvas-md.showing, .offcanvas-md.show:not(.hiding) {
    transform: none;
  }
  .offcanvas-md.showing, .offcanvas-md.hiding, .offcanvas-md.show {
    visibility: visible;
  }
}
@media (min-width: 768px) {
  .offcanvas-md {
    --bs-offcanvas-height: auto;
    --bs-offcanvas-border-width: 0;
    background-color: transparent !important;
  }
  .offcanvas-md .offcanvas-header {
    display: none;
  }
  .offcanvas-md .offcanvas-body {
    display: flex;
    flex-grow: 0;
    padding: 0;
    overflow-y: visible;
    background-color: transparent !important;
  }
}

@media (max-width: 991.98px) {
  .offcanvas-lg {
    position: fixed;
    bottom: 0;
    z-index: var(--bs-offcanvas-zindex);
    display: flex;
    flex-direction: column;
    max-width: 100%;
    color: var(--bs-offcanvas-color);
    visibility: hidden;
    background-color: var(--bs-offcanvas-bg);
    background-clip: padding-box;
    outline: 0;
    box-shadow: var(--bs-offcanvas-box-shadow);
    transition: var(--bs-offcanvas-transition);
  }
}
@media (max-width: 991.98px) and (prefers-reduced-motion: reduce) {
  .offcanvas-lg {
    transition: none;
  }
}
@media (max-width: 991.98px) {
  .offcanvas-lg.offcanvas-start {
    top: 0;
    left: 0;
    width: var(--bs-offcanvas-width);
    border-right: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateX(-100%);
  }
  .offcanvas-lg.offcanvas-end {
    top: 0;
    right: 0;
    width: var(--bs-offcanvas-width);
    border-left: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateX(100%);
  }
  .offcanvas-lg.offcanvas-top {
    top: 0;
    right: 0;
    left: 0;
    height: var(--bs-offcanvas-height);
    max-height: 100%;
    border-bottom: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateY(-100%);
  }
  .offcanvas-lg.offcanvas-bottom {
    right: 0;
    left: 0;
    height: var(--bs-offcanvas-height);
    max-height: 100%;
    border-top: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateY(100%);
  }
  .offcanvas-lg.showing, .offcanvas-lg.show:not(.hiding) {
    transform: none;
  }
  .offcanvas-lg.showing, .offcanvas-lg.hiding, .offcanvas-lg.show {
    visibility: visible;
  }
}
@media (min-width: 992px) {
  .offcanvas-lg {
    --bs-offcanvas-height: auto;
    --bs-offcanvas-border-width: 0;
    background-color: transparent !important;
  }
  .offcanvas-lg .offcanvas-header {
    display: none;
  }
  .offcanvas-lg .offcanvas-body {
    display: flex;
    flex-grow: 0;
    padding: 0;
    overflow-y: visible;
    background-color: transparent !important;
  }
}

@media (max-width: 1199.98px) {
  .offcanvas-xl {
    position: fixed;
    bottom: 0;
    z-index: var(--bs-offcanvas-zindex);
    display: flex;
    flex-direction: column;
    max-width: 100%;
    color: var(--bs-offcanvas-color);
    visibility: hidden;
    background-color: var(--bs-offcanvas-bg);
    background-clip: padding-box;
    outline: 0;
    box-shadow: var(--bs-offcanvas-box-shadow);
    transition: var(--bs-offcanvas-transition);
  }
}
@media (max-width: 1199.98px) and (prefers-reduced-motion: reduce) {
  .offcanvas-xl {
    transition: none;
  }
}
@media (max-width: 1199.98px) {
  .offcanvas-xl.offcanvas-start {
    top: 0;
    left: 0;
    width: var(--bs-offcanvas-width);
    border-right: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateX(-100%);
  }
  .offcanvas-xl.offcanvas-end {
    top: 0;
    right: 0;
    width: var(--bs-offcanvas-width);
    border-left: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateX(100%);
  }
  .offcanvas-xl.offcanvas-top {
    top: 0;
    right: 0;
    left: 0;
    height: var(--bs-offcanvas-height);
    max-height: 100%;
    border-bottom: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateY(-100%);
  }
  .offcanvas-xl.offcanvas-bottom {
    right: 0;
    left: 0;
    height: var(--bs-offcanvas-height);
    max-height: 100%;
    border-top: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateY(100%);
  }
  .offcanvas-xl.showing, .offcanvas-xl.show:not(.hiding) {
    transform: none;
  }
  .offcanvas-xl.showing, .offcanvas-xl.hiding, .offcanvas-xl.show {
    visibility: visible;
  }
}
@media (min-width: 1200px) {
  .offcanvas-xl {
    --bs-offcanvas-height: auto;
    --bs-offcanvas-border-width: 0;
    background-color: transparent !important;
  }
  .offcanvas-xl .offcanvas-header {
    display: none;
  }
  .offcanvas-xl .offcanvas-body {
    display: flex;
    flex-grow: 0;
    padding: 0;
    overflow-y: visible;
    background-color: transparent !important;
  }
}

@media (max-width: 1399.98px) {
  .offcanvas-xxl {
    position: fixed;
    bottom: 0;
    z-index: var(--bs-offcanvas-zindex);
    display: flex;
    flex-direction: column;
    max-width: 100%;
    color: var(--bs-offcanvas-color);
    visibility: hidden;
    background-color: var(--bs-offcanvas-bg);
    background-clip: padding-box;
    outline: 0;
    box-shadow: var(--bs-offcanvas-box-shadow);
    transition: var(--bs-offcanvas-transition);
  }
}
@media (max-width: 1399.98px) and (prefers-reduced-motion: reduce) {
  .offcanvas-xxl {
    transition: none;
  }
}
@media (max-width: 1399.98px) {
  .offcanvas-xxl.offcanvas-start {
    top: 0;
    left: 0;
    width: var(--bs-offcanvas-width);
    border-right: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateX(-100%);
  }
  .offcanvas-xxl.offcanvas-end {
    top: 0;
    right: 0;
    width: var(--bs-offcanvas-width);
    border-left: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateX(100%);
  }
  .offcanvas-xxl.offcanvas-top {
    top: 0;
    right: 0;
    left: 0;
    height: var(--bs-offcanvas-height);
    max-height: 100%;
    border-bottom: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateY(-100%);
  }
  .offcanvas-xxl.offcanvas-bottom {
    right: 0;
    left: 0;
    height: var(--bs-offcanvas-height);
    max-height: 100%;
    border-top: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
    transform: translateY(100%);
  }
  .offcanvas-xxl.showing, .offcanvas-xxl.show:not(.hiding) {
    transform: none;
  }
  .offcanvas-xxl.showing, .offcanvas-xxl.hiding, .offcanvas-xxl.show {
    visibility: visible;
  }
}
@media (min-width: 1400px) {
  .offcanvas-xxl {
    --bs-offcanvas-height: auto;
    --bs-offcanvas-border-width: 0;
    background-color: transparent !important;
  }
  .offcanvas-xxl .offcanvas-header {
    display: none;
  }
  .offcanvas-xxl .offcanvas-body {
    display: flex;
    flex-grow: 0;
    padding: 0;
    overflow-y: visible;
    background-color: transparent !important;
  }
}

.offcanvas {
  position: fixed;
  bottom: 0;
  z-index: var(--bs-offcanvas-zindex);
  display: flex;
  flex-direction: column;
  max-width: 100%;
  color: var(--bs-offcanvas-color);
  visibility: hidden;
  background-color: var(--bs-offcanvas-bg);
  background-clip: padding-box;
  outline: 0;
  box-shadow: var(--bs-offcanvas-box-shadow);
  transition: var(--bs-offcanvas-transition);
}
@media (prefers-reduced-motion: reduce) {
  .offcanvas {
    transition: none;
  }
}
.offcanvas.offcanvas-start {
  top: 0;
  left: 0;
  width: var(--bs-offcanvas-width);
  border-right: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
  transform: translateX(-100%);
}
.offcanvas.offcanvas-end {
  top: 0;
  right: 0;
  width: var(--bs-offcanvas-width);
  border-left: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
  transform: translateX(100%);
}
.offcanvas.offcanvas-top {
  top: 0;
  right: 0;
  left: 0;
  height: var(--bs-offcanvas-height);
  max-height: 100%;
  border-bottom: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
  transform: translateY(-100%);
}
.offcanvas.offcanvas-bottom {
  right: 0;
  left: 0;
  height: var(--bs-offcanvas-height);
  max-height: 100%;
  border-top: var(--bs-offcanvas-border-width) solid var(--bs-offcanvas-border-color);
  transform: translateY(100%);
}
.offcanvas.showing, .offcanvas.show:not(.hiding) {
  transform: none;
}
.offcanvas.showing, .offcanvas.hiding, .offcanvas.show {
  visibility: visible;
}

.offcanvas-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 1040;
  width: 100vw;
  height: 100vh;
  background-color: #000;
}
.offcanvas-backdrop.fade {
  opacity: 0;
}
.offcanvas-backdrop.show {
  opacity: 0.5;
}

.offcanvas-header {
  display: flex;
  align-items: center;
  padding: var(--bs-offcanvas-padding-y) var(--bs-offcanvas-padding-x);
}
.offcanvas-header .btn-close {
  padding: calc(var(--bs-offcanvas-padding-y) * 0.5) calc(var(--bs-offcanvas-padding-x) * 0.5);
  margin-top: calc(-0.5 * var(--bs-offcanvas-padding-y));
  margin-right: calc(-0.5 * var(--bs-offcanvas-padding-x));
  margin-bottom: calc(-0.5 * var(--bs-offcanvas-padding-y));
  margin-left: auto;
}

.offcanvas-title {
  margin-bottom: 0;
  line-height: var(--bs-offcanvas-title-line-height);
}

.offcanvas-body {
  flex-grow: 1;
  padding: var(--bs-offcanvas-padding-y) var(--bs-offcanvas-padding-x);
  overflow-y: auto;
}

.placeholder {
  display: inline-block;
  min-height: 1em;
  vertical-align: middle;
  cursor: wait;
  background-color: currentcolor;
  opacity: 0.5;
}
.placeholder.btn::before {
  display: inline-block;
  content: "";
}

.placeholder-xs {
  min-height: 0.6em;
}

.placeholder-sm {
  min-height: 0.8em;
}

.placeholder-lg {
  min-height: 1.2em;
}

.placeholder-glow .placeholder {
  animation: placeholder-glow 2s ease-in-out infinite;
}

@keyframes placeholder-glow {
  50% {
    opacity: 0.2;
  }
}
.placeholder-wave {
  -webkit-mask-image: linear-gradient(130deg, #000 55%, rgba(0, 0, 0, 0.8) 75%, #000 95%);
  mask-image: linear-gradient(130deg, #000 55%, rgba(0, 0, 0, 0.8) 75%, #000 95%);
  -webkit-mask-size: 200% 100%;
  mask-size: 200% 100%;
  animation: placeholder-wave 2s linear infinite;
}

@keyframes placeholder-wave {
  100% {
    -webkit-mask-position: -200% 0%;
    mask-position: -200% 0%;
  }
}
.clearfix::after {
  display: block;
  clear: both;
  content: "";
}

.text-bg-primary {
  color: #fff !important;
  background-color: RGBA(var(--bs-primary-rgb), var(--bs-bg-opacity, 1)) !important;
}

.text-bg-secondary {
  color: #fff !important;
  background-color: RGBA(var(--bs-secondary-rgb), var(--bs-bg-opacity, 1)) !important;
}

.text-bg-success {
  color: #fff !important;
  background-color: RGBA(var(--bs-success-rgb), var(--bs-bg-opacity, 1)) !important;
}

.text-bg-info {
  color: #000 !important;
  background-color: RGBA(var(--bs-info-rgb), var(--bs-bg-opacity, 1)) !important;
}

.text-bg-warning {
  color: #000 !important;
  background-color: RGBA(var(--bs-warning-rgb), var(--bs-bg-opacity, 1)) !important;
}

.text-bg-danger {
  color: #fff !important;
  background-color: RGBA(var(--bs-danger-rgb), var(--bs-bg-opacity, 1)) !important;
}

.text-bg-light {
  color: #000 !important;
  background-color: RGBA(var(--bs-light-rgb), var(--bs-bg-opacity, 1)) !important;
}

.text-bg-dark {
  color: #fff !important;
  background-color: RGBA(var(--bs-dark-rgb), var(--bs-bg-opacity, 1)) !important;
}

.link-primary {
  color: RGBA(var(--bs-primary-rgb), var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(var(--bs-primary-rgb), var(--bs-link-underline-opacity, 1)) !important;
}
.link-primary:hover, .link-primary:focus {
  color: RGBA(10, 88, 202, var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(10, 88, 202, var(--bs-link-underline-opacity, 1)) !important;
}

.link-secondary {
  color: RGBA(var(--bs-secondary-rgb), var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(var(--bs-secondary-rgb), var(--bs-link-underline-opacity, 1)) !important;
}
.link-secondary:hover, .link-secondary:focus {
  color: RGBA(86, 94, 100, var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(86, 94, 100, var(--bs-link-underline-opacity, 1)) !important;
}

.link-success {
  color: RGBA(var(--bs-success-rgb), var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(var(--bs-success-rgb), var(--bs-link-underline-opacity, 1)) !important;
}
.link-success:hover, .link-success:focus {
  color: RGBA(20, 108, 67, var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(20, 108, 67, var(--bs-link-underline-opacity, 1)) !important;
}

.link-info {
  color: RGBA(var(--bs-info-rgb), var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(var(--bs-info-rgb), var(--bs-link-underline-opacity, 1)) !important;
}
.link-info:hover, .link-info:focus {
  color: RGBA(61, 213, 243, var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(61, 213, 243, var(--bs-link-underline-opacity, 1)) !important;
}

.link-warning {
  color: RGBA(var(--bs-warning-rgb), var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(var(--bs-warning-rgb), var(--bs-link-underline-opacity, 1)) !important;
}
.link-warning:hover, .link-warning:focus {
  color: RGBA(255, 205, 57, var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(255, 205, 57, var(--bs-link-underline-opacity, 1)) !important;
}

.link-danger {
  color: RGBA(var(--bs-danger-rgb), var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(var(--bs-danger-rgb), var(--bs-link-underline-opacity, 1)) !important;
}
.link-danger:hover, .link-danger:focus {
  color: RGBA(176, 42, 55, var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(176, 42, 55, var(--bs-link-underline-opacity, 1)) !important;
}

.link-light {
  color: RGBA(var(--bs-light-rgb), var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(var(--bs-light-rgb), var(--bs-link-underline-opacity, 1)) !important;
}
.link-light:hover, .link-light:focus {
  color: RGBA(249, 250, 251, var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(249, 250, 251, var(--bs-link-underline-opacity, 1)) !important;
}

.link-dark {
  color: RGBA(var(--bs-dark-rgb), var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(var(--bs-dark-rgb), var(--bs-link-underline-opacity, 1)) !important;
}
.link-dark:hover, .link-dark:focus {
  color: RGBA(26, 30, 33, var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(26, 30, 33, var(--bs-link-underline-opacity, 1)) !important;
}

.link-body-emphasis {
  color: RGBA(var(--bs-emphasis-color-rgb), var(--bs-link-opacity, 1)) !important;
  text-decoration-color: RGBA(var(--bs-emphasis-color-rgb), var(--bs-link-underline-opacity, 1)) !important;
}
.link-body-emphasis:hover, .link-body-emphasis:focus {
  color: RGBA(var(--bs-emphasis-color-rgb), var(--bs-link-opacity, 0.75)) !important;
  text-decoration-color: RGBA(var(--bs-emphasis-color-rgb), var(--bs-link-underline-opacity, 0.75)) !important;
}

.focus-ring:focus {
  outline: 0;
  box-shadow: var(--bs-focus-ring-x, 0) var(--bs-focus-ring-y, 0) var(--bs-focus-ring-blur, 0) var(--bs-focus-ring-width) var(--bs-focus-ring-color);
}

.icon-link {
  display: inline-flex;
  gap: 0.375rem;
  align-items: center;
  text-decoration-color: rgba(var(--bs-link-color-rgb), var(--bs-link-opacity, 0.5));
  text-underline-offset: 0.25em;
  backface-visibility: hidden;
}
.icon-link > .bi {
  flex-shrink: 0;
  width: 1em;
  height: 1em;
  fill: currentcolor;
  transition: 0.2s ease-in-out transform;
}
@media (prefers-reduced-motion: reduce) {
  .icon-link > .bi {
    transition: none;
  }
}

.icon-link-hover:hover > .bi, .icon-link-hover:focus-visible > .bi {
  transform: var(--bs-icon-link-transform, translate3d(0.25em, 0, 0));
}

.ratio {
  position: relative;
  width: 100%;
}
.ratio::before {
  display: block;
  padding-top: var(--bs-aspect-ratio);
  content: "";
}
.ratio > * {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.ratio-1x1 {
  --bs-aspect-ratio: 100%;
}

.ratio-4x3 {
  --bs-aspect-ratio: 75%;
}

.ratio-16x9 {
  --bs-aspect-ratio: 56.25%;
}

.ratio-21x9 {
  --bs-aspect-ratio: 42.8571428571%;
}

.fixed-top {
  position: fixed;
  top: 0;
  right: 0;
  left: 0;
  z-index: 1030;
}

.fixed-bottom {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 1030;
}

.sticky-top {
  position: sticky;
  top: 0;
  z-index: 1020;
}

.sticky-bottom {
  position: sticky;
  bottom: 0;
  z-index: 1020;
}

@media (min-width: 576px) {
  .sticky-sm-top {
    position: sticky;
    top: 0;
    z-index: 1020;
  }
  .sticky-sm-bottom {
    position: sticky;
    bottom: 0;
    z-index: 1020;
  }
}
@media (min-width: 768px) {
  .sticky-md-top {
    position: sticky;
    top: 0;
    z-index: 1020;
  }
  .sticky-md-bottom {
    position: sticky;
    bottom: 0;
    z-index: 1020;
  }
}
@media (min-width: 992px) {
  .sticky-lg-top {
    position: sticky;
    top: 0;
    z-index: 1020;
  }
  .sticky-lg-bottom {
    position: sticky;
    bottom: 0;
    z-index: 1020;
  }
}
@media (min-width: 1200px) {
  .sticky-xl-top {
    position: sticky;
    top: 0;
    z-index: 1020;
  }
  .sticky-xl-bottom {
    position: sticky;
    bottom: 0;
    z-index: 1020;
  }
}
@media (min-width: 1400px) {
  .sticky-xxl-top {
    position: sticky;
    top: 0;
    z-index: 1020;
  }
  .sticky-xxl-bottom {
    position: sticky;
    bottom: 0;
    z-index: 1020;
  }
}
.hstack {
  display: flex;
  flex-direction: row;
  align-items: center;
  align-self: stretch;
}

.vstack {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  align-self: stretch;
}

.visually-hidden,
.visually-hidden-focusable:not(:focus):not(:focus-within) {
  width: 1px !important;
  height: 1px !important;
  padding: 0 !important;
  margin: -1px !important;
  overflow: hidden !important;
  clip: rect(0, 0, 0, 0) !important;
  white-space: nowrap !important;
  border: 0 !important;
}
.visually-hidden:not(caption),
.visually-hidden-focusable:not(:focus):not(:focus-within):not(caption) {
  position: absolute !important;
}
.visually-hidden *,
.visually-hidden-focusable:not(:focus):not(:focus-within) * {
  overflow: hidden !important;
}

.stretched-link::after {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 1;
  content: "";
}

.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.vr {
  display: inline-block;
  align-self: stretch;
  width: var(--bs-border-width);
  min-height: 1em;
  background-color: currentcolor;
  opacity: 0.25;
}

.align-baseline {
  vertical-align: baseline !important;
}

.align-top {
  vertical-align: top !important;
}

.align-middle {
  vertical-align: middle !important;
}

.align-bottom {
  vertical-align: bottom !important;
}

.align-text-bottom {
  vertical-align: text-bottom !important;
}

.align-text-top {
  vertical-align: text-top !important;
}

.float-start {
  float: left !important;
}

.float-end {
  float: right !important;
}

.float-none {
  float: none !important;
}

.object-fit-contain {
  -o-object-fit: contain !important;
  object-fit: contain !important;
}

.object-fit-cover {
  -o-object-fit: cover !important;
  object-fit: cover !important;
}

.object-fit-fill {
  -o-object-fit: fill !important;
  object-fit: fill !important;
}

.object-fit-scale {
  -o-object-fit: scale-down !important;
  object-fit: scale-down !important;
}

.object-fit-none {
  -o-object-fit: none !important;
  object-fit: none !important;
}

.opacity-0 {
  opacity: 0 !important;
}

.opacity-25 {
  opacity: 0.25 !important;
}

.opacity-50 {
  opacity: 0.5 !important;
}

.opacity-75 {
  opacity: 0.75 !important;
}

.opacity-100 {
  opacity: 1 !important;
}

.overflow-auto {
  overflow: auto !important;
}

.overflow-hidden {
  overflow: hidden !important;
}

.overflow-visible {
  overflow: visible !important;
}

.overflow-scroll {
  overflow: scroll !important;
}

.overflow-x-auto {
  overflow-x: auto !important;
}

.overflow-x-hidden {
  overflow-x: hidden !important;
}

.overflow-x-visible {
  overflow-x: visible !important;
}

.overflow-x-scroll {
  overflow-x: scroll !important;
}

.overflow-y-auto {
  overflow-y: auto !important;
}

.overflow-y-hidden {
  overflow-y: hidden !important;
}

.overflow-y-visible {
  overflow-y: visible !important;
}

.overflow-y-scroll {
  overflow-y: scroll !important;
}

.d-inline {
  display: inline !important;
}

.d-inline-block {
  display: inline-block !important;
}

.d-block {
  display: block !important;
}

.d-grid {
  display: grid !important;
}

.d-inline-grid {
  display: inline-grid !important;
}

.d-table {
  display: table !important;
}

.d-table-row {
  display: table-row !important;
}

.d-table-cell {
  display: table-cell !important;
}

.d-flex {
  display: flex !important;
}

.d-inline-flex {
  display: inline-flex !important;
}

.d-none {
  display: none !important;
}

.shadow {
  box-shadow: var(--bs-box-shadow) !important;
}

.shadow-sm {
  box-shadow: var(--bs-box-shadow-sm) !important;
}

.shadow-lg {
  box-shadow: var(--bs-box-shadow-lg) !important;
}

.shadow-none {
  box-shadow: none !important;
}

.focus-ring-primary {
  --bs-focus-ring-color: rgba(var(--bs-primary-rgb), var(--bs-focus-ring-opacity));
}

.focus-ring-secondary {
  --bs-focus-ring-color: rgba(var(--bs-secondary-rgb), var(--bs-focus-ring-opacity));
}

.focus-ring-success {
  --bs-focus-ring-color: rgba(var(--bs-success-rgb), var(--bs-focus-ring-opacity));
}

.focus-ring-info {
  --bs-focus-ring-color: rgba(var(--bs-info-rgb), var(--bs-focus-ring-opacity));
}

.focus-ring-warning {
  --bs-focus-ring-color: rgba(var(--bs-warning-rgb), var(--bs-focus-ring-opacity));
}

.focus-ring-danger {
  --bs-focus-ring-color: rgba(var(--bs-danger-rgb), var(--bs-focus-ring-opacity));
}

.focus-ring-light {
  --bs-focus-ring-color: rgba(var(--bs-light-rgb), var(--bs-focus-ring-opacity));
}

.focus-ring-dark {
  --bs-focus-ring-color: rgba(var(--bs-dark-rgb), var(--bs-focus-ring-opacity));
}

.position-static {
  position: static !important;
}

.position-relative {
  position: relative !important;
}

.position-absolute {
  position: absolute !important;
}

.position-fixed {
  position: fixed !important;
}

.position-sticky {
  position: sticky !important;
}

.top-0 {
  top: 0 !important;
}

.top-50 {
  top: 50% !important;
}

.top-100 {
  top: 100% !important;
}

.bottom-0 {
  bottom: 0 !important;
}

.bottom-50 {
  bottom: 50% !important;
}

.bottom-100 {
  bottom: 100% !important;
}

.start-0 {
  left: 0 !important;
}

.start-50 {
  left: 50% !important;
}

.start-100 {
  left: 100% !important;
}

.end-0 {
  right: 0 !important;
}

.end-50 {
  right: 50% !important;
}

.end-100 {
  right: 100% !important;
}

.translate-middle {
  transform: translate(-50%, -50%) !important;
}

.translate-middle-x {
  transform: translateX(-50%) !important;
}

.translate-middle-y {
  transform: translateY(-50%) !important;
}

.border {
  border: var(--bs-border-width) var(--bs-border-style) var(--bs-border-color) !important;
}

.border-0 {
  border: 0 !important;
}

.border-top {
  border-top: var(--bs-border-width) var(--bs-border-style) var(--bs-border-color) !important;
}

.border-top-0 {
  border-top: 0 !important;
}

.border-end {
  border-right: var(--bs-border-width) var(--bs-border-style) var(--bs-border-color) !important;
}

.border-end-0 {
  border-right: 0 !important;
}

.border-bottom {
  border-bottom: var(--bs-border-width) var(--bs-border-style) var(--bs-border-color) !important;
}

.border-bottom-0 {
  border-bottom: 0 !important;
}

.border-start {
  border-left: var(--bs-border-width) var(--bs-border-style) var(--bs-border-color) !important;
}

.border-start-0 {
  border-left: 0 !important;
}

.border-primary {
  --bs-border-opacity: 1;
  border-color: rgba(var(--bs-primary-rgb), var(--bs-border-opacity)) !important;
}

.border-secondary {
  --bs-border-opacity: 1;
  border-color: rgba(var(--bs-secondary-rgb), var(--bs-border-opacity)) !important;
}

.border-success {
  --bs-border-opacity: 1;
  border-color: rgba(var(--bs-success-rgb), var(--bs-border-opacity)) !important;
}

.border-info {
  --bs-border-opacity: 1;
  border-color: rgba(var(--bs-info-rgb), var(--bs-border-opacity)) !important;
}

.border-warning {
  --bs-border-opacity: 1;
  border-color: rgba(var(--bs-warning-rgb), var(--bs-border-opacity)) !important;
}

.border-danger {
  --bs-border-opacity: 1;
  border-color: rgba(var(--bs-danger-rgb), var(--bs-border-opacity)) !important;
}

.border-light {
  --bs-border-opacity: 1;
  border-color: rgba(var(--bs-light-rgb), var(--bs-border-opacity)) !important;
}

.border-dark {
  --bs-border-opacity: 1;
  border-color: rgba(var(--bs-dark-rgb), var(--bs-border-opacity)) !important;
}

.border-black {
  --bs-border-opacity: 1;
  border-color: rgba(var(--bs-black-rgb), var(--bs-border-opacity)) !important;
}

.border-white {
  --bs-border-opacity: 1;
  border-color: rgba(var(--bs-white-rgb), var(--bs-border-opacity)) !important;
}

.border-primary-subtle {
  border-color: var(--bs-primary-border-subtle) !important;
}

.border-secondary-subtle {
  border-color: var(--bs-secondary-border-subtle) !important;
}

.border-success-subtle {
  border-color: var(--bs-success-border-subtle) !important;
}

.border-info-subtle {
  border-color: var(--bs-info-border-subtle) !important;
}

.border-warning-subtle {
  border-color: var(--bs-warning-border-subtle) !important;
}

.border-danger-subtle {
  border-color: var(--bs-danger-border-subtle) !important;
}

.border-light-subtle {
  border-color: var(--bs-light-border-subtle) !important;
}

.border-dark-subtle {
  border-color: var(--bs-dark-border-subtle) !important;
}

.border-1 {
  border-width: 1px !important;
}

.border-2 {
  border-width: 2px !important;
}

.border-3 {
  border-width: 3px !important;
}

.border-4 {
  border-width: 4px !important;
}

.border-5 {
  border-width: 5px !important;
}

.border-opacity-10 {
  --bs-border-opacity: 0.1;
}

.border-opacity-25 {
  --bs-border-opacity: 0.25;
}

.border-opacity-50 {
  --bs-border-opacity: 0.5;
}

.border-opacity-75 {
  --bs-border-opacity: 0.75;
}

.border-opacity-100 {
  --bs-border-opacity: 1;
}

.w-25 {
  width: 25% !important;
}

.w-50 {
  width: 50% !important;
}

.w-75 {
  width: 75% !important;
}

.w-100 {
  width: 100% !important;
}

.w-auto {
  width: auto !important;
}

.mw-100 {
  max-width: 100% !important;
}

.vw-100 {
  width: 100vw !important;
}

.min-vw-100 {
  min-width: 100vw !important;
}

.h-25 {
  height: 25% !important;
}

.h-50 {
  height: 50% !important;
}

.h-75 {
  height: 75% !important;
}

.h-100 {
  height: 100% !important;
}

.h-auto {
  height: auto !important;
}

.mh-100 {
  max-height: 100% !important;
}

.vh-100 {
  height: 100vh !important;
}

.min-vh-100 {
  min-height: 100vh !important;
}

.flex-fill {
  flex: 1 1 auto !important;
}

.flex-row {
  flex-direction: row !important;
}

.flex-column {
  flex-direction: column !important;
}

.flex-row-reverse {
  flex-direction: row-reverse !important;
}

.flex-column-reverse {
  flex-direction: column-reverse !important;
}

.flex-grow-0 {
  flex-grow: 0 !important;
}

.flex-grow-1 {
  flex-grow: 1 !important;
}

.flex-shrink-0 {
  flex-shrink: 0 !important;
}

.flex-shrink-1 {
  flex-shrink: 1 !important;
}

.flex-wrap {
  flex-wrap: wrap !important;
}

.flex-nowrap {
  flex-wrap: nowrap !important;
}

.flex-wrap-reverse {
  flex-wrap: wrap-reverse !important;
}

.justify-content-start {
  justify-content: flex-start !important;
}

.justify-content-end {
  justify-content: flex-end !important;
}

.justify-content-center {
  justify-content: center !important;
}

.justify-content-between {
  justify-content: space-between !important;
}

.justify-content-around {
  justify-content: space-around !important;
}

.justify-content-evenly {
  justify-content: space-evenly !important;
}

.align-items-start {
  align-items: flex-start !important;
}

.align-items-end {
  align-items: flex-end !important;
}

.align-items-center {
  align-items: center !important;
}

.align-items-baseline {
  align-items: baseline !important;
}

.align-items-stretch {
  align-items: stretch !important;
}

.align-content-start {
  align-content: flex-start !important;
}

.align-content-end {
  align-content: flex-end !important;
}

.align-content-center {
  align-content: center !important;
}

.align-content-between {
  align-content: space-between !important;
}

.align-content-around {
  align-content: space-around !important;
}

.align-content-stretch {
  align-content: stretch !important;
}

.align-self-auto {
  align-self: auto !important;
}

.align-self-start {
  align-self: flex-start !important;
}

.align-self-end {
  align-self: flex-end !important;
}

.align-self-center {
  align-self: center !important;
}

.align-self-baseline {
  align-self: baseline !important;
}

.align-self-stretch {
  align-self: stretch !important;
}

.order-first {
  order: -1 !important;
}

.order-0 {
  order: 0 !important;
}

.order-1 {
  order: 1 !important;
}

.order-2 {
  order: 2 !important;
}

.order-3 {
  order: 3 !important;
}

.order-4 {
  order: 4 !important;
}

.order-5 {
  order: 5 !important;
}

.order-last {
  order: 6 !important;
}

.m-0 {
  margin: 0 !important;
}

.m-1 {
  margin: 0.25rem !important;
}

.m-2 {
  margin: 0.5rem !important;
}

.m-3 {
  margin: 1rem !important;
}

.m-4 {
  margin: 1.5rem !important;
}

.m-5 {
  margin: 3rem !important;
}

.m-auto {
  margin: auto !important;
}

.mx-0 {
  margin-right: 0 !important;
  margin-left: 0 !important;
}

.mx-1 {
  margin-right: 0.25rem !important;
  margin-left: 0.25rem !important;
}

.mx-2 {
  margin-right: 0.5rem !important;
  margin-left: 0.5rem !important;
}

.mx-3 {
  margin-right: 1rem !important;
  margin-left: 1rem !important;
}

.mx-4 {
  margin-right: 1.5rem !important;
  margin-left: 1.5rem !important;
}

.mx-5 {
  margin-right: 3rem !important;
  margin-left: 3rem !important;
}

.mx-auto {
  margin-right: auto !important;
  margin-left: auto !important;
}

.my-0 {
  margin-top: 0 !important;
  margin-bottom: 0 !important;
}

.my-1 {
  margin-top: 0.25rem !important;
  margin-bottom: 0.25rem !important;
}

.my-2 {
  margin-top: 0.5rem !important;
  margin-bottom: 0.5rem !important;
}

.my-3 {
  margin-top: 1rem !important;
  margin-bottom: 1rem !important;
}

.my-4 {
  margin-top: 1.5rem !important;
  margin-bottom: 1.5rem !important;
}

.my-5 {
  margin-top: 3rem !important;
  margin-bottom: 3rem !important;
}

.my-auto {
  margin-top: auto !important;
  margin-bottom: auto !important;
}

.mt-0 {
  margin-top: 0 !important;
}

.mt-1 {
  margin-top: 0.25rem !important;
}

.mt-2 {
  margin-top: 0.5rem !important;
}

.mt-3 {
  margin-top: 1rem !important;
}

.mt-4 {
  margin-top: 1.5rem !important;
}

.mt-5 {
  margin-top: 3rem !important;
}

.mt-auto {
  margin-top: auto !important;
}

.me-0 {
  margin-right: 0 !important;
}

.me-1 {
  margin-right: 0.25rem !important;
}

.me-2 {
  margin-right: 0.5rem !important;
}

.me-3 {
  margin-right: 1rem !important;
}

.me-4 {
  margin-right: 1.5rem !important;
}

.me-5 {
  margin-right: 3rem !important;
}

.me-auto {
  margin-right: auto !important;
}

.mb-0 {
  margin-bottom: 0 !important;
}

.mb-1 {
  margin-bottom: 0.25rem !important;
}

.mb-2 {
  margin-bottom: 0.5rem !important;
}

.mb-3 {
  margin-bottom: 1rem !important;
}

.mb-4 {
  margin-bottom: 1.5rem !important;
}

.mb-5 {
  margin-bottom: 3rem !important;
}

.mb-auto {
  margin-bottom: auto !important;
}

.ms-0 {
  margin-left: 0 !important;
}

.ms-1 {
  margin-left: 0.25rem !important;
}

.ms-2 {
  margin-left: 0.5rem !important;
}

.ms-3 {
  margin-left: 1rem !important;
}

.ms-4 {
  margin-left: 1.5rem !important;
}

.ms-5 {
  margin-left: 3rem !important;
}

.ms-auto {
  margin-left: auto !important;
}

.m-n1 {
  margin: -0.25rem !important;
}

.m-n2 {
  margin: -0.5rem !important;
}

.m-n3 {
  margin: -1rem !important;
}

.m-n4 {
  margin: -1.5rem !important;
}

.m-n5 {
  margin: -3rem !important;
}

.mx-n1 {
  margin-right: -0.25rem !important;
  margin-left: -0.25rem !important;
}

.mx-n2 {
  margin-right: -0.5rem !important;
  margin-left: -0.5rem !important;
}

.mx-n3 {
  margin-right: -1rem !important;
  margin-left: -1rem !important;
}

.mx-n4 {
  margin-right: -1.5rem !important;
  margin-left: -1.5rem !important;
}

.mx-n5 {
  margin-right: -3rem !important;
  margin-left: -3rem !important;
}

.my-n1 {
  margin-top: -0.25rem !important;
  margin-bottom: -0.25rem !important;
}

.my-n2 {
  margin-top: -0.5rem !important;
  margin-bottom: -0.5rem !important;
}

.my-n3 {
  margin-top: -1rem !important;
  margin-bottom: -1rem !important;
}

.my-n4 {
  margin-top: -1.5rem !important;
  margin-bottom: -1.5rem !important;
}

.my-n5 {
  margin-top: -3rem !important;
  margin-bottom: -3rem !important;
}

.mt-n1 {
  margin-top: -0.25rem !important;
}

.mt-n2 {
  margin-top: -0.5rem !important;
}

.mt-n3 {
  margin-top: -1rem !important;
}

.mt-n4 {
  margin-top: -1.5rem !important;
}

.mt-n5 {
  margin-top: -3rem !important;
}

.me-n1 {
  margin-right: -0.25rem !important;
}

.me-n2 {
  margin-right: -0.5rem !important;
}

.me-n3 {
  margin-right: -1rem !important;
}

.me-n4 {
  margin-right: -1.5rem !important;
}

.me-n5 {
  margin-right: -3rem !important;
}

.mb-n1 {
  margin-bottom: -0.25rem !important;
}

.mb-n2 {
  margin-bottom: -0.5rem !important;
}

.mb-n3 {
  margin-bottom: -1rem !important;
}

.mb-n4 {
  margin-bottom: -1.5rem !important;
}

.mb-n5 {
  margin-bottom: -3rem !important;
}

.ms-n1 {
  margin-left: -0.25rem !important;
}

.ms-n2 {
  margin-left: -0.5rem !important;
}

.ms-n3 {
  margin-left: -1rem !important;
}

.ms-n4 {
  margin-left: -1.5rem !important;
}

.ms-n5 {
  margin-left: -3rem !important;
}

.p-0 {
  padding: 0 !important;
}

.p-1 {
  padding: 0.25rem !important;
}

.p-2 {
  padding: 0.5rem !important;
}

.p-3 {
  padding: 1rem !important;
}

.p-4 {
  padding: 1.5rem !important;
}

.p-5 {
  padding: 3rem !important;
}

.px-0 {
  padding-right: 0 !important;
  padding-left: 0 !important;
}

.px-1 {
  padding-right: 0.25rem !important;
  padding-left: 0.25rem !important;
}

.px-2 {
  padding-right: 0.5rem !important;
  padding-left: 0.5rem !important;
}

.px-3 {
  padding-right: 1rem !important;
  padding-left: 1rem !important;
}

.px-4 {
  padding-right: 1.5rem !important;
  padding-left: 1.5rem !important;
}

.px-5 {
  padding-right: 3rem !important;
  padding-left: 3rem !important;
}

.py-0 {
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}

.py-1 {
  padding-top: 0.25rem !important;
  padding-bottom: 0.25rem !important;
}

.py-2 {
  padding-top: 0.5rem !important;
  padding-bottom: 0.5rem !important;
}

.py-3 {
  padding-top: 1rem !important;
  padding-bottom: 1rem !important;
}

.py-4 {
  padding-top: 1.5rem !important;
  padding-bottom: 1.5rem !important;
}

.py-5 {
  padding-top: 3rem !important;
  padding-bottom: 3rem !important;
}

.pt-0 {
  padding-top: 0 !important;
}

.pt-1 {
  padding-top: 0.25rem !important;
}

.pt-2 {
  padding-top: 0.5rem !important;
}

.pt-3 {
  padding-top: 1rem !important;
}

.pt-4 {
  padding-top: 1.5rem !important;
}

.pt-5 {
  padding-top: 3rem !important;
}

.pe-0 {
  padding-right: 0 !important;
}

.pe-1 {
  padding-right: 0.25rem !important;
}

.pe-2 {
  padding-right: 0.5rem !important;
}

.pe-3 {
  padding-right: 1rem !important;
}

.pe-4 {
  padding-right: 1.5rem !important;
}

.pe-5 {
  padding-right: 3rem !important;
}

.pb-0 {
  padding-bottom: 0 !important;
}

.pb-1 {
  padding-bottom: 0.25rem !important;
}

.pb-2 {
  padding-bottom: 0.5rem !important;
}

.pb-3 {
  padding-bottom: 1rem !important;
}

.pb-4 {
  padding-bottom: 1.5rem !important;
}

.pb-5 {
  padding-bottom: 3rem !important;
}

.ps-0 {
  padding-left: 0 !important;
}

.ps-1 {
  padding-left: 0.25rem !important;
}

.ps-2 {
  padding-left: 0.5rem !important;
}

.ps-3 {
  padding-left: 1rem !important;
}

.ps-4 {
  padding-left: 1.5rem !important;
}

.ps-5 {
  padding-left: 3rem !important;
}

.gap-0 {
  gap: 0 !important;
}

.gap-1 {
  gap: 0.25rem !important;
}

.gap-2 {
  gap: 0.5rem !important;
}

.gap-3 {
  gap: 1rem !important;
}

.gap-4 {
  gap: 1.5rem !important;
}

.gap-5 {
  gap: 3rem !important;
}

.row-gap-0 {
  row-gap: 0 !important;
}

.row-gap-1 {
  row-gap: 0.25rem !important;
}

.row-gap-2 {
  row-gap: 0.5rem !important;
}

.row-gap-3 {
  row-gap: 1rem !important;
}

.row-gap-4 {
  row-gap: 1.5rem !important;
}

.row-gap-5 {
  row-gap: 3rem !important;
}

.column-gap-0 {
  -moz-column-gap: 0 !important;
  column-gap: 0 !important;
}

.column-gap-1 {
  -moz-column-gap: 0.25rem !important;
  column-gap: 0.25rem !important;
}

.column-gap-2 {
  -moz-column-gap: 0.5rem !important;
  column-gap: 0.5rem !important;
}

.column-gap-3 {
  -moz-column-gap: 1rem !important;
  column-gap: 1rem !important;
}

.column-gap-4 {
  -moz-column-gap: 1.5rem !important;
  column-gap: 1.5rem !important;
}

.column-gap-5 {
  -moz-column-gap: 3rem !important;
  column-gap: 3rem !important;
}

.font-monospace {
  font-family: var(--bs-font-monospace) !important;
}

.fs-1 {
  font-size: calc(1.375rem + 1.5vw) !important;
}

.fs-2 {
  font-size: calc(1.325rem + 0.9vw) !important;
}

.fs-3 {
  font-size: calc(1.3rem + 0.6vw) !important;
}

.fs-4 {
  font-size: calc(1.275rem + 0.3vw) !important;
}

.fs-5 {
  font-size: 1.25rem !important;
}

.fs-6 {
  font-size: 1rem !important;
}

.fs-7 {
  font-size: 0.875rem !important;
}

.fs-8 {
  font-size: 0.75rem !important;
}

.fst-italic {
  font-style: italic !important;
}

.fst-normal {
  font-style: normal !important;
}

.fw-lighter {
  font-weight: lighter !important;
}

.fw-light {
  font-weight: 300 !important;
}

.fw-normal {
  font-weight: 400 !important;
}

.fw-medium {
  font-weight: 500 !important;
}

.fw-semibold {
  font-weight: 600 !important;
}

.fw-bold {
  font-weight: 700 !important;
}

.fw-bolder {
  font-weight: bolder !important;
}

.lh-1 {
  line-height: 1 !important;
}

.lh-sm {
  line-height: 1.25 !important;
}

.lh-base {
  line-height: 1.5 !important;
}

.lh-lg {
  line-height: 2 !important;
}

.text-start {
  text-align: left !important;
}

.text-end {
  text-align: right !important;
}

.text-center {
  text-align: center !important;
}

.text-decoration-none {
  text-decoration: none !important;
}

.text-decoration-underline {
  text-decoration: underline !important;
}

.text-decoration-line-through {
  text-decoration: line-through !important;
}

.text-lowercase {
  text-transform: lowercase !important;
}

.text-uppercase {
  text-transform: uppercase !important;
}

.text-capitalize {
  text-transform: capitalize !important;
}

.text-wrap {
  white-space: normal !important;
}

.text-nowrap {
  white-space: nowrap !important;
}

/* rtl:begin:remove */
.text-break {
  word-wrap: break-word !important;
  word-break: break-word !important;
}

/* rtl:end:remove */
.text-primary {
  --bs-text-opacity: 1;
  color: rgba(var(--bs-primary-rgb), var(--bs-text-opacity)) !important;
}

.text-secondary {
  --bs-text-opacity: 1;
  color: rgba(var(--bs-secondary-rgb), var(--bs-text-opacity)) !important;
}

.text-success {
  --bs-text-opacity: 1;
  color: rgba(var(--bs-success-rgb), var(--bs-text-opacity)) !important;
}

.text-info {
  --bs-text-opacity: 1;
  color: rgba(var(--bs-info-rgb), var(--bs-text-opacity)) !important;
}

.text-warning {
  --bs-text-opacity: 1;
  color: rgba(var(--bs-warning-rgb), var(--bs-text-opacity)) !important;
}

.text-danger {
  --bs-text-opacity: 1;
  color: rgba(var(--bs-danger-rgb), var(--bs-text-opacity)) !important;
}

.text-light {
  --bs-text-opacity: 1;
  color: rgba(var(--bs-light-rgb), var(--bs-text-opacity)) !important;
}

.text-dark {
  --bs-text-opacity: 1;
  color: rgba(var(--bs-dark-rgb), var(--bs-text-opacity)) !important;
}

.text-black {
  --bs-text-opacity: 1;
  color: rgba(var(--bs-black-rgb), var(--bs-text-opacity)) !important;
}

.text-white {
  --bs-text-opacity: 1;
  color: rgba(var(--bs-white-rgb), var(--bs-text-opacity)) !important;
}

.text-body {
  --bs-text-opacity: 1;
  color: rgba(var(--bs-body-color-rgb), var(--bs-text-opacity)) !important;
}

.text-muted {
  --bs-text-opacity: 1;
  color: var(--bs-secondary-color) !important;
}

.text-black-50 {
  --bs-text-opacity: 1;
  color: rgba(0, 0, 0, 0.5) !important;
}

.text-white-50 {
  --bs-text-opacity: 1;
  color: rgba(255, 255, 255, 0.5) !important;
}

.text-body-secondary {
  --bs-text-opacity: 1;
  color: var(--bs-secondary-color) !important;
}

.text-body-tertiary {
  --bs-text-opacity: 1;
  color: var(--bs-tertiary-color) !important;
}

.text-body-emphasis {
  --bs-text-opacity: 1;
  color: var(--bs-emphasis-color) !important;
}

.text-reset {
  --bs-text-opacity: 1;
  color: inherit !important;
}

.text-opacity-25 {
  --bs-text-opacity: 0.25;
}

.text-opacity-50 {
  --bs-text-opacity: 0.5;
}

.text-opacity-75 {
  --bs-text-opacity: 0.75;
}

.text-opacity-100 {
  --bs-text-opacity: 1;
}

.text-primary-emphasis {
  color: var(--bs-primary-text-emphasis) !important;
}

.text-secondary-emphasis {
  color: var(--bs-secondary-text-emphasis) !important;
}

.text-success-emphasis {
  color: var(--bs-success-text-emphasis) !important;
}

.text-info-emphasis {
  color: var(--bs-info-text-emphasis) !important;
}

.text-warning-emphasis {
  color: var(--bs-warning-text-emphasis) !important;
}

.text-danger-emphasis {
  color: var(--bs-danger-text-emphasis) !important;
}

.text-light-emphasis {
  color: var(--bs-light-text-emphasis) !important;
}

.text-dark-emphasis {
  color: var(--bs-dark-text-emphasis) !important;
}

.link-opacity-10 {
  --bs-link-opacity: 0.1;
}

.link-opacity-10-hover:hover {
  --bs-link-opacity: 0.1;
}

.link-opacity-25 {
  --bs-link-opacity: 0.25;
}

.link-opacity-25-hover:hover {
  --bs-link-opacity: 0.25;
}

.link-opacity-50 {
  --bs-link-opacity: 0.5;
}

.link-opacity-50-hover:hover {
  --bs-link-opacity: 0.5;
}

.link-opacity-75 {
  --bs-link-opacity: 0.75;
}

.link-opacity-75-hover:hover {
  --bs-link-opacity: 0.75;
}

.link-opacity-100 {
  --bs-link-opacity: 1;
}

.link-opacity-100-hover:hover {
  --bs-link-opacity: 1;
}

.link-offset-1 {
  text-underline-offset: 0.125em !important;
}

.link-offset-1-hover:hover {
  text-underline-offset: 0.125em !important;
}

.link-offset-2 {
  text-underline-offset: 0.25em !important;
}

.link-offset-2-hover:hover {
  text-underline-offset: 0.25em !important;
}

.link-offset-3 {
  text-underline-offset: 0.375em !important;
}

.link-offset-3-hover:hover {
  text-underline-offset: 0.375em !important;
}

.link-underline-primary {
  --bs-link-underline-opacity: 1;
  text-decoration-color: rgba(var(--bs-primary-rgb), var(--bs-link-underline-opacity)) !important;
}

.link-underline-secondary {
  --bs-link-underline-opacity: 1;
  text-decoration-color: rgba(var(--bs-secondary-rgb), var(--bs-link-underline-opacity)) !important;
}

.link-underline-success {
  --bs-link-underline-opacity: 1;
  text-decoration-color: rgba(var(--bs-success-rgb), var(--bs-link-underline-opacity)) !important;
}

.link-underline-info {
  --bs-link-underline-opacity: 1;
  text-decoration-color: rgba(var(--bs-info-rgb), var(--bs-link-underline-opacity)) !important;
}

.link-underline-warning {
  --bs-link-underline-opacity: 1;
  text-decoration-color: rgba(var(--bs-warning-rgb), var(--bs-link-underline-opacity)) !important;
}

.link-underline-danger {
  --bs-link-underline-opacity: 1;
  text-decoration-color: rgba(var(--bs-danger-rgb), var(--bs-link-underline-opacity)) !important;
}

.link-underline-light {
  --bs-link-underline-opacity: 1;
  text-decoration-color: rgba(var(--bs-light-rgb), var(--bs-link-underline-opacity)) !important;
}

.link-underline-dark {
  --bs-link-underline-opacity: 1;
  text-decoration-color: rgba(var(--bs-dark-rgb), var(--bs-link-underline-opacity)) !important;
}

.link-underline {
  --bs-link-underline-opacity: 1;
  text-decoration-color: rgba(var(--bs-link-color-rgb), var(--bs-link-underline-opacity, 1)) !important;
}

.link-underline-opacity-0 {
  --bs-link-underline-opacity: 0;
}

.link-underline-opacity-0-hover:hover {
  --bs-link-underline-opacity: 0;
}

.link-underline-opacity-10 {
  --bs-link-underline-opacity: 0.1;
}

.link-underline-opacity-10-hover:hover {
  --bs-link-underline-opacity: 0.1;
}

.link-underline-opacity-25 {
  --bs-link-underline-opacity: 0.25;
}

.link-underline-opacity-25-hover:hover {
  --bs-link-underline-opacity: 0.25;
}

.link-underline-opacity-50 {
  --bs-link-underline-opacity: 0.5;
}

.link-underline-opacity-50-hover:hover {
  --bs-link-underline-opacity: 0.5;
}

.link-underline-opacity-75 {
  --bs-link-underline-opacity: 0.75;
}

.link-underline-opacity-75-hover:hover {
  --bs-link-underline-opacity: 0.75;
}

.link-underline-opacity-100 {
  --bs-link-underline-opacity: 1;
}

.link-underline-opacity-100-hover:hover {
  --bs-link-underline-opacity: 1;
}

.bg-primary {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-primary-rgb), var(--bs-bg-opacity)) !important;
}

.bg-secondary {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-secondary-rgb), var(--bs-bg-opacity)) !important;
}

.bg-success {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-success-rgb), var(--bs-bg-opacity)) !important;
}

.bg-info {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-info-rgb), var(--bs-bg-opacity)) !important;
}

.bg-warning {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-warning-rgb), var(--bs-bg-opacity)) !important;
}

.bg-danger {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-danger-rgb), var(--bs-bg-opacity)) !important;
}

.bg-light {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-light-rgb), var(--bs-bg-opacity)) !important;
}

.bg-dark {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-dark-rgb), var(--bs-bg-opacity)) !important;
}

.bg-black {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-black-rgb), var(--bs-bg-opacity)) !important;
}

.bg-white {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-white-rgb), var(--bs-bg-opacity)) !important;
}

.bg-body {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-body-bg-rgb), var(--bs-bg-opacity)) !important;
}

.bg-transparent {
  --bs-bg-opacity: 1;
  background-color: transparent !important;
}

.bg-body-secondary {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-secondary-bg-rgb), var(--bs-bg-opacity)) !important;
}

.bg-body-tertiary {
  --bs-bg-opacity: 1;
  background-color: rgba(var(--bs-tertiary-bg-rgb), var(--bs-bg-opacity)) !important;
}

.bg-opacity-10 {
  --bs-bg-opacity: 0.1;
}

.bg-opacity-25 {
  --bs-bg-opacity: 0.25;
}

.bg-opacity-50 {
  --bs-bg-opacity: 0.5;
}

.bg-opacity-75 {
  --bs-bg-opacity: 0.75;
}

.bg-opacity-100 {
  --bs-bg-opacity: 1;
}

.bg-primary-subtle {
  background-color: var(--bs-primary-bg-subtle) !important;
}

.bg-secondary-subtle {
  background-color: var(--bs-secondary-bg-subtle) !important;
}

.bg-success-subtle {
  background-color: var(--bs-success-bg-subtle) !important;
}

.bg-info-subtle {
  background-color: var(--bs-info-bg-subtle) !important;
}

.bg-warning-subtle {
  background-color: var(--bs-warning-bg-subtle) !important;
}

.bg-danger-subtle {
  background-color: var(--bs-danger-bg-subtle) !important;
}

.bg-light-subtle {
  background-color: var(--bs-light-bg-subtle) !important;
}

.bg-dark-subtle {
  background-color: var(--bs-dark-bg-subtle) !important;
}

.bg-gradient {
  background-image: var(--bs-gradient) !important;
}

.user-select-all {
  -webkit-user-select: all !important;
  -moz-user-select: all !important;
  user-select: all !important;
}

.user-select-auto {
  -webkit-user-select: auto !important;
  -moz-user-select: auto !important;
  user-select: auto !important;
}

.user-select-none {
  -webkit-user-select: none !important;
  -moz-user-select: none !important;
  user-select: none !important;
}

.pe-none {
  pointer-events: none !important;
}

.pe-auto {
  pointer-events: auto !important;
}

.rounded {
  border-radius: var(--bs-border-radius) !important;
}

.rounded-0 {
  border-radius: 0 !important;
}

.rounded-1 {
  border-radius: var(--bs-border-radius-sm) !important;
}

.rounded-2 {
  border-radius: var(--bs-border-radius) !important;
}

.rounded-3 {
  border-radius: var(--bs-border-radius-lg) !important;
}

.rounded-4 {
  border-radius: var(--bs-border-radius-xl) !important;
}

.rounded-5 {
  border-radius: var(--bs-border-radius-xxl) !important;
}

.rounded-circle {
  border-radius: 50% !important;
}

.rounded-pill {
  border-radius: var(--bs-border-radius-pill) !important;
}

.rounded-top {
  border-top-left-radius: var(--bs-border-radius) !important;
  border-top-right-radius: var(--bs-border-radius) !important;
}

.rounded-top-0 {
  border-top-left-radius: 0 !important;
  border-top-right-radius: 0 !important;
}

.rounded-top-1 {
  border-top-left-radius: var(--bs-border-radius-sm) !important;
  border-top-right-radius: var(--bs-border-radius-sm) !important;
}

.rounded-top-2 {
  border-top-left-radius: var(--bs-border-radius) !important;
  border-top-right-radius: var(--bs-border-radius) !important;
}

.rounded-top-3 {
  border-top-left-radius: var(--bs-border-radius-lg) !important;
  border-top-right-radius: var(--bs-border-radius-lg) !important;
}

.rounded-top-4 {
  border-top-left-radius: var(--bs-border-radius-xl) !important;
  border-top-right-radius: var(--bs-border-radius-xl) !important;
}

.rounded-top-5 {
  border-top-left-radius: var(--bs-border-radius-xxl) !important;
  border-top-right-radius: var(--bs-border-radius-xxl) !important;
}

.rounded-top-circle {
  border-top-left-radius: 50% !important;
  border-top-right-radius: 50% !important;
}

.rounded-top-pill {
  border-top-left-radius: var(--bs-border-radius-pill) !important;
  border-top-right-radius: var(--bs-border-radius-pill) !important;
}

.rounded-end {
  border-top-right-radius: var(--bs-border-radius) !important;
  border-bottom-right-radius: var(--bs-border-radius) !important;
}

.rounded-end-0 {
  border-top-right-radius: 0 !important;
  border-bottom-right-radius: 0 !important;
}

.rounded-end-1 {
  border-top-right-radius: var(--bs-border-radius-sm) !important;
  border-bottom-right-radius: var(--bs-border-radius-sm) !important;
}

.rounded-end-2 {
  border-top-right-radius: var(--bs-border-radius) !important;
  border-bottom-right-radius: var(--bs-border-radius) !important;
}

.rounded-end-3 {
  border-top-right-radius: var(--bs-border-radius-lg) !important;
  border-bottom-right-radius: var(--bs-border-radius-lg) !important;
}

.rounded-end-4 {
  border-top-right-radius: var(--bs-border-radius-xl) !important;
  border-bottom-right-radius: var(--bs-border-radius-xl) !important;
}

.rounded-end-5 {
  border-top-right-radius: var(--bs-border-radius-xxl) !important;
  border-bottom-right-radius: var(--bs-border-radius-xxl) !important;
}

.rounded-end-circle {
  border-top-right-radius: 50% !important;
  border-bottom-right-radius: 50% !important;
}

.rounded-end-pill {
  border-top-right-radius: var(--bs-border-radius-pill) !important;
  border-bottom-right-radius: var(--bs-border-radius-pill) !important;
}

.rounded-bottom {
  border-bottom-right-radius: var(--bs-border-radius) !important;
  border-bottom-left-radius: var(--bs-border-radius) !important;
}

.rounded-bottom-0 {
  border-bottom-right-radius: 0 !important;
  border-bottom-left-radius: 0 !important;
}

.rounded-bottom-1 {
  border-bottom-right-radius: var(--bs-border-radius-sm) !important;
  border-bottom-left-radius: var(--bs-border-radius-sm) !important;
}

.rounded-bottom-2 {
  border-bottom-right-radius: var(--bs-border-radius) !important;
  border-bottom-left-radius: var(--bs-border-radius) !important;
}

.rounded-bottom-3 {
  border-bottom-right-radius: var(--bs-border-radius-lg) !important;
  border-bottom-left-radius: var(--bs-border-radius-lg) !important;
}

.rounded-bottom-4 {
  border-bottom-right-radius: var(--bs-border-radius-xl) !important;
  border-bottom-left-radius: var(--bs-border-radius-xl) !important;
}

.rounded-bottom-5 {
  border-bottom-right-radius: var(--bs-border-radius-xxl) !important;
  border-bottom-left-radius: var(--bs-border-radius-xxl) !important;
}

.rounded-bottom-circle {
  border-bottom-right-radius: 50% !important;
  border-bottom-left-radius: 50% !important;
}

.rounded-bottom-pill {
  border-bottom-right-radius: var(--bs-border-radius-pill) !important;
  border-bottom-left-radius: var(--bs-border-radius-pill) !important;
}

.rounded-start {
  border-bottom-left-radius: var(--bs-border-radius) !important;
  border-top-left-radius: var(--bs-border-radius) !important;
}

.rounded-start-0 {
  border-bottom-left-radius: 0 !important;
  border-top-left-radius: 0 !important;
}

.rounded-start-1 {
  border-bottom-left-radius: var(--bs-border-radius-sm) !important;
  border-top-left-radius: var(--bs-border-radius-sm) !important;
}

.rounded-start-2 {
  border-bottom-left-radius: var(--bs-border-radius) !important;
  border-top-left-radius: var(--bs-border-radius) !important;
}

.rounded-start-3 {
  border-bottom-left-radius: var(--bs-border-radius-lg) !important;
  border-top-left-radius: var(--bs-border-radius-lg) !important;
}

.rounded-start-4 {
  border-bottom-left-radius: var(--bs-border-radius-xl) !important;
  border-top-left-radius: var(--bs-border-radius-xl) !important;
}

.rounded-start-5 {
  border-bottom-left-radius: var(--bs-border-radius-xxl) !important;
  border-top-left-radius: var(--bs-border-radius-xxl) !important;
}

.rounded-start-circle {
  border-bottom-left-radius: 50% !important;
  border-top-left-radius: 50% !important;
}

.rounded-start-pill {
  border-bottom-left-radius: var(--bs-border-radius-pill) !important;
  border-top-left-radius: var(--bs-border-radius-pill) !important;
}

.visible {
  visibility: visible !important;
}

.invisible {
  visibility: hidden !important;
}

.z-n1 {
  z-index: -1 !important;
}

.z-0 {
  z-index: 0 !important;
}

.z-1 {
  z-index: 1 !important;
}

.z-2 {
  z-index: 2 !important;
}

.z-3 {
  z-index: 3 !important;
}

@media (min-width: 576px) {
  .float-sm-start {
    float: left !important;
  }
  .float-sm-end {
    float: right !important;
  }
  .float-sm-none {
    float: none !important;
  }
  .object-fit-sm-contain {
    -o-object-fit: contain !important;
    object-fit: contain !important;
  }
  .object-fit-sm-cover {
    -o-object-fit: cover !important;
    object-fit: cover !important;
  }
  .object-fit-sm-fill {
    -o-object-fit: fill !important;
    object-fit: fill !important;
  }
  .object-fit-sm-scale {
    -o-object-fit: scale-down !important;
    object-fit: scale-down !important;
  }
  .object-fit-sm-none {
    -o-object-fit: none !important;
    object-fit: none !important;
  }
  .d-sm-inline {
    display: inline !important;
  }
  .d-sm-inline-block {
    display: inline-block !important;
  }
  .d-sm-block {
    display: block !important;
  }
  .d-sm-grid {
    display: grid !important;
  }
  .d-sm-inline-grid {
    display: inline-grid !important;
  }
  .d-sm-table {
    display: table !important;
  }
  .d-sm-table-row {
    display: table-row !important;
  }
  .d-sm-table-cell {
    display: table-cell !important;
  }
  .d-sm-flex {
    display: flex !important;
  }
  .d-sm-inline-flex {
    display: inline-flex !important;
  }
  .d-sm-none {
    display: none !important;
  }
  .flex-sm-fill {
    flex: 1 1 auto !important;
  }
  .flex-sm-row {
    flex-direction: row !important;
  }
  .flex-sm-column {
    flex-direction: column !important;
  }
  .flex-sm-row-reverse {
    flex-direction: row-reverse !important;
  }
  .flex-sm-column-reverse {
    flex-direction: column-reverse !important;
  }
  .flex-sm-grow-0 {
    flex-grow: 0 !important;
  }
  .flex-sm-grow-1 {
    flex-grow: 1 !important;
  }
  .flex-sm-shrink-0 {
    flex-shrink: 0 !important;
  }
  .flex-sm-shrink-1 {
    flex-shrink: 1 !important;
  }
  .flex-sm-wrap {
    flex-wrap: wrap !important;
  }
  .flex-sm-nowrap {
    flex-wrap: nowrap !important;
  }
  .flex-sm-wrap-reverse {
    flex-wrap: wrap-reverse !important;
  }
  .justify-content-sm-start {
    justify-content: flex-start !important;
  }
  .justify-content-sm-end {
    justify-content: flex-end !important;
  }
  .justify-content-sm-center {
    justify-content: center !important;
  }
  .justify-content-sm-between {
    justify-content: space-between !important;
  }
  .justify-content-sm-around {
    justify-content: space-around !important;
  }
  .justify-content-sm-evenly {
    justify-content: space-evenly !important;
  }
  .align-items-sm-start {
    align-items: flex-start !important;
  }
  .align-items-sm-end {
    align-items: flex-end !important;
  }
  .align-items-sm-center {
    align-items: center !important;
  }
  .align-items-sm-baseline {
    align-items: baseline !important;
  }
  .align-items-sm-stretch {
    align-items: stretch !important;
  }
  .align-content-sm-start {
    align-content: flex-start !important;
  }
  .align-content-sm-end {
    align-content: flex-end !important;
  }
  .align-content-sm-center {
    align-content: center !important;
  }
  .align-content-sm-between {
    align-content: space-between !important;
  }
  .align-content-sm-around {
    align-content: space-around !important;
  }
  .align-content-sm-stretch {
    align-content: stretch !important;
  }
  .align-self-sm-auto {
    align-self: auto !important;
  }
  .align-self-sm-start {
    align-self: flex-start !important;
  }
  .align-self-sm-end {
    align-self: flex-end !important;
  }
  .align-self-sm-center {
    align-self: center !important;
  }
  .align-self-sm-baseline {
    align-self: baseline !important;
  }
  .align-self-sm-stretch {
    align-self: stretch !important;
  }
  .order-sm-first {
    order: -1 !important;
  }
  .order-sm-0 {
    order: 0 !important;
  }
  .order-sm-1 {
    order: 1 !important;
  }
  .order-sm-2 {
    order: 2 !important;
  }
  .order-sm-3 {
    order: 3 !important;
  }
  .order-sm-4 {
    order: 4 !important;
  }
  .order-sm-5 {
    order: 5 !important;
  }
  .order-sm-last {
    order: 6 !important;
  }
  .m-sm-0 {
    margin: 0 !important;
  }
  .m-sm-1 {
    margin: 0.25rem !important;
  }
  .m-sm-2 {
    margin: 0.5rem !important;
  }
  .m-sm-3 {
    margin: 1rem !important;
  }
  .m-sm-4 {
    margin: 1.5rem !important;
  }
  .m-sm-5 {
    margin: 3rem !important;
  }
  .m-sm-auto {
    margin: auto !important;
  }
  .mx-sm-0 {
    margin-right: 0 !important;
    margin-left: 0 !important;
  }
  .mx-sm-1 {
    margin-right: 0.25rem !important;
    margin-left: 0.25rem !important;
  }
  .mx-sm-2 {
    margin-right: 0.5rem !important;
    margin-left: 0.5rem !important;
  }
  .mx-sm-3 {
    margin-right: 1rem !important;
    margin-left: 1rem !important;
  }
  .mx-sm-4 {
    margin-right: 1.5rem !important;
    margin-left: 1.5rem !important;
  }
  .mx-sm-5 {
    margin-right: 3rem !important;
    margin-left: 3rem !important;
  }
  .mx-sm-auto {
    margin-right: auto !important;
    margin-left: auto !important;
  }
  .my-sm-0 {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
  }
  .my-sm-1 {
    margin-top: 0.25rem !important;
    margin-bottom: 0.25rem !important;
  }
  .my-sm-2 {
    margin-top: 0.5rem !important;
    margin-bottom: 0.5rem !important;
  }
  .my-sm-3 {
    margin-top: 1rem !important;
    margin-bottom: 1rem !important;
  }
  .my-sm-4 {
    margin-top: 1.5rem !important;
    margin-bottom: 1.5rem !important;
  }
  .my-sm-5 {
    margin-top: 3rem !important;
    margin-bottom: 3rem !important;
  }
  .my-sm-auto {
    margin-top: auto !important;
    margin-bottom: auto !important;
  }
  .mt-sm-0 {
    margin-top: 0 !important;
  }
  .mt-sm-1 {
    margin-top: 0.25rem !important;
  }
  .mt-sm-2 {
    margin-top: 0.5rem !important;
  }
  .mt-sm-3 {
    margin-top: 1rem !important;
  }
  .mt-sm-4 {
    margin-top: 1.5rem !important;
  }
  .mt-sm-5 {
    margin-top: 3rem !important;
  }
  .mt-sm-auto {
    margin-top: auto !important;
  }
  .me-sm-0 {
    margin-right: 0 !important;
  }
  .me-sm-1 {
    margin-right: 0.25rem !important;
  }
  .me-sm-2 {
    margin-right: 0.5rem !important;
  }
  .me-sm-3 {
    margin-right: 1rem !important;
  }
  .me-sm-4 {
    margin-right: 1.5rem !important;
  }
  .me-sm-5 {
    margin-right: 3rem !important;
  }
  .me-sm-auto {
    margin-right: auto !important;
  }
  .mb-sm-0 {
    margin-bottom: 0 !important;
  }
  .mb-sm-1 {
    margin-bottom: 0.25rem !important;
  }
  .mb-sm-2 {
    margin-bottom: 0.5rem !important;
  }
  .mb-sm-3 {
    margin-bottom: 1rem !important;
  }
  .mb-sm-4 {
    margin-bottom: 1.5rem !important;
  }
  .mb-sm-5 {
    margin-bottom: 3rem !important;
  }
  .mb-sm-auto {
    margin-bottom: auto !important;
  }
  .ms-sm-0 {
    margin-left: 0 !important;
  }
  .ms-sm-1 {
    margin-left: 0.25rem !important;
  }
  .ms-sm-2 {
    margin-left: 0.5rem !important;
  }
  .ms-sm-3 {
    margin-left: 1rem !important;
  }
  .ms-sm-4 {
    margin-left: 1.5rem !important;
  }
  .ms-sm-5 {
    margin-left: 3rem !important;
  }
  .ms-sm-auto {
    margin-left: auto !important;
  }
  .m-sm-n1 {
    margin: -0.25rem !important;
  }
  .m-sm-n2 {
    margin: -0.5rem !important;
  }
  .m-sm-n3 {
    margin: -1rem !important;
  }
  .m-sm-n4 {
    margin: -1.5rem !important;
  }
  .m-sm-n5 {
    margin: -3rem !important;
  }
  .mx-sm-n1 {
    margin-right: -0.25rem !important;
    margin-left: -0.25rem !important;
  }
  .mx-sm-n2 {
    margin-right: -0.5rem !important;
    margin-left: -0.5rem !important;
  }
  .mx-sm-n3 {
    margin-right: -1rem !important;
    margin-left: -1rem !important;
  }
  .mx-sm-n4 {
    margin-right: -1.5rem !important;
    margin-left: -1.5rem !important;
  }
  .mx-sm-n5 {
    margin-right: -3rem !important;
    margin-left: -3rem !important;
  }
  .my-sm-n1 {
    margin-top: -0.25rem !important;
    margin-bottom: -0.25rem !important;
  }
  .my-sm-n2 {
    margin-top: -0.5rem !important;
    margin-bottom: -0.5rem !important;
  }
  .my-sm-n3 {
    margin-top: -1rem !important;
    margin-bottom: -1rem !important;
  }
  .my-sm-n4 {
    margin-top: -1.5rem !important;
    margin-bottom: -1.5rem !important;
  }
  .my-sm-n5 {
    margin-top: -3rem !important;
    margin-bottom: -3rem !important;
  }
  .mt-sm-n1 {
    margin-top: -0.25rem !important;
  }
  .mt-sm-n2 {
    margin-top: -0.5rem !important;
  }
  .mt-sm-n3 {
    margin-top: -1rem !important;
  }
  .mt-sm-n4 {
    margin-top: -1.5rem !important;
  }
  .mt-sm-n5 {
    margin-top: -3rem !important;
  }
  .me-sm-n1 {
    margin-right: -0.25rem !important;
  }
  .me-sm-n2 {
    margin-right: -0.5rem !important;
  }
  .me-sm-n3 {
    margin-right: -1rem !important;
  }
  .me-sm-n4 {
    margin-right: -1.5rem !important;
  }
  .me-sm-n5 {
    margin-right: -3rem !important;
  }
  .mb-sm-n1 {
    margin-bottom: -0.25rem !important;
  }
  .mb-sm-n2 {
    margin-bottom: -0.5rem !important;
  }
  .mb-sm-n3 {
    margin-bottom: -1rem !important;
  }
  .mb-sm-n4 {
    margin-bottom: -1.5rem !important;
  }
  .mb-sm-n5 {
    margin-bottom: -3rem !important;
  }
  .ms-sm-n1 {
    margin-left: -0.25rem !important;
  }
  .ms-sm-n2 {
    margin-left: -0.5rem !important;
  }
  .ms-sm-n3 {
    margin-left: -1rem !important;
  }
  .ms-sm-n4 {
    margin-left: -1.5rem !important;
  }
  .ms-sm-n5 {
    margin-left: -3rem !important;
  }
  .p-sm-0 {
    padding: 0 !important;
  }
  .p-sm-1 {
    padding: 0.25rem !important;
  }
  .p-sm-2 {
    padding: 0.5rem !important;
  }
  .p-sm-3 {
    padding: 1rem !important;
  }
  .p-sm-4 {
    padding: 1.5rem !important;
  }
  .p-sm-5 {
    padding: 3rem !important;
  }
  .px-sm-0 {
    padding-right: 0 !important;
    padding-left: 0 !important;
  }
  .px-sm-1 {
    padding-right: 0.25rem !important;
    padding-left: 0.25rem !important;
  }
  .px-sm-2 {
    padding-right: 0.5rem !important;
    padding-left: 0.5rem !important;
  }
  .px-sm-3 {
    padding-right: 1rem !important;
    padding-left: 1rem !important;
  }
  .px-sm-4 {
    padding-right: 1.5rem !important;
    padding-left: 1.5rem !important;
  }
  .px-sm-5 {
    padding-right: 3rem !important;
    padding-left: 3rem !important;
  }
  .py-sm-0 {
    padding-top: 0 !important;
    padding-bottom: 0 !important;
  }
  .py-sm-1 {
    padding-top: 0.25rem !important;
    padding-bottom: 0.25rem !important;
  }
  .py-sm-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
  }
  .py-sm-3 {
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
  }
  .py-sm-4 {
    padding-top: 1.5rem !important;
    padding-bottom: 1.5rem !important;
  }
  .py-sm-5 {
    padding-top: 3rem !important;
    padding-bottom: 3rem !important;
  }
  .pt-sm-0 {
    padding-top: 0 !important;
  }
  .pt-sm-1 {
    padding-top: 0.25rem !important;
  }
  .pt-sm-2 {
    padding-top: 0.5rem !important;
  }
  .pt-sm-3 {
    padding-top: 1rem !important;
  }
  .pt-sm-4 {
    padding-top: 1.5rem !important;
  }
  .pt-sm-5 {
    padding-top: 3rem !important;
  }
  .pe-sm-0 {
    padding-right: 0 !important;
  }
  .pe-sm-1 {
    padding-right: 0.25rem !important;
  }
  .pe-sm-2 {
    padding-right: 0.5rem !important;
  }
  .pe-sm-3 {
    padding-right: 1rem !important;
  }
  .pe-sm-4 {
    padding-right: 1.5rem !important;
  }
  .pe-sm-5 {
    padding-right: 3rem !important;
  }
  .pb-sm-0 {
    padding-bottom: 0 !important;
  }
  .pb-sm-1 {
    padding-bottom: 0.25rem !important;
  }
  .pb-sm-2 {
    padding-bottom: 0.5rem !important;
  }
  .pb-sm-3 {
    padding-bottom: 1rem !important;
  }
  .pb-sm-4 {
    padding-bottom: 1.5rem !important;
  }
  .pb-sm-5 {
    padding-bottom: 3rem !important;
  }
  .ps-sm-0 {
    padding-left: 0 !important;
  }
  .ps-sm-1 {
    padding-left: 0.25rem !important;
  }
  .ps-sm-2 {
    padding-left: 0.5rem !important;
  }
  .ps-sm-3 {
    padding-left: 1rem !important;
  }
  .ps-sm-4 {
    padding-left: 1.5rem !important;
  }
  .ps-sm-5 {
    padding-left: 3rem !important;
  }
  .gap-sm-0 {
    gap: 0 !important;
  }
  .gap-sm-1 {
    gap: 0.25rem !important;
  }
  .gap-sm-2 {
    gap: 0.5rem !important;
  }
  .gap-sm-3 {
    gap: 1rem !important;
  }
  .gap-sm-4 {
    gap: 1.5rem !important;
  }
  .gap-sm-5 {
    gap: 3rem !important;
  }
  .row-gap-sm-0 {
    row-gap: 0 !important;
  }
  .row-gap-sm-1 {
    row-gap: 0.25rem !important;
  }
  .row-gap-sm-2 {
    row-gap: 0.5rem !important;
  }
  .row-gap-sm-3 {
    row-gap: 1rem !important;
  }
  .row-gap-sm-4 {
    row-gap: 1.5rem !important;
  }
  .row-gap-sm-5 {
    row-gap: 3rem !important;
  }
  .column-gap-sm-0 {
    -moz-column-gap: 0 !important;
    column-gap: 0 !important;
  }
  .column-gap-sm-1 {
    -moz-column-gap: 0.25rem !important;
    column-gap: 0.25rem !important;
  }
  .column-gap-sm-2 {
    -moz-column-gap: 0.5rem !important;
    column-gap: 0.5rem !important;
  }
  .column-gap-sm-3 {
    -moz-column-gap: 1rem !important;
    column-gap: 1rem !important;
  }
  .column-gap-sm-4 {
    -moz-column-gap: 1.5rem !important;
    column-gap: 1.5rem !important;
  }
  .column-gap-sm-5 {
    -moz-column-gap: 3rem !important;
    column-gap: 3rem !important;
  }
  .text-sm-start {
    text-align: left !important;
  }
  .text-sm-end {
    text-align: right !important;
  }
  .text-sm-center {
    text-align: center !important;
  }
}
@media (min-width: 768px) {
  .float-md-start {
    float: left !important;
  }
  .float-md-end {
    float: right !important;
  }
  .float-md-none {
    float: none !important;
  }
  .object-fit-md-contain {
    -o-object-fit: contain !important;
    object-fit: contain !important;
  }
  .object-fit-md-cover {
    -o-object-fit: cover !important;
    object-fit: cover !important;
  }
  .object-fit-md-fill {
    -o-object-fit: fill !important;
    object-fit: fill !important;
  }
  .object-fit-md-scale {
    -o-object-fit: scale-down !important;
    object-fit: scale-down !important;
  }
  .object-fit-md-none {
    -o-object-fit: none !important;
    object-fit: none !important;
  }
  .d-md-inline {
    display: inline !important;
  }
  .d-md-inline-block {
    display: inline-block !important;
  }
  .d-md-block {
    display: block !important;
  }
  .d-md-grid {
    display: grid !important;
  }
  .d-md-inline-grid {
    display: inline-grid !important;
  }
  .d-md-table {
    display: table !important;
  }
  .d-md-table-row {
    display: table-row !important;
  }
  .d-md-table-cell {
    display: table-cell !important;
  }
  .d-md-flex {
    display: flex !important;
  }
  .d-md-inline-flex {
    display: inline-flex !important;
  }
  .d-md-none {
    display: none !important;
  }
  .flex-md-fill {
    flex: 1 1 auto !important;
  }
  .flex-md-row {
    flex-direction: row !important;
  }
  .flex-md-column {
    flex-direction: column !important;
  }
  .flex-md-row-reverse {
    flex-direction: row-reverse !important;
  }
  .flex-md-column-reverse {
    flex-direction: column-reverse !important;
  }
  .flex-md-grow-0 {
    flex-grow: 0 !important;
  }
  .flex-md-grow-1 {
    flex-grow: 1 !important;
  }
  .flex-md-shrink-0 {
    flex-shrink: 0 !important;
  }
  .flex-md-shrink-1 {
    flex-shrink: 1 !important;
  }
  .flex-md-wrap {
    flex-wrap: wrap !important;
  }
  .flex-md-nowrap {
    flex-wrap: nowrap !important;
  }
  .flex-md-wrap-reverse {
    flex-wrap: wrap-reverse !important;
  }
  .justify-content-md-start {
    justify-content: flex-start !important;
  }
  .justify-content-md-end {
    justify-content: flex-end !important;
  }
  .justify-content-md-center {
    justify-content: center !important;
  }
  .justify-content-md-between {
    justify-content: space-between !important;
  }
  .justify-content-md-around {
    justify-content: space-around !important;
  }
  .justify-content-md-evenly {
    justify-content: space-evenly !important;
  }
  .align-items-md-start {
    align-items: flex-start !important;
  }
  .align-items-md-end {
    align-items: flex-end !important;
  }
  .align-items-md-center {
    align-items: center !important;
  }
  .align-items-md-baseline {
    align-items: baseline !important;
  }
  .align-items-md-stretch {
    align-items: stretch !important;
  }
  .align-content-md-start {
    align-content: flex-start !important;
  }
  .align-content-md-end {
    align-content: flex-end !important;
  }
  .align-content-md-center {
    align-content: center !important;
  }
  .align-content-md-between {
    align-content: space-between !important;
  }
  .align-content-md-around {
    align-content: space-around !important;
  }
  .align-content-md-stretch {
    align-content: stretch !important;
  }
  .align-self-md-auto {
    align-self: auto !important;
  }
  .align-self-md-start {
    align-self: flex-start !important;
  }
  .align-self-md-end {
    align-self: flex-end !important;
  }
  .align-self-md-center {
    align-self: center !important;
  }
  .align-self-md-baseline {
    align-self: baseline !important;
  }
  .align-self-md-stretch {
    align-self: stretch !important;
  }
  .order-md-first {
    order: -1 !important;
  }
  .order-md-0 {
    order: 0 !important;
  }
  .order-md-1 {
    order: 1 !important;
  }
  .order-md-2 {
    order: 2 !important;
  }
  .order-md-3 {
    order: 3 !important;
  }
  .order-md-4 {
    order: 4 !important;
  }
  .order-md-5 {
    order: 5 !important;
  }
  .order-md-last {
    order: 6 !important;
  }
  .m-md-0 {
    margin: 0 !important;
  }
  .m-md-1 {
    margin: 0.25rem !important;
  }
  .m-md-2 {
    margin: 0.5rem !important;
  }
  .m-md-3 {
    margin: 1rem !important;
  }
  .m-md-4 {
    margin: 1.5rem !important;
  }
  .m-md-5 {
    margin: 3rem !important;
  }
  .m-md-auto {
    margin: auto !important;
  }
  .mx-md-0 {
    margin-right: 0 !important;
    margin-left: 0 !important;
  }
  .mx-md-1 {
    margin-right: 0.25rem !important;
    margin-left: 0.25rem !important;
  }
  .mx-md-2 {
    margin-right: 0.5rem !important;
    margin-left: 0.5rem !important;
  }
  .mx-md-3 {
    margin-right: 1rem !important;
    margin-left: 1rem !important;
  }
  .mx-md-4 {
    margin-right: 1.5rem !important;
    margin-left: 1.5rem !important;
  }
  .mx-md-5 {
    margin-right: 3rem !important;
    margin-left: 3rem !important;
  }
  .mx-md-auto {
    margin-right: auto !important;
    margin-left: auto !important;
  }
  .my-md-0 {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
  }
  .my-md-1 {
    margin-top: 0.25rem !important;
    margin-bottom: 0.25rem !important;
  }
  .my-md-2 {
    margin-top: 0.5rem !important;
    margin-bottom: 0.5rem !important;
  }
  .my-md-3 {
    margin-top: 1rem !important;
    margin-bottom: 1rem !important;
  }
  .my-md-4 {
    margin-top: 1.5rem !important;
    margin-bottom: 1.5rem !important;
  }
  .my-md-5 {
    margin-top: 3rem !important;
    margin-bottom: 3rem !important;
  }
  .my-md-auto {
    margin-top: auto !important;
    margin-bottom: auto !important;
  }
  .mt-md-0 {
    margin-top: 0 !important;
  }
  .mt-md-1 {
    margin-top: 0.25rem !important;
  }
  .mt-md-2 {
    margin-top: 0.5rem !important;
  }
  .mt-md-3 {
    margin-top: 1rem !important;
  }
  .mt-md-4 {
    margin-top: 1.5rem !important;
  }
  .mt-md-5 {
    margin-top: 3rem !important;
  }
  .mt-md-auto {
    margin-top: auto !important;
  }
  .me-md-0 {
    margin-right: 0 !important;
  }
  .me-md-1 {
    margin-right: 0.25rem !important;
  }
  .me-md-2 {
    margin-right: 0.5rem !important;
  }
  .me-md-3 {
    margin-right: 1rem !important;
  }
  .me-md-4 {
    margin-right: 1.5rem !important;
  }
  .me-md-5 {
    margin-right: 3rem !important;
  }
  .me-md-auto {
    margin-right: auto !important;
  }
  .mb-md-0 {
    margin-bottom: 0 !important;
  }
  .mb-md-1 {
    margin-bottom: 0.25rem !important;
  }
  .mb-md-2 {
    margin-bottom: 0.5rem !important;
  }
  .mb-md-3 {
    margin-bottom: 1rem !important;
  }
  .mb-md-4 {
    margin-bottom: 1.5rem !important;
  }
  .mb-md-5 {
    margin-bottom: 3rem !important;
  }
  .mb-md-auto {
    margin-bottom: auto !important;
  }
  .ms-md-0 {
    margin-left: 0 !important;
  }
  .ms-md-1 {
    margin-left: 0.25rem !important;
  }
  .ms-md-2 {
    margin-left: 0.5rem !important;
  }
  .ms-md-3 {
    margin-left: 1rem !important;
  }
  .ms-md-4 {
    margin-left: 1.5rem !important;
  }
  .ms-md-5 {
    margin-left: 3rem !important;
  }
  .ms-md-auto {
    margin-left: auto !important;
  }
  .m-md-n1 {
    margin: -0.25rem !important;
  }
  .m-md-n2 {
    margin: -0.5rem !important;
  }
  .m-md-n3 {
    margin: -1rem !important;
  }
  .m-md-n4 {
    margin: -1.5rem !important;
  }
  .m-md-n5 {
    margin: -3rem !important;
  }
  .mx-md-n1 {
    margin-right: -0.25rem !important;
    margin-left: -0.25rem !important;
  }
  .mx-md-n2 {
    margin-right: -0.5rem !important;
    margin-left: -0.5rem !important;
  }
  .mx-md-n3 {
    margin-right: -1rem !important;
    margin-left: -1rem !important;
  }
  .mx-md-n4 {
    margin-right: -1.5rem !important;
    margin-left: -1.5rem !important;
  }
  .mx-md-n5 {
    margin-right: -3rem !important;
    margin-left: -3rem !important;
  }
  .my-md-n1 {
    margin-top: -0.25rem !important;
    margin-bottom: -0.25rem !important;
  }
  .my-md-n2 {
    margin-top: -0.5rem !important;
    margin-bottom: -0.5rem !important;
  }
  .my-md-n3 {
    margin-top: -1rem !important;
    margin-bottom: -1rem !important;
  }
  .my-md-n4 {
    margin-top: -1.5rem !important;
    margin-bottom: -1.5rem !important;
  }
  .my-md-n5 {
    margin-top: -3rem !important;
    margin-bottom: -3rem !important;
  }
  .mt-md-n1 {
    margin-top: -0.25rem !important;
  }
  .mt-md-n2 {
    margin-top: -0.5rem !important;
  }
  .mt-md-n3 {
    margin-top: -1rem !important;
  }
  .mt-md-n4 {
    margin-top: -1.5rem !important;
  }
  .mt-md-n5 {
    margin-top: -3rem !important;
  }
  .me-md-n1 {
    margin-right: -0.25rem !important;
  }
  .me-md-n2 {
    margin-right: -0.5rem !important;
  }
  .me-md-n3 {
    margin-right: -1rem !important;
  }
  .me-md-n4 {
    margin-right: -1.5rem !important;
  }
  .me-md-n5 {
    margin-right: -3rem !important;
  }
  .mb-md-n1 {
    margin-bottom: -0.25rem !important;
  }
  .mb-md-n2 {
    margin-bottom: -0.5rem !important;
  }
  .mb-md-n3 {
    margin-bottom: -1rem !important;
  }
  .mb-md-n4 {
    margin-bottom: -1.5rem !important;
  }
  .mb-md-n5 {
    margin-bottom: -3rem !important;
  }
  .ms-md-n1 {
    margin-left: -0.25rem !important;
  }
  .ms-md-n2 {
    margin-left: -0.5rem !important;
  }
  .ms-md-n3 {
    margin-left: -1rem !important;
  }
  .ms-md-n4 {
    margin-left: -1.5rem !important;
  }
  .ms-md-n5 {
    margin-left: -3rem !important;
  }
  .p-md-0 {
    padding: 0 !important;
  }
  .p-md-1 {
    padding: 0.25rem !important;
  }
  .p-md-2 {
    padding: 0.5rem !important;
  }
  .p-md-3 {
    padding: 1rem !important;
  }
  .p-md-4 {
    padding: 1.5rem !important;
  }
  .p-md-5 {
    padding: 3rem !important;
  }
  .px-md-0 {
    padding-right: 0 !important;
    padding-left: 0 !important;
  }
  .px-md-1 {
    padding-right: 0.25rem !important;
    padding-left: 0.25rem !important;
  }
  .px-md-2 {
    padding-right: 0.5rem !important;
    padding-left: 0.5rem !important;
  }
  .px-md-3 {
    padding-right: 1rem !important;
    padding-left: 1rem !important;
  }
  .px-md-4 {
    padding-right: 1.5rem !important;
    padding-left: 1.5rem !important;
  }
  .px-md-5 {
    padding-right: 3rem !important;
    padding-left: 3rem !important;
  }
  .py-md-0 {
    padding-top: 0 !important;
    padding-bottom: 0 !important;
  }
  .py-md-1 {
    padding-top: 0.25rem !important;
    padding-bottom: 0.25rem !important;
  }
  .py-md-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
  }
  .py-md-3 {
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
  }
  .py-md-4 {
    padding-top: 1.5rem !important;
    padding-bottom: 1.5rem !important;
  }
  .py-md-5 {
    padding-top: 3rem !important;
    padding-bottom: 3rem !important;
  }
  .pt-md-0 {
    padding-top: 0 !important;
  }
  .pt-md-1 {
    padding-top: 0.25rem !important;
  }
  .pt-md-2 {
    padding-top: 0.5rem !important;
  }
  .pt-md-3 {
    padding-top: 1rem !important;
  }
  .pt-md-4 {
    padding-top: 1.5rem !important;
  }
  .pt-md-5 {
    padding-top: 3rem !important;
  }
  .pe-md-0 {
    padding-right: 0 !important;
  }
  .pe-md-1 {
    padding-right: 0.25rem !important;
  }
  .pe-md-2 {
    padding-right: 0.5rem !important;
  }
  .pe-md-3 {
    padding-right: 1rem !important;
  }
  .pe-md-4 {
    padding-right: 1.5rem !important;
  }
  .pe-md-5 {
    padding-right: 3rem !important;
  }
  .pb-md-0 {
    padding-bottom: 0 !important;
  }
  .pb-md-1 {
    padding-bottom: 0.25rem !important;
  }
  .pb-md-2 {
    padding-bottom: 0.5rem !important;
  }
  .pb-md-3 {
    padding-bottom: 1rem !important;
  }
  .pb-md-4 {
    padding-bottom: 1.5rem !important;
  }
  .pb-md-5 {
    padding-bottom: 3rem !important;
  }
  .ps-md-0 {
    padding-left: 0 !important;
  }
  .ps-md-1 {
    padding-left: 0.25rem !important;
  }
  .ps-md-2 {
    padding-left: 0.5rem !important;
  }
  .ps-md-3 {
    padding-left: 1rem !important;
  }
  .ps-md-4 {
    padding-left: 1.5rem !important;
  }
  .ps-md-5 {
    padding-left: 3rem !important;
  }
  .gap-md-0 {
    gap: 0 !important;
  }
  .gap-md-1 {
    gap: 0.25rem !important;
  }
  .gap-md-2 {
    gap: 0.5rem !important;
  }
  .gap-md-3 {
    gap: 1rem !important;
  }
  .gap-md-4 {
    gap: 1.5rem !important;
  }
  .gap-md-5 {
    gap: 3rem !important;
  }
  .row-gap-md-0 {
    row-gap: 0 !important;
  }
  .row-gap-md-1 {
    row-gap: 0.25rem !important;
  }
  .row-gap-md-2 {
    row-gap: 0.5rem !important;
  }
  .row-gap-md-3 {
    row-gap: 1rem !important;
  }
  .row-gap-md-4 {
    row-gap: 1.5rem !important;
  }
  .row-gap-md-5 {
    row-gap: 3rem !important;
  }
  .column-gap-md-0 {
    -moz-column-gap: 0 !important;
    column-gap: 0 !important;
  }
  .column-gap-md-1 {
    -moz-column-gap: 0.25rem !important;
    column-gap: 0.25rem !important;
  }
  .column-gap-md-2 {
    -moz-column-gap: 0.5rem !important;
    column-gap: 0.5rem !important;
  }
  .column-gap-md-3 {
    -moz-column-gap: 1rem !important;
    column-gap: 1rem !important;
  }
  .column-gap-md-4 {
    -moz-column-gap: 1.5rem !important;
    column-gap: 1.5rem !important;
  }
  .column-gap-md-5 {
    -moz-column-gap: 3rem !important;
    column-gap: 3rem !important;
  }
  .text-md-start {
    text-align: left !important;
  }
  .text-md-end {
    text-align: right !important;
  }
  .text-md-center {
    text-align: center !important;
  }
}
@media (min-width: 992px) {
  .float-lg-start {
    float: left !important;
  }
  .float-lg-end {
    float: right !important;
  }
  .float-lg-none {
    float: none !important;
  }
  .object-fit-lg-contain {
    -o-object-fit: contain !important;
    object-fit: contain !important;
  }
  .object-fit-lg-cover {
    -o-object-fit: cover !important;
    object-fit: cover !important;
  }
  .object-fit-lg-fill {
    -o-object-fit: fill !important;
    object-fit: fill !important;
  }
  .object-fit-lg-scale {
    -o-object-fit: scale-down !important;
    object-fit: scale-down !important;
  }
  .object-fit-lg-none {
    -o-object-fit: none !important;
    object-fit: none !important;
  }
  .d-lg-inline {
    display: inline !important;
  }
  .d-lg-inline-block {
    display: inline-block !important;
  }
  .d-lg-block {
    display: block !important;
  }
  .d-lg-grid {
    display: grid !important;
  }
  .d-lg-inline-grid {
    display: inline-grid !important;
  }
  .d-lg-table {
    display: table !important;
  }
  .d-lg-table-row {
    display: table-row !important;
  }
  .d-lg-table-cell {
    display: table-cell !important;
  }
  .d-lg-flex {
    display: flex !important;
  }
  .d-lg-inline-flex {
    display: inline-flex !important;
  }
  .d-lg-none {
    display: none !important;
  }
  .flex-lg-fill {
    flex: 1 1 auto !important;
  }
  .flex-lg-row {
    flex-direction: row !important;
  }
  .flex-lg-column {
    flex-direction: column !important;
  }
  .flex-lg-row-reverse {
    flex-direction: row-reverse !important;
  }
  .flex-lg-column-reverse {
    flex-direction: column-reverse !important;
  }
  .flex-lg-grow-0 {
    flex-grow: 0 !important;
  }
  .flex-lg-grow-1 {
    flex-grow: 1 !important;
  }
  .flex-lg-shrink-0 {
    flex-shrink: 0 !important;
  }
  .flex-lg-shrink-1 {
    flex-shrink: 1 !important;
  }
  .flex-lg-wrap {
    flex-wrap: wrap !important;
  }
  .flex-lg-nowrap {
    flex-wrap: nowrap !important;
  }
  .flex-lg-wrap-reverse {
    flex-wrap: wrap-reverse !important;
  }
  .justify-content-lg-start {
    justify-content: flex-start !important;
  }
  .justify-content-lg-end {
    justify-content: flex-end !important;
  }
  .justify-content-lg-center {
    justify-content: center !important;
  }
  .justify-content-lg-between {
    justify-content: space-between !important;
  }
  .justify-content-lg-around {
    justify-content: space-around !important;
  }
  .justify-content-lg-evenly {
    justify-content: space-evenly !important;
  }
  .align-items-lg-start {
    align-items: flex-start !important;
  }
  .align-items-lg-end {
    align-items: flex-end !important;
  }
  .align-items-lg-center {
    align-items: center !important;
  }
  .align-items-lg-baseline {
    align-items: baseline !important;
  }
  .align-items-lg-stretch {
    align-items: stretch !important;
  }
  .align-content-lg-start {
    align-content: flex-start !important;
  }
  .align-content-lg-end {
    align-content: flex-end !important;
  }
  .align-content-lg-center {
    align-content: center !important;
  }
  .align-content-lg-between {
    align-content: space-between !important;
  }
  .align-content-lg-around {
    align-content: space-around !important;
  }
  .align-content-lg-stretch {
    align-content: stretch !important;
  }
  .align-self-lg-auto {
    align-self: auto !important;
  }
  .align-self-lg-start {
    align-self: flex-start !important;
  }
  .align-self-lg-end {
    align-self: flex-end !important;
  }
  .align-self-lg-center {
    align-self: center !important;
  }
  .align-self-lg-baseline {
    align-self: baseline !important;
  }
  .align-self-lg-stretch {
    align-self: stretch !important;
  }
  .order-lg-first {
    order: -1 !important;
  }
  .order-lg-0 {
    order: 0 !important;
  }
  .order-lg-1 {
    order: 1 !important;
  }
  .order-lg-2 {
    order: 2 !important;
  }
  .order-lg-3 {
    order: 3 !important;
  }
  .order-lg-4 {
    order: 4 !important;
  }
  .order-lg-5 {
    order: 5 !important;
  }
  .order-lg-last {
    order: 6 !important;
  }
  .m-lg-0 {
    margin: 0 !important;
  }
  .m-lg-1 {
    margin: 0.25rem !important;
  }
  .m-lg-2 {
    margin: 0.5rem !important;
  }
  .m-lg-3 {
    margin: 1rem !important;
  }
  .m-lg-4 {
    margin: 1.5rem !important;
  }
  .m-lg-5 {
    margin: 3rem !important;
  }
  .m-lg-auto {
    margin: auto !important;
  }
  .mx-lg-0 {
    margin-right: 0 !important;
    margin-left: 0 !important;
  }
  .mx-lg-1 {
    margin-right: 0.25rem !important;
    margin-left: 0.25rem !important;
  }
  .mx-lg-2 {
    margin-right: 0.5rem !important;
    margin-left: 0.5rem !important;
  }
  .mx-lg-3 {
    margin-right: 1rem !important;
    margin-left: 1rem !important;
  }
  .mx-lg-4 {
    margin-right: 1.5rem !important;
    margin-left: 1.5rem !important;
  }
  .mx-lg-5 {
    margin-right: 3rem !important;
    margin-left: 3rem !important;
  }
  .mx-lg-auto {
    margin-right: auto !important;
    margin-left: auto !important;
  }
  .my-lg-0 {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
  }
  .my-lg-1 {
    margin-top: 0.25rem !important;
    margin-bottom: 0.25rem !important;
  }
  .my-lg-2 {
    margin-top: 0.5rem !important;
    margin-bottom: 0.5rem !important;
  }
  .my-lg-3 {
    margin-top: 1rem !important;
    margin-bottom: 1rem !important;
  }
  .my-lg-4 {
    margin-top: 1.5rem !important;
    margin-bottom: 1.5rem !important;
  }
  .my-lg-5 {
    margin-top: 3rem !important;
    margin-bottom: 3rem !important;
  }
  .my-lg-auto {
    margin-top: auto !important;
    margin-bottom: auto !important;
  }
  .mt-lg-0 {
    margin-top: 0 !important;
  }
  .mt-lg-1 {
    margin-top: 0.25rem !important;
  }
  .mt-lg-2 {
    margin-top: 0.5rem !important;
  }
  .mt-lg-3 {
    margin-top: 1rem !important;
  }
  .mt-lg-4 {
    margin-top: 1.5rem !important;
  }
  .mt-lg-5 {
    margin-top: 3rem !important;
  }
  .mt-lg-auto {
    margin-top: auto !important;
  }
  .me-lg-0 {
    margin-right: 0 !important;
  }
  .me-lg-1 {
    margin-right: 0.25rem !important;
  }
  .me-lg-2 {
    margin-right: 0.5rem !important;
  }
  .me-lg-3 {
    margin-right: 1rem !important;
  }
  .me-lg-4 {
    margin-right: 1.5rem !important;
  }
  .me-lg-5 {
    margin-right: 3rem !important;
  }
  .me-lg-auto {
    margin-right: auto !important;
  }
  .mb-lg-0 {
    margin-bottom: 0 !important;
  }
  .mb-lg-1 {
    margin-bottom: 0.25rem !important;
  }
  .mb-lg-2 {
    margin-bottom: 0.5rem !important;
  }
  .mb-lg-3 {
    margin-bottom: 1rem !important;
  }
  .mb-lg-4 {
    margin-bottom: 1.5rem !important;
  }
  .mb-lg-5 {
    margin-bottom: 3rem !important;
  }
  .mb-lg-auto {
    margin-bottom: auto !important;
  }
  .ms-lg-0 {
    margin-left: 0 !important;
  }
  .ms-lg-1 {
    margin-left: 0.25rem !important;
  }
  .ms-lg-2 {
    margin-left: 0.5rem !important;
  }
  .ms-lg-3 {
    margin-left: 1rem !important;
  }
  .ms-lg-4 {
    margin-left: 1.5rem !important;
  }
  .ms-lg-5 {
    margin-left: 3rem !important;
  }
  .ms-lg-auto {
    margin-left: auto !important;
  }
  .m-lg-n1 {
    margin: -0.25rem !important;
  }
  .m-lg-n2 {
    margin: -0.5rem !important;
  }
  .m-lg-n3 {
    margin: -1rem !important;
  }
  .m-lg-n4 {
    margin: -1.5rem !important;
  }
  .m-lg-n5 {
    margin: -3rem !important;
  }
  .mx-lg-n1 {
    margin-right: -0.25rem !important;
    margin-left: -0.25rem !important;
  }
  .mx-lg-n2 {
    margin-right: -0.5rem !important;
    margin-left: -0.5rem !important;
  }
  .mx-lg-n3 {
    margin-right: -1rem !important;
    margin-left: -1rem !important;
  }
  .mx-lg-n4 {
    margin-right: -1.5rem !important;
    margin-left: -1.5rem !important;
  }
  .mx-lg-n5 {
    margin-right: -3rem !important;
    margin-left: -3rem !important;
  }
  .my-lg-n1 {
    margin-top: -0.25rem !important;
    margin-bottom: -0.25rem !important;
  }
  .my-lg-n2 {
    margin-top: -0.5rem !important;
    margin-bottom: -0.5rem !important;
  }
  .my-lg-n3 {
    margin-top: -1rem !important;
    margin-bottom: -1rem !important;
  }
  .my-lg-n4 {
    margin-top: -1.5rem !important;
    margin-bottom: -1.5rem !important;
  }
  .my-lg-n5 {
    margin-top: -3rem !important;
    margin-bottom: -3rem !important;
  }
  .mt-lg-n1 {
    margin-top: -0.25rem !important;
  }
  .mt-lg-n2 {
    margin-top: -0.5rem !important;
  }
  .mt-lg-n3 {
    margin-top: -1rem !important;
  }
  .mt-lg-n4 {
    margin-top: -1.5rem !important;
  }
  .mt-lg-n5 {
    margin-top: -3rem !important;
  }
  .me-lg-n1 {
    margin-right: -0.25rem !important;
  }
  .me-lg-n2 {
    margin-right: -0.5rem !important;
  }
  .me-lg-n3 {
    margin-right: -1rem !important;
  }
  .me-lg-n4 {
    margin-right: -1.5rem !important;
  }
  .me-lg-n5 {
    margin-right: -3rem !important;
  }
  .mb-lg-n1 {
    margin-bottom: -0.25rem !important;
  }
  .mb-lg-n2 {
    margin-bottom: -0.5rem !important;
  }
  .mb-lg-n3 {
    margin-bottom: -1rem !important;
  }
  .mb-lg-n4 {
    margin-bottom: -1.5rem !important;
  }
  .mb-lg-n5 {
    margin-bottom: -3rem !important;
  }
  .ms-lg-n1 {
    margin-left: -0.25rem !important;
  }
  .ms-lg-n2 {
    margin-left: -0.5rem !important;
  }
  .ms-lg-n3 {
    margin-left: -1rem !important;
  }
  .ms-lg-n4 {
    margin-left: -1.5rem !important;
  }
  .ms-lg-n5 {
    margin-left: -3rem !important;
  }
  .p-lg-0 {
    padding: 0 !important;
  }
  .p-lg-1 {
    padding: 0.25rem !important;
  }
  .p-lg-2 {
    padding: 0.5rem !important;
  }
  .p-lg-3 {
    padding: 1rem !important;
  }
  .p-lg-4 {
    padding: 1.5rem !important;
  }
  .p-lg-5 {
    padding: 3rem !important;
  }
  .px-lg-0 {
    padding-right: 0 !important;
    padding-left: 0 !important;
  }
  .px-lg-1 {
    padding-right: 0.25rem !important;
    padding-left: 0.25rem !important;
  }
  .px-lg-2 {
    padding-right: 0.5rem !important;
    padding-left: 0.5rem !important;
  }
  .px-lg-3 {
    padding-right: 1rem !important;
    padding-left: 1rem !important;
  }
  .px-lg-4 {
    padding-right: 1.5rem !important;
    padding-left: 1.5rem !important;
  }
  .px-lg-5 {
    padding-right: 3rem !important;
    padding-left: 3rem !important;
  }
  .py-lg-0 {
    padding-top: 0 !important;
    padding-bottom: 0 !important;
  }
  .py-lg-1 {
    padding-top: 0.25rem !important;
    padding-bottom: 0.25rem !important;
  }
  .py-lg-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
  }
  .py-lg-3 {
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
  }
  .py-lg-4 {
    padding-top: 1.5rem !important;
    padding-bottom: 1.5rem !important;
  }
  .py-lg-5 {
    padding-top: 3rem !important;
    padding-bottom: 3rem !important;
  }
  .pt-lg-0 {
    padding-top: 0 !important;
  }
  .pt-lg-1 {
    padding-top: 0.25rem !important;
  }
  .pt-lg-2 {
    padding-top: 0.5rem !important;
  }
  .pt-lg-3 {
    padding-top: 1rem !important;
  }
  .pt-lg-4 {
    padding-top: 1.5rem !important;
  }
  .pt-lg-5 {
    padding-top: 3rem !important;
  }
  .pe-lg-0 {
    padding-right: 0 !important;
  }
  .pe-lg-1 {
    padding-right: 0.25rem !important;
  }
  .pe-lg-2 {
    padding-right: 0.5rem !important;
  }
  .pe-lg-3 {
    padding-right: 1rem !important;
  }
  .pe-lg-4 {
    padding-right: 1.5rem !important;
  }
  .pe-lg-5 {
    padding-right: 3rem !important;
  }
  .pb-lg-0 {
    padding-bottom: 0 !important;
  }
  .pb-lg-1 {
    padding-bottom: 0.25rem !important;
  }
  .pb-lg-2 {
    padding-bottom: 0.5rem !important;
  }
  .pb-lg-3 {
    padding-bottom: 1rem !important;
  }
  .pb-lg-4 {
    padding-bottom: 1.5rem !important;
  }
  .pb-lg-5 {
    padding-bottom: 3rem !important;
  }
  .ps-lg-0 {
    padding-left: 0 !important;
  }
  .ps-lg-1 {
    padding-left: 0.25rem !important;
  }
  .ps-lg-2 {
    padding-left: 0.5rem !important;
  }
  .ps-lg-3 {
    padding-left: 1rem !important;
  }
  .ps-lg-4 {
    padding-left: 1.5rem !important;
  }
  .ps-lg-5 {
    padding-left: 3rem !important;
  }
  .gap-lg-0 {
    gap: 0 !important;
  }
  .gap-lg-1 {
    gap: 0.25rem !important;
  }
  .gap-lg-2 {
    gap: 0.5rem !important;
  }
  .gap-lg-3 {
    gap: 1rem !important;
  }
  .gap-lg-4 {
    gap: 1.5rem !important;
  }
  .gap-lg-5 {
    gap: 3rem !important;
  }
  .row-gap-lg-0 {
    row-gap: 0 !important;
  }
  .row-gap-lg-1 {
    row-gap: 0.25rem !important;
  }
  .row-gap-lg-2 {
    row-gap: 0.5rem !important;
  }
  .row-gap-lg-3 {
    row-gap: 1rem !important;
  }
  .row-gap-lg-4 {
    row-gap: 1.5rem !important;
  }
  .row-gap-lg-5 {
    row-gap: 3rem !important;
  }
  .column-gap-lg-0 {
    -moz-column-gap: 0 !important;
    column-gap: 0 !important;
  }
  .column-gap-lg-1 {
    -moz-column-gap: 0.25rem !important;
    column-gap: 0.25rem !important;
  }
  .column-gap-lg-2 {
    -moz-column-gap: 0.5rem !important;
    column-gap: 0.5rem !important;
  }
  .column-gap-lg-3 {
    -moz-column-gap: 1rem !important;
    column-gap: 1rem !important;
  }
  .column-gap-lg-4 {
    -moz-column-gap: 1.5rem !important;
    column-gap: 1.5rem !important;
  }
  .column-gap-lg-5 {
    -moz-column-gap: 3rem !important;
    column-gap: 3rem !important;
  }
  .text-lg-start {
    text-align: left !important;
  }
  .text-lg-end {
    text-align: right !important;
  }
  .text-lg-center {
    text-align: center !important;
  }
}
@media (min-width: 1200px) {
  .float-xl-start {
    float: left !important;
  }
  .float-xl-end {
    float: right !important;
  }
  .float-xl-none {
    float: none !important;
  }
  .object-fit-xl-contain {
    -o-object-fit: contain !important;
    object-fit: contain !important;
  }
  .object-fit-xl-cover {
    -o-object-fit: cover !important;
    object-fit: cover !important;
  }
  .object-fit-xl-fill {
    -o-object-fit: fill !important;
    object-fit: fill !important;
  }
  .object-fit-xl-scale {
    -o-object-fit: scale-down !important;
    object-fit: scale-down !important;
  }
  .object-fit-xl-none {
    -o-object-fit: none !important;
    object-fit: none !important;
  }
  .d-xl-inline {
    display: inline !important;
  }
  .d-xl-inline-block {
    display: inline-block !important;
  }
  .d-xl-block {
    display: block !important;
  }
  .d-xl-grid {
    display: grid !important;
  }
  .d-xl-inline-grid {
    display: inline-grid !important;
  }
  .d-xl-table {
    display: table !important;
  }
  .d-xl-table-row {
    display: table-row !important;
  }
  .d-xl-table-cell {
    display: table-cell !important;
  }
  .d-xl-flex {
    display: flex !important;
  }
  .d-xl-inline-flex {
    display: inline-flex !important;
  }
  .d-xl-none {
    display: none !important;
  }
  .flex-xl-fill {
    flex: 1 1 auto !important;
  }
  .flex-xl-row {
    flex-direction: row !important;
  }
  .flex-xl-column {
    flex-direction: column !important;
  }
  .flex-xl-row-reverse {
    flex-direction: row-reverse !important;
  }
  .flex-xl-column-reverse {
    flex-direction: column-reverse !important;
  }
  .flex-xl-grow-0 {
    flex-grow: 0 !important;
  }
  .flex-xl-grow-1 {
    flex-grow: 1 !important;
  }
  .flex-xl-shrink-0 {
    flex-shrink: 0 !important;
  }
  .flex-xl-shrink-1 {
    flex-shrink: 1 !important;
  }
  .flex-xl-wrap {
    flex-wrap: wrap !important;
  }
  .flex-xl-nowrap {
    flex-wrap: nowrap !important;
  }
  .flex-xl-wrap-reverse {
    flex-wrap: wrap-reverse !important;
  }
  .justify-content-xl-start {
    justify-content: flex-start !important;
  }
  .justify-content-xl-end {
    justify-content: flex-end !important;
  }
  .justify-content-xl-center {
    justify-content: center !important;
  }
  .justify-content-xl-between {
    justify-content: space-between !important;
  }
  .justify-content-xl-around {
    justify-content: space-around !important;
  }
  .justify-content-xl-evenly {
    justify-content: space-evenly !important;
  }
  .align-items-xl-start {
    align-items: flex-start !important;
  }
  .align-items-xl-end {
    align-items: flex-end !important;
  }
  .align-items-xl-center {
    align-items: center !important;
  }
  .align-items-xl-baseline {
    align-items: baseline !important;
  }
  .align-items-xl-stretch {
    align-items: stretch !important;
  }
  .align-content-xl-start {
    align-content: flex-start !important;
  }
  .align-content-xl-end {
    align-content: flex-end !important;
  }
  .align-content-xl-center {
    align-content: center !important;
  }
  .align-content-xl-between {
    align-content: space-between !important;
  }
  .align-content-xl-around {
    align-content: space-around !important;
  }
  .align-content-xl-stretch {
    align-content: stretch !important;
  }
  .align-self-xl-auto {
    align-self: auto !important;
  }
  .align-self-xl-start {
    align-self: flex-start !important;
  }
  .align-self-xl-end {
    align-self: flex-end !important;
  }
  .align-self-xl-center {
    align-self: center !important;
  }
  .align-self-xl-baseline {
    align-self: baseline !important;
  }
  .align-self-xl-stretch {
    align-self: stretch !important;
  }
  .order-xl-first {
    order: -1 !important;
  }
  .order-xl-0 {
    order: 0 !important;
  }
  .order-xl-1 {
    order: 1 !important;
  }
  .order-xl-2 {
    order: 2 !important;
  }
  .order-xl-3 {
    order: 3 !important;
  }
  .order-xl-4 {
    order: 4 !important;
  }
  .order-xl-5 {
    order: 5 !important;
  }
  .order-xl-last {
    order: 6 !important;
  }
  .m-xl-0 {
    margin: 0 !important;
  }
  .m-xl-1 {
    margin: 0.25rem !important;
  }
  .m-xl-2 {
    margin: 0.5rem !important;
  }
  .m-xl-3 {
    margin: 1rem !important;
  }
  .m-xl-4 {
    margin: 1.5rem !important;
  }
  .m-xl-5 {
    margin: 3rem !important;
  }
  .m-xl-auto {
    margin: auto !important;
  }
  .mx-xl-0 {
    margin-right: 0 !important;
    margin-left: 0 !important;
  }
  .mx-xl-1 {
    margin-right: 0.25rem !important;
    margin-left: 0.25rem !important;
  }
  .mx-xl-2 {
    margin-right: 0.5rem !important;
    margin-left: 0.5rem !important;
  }
  .mx-xl-3 {
    margin-right: 1rem !important;
    margin-left: 1rem !important;
  }
  .mx-xl-4 {
    margin-right: 1.5rem !important;
    margin-left: 1.5rem !important;
  }
  .mx-xl-5 {
    margin-right: 3rem !important;
    margin-left: 3rem !important;
  }
  .mx-xl-auto {
    margin-right: auto !important;
    margin-left: auto !important;
  }
  .my-xl-0 {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
  }
  .my-xl-1 {
    margin-top: 0.25rem !important;
    margin-bottom: 0.25rem !important;
  }
  .my-xl-2 {
    margin-top: 0.5rem !important;
    margin-bottom: 0.5rem !important;
  }
  .my-xl-3 {
    margin-top: 1rem !important;
    margin-bottom: 1rem !important;
  }
  .my-xl-4 {
    margin-top: 1.5rem !important;
    margin-bottom: 1.5rem !important;
  }
  .my-xl-5 {
    margin-top: 3rem !important;
    margin-bottom: 3rem !important;
  }
  .my-xl-auto {
    margin-top: auto !important;
    margin-bottom: auto !important;
  }
  .mt-xl-0 {
    margin-top: 0 !important;
  }
  .mt-xl-1 {
    margin-top: 0.25rem !important;
  }
  .mt-xl-2 {
    margin-top: 0.5rem !important;
  }
  .mt-xl-3 {
    margin-top: 1rem !important;
  }
  .mt-xl-4 {
    margin-top: 1.5rem !important;
  }
  .mt-xl-5 {
    margin-top: 3rem !important;
  }
  .mt-xl-auto {
    margin-top: auto !important;
  }
  .me-xl-0 {
    margin-right: 0 !important;
  }
  .me-xl-1 {
    margin-right: 0.25rem !important;
  }
  .me-xl-2 {
    margin-right: 0.5rem !important;
  }
  .me-xl-3 {
    margin-right: 1rem !important;
  }
  .me-xl-4 {
    margin-right: 1.5rem !important;
  }
  .me-xl-5 {
    margin-right: 3rem !important;
  }
  .me-xl-auto {
    margin-right: auto !important;
  }
  .mb-xl-0 {
    margin-bottom: 0 !important;
  }
  .mb-xl-1 {
    margin-bottom: 0.25rem !important;
  }
  .mb-xl-2 {
    margin-bottom: 0.5rem !important;
  }
  .mb-xl-3 {
    margin-bottom: 1rem !important;
  }
  .mb-xl-4 {
    margin-bottom: 1.5rem !important;
  }
  .mb-xl-5 {
    margin-bottom: 3rem !important;
  }
  .mb-xl-auto {
    margin-bottom: auto !important;
  }
  .ms-xl-0 {
    margin-left: 0 !important;
  }
  .ms-xl-1 {
    margin-left: 0.25rem !important;
  }
  .ms-xl-2 {
    margin-left: 0.5rem !important;
  }
  .ms-xl-3 {
    margin-left: 1rem !important;
  }
  .ms-xl-4 {
    margin-left: 1.5rem !important;
  }
  .ms-xl-5 {
    margin-left: 3rem !important;
  }
  .ms-xl-auto {
    margin-left: auto !important;
  }
  .m-xl-n1 {
    margin: -0.25rem !important;
  }
  .m-xl-n2 {
    margin: -0.5rem !important;
  }
  .m-xl-n3 {
    margin: -1rem !important;
  }
  .m-xl-n4 {
    margin: -1.5rem !important;
  }
  .m-xl-n5 {
    margin: -3rem !important;
  }
  .mx-xl-n1 {
    margin-right: -0.25rem !important;
    margin-left: -0.25rem !important;
  }
  .mx-xl-n2 {
    margin-right: -0.5rem !important;
    margin-left: -0.5rem !important;
  }
  .mx-xl-n3 {
    margin-right: -1rem !important;
    margin-left: -1rem !important;
  }
  .mx-xl-n4 {
    margin-right: -1.5rem !important;
    margin-left: -1.5rem !important;
  }
  .mx-xl-n5 {
    margin-right: -3rem !important;
    margin-left: -3rem !important;
  }
  .my-xl-n1 {
    margin-top: -0.25rem !important;
    margin-bottom: -0.25rem !important;
  }
  .my-xl-n2 {
    margin-top: -0.5rem !important;
    margin-bottom: -0.5rem !important;
  }
  .my-xl-n3 {
    margin-top: -1rem !important;
    margin-bottom: -1rem !important;
  }
  .my-xl-n4 {
    margin-top: -1.5rem !important;
    margin-bottom: -1.5rem !important;
  }
  .my-xl-n5 {
    margin-top: -3rem !important;
    margin-bottom: -3rem !important;
  }
  .mt-xl-n1 {
    margin-top: -0.25rem !important;
  }
  .mt-xl-n2 {
    margin-top: -0.5rem !important;
  }
  .mt-xl-n3 {
    margin-top: -1rem !important;
  }
  .mt-xl-n4 {
    margin-top: -1.5rem !important;
  }
  .mt-xl-n5 {
    margin-top: -3rem !important;
  }
  .me-xl-n1 {
    margin-right: -0.25rem !important;
  }
  .me-xl-n2 {
    margin-right: -0.5rem !important;
  }
  .me-xl-n3 {
    margin-right: -1rem !important;
  }
  .me-xl-n4 {
    margin-right: -1.5rem !important;
  }
  .me-xl-n5 {
    margin-right: -3rem !important;
  }
  .mb-xl-n1 {
    margin-bottom: -0.25rem !important;
  }
  .mb-xl-n2 {
    margin-bottom: -0.5rem !important;
  }
  .mb-xl-n3 {
    margin-bottom: -1rem !important;
  }
  .mb-xl-n4 {
    margin-bottom: -1.5rem !important;
  }
  .mb-xl-n5 {
    margin-bottom: -3rem !important;
  }
  .ms-xl-n1 {
    margin-left: -0.25rem !important;
  }
  .ms-xl-n2 {
    margin-left: -0.5rem !important;
  }
  .ms-xl-n3 {
    margin-left: -1rem !important;
  }
  .ms-xl-n4 {
    margin-left: -1.5rem !important;
  }
  .ms-xl-n5 {
    margin-left: -3rem !important;
  }
  .p-xl-0 {
    padding: 0 !important;
  }
  .p-xl-1 {
    padding: 0.25rem !important;
  }
  .p-xl-2 {
    padding: 0.5rem !important;
  }
  .p-xl-3 {
    padding: 1rem !important;
  }
  .p-xl-4 {
    padding: 1.5rem !important;
  }
  .p-xl-5 {
    padding: 3rem !important;
  }
  .px-xl-0 {
    padding-right: 0 !important;
    padding-left: 0 !important;
  }
  .px-xl-1 {
    padding-right: 0.25rem !important;
    padding-left: 0.25rem !important;
  }
  .px-xl-2 {
    padding-right: 0.5rem !important;
    padding-left: 0.5rem !important;
  }
  .px-xl-3 {
    padding-right: 1rem !important;
    padding-left: 1rem !important;
  }
  .px-xl-4 {
    padding-right: 1.5rem !important;
    padding-left: 1.5rem !important;
  }
  .px-xl-5 {
    padding-right: 3rem !important;
    padding-left: 3rem !important;
  }
  .py-xl-0 {
    padding-top: 0 !important;
    padding-bottom: 0 !important;
  }
  .py-xl-1 {
    padding-top: 0.25rem !important;
    padding-bottom: 0.25rem !important;
  }
  .py-xl-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
  }
  .py-xl-3 {
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
  }
  .py-xl-4 {
    padding-top: 1.5rem !important;
    padding-bottom: 1.5rem !important;
  }
  .py-xl-5 {
    padding-top: 3rem !important;
    padding-bottom: 3rem !important;
  }
  .pt-xl-0 {
    padding-top: 0 !important;
  }
  .pt-xl-1 {
    padding-top: 0.25rem !important;
  }
  .pt-xl-2 {
    padding-top: 0.5rem !important;
  }
  .pt-xl-3 {
    padding-top: 1rem !important;
  }
  .pt-xl-4 {
    padding-top: 1.5rem !important;
  }
  .pt-xl-5 {
    padding-top: 3rem !important;
  }
  .pe-xl-0 {
    padding-right: 0 !important;
  }
  .pe-xl-1 {
    padding-right: 0.25rem !important;
  }
  .pe-xl-2 {
    padding-right: 0.5rem !important;
  }
  .pe-xl-3 {
    padding-right: 1rem !important;
  }
  .pe-xl-4 {
    padding-right: 1.5rem !important;
  }
  .pe-xl-5 {
    padding-right: 3rem !important;
  }
  .pb-xl-0 {
    padding-bottom: 0 !important;
  }
  .pb-xl-1 {
    padding-bottom: 0.25rem !important;
  }
  .pb-xl-2 {
    padding-bottom: 0.5rem !important;
  }
  .pb-xl-3 {
    padding-bottom: 1rem !important;
  }
  .pb-xl-4 {
    padding-bottom: 1.5rem !important;
  }
  .pb-xl-5 {
    padding-bottom: 3rem !important;
  }
  .ps-xl-0 {
    padding-left: 0 !important;
  }
  .ps-xl-1 {
    padding-left: 0.25rem !important;
  }
  .ps-xl-2 {
    padding-left: 0.5rem !important;
  }
  .ps-xl-3 {
    padding-left: 1rem !important;
  }
  .ps-xl-4 {
    padding-left: 1.5rem !important;
  }
  .ps-xl-5 {
    padding-left: 3rem !important;
  }
  .gap-xl-0 {
    gap: 0 !important;
  }
  .gap-xl-1 {
    gap: 0.25rem !important;
  }
  .gap-xl-2 {
    gap: 0.5rem !important;
  }
  .gap-xl-3 {
    gap: 1rem !important;
  }
  .gap-xl-4 {
    gap: 1.5rem !important;
  }
  .gap-xl-5 {
    gap: 3rem !important;
  }
  .row-gap-xl-0 {
    row-gap: 0 !important;
  }
  .row-gap-xl-1 {
    row-gap: 0.25rem !important;
  }
  .row-gap-xl-2 {
    row-gap: 0.5rem !important;
  }
  .row-gap-xl-3 {
    row-gap: 1rem !important;
  }
  .row-gap-xl-4 {
    row-gap: 1.5rem !important;
  }
  .row-gap-xl-5 {
    row-gap: 3rem !important;
  }
  .column-gap-xl-0 {
    -moz-column-gap: 0 !important;
    column-gap: 0 !important;
  }
  .column-gap-xl-1 {
    -moz-column-gap: 0.25rem !important;
    column-gap: 0.25rem !important;
  }
  .column-gap-xl-2 {
    -moz-column-gap: 0.5rem !important;
    column-gap: 0.5rem !important;
  }
  .column-gap-xl-3 {
    -moz-column-gap: 1rem !important;
    column-gap: 1rem !important;
  }
  .column-gap-xl-4 {
    -moz-column-gap: 1.5rem !important;
    column-gap: 1.5rem !important;
  }
  .column-gap-xl-5 {
    -moz-column-gap: 3rem !important;
    column-gap: 3rem !important;
  }
  .text-xl-start {
    text-align: left !important;
  }
  .text-xl-end {
    text-align: right !important;
  }
  .text-xl-center {
    text-align: center !important;
  }
}
@media (min-width: 1400px) {
  .float-xxl-start {
    float: left !important;
  }
  .float-xxl-end {
    float: right !important;
  }
  .float-xxl-none {
    float: none !important;
  }
  .object-fit-xxl-contain {
    -o-object-fit: contain !important;
    object-fit: contain !important;
  }
  .object-fit-xxl-cover {
    -o-object-fit: cover !important;
    object-fit: cover !important;
  }
  .object-fit-xxl-fill {
    -o-object-fit: fill !important;
    object-fit: fill !important;
  }
  .object-fit-xxl-scale {
    -o-object-fit: scale-down !important;
    object-fit: scale-down !important;
  }
  .object-fit-xxl-none {
    -o-object-fit: none !important;
    object-fit: none !important;
  }
  .d-xxl-inline {
    display: inline !important;
  }
  .d-xxl-inline-block {
    display: inline-block !important;
  }
  .d-xxl-block {
    display: block !important;
  }
  .d-xxl-grid {
    display: grid !important;
  }
  .d-xxl-inline-grid {
    display: inline-grid !important;
  }
  .d-xxl-table {
    display: table !important;
  }
  .d-xxl-table-row {
    display: table-row !important;
  }
  .d-xxl-table-cell {
    display: table-cell !important;
  }
  .d-xxl-flex {
    display: flex !important;
  }
  .d-xxl-inline-flex {
    display: inline-flex !important;
  }
  .d-xxl-none {
    display: none !important;
  }
  .flex-xxl-fill {
    flex: 1 1 auto !important;
  }
  .flex-xxl-row {
    flex-direction: row !important;
  }
  .flex-xxl-column {
    flex-direction: column !important;
  }
  .flex-xxl-row-reverse {
    flex-direction: row-reverse !important;
  }
  .flex-xxl-column-reverse {
    flex-direction: column-reverse !important;
  }
  .flex-xxl-grow-0 {
    flex-grow: 0 !important;
  }
  .flex-xxl-grow-1 {
    flex-grow: 1 !important;
  }
  .flex-xxl-shrink-0 {
    flex-shrink: 0 !important;
  }
  .flex-xxl-shrink-1 {
    flex-shrink: 1 !important;
  }
  .flex-xxl-wrap {
    flex-wrap: wrap !important;
  }
  .flex-xxl-nowrap {
    flex-wrap: nowrap !important;
  }
  .flex-xxl-wrap-reverse {
    flex-wrap: wrap-reverse !important;
  }
  .justify-content-xxl-start {
    justify-content: flex-start !important;
  }
  .justify-content-xxl-end {
    justify-content: flex-end !important;
  }
  .justify-content-xxl-center {
    justify-content: center !important;
  }
  .justify-content-xxl-between {
    justify-content: space-between !important;
  }
  .justify-content-xxl-around {
    justify-content: space-around !important;
  }
  .justify-content-xxl-evenly {
    justify-content: space-evenly !important;
  }
  .align-items-xxl-start {
    align-items: flex-start !important;
  }
  .align-items-xxl-end {
    align-items: flex-end !important;
  }
  .align-items-xxl-center {
    align-items: center !important;
  }
  .align-items-xxl-baseline {
    align-items: baseline !important;
  }
  .align-items-xxl-stretch {
    align-items: stretch !important;
  }
  .align-content-xxl-start {
    align-content: flex-start !important;
  }
  .align-content-xxl-end {
    align-content: flex-end !important;
  }
  .align-content-xxl-center {
    align-content: center !important;
  }
  .align-content-xxl-between {
    align-content: space-between !important;
  }
  .align-content-xxl-around {
    align-content: space-around !important;
  }
  .align-content-xxl-stretch {
    align-content: stretch !important;
  }
  .align-self-xxl-auto {
    align-self: auto !important;
  }
  .align-self-xxl-start {
    align-self: flex-start !important;
  }
  .align-self-xxl-end {
    align-self: flex-end !important;
  }
  .align-self-xxl-center {
    align-self: center !important;
  }
  .align-self-xxl-baseline {
    align-self: baseline !important;
  }
  .align-self-xxl-stretch {
    align-self: stretch !important;
  }
  .order-xxl-first {
    order: -1 !important;
  }
  .order-xxl-0 {
    order: 0 !important;
  }
  .order-xxl-1 {
    order: 1 !important;
  }
  .order-xxl-2 {
    order: 2 !important;
  }
  .order-xxl-3 {
    order: 3 !important;
  }
  .order-xxl-4 {
    order: 4 !important;
  }
  .order-xxl-5 {
    order: 5 !important;
  }
  .order-xxl-last {
    order: 6 !important;
  }
  .m-xxl-0 {
    margin: 0 !important;
  }
  .m-xxl-1 {
    margin: 0.25rem !important;
  }
  .m-xxl-2 {
    margin: 0.5rem !important;
  }
  .m-xxl-3 {
    margin: 1rem !important;
  }
  .m-xxl-4 {
    margin: 1.5rem !important;
  }
  .m-xxl-5 {
    margin: 3rem !important;
  }
  .m-xxl-auto {
    margin: auto !important;
  }
  .mx-xxl-0 {
    margin-right: 0 !important;
    margin-left: 0 !important;
  }
  .mx-xxl-1 {
    margin-right: 0.25rem !important;
    margin-left: 0.25rem !important;
  }
  .mx-xxl-2 {
    margin-right: 0.5rem !important;
    margin-left: 0.5rem !important;
  }
  .mx-xxl-3 {
    margin-right: 1rem !important;
    margin-left: 1rem !important;
  }
  .mx-xxl-4 {
    margin-right: 1.5rem !important;
    margin-left: 1.5rem !important;
  }
  .mx-xxl-5 {
    margin-right: 3rem !important;
    margin-left: 3rem !important;
  }
  .mx-xxl-auto {
    margin-right: auto !important;
    margin-left: auto !important;
  }
  .my-xxl-0 {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
  }
  .my-xxl-1 {
    margin-top: 0.25rem !important;
    margin-bottom: 0.25rem !important;
  }
  .my-xxl-2 {
    margin-top: 0.5rem !important;
    margin-bottom: 0.5rem !important;
  }
  .my-xxl-3 {
    margin-top: 1rem !important;
    margin-bottom: 1rem !important;
  }
  .my-xxl-4 {
    margin-top: 1.5rem !important;
    margin-bottom: 1.5rem !important;
  }
  .my-xxl-5 {
    margin-top: 3rem !important;
    margin-bottom: 3rem !important;
  }
  .my-xxl-auto {
    margin-top: auto !important;
    margin-bottom: auto !important;
  }
  .mt-xxl-0 {
    margin-top: 0 !important;
  }
  .mt-xxl-1 {
    margin-top: 0.25rem !important;
  }
  .mt-xxl-2 {
    margin-top: 0.5rem !important;
  }
  .mt-xxl-3 {
    margin-top: 1rem !important;
  }
  .mt-xxl-4 {
    margin-top: 1.5rem !important;
  }
  .mt-xxl-5 {
    margin-top: 3rem !important;
  }
  .mt-xxl-auto {
    margin-top: auto !important;
  }
  .me-xxl-0 {
    margin-right: 0 !important;
  }
  .me-xxl-1 {
    margin-right: 0.25rem !important;
  }
  .me-xxl-2 {
    margin-right: 0.5rem !important;
  }
  .me-xxl-3 {
    margin-right: 1rem !important;
  }
  .me-xxl-4 {
    margin-right: 1.5rem !important;
  }
  .me-xxl-5 {
    margin-right: 3rem !important;
  }
  .me-xxl-auto {
    margin-right: auto !important;
  }
  .mb-xxl-0 {
    margin-bottom: 0 !important;
  }
  .mb-xxl-1 {
    margin-bottom: 0.25rem !important;
  }
  .mb-xxl-2 {
    margin-bottom: 0.5rem !important;
  }
  .mb-xxl-3 {
    margin-bottom: 1rem !important;
  }
  .mb-xxl-4 {
    margin-bottom: 1.5rem !important;
  }
  .mb-xxl-5 {
    margin-bottom: 3rem !important;
  }
  .mb-xxl-auto {
    margin-bottom: auto !important;
  }
  .ms-xxl-0 {
    margin-left: 0 !important;
  }
  .ms-xxl-1 {
    margin-left: 0.25rem !important;
  }
  .ms-xxl-2 {
    margin-left: 0.5rem !important;
  }
  .ms-xxl-3 {
    margin-left: 1rem !important;
  }
  .ms-xxl-4 {
    margin-left: 1.5rem !important;
  }
  .ms-xxl-5 {
    margin-left: 3rem !important;
  }
  .ms-xxl-auto {
    margin-left: auto !important;
  }
  .m-xxl-n1 {
    margin: -0.25rem !important;
  }
  .m-xxl-n2 {
    margin: -0.5rem !important;
  }
  .m-xxl-n3 {
    margin: -1rem !important;
  }
  .m-xxl-n4 {
    margin: -1.5rem !important;
  }
  .m-xxl-n5 {
    margin: -3rem !important;
  }
  .mx-xxl-n1 {
    margin-right: -0.25rem !important;
    margin-left: -0.25rem !important;
  }
  .mx-xxl-n2 {
    margin-right: -0.5rem !important;
    margin-left: -0.5rem !important;
  }
  .mx-xxl-n3 {
    margin-right: -1rem !important;
    margin-left: -1rem !important;
  }
  .mx-xxl-n4 {
    margin-right: -1.5rem !important;
    margin-left: -1.5rem !important;
  }
  .mx-xxl-n5 {
    margin-right: -3rem !important;
    margin-left: -3rem !important;
  }
  .my-xxl-n1 {
    margin-top: -0.25rem !important;
    margin-bottom: -0.25rem !important;
  }
  .my-xxl-n2 {
    margin-top: -0.5rem !important;
    margin-bottom: -0.5rem !important;
  }
  .my-xxl-n3 {
    margin-top: -1rem !important;
    margin-bottom: -1rem !important;
  }
  .my-xxl-n4 {
    margin-top: -1.5rem !important;
    margin-bottom: -1.5rem !important;
  }
  .my-xxl-n5 {
    margin-top: -3rem !important;
    margin-bottom: -3rem !important;
  }
  .mt-xxl-n1 {
    margin-top: -0.25rem !important;
  }
  .mt-xxl-n2 {
    margin-top: -0.5rem !important;
  }
  .mt-xxl-n3 {
    margin-top: -1rem !important;
  }
  .mt-xxl-n4 {
    margin-top: -1.5rem !important;
  }
  .mt-xxl-n5 {
    margin-top: -3rem !important;
  }
  .me-xxl-n1 {
    margin-right: -0.25rem !important;
  }
  .me-xxl-n2 {
    margin-right: -0.5rem !important;
  }
  .me-xxl-n3 {
    margin-right: -1rem !important;
  }
  .me-xxl-n4 {
    margin-right: -1.5rem !important;
  }
  .me-xxl-n5 {
    margin-right: -3rem !important;
  }
  .mb-xxl-n1 {
    margin-bottom: -0.25rem !important;
  }
  .mb-xxl-n2 {
    margin-bottom: -0.5rem !important;
  }
  .mb-xxl-n3 {
    margin-bottom: -1rem !important;
  }
  .mb-xxl-n4 {
    margin-bottom: -1.5rem !important;
  }
  .mb-xxl-n5 {
    margin-bottom: -3rem !important;
  }
  .ms-xxl-n1 {
    margin-left: -0.25rem !important;
  }
  .ms-xxl-n2 {
    margin-left: -0.5rem !important;
  }
  .ms-xxl-n3 {
    margin-left: -1rem !important;
  }
  .ms-xxl-n4 {
    margin-left: -1.5rem !important;
  }
  .ms-xxl-n5 {
    margin-left: -3rem !important;
  }
  .p-xxl-0 {
    padding: 0 !important;
  }
  .p-xxl-1 {
    padding: 0.25rem !important;
  }
  .p-xxl-2 {
    padding: 0.5rem !important;
  }
  .p-xxl-3 {
    padding: 1rem !important;
  }
  .p-xxl-4 {
    padding: 1.5rem !important;
  }
  .p-xxl-5 {
    padding: 3rem !important;
  }
  .px-xxl-0 {
    padding-right: 0 !important;
    padding-left: 0 !important;
  }
  .px-xxl-1 {
    padding-right: 0.25rem !important;
    padding-left: 0.25rem !important;
  }
  .px-xxl-2 {
    padding-right: 0.5rem !important;
    padding-left: 0.5rem !important;
  }
  .px-xxl-3 {
    padding-right: 1rem !important;
    padding-left: 1rem !important;
  }
  .px-xxl-4 {
    padding-right: 1.5rem !important;
    padding-left: 1.5rem !important;
  }
  .px-xxl-5 {
    padding-right: 3rem !important;
    padding-left: 3rem !important;
  }
  .py-xxl-0 {
    padding-top: 0 !important;
    padding-bottom: 0 !important;
  }
  .py-xxl-1 {
    padding-top: 0.25rem !important;
    padding-bottom: 0.25rem !important;
  }
  .py-xxl-2 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
  }
  .py-xxl-3 {
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
  }
  .py-xxl-4 {
    padding-top: 1.5rem !important;
    padding-bottom: 1.5rem !important;
  }
  .py-xxl-5 {
    padding-top: 3rem !important;
    padding-bottom: 3rem !important;
  }
  .pt-xxl-0 {
    padding-top: 0 !important;
  }
  .pt-xxl-1 {
    padding-top: 0.25rem !important;
  }
  .pt-xxl-2 {
    padding-top: 0.5rem !important;
  }
  .pt-xxl-3 {
    padding-top: 1rem !important;
  }
  .pt-xxl-4 {
    padding-top: 1.5rem !important;
  }
  .pt-xxl-5 {
    padding-top: 3rem !important;
  }
  .pe-xxl-0 {
    padding-right: 0 !important;
  }
  .pe-xxl-1 {
    padding-right: 0.25rem !important;
  }
  .pe-xxl-2 {
    padding-right: 0.5rem !important;
  }
  .pe-xxl-3 {
    padding-right: 1rem !important;
  }
  .pe-xxl-4 {
    padding-right: 1.5rem !important;
  }
  .pe-xxl-5 {
    padding-right: 3rem !important;
  }
  .pb-xxl-0 {
    padding-bottom: 0 !important;
  }
  .pb-xxl-1 {
    padding-bottom: 0.25rem !important;
  }
  .pb-xxl-2 {
    padding-bottom: 0.5rem !important;
  }
  .pb-xxl-3 {
    padding-bottom: 1rem !important;
  }
  .pb-xxl-4 {
    padding-bottom: 1.5rem !important;
  }
  .pb-xxl-5 {
    padding-bottom: 3rem !important;
  }
  .ps-xxl-0 {
    padding-left: 0 !important;
  }
  .ps-xxl-1 {
    padding-left: 0.25rem !important;
  }
  .ps-xxl-2 {
    padding-left: 0.5rem !important;
  }
  .ps-xxl-3 {
    padding-left: 1rem !important;
  }
  .ps-xxl-4 {
    padding-left: 1.5rem !important;
  }
  .ps-xxl-5 {
    padding-left: 3rem !important;
  }
  .gap-xxl-0 {
    gap: 0 !important;
  }
  .gap-xxl-1 {
    gap: 0.25rem !important;
  }
  .gap-xxl-2 {
    gap: 0.5rem !important;
  }
  .gap-xxl-3 {
    gap: 1rem !important;
  }
  .gap-xxl-4 {
    gap: 1.5rem !important;
  }
  .gap-xxl-5 {
    gap: 3rem !important;
  }
  .row-gap-xxl-0 {
    row-gap: 0 !important;
  }
  .row-gap-xxl-1 {
    row-gap: 0.25rem !important;
  }
  .row-gap-xxl-2 {
    row-gap: 0.5rem !important;
  }
  .row-gap-xxl-3 {
    row-gap: 1rem !important;
  }
  .row-gap-xxl-4 {
    row-gap: 1.5rem !important;
  }
  .row-gap-xxl-5 {
    row-gap: 3rem !important;
  }
  .column-gap-xxl-0 {
    -moz-column-gap: 0 !important;
    column-gap: 0 !important;
  }
  .column-gap-xxl-1 {
    -moz-column-gap: 0.25rem !important;
    column-gap: 0.25rem !important;
  }
  .column-gap-xxl-2 {
    -moz-column-gap: 0.5rem !important;
    column-gap: 0.5rem !important;
  }
  .column-gap-xxl-3 {
    -moz-column-gap: 1rem !important;
    column-gap: 1rem !important;
  }
  .column-gap-xxl-4 {
    -moz-column-gap: 1.5rem !important;
    column-gap: 1.5rem !important;
  }
  .column-gap-xxl-5 {
    -moz-column-gap: 3rem !important;
    column-gap: 3rem !important;
  }
  .text-xxl-start {
    text-align: left !important;
  }
  .text-xxl-end {
    text-align: right !important;
  }
  .text-xxl-center {
    text-align: center !important;
  }
}
@media (min-width: 1200px) {
  .fs-1 {
    font-size: 2.5rem !important;
  }
  .fs-2 {
    font-size: 2rem !important;
  }
  .fs-3 {
    font-size: 1.75rem !important;
  }
  .fs-4 {
    font-size: 1.5rem !important;
  }
}
@media print {
  .d-print-inline {
    display: inline !important;
  }
  .d-print-inline-block {
    display: inline-block !important;
  }
  .d-print-block {
    display: block !important;
  }
  .d-print-grid {
    display: grid !important;
  }
  .d-print-inline-grid {
    display: inline-grid !important;
  }
  .d-print-table {
    display: table !important;
  }
  .d-print-table-row {
    display: table-row !important;
  }
  .d-print-table-cell {
    display: table-cell !important;
  }
  .d-print-flex {
    display: flex !important;
  }
  .d-print-inline-flex {
    display: inline-flex !important;
  }
  .d-print-none {
    display: none !important;
  }
}
@keyframes flipInX {
  0% {
    opacity: 0;
    transition-timing-function: ease-in;
    transform: perspective(400px) rotate3d(1, 0, 0, 90deg);
  }
  40% {
    transition-timing-function: ease-in;
    transform: perspective(400px) rotate3d(1, 0, 0, -20deg);
  }
  60% {
    opacity: 1;
    transform: perspective(400px) rotate3d(1, 0, 0, 10deg);
  }
  80% {
    transform: perspective(400px) rotate3d(1, 0, 0, -5deg);
  }
  100% {
    transform: perspective(400px);
  }
}
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
@keyframes fadeOut {
  from {
    opacity: 1;
  }
  to {
    opacity: 0;
  }
}
@keyframes shake {
  0% {
    transform: translate(2px, 1px) rotate(0deg);
  }
  10% {
    transform: translate(-1px, -2px) rotate(-2deg);
  }
  20% {
    transform: translate(-3px, 0) rotate(3deg);
  }
  30% {
    transform: translate(0, 2px) rotate(0deg);
  }
  40% {
    transform: translate(1px, -1px) rotate(1deg);
  }
  50% {
    transform: translate(-1px, 2px) rotate(-1deg);
  }
  60% {
    transform: translate(-3px, 1px) rotate(0deg);
  }
  70% {
    transform: translate(2px, 1px) rotate(-2deg);
  }
  80% {
    transform: translate(-1px, -1px) rotate(4deg);
  }
  90% {
    transform: translate(2px, 2px) rotate(0deg);
  }
  100% {
    transform: translate(1px, -2px) rotate(-1deg);
  }
}
@keyframes wobble {
  0% {
    transform: none;
  }
  15% {
    transform: translate3d(-25%, 0, 0) rotate3d(0, 0, 1, -5deg);
  }
  30% {
    transform: translate3d(20%, 0, 0) rotate3d(0, 0, 1, 3deg);
  }
  45% {
    transform: translate3d(-15%, 0, 0) rotate3d(0, 0, 1, -3deg);
  }
  60% {
    transform: translate3d(10%, 0, 0) rotate3d(0, 0, 1, 2deg);
  }
  75% {
    transform: translate3d(-5%, 0, 0) rotate3d(0, 0, 1, -1deg);
  }
  100% {
    transform: none;
  }
}
:root,
[data-bs-theme=light] {
  --lte-sidebar-width: 250px;
}

.app-wrapper {
  position: relative;
  display: grid;
  grid-template-areas: "lte-app-sidebar lte-app-header" "lte-app-sidebar lte-app-main" "lte-app-sidebar lte-app-footer";
  grid-template-rows: min-content 1fr min-content;
  grid-template-columns: auto 1fr;
  grid-gap: 0;
  align-content: stretch;
  align-items: stretch;
  max-width: 100vw;
  min-height: 100vh;
}
.app-wrapper > * {
  min-width: 0;
}

.app-content {
  padding: 0 0.5rem;
}

.app-header {
  z-index: 1034;
  grid-area: lte-app-header;
  max-width: 100vw;
  border-bottom: 1px solid var(--bs-border-color);
  transition: 0.3s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .app-header {
    transition: none;
  }
}
.app-header .nav-link {
  position: relative;
  height: 2.5rem;
}

.navbar-badge {
  position: absolute;
  top: 9px;
  right: 5px;
  padding: 2px 4px;
  font-size: 0.6rem;
  font-weight: 400;
}

.fixed-header .app-header {
  position: sticky;
  top: 0;
  z-index: 1030;
}

.app-sidebar {
  --lte-sidebar-hover-bg: rgba(0, 0, 0, 0.1);
  --lte-sidebar-color: #343a40;
  --lte-sidebar-hover-color: #212529;
  --lte-sidebar-active-color: #000;
  --lte-sidebar-menu-active-bg: rgba(0, 0, 0, 0.1);
  --lte-sidebar-menu-active-color: #000;
  --lte-sidebar-submenu-bg: transparent;
  --lte-sidebar-submenu-color: #777;
  --lte-sidebar-submenu-hover-color: #000;
  --lte-sidebar-submenu-hover-bg: rgba(0, 0, 0, 0.1);
  --lte-sidebar-submenu-active-color: #212529;
  --lte-sidebar-submenu-active-bg: rgba(0, 0, 0, 0.1);
  --lte-sidebar-header-color: rgb(49.4, 55.1, 60.8);
  z-index: 1038;
  grid-area: lte-app-sidebar;
  min-width: var(--lte-sidebar-width);
  max-width: var(--lte-sidebar-width);
  transition: min-width 0.3s ease-in-out, max-width 0.3s ease-in-out, margin-left 0.3s ease-in-out, margin-right 0.3s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .app-sidebar {
    transition: none;
  }
}

.sidebar-brand {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 3.5rem;
  padding: 0.8125rem 0.5rem;
  overflow: hidden;
  font-size: 1.25rem;
  white-space: nowrap;
  border-bottom: 1px solid var(--bs-border-color);
  transition: width 0.3s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .sidebar-brand {
    transition: none;
  }
}
.sidebar-brand .brand-link {
  display: flex;
  align-items: center;
  text-decoration: none;
}
.sidebar-brand .brand-link .brand-image {
  float: left;
  width: auto;
  max-height: 33px;
  line-height: 0.8;
}
.sidebar-brand .brand-link .brand-image-xs {
  float: left;
  width: auto;
  max-height: 33px;
  margin-top: -0.1rem;
  line-height: 0.8;
}
.sidebar-brand .brand-link .brand-image-xl {
  width: auto;
  max-height: 40px;
  line-height: 0.8;
}
.sidebar-brand .brand-link .brand-image-xl.single {
  margin-top: -0.3rem;
}
.sidebar-brand .brand-text {
  margin-left: 0.5rem;
  color: rgba(var(--bs-emphasis-color-rgb), 0.8);
  transition: flex 0.3s ease-in-out, width 0.3s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .sidebar-brand .brand-text {
    transition: none;
  }
}
.sidebar-brand .brand-text:hover {
  color: var(--bs-emphasis-color);
}

.sidebar-wrapper {
  padding-top: 0.5rem;
  padding-right: 0.5rem;
  padding-bottom: 0.5rem;
  padding-left: 0.5rem;
  scrollbar-color: var(--bs-secondary-bg) transparent;
  scrollbar-width: thin;
}
.sidebar-wrapper::-webkit-scrollbar-thumb {
  background-color: var(--bs-secondary-bg);
}
.sidebar-wrapper::-webkit-scrollbar-track {
  background-color: transparent;
}
.sidebar-wrapper::-webkit-scrollbar-corner {
  background-color: transparent;
}
.sidebar-wrapper::-webkit-scrollbar {
  width: 0.5rem;
  height: 0.5rem;
}
.sidebar-wrapper .nav-item {
  max-width: 100%;
}
.sidebar-wrapper .nav-link {
  display: flex;
  justify-content: flex-start;
}
.sidebar-wrapper .nav-link p {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sidebar-wrapper .nav-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 1.5rem;
  max-width: 1.5rem;
}
.sidebar-wrapper .sidebar-menu > .nav-item.menu-open .nav-link.active:not(:hover) {
  --lte-sidebar-menu-active-bg: transparent;
}
.sidebar-wrapper .sidebar-menu > .nav-item > .nav-link:active, .sidebar-wrapper .sidebar-menu > .nav-item > .nav-link:focus {
  color: var(--lte-sidebar-color);
}
.sidebar-wrapper .sidebar-menu > .nav-item > .nav-link.active:not(:hover) {
  color: var(--lte-sidebar-menu-active-color);
  background-color: var(--lte-sidebar-menu-active-bg);
}
.sidebar-wrapper .sidebar-menu > .nav-item.menu-open > .nav-link, .sidebar-wrapper .sidebar-menu > .nav-item:hover > .nav-link,
.sidebar-wrapper .sidebar-menu > .nav-item > .nav-link:focus {
  color: var(--lte-sidebar-hover-color);
  background-color: var(--lte-sidebar-hover-bg);
}
.sidebar-wrapper .sidebar-menu > .nav-item > .nav-treeview {
  background-color: var(--lte-sidebar-submenu-bg);
}
.sidebar-wrapper .nav-header {
  color: var(--lte-sidebar-header-color);
  background-color: inherit;
}
.sidebar-wrapper a {
  color: var(--lte-sidebar-color);
}
.sidebar-wrapper .nav-treeview > .nav-item > .nav-link {
  color: var(--lte-sidebar-submenu-color);
}
.sidebar-wrapper .nav-treeview > .nav-item > .nav-link:hover, .sidebar-wrapper .nav-treeview > .nav-item > .nav-link:focus {
  color: var(--lte-sidebar-submenu-hover-color);
}
.sidebar-wrapper .nav-treeview > .nav-item > .nav-link.active, .sidebar-wrapper .nav-treeview > .nav-item > .nav-link.active:hover, .sidebar-wrapper .nav-treeview > .nav-item > .nav-link.active:focus {
  color: var(--lte-sidebar-submenu-active-color);
  background-color: var(--lte-sidebar-submenu-active-bg);
}
.sidebar-wrapper .nav-treeview > .nav-item > .nav-link:hover {
  background-color: var(--lte-sidebar-submenu-hover-bg);
}

.sidebar-menu .nav-item > .nav-link {
  margin-bottom: 0.2rem;
}
.sidebar-menu .nav-item > .nav-link .nav-arrow {
  transition: transform ease-in-out 0.3s;
  transform: translateY(-50%) /*rtl:append:rotate(180deg)*/;
  animation-name: fadeIn;
  animation-duration: 0.3s;
  animation-fill-mode: both;
}
@media (prefers-reduced-motion: reduce) {
  .sidebar-menu .nav-item > .nav-link .nav-arrow {
    transition: none;
  }
}
.sidebar-menu .nav-link > .nav-badge,
.sidebar-menu .nav-link > p > .nav-badge {
  position: absolute;
  top: 50%;
  right: 1rem;
  transform: translateY(-50%);
}
.sidebar-menu .nav-link > .nav-arrow,
.sidebar-menu .nav-link > p > .nav-arrow {
  position: absolute;
  top: 50%;
  right: 1rem;
}
.sidebar-menu .nav-link {
  position: relative;
  width: 100%;
  transition: width ease-in-out 0.3s;
  border-radius: 0.375rem;
}
@media (prefers-reduced-motion: reduce) {
  .sidebar-menu .nav-link {
    transition: none;
  }
}
.sidebar-menu .nav-link p {
  display: inline;
  padding-left: 0.5rem;
  margin: 0;
}
.sidebar-menu .nav-header {
  padding: 0.5rem 0.75rem;
  font-size: 0.9rem;
}
.sidebar-menu .nav-treeview {
  display: none;
  padding: 0;
  list-style: none;
}
.nav-indent .sidebar-menu .nav-treeview {
  padding-left: 0.5rem;
}
.sidebar-menu .menu-open > .nav-treeview {
  display: block;
}
.sidebar-menu .menu-open > .nav-link .nav-arrow {
  transform: translateY(-50%) rotate(90deg) /*rtl:ignore*/;
}
.sidebar-menu .nav-link > .nav-badge,
.sidebar-menu .nav-link > p > .nav-badge,
.sidebar-menu .nav-link > .nav-arrow,
.sidebar-menu .nav-link > p > .nav-arrow {
  right: 1rem !important;
  left: auto !important;
}

.nav-compact.nav-indent .nav-treeview {
  padding-left: 0;
}
.nav-compact.nav-indent .nav-treeview .nav-item {
  padding-left: 0.5rem;
}

.sidebar-mini.sidebar-collapse.nav-indent .app-sidebar:hover .nav-treeview {
  padding-left: 0;
}
.sidebar-mini.sidebar-collapse.nav-indent .app-sidebar:hover .nav-treeview .nav-item {
  padding-left: 0.5rem;
}

.sidebar-collapse.nav-compact.nav-indent .nav-treeview .nav-item {
  padding-left: 0;
}

.nav-compact .nav-link {
  border-radius: 0;
  margin-bottom: 0 !important;
}

.sidebar-menu,
.sidebar-menu > .nav-header,
.sidebar-menu .nav-link {
  white-space: nowrap;
}

.logo-xs,
.logo-xl {
  position: absolute;
  visibility: visible;
  opacity: 1;
}
.logo-xs.brand-image-xs,
.logo-xl.brand-image-xs {
  top: 12px;
  left: 18px;
}
.logo-xs.brand-image-xl,
.logo-xl.brand-image-xl {
  top: 6px;
  left: 12px;
}

.logo-xs {
  visibility: hidden;
  opacity: 0;
}
.logo-xs.brand-image-xl {
  top: 8px;
  left: 16px;
}

.brand-link.logo-switch::before {
  content: " ";
}

.sidebar-mini.sidebar-collapse .app-sidebar {
  min-width: 4.6rem;
  max-width: 4.6rem;
}
.sidebar-mini.sidebar-collapse .sidebar-menu .nav-header {
  display: none;
}
.sidebar-mini.sidebar-collapse .sidebar-menu .nav-link {
  width: 3.6rem;
}
.sidebar-mini.sidebar-collapse .sidebar-menu .nav-link p {
  display: inline-block;
  width: 0;
  white-space: nowrap;
}
.sidebar-mini.sidebar-collapse .sidebar-menu .nav-badge,
.sidebar-mini.sidebar-collapse .sidebar-menu .nav-arrow {
  display: none;
  animation-name: fadeOut;
  animation-duration: 0.3s;
  animation-fill-mode: both;
}
.sidebar-mini.sidebar-collapse .brand-text {
  display: inline-block;
  max-width: 0;
  overflow: hidden;
}
.sidebar-mini.sidebar-collapse .sidebar-menu .nav-link p,
.sidebar-mini.sidebar-collapse .brand-text,
.sidebar-mini.sidebar-collapse .logo-xl,
.sidebar-mini.sidebar-collapse .nav-arrow {
  visibility: hidden;
  animation-name: fadeOut;
  animation-duration: 0.3s;
  animation-fill-mode: both;
}
.sidebar-mini.sidebar-collapse .logo-xs {
  display: inline-block;
  visibility: visible;
  animation-name: fadeIn;
  animation-duration: 0.3s;
  animation-fill-mode: both;
}
.sidebar-mini.sidebar-collapse .app-sidebar:hover {
  min-width: var(--lte-sidebar-width);
  max-width: var(--lte-sidebar-width);
}
.sidebar-mini.sidebar-collapse .app-sidebar:hover .sidebar-menu .nav-header {
  display: inline-block;
}
.sidebar-mini.sidebar-collapse .app-sidebar:hover .sidebar-menu .nav-link {
  width: auto;
}
.sidebar-mini.sidebar-collapse .app-sidebar:hover .sidebar-menu .nav-link p,
.sidebar-mini.sidebar-collapse .app-sidebar:hover .brand-text,
.sidebar-mini.sidebar-collapse .app-sidebar:hover .logo-xl {
  width: auto;
  margin-left: 0;
  visibility: visible;
  animation-name: fadeIn;
  animation-duration: 0.3s;
  animation-fill-mode: both;
}
.sidebar-mini.sidebar-collapse .app-sidebar:hover .brand-text {
  display: inline;
  max-width: inherit;
  margin-left: 0.5rem;
  animation-name: fadeIn;
  animation-duration: 0.3s;
  animation-fill-mode: both;
}
.sidebar-mini.sidebar-collapse .app-sidebar:hover .nav-badge,
.sidebar-mini.sidebar-collapse .app-sidebar:hover .nav-arrow {
  display: inline-block;
  visibility: visible;
  animation-name: fadeIn;
  animation-duration: 0.3s;
  animation-fill-mode: both;
  animation-delay: 0.3s;
}
.sidebar-mini.sidebar-collapse .app-sidebar:hover .nav-link p {
  padding-left: 0.5rem;
}
.sidebar-mini.sidebar-collapse .app-sidebar:hover .logo-xs {
  visibility: hidden;
  animation-name: fadeOut;
  animation-duration: 0.3s;
  animation-fill-mode: both;
}

.sidebar-collapse:not(.sidebar-mini) .app-sidebar {
  margin-left: calc(var(--lte-sidebar-width) * -1);
}

.sidebar-expand {
  /* stylelint-disable-next-line scss/selector-no-union-class-name */
  /* stylelint-disable-next-line scss/selector-no-union-class-name */
  /* stylelint-disable-next-line scss/selector-no-union-class-name */
  /* stylelint-disable-next-line scss/selector-no-union-class-name */
  /* stylelint-disable-next-line scss/selector-no-union-class-name */
  /* stylelint-disable-next-line scss/selector-no-union-class-name */
}
@media (min-width: 576px) {
  .sidebar-expand-sm.layout-fixed .app-main-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }
  .sidebar-expand-sm.layout-fixed .app-sidebar-wrapper {
    position: relative;
  }
  .sidebar-expand-sm.layout-fixed .app-main {
    flex: 1 1 auto;
    overflow: auto;
  }
  .sidebar-expand-sm.layout-fixed .app-sidebar {
    position: sticky;
    top: 0;
    bottom: 0;
    max-height: 100vh;
  }
  .sidebar-expand-sm.layout-fixed .app-sidebar .sidebar-wrapper {
    height: calc(100vh - (calc(3.5rem + 1px)));
    overflow-x: hidden;
    overflow-y: auto;
  }
  .sidebar-expand-sm.sidebar-open .nav-link > .nav-badge,
  .sidebar-expand-sm.sidebar-open .nav-link > p > .nav-badge {
    animation-name: fadeIn;
    animation-duration: 0.3s;
    animation-fill-mode: both;
    animation-delay: 0.3s;
  }
  .sidebar-expand-sm.sidebar-open .nav-link > .nav-arrow,
  .sidebar-expand-sm.sidebar-open .nav-link > p > .nav-arrow {
    animation-name: fadeIn;
    animation-duration: 0.3s;
    animation-fill-mode: both;
    animation-delay: 0.3s;
  }
}
@media (max-width: 575.98px) {
  .sidebar-expand-sm::before {
    display: none;
    content: "575.98px";
  }
  .sidebar-expand-sm .app-sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    max-height: 100vh;
    margin-left: calc(var(--lte-sidebar-width) * -1);
  }
  .sidebar-expand-sm .app-sidebar .sidebar-wrapper {
    height: calc(100vh - (calc(3.5rem + 1px)));
    overflow-x: hidden;
    overflow-y: auto;
  }
  .sidebar-expand-sm.sidebar-open .app-sidebar {
    margin-left: 0;
  }
  .sidebar-expand-sm.sidebar-open .sidebar-overlay {
    position: absolute;
    inset: 0;
    z-index: 1037;
    width: 100%;
    height: 100%;
    cursor: pointer;
    visibility: visible;
    background-color: rgba(0, 0, 0, 0.2);
    animation-name: fadeIn;
    animation-fill-mode: both;
  }
}
@media (min-width: 768px) {
  .sidebar-expand-md.layout-fixed .app-main-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }
  .sidebar-expand-md.layout-fixed .app-sidebar-wrapper {
    position: relative;
  }
  .sidebar-expand-md.layout-fixed .app-main {
    flex: 1 1 auto;
    overflow: auto;
  }
  .sidebar-expand-md.layout-fixed .app-sidebar {
    position: sticky;
    top: 0;
    bottom: 0;
    max-height: 100vh;
  }
  .sidebar-expand-md.layout-fixed .app-sidebar .sidebar-wrapper {
    height: calc(100vh - (calc(3.5rem + 1px)));
    overflow-x: hidden;
    overflow-y: auto;
  }
  .sidebar-expand-md.sidebar-open .nav-link > .nav-badge,
  .sidebar-expand-md.sidebar-open .nav-link > p > .nav-badge {
    animation-name: fadeIn;
    animation-duration: 0.3s;
    animation-fill-mode: both;
    animation-delay: 0.3s;
  }
  .sidebar-expand-md.sidebar-open .nav-link > .nav-arrow,
  .sidebar-expand-md.sidebar-open .nav-link > p > .nav-arrow {
    animation-name: fadeIn;
    animation-duration: 0.3s;
    animation-fill-mode: both;
    animation-delay: 0.3s;
  }
}
@media (max-width: 767.98px) {
  .sidebar-expand-md::before {
    display: none;
    content: "767.98px";
  }
  .sidebar-expand-md .app-sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    max-height: 100vh;
    margin-left: calc(var(--lte-sidebar-width) * -1);
  }
  .sidebar-expand-md .app-sidebar .sidebar-wrapper {
    height: calc(100vh - (calc(3.5rem + 1px)));
    overflow-x: hidden;
    overflow-y: auto;
  }
  .sidebar-expand-md.sidebar-open .app-sidebar {
    margin-left: 0;
  }
  .sidebar-expand-md.sidebar-open .sidebar-overlay {
    position: absolute;
    inset: 0;
    z-index: 1037;
    width: 100%;
    height: 100%;
    cursor: pointer;
    visibility: visible;
    background-color: rgba(0, 0, 0, 0.2);
    animation-name: fadeIn;
    animation-fill-mode: both;
  }
}
@media (min-width: 992px) {
  .sidebar-expand-lg.layout-fixed .app-main-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }
  .sidebar-expand-lg.layout-fixed .app-sidebar-wrapper {
    position: relative;
  }
  .sidebar-expand-lg.layout-fixed .app-main {
    flex: 1 1 auto;
    overflow: auto;
  }
  .sidebar-expand-lg.layout-fixed .app-sidebar {
    position: sticky;
    top: 0;
    bottom: 0;
    max-height: 100vh;
  }
  .sidebar-expand-lg.layout-fixed .app-sidebar .sidebar-wrapper {
    height: calc(100vh - (calc(3.5rem + 1px)));
    overflow-x: hidden;
    overflow-y: auto;
  }
  .sidebar-expand-lg.sidebar-open .nav-link > .nav-badge,
  .sidebar-expand-lg.sidebar-open .nav-link > p > .nav-badge {
    animation-name: fadeIn;
    animation-duration: 0.3s;
    animation-fill-mode: both;
    animation-delay: 0.3s;
  }
  .sidebar-expand-lg.sidebar-open .nav-link > .nav-arrow,
  .sidebar-expand-lg.sidebar-open .nav-link > p > .nav-arrow {
    animation-name: fadeIn;
    animation-duration: 0.3s;
    animation-fill-mode: both;
    animation-delay: 0.3s;
  }
}
@media (max-width: 991.98px) {
  .sidebar-expand-lg::before {
    display: none;
    content: "991.98px";
  }
  .sidebar-expand-lg .app-sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    max-height: 100vh;
    margin-left: calc(var(--lte-sidebar-width) * -1);
  }
  .sidebar-expand-lg .app-sidebar .sidebar-wrapper {
    height: calc(100vh - (calc(3.5rem + 1px)));
    overflow-x: hidden;
    overflow-y: auto;
  }
  .sidebar-expand-lg.sidebar-open .app-sidebar {
    margin-left: 0;
  }
  .sidebar-expand-lg.sidebar-open .sidebar-overlay {
    position: absolute;
    inset: 0;
    z-index: 1037;
    width: 100%;
    height: 100%;
    cursor: pointer;
    visibility: visible;
    background-color: rgba(0, 0, 0, 0.2);
    animation-name: fadeIn;
    animation-fill-mode: both;
  }
}
@media (min-width: 1200px) {
  .sidebar-expand-xl.layout-fixed .app-main-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }
  .sidebar-expand-xl.layout-fixed .app-sidebar-wrapper {
    position: relative;
  }
  .sidebar-expand-xl.layout-fixed .app-main {
    flex: 1 1 auto;
    overflow: auto;
  }
  .sidebar-expand-xl.layout-fixed .app-sidebar {
    position: sticky;
    top: 0;
    bottom: 0;
    max-height: 100vh;
  }
  .sidebar-expand-xl.layout-fixed .app-sidebar .sidebar-wrapper {
    height: calc(100vh - (calc(3.5rem + 1px)));
    overflow-x: hidden;
    overflow-y: auto;
  }
  .sidebar-expand-xl.sidebar-open .nav-link > .nav-badge,
  .sidebar-expand-xl.sidebar-open .nav-link > p > .nav-badge {
    animation-name: fadeIn;
    animation-duration: 0.3s;
    animation-fill-mode: both;
    animation-delay: 0.3s;
  }
  .sidebar-expand-xl.sidebar-open .nav-link > .nav-arrow,
  .sidebar-expand-xl.sidebar-open .nav-link > p > .nav-arrow {
    animation-name: fadeIn;
    animation-duration: 0.3s;
    animation-fill-mode: both;
    animation-delay: 0.3s;
  }
}
@media (max-width: 1199.98px) {
  .sidebar-expand-xl::before {
    display: none;
    content: "1199.98px";
  }
  .sidebar-expand-xl .app-sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    max-height: 100vh;
    margin-left: calc(var(--lte-sidebar-width) * -1);
  }
  .sidebar-expand-xl .app-sidebar .sidebar-wrapper {
    height: calc(100vh - (calc(3.5rem + 1px)));
    overflow-x: hidden;
    overflow-y: auto;
  }
  .sidebar-expand-xl.sidebar-open .app-sidebar {
    margin-left: 0;
  }
  .sidebar-expand-xl.sidebar-open .sidebar-overlay {
    position: absolute;
    inset: 0;
    z-index: 1037;
    width: 100%;
    height: 100%;
    cursor: pointer;
    visibility: visible;
    background-color: rgba(0, 0, 0, 0.2);
    animation-name: fadeIn;
    animation-fill-mode: both;
  }
}
@media (min-width: 1400px) {
  .sidebar-expand-xxl.layout-fixed .app-main-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }
  .sidebar-expand-xxl.layout-fixed .app-sidebar-wrapper {
    position: relative;
  }
  .sidebar-expand-xxl.layout-fixed .app-main {
    flex: 1 1 auto;
    overflow: auto;
  }
  .sidebar-expand-xxl.layout-fixed .app-sidebar {
    position: sticky;
    top: 0;
    bottom: 0;
    max-height: 100vh;
  }
  .sidebar-expand-xxl.layout-fixed .app-sidebar .sidebar-wrapper {
    height: calc(100vh - (calc(3.5rem + 1px)));
    overflow-x: hidden;
    overflow-y: auto;
  }
  .sidebar-expand-xxl.sidebar-open .nav-link > .nav-badge,
  .sidebar-expand-xxl.sidebar-open .nav-link > p > .nav-badge {
    animation-name: fadeIn;
    animation-duration: 0.3s;
    animation-fill-mode: both;
    animation-delay: 0.3s;
  }
  .sidebar-expand-xxl.sidebar-open .nav-link > .nav-arrow,
  .sidebar-expand-xxl.sidebar-open .nav-link > p > .nav-arrow {
    animation-name: fadeIn;
    animation-duration: 0.3s;
    animation-fill-mode: both;
    animation-delay: 0.3s;
  }
}
@media (max-width: 1399.98px) {
  .sidebar-expand-xxl::before {
    display: none;
    content: "1399.98px";
  }
  .sidebar-expand-xxl .app-sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    max-height: 100vh;
    margin-left: calc(var(--lte-sidebar-width) * -1);
  }
  .sidebar-expand-xxl .app-sidebar .sidebar-wrapper {
    height: calc(100vh - (calc(3.5rem + 1px)));
    overflow-x: hidden;
    overflow-y: auto;
  }
  .sidebar-expand-xxl.sidebar-open .app-sidebar {
    margin-left: 0;
  }
  .sidebar-expand-xxl.sidebar-open .sidebar-overlay {
    position: absolute;
    inset: 0;
    z-index: 1037;
    width: 100%;
    height: 100%;
    cursor: pointer;
    visibility: visible;
    background-color: rgba(0, 0, 0, 0.2);
    animation-name: fadeIn;
    animation-fill-mode: both;
  }
}
.sidebar-expand.layout-fixed .app-main-wrapper {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}
.sidebar-expand.layout-fixed .app-sidebar-wrapper {
  position: relative;
}
.sidebar-expand.layout-fixed .app-main {
  flex: 1 1 auto;
  overflow: auto;
}
.sidebar-expand.layout-fixed .app-sidebar {
  position: sticky;
  top: 0;
  bottom: 0;
  max-height: 100vh;
}
.sidebar-expand.layout-fixed .app-sidebar .sidebar-wrapper {
  height: calc(100vh - (calc(3.5rem + 1px)));
  overflow-x: hidden;
  overflow-y: auto;
}
.sidebar-expand.sidebar-open .nav-link > .nav-badge,
.sidebar-expand.sidebar-open .nav-link > p > .nav-badge {
  animation-name: fadeIn;
  animation-duration: 0.3s;
  animation-fill-mode: both;
  animation-delay: 0.3s;
}
.sidebar-expand.sidebar-open .nav-link > .nav-arrow,
.sidebar-expand.sidebar-open .nav-link > p > .nav-arrow {
  animation-name: fadeIn;
  animation-duration: 0.3s;
  animation-fill-mode: both;
  animation-delay: 0.3s;
}
.sidebar-expand::before {
  display: none;
  content: "";
}
.sidebar-expand .app-sidebar {
  position: fixed;
  top: 0;
  bottom: 0;
  max-height: 100vh;
  margin-left: calc(var(--lte-sidebar-width) * -1);
}
.sidebar-expand .app-sidebar .sidebar-wrapper {
  height: calc(100vh - (calc(3.5rem + 1px)));
  overflow-x: hidden;
  overflow-y: auto;
}
.sidebar-expand.sidebar-open .app-sidebar {
  margin-left: 0;
}
.sidebar-expand.sidebar-open .sidebar-overlay {
  position: absolute;
  inset: 0;
  z-index: 1037;
  width: 100%;
  height: 100%;
  cursor: pointer;
  visibility: visible;
  background-color: rgba(0, 0, 0, 0.2);
  animation-name: fadeIn;
  animation-fill-mode: both;
}

.sidebar-menu .nav-link p,
.app-sidebar .brand-text,
.app-sidebar .logo-xs,
.app-sidebar .logo-xl {
  transition: margin-left 0.3s linear, opacity 0.3s ease, visibility 0.3s ease;
}
@media (prefers-reduced-motion: reduce) {
  .sidebar-menu .nav-link p,
  .app-sidebar .brand-text,
  .app-sidebar .logo-xs,
  .app-sidebar .logo-xl {
    transition: none;
  }
}

.app-loaded.sidebar-mini.sidebar-collapse .sidebar-menu .nav-link p,
.app-loaded.sidebar-mini.sidebar-collapse .brand-text {
  animation-duration: 0.3s;
}

body:not(.app-loaded) .app-header,
body:not(.app-loaded) .app-sidebar,
body:not(.app-loaded) .app-main,
body:not(.app-loaded) .app-footer {
  transition: none !important;
  animation-duration: 0s !important;
}
@media (prefers-reduced-motion: reduce) {
  body:not(.app-loaded) .app-header,
  body:not(.app-loaded) .app-sidebar,
  body:not(.app-loaded) .app-main,
  body:not(.app-loaded) .app-footer {
    transition: none;
  }
}

.hold-transition .app-header,
.hold-transition .app-sidebar,
.hold-transition .app-main,
.hold-transition .app-footer,
.hold-transition .nav-arrow,
.hold-transition .nav-badge {
  transition: none !important;
  animation-duration: 0s !important;
}
@media (prefers-reduced-motion: reduce) {
  .hold-transition .app-header,
  .hold-transition .app-sidebar,
  .hold-transition .app-main,
  .hold-transition .app-footer,
  .hold-transition .nav-arrow,
  .hold-transition .nav-badge {
    transition: none;
  }
}

[data-bs-theme=dark].app-sidebar,
[data-bs-theme=dark] .app-sidebar {
  --lte-sidebar-hover-bg: rgba(255, 255, 255, 0.1);
  --lte-sidebar-color: #c2c7d0;
  --lte-sidebar-hover-color: #fff;
  --lte-sidebar-active-color: #fff;
  --lte-sidebar-menu-active-bg: rgba(255, 255, 255, 0.1);
  --lte-sidebar-menu-active-color: #fff;
  --lte-sidebar-submenu-bg: transparent;
  --lte-sidebar-submenu-color: #c2c7d0;
  --lte-sidebar-submenu-hover-color: #fff;
  --lte-sidebar-submenu-hover-bg: rgba(255, 255, 255, 0.1);
  --lte-sidebar-submenu-active-color: #fff;
  --lte-sidebar-submenu-active-bg: rgba(255, 255, 255, 0.1);
  --lte-sidebar-header-color: rgb(197.05, 201.8, 210.35);
}

.app-main {
  position: relative;
  display: flex;
  flex-direction: column;
  grid-area: lte-app-main;
  max-width: 100vw;
  padding-bottom: 0.75rem;
  transition: 0.3s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .app-main {
    transition: none;
  }
}
.app-main .app-content-header {
  padding: 1rem 0.5rem;
}
.app-main .app-content-header .breadcrumb {
  padding: 0;
  margin-bottom: 0;
  line-height: 2.5rem;
}
.app-main .app-content-header .breadcrumb a {
  text-decoration: none;
}
.app-main .app-content-top-area,
.app-main .app-content-bottom-area {
  color: var(--bs-secondary-color);
  background-color: var(--bs-body-bg);
}
.app-main .app-content-top-area {
  padding: 1rem 0;
  border-bottom: 1px solid var(--bs-border-color);
}
.app-main .app-content-bottom-area {
  padding: 1rem 0;
  margin-top: auto;
  margin-bottom: -0.75rem;
  border-top: 1px solid var(--bs-border-color);
}

.app-footer {
  grid-area: lte-app-footer;
  width: inherit;
  max-width: 100vw;
  min-height: 3rem;
  padding: 1rem;
  color: var(--bs-secondary-color);
  background-color: var(--bs-body-bg);
  border-top: 1px solid var(--bs-border-color);
  transition: 0.3s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .app-footer {
    transition: none;
  }
}

.fixed-footer .app-footer {
  position: sticky;
  bottom: 0;
  z-index: 1030;
}

.fs-7 .dropdown-menu {
  font-size: 0.875rem !important;
}
.fs-7 .dropdown-toggle::after {
  vertical-align: 0.2rem;
}

.dropdown-item-title {
  margin: 0;
  font-size: 1rem;
}

.dropdown-icon::after {
  margin-left: 0;
}

.dropdown-menu-lg {
  min-width: 280px;
  max-width: 300px;
  padding: 0;
}
.dropdown-menu-lg .dropdown-divider {
  margin: 0;
}
.dropdown-menu-lg .dropdown-item {
  padding: 0.5rem 1rem;
}
.dropdown-menu-lg p {
  margin: 0;
  word-wrap: break-word;
  white-space: normal;
}

.dropdown-submenu {
  position: relative;
}
.dropdown-submenu > a::after {
  border-top: 0.3em solid transparent;
  border-right: 0;
  border-bottom: 0.3em solid transparent;
  border-left: 0.3em solid;
  float: right;
  margin-top: 0.5rem;
  margin-left: 0.5rem;
}
.dropdown-submenu > .dropdown-menu {
  top: 0;
  left: 100%;
  margin-top: 0;
  margin-left: 0;
}

.dropdown-hover:hover > .dropdown-menu, .dropdown-hover.nav-item.dropdown:hover > .dropdown-menu,
.dropdown-hover .dropdown-submenu:hover > .dropdown-menu, .dropdown-hover.dropdown-submenu:hover > .dropdown-menu {
  display: block;
}

.dropdown-menu-xl {
  min-width: 360px;
  max-width: 420px;
  padding: 0;
}
.dropdown-menu-xl .dropdown-divider {
  margin: 0;
}
.dropdown-menu-xl .dropdown-item {
  padding: 0.5rem 1rem;
}
.dropdown-menu-xl p {
  margin: 0;
  word-wrap: break-word;
  white-space: normal;
}

.dropdown-footer,
.dropdown-header {
  display: block;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  text-align: center;
}

.open:not(.dropup) > .animated-dropdown-menu {
  animation: flipInX 0.7s both;
  backface-visibility: visible !important;
}

.navbar-custom-menu > .navbar-nav > li {
  position: relative;
}
.navbar-custom-menu > .navbar-nav > li > .dropdown-menu {
  position: absolute;
  right: 0;
  left: auto;
}

@media (max-width: 575.98px) {
  .navbar-custom-menu > .navbar-nav {
    float: right;
  }
  .navbar-custom-menu > .navbar-nav > li {
    position: static;
  }
  .navbar-custom-menu > .navbar-nav > li > .dropdown-menu {
    position: absolute;
    right: 5%;
    left: auto;
    background-color: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
  }
}
.navbar-nav > .user-menu > .nav-link::after {
  content: none;
}
.navbar-nav > .user-menu > .dropdown-menu {
  border-top-left-radius: 0;
  border-top-right-radius: 0;
  width: 280px;
  padding: 0;
}
.navbar-nav > .user-menu > .dropdown-menu,
.navbar-nav > .user-menu > .dropdown-menu > .user-body {
  border-bottom-right-radius: 4px;
  border-bottom-left-radius: 4px;
}
.navbar-nav > .user-menu > .dropdown-menu > li.user-header {
  min-height: 175px;
  padding: 10px;
  text-align: center;
}
.navbar-nav > .user-menu > .dropdown-menu > li.user-header > img {
  z-index: 5;
  width: 90px;
  height: 90px;
  border: 3px solid;
  border-color: transparent;
  border-color: var(--bs-border-color-translucent);
}
.navbar-nav > .user-menu > .dropdown-menu > li.user-header > p {
  z-index: 5;
  margin-top: 10px;
  font-size: 17px;
  word-wrap: break-word;
}
.navbar-nav > .user-menu > .dropdown-menu > li.user-header > p > small, .navbar-nav > .user-menu > .dropdown-menu > li.user-header > p > .small {
  display: block;
  font-size: 12px;
}
.navbar-nav > .user-menu > .dropdown-menu > .user-body {
  padding: 15px;
  border-top: 1px solid var(--bs-border-color);
  border-bottom: 1px solid var(--bs-border-color-translucent);
}
.navbar-nav > .user-menu > .dropdown-menu > .user-body::after {
  display: block;
  clear: both;
  content: "";
}
.navbar-nav > .user-menu > .dropdown-menu > .user-body a {
  text-decoration: none;
}
@media (min-width: 576px) {
  .navbar-nav > .user-menu > .dropdown-menu > .user-body a {
    color: var(--bs-body-color) !important;
    background-color: var(--bs-body-bg) !important;
  }
}
.navbar-nav > .user-menu > .dropdown-menu > .user-footer {
  padding: 10px;
  background-color: var(--bs-light-bg);
}
.navbar-nav > .user-menu > .dropdown-menu > .user-footer::after {
  display: block;
  clear: both;
  content: "";
}
.navbar-nav > .user-menu > .dropdown-menu > .user-footer .btn-default {
  color: var(--bs-body-color);
}
@media (min-width: 576px) {
  .navbar-nav > .user-menu > .dropdown-menu > .user-footer .btn-default:hover {
    background-color: var(--bs-body-bg);
  }
}
.navbar-nav > .user-menu .user-image {
  float: left;
  width: 2rem;
  height: 2rem;
  margin-top: -2px;
  border-radius: 50%;
}
@media (min-width: 576px) {
  .navbar-nav > .user-menu .user-image {
    float: none;
    margin-top: -8px;
    margin-right: 0.4rem;
    line-height: 10px;
  }
}

.callout {
  --bs-link-color-rgb: var(--lte-callout-link);
  --bs-code-color: var(--lte-callout-code-color);
  padding: 1.25rem;
  color: var(--lte-callout-color, inherit);
  background-color: var(--lte-callout-bg, var(--bs-gray-100));
  border-left: 0.25rem solid var(--lte-callout-border, var(--bs-gray-300));
}
.callout .callout-link {
  font-weight: 700;
  color: var(--bs-callout-link-color);
}
.callout h4, .callout .h4 {
  margin-bottom: 0.25rem;
}
.callout > :last-child {
  margin-bottom: 0;
}
.callout + .callout {
  margin-top: -0.25rem;
}

.callout-primary {
  --lte-callout-color: var(--bs-primary-text-emphasis);
  --lte-callout-bg: var(--bs-primary-bg-subtle);
  --lte-callout-border: var(--bs-primary-border-subtle);
  --bs-callout-link-color: var(--bs-primary-text-emphasis);
}

.callout-secondary {
  --lte-callout-color: var(--bs-secondary-text-emphasis);
  --lte-callout-bg: var(--bs-secondary-bg-subtle);
  --lte-callout-border: var(--bs-secondary-border-subtle);
  --bs-callout-link-color: var(--bs-secondary-text-emphasis);
}

.callout-success {
  --lte-callout-color: var(--bs-success-text-emphasis);
  --lte-callout-bg: var(--bs-success-bg-subtle);
  --lte-callout-border: var(--bs-success-border-subtle);
  --bs-callout-link-color: var(--bs-success-text-emphasis);
}

.callout-info {
  --lte-callout-color: var(--bs-info-text-emphasis);
  --lte-callout-bg: var(--bs-info-bg-subtle);
  --lte-callout-border: var(--bs-info-border-subtle);
  --bs-callout-link-color: var(--bs-info-text-emphasis);
}

.callout-warning {
  --lte-callout-color: var(--bs-warning-text-emphasis);
  --lte-callout-bg: var(--bs-warning-bg-subtle);
  --lte-callout-border: var(--bs-warning-border-subtle);
  --bs-callout-link-color: var(--bs-warning-text-emphasis);
}

.callout-danger {
  --lte-callout-color: var(--bs-danger-text-emphasis);
  --lte-callout-bg: var(--bs-danger-bg-subtle);
  --lte-callout-border: var(--bs-danger-border-subtle);
  --bs-callout-link-color: var(--bs-danger-text-emphasis);
}

.callout-light {
  --lte-callout-color: var(--bs-light-text-emphasis);
  --lte-callout-bg: var(--bs-light-bg-subtle);
  --lte-callout-border: var(--bs-light-border-subtle);
  --bs-callout-link-color: var(--bs-light-text-emphasis);
}

.callout-dark {
  --lte-callout-color: var(--bs-dark-text-emphasis);
  --lte-callout-bg: var(--bs-dark-bg-subtle);
  --lte-callout-border: var(--bs-dark-border-subtle);
  --bs-callout-link-color: var(--bs-dark-text-emphasis);
}

.compact-mode .app-header {
  max-height: 2.75rem;
}
.compact-mode .app-header .nav-link {
  max-height: 1.75rem;
}
.compact-mode .nav-link {
  --bs-nav-link-padding-y: .25rem;
  --bs-nav-link-padding-x: .5rem;
}
.compact-mode.sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) {
  min-width: 3.1rem;
  max-width: 3.1rem;
}
.compact-mode.sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .sidebar-menu .nav-link {
  width: 2.1rem !important;
}
.compact-mode .logo-xs,
.compact-mode .logo-xl {
  max-height: 2.75rem;
}
.compact-mode .brand-image {
  width: 1.75rem;
  height: 1.75rem;
}
.compact-mode .sidebar-brand {
  height: 2.75rem;
}
.compact-mode .app-footer {
  padding: 0.5rem;
}
.compact-mode .sidebar-wrapper .nav-icon {
  min-width: 1.1rem;
  max-width: 1.1rem;
}

.astro-code {
  padding: 0.75rem;
  border-radius: 0.375rem;
}

.progress {
  border-radius: 1px;
}
.progress.vertical {
  position: relative;
  display: inline-block;
  width: 30px;
  height: 200px;
  margin-right: 10px;
}
.progress.vertical > .progress-bar {
  position: absolute;
  bottom: 0;
  width: 100%;
}
.progress.vertical.sm, .progress.vertical.progress-sm {
  width: 20px;
}
.progress.vertical.xs, .progress.vertical.progress-xs {
  width: 10px;
}
.progress.vertical.xxs, .progress.vertical.progress-xxs {
  width: 3px;
}

.progress-group {
  margin-bottom: 0.5rem;
}

.progress-sm {
  height: 10px;
}

.progress-xs {
  height: 7px;
}

.progress-xxs {
  height: 3px;
}

.table tr > td .progress {
  margin: 0;
}

.card {
  box-shadow: 0 0 1px rgba(var(--bs-body-color-rgb), 0.125), 0 1px 3px rgba(var(--bs-body-color-rgb), 0.2);
}
.card[class*=card-]:not(.card-outline) > .card-header, .card[class*=text-bg-]:not(.card-outline) > .card-header {
  color: var(--lte-card-variant-color);
  background-color: var(--lte-card-variant-bg);
}
.card[class*=card-]:not(.card-outline) > .card-header .btn-tool, .card[class*=text-bg-]:not(.card-outline) > .card-header .btn-tool {
  --bs-btn-color: rgba(var(--lte-card-variant-color-rgb), .8);
  --bs-btn-hover-color: var(--lte-card-variant-color);
}
.card.card-outline {
  border-top: 3px solid var(--lte-card-variant-bg);
}
.card.maximized-card {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 1050;
  width: 100% !important;
  max-width: 100% !important;
  height: 100% !important;
  max-height: 100% !important;
}
.card.maximized-card.was-collapsed .card-body {
  display: block !important;
}
.card.maximized-card .card-body {
  overflow: auto;
}
.card.maximized-card [data-lte-toggle=card-collapse] {
  display: none;
}
.card.maximized-card [data-lte-icon=maximize] {
  display: none;
}
.card.maximized-card .card-header,
.card.maximized-card .card-footer {
  border-radius: 0 !important;
}
.card:not(.maximized-card) [data-lte-icon=minimize] {
  display: none;
}
.card.collapsed-card [data-lte-icon=collapse] {
  display: none;
}
.card.collapsed-card .card-body,
.card.collapsed-card .card-footer {
  display: none;
}
.card:not(.collapsed-card) [data-lte-icon=expand] {
  display: none;
}
.card .nav.flex-column > li {
  margin: 0;
  border-bottom: 1px solid var(--bs-border-color-translucent);
}
.card .nav.flex-column > li:last-of-type {
  border-bottom: 0;
}
.card.height-control .card-body {
  max-height: 300px;
  overflow: auto;
}
.card .border-end {
  border-right: 1px solid var(--bs-border-color-translucent);
}
.card .border-start {
  border-left: 1px solid var(--bs-border-color-translucent);
}
.card.card-tabs:not(.card-outline) > .card-header {
  border-bottom: 0;
}
.card.card-tabs:not(.card-outline) > .card-header .nav-item:first-child .nav-link {
  border-left-color: transparent;
}
.card.card-tabs.card-outline .nav-item {
  border-bottom: 0;
}
.card.card-tabs.card-outline .nav-item:first-child .nav-link {
  margin-left: 0;
  border-left: 0;
}
.card.card-tabs .card-tools {
  margin: 0.3rem 0.5rem;
}
.card.card-tabs:not(.expanding-card).collapsed-card .card-header {
  border-bottom: 0;
}
.card.card-tabs:not(.expanding-card).collapsed-card .card-header .nav-tabs {
  border-bottom: 0;
}
.card.card-tabs:not(.expanding-card).collapsed-card .card-header .nav-tabs .nav-item {
  margin-bottom: 0;
}
.card.card-tabs.expanding-card .card-header .nav-tabs .nav-item {
  margin-bottom: -1px;
}
.card.card-outline-tabs {
  border-top: 0;
}
.card.card-outline-tabs .card-header .nav-item:first-child .nav-link {
  margin-left: 0;
  border-left: 0;
}
.card.card-outline-tabs .card-header a {
  text-decoration: none;
  border-top: 3px solid transparent;
}
.card.card-outline-tabs .card-header a:hover {
  border-top: 3px solid var(--bs-border-color);
}
.card.card-outline-tabs .card-header a.active:hover {
  margin-top: 0;
}
.card.card-outline-tabs .card-tools {
  margin: 0.5rem 0.5rem 0.3rem;
}
.card.card-outline-tabs:not(.expanding-card).collapsed-card .card-header {
  border-bottom: 0;
}
.card.card-outline-tabs:not(.expanding-card).collapsed-card .card-header .nav-tabs {
  border-bottom: 0;
}
.card.card-outline-tabs:not(.expanding-card).collapsed-card .card-header .nav-tabs .nav-item {
  margin-bottom: 0;
}
.card.card-outline-tabs.expanding-card .card-header .nav-tabs .nav-item {
  margin-bottom: -1px;
}

html.maximized-card {
  overflow: hidden;
}

.card-header::after,
.card-body::after,
.card-footer::after {
  display: block;
  clear: both;
  content: "";
}

.card-header {
  position: relative;
  padding: 1rem 1rem;
  background-color: transparent;
  border-bottom: 1px solid var(--bs-border-color-translucent);
  border-top-left-radius: 0.375rem;
  border-top-right-radius: 0.375rem;
}
.collapsed-card .card-header {
  border-bottom: 0;
}
.card-header > .card-tools {
  float: right;
  margin-right: -0.5rem;
}
.card-header > .card-tools .input-group,
.card-header > .card-tools .nav,
.card-header > .card-tools .pagination {
  margin-top: -0.4rem;
  margin-bottom: -0.4rem;
}
.card-header > .card-tools [data-bs-toggle=tooltip] {
  position: relative;
}

.card-title {
  float: left;
  margin: 0;
  font-size: 1.1rem;
  font-weight: 400;
}

.btn-tool {
  --bs-btn-padding-x: .5rem;
  --bs-btn-padding-y: .25rem;
  margin: -1rem 0;
  font-size: 0.875rem;
}
.btn-tool:not(.btn-tool-custom) {
  --bs-btn-color: var(--bs-tertiary-color);
  --bs-btn-bg: transparent;
  --bs-btn-box-shadow: none;
  --bs-btn-hover-color: var(--bs-secondary-color);
  --bs-btn-active-border-color: transparent;
}

.card-primary,
.bg-primary,
.text-bg-primary {
  --lte-card-variant-bg: #0d6efd;
  --lte-card-variant-bg-rgb: 13, 110, 253;
  --lte-card-variant-color: #fff;
  --lte-card-variant-color-rgb: 255, 255, 255;
}

.card-secondary,
.bg-secondary,
.text-bg-secondary {
  --lte-card-variant-bg: #6c757d;
  --lte-card-variant-bg-rgb: 108, 117, 125;
  --lte-card-variant-color: #fff;
  --lte-card-variant-color-rgb: 255, 255, 255;
}

.card-success,
.bg-success,
.text-bg-success {
  --lte-card-variant-bg: #198754;
  --lte-card-variant-bg-rgb: 25, 135, 84;
  --lte-card-variant-color: #fff;
  --lte-card-variant-color-rgb: 255, 255, 255;
}

.card-info,
.bg-info,
.text-bg-info {
  --lte-card-variant-bg: #0dcaf0;
  --lte-card-variant-bg-rgb: 13, 202, 240;
  --lte-card-variant-color: #000;
  --lte-card-variant-color-rgb: 0, 0, 0;
}

.card-warning,
.bg-warning,
.text-bg-warning {
  --lte-card-variant-bg: #ffc107;
  --lte-card-variant-bg-rgb: 255, 193, 7;
  --lte-card-variant-color: #000;
  --lte-card-variant-color-rgb: 0, 0, 0;
}

.card-danger,
.bg-danger,
.text-bg-danger {
  --lte-card-variant-bg: #dc3545;
  --lte-card-variant-bg-rgb: 220, 53, 69;
  --lte-card-variant-color: #fff;
  --lte-card-variant-color-rgb: 255, 255, 255;
}

.card-light,
.bg-light,
.text-bg-light {
  --lte-card-variant-bg: #333334;
  --lte-card-variant-bg-rgb: 248, 249, 250;
  --lte-card-variant-color: #000;
  --lte-card-variant-color-rgb: 0, 0, 0;
}

.card-dark,
.bg-dark,
.text-bg-dark {
  --lte-card-variant-bg: #212529;
  --lte-card-variant-bg-rgb: 33, 37, 41;
  --lte-card-variant-color: #fff;
  --lte-card-variant-color-rgb: 255, 255, 255;
}

.card-body > .table {
  margin-bottom: 0;
}
.card-body > .table > thead > tr > th,
.card-body > .table > thead > tr > td {
  border-top-width: 0;
}

.table:not(.table-dark) {
  color: inherit;
}
.table.table-head-fixed thead tr:nth-child(1) th {
  position: sticky;
  top: 0;
  z-index: 10;
  background-color: #fff;
  border-bottom: 0;
  box-shadow: inset 0 1px 0 var(--bs-border-color), inset 0 -1px 0 var(--bs-border-color);
}
.table.no-border,
.table.no-border td,
.table.no-border th {
  border: 0;
}
.table.text-center,
.table.text-center td,
.table.text-center th {
  text-align: center;
}
.table.table-valign-middle thead > tr > th,
.table.table-valign-middle thead > tr > td,
.table.table-valign-middle tbody > tr > th,
.table.table-valign-middle tbody > tr > td {
  vertical-align: middle;
}
.card-body.p-0 .table thead > tr > th:first-of-type,
.card-body.p-0 .table thead > tr > td:first-of-type,
.card-body.p-0 .table tfoot > tr > th:first-of-type,
.card-body.p-0 .table tfoot > tr > td:first-of-type,
.card-body.p-0 .table tbody > tr > th:first-of-type,
.card-body.p-0 .table tbody > tr > td:first-of-type {
  padding-left: 1.5rem;
}
.card-body.p-0 .table thead > tr > th:last-of-type,
.card-body.p-0 .table thead > tr > td:last-of-type,
.card-body.p-0 .table tfoot > tr > th:last-of-type,
.card-body.p-0 .table tfoot > tr > td:last-of-type,
.card-body.p-0 .table tbody > tr > th:last-of-type,
.card-body.p-0 .table tbody > tr > td:last-of-type {
  padding-right: 1.5rem;
}

.small-box {
  border-radius: 0.375rem;
  box-shadow: 0 0 1px rgba(var(--bs-body-color-rgb), 0.125), 0 1px 3px rgba(var(--bs-body-color-rgb), 0.2);
  position: relative;
  display: block;
  margin-bottom: 1.25rem;
  --bs-link-color-rgb: none;
  --bs-link-hover-color-rgb: none;
  --bs-heading-color: none;
}
.small-box > .inner {
  padding: 10px;
}
.small-box > .small-box-footer {
  position: relative;
  z-index: 10;
  display: block;
  padding: 3px 0;
  text-align: center;
  background-color: rgba(0, 0, 0, 0.07);
}
.small-box > .small-box-footer:hover {
  background-color: rgba(0, 0, 0, 0.1);
}
.small-box h3, .small-box .h3 {
  font-size: calc(1.345rem + 1.14vw);
  padding: 0;
  margin: 0 0 10px;
  font-weight: 700;
  white-space: nowrap;
}
@media (min-width: 1200px) {
  .small-box h3, .small-box .h3 {
    font-size: 2.2rem;
  }
}
@media (min-width: 992px) {
  .col-xl-2 .small-box h3, .col-xl-2 .small-box .h3, .col-lg-2 .small-box h3, .col-lg-2 .small-box .h3, .col-md-2 .small-box h3, .col-md-2 .small-box .h3 {
    font-size: calc(1.285rem + 0.42vw);
  }
}
@media (min-width: 992px) and (min-width: 1200px) {
  .col-xl-2 .small-box h3, .col-xl-2 .small-box .h3, .col-lg-2 .small-box h3, .col-lg-2 .small-box .h3, .col-md-2 .small-box h3, .col-md-2 .small-box .h3 {
    font-size: 1.6rem;
  }
}
@media (min-width: 992px) {
  .col-xl-3 .small-box h3, .col-xl-3 .small-box .h3, .col-lg-3 .small-box h3, .col-lg-3 .small-box .h3, .col-md-3 .small-box h3, .col-md-3 .small-box .h3 {
    font-size: calc(1.285rem + 0.42vw);
  }
}
@media (min-width: 992px) and (min-width: 1200px) {
  .col-xl-3 .small-box h3, .col-xl-3 .small-box .h3, .col-lg-3 .small-box h3, .col-lg-3 .small-box .h3, .col-md-3 .small-box h3, .col-md-3 .small-box .h3 {
    font-size: 1.6rem;
  }
}
@media (min-width: 1200px) {
  .col-xl-2 .small-box h3, .col-xl-2 .small-box .h3, .col-lg-2 .small-box h3, .col-lg-2 .small-box .h3, .col-md-2 .small-box h3, .col-md-2 .small-box .h3 {
    font-size: calc(1.345rem + 1.14vw);
  }
}
@media (min-width: 1200px) and (min-width: 1200px) {
  .col-xl-2 .small-box h3, .col-xl-2 .small-box .h3, .col-lg-2 .small-box h3, .col-lg-2 .small-box .h3, .col-md-2 .small-box h3, .col-md-2 .small-box .h3 {
    font-size: 2.2rem;
  }
}
@media (min-width: 1200px) {
  .col-xl-3 .small-box h3, .col-xl-3 .small-box .h3, .col-lg-3 .small-box h3, .col-lg-3 .small-box .h3, .col-md-3 .small-box h3, .col-md-3 .small-box .h3 {
    font-size: calc(1.345rem + 1.14vw);
  }
}
@media (min-width: 1200px) and (min-width: 1200px) {
  .col-xl-3 .small-box h3, .col-xl-3 .small-box .h3, .col-lg-3 .small-box h3, .col-lg-3 .small-box .h3, .col-md-3 .small-box h3, .col-md-3 .small-box .h3 {
    font-size: 2.2rem;
  }
}
.small-box p {
  font-size: 1rem;
}
.small-box p > small, .small-box p > .small {
  display: block;
  margin-top: 5px;
  font-size: 0.9rem;
  color: #f8f9fa;
}
.small-box h3, .small-box .h3,
.small-box p {
  z-index: 5;
}
.small-box .small-box-icon {
  position: absolute;
  top: 15px;
  right: 15px;
  z-index: 0;
  height: 70px;
  font-size: 70px;
  color: rgba(0, 0, 0, 0.15);
  transition: transform 0.3s linear;
}
@media (prefers-reduced-motion: reduce) {
  .small-box .small-box-icon {
    transition: none;
  }
}
.small-box:hover .small-box-icon {
  transform: scale(1.1);
}

@media (max-width: 575.98px) {
  .small-box {
    text-align: center;
  }
  .small-box .small-box-icon {
    display: none;
  }
  .small-box p {
    font-size: 12px;
  }
}
.info-box {
  box-shadow: 0 0 1px rgba(var(--bs-body-color-rgb), 0.125), 0 1px 3px rgba(var(--bs-body-color-rgb), 0.2);
  border-radius: 0.375rem;
  position: relative;
  display: flex;
  width: 100%;
  min-height: 80px;
  padding: 0.5rem;
  margin-bottom: 1rem;
  color: var(--bs-body-color);
  background-color: var(--bs-body-bg);
}
.info-box .progress {
  height: 2px;
  margin: 5px 0;
  background-color: rgba(var(--lte-card-variant-color-rgb), 0.125);
}
.info-box .progress .progress-bar {
  background-color: var(--lte-card-variant-color);
}
.info-box .info-box-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 70px;
  font-size: 1.875rem;
  text-align: center;
  border-radius: 0.375rem;
}
.info-box .info-box-icon > img {
  max-width: 100%;
}
.info-box .info-box-content {
  display: flex;
  flex: 1;
  flex-direction: column;
  justify-content: center;
  padding: 0 10px;
  line-height: 1.8;
}
.info-box .info-box-number {
  display: block;
  margin-top: 0.25rem;
  font-weight: 700;
}
.info-box .progress-description,
.info-box .info-box-text {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.info-box .info-box-more {
  display: block;
}
.info-box .progress-description {
  margin: 0;
}
@media (min-width: 768px) {
  .col-xl-2 .info-box .progress-description, .col-lg-2 .info-box .progress-description, .col-md-2 .info-box .progress-description {
    display: none;
  }
  .col-xl-3 .info-box .progress-description, .col-lg-3 .info-box .progress-description, .col-md-3 .info-box .progress-description {
    display: none;
  }
}
@media (min-width: 992px) {
  .col-xl-2 .info-box .progress-description, .col-lg-2 .info-box .progress-description, .col-md-2 .info-box .progress-description {
    font-size: 0.75rem;
    display: block;
  }
  .col-xl-3 .info-box .progress-description, .col-lg-3 .info-box .progress-description, .col-md-3 .info-box .progress-description {
    font-size: 0.75rem;
    display: block;
  }
}
@media (min-width: 1200px) {
  .col-xl-2 .info-box .progress-description, .col-lg-2 .info-box .progress-description, .col-md-2 .info-box .progress-description {
    font-size: 1rem;
    display: block;
  }
  .col-xl-3 .info-box .progress-description, .col-lg-3 .info-box .progress-description, .col-md-3 .info-box .progress-description {
    font-size: 1rem;
    display: block;
  }
}

.timeline {
  position: relative;
  padding: 0;
  margin: 0 0 45px;
}
.timeline::before {
  border-radius: 0.375rem;
  position: absolute;
  top: 0;
  bottom: 0;
  left: 31px;
  width: 4px;
  margin: 0;
  content: "";
  background-color: var(--bs-border-color);
}
.timeline > div {
  position: relative;
  margin-right: 10px;
  margin-bottom: 15px;
}
.timeline > div::before, .timeline > div::after {
  display: table;
  content: "";
}
.timeline > div > .timeline-item {
  box-shadow: 0 0 1px rgba(var(--bs-body-color-rgb), 0.125), 0 1px 3px rgba(var(--bs-body-color-rgb), 0.2);
  border-radius: 0.375rem;
  position: relative;
  padding: 0;
  margin-top: 0;
  margin-right: 15px;
  margin-left: 60px;
  color: var(--bs-body-color);
  background-color: var(--bs-body-bg);
}
.timeline > div > .timeline-item > .time {
  float: right;
  padding: 10px;
  font-size: 12px;
  color: var(--bs-secondary-color);
}
.timeline > div > .timeline-item > .timeline-header {
  padding: 10px;
  margin: 0;
  font-size: 16px;
  line-height: 1.1;
  color: var(--bs-secondary-color);
  border-bottom: 1px solid var(--bs-border-color);
}
.timeline > div > .timeline-item > .timeline-header > a {
  font-weight: 600;
  text-decoration: none;
}
.timeline > div > .timeline-item > .timeline-body,
.timeline > div > .timeline-item > .timeline-footer {
  padding: 10px;
}
.timeline > div > .timeline-item > .timeline-body > img {
  margin: 10px;
}
.timeline > div > .timeline-item > .timeline-body > dl,
.timeline > div > .timeline-item > .timeline-body ol,
.timeline > div > .timeline-item > .timeline-body ul {
  margin: 0;
}
.timeline > div .timeline-icon {
  position: absolute;
  top: 0;
  left: 18px;
  width: 30px;
  height: 30px;
  font-size: 16px;
  line-height: 30px;
  text-align: center;
  background-color: var(--bs-secondary-bg);
  border-radius: 50%;
}
.timeline > .time-label > span {
  border-radius: 4px;
  display: inline-block;
  padding: 5px;
  font-weight: 600;
  background-color: var(--bs-body-bg);
}

.timeline-inverse > div > .timeline-item {
  box-shadow: none;
  background-color: var(--bs-tertiary-bg);
  border: 1px solid var(--bs-border-color);
}
.timeline-inverse > div > .timeline-item > .timeline-header {
  border-bottom-color: var(--bs-border-color);
}

.direct-chat .card-body {
  position: relative;
  padding: 0;
  overflow-x: hidden;
}
.direct-chat.chat-pane-open .direct-chat-contacts {
  transform: translate(0, 0);
}
.direct-chat.timestamp-light .direct-chat-timestamp {
  color: rgba(var(--bs-body-color-rgb), 0.65);
}
.direct-chat.timestamp-dark .direct-chat-timestamp {
  color: rgba(var(--bs-body-color-rgb), 0.9);
}

.direct-chat-messages {
  height: 250px;
  padding: 10px;
  overflow: auto;
  transform: translate(0, 0);
}

.direct-chat-msg,
.direct-chat-text {
  display: block;
}

.direct-chat-msg {
  margin-bottom: 10px;
}
.direct-chat-msg::after {
  display: block;
  clear: both;
  content: "";
}

.direct-chat-messages,
.direct-chat-contacts {
  transition: transform 0.5s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .direct-chat-messages,
  .direct-chat-contacts {
    transition: none;
  }
}

.direct-chat-text {
  border-radius: 0.5rem;
  position: relative;
  padding: 5px 10px;
  margin: 5px 0 0 50px;
  color: var(--bs-emphasis-color);
  background-color: var(--bs-secondary-bg);
  border: 1px solid var(--bs-border-color);
}
.direct-chat-text::after, .direct-chat-text::before {
  position: absolute;
  top: 15px;
  right: 100%;
  width: 0;
  height: 0;
  pointer-events: none;
  content: " ";
  border: solid transparent;
  border-right-color: var(--bs-border-color);
}
.direct-chat-text::after {
  margin-top: -5px;
  border-width: 5px;
}
.direct-chat-text::before {
  margin-top: -6px;
  border-width: 6px;
}
.end .direct-chat-text {
  margin-right: 50px;
  margin-left: 0;
}
.end .direct-chat-text::after, .end .direct-chat-text::before {
  right: auto;
  left: 100%;
  border-right-color: transparent;
  border-left-color: var(--bs-border-color);
}

.direct-chat-img {
  border-radius: 50%;
  float: left;
  width: 40px;
  height: 40px;
}
.end .direct-chat-img {
  float: right;
}

.direct-chat-infos {
  display: block;
  margin-bottom: 2px;
  font-size: 0.875rem;
}

.direct-chat-name {
  font-weight: 600;
}

.direct-chat-timestamp {
  color: rgba(var(--bs-body-color-rgb), 0.75);
}

.direct-chat-contacts-open .direct-chat-contacts {
  transform: translate(0, 0);
}

.direct-chat-contacts {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 100%;
  height: 250px;
  overflow: auto;
  color: var(--bs-body-bg);
  background-color: var(--bs-body-color);
  transform: translate(101%, 0);
}

.direct-chat-contacts-light {
  background-color: var(--bs-light-bg-subtle);
}
.direct-chat-contacts-light .contacts-list-name {
  color: var(--bs-body-color);
}
.direct-chat-contacts-light .contacts-list-date {
  color: var(--bs-secondary-color);
}
.direct-chat-contacts-light .contacts-list-msg {
  color: var(--bs-secondary-color);
}

.contacts-list {
  padding-left: 0;
  list-style: none;
}
.contacts-list > li {
  padding: 10px;
  margin: 0;
  text-decoration: none;
  border-bottom: 1px solid rgba(0, 0, 0, 0.2);
}
.contacts-list > li::after {
  display: block;
  clear: both;
  content: "";
}
.contacts-list > li:last-of-type {
  border-bottom: 0;
}
.contacts-list > li a {
  text-decoration: none;
}

.contacts-list-img {
  border-radius: 50%;
  float: left;
  width: 40px;
}

.contacts-list-info {
  margin-left: 45px;
  color: var(--bs-body-bg);
}

.contacts-list-name,
.contacts-list-status {
  display: block;
}

.contacts-list-name {
  font-weight: 600;
}

.contacts-list-status {
  font-size: 0.875rem;
}

.contacts-list-date {
  font-weight: 400;
  color: var(--bs-secondary-bg);
}

.contacts-list-msg {
  color: var(--bs-secondary-bg);
}

.end > .direct-chat-text {
  color: var(--lte-direct-chat-color);
  background-color: var(--lte-direct-chat-bg);
  border-color: var(--lte-direct-chat-bg);
}
.end > .direct-chat-text::after, .end > .direct-chat-text::before {
  border-left-color: var(--lte-direct-chat-bg);
}

.direct-chat-primary {
  --lte-direct-chat-color: #fff;
  --lte-direct-chat-bg: #0d6efd;
}

.direct-chat-secondary {
  --lte-direct-chat-color: #fff;
  --lte-direct-chat-bg: #6c757d;
}

.direct-chat-success {
  --lte-direct-chat-color: #fff;
  --lte-direct-chat-bg: #198754;
}

.direct-chat-info {
  --lte-direct-chat-color: #000;
  --lte-direct-chat-bg: #0dcaf0;
}

.direct-chat-warning {
  --lte-direct-chat-color: #000;
  --lte-direct-chat-bg: #ffc107;
}

.direct-chat-danger {
  --lte-direct-chat-color: #fff;
  --lte-direct-chat-bg: #dc3545;
}

.direct-chat-light {
  --lte-direct-chat-color: #000;
  --lte-direct-chat-bg: #f8f9fa;
}

.direct-chat-dark {
  --lte-direct-chat-color: #fff;
  --lte-direct-chat-bg: #212529;
}

.toast-primary {
  --bs-toast-header-color: #fff;
  --bs-toast-header-bg: #0d6efd;
  --bs-toast-header-border-color: #0d6efd;
  --bs-toast-border-color: #0d6efd;
  --bs-toast-bg: var(--bs-primary-bg-subtle);
}
.toast-primary .btn-close {
  --bs-btn-close-filter: invert(1) grayscale(100%) brightness(200%);
}

.toast-secondary {
  --bs-toast-header-color: #fff;
  --bs-toast-header-bg: #6c757d;
  --bs-toast-header-border-color: #6c757d;
  --bs-toast-border-color: #6c757d;
  --bs-toast-bg: var(--bs-secondary-bg-subtle);
}
.toast-secondary .btn-close {
  --bs-btn-close-filter: invert(1) grayscale(100%) brightness(200%);
}

.toast-success {
  --bs-toast-header-color: #fff;
  --bs-toast-header-bg: #198754;
  --bs-toast-header-border-color: #198754;
  --bs-toast-border-color: #198754;
  --bs-toast-bg: var(--bs-success-bg-subtle);
}
.toast-success .btn-close {
  --bs-btn-close-filter: invert(1) grayscale(100%) brightness(200%);
}

.toast-info {
  --bs-toast-header-color: #000;
  --bs-toast-header-bg: #0dcaf0;
  --bs-toast-header-border-color: #0dcaf0;
  --bs-toast-border-color: #0dcaf0;
  --bs-toast-bg: var(--bs-info-bg-subtle);
}

.toast-warning {
  --bs-toast-header-color: #000;
  --bs-toast-header-bg: #ffc107;
  --bs-toast-header-border-color: #ffc107;
  --bs-toast-border-color: #ffc107;
  --bs-toast-bg: var(--bs-warning-bg-subtle);
}

.toast-danger {
  --bs-toast-header-color: #fff;
  --bs-toast-header-bg: #dc3545;
  --bs-toast-header-border-color: #dc3545;
  --bs-toast-border-color: #dc3545;
  --bs-toast-bg: var(--bs-danger-bg-subtle);
}
.toast-danger .btn-close {
  --bs-btn-close-filter: invert(1) grayscale(100%) brightness(200%);
}

.toast-light {
  --bs-toast-header-color: #000;
  --bs-toast-header-bg: #f8f9fa;
  --bs-toast-header-border-color: #f8f9fa;
  --bs-toast-border-color: #f8f9fa;
  --bs-toast-bg: var(--bs-light-bg-subtle);
}

.toast-dark {
  --bs-toast-header-color: #fff;
  --bs-toast-header-bg: #212529;
  --bs-toast-header-border-color: #212529;
  --bs-toast-border-color: #212529;
  --bs-toast-bg: var(--bs-dark-bg-subtle);
}
.toast-dark .btn-close {
  --bs-btn-close-filter: invert(1) grayscale(100%) brightness(200%);
}

[data-bs-theme=dark] .toast-info .btn-close {
  --bs-btn-close-white-filter: none;
}
[data-bs-theme=dark] .toast-warning .btn-close {
  --bs-btn-close-white-filter: none;
}
[data-bs-theme=dark] .toast-light .btn-close {
  --bs-btn-close-white-filter: none;
}
.login-logo,
.register-logo {
  margin-bottom: 0.9rem;
  font-size: 2.1rem;
  font-weight: 300;
  text-align: center;
}
.login-logo a,
.register-logo a {
  color: var(--bs-secondary-color);
  text-decoration: none;
}

.login-page,
.register-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
}

.login-box,
.register-box {
  width: 360px;
}
@media (max-width: 576px) {
  .login-box,
  .register-box {
    width: 90%;
    margin-top: 0.5rem;
  }
}
.login-box .card,
.register-box .card {
  margin-bottom: 0;
}

.login-card-body,
.register-card-body {
  padding: 20px;
  color: var(--bs-secondary-color);
  background-color: var(--bs-body-bg);
  border-top: 0;
}
.login-card-body .input-group .form-control:focus,
.register-card-body .input-group .form-control:focus {
  box-shadow: none;
}
.login-card-body .input-group .form-control:focus ~ .input-group-prepend .input-group-text,
.login-card-body .input-group .form-control:focus ~ .input-group-append .input-group-text,
.register-card-body .input-group .form-control:focus ~ .input-group-prepend .input-group-text,
.register-card-body .input-group .form-control:focus ~ .input-group-append .input-group-text {
  border-color: rgb(134, 182.5, 254);
}
.login-card-body .input-group .form-control.is-valid:focus,
.register-card-body .input-group .form-control.is-valid:focus {
  box-shadow: none;
}
.login-card-body .input-group .form-control.is-valid ~ .input-group-prepend .input-group-text,
.login-card-body .input-group .form-control.is-valid ~ .input-group-append .input-group-text,
.register-card-body .input-group .form-control.is-valid ~ .input-group-prepend .input-group-text,
.register-card-body .input-group .form-control.is-valid ~ .input-group-append .input-group-text {
  border-color: #198754;
}
.login-card-body .input-group .form-control.is-invalid:focus,
.register-card-body .input-group .form-control.is-invalid:focus {
  box-shadow: none;
}
.login-card-body .input-group .form-control.is-invalid ~ .input-group-append .input-group-text,
.register-card-body .input-group .form-control.is-invalid ~ .input-group-append .input-group-text {
  border-color: #dc3545;
}
.login-card-body .input-group .input-group-text,
.register-card-body .input-group .input-group-text {
  color: var(--bs-secondary-color);
  background-color: transparent;
  border-top-right-radius: 0.375rem;
  border-bottom-right-radius: 0.375rem;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
  .login-card-body .input-group .input-group-text,
  .register-card-body .input-group .input-group-text {
    transition: none;
  }
}

.login-box-msg,
.register-box-msg {
  padding: 0 20px 20px;
  margin: 0;
  text-align: center;
}

.social-auth-links {
  margin: 10px 0;
}

.lockscreen .lockscreen-name {
  font-weight: 600;
  text-align: center;
}
.lockscreen .lockscreen-logo {
  margin-bottom: 25px;
  font-size: 35px;
  font-weight: 300;
  text-align: center;
}
.lockscreen .lockscreen-logo a {
  color: var(--bs-emphasis-color);
  text-decoration: none;
}
.lockscreen .lockscreen-wrapper {
  max-width: 400px;
  margin: 0 auto;
  margin-top: 10%;
}
.lockscreen .lockscreen-item {
  position: relative;
  width: 290px;
  padding: 0;
  margin: 10px auto 30px;
  background-color: var(--bs-body-bg);
  border-radius: 4px;
}
.lockscreen .lockscreen-image {
  position: absolute;
  top: -25px;
  left: -10px;
  z-index: 10;
  padding: 5px;
  background-color: var(--bs-body-bg);
  border-radius: 50%;
}
.lockscreen .lockscreen-image > img {
  border-radius: 50%;
  width: 70px;
  height: 70px;
}
.lockscreen .lockscreen-credentials {
  margin-left: 70px;
}
.lockscreen .lockscreen-credentials .form-control {
  border: 0;
}
.lockscreen .lockscreen-credentials .btn {
  padding: 0 10px;
  border: 0;
}
.lockscreen .lockscreen-footer {
  margin-top: 10px;
}

.img-size-64,
.img-size-50,
.img-size-32 {
  height: auto;
}

.img-size-64 {
  width: 64px;
}

.img-size-50 {
  width: 50px;
}

.img-size-32 {
  width: 32px;
}

/* ==========================================================================
   AdminLTE Accessibility Styles - WCAG 2.1 AA Compliance
   ========================================================================== */
/* Skip Links - WCAG 2.4.1: Bypass Blocks */
.skip-link {
  position: absolute;
  top: -40px;
  left: 6px;
  z-index: 999999;
  padding: 8px 16px;
  font-weight: 600;
  color: var(--bs-white);
  text-decoration: none;
  background: var(--bs-primary);
}
.skip-link:focus {
  top: 0;
  outline: 3px solid var(--bs-warning);
  outline-offset: 2px;
}
.skip-link:hover {
  color: var(--bs-white);
  text-decoration: none;
  background: var(--bs-primary-emphasis);
}

/* Enhanced Focus Indicators - WCAG 2.4.7: Focus Visible */
.focus-enhanced:focus {
  outline: 3px solid var(--bs-focus-ring-color, #0d6efd);
  outline-offset: 2px;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* High Contrast Mode Support */
@media (prefers-contrast: high) {
  .card {
    border: 2px solid;
  }
  .btn {
    border-width: 2px;
  }
  .nav-link {
    border: 1px solid transparent;
  }
  .nav-link:hover, .nav-link:focus {
    border-color: currentcolor;
  }
}
/* Reduced Motion Support - WCAG 2.3.3: Animation from Interactions */
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    scroll-behavior: auto !important;
  }
  .fade {
    opacity: 1 !important;
    /* stylelint-disable-next-line property-disallowed-list */
    transition: none !important;
  }
  .collapse {
    /* stylelint-disable-next-line property-disallowed-list */
    transition: none !important;
  }
  .modal.fade .modal-dialog {
    transform: none !important;
  }
}
/* Screen Reader Only Content */
.sr-only {
  position: absolute !important;
  width: 1px !important;
  height: 1px !important;
  padding: 0 !important;
  margin: -1px !important;
  overflow: hidden !important;
  clip: rect(0, 0, 0, 0) !important;
  white-space: nowrap !important;
  border: 0 !important;
}

.sr-only-focusable:focus {
  position: static !important;
  width: auto !important;
  height: auto !important;
  padding: inherit !important;
  margin: inherit !important;
  overflow: visible !important;
  clip: auto !important;
  white-space: normal !important;
}

/* Focus Trap Utilities */
.focus-trap:focus {
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Accessible Color Combinations - WCAG 1.4.3: Contrast (Minimum) */
.text-accessible-primary {
  color: #003d82; /* 4.5:1 contrast on white */
}

.text-accessible-success {
  color: #0f5132; /* 4.5:1 contrast on white */
}

.text-accessible-danger {
  color: #842029; /* 4.5:1 contrast on white */
}

.text-accessible-warning {
  color: #664d03; /* 4.5:1 contrast on white */
}

/* ARIA Live Regions */
.live-region {
  position: absolute;
  left: -10000px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}
.live-region.live-region-visible {
  position: static;
  left: auto;
  width: auto;
  height: auto;
  overflow: visible;
}

/* Enhanced Error States - WCAG 3.3.1: Error Identification */
.form-control.is-invalid {
  border-color: var(--bs-danger);
}
.form-control.is-invalid:focus {
  border-color: var(--bs-danger);
  box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
}

.invalid-feedback {
  display: block;
  width: 100%;
  margin-top: 0.25rem;
  font-size: 0.875em;
  color: var(--bs-danger);
}
.invalid-feedback[role=alert] {
  font-weight: 600;
}

/* Target Size - WCAG 2.5.8: Target Size (Minimum) */
.touch-target {
  min-width: 44px;
  min-height: 44px;
}
.touch-target.touch-target-small {
  min-width: 24px;
  min-height: 24px;
}

/* Table Accessibility */
.table-accessible th {
  font-weight: 600;
  background-color: var(--bs-secondary-bg);
}
.table-accessible th[scope=col] {
  border-bottom: 2px solid var(--bs-border-color);
}
.table-accessible th[scope=row] {
  border-right: 2px solid var(--bs-border-color);
}
.table-accessible caption {
  padding: 0.75rem;
  font-weight: 600;
  color: var(--bs-secondary);
  text-align: left;
  caption-side: top;
}

/* Navigation Landmarks */
nav[role=navigation]:not([aria-label]):not([aria-labelledby])::before {
  position: absolute;
  left: -10000px;
  content: "Navigation";
}

/* Form Fieldset Styling */
fieldset {
  padding: 1rem;
  margin-bottom: 1rem;
  border: 1px solid var(--bs-border-color);
}
fieldset legend {
  padding: 0 0.5rem;
  margin-bottom: 0.5rem;
  font-size: 1.1em;
  font-weight: 600;
}

/* Loading States */
.loading[aria-busy=true] {
  position: relative;
  pointer-events: none;
}
.loading[aria-busy=true]::after {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 20px;
  height: 20px;
  margin-top: -10px;
  margin-left: -10px;
  content: "";
  border: 2px solid var(--bs-primary);
  border-top-color: transparent;
  animation: spin 1s linear infinite;
}
@media (prefers-reduced-motion: reduce) {
  .loading[aria-busy=true]::after {
    border-top-color: var(--bs-primary);
    animation: none;
  }
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
/* Dark Mode Accessibility */
[data-bs-theme=dark] .text-accessible-primary {
  color: #6ea8fe;
}
[data-bs-theme=dark] .text-accessible-success {
  color: #75b798;
}
[data-bs-theme=dark] .text-accessible-danger {
  color: #f1aeb5;
}
[data-bs-theme=dark] .text-accessible-warning {
  color: #ffda6a;
}

/* Print Accessibility */
@media print {
  .skip-link,
  .btn,
  .nav-link {
    color: #000 !important;
    background: transparent !important;
    border: 1px solid #000 !important;
  }
  a[href^=http]::after {
    font-size: 0.8em;
    content: " (" attr(href) ")";
  }
}

/*# sourceMappingURL=adminlte.css.map */ ```

## Archivo: ./public/css/custom.css
 ```css
/* ==========================================================================
   Tema Claro estilo Facebook para AdminLTE v4
   ========================================================================== */

:root,
[data-bs-theme="light"] {
  /* 1. Fondo principal de la página (Gris claro característico) */
  --bs-body-bg: #f0f2f5;
  --bs-body-bg-rgb: 240, 242, 245;

  /* 2. Textos */
  --bs-body-color: #050505; /* Texto principal (casi negro) */
  --bs-body-color-rgb: 5, 5, 5;
  --bs-secondary-color: #65676b; /* Texto secundario (gris medio) */
  --bs-secondary-color-rgb: 101, 103, 107;

  /* 3. Fondos secundarios (Hover y botones inactivos) */
  --bs-secondary-bg: #e4e6eb;
  --bs-secondary-bg-rgb: 228, 230, 235;
  --bs-tertiary-bg: #e4e6eb;

  /* 4. Bordes y separadores */
  --bs-border-color: #ced0d4;
  --bs-border-color-translucent: rgba(0, 0, 0, 0.1);

  /* 5. Color Primario (El azul clásico) */
  --bs-primary: #1877f2;
  --bs-primary-rgb: 24, 119, 242;
  --bs-link-color: #1877f2;
  --bs-link-hover-color: #166fe5;
}

/* --------------------------------------------------------------------------
   Ajustes específicos para componentes de AdminLTE (Tema Claro)
   -------------------------------------------------------------------------- */

/* Tarjetas (Cards) en blanco puro para que resalten sobre el fondo gris */
:root .card,
[data-bs-theme="light"] .card {
  --bs-card-bg: #ffffff;
  border-color: transparent; /* Se quitan los bordes duros */
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2); /* Sombra muy suave */
  border-radius: 8px; /* Bordes ligeramente más redondeados */
}

/* Barra superior (Header) y barra lateral (Sidebar) en blanco */
:root .app-header,
[data-bs-theme="light"] .app-header,
:root .app-sidebar,
[data-bs-theme="light"] .app-sidebar {
  background-color: #ffffff !important;
  border-color: #ced0d4 !important;
}

/* Comportamiento del menú lateral (Hover y activo) */
:root .app-sidebar,
[data-bs-theme="light"] .app-sidebar {
  --lte-sidebar-hover-bg: #f2f2f2; /* Gris muy suave al pasar el mouse */
  --lte-sidebar-color: #050505;
  --lte-sidebar-hover-color: #000000;
  --lte-sidebar-menu-active-bg: #e7f3ff; /* Fondo azul muy claro para el item activo */
  --lte-sidebar-menu-active-color: #1877f2; /* Texto azul para el item activo */
}

/* Formularios, inputs y selects */
:root .form-control,
:root .form-select,
[data-bs-theme="light"] .form-control,
[data-bs-theme="light"] .form-select {
  background-color: #f0f2f5; /* Fondo gris claro en reposo */
  border-color: transparent;
  color: #050505;
  border-radius: 6px;
}

:root .form-control:focus,
:root .form-select:focus,
[data-bs-theme="light"] .form-control:focus,
[data-bs-theme="light"] .form-select:focus {
  background-color: #ffffff; /* Se iluminan a blanco al escribir */
  border-color: #1877f2;
  box-shadow: 0 0 0 0.25rem rgba(24, 119, 242, 0.25);
}



/* ==========================================================================
   Modo Oscuro estilo Facebook para AdminLTE v4
   ========================================================================== */

[data-bs-theme="dark"] {
  /* 1. Fondo principal de la página (Gris muy oscuro) */
  --bs-body-bg: #18191a;
  --bs-body-bg-rgb: 24, 25, 26;

  /* 2. Textos */
  --bs-body-color: #e4e6eb; /* Texto principal (casi blanco) */
  --bs-body-color-rgb: 228, 230, 235;
  --bs-secondary-color: #b0b3b8; /* Texto secundario (gris claro) */
  --bs-secondary-color-rgb: 176, 179, 184;

  /* 3. Fondos secundarios (Hover y elementos inactivos) */
  --bs-secondary-bg: #3a3b3c;
  --bs-secondary-bg-rgb: 23, 23, 23;
  --bs-tertiary-bg: #3a3b3c;

  /* 4. Bordes y separadores */
  --bs-border-color: #3e4042;
  --bs-border-color-translucent: rgba(255, 255, 255, 0.1);

  /* 5. Color Primario (El azul característico adaptado para modo oscuro) */
  --bs-primary: #2d88ff;
  --bs-primary-rgb: 45, 136, 255;
  --bs-link-color: #2d88ff;
  --bs-link-hover-color: #5c9eff;
}

/* --------------------------------------------------------------------------
   Ajustes específicos para componentes de AdminLTE
   -------------------------------------------------------------------------- */

/* Tarjetas (Cards) y paneles con el gris de superficie de FB */
[data-bs-theme="dark"] .card {
  --bs-card-bg: #242526;
  border-color: #3e4042;
}

/* Barra superior (Header) y barra lateral (Sidebar) */
[data-bs-theme="dark"] .app-header,
[data-bs-theme="dark"] .app-sidebar {
  background-color: #242526 !important;
  border-color: #3e4042 !important;
}

/* Comportamiento del menú lateral (Hover y activo) */
[data-bs-theme="dark"] .app-sidebar {
  --lte-sidebar-hover-bg: #3a3b3c;
  --lte-sidebar-color: #e4e6eb;
  --lte-sidebar-hover-color: #ffffff;
  --lte-sidebar-menu-active-bg: rgba(45, 136, 255, 0.15); /* Fondo azul sutil */
  --lte-sidebar-menu-active-color: #2d88ff;
}

/* Formularios, inputs y selects */
[data-bs-theme="dark"] .form-control,
[data-bs-theme="dark"] .form-select {
  background-color: #3a3b3c;
  border-color: #3e4042;
  color: #e4e6eb;
}

[data-bs-theme="dark"] .form-control:focus,
[data-bs-theme="dark"] .form-select:focus {
  background-color: #3a3b3c;
  border-color: #2d88ff;
  box-shadow: 0 0 0 0.25rem rgba(45, 136, 255, 0.25);
}

[data-bs-theme="dark"] .bg-light {
    --bs-bg-opacity: 1;
    background-color
: rgb(24 25 26) !important;
}
[data-bs-theme="dark"] .bg-primary {
    background-color: rgb(24 25 26) !important;
}

[data-bs-theme="dark"] .text-bg-primary {
    color: rgb(36 37 38) !important;
        background-color: rgb(36 37 38) !important;
    
}

.product-item .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
}
.product-item img {
    width: 100%;
    height: 100%;
    object-fit: contain; /* Esto evita que la imagen se deforme */
    padding: 5px;
}
 ```

## Archivo: ./public/customers.php
 ```php
<?php
require_once '../controllers/CustomerController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php';
?>
<main class="app-main">
    <?= render_content_header($headerConfig) ?>
    <div class="app-content">
        <div class="container-fluid">

            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header border-0 pb-0">
                    <h3 class="card-title fw-bold">Lista de Clientes</h3>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="customersTable" class="table table-hover table-striped align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Nombre Completo</th>
                                    <th>Cédula / RIF</th>
                                    <th>Teléfono</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $c):
                                    // Preparamos los datos para pasarlos al botón de editar de forma segura
                                    $c_json = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr>
                                        <td class="fw-bold text-primary">
                                            <i class="fas fa-user-circle text-secondary me-2"></i><?= htmlspecialchars($c['name']) ?>
                                        </td>
                                        <td><?= !empty($c['document']) ? htmlspecialchars($c['document']) : '<span class="text-muted fst-italic">No registrado</span>' ?></td>
                                        <td><?= !empty($c['phone']) ? htmlspecialchars($c['phone']) : '<span class="text-muted fst-italic">No registrado</span>' ?></td>

                                        <td class="text-end text-nowrap">
                                                    <button class="btn btn-sm btn-outline-info me-1" onclick='viewCustomer(<?= $c_json ?>)' title="Ver Detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                            <button class="btn btn-sm btn-outline-warning me-1" onclick='openCustomerModal(<?= $c_json ?>)' title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCustomer(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['name'])) ?>')" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
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
include 'layouts/modals/modals_customer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/customers.js"></script>
</body>

</html> ```

## Archivo: ./public/dashboard.php
 ```php
<?php
require_once '../controllers/DashboardController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php';
?>
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
    window.APP_JS_CHARTSALE = <?= $jsChartSales ?>;
    window.APP_JS_CHARTDATES = <?= $jsChartDates ?>;
</script>
<script src="js/dashboard.js"></script>
</body>

</html> ```

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
</html> ```

## Archivo: ./public/get_sale_details.php
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
</div> ```

## Archivo: ./public/js/admin.js
 ```javascript
let modalViewInstance;
let modalEditInstance;
let modalInsertInstance; // Añadido para controlar el modal de crear

document.addEventListener("DOMContentLoaded", function () {
    if (typeof bootstrap === "undefined") {
        console.error("⚠️ ERROR: Bootstrap JS no está cargado.");
        return;
    }

    modalViewInstance = new bootstrap.Modal(document.getElementById("modalView"));
    modalEditInstance = new bootstrap.Modal(document.getElementById("modalEdit"));
    
    // Si tu modal de insertar tiene este ID:
    const modalInsertEl = document.getElementById("modalInsert");
    if(modalInsertEl) modalInsertInstance = new bootstrap.Modal(modalInsertEl);

    // --- INTERCEPTAR FORMULARIO DE CREAR ---
    const formInsert = document.getElementById("formInsert"); // <-- Verifica que este ID exista en modals_admin.php
    if (formInsert) {
        formInsert.addEventListener("submit", function (e) {
            e.preventDefault();
            submitFormAjax(this, "create", modalInsertInstance);
        });
    }

    // --- INTERCEPTAR FORMULARIO DE EDITAR ---
    const formEdit = document.getElementById("formEdit"); // <-- Verifica que este ID exista en modals_admin.php
    if (formEdit) {
        formEdit.addEventListener("submit", function (e) {
            e.preventDefault();
            submitFormAjax(this, "update", modalEditInstance);
        });
    }
});

// Inicialización de DataTables
$(document).ready(function () {
    $("#productosTable").DataTable({
        language: { url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" },
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
        order: [[0, "asc"]],
        columnDefs: [{ orderable: false, targets: 10 }],
        dom: '<"row mb-3"<"col-md-6"l><"col-md-6"f>>rt<"row mt-3"<"col-md-6"i><"col-md-6"p>>'
    });
});

// --- FUNCIÓN GENÉRICA PARA ENVIAR FORMULARIOS CON FETCH ---
function submitFormAjax(form, actionType, modalInstance) {
    const formData = new FormData(form);
    formData.append("action", actionType);

    // Deshabilitar el botón de guardado para evitar doble clic
    const btnSubmit = form.querySelector('button[type="submit"]');
    if(btnSubmit) btnSubmit.disabled = true;

    fetch("actions/actions_product.php", {
        method: "POST",
        body: formData,
    })
    .then((response) => response.json())
    .then((res) => {
        if (res.status) {
            if(modalInstance) modalInstance.hide();
            Swal.fire({
                title: "¡Éxito!",
                text: res.message,
                icon: "success",
                confirmButtonColor: "#198754"
            }).then(() => {
                location.reload(); // Recargar para ver los cambios
            });
        } else {
            Swal.fire("Error", res.message, "error");
        }
    })
    .catch((error) => {
        console.error("Error:", error);
        Swal.fire("Error", "Problema de conexión con el servidor.", "error");
    })
    .finally(() => {
        if(btnSubmit) btnSubmit.disabled = false;
    });
}


function viewProduct(p) {
    if (!modalViewInstance) return alert('El modal aún no se ha inicializado.');

    const imgHtml = p.image 
        ? `<div class="text-center mb-3"><img src="${p.image}" class="img-fluid rounded border shadow-sm" style="max-height: 200px; object-fit: contain;"></div>` 
        : `<div class="text-center py-4 bg-secondary bg-opacity-10 border rounded mb-3"><i class="fas fa-box fa-4x text-secondary opacity-50"></i></div>`;
    
    const descHtml = p.description 
        ? `<div class="alert alert-secondary small mb-3 p-2"><i class="fas fa-quote-left me-2 text-muted"></i><em>${p.description}</em></div>` 
        : '';

    const content = `
        ${imgHtml}
        <ul class="list-group list-group-flush mb-3">
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Nombre:</span>
                <span class="fw-bold">${p.name}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Marca:</span>
                <span>${p.brand || '<span class="text-muted fst-italic">N/A</span>'}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">SKU:</span>
                <span>${p.sku || '<span class="text-muted fst-italic">N/A</span>'}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Cód. Barras:</span>
                <span>${p.barcode || '<span class="text-muted fst-italic">N/A</span>'}</span>
            </li>
            ${descHtml ? `<li class="list-group-item bg-transparent px-0 border-0 pb-0">${descHtml}</li>` : ''}
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Categoría:</span>
                <span class="badge text-bg-secondary">${p.category_name || 'General'}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Stock actual:</span>
                <span><span class="badge ${p.stock < 5 ? 'text-bg-danger' : 'text-bg-success'} rounded-pill me-1">${p.stock}</span> Unidades</span>
            </li>
        </ul>
        <div class="card bg-light border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted fw-bold">Costo Base:</span>
                    <span class="text-success fw-bold">$${parseFloat(p.price_base_usd).toFixed(2)}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted fw-bold">Margen:</span>
                    <span class="text-info fw-bold">${parseFloat(p.profit_margin).toFixed(2)}%</span>
                </div>
            </div>
        </div>
    `;
    document.getElementById('viewContent').innerHTML = content;
    modalViewInstance.show();
}

function editProduct(p) {
    if (!modalEditInstance) return alert("El modal aún no se ha inicializado.");
    document.getElementById("edit_id").value = p.id;
    document.getElementById("edit_name").value = p.name;
    document.getElementById("edit_category").value = p.category_id || "";
    document.getElementById("edit_sku").value = p.sku || "";
    document.getElementById("edit_barcode").value = p.barcode || "";
    document.getElementById("edit_brand").value = p.brand || "";
    document.getElementById("edit_price").value = p.price_base_usd;
    document.getElementById("edit_margin").value = p.profit_margin;
    document.getElementById("edit_stock").value = p.stock;
    document.getElementById("edit_image").value = p.image || "";
    document.getElementById("edit_description").value = p.description || "";
    modalEditInstance.show();
}

// Función para Eliminar con SweetAlert2
function deleteProduct(id, name) {
    Swal.fire({
        title: "¿Eliminar Producto?",
        html: `Estás a punto de eliminar <strong>${name}</strong>.<br>Esta acción no se puede deshacer.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append("action", "delete");
            formData.append("id", id);

            fetch("actions/actions_product.php", {
                method: "POST",
                body: formData,
            })
            .then((response) => response.json())
            .then((res) => {
                if (res.status) {
                    Swal.fire({
                        title: "¡Eliminado!", 
                        text: res.message, 
                        icon: "success",
                        confirmButtonColor: "#198754"
                    }).then(() => location.reload());
                } else {
                    Swal.fire("Error", res.message, "error");
                }
            })
            .catch((error) => {
                console.error("Error:", error);
                Swal.fire("Error", "Problema al intentar eliminar.", "error");
            });
        }
    });
} ```

## Archivo: ./public/js/adminlte.js
 ```javascript
/*!
 * AdminLTE v4.0.0-rc3 (https://adminlte.io)
 * Copyright 2014-2025 Colorlib <https://colorlib.com>
 * Licensed under MIT (https://github.com/ColorlibHQ/AdminLTE/blob/master/LICENSE)
 */
(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? factory(exports) :
    typeof define === 'function' && define.amd ? define(['exports'], factory) :
    (global = typeof globalThis !== 'undefined' ? globalThis : global || self, factory(global.adminlte = {}));
})(this, (function (exports) { 'use strict';

    const domContentLoadedCallbacks = [];
    const onDOMContentLoaded = (callback) => {
        if (document.readyState === 'loading') {
            // add listener on the first call when the document is in loading state
            if (!domContentLoadedCallbacks.length) {
                document.addEventListener('DOMContentLoaded', () => {
                    for (const callback of domContentLoadedCallbacks) {
                        callback();
                    }
                });
            }
            domContentLoadedCallbacks.push(callback);
        }
        else {
            callback();
        }
    };
    /* SLIDE UP */
    const slideUp = (target, duration = 500) => {
        target.style.transitionProperty = 'height, margin, padding';
        target.style.transitionDuration = `${duration}ms`;
        target.style.boxSizing = 'border-box';
        target.style.height = `${target.offsetHeight}px`;
        target.style.overflow = 'hidden';
        globalThis.setTimeout(() => {
            target.style.height = '0';
            target.style.paddingTop = '0';
            target.style.paddingBottom = '0';
            target.style.marginTop = '0';
            target.style.marginBottom = '0';
        }, 1);
        globalThis.setTimeout(() => {
            target.style.display = 'none';
            target.style.removeProperty('height');
            target.style.removeProperty('padding-top');
            target.style.removeProperty('padding-bottom');
            target.style.removeProperty('margin-top');
            target.style.removeProperty('margin-bottom');
            target.style.removeProperty('overflow');
            target.style.removeProperty('transition-duration');
            target.style.removeProperty('transition-property');
        }, duration);
    };
    /* SLIDE DOWN */
    const slideDown = (target, duration = 500) => {
        target.style.removeProperty('display');
        let { display } = globalThis.getComputedStyle(target);
        if (display === 'none') {
            display = 'block';
        }
        target.style.display = display;
        const height = target.offsetHeight;
        target.style.overflow = 'hidden';
        target.style.height = '0';
        target.style.paddingTop = '0';
        target.style.paddingBottom = '0';
        target.style.marginTop = '0';
        target.style.marginBottom = '0';
        globalThis.setTimeout(() => {
            target.style.boxSizing = 'border-box';
            target.style.transitionProperty = 'height, margin, padding';
            target.style.transitionDuration = `${duration}ms`;
            target.style.height = `${height}px`;
            target.style.removeProperty('padding-top');
            target.style.removeProperty('padding-bottom');
            target.style.removeProperty('margin-top');
            target.style.removeProperty('margin-bottom');
        }, 1);
        globalThis.setTimeout(() => {
            target.style.removeProperty('height');
            target.style.removeProperty('overflow');
            target.style.removeProperty('transition-duration');
            target.style.removeProperty('transition-property');
        }, duration);
    };

    /**
     * --------------------------------------------
     * @file AdminLTE layout.ts
     * @description Layout for AdminLTE.
     * @license MIT
     * --------------------------------------------
     */
    /**
     * ------------------------------------------------------------------------
     * Constants
     * ------------------------------------------------------------------------
     */
    const CLASS_NAME_HOLD_TRANSITIONS = 'hold-transition';
    const CLASS_NAME_APP_LOADED = 'app-loaded';
    /**
     * Class Definition
     * ====================================================
     */
    class Layout {
        _element;
        constructor(element) {
            this._element = element;
        }
        holdTransition() {
            let resizeTimer;
            window.addEventListener('resize', () => {
                document.body.classList.add(CLASS_NAME_HOLD_TRANSITIONS);
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    document.body.classList.remove(CLASS_NAME_HOLD_TRANSITIONS);
                }, 400);
            });
        }
    }
    onDOMContentLoaded(() => {
        const data = new Layout(document.body);
        data.holdTransition();
        setTimeout(() => {
            document.body.classList.add(CLASS_NAME_APP_LOADED);
        }, 400);
    });

    /**
     * --------------------------------------------
     * @file AdminLTE card-widget.ts
     * @description Card widget for AdminLTE.
     * @license MIT
     * --------------------------------------------
     */
    /**
     * Constants
     * ====================================================
     */
    const DATA_KEY$4 = 'lte.card-widget';
    const EVENT_KEY$4 = `.${DATA_KEY$4}`;
    const EVENT_COLLAPSED$2 = `collapsed${EVENT_KEY$4}`;
    const EVENT_EXPANDED$2 = `expanded${EVENT_KEY$4}`;
    const EVENT_REMOVE = `remove${EVENT_KEY$4}`;
    const EVENT_MAXIMIZED$1 = `maximized${EVENT_KEY$4}`;
    const EVENT_MINIMIZED$1 = `minimized${EVENT_KEY$4}`;
    const CLASS_NAME_CARD = 'card';
    const CLASS_NAME_COLLAPSED = 'collapsed-card';
    const CLASS_NAME_COLLAPSING = 'collapsing-card';
    const CLASS_NAME_EXPANDING = 'expanding-card';
    const CLASS_NAME_WAS_COLLAPSED = 'was-collapsed';
    const CLASS_NAME_MAXIMIZED = 'maximized-card';
    const SELECTOR_DATA_REMOVE = '[data-lte-toggle="card-remove"]';
    const SELECTOR_DATA_COLLAPSE = '[data-lte-toggle="card-collapse"]';
    const SELECTOR_DATA_MAXIMIZE = '[data-lte-toggle="card-maximize"]';
    const SELECTOR_CARD = `.${CLASS_NAME_CARD}`;
    const SELECTOR_CARD_BODY = '.card-body';
    const SELECTOR_CARD_FOOTER = '.card-footer';
    const Default$1 = {
        animationSpeed: 500,
        collapseTrigger: SELECTOR_DATA_COLLAPSE,
        removeTrigger: SELECTOR_DATA_REMOVE,
        maximizeTrigger: SELECTOR_DATA_MAXIMIZE
    };
    class CardWidget {
        _element;
        _parent;
        _clone;
        _config;
        constructor(element, config) {
            this._element = element;
            this._parent = element.closest(SELECTOR_CARD);
            if (element.classList.contains(CLASS_NAME_CARD)) {
                this._parent = element;
            }
            this._config = { ...Default$1, ...config };
        }
        collapse() {
            const event = new Event(EVENT_COLLAPSED$2);
            if (this._parent) {
                this._parent.classList.add(CLASS_NAME_COLLAPSING);
                const elm = this._parent?.querySelectorAll(`${SELECTOR_CARD_BODY}, ${SELECTOR_CARD_FOOTER}`);
                elm.forEach(el => {
                    if (el instanceof HTMLElement) {
                        slideUp(el, this._config.animationSpeed);
                    }
                });
                setTimeout(() => {
                    if (this._parent) {
                        this._parent.classList.add(CLASS_NAME_COLLAPSED);
                        this._parent.classList.remove(CLASS_NAME_COLLAPSING);
                    }
                }, this._config.animationSpeed);
            }
            this._element?.dispatchEvent(event);
        }
        expand() {
            const event = new Event(EVENT_EXPANDED$2);
            if (this._parent) {
                this._parent.classList.add(CLASS_NAME_EXPANDING);
                const elm = this._parent?.querySelectorAll(`${SELECTOR_CARD_BODY}, ${SELECTOR_CARD_FOOTER}`);
                elm.forEach(el => {
                    if (el instanceof HTMLElement) {
                        slideDown(el, this._config.animationSpeed);
                    }
                });
                setTimeout(() => {
                    if (this._parent) {
                        this._parent.classList.remove(CLASS_NAME_COLLAPSED, CLASS_NAME_EXPANDING);
                    }
                }, this._config.animationSpeed);
            }
            this._element?.dispatchEvent(event);
        }
        remove() {
            const event = new Event(EVENT_REMOVE);
            if (this._parent) {
                slideUp(this._parent, this._config.animationSpeed);
            }
            this._element?.dispatchEvent(event);
        }
        toggle() {
            if (this._parent?.classList.contains(CLASS_NAME_COLLAPSED)) {
                this.expand();
                return;
            }
            this.collapse();
        }
        maximize() {
            const event = new Event(EVENT_MAXIMIZED$1);
            if (this._parent) {
                this._parent.style.height = `${this._parent.offsetHeight}px`;
                this._parent.style.width = `${this._parent.offsetWidth}px`;
                this._parent.style.transition = 'all .15s';
                setTimeout(() => {
                    const htmlTag = document.querySelector('html');
                    if (htmlTag) {
                        htmlTag.classList.add(CLASS_NAME_MAXIMIZED);
                    }
                    if (this._parent) {
                        this._parent.classList.add(CLASS_NAME_MAXIMIZED);
                        if (this._parent.classList.contains(CLASS_NAME_COLLAPSED)) {
                            this._parent.classList.add(CLASS_NAME_WAS_COLLAPSED);
                        }
                    }
                }, 150);
            }
            this._element?.dispatchEvent(event);
        }
        minimize() {
            const event = new Event(EVENT_MINIMIZED$1);
            if (this._parent) {
                this._parent.style.height = 'auto';
                this._parent.style.width = 'auto';
                this._parent.style.transition = 'all .15s';
                setTimeout(() => {
                    const htmlTag = document.querySelector('html');
                    if (htmlTag) {
                        htmlTag.classList.remove(CLASS_NAME_MAXIMIZED);
                    }
                    if (this._parent) {
                        this._parent.classList.remove(CLASS_NAME_MAXIMIZED);
                        if (this._parent?.classList.contains(CLASS_NAME_WAS_COLLAPSED)) {
                            this._parent.classList.remove(CLASS_NAME_WAS_COLLAPSED);
                        }
                    }
                }, 10);
            }
            this._element?.dispatchEvent(event);
        }
        toggleMaximize() {
            if (this._parent?.classList.contains(CLASS_NAME_MAXIMIZED)) {
                this.minimize();
                return;
            }
            this.maximize();
        }
    }
    /**
     *
     * Data Api implementation
     * ====================================================
     */
    onDOMContentLoaded(() => {
        const collapseBtn = document.querySelectorAll(SELECTOR_DATA_COLLAPSE);
        collapseBtn.forEach(btn => {
            btn.addEventListener('click', event => {
                event.preventDefault();
                const target = event.target;
                const data = new CardWidget(target, Default$1);
                data.toggle();
            });
        });
        const removeBtn = document.querySelectorAll(SELECTOR_DATA_REMOVE);
        removeBtn.forEach(btn => {
            btn.addEventListener('click', event => {
                event.preventDefault();
                const target = event.target;
                const data = new CardWidget(target, Default$1);
                data.remove();
            });
        });
        const maxBtn = document.querySelectorAll(SELECTOR_DATA_MAXIMIZE);
        maxBtn.forEach(btn => {
            btn.addEventListener('click', event => {
                event.preventDefault();
                const target = event.target;
                const data = new CardWidget(target, Default$1);
                data.toggleMaximize();
            });
        });
    });

    /**
     * --------------------------------------------
     * @file AdminLTE treeview.ts
     * @description Treeview plugin for AdminLTE.
     * @license MIT
     * --------------------------------------------
     */
    /**
     * ------------------------------------------------------------------------
     * Constants
     * ------------------------------------------------------------------------
     */
    // const NAME = 'Treeview'
    const DATA_KEY$3 = 'lte.treeview';
    const EVENT_KEY$3 = `.${DATA_KEY$3}`;
    const EVENT_EXPANDED$1 = `expanded${EVENT_KEY$3}`;
    const EVENT_COLLAPSED$1 = `collapsed${EVENT_KEY$3}`;
    // const EVENT_LOAD_DATA_API = `load${EVENT_KEY}`
    const CLASS_NAME_MENU_OPEN$1 = 'menu-open';
    const SELECTOR_NAV_ITEM$1 = '.nav-item';
    const SELECTOR_NAV_LINK = '.nav-link';
    const SELECTOR_TREEVIEW_MENU = '.nav-treeview';
    const SELECTOR_DATA_TOGGLE$1 = '[data-lte-toggle="treeview"]';
    const Default = {
        animationSpeed: 300,
        accordion: true
    };
    /**
     * Class Definition
     * ====================================================
     */
    class Treeview {
        _element;
        _config;
        constructor(element, config) {
            this._element = element;
            this._config = { ...Default, ...config };
        }
        open() {
            const event = new Event(EVENT_EXPANDED$1);
            if (this._config.accordion) {
                const openMenuList = this._element.parentElement?.querySelectorAll(`${SELECTOR_NAV_ITEM$1}.${CLASS_NAME_MENU_OPEN$1}`);
                openMenuList?.forEach(openMenu => {
                    if (openMenu !== this._element.parentElement) {
                        openMenu.classList.remove(CLASS_NAME_MENU_OPEN$1);
                        const childElement = openMenu?.querySelector(SELECTOR_TREEVIEW_MENU);
                        if (childElement) {
                            slideUp(childElement, this._config.animationSpeed);
                        }
                    }
                });
            }
            this._element.classList.add(CLASS_NAME_MENU_OPEN$1);
            const childElement = this._element?.querySelector(SELECTOR_TREEVIEW_MENU);
            if (childElement) {
                slideDown(childElement, this._config.animationSpeed);
            }
            this._element.dispatchEvent(event);
        }
        close() {
            const event = new Event(EVENT_COLLAPSED$1);
            this._element.classList.remove(CLASS_NAME_MENU_OPEN$1);
            const childElement = this._element?.querySelector(SELECTOR_TREEVIEW_MENU);
            if (childElement) {
                slideUp(childElement, this._config.animationSpeed);
            }
            this._element.dispatchEvent(event);
        }
        toggle() {
            if (this._element.classList.contains(CLASS_NAME_MENU_OPEN$1)) {
                this.close();
            }
            else {
                this.open();
            }
        }
    }
    /**
     * ------------------------------------------------------------------------
     * Data Api implementation
     * ------------------------------------------------------------------------
     */
    onDOMContentLoaded(() => {
        const button = document.querySelectorAll(SELECTOR_DATA_TOGGLE$1);
        button.forEach(btn => {
            btn.addEventListener('click', event => {
                const target = event.target;
                const targetItem = target.closest(SELECTOR_NAV_ITEM$1);
                const targetLink = target.closest(SELECTOR_NAV_LINK);
                const lteToggleElement = event.currentTarget;
                if (target?.getAttribute('href') === '#' || targetLink?.getAttribute('href') === '#') {
                    event.preventDefault();
                }
                if (targetItem) {
                    // Read data attributes
                    const accordionAttr = lteToggleElement.dataset.accordion;
                    const animationSpeedAttr = lteToggleElement.dataset.animationSpeed;
                    // Build config from data attributes, fallback to Default
                    const config = {
                        accordion: accordionAttr === undefined ? Default.accordion : accordionAttr === 'true',
                        animationSpeed: animationSpeedAttr === undefined ? Default.animationSpeed : Number(animationSpeedAttr)
                    };
                    const data = new Treeview(targetItem, config);
                    data.toggle();
                }
            });
        });
    });

    /**
     * --------------------------------------------
     * @file AdminLTE direct-chat.ts
     * @description Direct chat for AdminLTE.
     * @license MIT
     * --------------------------------------------
     */
    /**
     * Constants
     * ====================================================
     */
    const DATA_KEY$2 = 'lte.direct-chat';
    const EVENT_KEY$2 = `.${DATA_KEY$2}`;
    const EVENT_EXPANDED = `expanded${EVENT_KEY$2}`;
    const EVENT_COLLAPSED = `collapsed${EVENT_KEY$2}`;
    const SELECTOR_DATA_TOGGLE = '[data-lte-toggle="chat-pane"]';
    const SELECTOR_DIRECT_CHAT = '.direct-chat';
    const CLASS_NAME_DIRECT_CHAT_OPEN = 'direct-chat-contacts-open';
    /**
     * Class Definition
     * ====================================================
     */
    class DirectChat {
        _element;
        constructor(element) {
            this._element = element;
        }
        toggle() {
            if (this._element.classList.contains(CLASS_NAME_DIRECT_CHAT_OPEN)) {
                const event = new Event(EVENT_COLLAPSED);
                this._element.classList.remove(CLASS_NAME_DIRECT_CHAT_OPEN);
                this._element.dispatchEvent(event);
            }
            else {
                const event = new Event(EVENT_EXPANDED);
                this._element.classList.add(CLASS_NAME_DIRECT_CHAT_OPEN);
                this._element.dispatchEvent(event);
            }
        }
    }
    /**
     *
     * Data Api implementation
     * ====================================================
     */
    onDOMContentLoaded(() => {
        const button = document.querySelectorAll(SELECTOR_DATA_TOGGLE);
        button.forEach(btn => {
            btn.addEventListener('click', event => {
                event.preventDefault();
                const target = event.target;
                const chatPane = target.closest(SELECTOR_DIRECT_CHAT);
                if (chatPane) {
                    const data = new DirectChat(chatPane);
                    data.toggle();
                }
            });
        });
    });

    /**
     * --------------------------------------------
     * @file AdminLTE fullscreen.ts
     * @description Fullscreen plugin for AdminLTE.
     * @license MIT
     * --------------------------------------------
     */
    /**
     * Constants
     * ============================================================================
     */
    const DATA_KEY$1 = 'lte.fullscreen';
    const EVENT_KEY$1 = `.${DATA_KEY$1}`;
    const EVENT_MAXIMIZED = `maximized${EVENT_KEY$1}`;
    const EVENT_MINIMIZED = `minimized${EVENT_KEY$1}`;
    const SELECTOR_FULLSCREEN_TOGGLE = '[data-lte-toggle="fullscreen"]';
    const SELECTOR_MAXIMIZE_ICON = '[data-lte-icon="maximize"]';
    const SELECTOR_MINIMIZE_ICON = '[data-lte-icon="minimize"]';
    /**
     * Class Definition.
     * ============================================================================
     */
    class FullScreen {
        _element;
        _config;
        constructor(element, config) {
            this._element = element;
            this._config = config;
        }
        inFullScreen() {
            const event = new Event(EVENT_MAXIMIZED);
            const iconMaximize = document.querySelector(SELECTOR_MAXIMIZE_ICON);
            const iconMinimize = document.querySelector(SELECTOR_MINIMIZE_ICON);
            void document.documentElement.requestFullscreen();
            if (iconMaximize) {
                iconMaximize.style.display = 'none';
            }
            if (iconMinimize) {
                iconMinimize.style.display = 'block';
            }
            this._element.dispatchEvent(event);
        }
        outFullscreen() {
            const event = new Event(EVENT_MINIMIZED);
            const iconMaximize = document.querySelector(SELECTOR_MAXIMIZE_ICON);
            const iconMinimize = document.querySelector(SELECTOR_MINIMIZE_ICON);
            void document.exitFullscreen();
            if (iconMaximize) {
                iconMaximize.style.display = 'block';
            }
            if (iconMinimize) {
                iconMinimize.style.display = 'none';
            }
            this._element.dispatchEvent(event);
        }
        toggleFullScreen() {
            if (document.fullscreenEnabled) {
                if (document.fullscreenElement) {
                    this.outFullscreen();
                }
                else {
                    this.inFullScreen();
                }
            }
        }
    }
    /**
     * Data Api implementation
     * ============================================================================
     */
    onDOMContentLoaded(() => {
        const buttons = document.querySelectorAll(SELECTOR_FULLSCREEN_TOGGLE);
        buttons.forEach(btn => {
            btn.addEventListener('click', event => {
                event.preventDefault();
                const target = event.target;
                const button = target.closest(SELECTOR_FULLSCREEN_TOGGLE);
                if (button) {
                    const data = new FullScreen(button, undefined);
                    data.toggleFullScreen();
                }
            });
        });
    });

    /**
     * --------------------------------------------
     * @file AdminLTE push-menu.ts
     * @description Push menu for AdminLTE.
     * @license MIT
     * --------------------------------------------
     */
    /**
     * ------------------------------------------------------------------------
     * Constants
     * ------------------------------------------------------------------------
     */
    const DATA_KEY = 'lte.push-menu';
    const EVENT_KEY = `.${DATA_KEY}`;
    const EVENT_OPEN = `open${EVENT_KEY}`;
    const EVENT_COLLAPSE = `collapse${EVENT_KEY}`;
    const CLASS_NAME_SIDEBAR_MINI = 'sidebar-mini';
    const CLASS_NAME_SIDEBAR_COLLAPSE = 'sidebar-collapse';
    const CLASS_NAME_SIDEBAR_OPEN = 'sidebar-open';
    const CLASS_NAME_SIDEBAR_EXPAND = 'sidebar-expand';
    const CLASS_NAME_SIDEBAR_OVERLAY = 'sidebar-overlay';
    const CLASS_NAME_MENU_OPEN = 'menu-open';
    const SELECTOR_APP_SIDEBAR = '.app-sidebar';
    const SELECTOR_SIDEBAR_MENU = '.sidebar-menu';
    const SELECTOR_NAV_ITEM = '.nav-item';
    const SELECTOR_NAV_TREEVIEW = '.nav-treeview';
    const SELECTOR_APP_WRAPPER = '.app-wrapper';
    const SELECTOR_SIDEBAR_EXPAND = `[class*="${CLASS_NAME_SIDEBAR_EXPAND}"]`;
    const SELECTOR_SIDEBAR_TOGGLE = '[data-lte-toggle="sidebar"]';
    const Defaults = {
        sidebarBreakpoint: 992
    };
    /**
     * Class Definition
     * ====================================================
     */
    class PushMenu {
        _element;
        _config;
        constructor(element, config) {
            this._element = element;
            this._config = { ...Defaults, ...config };
        }
        menusClose() {
            const navTreeview = document.querySelectorAll(SELECTOR_NAV_TREEVIEW);
            navTreeview.forEach(navTree => {
                navTree.style.removeProperty('display');
                navTree.style.removeProperty('height');
            });
            const navSidebar = document.querySelector(SELECTOR_SIDEBAR_MENU);
            const navItem = navSidebar?.querySelectorAll(SELECTOR_NAV_ITEM);
            if (navItem) {
                navItem.forEach(navI => {
                    navI.classList.remove(CLASS_NAME_MENU_OPEN);
                });
            }
        }
        expand() {
            const event = new Event(EVENT_OPEN);
            document.body.classList.remove(CLASS_NAME_SIDEBAR_COLLAPSE);
            document.body.classList.add(CLASS_NAME_SIDEBAR_OPEN);
            this._element.dispatchEvent(event);
        }
        collapse() {
            const event = new Event(EVENT_COLLAPSE);
            document.body.classList.remove(CLASS_NAME_SIDEBAR_OPEN);
            document.body.classList.add(CLASS_NAME_SIDEBAR_COLLAPSE);
            this._element.dispatchEvent(event);
        }
        addSidebarBreakPoint() {
            const sidebarExpandList = document.querySelector(SELECTOR_SIDEBAR_EXPAND)?.classList ?? [];
            const sidebarExpand = Array.from(sidebarExpandList).find(className => className.startsWith(CLASS_NAME_SIDEBAR_EXPAND)) ?? '';
            const sidebar = document.getElementsByClassName(sidebarExpand)[0];
            const sidebarContent = globalThis.getComputedStyle(sidebar, '::before').getPropertyValue('content');
            this._config = { ...this._config, sidebarBreakpoint: Number(sidebarContent.replace(/[^\d.-]/g, '')) };
            if (window.innerWidth <= this._config.sidebarBreakpoint) {
                this.collapse();
            }
            else {
                if (!document.body.classList.contains(CLASS_NAME_SIDEBAR_MINI)) {
                    this.expand();
                }
                if (document.body.classList.contains(CLASS_NAME_SIDEBAR_MINI) && document.body.classList.contains(CLASS_NAME_SIDEBAR_COLLAPSE)) {
                    this.collapse();
                }
            }
        }
        toggle() {
            if (document.body.classList.contains(CLASS_NAME_SIDEBAR_COLLAPSE)) {
                this.expand();
            }
            else {
                this.collapse();
            }
        }
        init() {
            this.addSidebarBreakPoint();
        }
    }
    /**
     * ------------------------------------------------------------------------
     * Data Api implementation
     * ------------------------------------------------------------------------
     */
    onDOMContentLoaded(() => {
        const sidebar = document?.querySelector(SELECTOR_APP_SIDEBAR);
        if (sidebar) {
            const data = new PushMenu(sidebar, Defaults);
            data.init();
            window.addEventListener('resize', () => {
                data.init();
            });
        }
        const sidebarOverlay = document.createElement('div');
        sidebarOverlay.className = CLASS_NAME_SIDEBAR_OVERLAY;
        document.querySelector(SELECTOR_APP_WRAPPER)?.append(sidebarOverlay);
        let isTouchMoved = false;
        sidebarOverlay.addEventListener('touchstart', () => {
            isTouchMoved = false;
        }, { passive: true });
        sidebarOverlay.addEventListener('touchmove', () => {
            isTouchMoved = true;
        }, { passive: true });
        sidebarOverlay.addEventListener('touchend', event => {
            if (!isTouchMoved) {
                event.preventDefault();
                const target = event.currentTarget;
                const data = new PushMenu(target, Defaults);
                data.collapse();
            }
        }, { passive: false });
        sidebarOverlay.addEventListener('click', event => {
            event.preventDefault();
            const target = event.currentTarget;
            const data = new PushMenu(target, Defaults);
            data.collapse();
        });
        const fullBtn = document.querySelectorAll(SELECTOR_SIDEBAR_TOGGLE);
        fullBtn.forEach(btn => {
            btn.addEventListener('click', event => {
                event.preventDefault();
                let button = event.currentTarget;
                if (button?.dataset.lteToggle !== 'sidebar') {
                    button = button?.closest(SELECTOR_SIDEBAR_TOGGLE);
                }
                if (button) {
                    event?.preventDefault();
                    const data = new PushMenu(button, Defaults);
                    data.toggle();
                }
            });
        });
    });

    /**
     * AdminLTE Accessibility Module
     * WCAG 2.1 AA Compliance Features
     */
    class AccessibilityManager {
        config;
        liveRegion = null;
        focusHistory = [];
        constructor(config = {}) {
            this.config = {
                announcements: true,
                skipLinks: true,
                focusManagement: true,
                keyboardNavigation: true,
                reducedMotion: true,
                ...config
            };
            this.init();
        }
        init() {
            if (this.config.announcements) {
                this.createLiveRegion();
            }
            if (this.config.skipLinks) {
                this.addSkipLinks();
            }
            if (this.config.focusManagement) {
                this.initFocusManagement();
            }
            if (this.config.keyboardNavigation) {
                this.initKeyboardNavigation();
            }
            if (this.config.reducedMotion) {
                this.respectReducedMotion();
            }
            this.initErrorAnnouncements();
            this.initTableAccessibility();
            this.initFormAccessibility();
        }
        // WCAG 4.1.3: Status Messages
        createLiveRegion() {
            if (this.liveRegion)
                return;
            this.liveRegion = document.createElement('div');
            this.liveRegion.id = 'live-region';
            this.liveRegion.className = 'live-region';
            this.liveRegion.setAttribute('aria-live', 'polite');
            this.liveRegion.setAttribute('aria-atomic', 'true');
            this.liveRegion.setAttribute('role', 'status');
            document.body.append(this.liveRegion);
        }
        // WCAG 2.4.1: Bypass Blocks
        addSkipLinks() {
            const skipLinksContainer = document.createElement('div');
            skipLinksContainer.className = 'skip-links';
            const skipToMain = document.createElement('a');
            skipToMain.href = '#main';
            skipToMain.className = 'skip-link';
            skipToMain.textContent = 'Skip to main content';
            const skipToNav = document.createElement('a');
            skipToNav.href = '#navigation';
            skipToNav.className = 'skip-link';
            skipToNav.textContent = 'Skip to navigation';
            skipLinksContainer.append(skipToMain);
            skipLinksContainer.append(skipToNav);
            document.body.insertBefore(skipLinksContainer, document.body.firstChild);
            // Ensure targets exist and are focusable
            this.ensureSkipTargets();
        }
        ensureSkipTargets() {
            const main = document.querySelector('#main, main, [role="main"]');
            if (main && !main.id) {
                main.id = 'main';
            }
            if (main && !main.hasAttribute('tabindex')) {
                main.setAttribute('tabindex', '-1');
            }
            const nav = document.querySelector('#navigation, nav, [role="navigation"]');
            if (nav && !nav.id) {
                nav.id = 'navigation';
            }
            if (nav && !nav.hasAttribute('tabindex')) {
                nav.setAttribute('tabindex', '-1');
            }
        }
        // WCAG 2.4.3: Focus Order & 2.4.7: Focus Visible
        initFocusManagement() {
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Tab') {
                    this.handleTabNavigation(event);
                }
                if (event.key === 'Escape') {
                    this.handleEscapeKey(event);
                }
            });
            // Focus management for modals and dropdowns
            this.initModalFocusManagement();
            this.initDropdownFocusManagement();
        }
        handleTabNavigation(event) {
            const focusableElements = this.getFocusableElements();
            const currentIndex = focusableElements.indexOf(document.activeElement);
            if (event.shiftKey) {
                // Shift+Tab (backward)
                if (currentIndex <= 0) {
                    event.preventDefault();
                    focusableElements.at(-1)?.focus();
                }
            }
            else if (currentIndex >= focusableElements.length - 1) {
                // Tab (forward)
                event.preventDefault();
                focusableElements[0]?.focus();
            }
        }
        getFocusableElements() {
            const selector = [
                'a[href]',
                'button:not([disabled])',
                'input:not([disabled])',
                'select:not([disabled])',
                'textarea:not([disabled])',
                '[tabindex]:not([tabindex="-1"])',
                '[contenteditable="true"]'
            ].join(', ');
            return Array.from(document.querySelectorAll(selector));
        }
        handleEscapeKey(event) {
            // Close modals, dropdowns, etc.
            const activeModal = document.querySelector('.modal.show');
            const activeDropdown = document.querySelector('.dropdown-menu.show');
            if (activeModal) {
                const closeButton = activeModal.querySelector('[data-bs-dismiss="modal"]');
                closeButton?.click();
                event.preventDefault();
            }
            else if (activeDropdown) {
                const toggleButton = document.querySelector('[data-bs-toggle="dropdown"][aria-expanded="true"]');
                toggleButton?.click();
                event.preventDefault();
            }
        }
        // WCAG 2.1.1: Keyboard Access
        initKeyboardNavigation() {
            // Add keyboard support for custom components
            document.addEventListener('keydown', (event) => {
                const target = event.target;
                // Handle arrow key navigation for menus
                if (target.closest('.nav, .navbar-nav, .dropdown-menu')) {
                    this.handleMenuNavigation(event);
                }
                // Handle Enter and Space for custom buttons
                if ((event.key === 'Enter' || event.key === ' ') && target.hasAttribute('role') && target.getAttribute('role') === 'button' && !target.matches('button, input[type="button"], input[type="submit"]')) {
                    event.preventDefault();
                    target.click();
                }
            });
        }
        handleMenuNavigation(event) {
            if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }
            const currentElement = event.target;
            const menuItems = Array.from(currentElement.closest('.nav, .navbar-nav, .dropdown-menu')?.querySelectorAll('a, button') || []);
            const currentIndex = menuItems.indexOf(currentElement);
            let nextIndex;
            switch (event.key) {
                case 'ArrowDown':
                case 'ArrowRight': {
                    nextIndex = currentIndex < menuItems.length - 1 ? currentIndex + 1 : 0;
                    break;
                }
                case 'ArrowUp':
                case 'ArrowLeft': {
                    nextIndex = currentIndex > 0 ? currentIndex - 1 : menuItems.length - 1;
                    break;
                }
                case 'Home': {
                    nextIndex = 0;
                    break;
                }
                case 'End': {
                    nextIndex = menuItems.length - 1;
                    break;
                }
                default: {
                    return;
                }
            }
            event.preventDefault();
            menuItems[nextIndex]?.focus();
        }
        // WCAG 2.3.3: Animation from Interactions
        respectReducedMotion() {
            const prefersReducedMotion = globalThis.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (prefersReducedMotion) {
                document.body.classList.add('reduce-motion');
                // Disable smooth scrolling
                document.documentElement.style.scrollBehavior = 'auto';
                // Reduce animation duration
                const style = document.createElement('style');
                style.textContent = `
        *, *::before, *::after {
          animation-duration: 0.01ms !important;
          animation-iteration-count: 1 !important;
          transition-duration: 0.01ms !important;
        }
      `;
                document.head.append(style);
            }
        }
        // WCAG 3.3.1: Error Identification
        initErrorAnnouncements() {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            const element = node;
                            // Check for error messages
                            if (element.matches('.alert-danger, .invalid-feedback, .error')) {
                                this.announce(element.textContent || 'Error occurred', 'assertive');
                            }
                            // Check for success messages
                            if (element.matches('.alert-success, .success')) {
                                this.announce(element.textContent || 'Success', 'polite');
                            }
                        }
                    });
                });
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
        // WCAG 1.3.1: Info and Relationships
        initTableAccessibility() {
            document.querySelectorAll('table').forEach((table) => {
                // Add table role if missing
                if (!table.hasAttribute('role')) {
                    table.setAttribute('role', 'table');
                }
                // Ensure headers have proper scope
                table.querySelectorAll('th').forEach((th) => {
                    if (!th.hasAttribute('scope')) {
                        const isInThead = th.closest('thead');
                        const isFirstColumn = th.cellIndex === 0;
                        if (isInThead) {
                            th.setAttribute('scope', 'col');
                        }
                        else if (isFirstColumn) {
                            th.setAttribute('scope', 'row');
                        }
                    }
                });
                // Add caption if missing but title exists
                if (!table.querySelector('caption') && table.hasAttribute('title')) {
                    const caption = document.createElement('caption');
                    caption.textContent = table.getAttribute('title') || '';
                    table.insertBefore(caption, table.firstChild);
                }
            });
        }
        // WCAG 3.3.2: Labels or Instructions
        initFormAccessibility() {
            document.querySelectorAll('input, select, textarea').forEach((input) => {
                const htmlInput = input;
                // Ensure all inputs have labels
                if (!htmlInput.labels?.length && !htmlInput.hasAttribute('aria-label') && !htmlInput.hasAttribute('aria-labelledby')) {
                    const placeholder = htmlInput.getAttribute('placeholder');
                    if (placeholder) {
                        htmlInput.setAttribute('aria-label', placeholder);
                    }
                }
                // Add required indicators
                if (htmlInput.hasAttribute('required')) {
                    const label = htmlInput.labels?.[0];
                    if (label && !label.querySelector('.required-indicator')) {
                        const indicator = document.createElement('span');
                        indicator.className = 'required-indicator sr-only';
                        indicator.textContent = ' (required)';
                        label.append(indicator);
                    }
                }
                // Handle invalid states
                htmlInput.addEventListener('invalid', () => {
                    this.handleFormError(htmlInput);
                });
            });
        }
        handleFormError(input) {
            const errorId = `${input.id || input.name}-error`;
            let errorElement = document.getElementById(errorId);
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.id = errorId;
                errorElement.className = 'invalid-feedback';
                errorElement.setAttribute('role', 'alert');
                input.parentNode?.insertBefore(errorElement, input.nextSibling);
            }
            errorElement.textContent = input.validationMessage;
            input.setAttribute('aria-describedby', errorId);
            input.classList.add('is-invalid');
            this.announce(`Error in ${input.labels?.[0]?.textContent || input.name}: ${input.validationMessage}`, 'assertive');
        }
        // Modal focus management
        initModalFocusManagement() {
            document.addEventListener('shown.bs.modal', (event) => {
                const modal = event.target;
                const focusableElements = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (focusableElements.length > 0) {
                    focusableElements[0].focus();
                }
                // Store previous focus
                this.focusHistory.push(document.activeElement);
            });
            document.addEventListener('hidden.bs.modal', () => {
                // Restore previous focus
                const previousElement = this.focusHistory.pop();
                if (previousElement) {
                    previousElement.focus();
                }
            });
        }
        // Dropdown focus management
        initDropdownFocusManagement() {
            document.addEventListener('shown.bs.dropdown', (event) => {
                const dropdown = event.target;
                const menu = dropdown.querySelector('.dropdown-menu');
                const firstItem = menu?.querySelector('a, button');
                if (firstItem) {
                    firstItem.focus();
                }
            });
        }
        // Public API methods
        announce(message, priority = 'polite') {
            if (!this.liveRegion) {
                this.createLiveRegion();
            }
            if (this.liveRegion) {
                this.liveRegion.setAttribute('aria-live', priority);
                this.liveRegion.textContent = message;
                // Clear after announcement
                setTimeout(() => {
                    if (this.liveRegion) {
                        this.liveRegion.textContent = '';
                    }
                }, 1000);
            }
        }
        focusElement(selector) {
            const element = document.querySelector(selector);
            if (element) {
                element.focus();
                // Ensure element is visible
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        trapFocus(container) {
            const focusableElements = container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            const focusableArray = Array.from(focusableElements);
            const firstElement = focusableArray[0];
            const lastElement = focusableArray.at(-1);
            container.addEventListener('keydown', (event) => {
                if (event.key === 'Tab') {
                    if (event.shiftKey) {
                        if (document.activeElement === firstElement) {
                            lastElement?.focus();
                            event.preventDefault();
                        }
                    }
                    else if (document.activeElement === lastElement) {
                        firstElement.focus();
                        event.preventDefault();
                    }
                }
            });
        }
        addLandmarks() {
            // Add main landmark if missing
            const main = document.querySelector('main');
            if (!main) {
                const appMain = document.querySelector('.app-main');
                if (appMain) {
                    appMain.setAttribute('role', 'main');
                    appMain.id = 'main';
                }
            }
            // Add navigation landmarks
            document.querySelectorAll('.navbar-nav, .nav').forEach((nav, index) => {
                if (!nav.hasAttribute('role')) {
                    nav.setAttribute('role', 'navigation');
                }
                if (!nav.hasAttribute('aria-label')) {
                    nav.setAttribute('aria-label', `Navigation ${index + 1}`);
                }
            });
            // Add search landmark
            const searchForm = document.querySelector('form[role="search"], .navbar-search');
            if (searchForm && !searchForm.hasAttribute('role')) {
                searchForm.setAttribute('role', 'search');
            }
        }
    }
    // Initialize accessibility when DOM is ready
    const initAccessibility = (config) => {
        return new AccessibilityManager(config);
    };

    /**
     * AdminLTE v4.0.0-rc3
     * Author: Colorlib
     * Website: AdminLTE.io <https://adminlte.io>
     * License: Open source - MIT <https://opensource.org/licenses/MIT>
     */
    onDOMContentLoaded(() => {
        /**
         * Initialize AdminLTE Core Components
         * -------------------------------
         */
        const layout = new Layout(document.body);
        layout.holdTransition();
        /**
         * Initialize Accessibility Features - WCAG 2.1 AA Compliance
         * --------------------------------------------------------
         */
        const accessibilityManager = initAccessibility({
            announcements: true,
            skipLinks: true,
            focusManagement: true,
            keyboardNavigation: true,
            reducedMotion: true
        });
        // Add semantic landmarks
        accessibilityManager.addLandmarks();
        // Mark app as loaded after initialization
        setTimeout(() => {
            document.body.classList.add('app-loaded');
        }, 400);
    });

    exports.CardWidget = CardWidget;
    exports.DirectChat = DirectChat;
    exports.FullScreen = FullScreen;
    exports.Layout = Layout;
    exports.PushMenu = PushMenu;
    exports.Treeview = Treeview;
    exports.initAccessibility = initAccessibility;

}));
//# sourceMappingURL=adminlte.js.map
 ```

## Archivo: ./public/js/categories.js
 ```javascript
document.addEventListener("DOMContentLoaded", function() {
// Inicializar Modal de Categoría (Crear/Editar)
    const modalCatInstance = new bootstrap.Modal(document.getElementById('modalCat'));
    
    // Exponer funciones globalmente
    window.openModal = function(data = null) {
        document.getElementById('formCat').reset();
        const modalTitle = document.getElementById('modalTitle');
        
        if(data) {
            modalTitle.innerHTML = '<i class="fas fa-edit text-warning me-2"></i> Editar Categoría';
            document.getElementById('action').value = 'update';
            document.getElementById('catId').value = data.id;
            document.getElementById('catName').value = data.name;
            // Pobar el nuevo campo de descripción
            document.getElementById('catDesc').value = data.description || ''; 
        } else {
            modalTitle.innerHTML = '<i class="fas fa-plus-circle me-2"></i> Nueva Categoría';
            document.getElementById('action').value = 'create';
            document.getElementById('catId').value = '';
            document.getElementById('catDesc').value = '';
        }
        modalCatInstance.show();
    };

    // Reemplazamos el modal de Bootstrap por SweetAlert2 para eliminar
    window.confirmDelete = function(id, name) {
        Swal.fire({
            title: '¿Eliminar Categoría?',
            html: `Estás a punto de eliminar <strong>${name}</strong>.<br>Los productos asociados podrían quedar sin categoría. Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash me-1"></i> Sí, Eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id', id);

                fetch('actions/actions_category.php', { 
                    method: 'POST', 
                    body: fd 
                })
                .then(response => response.json())
                .then(res => {
                    if (res.status) {
                        Swal.fire({
                            title: '¡Eliminada!',
                            text: res.message,
                            icon: 'success',
                            confirmButtonColor: '#198754'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Ocurrió un error al intentar comunicar con el servidor.', 'error');
                });
            }
        });
    };

    // Submit del Formulario (Crear/Actualizar) con SweetAlert2
    document.getElementById('formCat').onsubmit = function(e) {
        e.preventDefault();
        
        const btnSubmit = this.querySelector('button[type="submit"]');
        const originalText = btnSubmit.innerHTML;
        
        // Estado de carga en el botón
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando...';

        fetch('actions/actions_category.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(response => response.json())
        .then(res => {
            if(res.status) {
                modalCatInstance.hide();
                Swal.fire({
                    title: '¡Éxito!',
                    text: res.message,
                    icon: 'success',
                    confirmButtonColor: '#198754'
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Ocurrió un error al intentar guardar la categoría.', 'error');
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = originalText;
        });
    };
}); ```

## Archivo: ./public/js/config.js
 ```javascript
// --- Lógica de Modo Oscuro ---

// Referencias a los elementos
const htmlElement = document.documentElement;
const darkModeSwitch = document.getElementById('darkModeSwitch');

// 1. Cargar preferencia guardada (localStorage tiene prioridad)
const currentTheme = localStorage.getItem('theme') || 'dark';
htmlElement.setAttribute('data-bs-theme', currentTheme);

// Verificar si el switch existe en el DOM antes de asignar el estado
if (darkModeSwitch) {
    darkModeSwitch.checked = (currentTheme === 'dark');

    // 2. Escuchar el cambio manual del usuario
    darkModeSwitch.addEventListener('change', () => {
        const newTheme = darkModeSwitch.checked ? 'dark' : 'light';
        
        // Aplicar cambio visual inmediato
        htmlElement.setAttribute('data-bs-theme', newTheme);
        
        // Guardar en el navegador
        localStorage.setItem('theme', newTheme);
    });
}

// --- Funciones de Interacción ---

/**
 * Función para confirmar acciones críticas
 * @param {string} task - Descripción de la tarea a realizar
 */

function confirmAction(task, actionType) {
    if(confirm("¿Estás completamente seguro de " + task + "? Esta acción eliminará registros permanentemente y es irreversible.")) {
        
        // Crear un formulario dinámico para enviarlo por POST de forma segura
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'actions/actions_critical.php'; // Crearemos este archivo para tareas peligrosas

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'critical_action';
        input.value = actionType;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
} ```

## Archivo: ./public/js/credits.js
 ```javascript
     const chartDates = window.APP_BCVRATE;

    $(document).ready(function() {
        $('#creditsTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            order: [[4, 'desc']]
        });

        // Calculadora en vivo en el modal
        $('#pay_amount').on('input', function() {
            let usd = parseFloat($(this).val()) || 0;
            $('#pay_bs_conversion').text(`Equivale a: Bs ${(usd * bcvRate).toFixed(2)}`);
        });

        // Enviar pago por AJAX
        $('#formPayment').on('submit', function(e) {
            e.preventDefault();
            $('#btnSubmitPayment').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');

            $.ajax({
                url: 'actions/actions_credit.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if(res.status) {
                        Swal.fire('¡Éxito!', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                        $('#btnSubmitPayment').prop('disabled', false).text('Confirmar Pago');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
                    $('#btnSubmitPayment').prop('disabled', false).text('Confirmar Pago');
                }
            });
        });
    });

    function openPaymentModal(id, maxAmount, customer) {
        $('#pay_credit_id').val(id);
        $('#pay_customer_name').text(customer);
        $('#pay_balance_display').text('$' + parseFloat(maxAmount).toFixed(2));
        $('#pay_amount').attr('max', maxAmount).val('');
        $('#pay_bs_conversion').text('Equivale a: Bs 0.00');
        
        let modal = new bootstrap.Modal(document.getElementById('modalPayment'));
        modal.show();
    }

    function viewHistory(credit_id) {
        $('#historyTableBody').html('<tr><td colspan="5"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>');
        let modal = new bootstrap.Modal(document.getElementById('modalHistory'));
        modal.show();

        $.ajax({
            url: 'actions/actions_credit.php',
            type: 'POST',
            data: { action: 'get_history', credit_id: credit_id },
            dataType: 'json',
            success: function(res) {
                if(res.status && res.data.length > 0) {
                    let html = '';
                    res.data.forEach(p => {
                        html += `<tr>
                            <td>${new Date(p.created_at).toLocaleString('es-VE')}</td>
                            <td class="text-success fw-bold">$${parseFloat(p.amount_usd).toFixed(2)}</td>
                            <td>Bs ${parseFloat(p.amount_bs).toFixed(2)}</td>
                            <td class="text-capitalize">${p.payment_method.replace('_', ' ')}</td>
                            <td><span class="badge bg-secondary">${p.username || 'N/A'}</span></td>
                        </tr>`;
                    });
                    $('#historyTableBody').html(html);
                } else {
                    $('#historyTableBody').html('<tr><td colspan="5" class="text-muted">No hay pagos registrados.</td></tr>');
                }
            }
        });
    } ```

## Archivo: ./public/js/customers.js
 ```javascript
let modalCustomerInstance;
let modalViewInstance;
document.addEventListener("DOMContentLoaded", function() {
    // Inicializar modal
    modalCustomerInstance = new bootstrap.Modal(document.getElementById('modalCustomerForm'));
    modalViewInstance = new bootstrap.Modal(document.getElementById("modalView"));
    
    // Inicializar DataTable
    if ($.fn.DataTable) {
        $('#customersTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            responsive: true,
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: 3 }] // Desactiva orden en columna acciones
        });
    }

    // Resetear formulario al abrir el modal para crear nuevo (clickeando el botón del header)
    document.querySelector('[data-bs-target="#modalCustomerForm"]').addEventListener('click', () => {
        document.getElementById('formCustomer').reset();
        document.getElementById('customerAction').value = 'create';
        document.getElementById('customerId').value = '';
        document.getElementById('modalCustomerTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i> Nuevo Cliente';
    });

    // Envío del formulario (Crear/Editar)
    document.getElementById('formCustomer').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btnSubmit = document.getElementById('btnSaveCustomer');
        const originalText = btnSubmit.innerText;
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

        const formData = new FormData(this);

        fetch('actions/actions_customer.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if(res.status) {
                Swal.fire('¡Éxito!', res.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
                btnSubmit.disabled = false;
                btnSubmit.innerText = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Problema de conexión con el servidor.', 'error');
            btnSubmit.disabled = false;
            btnSubmit.innerText = originalText;
        });
    });
});

// Función para abrir modal en modo Edición
function openCustomerModal(data) {
    document.getElementById('customerAction').value = 'update';
    document.getElementById('customerId').value = data.id;
    document.getElementById('customerName').value = data.name;
    document.getElementById('customerDoc').value = data.document || '';
    document.getElementById('customerPhone').value = data.phone || '';
    
    document.getElementById('modalCustomerTitle').innerHTML = '<i class="fas fa-user-edit text-warning me-2"></i> Editar Cliente';
    
    modalCustomerInstance.show();
}

// Función para Eliminar con SweetAlert2
function deleteCustomer(id, name) {
    Swal.fire({
        title: '¿Eliminar cliente?',
        html: `Estás a punto de eliminar a <strong>${name}</strong>.<br>Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            fetch('actions/actions_customer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(res => {
                if(res.status) {
                    Swal.fire('¡Eliminado!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Problema al intentar eliminar.', 'error');
            });
        }
    });
}

function viewCustomer(c) {
    if (!modalViewInstance) return alert('El modal aún no se ha inicializado.');

    // Validar si los campos están vacíos para mostrar un texto por defecto
    const documentText = c.document ? c.document : '<span class="text-muted fst-italic">No registrado</span>';
    const phoneText = c.phone ? c.phone : '<span class="text-muted fst-italic">No registrado</span>';

    const content = `
        <div class="text-center mb-4">
            <i class="fas fa-user-circle text-secondary" style="font-size: 4rem;"></i>
        </div>
        <ul class="list-group list-group-flush mb-3">
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Nombre:</span>
                <span class="fw-bold text-primary">${c.name}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Cédula / RIF:</span>
                <span class="fw-bold">${documentText}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Teléfono:</span>
                <span class="fw-bold">${phoneText}</span>
            </li>
        </ul>
    `;
    
    document.getElementById('viewContent').innerHTML = content;
    modalViewInstance.show();
} ```

## Archivo: ./public/js/dashboard.js
 ```javascript
    document.addEventListener("DOMContentLoaded", function() {
        // Obtenemos los datos inyectados desde PHP
        const chartSales = window.APP_JS_CHARTSALE;
        const chartDates = window.APP_JS_CHARTDATES;

        const sales_chart_options = {
            series: [{
                name: 'Ventas (USD)',
                data: chartSales
            }],
            chart: {
                height: 300,
                type: 'area',
                toolbar: { show: false },
                fontFamily: 'inherit',
                background: 'transparent'
            },
            colors: ['#0d6efd'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.3,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            xaxis: {
                type: 'category',
                categories: chartDates,
                labels: { style: { colors: '#adb5bd' } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: { 
                    style: { colors: '#adb5bd' },
                    formatter: function(val) { return "$" + val.toFixed(2); }
                }
            },
            grid: {
                borderColor: 'rgba(255, 255, 255, 0.05)',
                strokeDashArray: 4,
            },
            tooltip: {
                theme: 'dark',
                y: { formatter: function (val) { return "$" + val.toFixed(2) } }
            }
        };

        const sales_chart = new ApexCharts(document.querySelector('#revenue-chart'), sales_chart_options);
        sales_chart.render();
    }); ```

## Archivo: ./public/js/login.js
 ```javascript
    document.addEventListener('DOMContentLoaded', function() {
      const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
      const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
      if (sidebarWrapper && typeof OverlayScrollbarsGlobal !== 'undefined') {
        OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
          scrollbars: {
            theme: 'os-theme-light',
            autoHide: 'leave',
            clickScroll: true,
          },
        });
      }
    }); ```

## Archivo: ./public/js/pos.js
 ```javascript
// Variables Globales
let cart = [];
const bcvRate = window.APP_BCV_RATE;

let modalCheckoutInstance;
let modalMessageInstance;
let modalClearCartInstance;
let modalBCVInstance;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Modales Bootstrap
    modalCheckoutInstance = new bootstrap.Modal(document.getElementById('modalCheckout'));
    modalMessageInstance = new bootstrap.Modal(document.getElementById('modalMessage'));
    modalClearCartInstance = new bootstrap.Modal(document.getElementById('modalClearCart'));
    modalBCVInstance = new bootstrap.Modal(document.getElementById('modalBCV'));
});

// --- Atajo de teclado para el buscador ---
document.addEventListener('keydown', function(e) {
    if (e.key === 'F3') {
        e.preventDefault();
        document.getElementById('searchInput').focus();
    }
});

// --- LÓGICA DE CRÉDITO Y CLIENTES ---
document.getElementById('paymentMethod').addEventListener('change', function() {
    if (this.value === 'credito') {
        $('#creditData').slideDown();
        // Evitar procesar venta si no hay cliente
        document.getElementById('btnConfirmSale').disabled = (document.getElementById('selectedCustomerId').value === '');
    } else {
        $('#creditData').slideUp();
        document.getElementById('btnConfirmSale').disabled = false; // Rehabilitar
    }
});

let searchTimer;
$('#inputSearchCustomer').on('keyup', function() {
    clearTimeout(searchTimer);
    let term = $(this).val();
    
    if(term.length < 2) {
        $('#customerResults').html('<div class="text-center text-muted p-3 small">Escribe al menos 2 letras...</div>');
        return;
    }

    $('#customerResults').html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>');

    searchTimer = setTimeout(function() {
        $.ajax({
            url: 'actions/actions_customer.php',
            type: 'POST',
            data: { action: 'search', term: term },
            dataType: 'json',
            success: function(res) {
                if(res.status && res.data.length > 0) {
                    let html = '';
                    res.data.forEach(c => {
                        html += `<button type="button" class="list-group-item list-group-item-action" 
                                    onclick="selectCustomer(${c.id}, '${c.name.replace(/'/g, "\\'")}', '${c.document ? c.document.replace(/'/g, "\\'") : ''}')">
                                    <strong>${c.name}</strong> <br>
                                    <small class="text-muted">${c.document || 'Sin Cédula/RIF'}</small>
                                 </button>`;
                    });
                    $('#customerResults').html(html);
                } else {
                    $('#customerResults').html('<div class="text-center text-danger p-3 small">No se encontraron clientes.</div>');
                }
            }
        });
    }, 400); 
});

$('#formNewCustomer').on('submit', function(e) {
    e.preventDefault();
    let btn = $('#btnSaveCustomer');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

    $.ajax({
        url: 'actions/actions_customer.php',
        type: 'POST',
        data: $(this).serialize() + '&action=create',
        dataType: 'json',
        success: function(res) {
            if(res.status) {
                selectCustomer(res.customer.id, res.customer.name, res.customer.document);
                $('#formNewCustomer')[0].reset(); 
                Swal.fire({
                    icon: 'success',
                    title: 'Cliente guardado',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Guardar y Seleccionar');
        }
    });
});

function selectCustomer(id, name, customerDoc) {
    $('#selectedCustomerId').val(id);
    let displayText = name + (customerDoc ? ' (' + customerDoc + ')' : '');
    $('#selectedCustomerDisplay').val(displayText);
    
    // Habilitar el botón de venta
    $('#btnConfirmSale').prop('disabled', false);
    
    // Cerrar el modal
    var modal = bootstrap.Modal.getInstance(document.getElementById('modalCustomer'));
    modal.hide();
}


// --- UTILIDAD: Mostrar Mensaje Genérico ---
function showMessage(text, type = 'info') {
    const icon = document.getElementById('msgIcon');
    document.getElementById('msgText').innerText = text;
    
    icon.className = 'fas fa-3x mb-3';
    if(type === 'error') {
        icon.classList.add('fa-times-circle', 'text-danger');
    } else if (type === 'success') {
        icon.classList.add('fa-check-circle', 'text-success');
    } else {
        icon.classList.add('fa-exclamation-circle', 'text-warning');
    }
    modalMessageInstance.show();
}

// --- 1. LÓGICA TASA BCV ---
function openBCVModal() {
    modalBCVInstance.show();
}

function saveRate() {
    const newRate = document.getElementById('newRateInput').value;
    modalBCVInstance.hide();
    showMessage("Tasa actualizada a: " + newRate, 'success');
    setTimeout(() => location.reload(), 1500);
}

// --- 2. LÓGICA CRUD PRODUCTOS ---
function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_price').value = data.price_base_usd;
    document.getElementById('edit_margin').value = data.profit_margin;
    document.getElementById('edit_stock').value = data.stock;
    document.getElementById('edit_image').value = data.image || '';
    document.getElementById('edit_description').value = data.description || '';
     document.getElementById('edit_sku').value = data.sku || '';
    
    modalEditInstance.show();
}

function openDeleteModal(id, name) {
    document.getElementById('deleteProductId').value = id;
    document.getElementById('deleteProductName').innerText = name;
    modalDeleteInstance.show();
}

function executeDelete() {
    modalDeleteInstance.hide();
    showMessage("Producto eliminado correctamente", 'success');
    setTimeout(() => location.reload(), 1500);
}

// --- 3. LÓGICA POS / CARRITO ---
function addToCart(id, name, price, maxStock) {
    if(maxStock <= 0) {
        showMessage("❌ Producto agotado", 'error');
        return;
    }

    let existingItem = cart.find(item => item.id === id);
    if (existingItem) {
        if (existingItem.qty < maxStock) {
            existingItem.qty++;
        } else {
            showMessage("⚠️ Stock máximo alcanzado (" + maxStock + ")", 'warning');
            return;
        }
    } else {
        cart.push({ id: id, name: name, price: price, qty: 1, max: maxStock });
    }
    renderCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

function confirmClearCart() {
    if(cart.length === 0) return;
    modalClearCartInstance.show();
}

function executeClearCart() {
    cart = [];
    renderCart();
    modalClearCartInstance.hide();
}

function renderCart() {
    let tbody = document.getElementById('cartTableBody');
    tbody.innerHTML = '';
    let totalUsd = 0;
    let count = 0;

    if (cart.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="fas fa-cart-arrow-down fa-2x mb-2 opacity-50"></i><br>El carrito está vacío</td></tr>';
    } else {
        cart.forEach((item, index) => {
            let subtotal = Math.round((item.price * item.qty) * 100) / 100;
            totalUsd += subtotal;
            count += item.qty;

            tbody.innerHTML += `
                <tr>
                    <td class="align-middle text-center fw-bold">${item.qty}</td>
                    <td class="align-middle text-truncate" style="max-width: 120px;" title="${item.name}">${item.name}</td>
                    <td class="align-middle text-end text-success fw-bold">$${subtotal.toFixed(2)}</td>
                    <td class="align-middle text-end">
                        <button class="btn btn-outline-danger btn-sm p-1" onclick="removeFromCart(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    }

    let totalBs = totalUsd * bcvRate;
    document.getElementById('totalUsdDisplay').innerText = '$' + totalUsd.toFixed(2);
    document.getElementById('totalBsDisplay').innerText = 'Bs ' + totalBs.toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('itemCount').innerText = count;
}

// --- 4. PROCESO DE COBRO (CHECKOUT) ---
function initiateCheckout() {
    if (cart.length === 0) {
        showMessage("🛒 El carrito está vacío.", 'warning');
        return;
    }

    const methodSelect = document.getElementById('paymentMethod');
    const methodName = methodSelect.options[methodSelect.selectedIndex].text;
    const methodVal = methodSelect.value;
    
    // Validación adicional: si es crédito, debe haber cliente
    if (methodVal === 'credito') {
        const custId = document.getElementById('selectedCustomerId').value;
        if (!custId) {
            showMessage("Debe seleccionar un cliente para cobrar a crédito.", 'warning');
            return;
        }
    }

    let totalUsd = 0;
    let count = 0;
    cart.forEach(item => {
        totalUsd += (item.price * item.qty);
        count += item.qty;
    });
    let totalBs = totalUsd * bcvRate;

    document.getElementById('checkoutItems').innerText = count;
    document.getElementById('checkoutMethod').innerText = methodName;
    document.getElementById('checkoutTotalBs').innerText = 'Bs ' + totalBs.toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('checkoutTotalUsd').innerText = '$' + totalUsd.toFixed(2);

    resetCheckoutModal();
    modalCheckoutInstance.show();
}

function resetCheckoutModal() {
    document.getElementById('checkoutStateConfirm').style.display = 'block';
    document.getElementById('checkoutStateResult').style.display = 'none';
    document.getElementById('checkoutSuccess').style.display = 'none';
    document.getElementById('checkoutError').style.display = 'none';
    document.getElementById('checkoutSpinner').style.display = 'block';
    document.getElementById('btnCloseCheckout').style.display = 'block';
}

function executeSale() {
    document.getElementById('checkoutStateConfirm').style.display = 'none';
    document.getElementById('checkoutStateResult').style.display = 'block';
    document.getElementById('checkoutSpinner').style.display = 'block';
    
    const closeBtn = document.getElementById('btnCloseCheckout');
    if(closeBtn) closeBtn.style.display = 'none';

    let method = document.getElementById('paymentMethod').value;
    let customerId = document.getElementById('selectedCustomerId').value;
    let dueDate = document.getElementById('creditDueDate').value;

    $.ajax({
        url: 'process_sale.php',
        type: 'POST',
        data: { 
            cart: cart, 
            payment_method: method,
            customer_id: customerId,
            due_date: dueDate
        },
        success: function(response) {
            document.getElementById('checkoutSpinner').style.display = 'none';
            if(closeBtn) closeBtn.style.display = 'block'; 

            try {
                let res = (typeof response === 'object') ? response : JSON.parse(response);

                if (res.status === "success") {
                  document.getElementById("checkoutSuccess").style.display =
                    "block";
                  document.getElementById("ticketId").innerText =
                    res.sale_id || "####";

                  // --- NUEVO: Asignar la acción de abrir el ticket al botón ---
                  const btnImprimir =
                    document.getElementById("btnImprimirTicket");
                  if (btnImprimir && res.sale_id) {
                    btnImprimir.onclick = function () {
                      window.open(`ticket.php?id=${res.sale_id}`, "_blank");
                    };
                  }
                  // -----------------------------------------------------------

                  cart = [];
                  renderCart();

                  // Limpiar campos de crédito por si acaso
                  $("#selectedCustomerId").val("");
                  $("#selectedCustomerDisplay").val("");
                  $("#creditDueDate").val("");
                } else {
                  throw new Error(
                    res.message || "Error desconocido del servidor.",
                  );
                }
            } catch (e) {
                document.getElementById('checkoutError').style.display = 'block';
                document.getElementById('checkoutErrorMessage').innerText = "Error inesperado: " + (e.message || "El servidor devolvió datos inválidos.");
            }
        },
        error: function(xhr, status, error) {
            document.getElementById('checkoutSpinner').style.display = 'none';
            if(closeBtn) closeBtn.style.display = 'block';
            document.getElementById('checkoutError').style.display = 'block';
            document.getElementById('checkoutErrorMessage').innerText = "Error de conexión (" + xhr.status + "): " + error;
        }
    });
}

// --- BUSCADOR AJAX REAL ESTILO DATATABLES ---
let searchTimeout;
const searchInput = document.getElementById('searchInput');
const productsGrid = document.getElementById('productsGrid');

searchInput.addEventListener('input', function(e) {
    const term = e.target.value.trim();
    
    productsGrid.style.opacity = '0.5';

    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        fetchProducts(term);
    }, 300);
});

function fetchProducts(term) {
    fetch(`search_products.php?term=${encodeURIComponent(term)}`)
        .then(response => response.json())
        .then(res => {
            productsGrid.style.opacity = '1';
            
            if (res.status === 'success') {
                renderProductsGrid(res.data);
            } else {
                showMessage("Error al buscar productos", "error");
            }
        })
        .catch(error => {
            productsGrid.style.opacity = '1';
            console.error("Error en la búsqueda:", error);
        });
}

function renderProductsGrid(products) {
    productsGrid.innerHTML = ''; 

    if (products.length === 0) {
        productsGrid.innerHTML = `
            <div class="col-12 text-center py-5 text-muted">
                <i class="fas fa-box-open fa-3x mb-3 opacity-50"></i><br>
                <h4>No se encontraron productos</h4>
                <p>Intenta con otro término de búsqueda.</p>
            </div>`;
        return;
    }

    products.forEach(p => {
        const price_usd = parseFloat(p.price_base_usd) * (1 + (parseFloat(p.profit_margin) / 100));
        const price_bs = price_usd * bcvRate;
        const is_stock = p.stock > 0;
        const img_url = p.image ? `${escapeHtml(p.image)}` : null;
        const desc = p.description ? escapeHtml(p.description) : 'Sin descripción';
        const sku = p.sku ? escapeHtml(p.sku) : 'N/A';
        const name = escapeHtml(p.name);
        
        const cardHtml = `
        <div class="col-6 col-md-4 col-xl-3 product-item" data-name="${name.toLowerCase()}">
            <div class="card h-100 shadow-sm border" style="cursor: pointer; transition: transform 0.2s;">
                
                <div onclick='addToCart(${p.id}, "${name.replace(/'/g, "\\'")}", ${price_usd.toFixed(2)}, ${p.stock})' class="d-flex flex-column h-100">
                    <div class="text-center bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center" style="height: 120px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                        ${img_url 
                            ? `<img src="${img_url}" class="img-fluid" style="max-height: 100%; object-fit: contain;" alt="${name}">`
                            : `<i class="fas fa-box-open fa-3x text-secondary opacity-50"></i>`
                        }
                    </div>

                    <div class="card-body p-2 d-flex flex-column text-center">
                        <h6 class="card-title text-truncate fw-bold mb-1 w-100" title="${name}">
                            ${name}
                        </h6>
                        <small class="text-muted text-truncate w-100 mb-2">${desc}</small>
                        
                        <div class="mt-auto">
                            <div class="text-success fw-bold fs-5">$${price_usd.toFixed(2)}</div>
                            <div class="text-muted small mb-2">Bs ${price_bs.toFixed(2)}</div>
                            
                            ${is_stock 
                                ? `<span class="badge text-bg-info rounded-pill">Stock: ${p.stock}</span>`
                                : `<span class="badge text-bg-danger rounded-pill">Agotado</span>`
                            }
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
        
        productsGrid.insertAdjacentHTML('beforeend', cardHtml);
    });
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
         .toString()
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
} ```

## Archivo: ./public/js/sales.js
 ```javascript
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels, // Usamos la variable global
            datasets: [{
                label: 'Ventas USD',
                data: chartValues, // Usamos la variable global
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 3,
                pointBackgroundColor: '#0d6efd',
                pointRadius: 4,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: '#1e2b37',
                    padding: 12
                }
            },
            scales: {
                y: { 
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { 
                        color: '#adb5bd', 
                        callback: v => '$' + v 
                    }
                },
                x: { 
                    grid: { display: false },
                    ticks: { color: '#adb5bd' }
                }
            }
        }
    });
}); ```

## Archivo: ./public/js/sales_history.js
 ```javascript
    // Búsqueda en la tabla (Ya lo tenías, optimizado)
document.getElementById('tableSearch').addEventListener('keyup', function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll('#historyBody tr');
    rows.forEach(row => {
        // Ignorar la fila de "No hay ventas"
        if(row.cells.length > 1) {
            row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
        }
    });
});

// Cargar detalles en el modalView
function loadSaleDetails(id) {
    const modalContent = document.getElementById('modalViewContent');
    const modalTitle = document.getElementById('modalTicketNumber');
    
    modalTitle.textContent = `#${id}`;
    modalContent.innerHTML = `<div class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando detalles...
                              </div>`;

    // Reemplaza 'get_sale_details.php' con la ruta real de tu endpoint
    fetch(`get_sale_details.php?id=${id}`)
        .then(response => response.text()) // o .json() si devuelves JSON y construyes el HTML aquí
        .then(data => {
            modalContent.innerHTML = data;
        })
        .catch(error => {
            modalContent.innerHTML = `<div class="alert alert-danger">Error al cargar los detalles de la venta.</div>`;
            console.error('Error:', error);
        });
}

// Imprimir Ticket
function printTicket(id) {
    // Abre el ticket en una nueva pestaña y opcionalmente puede disparar print() desde allá
    window.open(`ticket.php?id=${id}`, '_blank');
}

// Anular Venta
let saleIdParaAnular = null; // Variable global para guardar el ID temporalmente

function cancelSale(id) {
    // 1. Guardamos el ID que queremos anular
    saleIdParaAnular = id;
    
    // 2. Actualizamos el número de ticket en el texto del modal
    document.getElementById('spanTicketAnular').textContent = `#${id}`;
    
    // 3. Mostramos el modal de Bootstrap
    const myModal = new bootstrap.Modal(document.getElementById('modalConfirmAnular'));
    myModal.show();
}

// Escuchador para el botón "Sí, Anular Venta" dentro del modal
document.getElementById('btnConfirmarAnular').addEventListener('click', function() {
    if (!saleIdParaAnular) return;

    // Cambiar estado del botón para evitar múltiples clics
    this.disabled = true;
    this.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Procesando...`;

    fetch(`controllers/cancel_sale.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${saleIdParaAnular}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Éxito: podrías usar un SweetAlert aquí o simplemente recargar
            location.reload(); 
        } else {
            alert('Error al anular: ' + (data.message || 'Error desconocido'));
            this.disabled = false;
            this.innerHTML = `Sí, Anular Venta`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        this.disabled = false;
        this.innerHTML = `Sí, Anular Venta`;
    });
});

// Exportar Tabla a Excel (CSV)
document.getElementById('btnExportExcel').addEventListener('click', function() {
    let table = document.querySelector(".table");
    let rows = table.querySelectorAll("tr");
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        
        // Evitamos exportar la última columna (Acciones)
        let colsLength = i === 0 ? cols.length - 1 : cols.length; 
        if(cols.length === 1) continue; // Saltar fila de "sin registros"

        for (let j = 0; j < colsLength; j++) {
            // Limpiar saltos de línea y espacios extras
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
            row.push(`"${data}"`);
        }
        csv.push(row.join(","));
    }

    let csvFile = new Blob(["\uFEFF" + csv.join("\n")], {type: "text/csv;charset=utf-8;"});
    let downloadLink = document.createElement("a");
    downloadLink.download = `historial_ventas_${new Date().toISOString().split('T')[0]}.csv`;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}); ```

## Archivo: ./public/js/users.js
 ```javascript
// Archivo: ./public/js/users.js
    const modalUser = new bootstrap.Modal(document.getElementById('modalUser'));

    function resetForm() {
        document.getElementById('userAction').value = 'create';
        document.getElementById('userId').value = '';
        document.getElementById('username').value = '';
        document.getElementById('full_name').value = '';
        document.getElementById('password').value = '';
        document.getElementById('password').required = true;
        document.getElementById('pwHelp').style.display = 'none';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i> Nuevo Usuario';
    }

    function editUser(u) {
        document.getElementById('userAction').value = 'update';
        document.getElementById('userId').value = u.id;
        document.getElementById('username').value = u.username;
        document.getElementById('full_name').value = u.full_name || '';
        document.getElementById('role').value = u.role;
        
        document.getElementById('password').value = '';
        document.getElementById('password').required = false;
        document.getElementById('pwHelp').style.display = 'block';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit text-warning me-2"></i> Editar Usuario';
        
        modalUser.show();
    }

function saveUser(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const btn = e.target.querySelector('button[type="submit"]');
    
    // Feedback visual en el botón
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

    fetch('actions/actions_user.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.status) {
            // Mensaje de éxito con SweetAlert2
            Swal.fire({
                title: "¡Logrado!",
                text: res.message || "Operación realizada con éxito",
                icon: "success",
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload(); // Recargamos después de que el usuario vea el mensaje
            });
        } else {
            // Mensaje de error con SweetAlert2
            Swal.fire({
                title: "Error",
                text: res.message || "Hubo un problema al guardar",
                icon: "error",
                confirmButtonColor: "#dc3545"
            });
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire("Error crítico", "No se pudo conectar con el servidor", "error");
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

    // Función para Eliminar con SweetAlert2
    function deleteUser(id, username) {
      Swal.fire({
        title: "¿Eliminar Usuario?",
        html: `Estás a punto de eliminar a <strong>${username}</strong>.<br>Esta acción no se puede deshacer.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        cancelButtonColor: "#232425",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append("action", "delete");
          formData.append("id", id);

          fetch("actions/actions_user.php", {
            method: "POST",
            body: formData,
          })
            .then((response) => response.json())
            .then((res) => {
              if (res.status) {
                Swal.fire("¡Eliminado!", res.message, "success").then(() =>
                  location.reload(),
                );
              } else {
                Swal.fire("Error", res.message, "error");
              }
            })
            .catch((error) => {
              console.error("Error:", error);
              Swal.fire("Error", "Problema al intentar eliminar.", "error");
            });
        }
      });
    }

    // Buscador en tiempo real
    document.getElementById('userSearch').addEventListener('keyup', function() {
        let value = this.value.toLowerCase();
        let rows = document.querySelectorAll('#userTableBody tr');
        rows.forEach(row => {
            if(!row.querySelector('td[colspan]')) {
                row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
            }
        });
    });
 ```

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
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script> ```

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
</html> ```

## Archivo: ./public/layouts/get_sale_details.php
 ```php
 ```

## Archivo: ./public/layouts/head.php
 ```php
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
   <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <title><?= isset($pageTitle) ? $pageTitle : 'Mi Negocio' ?></title>
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
    <div class="app-wrapper"> ```

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
                            <i class="fas fa-coins me-1" ></i> BCV: Bs. <?= number_format($bcvRate, 2) ?>
                        </span>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalInsert">
                            <i class="fas fa-plus me-1"></i> Nuevo Producto
                        </button>
                    </div>
                </div>
            </div>
        </div> ```

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
<body class="layout-fixed"> ```

## Archivo: ./public/layouts/modals/modals_admin.php
 ```php
<div class="modal fade" id="modalInsert" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="formInsert" method="POST" class="modal-content shadow">
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
                            <?php foreach ($categories as $c): ?>
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

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="formEdit" method="POST" class="modal-content shadow">
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
                            <?php foreach ($categories as $c): ?>
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


<div class="modal fade" id="modalView" tabindex="-1">
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
</div> ```

## Archivo: ./public/layouts/modals/modals_category.php
 ```php
<div class="modal fade" id="modalCat" tabindex="-1" aria-labelledby="modalTitle" >
    <div class="modal-dialog modal-dialog-centered">
        <form id="formCat" class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-tag me-2"></i> Categoría</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" id="action" value="create">
                <input type="hidden" name="id" id="catId">
                <div class="mb-3">
                    <label for="catName" class="form-label fw-bold small">Nombre de la Categoría <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="catName" class="form-control" placeholder="Ej: Lubricantes, Filtros..." required>
                </div>
                <div class="mb-2">
                    <label for="catDesc" class="form-label fw-bold small">Descripción (Opcional)</label>
                    <textarea name="description" id="catDesc" class="form-control" rows="2" placeholder="Breve detalle sobre esta categoría..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1"></i> Guardar Cambios</button>
            </div>
        </form>
    </div>
</div> ```

## Archivo: ./public/layouts/modals/modals_credits.php
 ```php

<div class="modal fade" id="modalPayment" tabindex="-1" >
    <div class="modal-dialog modal-dialog-centered">
        <form id="formPayment" class="modal-content shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i> Registrar Abono</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="add_payment">
                <input type="hidden" name="credit_id" id="pay_credit_id">
                
                <div class="alert alert-info py-2">
                    Cliente: <strong id="pay_customer_name"></strong><br>
                    Deuda Actual: <strong class="text-danger" id="pay_balance_display"></strong>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Monto a Abonar (USD) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-bold">$</span>
                        <input type="number" step="0.01" name="amount_usd" id="pay_amount" class="form-control form-control-lg" required>
                    </div>
                    <small class="text-muted" id="pay_bs_conversion">Equivale a: Bs 0.00</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Método de Pago</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="efectivo_bs">Efectivo Bolívares</option>
                        <option value="efectivo_usd">Efectivo Divisa</option>
                        <option value="pago_movil">Pago Móvil</option>
                        <option value="punto">Punto de Venta</option>
                        <option value="transferencia">Transferencia</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success px-4" id="btnSubmitPayment">Confirmar Pago</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalHistory" tabindex="-1" >
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i> Historial de Pagos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-striped mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Monto USD</th>
                            <th>Monto BS</th>
                            <th>Método</th>
                            <th>Cajero</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        </tbody>
                </table>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div> ```

## Archivo: ./public/layouts/modals/modals_customer.php
 ```php
<div class="modal fade" id="modalCustomerForm" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formCustomer" class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalCustomerTitle"><i class="fas fa-user-plus me-2"></i> Nuevo Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" id="customerAction" value="create">
                <input type="hidden" name="id" id="customerId">

                <div class="mb-3">
                    <label class="form-label fw-bold small">Nombre Completo <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="customerName" class="form-control" required placeholder="Ej: Juan Pérez">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold small">Cédula / RIF</label>
                        <input type="text" name="document" id="customerDoc" class="form-control" placeholder="Ej: V-12345678">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold small">Teléfono</label>
                        <input type="text" name="phone" id="customerPhone" class="form-control" autocomplete="phone" placeholder="Ej: 0414-1234567">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold" id="btnSaveCustomer">Guardar</button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="modalView" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i> Detalles del Cliente</h5>
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
 ```

## Archivo: ./public/layouts/modals/modals_pos.php
 ```php
<div class="modal fade" id="modalBCV" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-coins me-2"></i> Actualizar Tasa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <label form="newRateInput" class="form-label text-muted small">Nueva Tasa (Bs/$)</label>
                <input type="number" step="0.01" id="newRateInput" class="form-control form-control-lg text-center fw-bold text-dark border-warning" value="<?= $bcvRate ?>">
            </div>
            <div class="modal-footer bg-light justify-content-center">
                <button type="button" class="btn btn-warning fw-bold w-100" onclick="saveRate()">Guardar Cambio</button>
            </div>
        </div>
    </div>
</div>



<div class="modal fade" id="modalClearCart" tabindex="-1">
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

<div class="modal fade" id="modalCheckout" tabindex="-1" data-bs-backdrop="static">
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

                    <div class="d-flex justify-content-center gap-2 mt-3">
                        <button id="btnImprimirTicket" class="btn btn-primary shadow-sm" type="button">
                            <i class="fas fa-print me-1"></i> Ver Ticket
                        </button>
                        <button class="btn btn-outline-success shadow-sm" data-bs-dismiss="modal" onclick="window.location.reload()">
                            <i class="fas fa-redo me-1"></i> Nueva Venta
                        </button>
                    </div>
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

<div class="modal fade" id="modalMessage" tabindex="-1">
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
<div class="modal fade" id="modalDefault" tabindex="-1" aria-labelledby="modalDefaultLabel">
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
                            <h4 class="text-success fw-bold mb-0">$<?= number_format($resumenTotales['usd'], 2) ?></h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light">
                            <small class="text-muted d-block text-uppercase fw-bold">Total BS</small>
                            <h4 class="text-primary fw-bold mb-0">Bs. <?= number_format($resumenTotales['bs'], 2) ?></h4>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="list-group list-group-flush small">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-money-bill-wave me-2 text-muted"></i>Efectivo (Bs y USD)</span>
                        <span class="fw-bold">$<?= number_format($resumenTotales['efectivo_bs'] + $resumenTotales['efectivo_usd'], 2) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-mobile-alt me-2 text-muted"></i>Pago Móvil</span>
                        <span class="fw-bold">$<?= number_format($resumenTotales['pago_movil'], 2) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-credit-card me-2 text-muted"></i>Punto de Venta</span>
                        <span class="fw-bold">$<?= number_format($resumenTotales['punto'], 2) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-exchange-alt me-2 text-muted"></i>Transferencia</span>
                        <span class="fw-bold">$<?= number_format($resumenTotales['transferencia'], 2) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-file-invoice-dollar me-2 text-muted"></i>Crédito (Por Cobrar)</span>
                        <span class="fw-bold">$<?= number_format($resumenTotales['credito'], 2) ?></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cerrar Reporte</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCustomer" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="fas fa-users me-2"></i> Seleccionar o Crear Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <ul class="nav nav-tabs mb-3" id="customerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold text-dark" id="search-tab" data-bs-toggle="tab" data-bs-target="#searchCustomerPane" type="button">Buscar</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-dark" id="new-tab" data-bs-toggle="tab" data-bs-target="#newCustomerPane" type="button">Nuevo Cliente</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="searchCustomerPane">
                        <input type="text" id="inputSearchCustomer" class="form-control mb-3 border-warning" placeholder="Buscar por nombre o cédula/RIF...">
                        <div class="list-group" id="customerResults" style="max-height: 200px; overflow-y: auto;">
                            <div class="text-center text-muted p-3 small">Escribe para buscar...</div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="newCustomerPane">
                        <form id="formNewCustomer">
                            <div class="mb-2">
                                <label form="name" class="form-label small fw-bold">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" autocomplete="name" required>
                            </div>
                            <div class="mb-2">
                                <label form="document" class="form-label small fw-bold">Cédula / RIF</label>
                                <input type="text" name="document" class="form-control" autocomplete="document">
                            </div>
                            <div class="mb-3">
                                <label form="phone" class="form-label small fw-bold">Teléfono</label>
                                <input type="text" name="phone" class="form-control" autocomplete="phone">
                            </div>
                            <button type="submit" class="btn btn-success w-100 fw-bold" id="btnSaveCustomer">
                                <i class="fas fa-save me-1"></i> Guardar y Seleccionar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> ```

## Archivo: ./public/layouts/modals/modals_sales_history.php
 ```php
<div class="modal fade" id="modalView" tabindex="-1" aria-labelledby="modalViewLabel">
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
<div class="modal fade" id="modalConfirmAnular" tabindex="-1">
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
</div> ```

## Archivo: ./public/layouts/modals/modals_users.php
 ```php
<div class="modal fade" id="modalUser" tabindex="-1" >
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
                            <input type="text" name="username" id="username" class="form-control" autocomplete="username" required placeholder="ej: adolfo_dev">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre Completo</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" autocomplete="full_name" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Contraseña</label>
                            <input type="password" name="password" id="password" class="form-control" autocomplete="new-password" placeholder="••••••••">
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
</div> ```

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
</nav> ```

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
</nav> ```

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

        <li class="nav-item">
          <a href="dashboard.php" class="nav-link <?= ($pagina_actual == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-speedometer2 text-info"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <li class="nav-header text-uppercase opacity-75 small fw-bold">Operaciones</li>
        <li class="nav-item">
          <a href="pos.php" class="nav-link <?= ($pagina_actual == 'pos.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-cart-plus-fill text-success"></i>
            <p>Punto de Venta (POS)</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="sales_history.php" class="nav-link <?= ($pagina_actual == 'sales_history.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-receipt text-warning"></i>
            <p>Historial de Ventas</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="credits.php" class="nav-link <?= ($pagina_actual == 'credits.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-calendar-check text-danger"></i>
            <p>Cuentas por Cobrar</p>
          </a>
        </li>

        <li class="nav-header text-uppercase opacity-75 small fw-bold">Almacén</li>
        <li class="nav-item">
          <a href="admin.php" class="nav-link <?= ($pagina_actual == 'admin.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-box-seam-fill text-primary"></i>
            <p>Inventario / Productos</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="categories.php" class="nav-link <?= ($pagina_actual == 'categories.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-tags-fill text-info"></i>
            <p>Categorías</p>
          </a>
        </li>

        <li class="nav-header text-uppercase opacity-75 small fw-bold">Análisis</li>
        <li class="nav-item">
          <a href="sales.php" class="nav-link <?= ($pagina_actual == 'sales.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-graph-up-arrow text-success"></i>
            <p>Reporte de Ingresos</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="customers.php" class="nav-link <?= ($pagina_actual == 'customers.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-people-fill text-primary"></i>
            <p>Cartera de Clientes</p>
          </a>
        </li>

        <li class="nav-header text-uppercase opacity-75 small fw-bold">Seguridad</li>
        <li class="nav-item">
          <a href="users.php" class="nav-link <?= ($pagina_actual == 'users.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-shield-lock-fill text-secondary"></i>
            <p>Gestión de Usuarios</p>
          </a>
        </li>
      </ul>
    </nav>

    <div class="sidebar-footer border-top border-secondary pt-2 pb-3">
      <ul class="nav sidebar-menu flex-column">
        <li class="nav-item">
          <a href="configuration.php" class="nav-link <?= ($pagina_actual == 'configuration.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-gear-fill text-light"></i>
            <p>Configuración</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="logout.php" class="nav-link">
            <i class="nav-icon bi bi-door-open-fill text-danger"></i>
            <p class="text-danger">Cerrar Sesión</p>
          </a>
        </li>
      </ul>
    </div>
  </div>
</aside> ```

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
              <input id="username" name="username" type="text" class="form-control" placeholder="Usuario" autocomplete="username" required />
              <label for="username">Usuario</label>
            </div>
            <div class="input-group-text">
              <span class="bi bi-person-square"></span>
            </div>
          </div>

          <div class="input-group mb-3">
            <div class="form-floating">
              <input id="loginPassword" name="password" type="password" class="form-control" placeholder="Password" autocomplete="current-password" required>
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
</html> ```

## Archivo: ./public/logout.php
 ```php
<?php
require_once '../config/db.php';
require_once '../includes/Auth.php';
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->logout();
 ```

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
                        <?php foreach ($products as $p):
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
                                            <?php if ($has_image): ?>
                                                <img src="<?= $img_url ?>" class="img-fluid" style="max-height: 100%; object-fit: contain;" alt="<?= htmlspecialchars($p['name']) ?>">
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

                                                <?php if ($is_stock): ?>
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

                            <label form="paymentMethod" class="small fw-bold text-secondary mb-1">Método de Pago</label>
                            <select class="form-select mb-3 border-success" id="paymentMethod">
                                <option value="efectivo_bs"><i class="fas fa-money-bill-wave"></i>Efectivo Bolívares</option>
                                <option value="efectivo_usd"><i class="fas fa-dollar-sign"></i>Efectivo Divisa</option>
                                <option value="pago_movil"><i class="fas fa-mobile-alt"></i>Pago Móvil</option>
                                <option value="punto"><i class="fas fa-credit-card"></i>Punto de Venta</option>
                                <option value="credito"><i class="fas fa-file-invoice-dollar"></i>Crédito (Por Cobrar)</option>
                            </select>

                            <div id="creditData" style="display: none;" class="mb-3 p-3 bg-warning bg-opacity-10 border border-warning rounded">
                                <label form="selectedCustomerDisplay" class="small fw-bold text-dark">Cliente <span class="text-danger">*</span></label>
                                <input type="hidden" id="selectedCustomerId" name="customer_id" value="">
                                <div class="input-group mb-2">
                                    <input type="text" id="selectedCustomerDisplay" class="form-control form-control-sm border-warning bg-white" placeholder="Ningún cliente..." readonly>
                                    <button class="btn btn-warning btn-sm fw-bold text-dark" type="button" data-bs-toggle="modal" data-bs-target="#modalCustomer">
                                        <i class="fas fa-search me-1"></i> Buscar
                                    </button>
                                </div>
                                <label form="creditDueDate" class="small fw-bold text-dark">Fecha límite de pago</label>
                                <input type="date" id="creditDueDate" name="due_date" class="form-control form-control-sm border-warning">
                            </div>

                            <div class="row g-2">
                                <div class="col-9">
                                    <button class="btn btn-outline-success w-100 fw-bold btn-lg shadow-sm" onclick="initiateCheckout()" id="btnConfirmSale">
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/pos.js"></script>
</body>

</html> ```

## Archivo: ./public/process_sale.php
 ```php
<?php
// public/process_sale.php
ob_start();
header('Content-Type: application/json');

try {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
        throw new Exception("La sesión ha expirado. Por favor, recarga la página e inicia sesión nuevamente.");
    }

    $baseDir = __DIR__ . '/../';
    
    if (!file_exists($baseDir . 'config/db.php')) throw new Exception("Error interno: Falta config/db.php");
    require_once $baseDir . 'config/db.php';
    
    if (!file_exists($baseDir . 'includes/Sale.php')) throw new Exception("Error interno: Falta includes/Sale.php");
    require_once $baseDir . 'includes/Sale.php';
    
    if (!file_exists($baseDir . 'includes/ExchangeRate.php')) throw new Exception("Error interno: Falta includes/ExchangeRate.php");
    require_once $baseDir . 'includes/ExchangeRate.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido.");
    }

    if (!isset($_POST['cart']) || empty($_POST['cart'])) {
        throw new Exception("El carrito está vacío o los datos no llegaron correctamente.");
    }

    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Error de conexión a la base de datos.");
    }

    $tenant_id = $_SESSION['tenant_id'];
    $user_id = $_SESSION['user_id'];
    
    $rateObj = new ExchangeRate($db);
    $current_rate = $rateObj->getSystemRate();

    $saleObj = new Sale($db, $tenant_id, $user_id);
    
    $cart = $_POST['cart']; 
    $payment_method = $_POST['payment_method'] ?? 'efectivo_bs';
    
    // --- LÓGICA DE CRÉDITO ---
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    $result = $saleObj->createSale($cart, $payment_method, $current_rate, $customer_id, $due_date);

    ob_clean();
    echo json_encode($result);

} catch (Throwable $e) {
    ob_clean(); 
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}
?> ```

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
</html> ```

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
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="small"><i class="fas fa-file-invoice-dollar text-secondary me-1"></i> Crédito:</span>
                                <span class="fw-bold"><?= $ticketsCredito ?? 0 ?></span>
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
                                <a href="?filter=all" class="btn btn-sm btn-outline-primary <?= $filter == 'all' ? 'active' : '' ?>">Todos</a>
                                <a href="?filter=today" class="btn btn-sm btn-outline-primary <?= $filter == 'today' ? 'active' : '' ?>">Hoy</a>
                                <a href="?filter=7days" class="btn btn-sm btn-outline-primary <?= $filter == '7days' ? 'active' : '' ?>">Últimos 7 días</a>
                                <a href="?filter=30days" class="btn btn-sm btn-outline-primary <?= $filter == '30days' ? 'active' : '' ?>">Últimos 30 días</a>
                                <a href="?filter=custom" class="btn btn-sm btn-outline-primary <?= $filter == 'custom' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> Personalizado</a>
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
                                    <th>Cliente</th>
                                    <th>Método Pago</th>
                                    <th>Cant. Ítems</th>
                                    <th>Total USD</th>
                                    <th>Total BS</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="historyBody">
                                <?php if (!empty($sales)): ?>
                                    <?php foreach ($sales as $s): ?>
                                        <tr class="<?= $s['status'] === 'anulada' ? 'table-danger opacity-75' : '' ?>">
                                            <td class="ps-4 text-start fw-bold text-primary">
                                                #<?= $s['id'] ?>
                                                <?= $s['status'] === 'anulada' ? '<br><span class="badge bg-danger small mt-1">ANULADA</span>' : '' ?>
                                            </td>
                                            <td>
                                                <small><?= date('d/m/Y', strtotime($s['created_at'])) ?><br>
                                                    <span class="text-muted"><?= date('h:i A', strtotime($s['created_at'])) ?></span></small>
                                            </td>

                                            <td>
                                                <span class="fw-medium tex-primary">
                                                    <i class="fas fa-user-circle text-muted me-1"></i>
                                                    <?= htmlspecialchars($s['customer_name'] ?? 'Contado') ?>
                                                </span>
                                            </td>

                                            <td>
                                                
                                                <span class="badge bg-light border text-warning text-capitalize">
                                                    <?= str_replace('_', ' ', htmlspecialchars($s['payment_method'])) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span class="badge text-bg-secondary rounded-pill fs-6"><?= $s['total_items'] ?? 0 ?></span>
                                            </td>

                                            <td class="fw-bold text-success">$ <?= number_format($s['total_amount_usd'], 2) ?></td>
                                            <td class="fw-bold">Bs. <?= number_format($s['total_amount_bs'] ?? 0, 2) ?></td>

                                            <td class="text-end pe-4">
                                                <div class="btn-group shadow-sm">
                                                    <a href="ticket.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary me-1" title="Ver Ticket">
                                                        <i class="fas fa-receipt text-secondary"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-info me-1" data-bs-toggle="modal" data-bs-target="#modalView" onclick="loadSaleDetails(<?= $s['id'] ?>)" title="Ver Detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="printTicket(<?= $s['id'] ?>)" title="Imprimir">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <?php if ($s['status'] !== 'anulada'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger me-1" onclick="cancelSale(<?= $s['id'] ?>)" title="Anular Venta">
                                                            <i class="fas fa-times-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>No hay ventas registradas en este período.</td>
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
    include 'layouts/modals/modals_sales_history.php';
?>
<script src="js/sales_history.js"></script>
</body>

</html> ```

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
} ```

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
<div class="text-center">
    <?php if (isset($sale['status']) && $sale['status'] === 'anulada'): ?>
        <div style="border: 2px solid #000; padding: 5px; margin-top: 5px; font-weight: bold; font-size: 16px;">
            *** VENTA ANULADA ***
        </div>
    <?php endif; ?>

    <?php if ($sale['payment_method'] === 'credito'): ?>
        <div style="border: 1px dashed #000; padding: 5px; margin-top: 5px;">
            <strong>VENTA A CRÉDITO</strong><br>
            Cliente: <?= htmlspecialchars($sale['customer_name'] ?? 'N/A') ?><br>
            C.I/RIF: <?= htmlspecialchars($sale['customer_doc'] ?? 'N/A') ?>
        </div>
    <?php endif; ?>
</div>
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
</html> ```

## Archivo: ./public/users.php
 ```php
<?php
// Archivo: ./public/users.php
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
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $u): ?>
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
                                                <?php if ($u['role'] == 'admin'): ?>
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
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['username'])) ?>')" title="Eliminar">
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/users.js"></script>
</body>

</html> ```

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
?> ```

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
</html> ```

## Archivo: ./root/logout.php
 ```php
<?php
session_start();
session_destroy();
header("Location: login.php");
?> ```

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
</html> ```

