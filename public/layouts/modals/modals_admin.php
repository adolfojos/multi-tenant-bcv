<div class="modal fade" id="modalInsert" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="formInsert" method="POST" class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Añadir Nuevo Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="create">

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold">Nombre del Producto <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-box"></i></span>
                            <input type="text" name="name" class="form-control" placeholder="Nombre descriptivo" required>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Categoría <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="" disabled selected>Seleccione...</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Descripción</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Breve descripción del producto..."></textarea>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">SKU</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-barcode"></i></span>
                            <input type="text" class="form-control" name="sku" placeholder="MTB-001">
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Código de Barras</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-qrcode"></i></span>
                            <input type="text" class="form-control" name="barcode" placeholder="750123...">
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Marca</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-tag"></i></span>
                            <input type="text" class="form-control" name="brand" placeholder="Shimano">
                        </div>
                    </div>

                    <hr class="my-3 opacity-25">

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Costo Base ($) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">$</span>
                            <input type="number" step="0.01" name="price" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">% Margen <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="margin" class="form-control" value="30" required>
                            <span class="input-group-text bg-light">%</span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Stock Inicial <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-boxes"></i></span>
                            <input type="number" name="stock" class="form-control" required>
                        </div>
                    </div>

                    <div class="col-12 mb-0">
                        <label class="form-label fw-bold">URL Imagen</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-image"></i></span>
                            <input type="text" name="image" class="form-control" placeholder="https://ejemplo.com/foto.jpg">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Guardar Producto</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="formEdit" method="POST" class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Editar Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold">Nombre del Producto <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-box"></i></span>
                            <input type="text" name="name" id="edit_name" class="form-control" placeholder="Nombre descriptivo" required>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Categoría <span class="text-danger">*</span></label>
                        <select name="category_id" id="edit_category" class="form-select" required>
                            <option value="" disabled selected>Seleccione...</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Descripción</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2" placeholder="Breve descripción del producto..."></textarea>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">SKU</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-barcode"></i></span>
                            <input type="text" class="form-control" name="sku" id="edit_sku" placeholder="MTB-001">
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Código de Barras</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-qrcode"></i></span>
                            <input type="text" class="form-control" name="barcode" id="edit_barcode" placeholder="750123...">
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Marca</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-tag"></i></span>
                            <input type="text" class="form-control" name="brand" id="edit_brand" placeholder="Shimano">
                        </div>
                    </div>

                    <hr class="my-3 opacity-25">

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Costo Base ($) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">$</span>
                            <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">% Margen <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="margin" id="edit_margin" class="form-control" value="30" required>
                            <span class="input-group-text bg-light">%</span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Stock Inicial <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-boxes"></i></span>
                            <input type="number" name="stock" id="edit_stock" class="form-control" required>
                        </div>
                    </div>

                    <div class="col-12 mb-0">
                        <label class="form-label fw-bold">URL Imagen</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-image"></i></span>
                            <input type="text" name="image" id="edit_image" class="form-control" placeholder="https://ejemplo.com/foto.jpg">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Actualizar Producto</button>
            </div>
        </form>
    </div>
</div>


<div class="modal fade" id="modalView" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i> Detalles del Producto</h5>
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