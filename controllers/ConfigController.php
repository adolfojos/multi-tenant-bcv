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
    'icon'   => 'fas fa-cogs text-primary me-2',
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
?>