
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <h3 class="mb-0"><i class="fas fa-box text-primary me-2"></i> <?= htmlspecialchars($current_page) ?></h3>
                        <small class="text-secondary"><?= htmlspecialchars($tenant_name) ?></small>
                    </div>
                    <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
                        <span class="badge bg-dark border border-warning text-warning px-3 py-2 me-2" title="Tasa de cambio oficial">
                            <i class="fas fa-coins me-1" ></i> BCV: Bs. <?= number_format($bcvRate, 2) ?>
                        </span>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalInsert">
                            <i class="fas fa-plus me-1"></i> Nuevo Producto
                        </button>
                    </div>
                </div>
            </div>
        </div>