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

<div class="modal fade" id="modalPayment" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formPayment" class="modal-content shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i> Registrar Abono</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="add_payment">
                <input type="hidden" name="credit_id" id="pay_credit_id">
                
                <div class="alert alert-info py-2">
                    Cliente: <strong id="pay_customer_name"></strong><br>
                    Deuda Actual: <strong class="text-danger" id="pay_balance_display"></strong>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Monto a Abonar (USD) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-bold">$</span>
                        <input type="number" step="0.01" name="amount_usd" id="pay_amount" class="form-control form-control-lg" required>
                    </div>
                    <small class="text-muted" id="pay_bs_conversion">Equivale a: Bs 0.00</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Método de Pago</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="efectivo_bs">Efectivo Bolívares</option>
                        <option value="efectivo_usd">Efectivo Divisa</option>
                        <option value="pago_movil">Pago Móvil</option>
                        <option value="punto">Punto de Venta</option>
                        <option value="transferencia">Transferencia</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success px-4" id="btnSubmitPayment">Confirmar Pago</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalHistory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i> Historial de Pagos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-striped mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Monto USD</th>
                            <th>Monto BS</th>
                            <th>Método</th>
                            <th>Cajero</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        </tbody>
                </table>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <script>
    const bcvRate = <?= $bcvRate ?>;

    $(document).ready(function() {
        $('#creditsTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            order: [[4, 'desc']]
        });

        // Calculadora en vivo en el modal
        $('#pay_amount').on('input', function() {
            let usd = parseFloat($(this).val()) || 0;
            $('#pay_bs_conversion').text(`Equivale a: Bs ${(usd * bcvRate).toFixed(2)}`);
        });

        // Enviar pago por AJAX
        $('#formPayment').on('submit', function(e) {
            e.preventDefault();
            $('#btnSubmitPayment').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');

            $.ajax({
                url: 'actions/actions_credit.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if(res.status) {
                        Swal.fire('¡Éxito!', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                        $('#btnSubmitPayment').prop('disabled', false).text('Confirmar Pago');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
                    $('#btnSubmitPayment').prop('disabled', false).text('Confirmar Pago');
                }
            });
        });
    });

    function openPaymentModal(id, maxAmount, customer) {
        $('#pay_credit_id').val(id);
        $('#pay_customer_name').text(customer);
        $('#pay_balance_display').text('$' + parseFloat(maxAmount).toFixed(2));
        $('#pay_amount').attr('max', maxAmount).val('');
        $('#pay_bs_conversion').text('Equivale a: Bs 0.00');
        
        let modal = new bootstrap.Modal(document.getElementById('modalPayment'));
        modal.show();
    }

    function viewHistory(credit_id) {
        $('#historyTableBody').html('<tr><td colspan="5"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>');
        let modal = new bootstrap.Modal(document.getElementById('modalHistory'));
        modal.show();

        $.ajax({
            url: 'actions/actions_credit.php',
            type: 'POST',
            data: { action: 'get_history', credit_id: credit_id },
            dataType: 'json',
            success: function(res) {
                if(res.status && res.data.length > 0) {
                    let html = '';
                    res.data.forEach(p => {
                        html += `<tr>
                            <td>${new Date(p.created_at).toLocaleString('es-VE')}</td>
                            <td class="text-success fw-bold">$${parseFloat(p.amount_usd).toFixed(2)}</td>
                            <td>Bs ${parseFloat(p.amount_bs).toFixed(2)}</td>
                            <td class="text-capitalize">${p.payment_method.replace('_', ' ')}</td>
                            <td><span class="badge bg-secondary">${p.username || 'N/A'}</span></td>
                        </tr>`;
                    });
                    $('#historyTableBody').html(html);
                } else {
                    $('#historyTableBody').html('<tr><td colspan="5" class="text-muted">No hay pagos registrados.</td></tr>');
                }
            }
        });
    }
</script>
</body>
</html>