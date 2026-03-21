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
</div>