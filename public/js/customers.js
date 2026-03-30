let modalCustomerInstance;

document.addEventListener("DOMContentLoaded", function() {
    // Inicializar modal
    modalCustomerInstance = new bootstrap.Modal(document.getElementById('modalCustomerForm'));

    // Inicializar DataTable
    if ($.fn.DataTable) {
        $('#customersTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            responsive: true,
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: 3 }] // Desactiva orden en columna acciones
        });
    }

    // Resetear formulario al abrir el modal para crear nuevo (clickeando el botón del header)
    document.querySelector('[data-bs-target="#modalCustomerForm"]').addEventListener('click', () => {
        document.getElementById('formCustomer').reset();
        document.getElementById('customerAction').value = 'create';
        document.getElementById('customerId').value = '';
        document.getElementById('modalCustomerTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i> Nuevo Cliente';
    });

    // Envío del formulario (Crear/Editar)
    document.getElementById('formCustomer').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btnSubmit = document.getElementById('btnSaveCustomer');
        const originalText = btnSubmit.innerText;
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

        const formData = new FormData(this);

        fetch('actions_customer.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if(res.status) {
                Swal.fire('¡Éxito!', res.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
                btnSubmit.disabled = false;
                btnSubmit.innerText = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Problema de conexión con el servidor.', 'error');
            btnSubmit.disabled = false;
            btnSubmit.innerText = originalText;
        });
    });
});

// Función para abrir modal en modo Edición
function openCustomerModal(data) {
    document.getElementById('customerAction').value = 'update';
    document.getElementById('customerId').value = data.id;
    document.getElementById('customerName').value = data.name;
    document.getElementById('customerDoc').value = data.document || '';
    document.getElementById('customerPhone').value = data.phone || '';
    
    document.getElementById('modalCustomerTitle').innerHTML = '<i class="fas fa-user-edit text-warning me-2"></i> Editar Cliente';
    
    modalCustomerInstance.show();
}

// Función para Eliminar con SweetAlert2
function deleteCustomer(id, name) {
    Swal.fire({
        title: '¿Eliminar cliente?',
        html: `Estás a punto de eliminar a <strong>${name}</strong>.<br>Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            fetch('actions_customer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(res => {
                if(res.status) {
                    Swal.fire('¡Eliminado!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Problema al intentar eliminar.', 'error');
            });
        }
    });
}