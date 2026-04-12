<div class="modal fade" id="modalCustomerForm" tabindex="-1">
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
                        <input type="text" name="phone" id="customerPhone" class="form-control" autocomplete="phone" placeholder="Ej: 0414-1234567">
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
<div class="modal fade" id="modalView" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i> Detalles del Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewContent">
            </div>
            <div class="modal-footer bg-light p-2">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
