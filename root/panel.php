<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['is_superadmin'])) {
    header("Location: login.php");
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$bcvQuery = $conn->query("SELECT bcv_rate FROM system_settings WHERE id=1");
$bcv = $bcvQuery->fetch(PDO::FETCH_ASSOC);

$tenants = $conn->query("SELECT *, DATEDIFF(expiration_date, NOW()) as days_left FROM tenants ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - SuperAdmin | MultiPOS</title>

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

        <aside class="app-sidebar bg-body-tertiary shadow" data-bs-theme="dark">
            <div class="sidebar-brand border-bottom border-secondary">
                <a href="#" class="brand-link text-decoration-none">
                    <i class="fas fa-crown text-warning mx-2"></i>
                    <span class="brand-text fw-bold">SuperAdmin</span>
                </a>
            </div>
            <div class="sidebar-wrapper">
                <nav class="mt-2">
                    <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                        <li class="nav-item">
                            <a href="#" class="nav-link active">
                                <i class="nav-icon fas fa-store text-info"></i>
                                <p>Tiendas Registradas</p>
                            </a>
                        </li>
                        <li class="nav-header text-uppercase opacity-75 small fw-bold mt-3">Sistema</li>
                        <li class="nav-item">
                            <a href="logout.php" class="nav-link">
                                <i class="nav-icon fas fa-sign-out-alt text-danger"></i>
                                <p class="text-danger">Cerrar Sesión</p>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

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
                                                <td><span class="badge text-bg-dark border font-monospace"><?= $t['license_key'] ?></span></td>
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

    <div class="modal fade" id="modalNewTenant" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form id="formNewTenant" class="modal-content shadow border-0">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-store me-2"></i> Registrar Nueva Tienda</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="create_tenant">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Nombre del Negocio</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-building"></i></span>
                                <input type="text" name="name" class="form-control" placeholder="Ej: Supermercado XYZ" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">RIF / Identificación</label>
                            <input type="text" name="rif" class="form-control" placeholder="Ej: J-12345678-9" required>
                        </div>

                        <div class="col-12">
                            <hr class="opacity-25 my-2">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Usuario Administrador</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-shield"></i></span>
                                <input type="text" name="admin_user" class="form-control" placeholder="Ej: admin_xyz" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" name="admin_pass" class="form-control" required>
                            </div>
                        </div>

                        <div class="col-md-12 mt-4">
                            <label class="form-label fw-bold small text-muted text-uppercase">Duración de Licencia Inicial</label>
                            <select name="months" class="form-select form-select-lg border-success">
                                <option value="1">1 Mes (Prueba)</option>
                                <option value="6">6 Meses</option>
                                <option value="12">1 Año</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold px-4"><i class="fas fa-save me-1"></i> Crear y Activar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalBCV" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form id="formBCV" class="modal-content shadow border-0">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fas fa-sync-alt me-2"></i> Tasa Global</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <input type="hidden" name="action" value="update_bcv">
                    <label class="form-label fw-bold text-muted small text-uppercase">Tasa de Cambio (Bs/USD)</label>
                    <div class="input-group input-group-lg mb-3">
                        <span class="input-group-text bg-light fw-bold">Bs.</span>
                        <input type="number" step="0.01" name="rate" class="form-control text-center fw-bold text-primary" value="<?= $bcv['bcv_rate'] ?>" required>
                    </div>
                    <div class="alert alert-warning py-2 mb-0 small text-start">
                        <i class="fas fa-info-circle me-1"></i> Actualizará los precios de TODAS las tiendas.
                    </div>
                </div>
                <div class="modal-footer bg-light p-2 justify-content-center">
                    <button type="submit" class="btn btn-warning w-100 fw-bold shadow-sm">Guardar Tasa</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalRenew" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form id="formRenew" class="modal-content shadow border-0">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-calendar-plus me-2"></i> Renovar</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <input type="hidden" name="action" value="renew">
                    <input type="hidden" name="id" id="renew_id">

                    <h6 class="fw-bold text-primary mb-3" id="renew_name">Nombre Tienda</h6>

                    <label class="form-label text-muted small fw-bold text-uppercase">Extender por:</label>
                    <select name="months" class="form-select form-select-lg border-primary mb-2">
                        <option value="1">1 Mes</option>
                        <option value="3">3 Meses</option>
                        <option value="6">6 Meses</option>
                        <option value="12">1 Año</option>
                    </select>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Aplicar Renovación</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="modalViewTenant" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-eye me-2"></i> Detalles de la Tienda</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">ID:</span>
                            <span id="view_id" class="fw-bold"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Negocio:</span>
                            <span id="view_name" class="text-primary fw-bold"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">RIF:</span>
                            <span id="view_rif"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Usuario Admin:</span>
                            <span id="view_admin" class="font-monospace bg-light px-2 rounded"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Licencia:</span>
                            <span id="view_license" class="badge text-bg-dark font-monospace"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Fecha Creada:</span>
                            <span id="view_created"></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditTenant" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form id="formEditTenant" class="modal-content shadow border-0">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i> Editar Tienda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit_tenant">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Nombre del Negocio</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">RIF / Identificación</label>
                        <input type="text" name="rif" id="edit_rif" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Nueva Contraseña de Admin</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Dejar en blanco para no cambiar">
                        <small class="text-muted">Si el cliente olvidó su clave, puedes generar una nueva aquí.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark"><i class="fas fa-save me-1"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/panel.js"></script>

</body>

</html>