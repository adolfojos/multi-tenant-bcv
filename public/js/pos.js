// Variables Globales
let cart = [];
const bcvRate = window.APP_BCV_RATE;

let modalCheckoutInstance;
let modalMessageInstance;
let modalClearCartInstance;
let modalBCVInstance;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Modales Bootstrap
    modalCheckoutInstance = new bootstrap.Modal(document.getElementById('modalCheckout'));
    modalMessageInstance = new bootstrap.Modal(document.getElementById('modalMessage'));
    modalClearCartInstance = new bootstrap.Modal(document.getElementById('modalClearCart'));
    modalBCVInstance = new bootstrap.Modal(document.getElementById('modalBCV'));
});

// --- Atajo de teclado para el buscador ---
document.addEventListener('keydown', function(e) {
    if (e.key === 'F3') {
        e.preventDefault();
        document.getElementById('searchInput').focus();
    }
});

// --- LÓGICA DE CRÉDITO Y CLIENTES ---
document.getElementById('paymentMethod').addEventListener('change', function() {
    if (this.value === 'credito') {
        $('#creditData').slideDown();
        // Evitar procesar venta si no hay cliente
        document.getElementById('btnConfirmSale').disabled = (document.getElementById('selectedCustomerId').value === '');
    } else {
        $('#creditData').slideUp();
        document.getElementById('btnConfirmSale').disabled = false; // Rehabilitar
    }
});

let searchTimer;
$('#inputSearchCustomer').on('keyup', function() {
    clearTimeout(searchTimer);
    let term = $(this).val();
    
    if(term.length < 2) {
        $('#customerResults').html('<div class="text-center text-muted p-3 small">Escribe al menos 2 letras...</div>');
        return;
    }

    $('#customerResults').html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>');

    searchTimer = setTimeout(function() {
        $.ajax({
            url: 'actions_customer.php',
            type: 'POST',
            data: { action: 'search', term: term },
            dataType: 'json',
            success: function(res) {
                if(res.status && res.data.length > 0) {
                    let html = '';
                    res.data.forEach(c => {
                        html += `<button type="button" class="list-group-item list-group-item-action" 
                                    onclick="selectCustomer(${c.id}, '${c.name.replace(/'/g, "\\'")}', '${c.document ? c.document.replace(/'/g, "\\'") : ''}')">
                                    <strong>${c.name}</strong> <br>
                                    <small class="text-muted">${c.document || 'Sin Cédula/RIF'}</small>
                                 </button>`;
                    });
                    $('#customerResults').html(html);
                } else {
                    $('#customerResults').html('<div class="text-center text-danger p-3 small">No se encontraron clientes.</div>');
                }
            }
        });
    }, 400); 
});

