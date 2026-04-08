document.addEventListener("DOMContentLoaded", function() {
// Inicializar Modal de Categoría (Crear/Editar)
    const modalCatInstance = new bootstrap.Modal(document.getElementById('modalCat'));
    
    // Exponer funciones globalmente
    window.openModal = function(data = null) {
        document.getElementById('formCat').reset();
        const modalTitle = document.getElementById('modalTitle');
        
        if(data) {
            modalTitle.innerHTML = '<i class="fas fa-edit text-warning me-2"></i> Editar Categoría';
            document.getElementById('action').value = 'update';
            document.getElementById('catId').value = data.id;
            document.getElementById('catName').value = data.name;
            // Pobar el nuevo campo de descripción
            document.getElementById('catDesc').value = data.description || ''; 
        } else {
            modalTitle.innerHTML = '<i class="fas fa-plus-circle me-2"></i> Nueva Categoría';
            document.getElementById('action').value = 'create';
            document.getElementById('catId').value = '';
            document.getElementById('catDesc').value = '';
        }
        modalCatInstance.show();
    };

    // Reemplazamos el modal de Bootstrap por SweetAlert2 para eliminar
    window.confirmDelete = function(id, name) {
        Swal.fire({
            title: '¿Eliminar Categoría?',
            html: `Estás a punto de eliminar <strong>${name}</strong>.<br>Los productos asociados podrían quedar sin categoría. Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash me-1"></i> Sí, Eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id', id);

                fetch('actions/actions_category.php', { 
                    method: 'POST', 
                    body: fd 
                })
                .then(response => response.json())
                .then(res => {
                    if (res.status) {
                        Swal.fire({
                            title: '¡Eliminada!',
                            text: res.message,
                            icon: 'success',
                            confirmButtonColor: '#198754'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Ocurrió un error al intentar comunicar con el servidor.', 'error');
                });
            }
        });
    };

    // Submit del Formulario (Crear/Actualizar) con SweetAlert2
    document.getElementById('formCat').onsubmit = function(e) {
        e.preventDefault();
        
        const btnSubmit = this.querySelector('button[type="submit"]');
        const originalText = btnSubmit.innerHTML;
        
        // Estado de carga en el botón
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando...';

        fetch('actions/actions_category.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(response => response.json())
        .then(res => {
            if(res.status) {
                modalCatInstance.hide();
                Swal.fire({
                    title: '¡Éxito!',
                    text: res.message,
                    icon: 'success',
                    confirmButtonColor: '#198754'
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Ocurrió un error al intentar guardar la categoría.', 'error');
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = originalText;
        });
    };
});