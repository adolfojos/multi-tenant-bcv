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
?>