<?php
require_once '../controllers/CustomerController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
<main class="app-main">
    <?= render_content_header($headerConfig) ?>
    <div class="app-content">
        <div class="container-fluid">
            
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header border-0 pb-0">
                    <h3 class="card-title fw-bold">Lista de Clientes</h3>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="customersTable" class="table table-hover table-striped align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Nombre Completo</th>
                                    <th>Cédula / RIF</th>
                                    <th>Teléfono</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($customers as $c): 
                                    // Preparamos los datos para pasarlos al botón de editar de forma segura
                                    $c_json = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td class="fw-bold text-primary">
                                        <i class="fas fa-user-circle text-secondary me-2"></i><?= htmlspecialchars($c['name']) ?>
                                    </td>
                                    <td><?= !empty($c['document']) ? htmlspecialchars($c['document']) : '<span class="text-muted fst-italic">No registrado</span>' ?></td>
                                    <td><?= !empty($c['phone']) ? htmlspecialchars($c['phone']) : '<span class="text-muted fst-italic">No registrado</span>' ?></td>
                                    
                                    <td class="text-end text-nowrap">
                                        <button class="btn btn-sm btn-outline-warning me-1" onclick='openCustomerModal(<?= $c_json ?>)' title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCustomer(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['name'])) ?>')" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

<div class="modal fade" id="modalCustomerForm" tabindex="-1" >
    <div class="modal-dialog modal-dialog-centered">
        <form id="formCustomer" class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalCustomerTitle"><i class="fas fa-user-plus me-2"></i> Nuevo Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" id="customerAction" value="create">
                <input type="hidden" name="id" id="customerId">
                
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nombre Completo <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="customerName" class="form-control" required placeholder="Ej: Juan Pérez">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold small">Cédula / RIF</label>
                        <input type="text" name="document" id="customerDoc" class="form-control" placeholder="Ej: V-12345678">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold small">Teléfono</label>
                        <input type="text" name="phone" id="customerPhone" class="form-control" placeholder="Ej: 0414-1234567">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold" id="btnSaveCustomer">Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/customers.js"></script>
</body>
</html>