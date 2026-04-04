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
