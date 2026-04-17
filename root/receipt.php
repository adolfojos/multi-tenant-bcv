<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['is_superadmin']) || !isset($_GET['id'])) { 
    die("Acceso denegado o ID inválido."); 
}

$database = new Database();
$conn = $database->getConnection();

$id = (int)$_GET['id'];

// Obtener datos del pago cruzados con los datos de la tienda
$sql = "SELECT p.*, t.business_name, t.rif 
        FROM tenant_payments p 
        JOIN tenants t ON p.tenant_id = t.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die("Recibo no encontrado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago #<?= str_pad($payment['id'], 5, '0', STR_PAD_LEFT) ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; background: #f4f6f9; display: flex; justify-content: center; padding: 20px; }
        .receipt-card { background: #fff; width: 100%; max-width: 450px; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-top: 5px solid #0d6efd; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .header h2 { margin: 0; color: #0d6efd; font-size: 24px; }
        .header p { margin: 5px 0 0; color: #777; font-size: 14px; }
        .details { width: 100%; margin-bottom: 20px; font-size: 15px; }
        .details th { text-align: left; padding: 8px 0; color: #555; font-weight: normal; }
        .details td { text-align: right; padding: 8px 0; font-weight: bold; }
        .amount-box { background: #f8f9fa; padding: 15px; text-align: center; border-radius: 6px; margin-bottom: 20px; }
        .amount-box span { display: block; font-size: 13px; color: #666; text-transform: uppercase; }
        .amount-box h3 { margin: 5px 0 0; font-size: 32px; color: #198754; }
        .footer { text-align: center; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 20px; margin-top: 10px; }
        .btn-print { display: block; width: 100%; padding: 12px; background: #0d6efd; color: #fff; text-align: center; text-decoration: none; border-radius: 6px; font-weight: bold; cursor: pointer; border: none; }
        @media print { 
            body { background: #fff; padding: 0; }
            .receipt-card { box-shadow: none; max-width: 100%; border: none; }
            .btn-print { display: none; } 
        }
    </style>
</head>
<body>

<div class="receipt-card">
    <div class="header">
        <h2>MultiPOS</h2>
        <p>Recibo de Suscripción SaaS</p>
        <p><strong>N° <?= str_pad($payment['id'], 6, '0', STR_PAD_LEFT) ?></strong></p>
    </div>

    <div class="amount-box">
        <span>Total Pagado</span>
        <h3>$<?= number_format($payment['amount_usd'], 2) ?></h3>
    </div>

    <table class="details">
        <tr>
            <th>Fecha de Pago:</th>
            <td><?= date('d/m/Y h:i A', strtotime($payment['created_at'])) ?></td>
        </tr>
        <tr>
            <th>Cliente:</th>
            <td><?= htmlspecialchars($payment['business_name']) ?></td>
        </tr>
        <tr>
            <th>RIF/C.I:</th>
            <td><?= htmlspecialchars($payment['rif']) ?></td>
        </tr>
        <tr>
            <th>Concepto:</th>
            <td>Suscripción (<?= $payment['months_added'] ?> Meses)</td>
        </tr>
        <tr>
            <th>Método:</th>
            <td><?= htmlspecialchars($payment['payment_method']) ?></td>
        </tr>
        <?php if (!empty($payment['reference'])): ?>
        <tr>
            <th>Referencia:</th>
            <td><?= htmlspecialchars($payment['reference']) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="footer">
        <p>¡Gracias por confiar en MultiPOS para su negocio!</p>
        <p>Este comprobante certifica el pago de su licencia de uso.</p>
    </div>

    <button onclick="window.print()" class="btn-print mt-3">🖨️ Imprimir / Guardar PDF</button>
</div>

</body>
</html>