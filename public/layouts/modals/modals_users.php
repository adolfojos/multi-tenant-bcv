<div class="modal fade" id="modalUser" tabindex="-1" >
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-user-plus me-2"></i> Nuevo Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="saveUser(event)">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="userAction" value="create">
                    <input type="hidden" name="id" id="userId">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre de Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-at"></i></span>
                            <input type="text" name="username" id="username" class="form-control" required placeholder="ej: adolfo_dev">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre Completo</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Contraseña</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="••••••••">
                            <div id="pwHelp" class="form-text small" style="display:none;">Dejar en blanco para mantener actual.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">Rol de Acceso</label>
                            <select name="role" id="role" class="form-select">
                                <option value="seller">Vendedor</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>