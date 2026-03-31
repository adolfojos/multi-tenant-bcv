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
}