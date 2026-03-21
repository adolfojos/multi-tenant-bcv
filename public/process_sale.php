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
?>