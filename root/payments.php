<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['is_superadmin'])) { 
    header("Location: login.php"); 
    exit; 
}

$database = new Database();
$conn = $database->getConnection();

$tenant_name ="MultiPOS";
$pageTitle = "SuperAdmin - " . $tenant_name;
// --- 1. MÉTRICAS FINANCIERAS ---
// Ingresos Totales Históricos
$totalRevenue = $conn->query("SELECT COALESCE(SUM(amount_usd), 0) FROM tenant_payments")->fetchColumn();

// Ingresos del Mes Actual
$currentMonthRevenue = $conn->query("
    SELECT COALESCE(SUM(amount_usd), 0) 
    FROM tenant_payments 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())
")->fetchColumn();

// --- 2. OBTENER HISTORIAL DE PAGOS ---
$sql = "SELECT p.*, t.business_name, t.rif 
        FROM tenant_payments p 
        JOIN tenants t ON p.tenant_id = t.id 
        ORDER BY p.created_at DESC";
$payments = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
include 'layouts/head.php';
?> 
    <nav class="app-header navbar navbar-expand bg-body shadow-sm">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list"></i></a></li>
            </ul>
        </div>
    </nav>
    <?php include 'layouts/sidebar.php'; ?>
    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <h3 class="mb-0 fw-bold"><i class="fas fa-file-invoice-dollar text-success me-2"></i>Historial de Pagos</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                
                <div class="row mb-4">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="card shadow-sm h-100 border-0 border-start border-4 border-success">
                            <div class="card-body">
                                <p class="mb-1 text-muted fw-bold small text-uppercase"><i class="fas fa-calendar-day me-1"></i> Ingresos de este mes</p>
                                <h2 class="mb-0 text-success fw-bold">$ <?= number_format($currentMonthRevenue, 2) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100 border-0 border-start border-4 border-primary">
                            <div class="card-body">
                                <p class="mb-1 text-muted fw-bold small text-uppercase"><i class="fas fa-chart-line me-1"></i> Total Histórico Recaudado</p>
                                <h2 class="mb-0 text-primary fw-bold">$ <?= number_format($totalRevenue, 2) ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-outline card-success shadow-sm border-0">
                    <div class="card-header border-0 pb-0 pt-4">
                        <div class="row align-items-center">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <h5 class="card-title fw-bold m-0">Registro de Transacciones</h5>
                            </div>
                            <div class="col-md-6 d-flex justify-content-md-end gap-2">
                                <div class="input-group input-group-sm" style="max-width: 250px;">
                                    <span class="input-group-text bg-transparent text-muted"><i class="fas fa-search"></i></span>
                                    <input type="text" id="searchPayment" class="form-control" placeholder="Buscar cliente o ref...">
                                </div>
                                <button class="btn btn-sm btn-outline-success shadow-sm fw-bold" id="btnExportCSV">
                                    <i class="fas fa-file-excel me-1"></i> Exportar
                                </button>
                            </div>
                        </div>
                    </div>
                    <hr class="opacity-25 mx-3 mt-3 mb-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0" id="paymentsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="text-center ps-3">Recibo</th>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th>Método</th>
                                        <th>Referencia</th>
                                        <th>Meses</th>
                                        <th class="text-end">Monto (USD)</th>
                                        <th class="text-center pe-3">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($payments)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">
                                                <i class="fas fa-box-open fa-3x mb-3 opacity-25"></i>
                                                <p class="mb-0">Aún no hay pagos registrados.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($payments as $p): ?>
                                        <tr>
                                            <td class="text-center ps-3 fw-bold text-primary">#<?= str_pad($p['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($p['created_at'])) ?><br>
                                                <small class="text-muted"><?= date('h:i A', strtotime($p['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <strong class="text-light"><?= htmlspecialchars($p['business_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($p['rif']) ?></small>
                                            </td>
                                            <td><span class="badge bg-secondary-subtle text-secondary border border-secondary"><?= htmlspecialchars($p['payment_method']) ?></span></td>
                                            <td class="font-monospace text-muted"><?= !empty($p['reference']) ? htmlspecialchars($p['reference']) : 'N/A' ?></td>
                                            <td>+<?= $p['months_added'] ?></td>
                                            <td class="text-end fw-bold text-success">$<?= number_format($p['amount_usd'], 2) ?></td>
                                            <td class="text-center pe-3">
                                                <button class="btn btn-sm btn-outline-info" onclick="window.open('receipt.php?id=<?= $p['id'] ?>', '_blank')" title="Ver/Imprimir Recibo">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>
<script src="js/payments.js"></script>

</body>
</html>