// Variables globales para las instancias de los modales
let modalViewInstance;
let modalEditInstance;
let modalConfirmDeleteInstance;

document.addEventListener("DOMContentLoaded", function() {
    if (typeof bootstrap === 'undefined') {
        console.error('⚠️ ERROR: Bootstrap JS no está cargado. Los modales no funcionarán.');
        return;
    }

    // Inicializar modales dinámicos
    modalViewInstance = new bootstrap.Modal(document.getElementById('modalView'));
    modalEditInstance = new bootstrap.Modal(document.getElementById('modalEdit'));
    modalConfirmDeleteInstance = new bootstrap.Modal(document.getElementById('modalConfirmDelete'));
});

// Inicialización de DataTables usando jQuery
$(document).ready(function() {
    $('#productosTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" // Traducción al español
        },
        "responsive": true,
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
        "order": [[0, "asc"]], // Ordenar por la columna de "Producto" por defecto
        "columnDefs": [
            { "orderable": false, "targets": 10 } // Deshabilita el ordenamiento en la columna "Acciones"
        ],
        "dom": '<"row mb-3"<"col-md-6"l><"col-md-6"f>>rt<"row mt-3"<"col-md-6"i><"col-md-6"p>>' // Estructura adaptada a Bootstrap 5
    });
});

// --- FUNCIONES GLOBALES ---

function viewProduct(p) {
    if (!modalViewInstance) return alert('El modal aún no se ha inicializado.');

    const imgHtml = p.image 
        ? `<div class="text-center mb-3"><img src="${p.image}" class="img-fluid rounded border shadow-sm" style="max-height: 200px; object-fit: contain;"></div>` 
        : `<div class="text-center py-4 bg-secondary bg-opacity-10 border rounded mb-3"><i class="fas fa-box fa-4x text-secondary opacity-50"></i></div>`;
    
    const descHtml = p.description 
        ? `<div class="alert alert-secondary small mb-3 p-2"><i class="fas fa-quote-left me-2 text-muted"></i><em>${p.description}</em></div>` 
        : '';

    const content = `
        ${imgHtml}
        <ul class="list-group list-group-flush mb-3">
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Nombre:</span>
                <span class="fw-bold">${p.name}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Marca:</span>
                <span>${p.brand || '<span class="text-muted fst-italic">N/A</span>'}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">SKU:</span>
                <span>${p.sku || '<span class="text-muted fst-italic">N/A</span>'}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Cód. Barras:</span>
                <span>${p.barcode || '<span class="text-muted fst-italic">N/A</span>'}</span>
            </li>
            ${descHtml ? `<li class="list-group-item bg-transparent px-0 border-0 pb-0">${descHtml}</li>` : ''}
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Categoría:</span>
                <span class="badge text-bg-secondary">${p.category_name || 'General'}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Stock actual:</span>
                <span><span class="badge ${p.stock < 5 ? 'text-bg-danger' : 'text-bg-success'} rounded-pill me-1">${p.stock}</span> Unidades</span>
            </li>
        </ul>
        <div class="card bg-light border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted fw-bold">Costo Base:</span>
                    <span class="text-success fw-bold">$${parseFloat(p.price_base_usd).toFixed(2)}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted fw-bold">Margen:</span>
                    <span class="text-info fw-bold">${parseFloat(p.profit_margin).toFixed(2)}%</span>
                </div>
            </div>
        </div>
    `;
    document.getElementById('viewContent').innerHTML = content;
    modalViewInstance.show();
}

function editProduct(p) {
    if (!modalEditInstance) return alert('El modal aún no se ha inicializado.');

    document.getElementById('edit_id').value = p.id;
    document.getElementById('edit_name').value = p.name;
    document.getElementById('edit_category').value = p.category_id || '';
    document.getElementById('edit_sku').value = p.sku || '';
    document.getElementById('edit_barcode').value = p.barcode || '';
    document.getElementById('edit_brand').value = p.brand || '';
    document.getElementById('edit_price').value = p.price_base_usd;
    document.getElementById('edit_margin').value = p.profit_margin;
    document.getElementById('edit_stock').value = p.stock;
    document.getElementById('edit_image').value = p.image || '';
    document.getElementById('edit_description').value = p.description || '';

    modalEditInstance.show();
}

function confirmDelete(id, name) {
    if (!modalConfirmDeleteInstance) return alert('El modal aún no se ha inicializado.');

    document.getElementById('deleteProductName').innerText = name;
    document.getElementById('btnConfirmDelete').href = `actions/actions_product.php?action=delete&id=${id}`;
    
    modalConfirmDeleteInstance.show();
}