$('#formNewCustomer').on('submit', function(e) {
    e.preventDefault();
    let btn = $('#btnSaveCustomer');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

    $.ajax({
        url: 'actions/actions_customer.php',
        type: 'POST',
        data: $(this).serialize() + '&action=create',
        dataType: 'json',
        success: function(res) {
            if(res.status) {
                selectCustomer(res.customer.id, res.customer.name, res.customer.document);
                $('#formNewCustomer')[0].reset(); 
                Swal.fire({
                    icon: 'success',
                    title: 'Cliente guardado',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Guardar y Seleccionar');
        }
    });
});

function selectCustomer(id, name, customerDoc) {
    $('#selectedCustomerId').val(id);
    let displayText = name + (customerDoc ? ' (' + customerDoc + ')' : '');
    $('#selectedCustomerDisplay').val(displayText);
    
    // Habilitar el botón de venta
    $('#btnConfirmSale').prop('disabled', false);
    
    // Cerrar el modal
    var modal = bootstrap.Modal.getInstance(document.getElementById('modalCustomer'));
    modal.hide();
}


// --- UTILIDAD: Mostrar Mensaje Genérico ---
function showMessage(text, type = 'info') {
    const icon = document.getElementById('msgIcon');
    document.getElementById('msgText').innerText = text;
    
    icon.className = 'fas fa-3x mb-3';
    if(type === 'error') {
        icon.classList.add('fa-times-circle', 'text-danger');
    } else if (type === 'success') {
        icon.classList.add('fa-check-circle', 'text-success');
    } else {
        icon.classList.add('fa-exclamation-circle', 'text-warning');
    }
    modalMessageInstance.show();
}

// --- 1. LÓGICA TASA BCV ---
function openBCVModal() {
    modalBCVInstance.show();
}

function saveRate() {
    const newRate = document.getElementById('newRateInput').value;
    modalBCVInstance.hide();
    showMessage("Tasa actualizada a: " + newRate, 'success');
    setTimeout(() => location.reload(), 1500);
}

// --- 2. LÓGICA CRUD PRODUCTOS ---
function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_price').value = data.price_base_usd;
    document.getElementById('edit_margin').value = data.profit_margin;
    document.getElementById('edit_stock').value = data.stock;
    document.getElementById('edit_image').value = data.image || '';
    document.getElementById('edit_description').value = data.description || '';
     document.getElementById('edit_sku').value = data.sku || '';
    
    modalEditInstance.show();
}

function openDeleteModal(id, name) {
    document.getElementById('deleteProductId').value = id;
    document.getElementById('deleteProductName').innerText = name;
    modalDeleteInstance.show();
}

function executeDelete() {
    modalDeleteInstance.hide();
    showMessage("Producto eliminado correctamente", 'success');
    setTimeout(() => location.reload(), 1500);
}

// --- 3. LÓGICA POS / CARRITO ---
function addToCart(id, name, price, maxStock) {
    if(maxStock <= 0) {
        showMessage("❌ Producto agotado", 'error');
        return;
    }

    let existingItem = cart.find(item => item.id === id);
    if (existingItem) {
        if (existingItem.qty < maxStock) {
            existingItem.qty++;
        } else {
            showMessage("⚠️ Stock máximo alcanzado (" + maxStock + ")", 'warning');
            return;
        }
    } else {
        cart.push({ id: id, name: name, price: price, qty: 1, max: maxStock });
    }
    renderCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

function confirmClearCart() {
    if(cart.length === 0) return;
    modalClearCartInstance.show();
}

function executeClearCart() {
    cart = [];
    renderCart();
    modalClearCartInstance.hide();
}

function renderCart() {
    let tbody = document.getElementById('cartTableBody');
    tbody.innerHTML = '';
    let totalUsd = 0;
    let count = 0;

    if (cart.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="fas fa-cart-arrow-down fa-2x mb-2 opacity-50"></i><br>El carrito está vacío</td></tr>';
    } else {
        cart.forEach((item, index) => {
            let subtotal = Math.round((item.price * item.qty) * 100) / 100;
            totalUsd += subtotal;
            count += item.qty;

            tbody.innerHTML += `
                <tr>
                    <td class="align-middle text-center fw-bold">${item.qty}</td>
                    <td class="align-middle text-truncate" style="max-width: 120px;" title="${item.name}">${item.name}</td>
                    <td class="align-middle text-end text-success fw-bold">$${subtotal.toFixed(2)}</td>
                    <td class="align-middle text-end">
                        <button class="btn btn-outline-danger btn-sm p-1" onclick="removeFromCart(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    }

    let totalBs = totalUsd * bcvRate;
    document.getElementById('totalUsdDisplay').innerText = '$' + totalUsd.toFixed(2);
    document.getElementById('totalBsDisplay').innerText = 'Bs ' + totalBs.toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('itemCount').innerText = count;
}

// --- 4. PROCESO DE COBRO (CHECKOUT) ---
function initiateCheckout() {
    if (cart.length === 0) {
        showMessage("🛒 El carrito está vacío.", 'warning');
        return;
    }

    const methodSelect = document.getElementById('paymentMethod');
    const methodName = methodSelect.options[methodSelect.selectedIndex].text;
    const methodVal = methodSelect.value;
    
    // Validación adicional: si es crédito, debe haber cliente
    if (methodVal === 'credito') {
        const custId = document.getElementById('selectedCustomerId').value;
        if (!custId) {
            showMessage("Debe seleccionar un cliente para cobrar a crédito.", 'warning');
            return;
        }
    }

    let totalUsd = 0;
    let count = 0;
    cart.forEach(item => {
        totalUsd += (item.price * item.qty);
        count += item.qty;
    });
    let totalBs = totalUsd * bcvRate;

    document.getElementById('checkoutItems').innerText = count;
    document.getElementById('checkoutMethod').innerText = methodName;
    document.getElementById('checkoutTotalBs').innerText = 'Bs ' + totalBs.toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('checkoutTotalUsd').innerText = '$' + totalUsd.toFixed(2);

    resetCheckoutModal();
    modalCheckoutInstance.show();
}

function resetCheckoutModal() {
    document.getElementById('checkoutStateConfirm').style.display = 'block';
    document.getElementById('checkoutStateResult').style.display = 'none';
    document.getElementById('checkoutSuccess').style.display = 'none';
    document.getElementById('checkoutError').style.display = 'none';
    document.getElementById('checkoutSpinner').style.display = 'block';
    document.getElementById('btnCloseCheckout').style.display = 'block';
}

function executeSale() {
    document.getElementById('checkoutStateConfirm').style.display = 'none';
    document.getElementById('checkoutStateResult').style.display = 'block';
    document.getElementById('checkoutSpinner').style.display = 'block';
    
    const closeBtn = document.getElementById('btnCloseCheckout');
    if(closeBtn) closeBtn.style.display = 'none';

    let method = document.getElementById('paymentMethod').value;
    let customerId = document.getElementById('selectedCustomerId').value;
    let dueDate = document.getElementById('creditDueDate').value;

    $.ajax({
        url: 'process_sale.php',
        type: 'POST',
        data: { 
            cart: cart, 
            payment_method: method,
            customer_id: customerId,
            due_date: dueDate
        },
        success: function(response) {
            document.getElementById('checkoutSpinner').style.display = 'none';
            if(closeBtn) closeBtn.style.display = 'block'; 

            try {
                let res = (typeof response === 'object') ? response : JSON.parse(response);

                if (res.status === 'success') {
                    document.getElementById('checkoutSuccess').style.display = 'block';
                    document.getElementById('ticketId').innerText = res.sale_id || '####';
                    cart = []; 
                    renderCart(); 
                    
                    // Limpiar campos de crédito por si acaso
                    $('#selectedCustomerId').val('');
                    $('#selectedCustomerDisplay').val('');
                    $('#creditDueDate').val('');
                    
                } else {
                    throw new Error(res.message || 'Error desconocido del servidor.');
                }
            } catch (e) {
                document.getElementById('checkoutError').style.display = 'block';
                document.getElementById('checkoutErrorMessage').innerText = "Error inesperado: " + (e.message || "El servidor devolvió datos inválidos.");
            }
        },
        error: function(xhr, status, error) {
            document.getElementById('checkoutSpinner').style.display = 'none';
            if(closeBtn) closeBtn.style.display = 'block';
            document.getElementById('checkoutError').style.display = 'block';
            document.getElementById('checkoutErrorMessage').innerText = "Error de conexión (" + xhr.status + "): " + error;
        }
    });
}

// --- BUSCADOR AJAX REAL ESTILO DATATABLES ---
let searchTimeout;
const searchInput = document.getElementById('searchInput');
const productsGrid = document.getElementById('productsGrid');

searchInput.addEventListener('input', function(e) {
    const term = e.target.value.trim();
    
    productsGrid.style.opacity = '0.5';

    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        fetchProducts(term);
    }, 300);
});

function fetchProducts(term) {
    fetch(`search_products.php?term=${encodeURIComponent(term)}`)
        .then(response => response.json())
        .then(res => {
            productsGrid.style.opacity = '1';
            
            if (res.status === 'success') {
                renderProductsGrid(res.data);
            } else {
                showMessage("Error al buscar productos", "error");
            }
        })
        .catch(error => {
            productsGrid.style.opacity = '1';
            console.error("Error en la búsqueda:", error);
        });
}

function renderProductsGrid(products) {
    productsGrid.innerHTML = ''; 

    if (products.length === 0) {
        productsGrid.innerHTML = `
            <div class="col-12 text-center py-5 text-muted">
                <i class="fas fa-box-open fa-3x mb-3 opacity-50"></i><br>
                <h4>No se encontraron productos</h4>
                <p>Intenta con otro término de búsqueda.</p>
            </div>`;
        return;
    }

    products.forEach(p => {
        const price_usd = parseFloat(p.price_base_usd) * (1 + (parseFloat(p.profit_margin) / 100));
        const price_bs = price_usd * bcvRate;
        const is_stock = p.stock > 0;
        const img_url = p.image ? `uploads/${escapeHtml(p.image)}` : null;
        const desc = p.description ? escapeHtml(p.description) : 'Sin descripción';
        const sku = p.sku ? escapeHtml(p.sku) : 'N/A';
        const name = escapeHtml(p.name);
        
        const cardHtml = `
        <div class="col-6 col-md-4 col-xl-3 product-item" data-name="${name.toLowerCase()}">
            <div class="card h-100 shadow-sm border" style="cursor: pointer; transition: transform 0.2s;">
                
                <div onclick='addToCart(${p.id}, "${name.replace(/'/g, "\\'")}", ${price_usd.toFixed(2)}, ${p.stock})' class="d-flex flex-column h-100">
                    <div class="text-center bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center" style="height: 120px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                        ${img_url 
                            ? `<img src="${img_url}" class="img-fluid" style="max-height: 100%; object-fit: contain;" alt="${name}">`
                            : `<i class="fas fa-box-open fa-3x text-secondary opacity-50"></i>`
                        }
                    </div>

                    <div class="card-body p-2 d-flex flex-column text-center">
                        <h6 class="card-title text-truncate fw-bold mb-1 w-100" title="${name}">
                            ${name}
                        </h6>
                        <small class="text-muted text-truncate w-100 mb-2">${desc}</small>
                        
                        <div class="mt-auto">
                            <div class="text-success fw-bold fs-5">$${price_usd.toFixed(2)}</div>
                            <div class="text-muted small mb-2">Bs ${price_bs.toFixed(2)}</div>
                            
                            ${is_stock 
                                ? `<span class="badge text-bg-info rounded-pill">Stock: ${p.stock}</span>`
                                : `<span class="badge text-bg-danger rounded-pill">Agotado</span>`
                            }
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
        
        productsGrid.insertAdjacentHTML('beforeend', cardHtml);
    });
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
         .toString()
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}