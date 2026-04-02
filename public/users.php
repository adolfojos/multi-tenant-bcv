<?php
// Archivo: ./public/users.php
require_once '../controllers/UserController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php';
?>
<main class="app-main">
    <?= render_content_header($headerConfig) ?>
    <div class="app-content">
        <div class="container-fluid">

            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm">
                        <span class="info-box-icon text-bg-primary shadow-sm">
                            <i class="fas fa-users"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-secondary small">Total Usuarios</span>
                            <span class="info-box-number h5 mb-0"><?= $totalUsers ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm">
                        <span class="info-box-icon text-bg-warning shadow-sm">
                            <i class="fas fa-user-shield"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-secondary small">Administradores</span>
                            <span class="info-box-number h5 mb-0"><?= $adminCount ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header border-0 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title fw-bold"><i class="fas fa-list me-2"></i> Lista de Acceso</h3>
                        <div class="card-tools">
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <input type="text" id="userSearch" class="form-control" placeholder="Buscar usuario...">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-dark text-secondary small text-uppercase">
                                <tr>
                                    <th class="ps-4">Usuario</th>
                                    <th>Nombre Completo</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="userTableBody">
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-3 bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; border-radius: 50%;">
                                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                                    </div>
                                                    <span class="fw-bold"><?= htmlspecialchars($u['username']) ?></span>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($u['full_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php if ($u['role'] == 'admin'): ?>
                                                    <span class="badge text-bg-warning"><i class="fas fa-shield-alt me-1"></i> Admin</span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-info"><i class="fas fa-user me-1"></i> Vendedor</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill bg-success-subtle text-success border border-success">Activo</span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-warning me-1" onclick='editUser(<?= json_encode($u) ?>)' title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['username'])) ?>')" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fas fa-users fa-3x mb-3 opacity-25"></i>
                                            <p>No se encontraron usuarios registrados.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>
<?php
include 'layouts/footer.php';
include 'layouts/modals/modals_users.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/users.js"></script>
</body>

</html>