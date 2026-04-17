<div class="modal fade" id="modalView" tabindex="-1" aria-labelledby="modalViewLabel">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="modalViewLabel"><i class="fas fa-list text-primary me-2"></i>Detalles de la Venta <span id="modalTicketNumber" class="fw-bold"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalViewContent">
                <div class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando detalles...
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalConfirmAnular" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirmar Anulación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p class="fs-5 mb-0">¿Estás completamente seguro de que deseas <strong>ANULAR</strong> la venta <span id="spanTicketAnular" class="text-danger fw-bold"></span>?</p>
                <p class="text-muted small mt-2">Esta acción no se puede deshacer y revertirá el stock/totales.</p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarAnular">Sí, Anular Venta</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalConfirmBorrar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Confirmar Borrado</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p class="fs-5 mb-0">¿Estás completamente seguro de que deseas <strong class="text-danger">BORRAR DEFINITIVAMENTE</strong> la venta <span id="spanTicketBorrar" class="text-danger fw-bold"></span>?</p>
                <p class="text-muted small mt-2">Esta acción borrará el registro de la base de datos por completo y restaurará el stock (si no estaba anulada). <strong>Esta acción NO se puede deshacer.</strong></p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarBorrar">Sí, Borrar Venta</button>
            </div>
        </div>
    </div>
</div>