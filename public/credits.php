<?php
require_once '../controllers/CreditController.php';
include 'layouts/head.php';
echo '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
<main class="app-main">
    <?= render_content_header($headerConfig) ?>
    <div class="app-content">
        <div class="container-fluid">
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="info-box shadow-sm text-bg-warning h-100">
                        <span class="info-box-icon"><i class="fas fa-file-invoice-dollar text-white"></i></span>
                        <div class="info-box-content text-white">
                            <span class="info-box-text small fw-bold text-uppercase">Total por Cobrar</span>
                            <span class="info-box-number fs-4 mb-0">$<?= number_format($total_deuda_usd, 2) ?></span>
                            <span class="progress-description small">≈ Bs. <?= number_format($total_deuda_usd * $bcvRate, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-warning shadow-sm">
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="creditsTable" class="table table-hover table-striped align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th># Venta</th>
                                    <th>Cliente</th>
                                    <th>Fecha Emisión</th>
                                    <th>Vencimiento</th>
                                    <th>Total Deuda</th>
                                    <th>Saldo Pendiente</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($credits as $c): 
                                    $is_overdue = ($c['due_date'] && $c['due_date'] < date('Y-m-d') && $c['status'] == 'pending');
                                ?>
                                <tr>
                                    <td class="fw-bold text-primary">#<?= $c['sale_id'] ?></td>
                                    <td>
                                        <strong class="d-block"><?= htmlspecialchars($c['customer_name']) ?></strong>
                                        <small class="text-muted"><?= htmlspecialchars($c['document']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($c['sale_date'])) ?></td>
                                    <td>
                                        <?php if($c['due_date']): ?>
                                            <span class="<?= $is_overdue ? 'text-danger fw-bold' : '' ?>">
                                                <?= date('d/m/Y', strtotime($c['due_date'])) ?>
                                                <?= $is_overdue ? '<i class="fas fa-exclamation-circle ms-1"></i>' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted">$<?= number_format($c['total_amount_usd'], 2) ?></td>
                                    <td class="fw-bold fs-6 text-danger">$<?= number_format($c['balance_usd'], 2) ?></td>
                                    <td>
                                        <?php if($c['status'] == 'pending'): ?>
                                            <span class="badge text-bg-warning">Pendiente</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-success">Pagado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button class="btn btn-sm btn-outline-info me-1" onclick="viewHistory(<?= $c['id'] ?>)" title="Ver Pagos">
                                            <i class="fas fa-list"></i>
                                        </button>
                                        <?php if($c['status'] == 'pending'): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="openPaymentModal(<?= $c['id'] ?>, <?= $c['balance_usd'] ?>, '<?= htmlspecialchars(addslashes($c['customer_name'])) ?>')" title="Registrar Abono">
                                            <i class="fas fa-hand-holding-usd"></i> Abonar
                                        </button>
                                        <?php endif; ?>
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



<?php include 'layouts/footer.php'; ?><?php include 'layouts/modals/modals_credits.php'; ?> 
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    window.APP_BCVRATE = <?= $bcvRate ?>;
</script>
<script src="js/credits.js"></script>
</body>
</html>