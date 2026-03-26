<?php
require_once '../controllers/SalesHistoryController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
    <main class="app-main">
        <?= render_content_header($headerConfig) ?>
        <div class="app-content">
            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm h-100 border-0 border-start border-4 border-success">
                            <div class="card-body">
                                <p class="mb-1 opacity-75"><i class="fas fa-dollar-sign me-1"></i> Recaudo Total en USD</p>
                                <h3 class="mb-0">$ <?= number_format($totalDiaUsd ?? 0, 2) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm h-100 border-0 border-start border-4 border-warning">
                            <div class="card-body">
                                <p class="mb-1 text-secondary"><i class="fas fa-money-bill-wave me-1"></i> Equivalencia en Bs</p>
                                <h3 class="mb-0">Bs. <?= number_format($totalDiaBs ?? 0, 2) ?></h3>
                                <small class="text-muted">Tasa referencial</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm h-100 border-0 border-start border-4 border-info">
                            <div class="card-body py-2">
                                <p class="mb-1 fw-bold text-muted small">TICKET POR MÉTODO DE PAGO</p>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small"><i class="fas fa-cash-register text-success me-1"></i> Efectivo:</span>
                                    <span class="fw-bold"><?= $ticketsEfectivo ?? 0 ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small"><i class="fas fa-credit-card text-primary me-1"></i> Punto/Tarjeta:</span>
                                    <span class="fw-bold"><?= $ticketsPunto ?? 0 ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small"><i class="fas fa-mobile-alt text-info me-1"></i> Pago Móvil:</span>
                                    <span class="fw-bold"><?= $ticketsPMovil ?? 0 ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-outline card-primary shadow-sm">
                    
                    <div class="card-header border-0 pb-0">
                        <div class="row gy-3 align-items-center">
                            <div class="col-12 col-xl-6">
                                <div class="btn-group shadow-sm w-100 w-md-auto overflow-auto">
                                    <a href="?filter=all" class="btn btn-sm btn-outline-primary <?= $filter=='all'?'active':'' ?>">Todos</a>
                                    <a href="?filter=today" class="btn btn-sm btn-outline-primary <?= $filter=='today'?'active':'' ?>">Hoy</a>
                                    <a href="?filter=7days" class="btn btn-sm btn-outline-primary <?= $filter=='7days'?'active':'' ?>">Últimos 7 días</a>
                                    <a href="?filter=30days" class="btn btn-sm btn-outline-primary <?= $filter=='30days'?'active':'' ?>">Últimos 30 días</a>
                                    <a href="?filter=custom" class="btn btn-sm btn-outline-primary <?= $filter=='custom'?'active':'' ?>"><i class="fas fa-calendar-alt"></i> Personalizado</a>
                                </div>
                            </div>
                            
                            <div class="col-12 col-xl-6 d-flex justify-content-xl-end gap-2">
                                <div class="input-group input-group-sm" style="max-width: 300px;">
                                    <span class="input-group-text bg-transparent text-muted"><i class="fas fa-search"></i></span>
                                    <input type="text" id="tableSearch" class="form-control" placeholder="Buscar ticket, cliente o cajero...">
                                </div>
                                <button class="btn btn-outline-sm btn-outline-success shadow-sm" id="btnExportExcel">
                                    <i class="fas fa-file-excel me-1"></i> Exportar a Excel
                                </button>
                            </div>
                        </div>
                    </div>

                    <hr class="mx-3 mt-3 mb-0">

                    <div class="card-body p-0 mt-2">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0 text-center">
                                <thead class="table-transparent">
                                    <tr>
                                        <th class="ps-4 text-start">Ticket</th>
                                        <th>Fecha / Hora</th>
                                        <th>Productos (Cant/Unidad)</th>
                                        <th>Total USD</th>
                                        <th>Total BS</th>
                                        <th class="text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="historyBody">
                                    <?php if(!empty($sales)): ?>
                                        <?php foreach($sales as $s): ?>
                                        <tr class="<?= $s['status'] === 'anulada' ? 'table-danger opacity-75' : '' ?>">
                                            <td class="ps-4 text-start fw-bold text-primary">
                                                #<?= $s['id'] ?>
                                                <?= $s['status'] === 'anulada' ? '<span class="badge bg-danger small">ANULADA</span>' : '' ?>
                                        </td>
                                            <td><small><?= date('d/m/Y', strtotime($s['created_at'])) ?><br><span class="text-muted"><?= date('h:i A', strtotime($s['created_at'])) ?></span></small></td>
                                            
                                            <td><small class="text-muted"><?= htmlspecialchars($s['products_summary'] ?? '3 Ítems') ?></small></td>
                                            
                                            <td class="fw-bold text-success">$ <?= number_format($s['total_amount_usd'], 2) ?></td>
                                            <td class="fw-bold">Bs. <?= number_format($s['total_amount_bs'] ?? 0, 2) ?></td>
                                            
                                            <td class="text-end pe-4">
                                                <div class="btn-group shadow-sm">
                                                    <a href="ticket.php?id=<?= $s['id'] ?>" target="_blank"  class="btn btn-sm btn-outline-secondary me-1" title="Ver Ticket">
                                                        <i class="fas fa-receipt text-secondary"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-info me-1" data-bs-toggle="modal" data-bs-target="#modalView" onclick="loadSaleDetails(<?= $s['id'] ?>)" title="Ver Detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="printTicket(<?= $s['id'] ?>)" title="Imprimir">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <?php if($s['status'] !== 'anulada'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger me-1" onclick="cancelSale(<?= $s['id'] ?>)" title="Anular Venta">
                                                        <i class="fas fa-times-circle"></i>
                                                    </button>
                                                     <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>No hay ventas registradas en este período.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="modalView" tabindex="-1" aria-labelledby="modalViewLabel" aria-hidden="true">
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

    <div class="modal fade" id="modalConfirmAnular" tabindex="-1" aria-hidden="true">
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

<?php include 'layouts/footer.php'; ?>
    <script src="js/sales_history.js"></script>
</body>
</html>