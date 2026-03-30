<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Sale.php';

Middleware::checkAuth();

if (!isset($_GET['id'])) die("ID Requerido");

$db = (new Database())->getConnection();
// Pasamos el tenant_id de sesión para asegurar que solo busque ventas propias
$saleObj = new Sale($db, $_SESSION['tenant_id'], $_SESSION['user_id']);

$sale = $saleObj->getSaleHeader($_GET['id']);
// echo "<pre>"; print_r($sale); echo "</pre>";  Debug: Ver datos de la venta
$items = $saleObj->getSaleItems($_GET['id']);

if (!$sale) {
    die("❌ Venta no encontrada o no tienes permiso para verla.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket #<?= $sale['id'] ?></title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; width: 300px; margin: 0 auto; color: #000; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .border-bottom { border-bottom: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; }
        .btn-print { display: block; width: 100%; padding: 10px; background: #000; color: #fff; text-decoration: none; text-align: center; margin-bottom: 10px; }
        @media print { .btn-print { display: none; } }
    </style>
</head>
<body>

<a href="#" onclick="window.print()" class="btn-print">🖨️ IMPRIMIR</a>

<div class="text-center">
    <h3 style="margin-bottom: 5px;"><?= strtoupper($sale['business_name']) ?></h3>
    <span>RIF: <?= $sale['rif'] ?></span><br>
    <span>Fecha: <?= date('d/m/Y h:i A', strtotime($sale['created_at'])) ?></span><br>
    <span>Ticket #: <?= str_pad($sale['id'], 6, "0", STR_PAD_LEFT) ?></span>
    <span>Dólar BCV: <?= $sale['exchange_rate'] ?></span><br>
</div>
<div class="text-center">
    <?php if (isset($sale['status']) && $sale['status'] === 'anulada'): ?>
        <div style="border: 2px solid #000; padding: 5px; margin-top: 5px; font-weight: bold; font-size: 16px;">
            *** VENTA ANULADA ***
        </div>
    <?php endif; ?>
</div>
<div class="border-bottom"></div>
<div class="text-center">
    <?php if (isset($sale['status']) && $sale['status'] === 'anulada'): ?>
        <div style="border: 2px solid #000; padding: 5px; margin-top: 5px; font-weight: bold; font-size: 16px;">
            *** VENTA ANULADA ***
        </div>
    <?php endif; ?>

    <?php if ($sale['payment_method'] === 'credito'): ?>
        <div style="border: 1px dashed #000; padding: 5px; margin-top: 5px;">
            <strong>VENTA A CRÉDITO</strong><br>
            Cliente: <?= htmlspecialchars($sale['customer_name'] ?? 'N/A') ?><br>
            C.I/RIF: <?= htmlspecialchars($sale['customer_doc'] ?? 'N/A') ?>
        </div>
    <?php endif; ?>
</div>
<table>
    <thead>
        <tr>
            <th style="text-align:left">Cant</th>
            <th style="text-align:left">Desc</th>
            <th style="text-align:right">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($items as $item): ?>
        <tr>
            <td><?= $item['quantity'] ?></td>
            <td><?= substr($item['product_name'], 0, 30) ?></td>
            <td class="text-end">$<?= number_format($item['quantity'] * $item['price_at_moment_usd'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="border-bottom"></div>

<div class="text-end">
    <strong>TOTAL USD: $<?= number_format($sale['total_amount_usd'], 2) ?></strong><br>
    <strong>TOTAL BS: Bs <?= number_format($sale['total_amount_bs'], 2) ?></strong>
</div>

<div class="border-bottom"></div>

<div class="text-center">
    <small>Cajero: <?= $sale['username'] ?></small><br>
    <small><?= $sale['ticket_footer'] ?></small>
</div>

<script>
    // window.onload = function() { window.print(); } // Opcional: imprimir automático
</script>

</body>
</html>