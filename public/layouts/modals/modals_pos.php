
<div class="modal fade" id="modalBCV" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-coins me-2"></i> Actualizar Tasa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <label class="form-label text-muted small">Nueva Tasa (Bs/$)</label>
                <input type="number" step="0.01" id="newRateInput" class="form-control form-control-lg text-center fw-bold text-dark border-warning" value="<?= $bcvRate ?>">
            </div>
            <div class="modal-footer bg-light justify-content-center">
                <button type="button" class="btn btn-warning fw-bold w-100" onclick="saveRate()">Guardar Cambio</button>
            </div>
        </div>
    </div>
</div>



<div class="modal fade" id="modalClearCart" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                <h5 class="fw-bold">¿Limpiar carrito?</h5>
                <p class="text-muted mb-0">Se perderán los items seleccionados.</p>
            </div>
            <div class="modal-footer bg-light justify-content-center">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">No</button>
                <button type="button" class="btn btn-outline-danger px-4" onclick="executeClearCart()">Sí, limpiar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCheckout" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-cash-register me-2"></i> Procesar Venta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" id="btnCloseCheckout"></button>
            </div>
            
            <div id="checkoutStateConfirm">
                <div class="modal-body bg-light">
                    <ul class="list-group list-group-flush mb-3 shadow-sm rounded">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Total Items:</span>
                            <span class="badge bg-primary rounded-pill fs-6" id="checkoutItems">0</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Método:</span>
                            <span class="fw-bold text-success" id="checkoutMethod"></span>
                        </li>
                    </ul>
                    <div class="text-center p-4 rounded border shadow-sm">
                        <h1 class="text-success fw-bold mb-1" id="checkoutTotalBs">Bs 0.00</h1>
                        <span class="badge bg-secondary fs-6" id="checkoutTotalUsd">$0.00</span>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="button" class="btn btn-outline-success fw-bold px-4" onclick="executeSale()">
                        <i class="fas fa-check-circle me-1"></i> Confirmar Pago
                    </button>
                </div>
            </div>

            <div id="checkoutStateResult" style="display:none;" class="text-center py-5">
                <div id="checkoutSpinner">
                    <div class="spinner-border text-success" style="width: 3rem; height: 3rem;" role="status"></div>
                    <p class="mt-3 text-muted fw-bold">Procesando transacción...</p>
                </div>
                <div id="checkoutSuccess" style="display:none;">
                    <i class="fas fa-check-circle fa-5x text-success mb-3"></i>
                    <h3 class="fw-bold text-success">¡Venta Exitosa!</h3>
                    <p class="text-muted">Ticket #<span id="ticketId" class="fw-bold text-dark"></span> generado.</p>
                    <button class="btn btn-outline-success mt-3" data-bs-dismiss="modal" onclick="window.location.reload()"><i class="fas fa-redo me-1"></i> Nueva Venta</button>
                </div>
                <div id="checkoutError" style="display:none;">
                    <i class="fas fa-times-circle fa-5x text-danger mb-3"></i>
                    <h3 class="fw-bold text-danger">Error</h3>
                    <p class="text-muted" id="checkoutErrorMessage"></p>
                    <button class="btn btn-outline-secondary mt-3" onclick="resetCheckoutModal()">Intentar de nuevo</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMessage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i id="msgIcon" class="fas fa-info-circle fa-3x mb-3 text-warning"></i>
                <h5 id="msgText" class="mb-0 fw-bold"></h5>
            </div>
            <div class="modal-footer bg-light justify-content-center">
                 <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDefault" tabindex="-1" aria-labelledby="modalDefaultLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalDefaultLabel">
                    <i class="fas fa-chart-line me-2"></i>Resumen de Ventas - Hoy
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light">
                            <small class="text-muted d-block text-uppercase fw-bold">Total USD</small>
                            <h4 class="text-success fw-bold mb-0">$0.00</h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light">
                            <small class="text-muted d-block text-uppercase fw-bold">Total BS</small>
                            <h4 class="text-primary fw-bold mb-0">Bs. 0.00</h4>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="list-group list-group-flush small">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-money-bill-wave me-2 text-muted"></i>Efectivo</span>
                        <span class="fw-bold">$0.00</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-mobile-alt me-2 text-muted"></i>Pago Móvil</span>
                        <span class="fw-bold">$0.00</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-credit-card me-2 text-muted"></i>Punto de Venta</span>
                        <span class="fw-bold">$0.00</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cerrar Reporte</button>
            </div>
        </div>
    </div>
</div>

    <div class="modal fade" id="modalCustomer" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fas fa-users me-2"></i> Seleccionar o Crear Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <ul class="nav nav-tabs mb-3" id="customerTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold text-dark" id="search-tab" data-bs-toggle="tab" data-bs-target="#searchCustomerPane" type="button">Buscar</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold text-dark" id="new-tab" data-bs-toggle="tab" data-bs-target="#newCustomerPane" type="button">Nuevo Cliente</button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="searchCustomerPane">
                            <input type="text" id="inputSearchCustomer" class="form-control mb-3 border-warning" placeholder="Buscar por nombre o cédula/RIF...">
                            <div class="list-group" id="customerResults" style="max-height: 200px; overflow-y: auto;">
                                <div class="text-center text-muted p-3 small">Escribe para buscar...</div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="newCustomerPane">
                            <form id="formNewCustomer">
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Nombre Completo <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Cédula / RIF</label>
                                    <input type="text" name="document" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Teléfono</label>
                                    <input type="text" name="phone" class="form-control">
                                </div>
                                <button type="submit" class="btn btn-success w-100 fw-bold" id="btnSaveCustomer">
                                    <i class="fas fa-save me-1"></i> Guardar y Seleccionar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>