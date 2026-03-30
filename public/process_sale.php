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
?>