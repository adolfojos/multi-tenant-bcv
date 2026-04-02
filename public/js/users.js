// Archivo: ./public/js/users.js
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
    
    // Feedback visual en el botón
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

    fetch('actions/actions_user.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.status) {
            // Mensaje de éxito con SweetAlert2
            Swal.fire({
                title: "¡Logrado!",
                text: res.message || "Operación realizada con éxito",
                icon: "success",
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload(); // Recargamos después de que el usuario vea el mensaje
            });
        } else {
            // Mensaje de error con SweetAlert2
            Swal.fire({
                title: "Error",
                text: res.message || "Hubo un problema al guardar",
                icon: "error",
                confirmButtonColor: "#dc3545"
            });
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire("Error crítico", "No se pudo conectar con el servidor", "error");
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

    // Función para Eliminar con SweetAlert2
    function deleteUser(id, username) {
      Swal.fire({
        title: "¿Eliminar Usuario?",
        html: `Estás a punto de eliminar a <strong>${username}</strong>.<br>Esta acción no se puede deshacer.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        cancelButtonColor: "#232425",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append("action", "delete");
          formData.append("id", id);

          fetch("actions/actions_user.php", {
            method: "POST",
            body: formData,
          })
            .then((response) => response.json())
            .then((res) => {
              if (res.status) {
                Swal.fire("¡Eliminado!", res.message, "success").then(() =>
                  location.reload(),
                );
              } else {
                Swal.fire("Error", res.message, "error");
              }
            })
            .catch((error) => {
              console.error("Error:", error);
              Swal.fire("Error", "Problema al intentar eliminar.", "error");
            });
        }
      });
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
