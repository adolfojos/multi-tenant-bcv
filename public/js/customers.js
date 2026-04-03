let modalCustomerInstance;
let modalViewInstance;
document.addEventListener("DOMContentLoaded", function() {
    // Inicializar modal
    modalCustomerInstance = new bootstrap.Modal(document.getElementById('modalCustomerForm'));
    modalViewInstance = new bootstrap.Modal(document.getElementById("modalView"));
    
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

        fetch('actions/actions_customer.php', {
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

            fetch('actions/actions_customer.php', {
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

function viewCustomer(c) {
    if (!modalViewInstance) return alert('El modal aún no se ha inicializado.');

    // Validar si los campos están vacíos para mostrar un texto por defecto
    const documentText = c.document ? c.document : '<span class="text-muted fst-italic">No registrado</span>';
    const phoneText = c.phone ? c.phone : '<span class="text-muted fst-italic">No registrado</span>';

    const content = `
        <div class="text-center mb-4">
            <i class="fas fa-user-circle text-secondary" style="font-size: 4rem;"></i>
        </div>
        <ul class="list-group list-group-flush mb-3">
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Nombre:</span>
                <span class="fw-bold text-primary">${c.name}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Cédula / RIF:</span>
                <span class="fw-bold">${documentText}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                <span class="text-muted fw-bold">Teléfono:</span>
                <span class="fw-bold">${phoneText}</span>
            </li>
        </ul>
    `;
    
    document.getElementById('viewContent').innerHTML = content;
    modalViewInstance.show();
}