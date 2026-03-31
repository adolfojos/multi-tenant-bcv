<?php
require_once '../controllers/PosController.php';
include 'layouts/head.php';
include 'layouts/navbar.php';
include 'layouts/sidebar.php'; 
?>
    <main class="app-main">
        <?= render_content_header($headerConfig) ?>
        <div class="app-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-3 shadow-sm">
                            <div class="card-body p-2">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" id="searchInput" class="form-control border-0 bg-light" placeholder="Buscar producto (Presiona F3)...">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3" id="productsGrid">
                            <?php foreach($products as $p): 
                                $price_usd = $p['price_base_usd'] * (1 + ($p['profit_margin'] / 100));
                                $price_bs  = $price_usd * $bcvRate;
                                $is_stock  = $p['stock'] > 0;
                                $has_image = !empty($p['image']); 
                                $img_url = $has_image ? htmlspecialchars($p['image']) : '';
                                $desc = !empty($p['description']) ? htmlspecialchars($p['description']) : 'Sin descripción';
                                $sku = !empty($p['sku']) ? htmlspecialchars($p['sku']) : 'N/A';
                                $categoria = !empty($p['category']) ? htmlspecialchars($p['category']) : 'N/A';
                                $brand = !empty($p['brand']) ? htmlspecialchars($p['brand']) : 'N/A';
                            ?>
                            <div class="col-6 col-md-4 col-xl-3 product-item" 
                                 data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>" 
                                 data-desc="<?= htmlspecialchars(strtolower($desc)) ?>" 
                                 data-sku="<?= htmlspecialchars(strtolower($sku)) ?>" 
                                 data-category="<?= htmlspecialchars(strtolower($categoria)) ?>" 
                                 data-brand="<?= htmlspecialchars(strtolower($brand)) ?>" 
                                 data-price="<?= $price_usd ?>">
                            <div class="card h-100 shadow-sm border" style="cursor: pointer; transition: transform 0.2s;">
                                <div onclick='addToCart(<?= $p['id'] ?>, <?= json_encode($p['name']) ?>, <?= $price_usd ?>, <?= $p['stock'] ?>)' class="d-flex flex-column h-100">
                                    <div class="text-center bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center" style="height: 120px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                        <?php if($has_image): ?>
                                            <img src="uploads/<?= $img_url ?>" class="img-fluid" style="max-height: 100%; object-fit: contain;" alt="<?= htmlspecialchars($p['name']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-box-open fa-3x text-secondary opacity-50"></i>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-body p-2 d-flex flex-column text-center">
                                        <h6 class="card-title text-truncate fw-bold mb-1 w-100" title="<?= htmlspecialchars($p['name']) ?>">
                                            <?= htmlspecialchars($p['name']) ?>
                                        </h6>
                                        <small class="text-muted text-truncate w-100 mb-1"><?= $desc ?></small>
            
                                        <div class="d-flex flex-wrap justify-content-center gap-1 mb-2" style="font-size: 0.70rem;">
                                            <span class="badge bg-light text-secondary border" title="SKU"><i class="fas fa-barcode me-1"></i><?= $sku ?></span>
                                            <span class="badge bg-light text-secondary border" title="Categoría"><i class="fas fa-tags me-1"></i><?= $categoria ?></span>
                                            <span class="badge bg-light text-secondary border" title="Marca"><i class="fas fa-industry me-1"></i><?= $brand ?></span>
                                        </div>
                                        <div class="mt-auto">
                                            <div class="text-success fw-bold fs-5">$<?= number_format($price_usd, 2) ?></div>
                                            <div class="text-muted small mb-2">Bs <?= number_format($price_bs, 2) ?></div>
                                            
                                            <?php if($is_stock): ?>
                                                <span class="badge text-bg-info rounded-pill">Stock: <?= $p['stock'] ?></span>
                                            <?php else: ?>
                                                <span class="badge text-bg-danger rounded-pill">Agotado</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-lg-4 mt-4 mt-lg-0">
                        <div class="card card-outline card-success shadow-sm sticky-top" style="top: 70px; z-index: 1000;" id="posPanel">
                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center py-2">
                                <h5 class="card-title mb-0 fw-bold"><i class="fas fa-shopping-cart me-2"></i>Ticket Actual</h5>
                                <span class="badge bg-light text-success fs-6 rounded-pill" id="itemCount">0</span>
                            </div>

                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                    <table class="table table-hover table-striped align-middle mb-0 small">
                                        <thead class="table sticky-top">
                                            <tr>
                                                <th width="15%" class="text-center">Cant</th>
                                                <th>Item</th>
                                                <th class="text-end">Total</th>
                                                <th width="10%"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="cartTableBody">
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted">
                                                    <i class="fas fa-cart-arrow-down fa-2x mb-2 opacity-50"></i><br>El carrito está vacío
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="card-footer bg-light">
                                <div class="d-flex justify-content-between mb-1 text-secondary">
                                    <span>Subtotal USD:</span>
                                    <span class="fw-bold text-dark" id="totalUsdDisplay">$0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                                    <span class="h5 text-success mb-0">Total BS:</span>
                                    <span class="h5 text-success fw-bold mb-0" id="totalBsDisplay">Bs 0.00</span>
                                </div>

                                <label form="paymentMethod" class="small fw-bold text-secondary mb-1">Método de Pago</label>
                                <select class="form-select mb-3 border-success" id="paymentMethod">
                                    <option value="efectivo_bs"><i class="fas fa-money-bill-wave"></i>Efectivo Bolívares</option>
                                    <option value="efectivo_usd"><i class="fas fa-dollar-sign"></i>Efectivo Divisa</option>
                                    <option value="pago_movil"><i class="fas fa-mobile-alt"></i>Pago Móvil</option>
                                    <option value="punto"><i class="fas fa-credit-card"></i>Punto de Venta</option>
                                    <option value="credito"><i class="fas fa-file-invoice-dollar"></i>Crédito (Por Cobrar)</option>
                                </select>

                                <div id="creditData" style="display: none;" class="mb-3 p-3 bg-warning bg-opacity-10 border border-warning rounded">
                                    <label form="selectedCustomerDisplay" class="small fw-bold text-dark">Cliente <span class="text-danger">*</span></label>
                                    <input type="hidden" id="selectedCustomerId" name="customer_id" value="">
                                    <div class="input-group mb-2">
                                        <input type="text" id="selectedCustomerDisplay" class="form-control form-control-sm border-warning bg-white" placeholder="Ningún cliente..." readonly>
                                        <button class="btn btn-warning btn-sm fw-bold text-dark" type="button" data-bs-toggle="modal" data-bs-target="#modalCustomer">
                                            <i class="fas fa-search me-1"></i> Buscar
                                        </button>
                                    </div>
                                    <label form="creditDueDate" class="small fw-bold text-dark">Fecha límite de pago</label>
                                    <input type="date" id="creditDueDate" name="due_date" class="form-control form-control-sm border-warning">
                                </div>

                                <div class="row g-2">
                                    <div class="col-9">
                                        <button class="btn btn-outline-success w-100 fw-bold btn-lg shadow-sm" onclick="initiateCheckout()" id="btnConfirmSale">
                                            <i class="fas fa-check-circle me-1"></i> COBRAR
                                        </button>
                                    </div>
                                    <div class="col-3">
                                        <button class="btn btn-outline-danger w-100 btn-lg shadow-sm" onclick="confirmClearCart()" title="Limpiar carrito">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> 
            </div>
        </div>
    </main>



    <?php
    include 'layouts/footer.php'; 
    include 'layouts/modals/modals_pos.php';
    ?>
<script>
        window.APP_BCV_RATE = <?= $bcvRate ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/pos.js"></script>
</body>
</html>