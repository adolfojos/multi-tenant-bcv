    document.addEventListener("DOMContentLoaded", function() {
        // Inicializar Modales
        const modalCatInstance = new bootstrap.Modal(document.getElementById('modalCat'));
        const modalDeleteInstance = new bootstrap.Modal(document.getElementById('modalDelete'));
        
        // Exponer funciones globalmente
        window.openModal = function(data = null) {
            document.getElementById('formCat').reset();
            const modalTitle = document.getElementById('modalTitle');
            
            if(data) {
                modalTitle.innerHTML = '<i class="fas fa-edit me-2"></i> Editar Categoría';
                document.getElementById('action').value = 'update';
                document.getElementById('catId').value = data.id;
                document.getElementById('catName').value = data.name;
            } else {
                modalTitle.innerHTML = '<i class="fas fa-plus-circle me-2"></i> Nueva Categoría';
                document.getElementById('action').value = 'create';
                document.getElementById('catId').value = '';
            }
            modalCatInstance.show();
        };

        window.confirmDelete = function(id, name) {
            document.getElementById('deleteCatId').value = id;
            document.getElementById('deleteCatName').innerText = name;
            modalDeleteInstance.show();
        };

        window.executeDelete = function() {
            const id = document.getElementById('deleteCatId').value;
            const btnDelete = document.querySelector('#modalDelete .btn-danger');
            const originalText = btnDelete.innerHTML;
            
            btnDelete.disabled = true;
            btnDelete.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Eliminando...';

            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);

            fetch('actions_category.php', { 
                method: 'POST', 
                body: fd 
            })
            .then(() => {
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                btnDelete.disabled = false;
                btnDelete.innerHTML = originalText;
                alert("Ocurrió un error al intentar eliminar la categoría.");
            });
        };

        // Submit del Formulario
        document.getElementById('formCat').onsubmit = function(e) {
            e.preventDefault();
            
            const btnSubmit = this.querySelector('button[type="submit"]');
            const originalText = btnSubmit.innerHTML;
            
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

            fetch('actions_category.php', {
                method: 'POST',
                body: new FormData(this)
            }).then(() => {
                location.reload();
            }).catch(error => {
                console.error('Error:', error);
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalText;
                alert("Ocurrió un error al guardar la categoría.");
            });
        };
    });
