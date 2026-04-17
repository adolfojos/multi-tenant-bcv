document.addEventListener("DOMContentLoaded", function() {
    const modalPlan = new bootstrap.Modal(document.getElementById('modalPlan'));

    // 1. Abrir Modal para Crear
    window.openPlanModal = function() {
        document.getElementById('formPlan').reset();
        document.getElementById('plan_id').value = '';
        document.getElementById('modalPlanTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i> Crear Nuevo Plan';
        modalPlan.show();
    };

    // 2. Abrir Modal para Editar
    window.editPlan = function(id) {
        const formData = new FormData();
        formData.append('action', 'get_plan');
        formData.append('id', id);

        fetch('actions.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(res => {
            if (res.status) {
                document.getElementById('plan_id').value = res.data.id;
                document.getElementById('plan_name').value = res.data.name;
                document.getElementById('plan_price').value = res.data.price_usd;
                document.getElementById('plan_users').value = res.data.max_users;
                document.getElementById('plan_products').value = res.data.max_products;
                document.getElementById('plan_desc').value = res.data.description;
                
                document.getElementById('modalPlanTitle').innerHTML = '<i class="fas fa-edit me-2"></i> Editar Plan';
                modalPlan.show();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        });
    };

    // 3. Guardar Formulario
    document.getElementById('formPlan').addEventListener('submit', function(e) {
        e.preventDefault();
        const btnSubmit = this.querySelector('button[type="submit"]');
        const originalText = btnSubmit.innerHTML;

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

        fetch('actions.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(response => response.json())
        .then(res => {
            if (res.status) {
                modalPlan.hide();
                Swal.fire({ title: '¡Éxito!', text: res.message, icon: 'success', timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalText;
            }
        });
    });

    // 4. Eliminar Plan
    window.deletePlan = function(id, name) {
        Swal.fire({
            title: `¿Eliminar el plan ${name}?`,
            text: "No podrás eliminarlo si hay tiendas actualmente usando este plan.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'delete_plan');
                fd.append('id', id);

                fetch('actions.php', { method: 'POST', body: fd })
                .then(response => response.json())
                .then(res => {
                    if (res.status) {
                        Swal.fire('¡Eliminado!', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('No se pudo eliminar', res.message, 'error');
                    }
                });
            }
        });
    };
});