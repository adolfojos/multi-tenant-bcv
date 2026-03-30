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
?>