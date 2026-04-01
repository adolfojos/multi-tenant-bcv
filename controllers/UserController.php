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
