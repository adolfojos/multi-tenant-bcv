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
                                <?php foreach ($customers as $c):
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
                                                    <button class="btn btn-sm btn-outline-info me-1" onclick='viewCustomer(<?= $c_json ?>)' title="Ver Detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
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
<?php
include 'layouts/footer.php';
include 'layouts/modals/modals_customer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/customers.js"></script>
</body>

</html>