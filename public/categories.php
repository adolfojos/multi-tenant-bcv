<?php
require_once '../controllers/CategoryController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
<main class="app-main">
    <?= render_content_header($headerConfig) ?>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-8">
                    <div class="card card-outline card-primary shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title">Listado de Categorías</h3>
                        </div>
                        
                        <div class="card-body p-0">
                            <?php if(empty($categories)): ?>
                                <div class="p-5 text-center text-secondary">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-muted"></i>
                                    <p class="mb-0">No hay categorías registradas en el sistema.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="ps-4" style="width: 50px;">#</th>
                                                <th>Nombre</th>
                                                <th class="text-end pe-4" style="width: 150px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($categories as $c): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <i class="fas fa-tag text-secondary small"></i>
                                                </td>
                                                <td>
                                                    <span class="fw-bold"><?= htmlspecialchars($c['name']) ?></span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="btn-group">
                                                        <!-- Pasamos el array completo de forma segura -->
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick='openModal(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' 
                                                                title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" 
                                                                onclick="confirmDelete(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['name'])) ?>')" 
                                                                title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
include 'layouts/footer.php'; 
include 'layouts/modals/modals_category.php';
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/categories.js"></script>
</body>
</html>