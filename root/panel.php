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
$pageTitle = "Panel - " . $tenant_name;
$bcvQuery = $conn->query("SELECT bcv_rate FROM system_settings WHERE id=1");
$bcv = $bcvQuery->fetch(PDO::FETCH_ASSOC);

$tenants = $conn->query("
    SELECT t.*, p.name as plan_name, DATEDIFF(t.expiration_date, NOW()) as days_left 
    FROM tenants t 
    LEFT JOIN plans p ON t.plan_id = p.id 
    ORDER BY t.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener los planes para llenar el select del modal
$allPlans = $conn->query("SELECT id, name FROM plans ORDER BY price_usd ASC")->fetchAll(PDO::FETCH_ASSOC);
include 'layouts/head.php';
?>
        <nav class="app-header navbar navbar-expand bg-body shadow-sm">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list"></i></a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3">
                        <span class="badge border border-warning text-warning px-3 py-2 fs-6">
                            <i class="fas fa-coins me-1"></i> BCV: <strong><?= number_format($bcv['bcv_rate'], 2) ?> Bs</strong>
                        </span>
                    </li>
                    <li class="nav-item me-3">
                        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalBCV">
                            <i class="fas fa-sync-alt me-1"></i> Actualizar Tasa
                        </button>
                    </li>
                </ul>
            </div>
        </nav>

        <?php include 'layouts/sidebar.php'; ?>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-sm-6">
                            <h3 class="mb-0 fw-bold"><i class="fas fa-server text-primary me-2"></i>Gestión de Clientes (SaaS)</h3>
                        </div>
                        <div class="col-sm-6 text-end">
                            <button class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalNewTenant">
                                <i class="fas fa-plus-circle me-1"></i> Nueva Tienda
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    <div class="card card-outline card-primary shadow-sm border-0">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0 align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th class="text-center" width="5%">ID</th>
                                            <th>Negocio</th>
                                            <th>Licencia</th>
                                            <th>Estado</th>
                                            <th>Vencimiento</th>
                                            <th class="text-end pe-4">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tenants as $t): ?>
                                            <tr>
                                                <td class="text-center fw-bold"><?= $t['id'] ?></td>
                                                <td>
                                                    <strong class="text-primary"><?= htmlspecialchars($t['business_name']) ?></strong><br>
                                                    <small class="text-muted"><i class="fas fa-id-card me-1"></i> <?= htmlspecialchars($t['rif']) ?></small>
                                                </td>
                                                <<td>
                                                    <span class="badge text-bg-dark border font-monospace"><?= $t['license_key'] ?></span><br>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary mt-1">Plan: <?= htmlspecialchars($t['plan_name'] ?? 'Básico') ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($t['status'] == 'active'): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success"><i class="fas fa-check-circle me-1"></i> Activo</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger-subtle text-danger border border-danger"><i class="fas fa-ban me-1"></i> Suspendido</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="fw-medium"><?= date('d/m/Y', strtotime($t['expiration_date'])) ?></div>
                                                        <?php if ($t['days_left'] < 0): ?>
                                                            <span class="badge bg-danger mt-1"><i class="fas fa-exclamation-triangle"></i> Vencido (<?= abs($t['days_left']) ?> días)</span>
                                                        <?php elseif ($t['days_left'] <= 7): ?>
                                                            <span class="badge bg-warning text-dark mt-1"><i class="fas fa-clock"></i> Quedan <?= $t['days_left'] ?> días</span>
                                                        <?php else: ?>
                                                            <span class="text-info small"><i class="fas fa-calendar-check me-1"></i> <?= $t['days_left'] ?> días rest.</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end pe-3">
                                                        <div class="btn-group shadow-sm">
                                                            <button class="btn btn-sm btn-outline-info" onclick="viewTenant(<?= $t['id'] ?>)" title="Ver Detalles">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-secondary" onclick="openChangePlanModal(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['business_name'])) ?>', <?= $t['plan_id'] ?? 1 ?>)" title="Cambiar Plan (Upsell)">
                                                                <i class="fas fa-level-up-alt"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-warning" onclick="editTenant(<?= $t['id'] ?>)" title="Editar Tienda">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="openRenewModal(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['business_name'])) ?>')" title="Renovar Licencia">
                                                                <i class="fas fa-calendar-plus"></i>
                                                            </button>
                                                            <?php if ($t['status'] == 'active'): ?>
                                                                <button class="btn btn-sm btn-outline-secondary" onclick="toggleStatus(<?= $t['id'] ?>, 'suspended', '<?= htmlspecialchars(addslashes($t['business_name'])) ?>')" title="Suspender Tienda">
                                                                    <i class="fas fa-ban"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-sm btn-outline-success" onclick="toggleStatus(<?= $t['id'] ?>, 'active', '<?= htmlspecialchars(addslashes($t['business_name'])) ?>')" title="Reactivar Tienda">
                                                                    <i class="fas fa-check-circle"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTenant(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['business_name'])) ?>')" title="Eliminar Definitivamente">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </div>
                                                    </td>

                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <?php
    include 'layouts/footer.php';
    include 'layouts/modals/modals_panel.php';
    ?>
</body>

</html>