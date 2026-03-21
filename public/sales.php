<?php
require_once '../controllers/SaleController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
    <main class="app-main">
        <?= render_content_header($headerConfig) ?>
        <div class="app-content">
            <div class="container-fluid">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="text-uppercase small fw-bold text-secondary mb-0">Resumen Financiero</h5>
                    <div class="btn-group shadow-sm">
                        <a href="?period=day" class="btn btn-sm btn-outline-primary <?= $period=='day'?'active':'' ?>">Hoy</a>
                        <a href="?period=week" class="btn btn-sm btn-outline-primary <?= $period=='week'?'active':'' ?>">Semana</a>
                        <a href="?period=month" class="btn btn-sm btn-outline-primary <?= $period=='month'?'active':'' ?>">Mes</a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 col-12">
                        <div class="small-box text-bg-success shadow-sm">
                            <div class="inner">
                                <p class="mb-0 opacity-75">Ingresos Totales (USD)</p>
                                <h3>$ <?= number_format($grandTotalUsd, 2) ?></h3>
                            </div>
                            <div class="small-box-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="small-box-footer py-2">
                                <i class="fas fa-chart-line"></i> Datos del período actual
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-12">
                        <div class="small-box text-bg-warning shadow-sm">
                            <div class="inner text-white">
                                <p class="mb-0 opacity-75">Ingresos Totales (BS)</p>
                                <h3 class="text-white">Bs <?= number_format($grandTotalBs, 2) ?></h3>
                            </div>
                            <div class="small-box-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="small-box-footer py-2 text-white">
                                <i class="fas fa-exchange-alt"></i> Según tasa BCV configurada
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card card-outline card-primary shadow-sm h-100">
                            <div class="card-header border-0">
                                <div class="d-flex justify-content-between">
                                    <h3 class="card-title fw-bold">Ventas en el Tiempo (USD)</h3>
                                    <i class="fas fa-history text-muted"></i>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="position-relative mb-4" style="height: 300px;">
                                    <canvas id="salesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card card-outline card-info shadow-sm h-100">
                            <div class="card-header border-0">
                                <h3 class="card-title fw-bold">Por Método de Pago</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="ps-3 text-secondary small text-uppercase">Método</th>
                                                <th class="text-end pe-3 text-secondary small text-uppercase">Total USD</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($stats)): ?>
                                                <tr>
                                                    <td colspan="2" class="text-center text-muted py-5">
                                                        <i class="fas fa-receipt d-block mb-2 opacity-50"></i> Sin datos
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($stats as $row): ?>
                                                <tr>
                                                    <td class="ps-3 text-capitalize">
                                                        <span class="text-primary me-2">•</span>
                                                        <?= str_replace('_', ' ', $row['payment_method']) ?>
                                                    </td>
                                                    <td class="text-end pe-3 fw-bold text-success">
                                                        $ <?= number_format($row['total_usd'], 2) ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent text-center">
                                <small class="text-muted">Desglose de transacciones finalizadas</small>
                            </div>
                        </div>
                    </div>
                </div> </div> </div> </main>

<?php include 'layouts/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php
// Preparamos los datos en PHP
$labels = !empty($chartData) ? array_column($chartData, 'sale_date') : [];
$dataPoints = !empty($chartData) ? array_column($chartData, 'total') : [];
?>

<script>
    // Pasamos los datos a variables globales de JS
    const chartLabels = <?= json_encode($labels) ?>;
    const chartValues = <?= json_encode($dataPoints) ?>;
</script>
<script src="js/sales.js"></script>
</body>
</html>