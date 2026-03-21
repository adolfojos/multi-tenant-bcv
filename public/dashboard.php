<?php
require_once '../controllers/DashboardController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>

<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include 'layouts/head.php'; ?>

        <main class="app-main">
            <?= render_content_header($headerConfig) ?>
            <div class="app-content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-lg-3 col-6">
                            <div class="small-box text-bg-info shadow-sm">
                                <div class="inner">
                                    <h3>$<?= number_format($mySalesToday, 2) ?></h3>
                                    <p class="mb-0">Mis Ventas Hoy (USD)</p>
                                </div>
                                <div class="small-box-icon"><i class="fas fa-dollar-sign"></i></div>
                                <a href="sales_history.php?filter=today" class="small-box-footer link-light link-underline-opacity-0">
                                    Ver historial <i class="fas fa-arrow-circle-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box text-bg-success shadow-sm">
                                <div class="inner">
                                    <h3><?= $myInvoices ?></h3>
                                    <p class="mb-0">Mis Facturas Hoy</p>
                                </div>
                                <div class="small-box-icon"><i class="fas fa-file-invoice"></i></div>
                                <a href="sales_history.php?filter=today" class="small-box-footer link-light link-underline-opacity-0">
                                    Ver tickets <i class="fas fa-arrow-circle-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box text-bg-warning shadow-sm">
                                <div class="inner text-white">
                                    <h3 class="text-white">Bs <?= number_format($bcvRate, 2) ?></h3>
                                    <p class="mb-0 text-white">Tasa BCV del Día</p>
                                </div>
                                <div class="small-box-icon"><i class="fas fa-coins text-white"></i></div>
                                <a href="pos.php" class="small-box-footer text-white link-underline-opacity-0">
                                    Ir a caja <i class="fas fa-arrow-circle-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-6">
                            <div class="small-box text-bg-danger shadow-sm">
                                <div class="inner">
                                    <h3>Bs <?= number_format($mySalesToday * $bcvRate, 2) ?></h3>
                                    <p class="mb-0">Mis Ventas Hoy (Bs)</p>
                                </div>
                                <div class="small-box-icon"><i class="fas fa-wallet"></i></div>
                                <a href="sales_history.php" class="small-box-footer link-light link-underline-opacity-0">
                                    Cuadre de caja <i class="fas fa-arrow-circle-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-lg-8">
                            <div class="card card-outline card-primary shadow-sm mb-4">
                                <div class="card-header border-0">
                                    <h3 class="card-title fw-bold">Rendimiento de Ventas (USD)</h3>
                                </div>
                                <div class="card-body">
                                    <div id="revenue-chart" style="min-height: 300px;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card card-outline card-info shadow-sm mb-4 h-100">
                                <div class="card-header border-0">
                                    <h3 class="card-title fw-bold">Accesos Rápidos</h3>
                                </div>
                                <div class="card-body d-flex flex-column gap-2">
                                    <a href="pos.php" class="btn btn-outline-success btn-lg text-start fw-bold shadow-sm"><i class="fas fa-cash-register me-2"></i> Abrir Punto de Venta</a>
                                    <a href="admin.php" class="btn btn-outline-primary text-start fw-bold"><i class="fas fa-box me-2"></i> Inventario</a>
                                    <a href="sales.php" class="btn btn-outline-warning text-start fw-bold"><i class="fas fa-chart-line me-2"></i> Reportes</a>
                                    <a href="users.php" class="btn btn-outline-info text-start fw-bold"><i class="fas fa-users me-2"></i> Usuarios</a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>
    <?php include 'layouts/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"></script>
    <script>
        window.APP_JS_CHARTSALE = <?= $jsChartSales?>;
        window.APP_JS_CHARTDATES = <?= $jsChartDates?>;
    </script>
    <script src="js/dashboard.js"></script>
</body>
</html>