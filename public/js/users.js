
    const modalUser = new bootstrap.Modal(document.getElementById('modalUser'));

    function resetForm() {
        document.getElementById('userAction').value = 'create';
        document.getElementById('userId').value = '';
        document.getElementById('username').value = '';
        document.getElementById('full_name').value = '';
        document.getElementById('password').value = '';
        document.getElementById('password').required = true;
        document.getElementById('pwHelp').style.display = 'none';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i> Nuevo Usuario';
    }

    function editUser(u) {
        document.getElementById('userAction').value = 'update';
        document.getElementById('userId').value = u.id;
        document.getElementById('username').value = u.username;
        document.getElementById('full_name').value = u.full_name || '';
        document.getElementById('role').value = u.role;
        
        document.getElementById('password').value = '';
        document.getElementById('password').required = false;
        document.getElementById('pwHelp').style.display = 'block';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit text-warning me-2"></i> Editar Usuario';
        
        modalUser.show();
    }

    function saveUser(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        fetch('actions/actions_user.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if(res.status) {
                location.reload();
            } else {
                alert("Error: " + res.message);
                btn.disabled = false;
                btn.innerHTML = 'Guardar Usuario';
            }
        })
        .catch(err => {
            console.error(err);
            alert("Error de conexión");
            btn.disabled = false;
            btn.innerHTML = 'Guardar Usuario';
        });
    }

    function deleteUser(id) {
        if(confirm('¿Estás seguro de eliminar este usuario? Perderá el acceso al sistema inmediatamente.')) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            fetch('actions/actions_user.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if(res.status) location.reload();
                else alert(res.message);
            });
        }
    }

    // Buscador en tiempo real
    document.getElementById('userSearch').addEventListener('keyup', function() {
        let value = this.value.toLowerCase();
        let rows = document.querySelectorAll('#userTableBody tr');
        rows.forEach(row => {
            if(!row.querySelector('td[colspan]')) {
                row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
            }
        });
    });
