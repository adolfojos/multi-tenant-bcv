<div class="modal fade" id="modalCat" tabindex="-1" aria-labelledby="modalTitle" >
    <div class="modal-dialog modal-dialog-centered">
        <form id="formCat" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-tag me-2"></i> Categoría</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="action" value="create">
                <input type="hidden" name="id" id="catId">
                <div class="mb-3">
                    <label for="catName" class="form-label">Nombre de la Categoría <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="catName" class="form-control" placeholder="Ej: Lubricantes, Filtros..." required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="modalDelete" tabindex="-1" >
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal-dark">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Eliminar Categoría</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="text-danger mb-3">
                    <i class="fas fa-trash-alt fa-3x"></i>
                </div>
                <p class="mb-1">¿Estás seguro de que deseas eliminar la categoría?</p>
                <h4 id="deleteCatName" class="fw-bold mb-3"></h4>
                <div class="alert alert-warning text-start small mb-0">
                    <i class="fas fa-info-circle me-1"></i> Los productos asociados podrían quedar sin categoría asignada. Esta acción no se puede deshacer.
                </div>
                <input type="hidden" id="deleteCatId">
            </div>
            <div class="modal-footer justify-content-center bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="executeDelete()"><i class="fas fa-trash me-1"></i> Sí, Eliminar</button>
            </div>
        </div>
    </div>
</div>
