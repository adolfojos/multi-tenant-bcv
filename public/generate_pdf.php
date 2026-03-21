<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Report.php';

Middleware::onlyAdmin(); // Solo el jefe descarga reportes

$db = (new Database())->getConnection();
$reportObj = new Report($db, $_SESSION['tenant_id']);
$type = $_GET['type'] ?? 'sales';

if ($type == 'sales') {
    $data = $reportObj->getDailySales();
    $title = "Cierre de Caja - " . date('d/m/Y');
} else {
    $data = $reportObj->getInventoryStatus();
    $title = "Reporte de Inventario - " . date('d/m/Y');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #444; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f2f2f2; border: 1px solid #ddd; padding: 8px; text-align: left; }
        td { border: 1px solid #ddd; padding: 8px; }
        .total-box { text-align: right; font-size: 16px; margin-top: 20px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="no-print" style="background:#fff3cd; padding:15px; text-align:center; border:1px solid #ffeeba;">
    <strong>Vista Previa del Reporte</strong><br>
    Presione <button onclick="window.print()">CTRL + P</button> y seleccione "Guardar como PDF".
    <a href="admin.php">Volver</a>
</div>

<div class="header">
    <h1><?= strtoupper($_SESSION['tenant_name']) ?></h1>
    <h2><?= $title ?></h2>
    <p>Generado por: <?= $_SESSION['username'] ?> | Fecha: <?= date('d/m/Y H:i:s') ?></p>
</div>

<table>
    <thead>
        <?php if($type == 'sales'): ?>
            <tr>
                <th>ID</th>
                <th>Hora</th>
                <th>Usuario</th>
                <th>Método</th>
                <th>Total USD</th>
            </tr>
        <?php else: ?>
            <tr>
                <th>Producto</th>
                <th>Stock</th>
                <th>Costo ($)</th>
                <th>P. Venta ($)</th>
            </tr>
        <?php endif; ?>
    </thead>
    <tbody>
        <?php 
        $grandTotal = 0;
        foreach($data as $row): 
            if($type == 'sales') $grandTotal += $row['total_amount_usd'];
        ?>
            <tr>
                <?php if($type == 'sales'): ?>
                    <td><?= $row['id'] ?></td>
                    <td><?= date('H:i', strtotime($row['created_at'])) ?></td>
                    <td><?= $row['username'] ?></td>
                    <td><?= $row['payment_method'] ?></td>
                    <td>$<?= number_format($row['total_amount_usd'], 2) ?></td>
                <?php else: ?>
                    <td><?= $row['name'] ?></td>
                    <td><?= $row['stock'] ?></td>
                    <td>$<?= number_format($row['price_base_usd'], 2) ?></td>
                    <td>$<?= number_format($row['p_venta'], 2) ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if($type == 'sales'): ?>
<div class="total-box">
    <strong>TOTAL DEL DÍA: $<?= number_format($grandTotal, 2) ?></strong>
</div>
<?php endif; ?>

<script>
    // window.print(); // Descomentar para abrir diálogo de impresión automáticamente
</script>
</body>
</html>