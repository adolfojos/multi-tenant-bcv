
<div class="modal fade" id="modalPayment" tabindex="-1" >
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

<div class="modal fade" id="modalHistory" tabindex="-1" >
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