<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['is_superadmin'])) { header("Location: login.php"); exit; }

$database = new Database();
$conn = $database->getConnection();
$tenant_name ="MultiPOS";
$pageTitle = "Planes de Suscripción  - " . $tenant_name;
$plans = $conn->query("SELECT * FROM plans ORDER BY price_usd ASC")->fetchAll(PDO::FETCH_ASSOC);
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
                        <h3 class="mb-0 fw-bold"><i class="fas fa-box-open text-primary me-2"></i>Paquetes y Límites</h3>
                    </div>
                    <div class="col-sm-6 text-end">
                        <button class="btn btn-primary shadow-sm fw-bold" onclick="openPlanModal()">
                            <i class="fas fa-plus-circle me-1"></i> Crear Nuevo Plan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                <div class="row g-4 justify-content-center">
                    <?php foreach($plans as $p): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card h-100 shadow-sm border-0 <?= $p['price_usd'] > 0 ? 'border-top border-4 border-primary' : 'border-top border-4 border-secondary' ?>">
                            <div class="card-body text-center p-4 d-flex flex-column">
                                <h4 class="fw-bold text-uppercase"><?= htmlspecialchars($p['name']) ?></h4>
                                <div class="my-3">
                                    <span class="fs-1 fw-bold">$<?= number_format($p['price_usd'], 2) ?></span><span class="text-muted">/mes</span>
                                </div>
                                <p class="text-muted small mb-4"><?= htmlspecialchars($p['description']) ?></p>
                                
                                <ul class="list-group list-group-flush text-start mb-4 flex-grow-1">
                                    <li class="list-group-item bg-transparent">
                                        <i class="fas fa-users text-primary me-2"></i> 
                                        <strong><?= $p['max_users'] == 0 ? 'Ilimitados' : $p['max_users'] ?></strong> Usuarios/Cajeros
                                    </li>
                                    <li class="list-group-item bg-transparent">
                                        <i class="fas fa-cubes text-primary me-2"></i> 
                                        <strong><?= $p['max_products'] == 0 ? 'Ilimitados' : $p['max_products'] ?></strong> Productos
                                    </li>
                                </ul>

                                <div class="d-flex gap-2 justify-content-center mt-auto">
                                    <button class="btn btn-outline-warning w-50" onclick="editPlan(<?= $p['id'] ?>)">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="btn btn-outline-danger w-50" onclick="deletePlan(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="modalPlan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formPlan" class="modal-content shadow border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalPlanTitle"><i class="fas fa-box me-2"></i> Plan de Suscripción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="save_plan">
                <input type="hidden" name="id" id="plan_id">
                
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Nombre del Plan</label>
                    <input type="text" name="name" id="plan_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Precio Mensual (USD)</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="price_usd" id="plan_price" class="form-control" required>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Límite Usuarios</label>
                        <input type="number" name="max_users" id="plan_users" class="form-control" required>
                        <small class="text-info">Pon 0 para ilimitado</small>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Límite Productos</label>
                        <input type="number" name="max_products" id="plan_products" class="form-control" required>
                        <small class="text-info">Pon 0 para ilimitado</small>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Descripción Corta</label>
                    <textarea name="description" id="plan_desc" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fas fa-save me-1"></i> Guardar Plan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/plans.js"></script>

</body>
</html>