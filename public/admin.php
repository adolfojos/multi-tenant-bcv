<?php
require_once '../controllers/AdminController.php';
include 'layouts/head.php';
// Agregamos el CSS de DataTables (si prefieres, muévelo a layouts/head.php)
echo '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">';
include 'layouts/navbar.php';
include 'layouts/sidebar.php';
?>
<main class="app-main">
    <?= render_content_header($headerConfig) ?>
     <div class="app-content">
        <div class="container-fluid">
            
            <?php 
            if(isset($_GET['msg'])): 
                $alerts = [
                    'created' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Producto añadido correctamente.'],
                    'updated' => ['class' => 'info', 'icon' => 'edit', 'text' => 'Producto actualizado con éxito.'],
                    'deleted' => ['class' => 'warning', 'icon' => 'trash', 'text' => 'Producto eliminado del inventario.']
                ];
                $m = $alerts[$_GET['msg']] ?? null;
                if($m):
            ?>
                <div class="alert alert-<?= $m['class'] ?> alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-<?= $m['icon'] ?> me-2"></i> <?= $m['text'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; endif; ?>

            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <strong>Error:</strong> <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end gap-2 mb-3">
                <a href="generate_pdf.php?type=sales" target="_blank" class="btn btn-sm btn-outline-info">
                    <i class="fas fa-file-pdf me-1"></i> Cierre del Día
                </a>
                <a href="generate_pdf.php?type=inventory" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-boxes me-1"></i> Reporte Inventario
                </a>
            </div>

            <div class="row">
                <div class="col-12 col-sm-6 col-md-4 mb-3">
                    <div class="info-box shadow-sm h-100">
                        <span class="info-box-icon text-bg-success"><i class="fas fa-cash-register"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-uppercase small fw-bold text-secondary">Ventas de Hoy</span>
                            <span class="info-box-number text-success fs-4 mb-0">$<?= number_format($salesToday, 2) ?></span>
                            <span class="progress-description text-muted small">≈ Bs. <?= number_format($salesToday * $bcvRate, 2) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4 mb-3">
                    <div class="info-box shadow-sm h-100">
                        <span class="info-box-icon <?= $lowStockCount > 0 ? 'text-bg-danger' : 'text-bg-info' ?>">
                            <i class="fas fa-exclamation-triangle"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-uppercase small fw-bold text-secondary">Bajo Stock (< 5)</span>
                            <span class="info-box-number <?= $lowStockCount > 0 ? 'text-danger' : 'text-info' ?> fs-4 mb-0"><?= $lowStockCount ?></span>
                            <span class="progress-description <?= $lowStockCount > 0 ? 'text-danger opacity-75' : 'text-muted' ?> small">
                                <?= $lowStockCount > 0 ? '¡Necesitas reponer!' : 'Stock saludable' ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4 mb-3">
                    <div class="info-box shadow-sm h-100">
                        <span class="info-box-icon text-bg-warning"><i class="fas fa-cubes text-white"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-uppercase small fw-bold text-secondary">Capital Inventario</span>
                            <span class="info-box-number text-warning fs-4 mb-0">$<?= number_format($inventoryValue, 2) ?></span>
                            <span class="progress-description text-muted small">Valor a costo base</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="productosTable" class="table table-hover table-striped align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Producto</th>
                                    <th>SKU</th> 
                                    <th>Marca</th> 
                                    <th>Categoría</th>
                                    <th>Stock</th>
                                    <th>Costo ($)</th>
                                    <th>Costo (Bs)</th>
                                    <th>Precio ($)</th>
                                    <th>Precio (Bs)</th>
                                    <th>Ganancia</th>
                                    <th class="text-end pe-3">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="inventoryBody">
                                <?php if(!empty($products)): ?>
                                    <?php foreach($products as $p): 
                                        $costo_usd = $p['price_base_usd'];
                                        $precio_usd = $costo_usd * (1 + ($p['profit_margin'] / 100));
                                        $ganancia_usd = $precio_usd - $costo_usd;
                                        $ganancia_bs = $ganancia_usd * $bcvRate;
                                        $p_json = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center">
                                                <?php if(!empty($p['image'])): ?>
                                                    <img src="uploads/<?= htmlspecialchars($p['image']) ?>" class="rounded object-fit-contain me-3 border" style="width: 45px; height: 45px;" alt="img">
                                                <?php else: ?>
                                                    <div class="bg-secondary bg-opacity-25 rounded d-flex align-items-center justify-content-center me-3 border" style="width: 45px; height: 45px;">
                                                        <i class="fas fa-box text-secondary"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong class="d-block"><?= htmlspecialchars($p['name']) ?></strong>
                                                    <?php if(!empty($p['description'])): ?>
                                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;"><?= htmlspecialchars($p['description']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td class="small text-muted font-monospace"><?= !empty($p['sku']) ? htmlspecialchars($p['sku']) : '-' ?></td>
                                        <td><span class="badge text-bg-light border"><?= !empty($p['brand']) ? htmlspecialchars($p['brand']) : 'N/A' ?></span></td>

                                        <td>
                                            <span class="badge text-bg-secondary bg-opacity-75">
                                                <?= htmlspecialchars($p['category_name'] ?? 'General') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill <?= $p['stock'] < 5 ? 'text-bg-danger' : 'text-bg-success' ?>">
                                                <?= $p['stock'] ?> ud
                                            </span>
                                        </td>
                                        
                                        <td class="text-success fw-bold">$<?= number_format($costo_usd, 2) ?></td>
                                        <td class="fw-bold">Bs. <?= number_format($costo_usd * $bcvRate, 2) ?></td>
                                        <td class="text-success fw-bold">$<?= number_format($precio_usd, 2) ?></td>
                                        <td class="fw-bold">Bs. <?= number_format($precio_usd * $bcvRate, 2) ?></td>
                                        
                                        <td class="text-nowrap">
                                            <span style="color: #0d47a1;" class="fw-bold me-1">$<?= number_format($ganancia_usd, 2) ?></span>
                                            <span class="badge rounded-pill px-2 py-1" style="background-color: #d1fae5; color: #0f766e; font-size: 0.85rem; font-weight: 600;">
                                                <?= number_format($ganancia_bs, 2) ?> bs
                                            </span>
                                        </td>

                                        <td class="text-end pe-3">
                                            <div class="btn-group">                                        
                                                <button class="btn btn-sm btn-outline-info me-1" onclick='viewProduct(<?= $p_json ?>)' title="Ver Detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning me-1" onclick='editProduct(<?= $p_json ?>)' title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger me-1" onclick='confirmDelete(<?= $p["id"] ?>, "<?= addslashes($p["name"]) ?>")' title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div> </div> </div> </div> </main>

<?php
include 'layouts/footer.php'; 
include 'layouts/modals/modals_admin.php';
?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script src="js/admin.js"></script>
</body>
</html>