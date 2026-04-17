    <div class="modal fade" id="modalNewTenant" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form id="formNewTenant" class="modal-content shadow border-0">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-store me-2"></i> Registrar Nueva Tienda</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="create_tenant">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Nombre del Negocio</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-building"></i></span>
                                <input type="text" name="name" class="form-control" placeholder="Ej: Supermercado XYZ" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">RIF / Identificación</label>
                            <input type="text" name="rif" class="form-control" placeholder="Ej: J-12345678-9" required>
                        </div>

                        <div class="col-12">
                            <hr class="opacity-25 my-2">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Usuario Administrador</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-shield"></i></span>
                                <input type="text" name="admin_user" class="form-control" placeholder="Ej: admin_xyz" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" name="admin_pass" class="form-control" required>
                            </div>
                        </div>

                        <div class="col-md-12 mt-4">
                            <label class="form-label fw-bold small text-muted text-uppercase">Duración de Licencia Inicial</label>
                            <select name="months" class="form-select form-select-lg border-success">
                                <option value="1">1 Mes (Prueba)</option>
                                <option value="6">6 Meses</option>
                                <option value="12">1 Año</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold px-4"><i class="fas fa-save me-1"></i> Crear y Activar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalBCV" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form id="formBCV" class="modal-content shadow border-0">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fas fa-sync-alt me-2"></i> Tasa Global</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <input type="hidden" name="action" value="update_bcv">
                    <label class="form-label fw-bold text-muted small text-uppercase">Tasa de Cambio (Bs/USD)</label>
                    <div class="input-group input-group-lg mb-3">
                        <span class="input-group-text bg-light fw-bold">Bs.</span>
                        <input type="number" step="0.01" name="rate" class="form-control text-center fw-bold text-primary" value="<?= $bcv['bcv_rate'] ?>" required>
                    </div>
                    <div class="alert alert-warning py-2 mb-0 small text-start">
                        <i class="fas fa-info-circle me-1"></i> Actualizará los precios de TODAS las tiendas.
                    </div>
                </div>
                <div class="modal-footer bg-light p-2 justify-content-center">
                    <button type="submit" class="btn btn-warning w-100 fw-bold shadow-sm">Guardar Tasa</button>
                </div>
            </form>
        </div>
    </div>

<div class="modal fade" id="modalRenew" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formRenew" class="modal-content shadow border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> Renovar Suscripción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="renew">
                <input type="hidden" name="id" id="renew_id">
                
                <div class="alert alert-primary bg-primary-subtle text-primary border-0 mb-4">
                    Renovando tienda: <strong id="renew_name" class="fs-5 d-block">Nombre Tienda</strong>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold text-uppercase">Extender por</label>
                        <select name="months" class="form-select border-primary">
                            <option value="1">1 Mes</option>
                            <option value="3">3 Meses</option>
                            <option value="6">6 Meses</option>
                            <option value="12">1 Año</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold text-uppercase">Monto Cobrado (USD)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">$</span>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="Ej: 29.99" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold text-uppercase">Método de Pago</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="" disabled selected>Seleccionar...</option>
                            <option value="Binance Pay">Binance Pay (USDT)</option>
                            <option value="Zelle">Zelle</option>
                            <option value="Pago Movil">Pago Móvil (Bs)</option>
                            <option value="Efectivo USD">Efectivo (Divisa)</option>
                            <option value="Transferencia">Transferencia Bancaria</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-bold text-uppercase">N° Referencia</label>
                        <input type="text" name="reference" class="form-control" placeholder="Opcional">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fas fa-check-circle me-1"></i> Procesar Pago</button>
            </div>
        </form>
    </div>
</div>
    <div class="modal fade" id="modalViewTenant" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-eye me-2"></i> Detalles de la Tienda</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">ID:</span>
                            <span id="view_id" class="fw-bold"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Negocio:</span>
                            <span id="view_name" class="text-primary fw-bold"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">RIF:</span>
                            <span id="view_rif"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Usuario Admin:</span>
                            <span id="view_admin" class="font-monospace bg-light px-2 rounded"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Licencia:</span>
                            <span id="view_license" class="badge text-bg-dark font-monospace"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Fecha Creada:</span>
                            <span id="view_created"></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<div class="modal fade" id="modalEditTenant" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form id="formEditTenant" class="modal-content shadow border-0">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i> Editar Tienda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit_tenant">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Nombre del Negocio</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-building"></i></span>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">RIF / Identificación</label>
                            <input type="text" name="rif" id="edit_rif" class="form-control" required>
                        </div>
                        
                        <div class="col-12">
                            <hr class="opacity-25 my-2">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">Nueva Contraseña de Admin</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" name="new_password" class="form-control" placeholder="Dejar en blanco para no cambiar">
                            </div>
                            <small class="text-muted d-block mt-1">Si el cliente olvidó su clave, puedes generar una nueva aquí.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark"><i class="fas fa-save me-1"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>