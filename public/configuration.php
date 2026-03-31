<?php
require_once '../controllers/ConfigController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php';
?>
<main class="app-main">
    <?= render_content_header($headerConfig) ?>
    <div class="app-content">
        <div class="container-fluid">
            <form id="formConfig" action="actions/actions_config.php" method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card card-outline card-primary mb-4 shadow-sm">
                            <div class="card-header">
                                <h3 class="card-title fw-bold">Perfil del Negocio</h3>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Nombre de la Empresa</label>
                                        <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($tenant_data['business_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">RIF / Identificación Fiscal</label>
                                        <input type="text" name="rif" class="form-control" placeholder="J-12345678-0" value="<?= htmlspecialchars($tenant_data['rif'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Dirección Comercial</label>
                                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($tenant_data['address'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Teléfono de Contacto</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                            <input type="text" name="phone" class="form-control" placeholder="+58..." value="<?= htmlspecialchars($tenant_data['phone'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Moneda Principal</label>
                                        <select class="form-select" name="currency">
                                            <option value="USD" <?= ($tenant_data['currency'] == 'USD') ? 'selected' : '' ?>>Dólares (USD)</option>
                                            <option value="VES" <?= ($tenant_data['currency'] == 'VES') ? 'selected' : '' ?>>Bolívares (VES)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card card-outline card-info mb-4 shadow-sm">
                            <div class="card-header">
                                <h3 class="card-title fw-bold">Personalización de Tickets</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Mensaje al pie del ticket</label>
                                    <textarea name="ticket_footer" class="form-control"><?= htmlspecialchars($tenant_data['ticket_footer'] ?? '') ?></textarea>
                                    <div class="form-text">Este texto aparecerá al final de cada recibo impreso.</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="showLogo" name="show_logo" <?= !empty($tenant_data['show_logo']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="showLogo">Mostrar logo en el encabezado</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header">
                                <h3 class="card-title fw-bold">Interfaz</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="dark_mode" id="darkModeSwitch" <?= ($tenant_data['theme'] === 'dark') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="darkModeSwitch">Modo Oscuro</label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="compact_tables" id="compactTables" <?= !empty($tenant_data['compact_tables']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="compactTables">Tablas compactas</label>
                                </div>
                            </div>
                        </div>

                        <div class="card card-danger card-outline shadow-sm">
                            <div class="card-header">
                                <h3 class="card-title fw-bold text-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Zona Crítica
                                </h3>
                            </div>
                            <div class="card-body">
                                <p class="small text-secondary mb-3">Las siguientes acciones no se pueden deshacer. Por favor, proceda con precaución.</p>


                                <button type="button" class="btn btn-outline-danger w-100 mb-2 btn-sm text-start" onclick="confirmAction('purgar las ventas del mes', 'purge_sales')">
                                    <i class="fas fa-eraser me-2"></i> Purgar Ventas del Mes
                                </button>

                                <button type="button" class="btn btn-outline-danger w-100 btn-sm text-start" onclick="confirmAction('reiniciar el correlativo de facturas', 'reset_correlative')">
                                    <i class="fas fa-redo-alt me-2"></i> Reiniciar Correlativo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>
<?php include 'layouts/footer.php'; ?>
<script src="js/config.js"></script>
</body>

</html>