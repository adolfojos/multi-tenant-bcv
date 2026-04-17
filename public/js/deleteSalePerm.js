
// Forzamos la función al objeto global window para que el onclick la encuentre siempre
window.deleteSalePerm = function(id) {
    // 1. Guardar el ID en una variable global
    window.currentSaleIdToDelete = id;
    
    // 2. Actualizar el texto del modal si existe el elemento
    const span = document.getElementById('spanTicketBorrar');
    if (span) span.textContent = '#' + id;
    
    // 3. Mostrar el modal de Bootstrap
    const modalEl = document.getElementById('modalConfirmBorrar');
    if (modalEl) {
        const modalBorrar = new bootstrap.Modal(modalEl);
        modalBorrar.show();
    } else {
        // Si por alguna razón el modal no carga, usamos un confirm estándar de respaldo
        if (confirm('¿Estás seguro de borrar definitivamente la venta #' + id + '? Esta acción no se puede deshacer.')) {
            ejecutarBorrado(id);
        }
    }
};

// Función separada para la petición AJAX
function ejecutarBorrado(id) {
    const btn = document.getElementById('btnConfirmarBorrar');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }

    const formData = new FormData();
    formData.append('id', id);

    fetch('controllers/delete_sale.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = 'Sí, Borrar Venta';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error crítico al procesar la solicitud.');
    });
}

// Asignar el evento al botón del modal una vez que el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    const btnConfirm = document.getElementById('btnConfirmarBorrar');
    if (btnConfirm) {
        btnConfirm.onclick = function() {
            if (window.currentSaleIdToDelete) {
                ejecutarBorrado(window.currentSaleIdToDelete);
            }
        };
    }
});
