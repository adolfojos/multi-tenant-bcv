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
}