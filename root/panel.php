<?php
session_start();
require_once '../config/db.php';

// Seguridad: Solo el dueño puede entrar
if (!isset($_SESSION['is_superadmin'])) { header("Location: login.php"); exit; }

$database = new Database();
$conn = $database->getConnection();

// Obtener Tasa BCV Actual
$bcvQuery = $conn->query("SELECT bcv_rate FROM system_settings WHERE id=1");
$bcv = $bcvQuery->fetch(PDO::FETCH_ASSOC);

// Obtener Listado de Tiendas (Tenants)
$tenants = $conn->query("SELECT *, DATEDIFF(expiration_date, NOW()) as days_left FROM tenants ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Dueño | AdminLTE 4</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body">

<div class="app-wrapper">
    
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list"></i></a>
                </li>
            </ul>
            
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3">
                    <span class="text-warning">BCV: <strong><?= number_format($bcv['bcv_rate'], 2) ?> Bs</strong></span>
                </li>
                <li class="nav-item me-3">
                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalBCV">
                        <i class="bi bi-currency-exchange"></i> Cambiar Tasa
                    </button>
                </li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar bg-body-tertiary shadow">
        <div class="sidebar-brand">
            <a href="#" class="brand-link">
                <span class="brand-text fw-light">🛠️ Gestión SaaS</span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="#" class="nav-link active">
                            <i class="nav-icon bi bi-shop"></i>
                            <p>Tiendas Registradas</p>
                        </a>
                    </li>
                    <li class="nav-header">SESIÓN</li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link text-danger">
                            <i class="nav-icon bi bi-box-arrow-right"></i>
                            <p>Salir del Panel</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0">Gestión de Clientes</h3>
                    </div>
                    <div class="col-sm-6 text-end">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNewTenant">
                            <i class="bi bi-plus-lg"></i> Nueva Tienda
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                <div class="card card-outline card-primary shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title">Listado de Tiendas</h3>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center">ID</th>
                                    <th>Negocio</th>
                                    <th>Licencia</th>
                                    <th>Estado</th>
                                    <th>Vencimiento</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($tenants as $t): ?>
                                <tr>
                                    <td class="text-center"><?= $t['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($t['business_name']) ?></strong><br>
                                        <small class="text-secondary">RIF: <?= htmlspecialchars($t['rif']) ?></small>
                                    </td>
                                    <td><span class="badge text-bg-dark border font-monospace"><?= $t['license_key'] ?></span></td>
                                    <td>
                                        <?php if($t['status'] == 'active'): ?>
                                            <span class="badge text-bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-danger">Suspendido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $t['expiration_date'] ?><br>
                                        <?php if($t['days_left'] < 0): ?>
                                            <span class="text-danger fw-bold small"><i class="bi bi-exclamation-triangle"></i> Vencido</span>
                                        <?php else: ?>
                                            <span class="text-info small"><?= $t['days_left'] ?> días restantes</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#renew<?= $t['id'] ?>" title="Renovar">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        
                                        <form action="actions.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <?php if($t['status']=='active'): ?>
                                                <input type="hidden" name="status" value="suspended">
                                                <button class="btn btn-sm btn-outline-danger" title="Suspender"><i class="bi bi-slash-circle"></i></button>
                                            <?php else: ?>
                                                <input type="hidden" name="status" value="active">
                                                <button class="btn btn-sm btn-outline-success" title="Activar"><i class="bi bi-check-circle"></i></button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="renew<?= $t['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <form action="actions.php" method="POST" class="modal-content border-primary">
                                            <div class="modal-header bg-primary text-white border-bottom-0">
                                                <h5 class="modal-title">Renovar Suscripción</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="renew">
                                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                                <p>Tienda seleccionada: <strong class="text-primary"><?= $t['business_name'] ?></strong></p>
                                                <div class="mb-3">
                                                    <label class="form-label">Tiempo a agregar:</label>
                                                    <select name="months" class="form-select">
                                                        <option value="1">1 Mes</option>
                                                        <option value="6">6 Meses</option>
                                                        <option value="12">1 Año</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-top-0">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-primary">Aplicar Renovación</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<div class="modal fade" id="modalNewTenant" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="actions.php" method="POST" class="modal-content border-success">
            <div class="modal-header bg-success text-white border-bottom-0">
                <h5 class="modal-title"><i class="bi bi-shop me-2"></i>Registrar Nuevo Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="create_tenant">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre del Negocio</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">RIF</label>
                        <input type="text" name="rif" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Usuario Admin (Para la tienda)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="admin_user" class="form-control" placeholder="ej: admin" required>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="text" name="admin_pass" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Duración Inicial de la Licencia</label>
                    <select name="months" class="form-select">
                        <option value="1">1 Mes</option>
                        <option value="12">1 Año</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Crear y Activar Tienda</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalBCV" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="actions.php" method="POST" class="modal-content border-warning">
            <div class="modal-header bg-warning text-dark border-bottom-0">
                <h5 class="modal-title"><i class="bi bi-currency-exchange me-2"></i>Actualizar Tasa Global</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="update_bcv">
                <div class="mb-3">
                    <label class="form-label">Nueva Tasa (Bs/USD):</label>
                    <input type="number" step="0.01" name="rate" class="form-control form-control-lg text-center" value="<?= $bcv['bcv_rate'] ?>" required>
                </div>
                <div class="alert alert-info py-2 mb-0 border-0">
                    <small><i class="bi bi-info-circle me-1"></i> Esto actualizará los precios en BS de todas las tiendas de forma automática.</small>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning fw-bold text-dark">Guardar Tasa</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>

<script>
    // Inicializar OverlayScrollbars
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== "undefined") {
            OverlayScrollbarsGlobal.OverlayScrollbars(document.querySelector(".sidebar-wrapper"), {
                scrollbars: {
                    theme: "os-theme-light",
                    autoHide: "leave",
                    clickScroll: true,
                },
            });
        }
    });
</script>
</body>
</html>