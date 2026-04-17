<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['is_superadmin'])) { 
    header("Location: login.php"); 
    exit; 
}

$database = new Database();
$conn = $database->getConnection();

// --- 1. MÉTRICAS GENERALES ---
$totalTenants = $conn->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
$activeTenants = $conn->query("SELECT COUNT(*) FROM tenants WHERE status = 'active'")->fetchColumn();
$suspendedTenants = $conn->query("SELECT COUNT(*) FROM tenants WHERE status = 'suspended'")->fetchColumn();

// --- 2. ALERTAS DE VENCIMIENTO (Próximos 15 días) ---
$expiringQuery = $conn->query("
    SELECT id, business_name, expiration_date, DATEDIFF(expiration_date, NOW()) as days_left 
    FROM tenants 
    WHERE status = 'active' AND DATEDIFF(expiration_date, NOW()) BETWEEN 0 AND 15
    ORDER BY days_left ASC
");
$expiringTenants = $expiringQuery->fetchAll(PDO::FETCH_ASSOC);
$expiringCount = count($expiringTenants);

// --- 3. DATOS PARA EL GRÁFICO (Crecimiento últimos 6 meses) ---
// Asumiendo que tu tabla tenants tiene una columna 'created_at'
$chartQuery = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total 
    FROM tenants 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$chartData = $chartQuery->fetchAll(PDO::FETCH_ASSOC);

// Preparamos los datos para JavaScript
$chartLabels = json_encode(array_column($chartData, 'month'));
$chartSeries = json_encode(array_column($chartData, 'total'));

?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Analítico - SuperAdmin | MultiPOS</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body">

<div class="app-wrapper">
    
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
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0 fw-bold"><i class="fas fa-chart-line text-success me-2"></i>Métricas del Negocio</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box text-bg-primary shadow-sm">
                            <div class="inner">
                                <h3><?= $totalTenants ?></h3>
                                <p>Total Tiendas Históricas</p>
                            </div>
                            <div class="small-box-icon"><i class="fas fa-building"></i></div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-6">
                        <div class="small-box text-bg-success shadow-sm">
                            <div class="inner">
                                <h3><?= $activeTenants ?></h3>
                                <p>Suscripciones Activas</p>
                            </div>
                            <div class="small-box-icon"><i class="fas fa-check-circle"></i></div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-6">
                        <div class="small-box text-bg-danger shadow-sm">
                            <div class="inner">
                                <h3><?= $suspendedTenants ?></h3>
                                <p>Cuentas Suspendidas</p>
                            </div>
                            <div class="small-box-icon"><i class="fas fa-ban"></i></div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-6">
                        <div class="small-box text-bg-warning shadow-sm">
                            <div class="inner text-dark">
                                <h3><?= $expiringCount ?></h3>
                                <p>Por Vencer (15 días)</p>
                            </div>
                            <div class="small-box-icon"><i class="fas fa-exclamation-triangle text-dark"></i></div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-lg-7">
                        <div class="card card-outline card-success shadow-sm h-100 border-0">
                            <div class="card-header border-0">
                                <h3 class="card-title fw-bold">Crecimiento de Clientes (Últimos 6 meses)</h3>
                            </div>
                            <div class="card-body">
                                <div id="growthChart" style="min-height: 300px;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card card-outline card-warning shadow-sm h-100 border-0">
                            <div class="card-header border-0 d-flex justify-content-between align-items-center">
                                <h3 class="card-title fw-bold text-warning"><i class="fas fa-clock me-2"></i> Atención Requerida</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 330px;">
                                    <table class="table table-hover table-striped align-middle mb-0">
                                        <thead class="table-dark sticky-top">
                                            <tr>
                                                <th class="ps-3">Cliente</th>
                                                <th class="text-center">Vence en</th>
                                                <th class="text-end pe-3">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($expiringTenants)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-4">
                                                        <i class="fas fa-thumbs-up fa-2x mb-2 opacity-50"></i><br>
                                                        Todo al día. No hay licencias por vencer.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($expiringTenants as $exp): ?>
                                                <tr>
                                                    <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($exp['business_name']) ?></td>
                                                    <td class="text-center">
                                                        <span class="badge <?= $exp['days_left'] <= 5 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                                            <?= $exp['days_left'] ?> días
                                                        </span>
                                                    </td>
                                                    <td class="text-end pe-3">
                                                        <a href="panel.php" class="btn btn-sm btn-outline-success" title="Ir a renovar">
                                                            <i class="fas fa-calendar-plus"></i>
                                                        </a>
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

            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"></script>

<script>
    window.CHART_LABELS = <?= $chartLabels ?>;
    window.CHART_SERIES = <?= $chartSeries ?>;
</script>
<script src="js/dashboard.js"></script>

</body>
</html>