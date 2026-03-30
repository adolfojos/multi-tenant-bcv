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
?>