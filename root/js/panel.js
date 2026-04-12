document.addEventListener("DOMContentLoaded", function () {
  // Declarar las instancias de los nuevos modales
  const modalViewTenant = new bootstrap.Modal(
    document.getElementById("modalViewTenant"),
  );
  const modalEditTenant = new bootstrap.Modal(
    document.getElementById("modalEditTenant"),
  );

  // Habilitar el envío del formulario de edición
  handleFormSubmit("formEditTenant", modalEditTenant);

  // --- FUNCION: VER DETALLES ---
  window.viewTenant = function (id) {
    const formData = new FormData();
    formData.append("action", "get_tenant");
    formData.append("id", id);

    fetch("actions.php", { method: "POST", body: formData })
      .then((response) => response.json())
      .then((res) => {
        if (res.status) {
          document.getElementById("view_id").innerText = res.data.id;
          document.getElementById("view_name").innerText =
            res.data.business_name;
          document.getElementById("view_rif").innerText = res.data.rif || "N/A";
          document.getElementById("view_admin").innerText =
            res.data.admin_user || "Sin asignar";
          document.getElementById("view_license").innerText =
            res.data.license_key;
          document.getElementById("view_created").innerText =
            res.data.created_at || "N/A";
          modalViewTenant.show();
        } else {
          Swal.fire("Error", res.message, "error");
        }
      })
      .catch((err) => console.error(err));
  };

  // --- FUNCION: PREPARAR EDICIÓN ---
  window.editTenant = function (id) {
    const formData = new FormData();
    formData.append("action", "get_tenant");
    formData.append("id", id);

    fetch("actions.php", { method: "POST", body: formData })
      .then((response) => response.json())
      .then((res) => {
        if (res.status) {
          document.getElementById("edit_id").value = res.data.id;
          document.getElementById("edit_name").value = res.data.business_name;
          document.getElementById("edit_rif").value = res.data.rif;
          document.querySelector('input[name="new_password"]').value = ""; // Limpiar campo contraseña
          modalEditTenant.show();
        } else {
          Swal.fire("Error", res.message, "error");
        }
      })
      .catch((err) => console.error(err));
  };

  // --- FUNCION: ELIMINAR DEFINITIVAMENTE ---
  window.deleteTenant = function (id, name) {
    Swal.fire({
      title: "¿Estás completamente seguro?",
      html: `Estás a punto de eliminar <strong>${name}</strong> de forma permanente.<br><br><span class="text-danger">¡Esta acción borrará usuarios, productos y ventas de esta tienda y NO se puede deshacer!</span>`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#dc3545",
      cancelButtonColor: "#6c757d",
      confirmButtonText:
        '<i class="fas fa-trash-alt"></i> Sí, eliminar definitivamente',
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (result.isConfirmed) {
        // Doble confirmación de seguridad
        Swal.fire({
          title: "Última advertencia",
          text: 'Escribe la palabra "ELIMINAR" para confirmar:',
          input: "text",
          icon: "error",
          showCancelButton: true,
          confirmButtonText: "Borrar",
          cancelButtonText: "Cancelar",
          preConfirm: (text) => {
            if (text !== "ELIMINAR") {
              Swal.showValidationMessage(
                'Debes escribir "ELIMINAR" (en mayúsculas) para confirmar',
              );
            }
          },
        }).then((secondResult) => {
          if (secondResult.isConfirmed) {
            const formData = new FormData();
            formData.append("action", "delete_tenant");
            formData.append("id", id);

            fetch("actions.php", {
              method: "POST",
              body: formData,
            })
              .then((response) => response.json())
              .then((res) => {
                if (res.status) {
                  Swal.fire({
                    title: "¡Eliminado!",
                    text: res.message,
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false,
                  }).then(() => location.reload());
                } else {
                  Swal.fire("Error", res.message, "error");
                }
              })
              .catch((err) => console.error(err));
          }
        });
      }
    });
  };

  // Inicializar Scrollbars de AdminLTE
  if (typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== "undefined") {
    OverlayScrollbarsGlobal.OverlayScrollbars(
      document.querySelector(".sidebar-wrapper"),
      {
        scrollbars: {
          theme: "os-theme-light",
          autoHide: "leave",
          clickScroll: true,
        },
      },
    );
  }

  // Instancias de modales de Bootstrap
  const modalNewTenant = new bootstrap.Modal(
    document.getElementById("modalNewTenant"),
  );
  const modalBCV = new bootstrap.Modal(document.getElementById("modalBCV"));
  const modalRenew = new bootstrap.Modal(document.getElementById("modalRenew"));

  // --- Función genérica para enviar formularios vía AJAX ---
  function handleFormSubmit(formId, modalInstance) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);
      const btnSubmit = this.querySelector('button[type="submit"]');
      const originalText = btnSubmit.innerHTML;

      // Estado Loading
      btnSubmit.disabled = true;
      btnSubmit.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';

      fetch("actions.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((res) => {
          if (res.status) {
            modalInstance.hide();
            Swal.fire({
              title: "¡Éxito!",
              text: res.message,
              icon: "success",
              confirmButtonColor: "#198754",
              timer: 2000,
              showConfirmButton: false,
            }).then(() => location.reload());
          } else {
            Swal.fire("Error", res.message, "error");
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = originalText;
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          Swal.fire(
            "Error de conexión",
            "No se pudo comunicar con el servidor.",
            "error",
          );
          btnSubmit.disabled = false;
          btnSubmit.innerHTML = originalText;
        });
    });
  }

  // Aplicar lógica a los formularios
  handleFormSubmit("formNewTenant", modalNewTenant);
  handleFormSubmit("formBCV", modalBCV);
  handleFormSubmit("formRenew", modalRenew);

  // --- Funciones Globales para botones de la tabla ---

  // Abrir Modal de Renovación
  window.openRenewModal = function (id, name) {
    document.getElementById("renew_id").value = id;
    document.getElementById("renew_name").innerText = name;
    modalRenew.show();
  };

  // Cambiar estado (Activar / Suspender) con Confirmación
  window.toggleStatus = function (id, newStatus, name) {
    const isSuspending = newStatus === "suspended";
    const actionText = isSuspending ? "suspender" : "reactivar";
    const confirmColor = isSuspending ? "#dc3545" : "#198754";
    const iconType = isSuspending ? "warning" : "question";

    Swal.fire({
      title: `¿${actionText.charAt(0).toUpperCase() + actionText.slice(1)} tienda?`,
      html: `Estás a punto de <strong>${actionText}</strong> el acceso a <strong>${name}</strong>.`,
      icon: iconType,
      showCancelButton: true,
      confirmButtonColor: confirmColor,
      cancelButtonColor: "#6c757d",
      confirmButtonText: `Sí, ${actionText}`,
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (result.isConfirmed) {
        const formData = new FormData();
        formData.append("action", "toggle_status");
        formData.append("id", id);
        formData.append("status", newStatus);

        fetch("actions.php", {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((res) => {
            if (res.status) {
              Swal.fire({
                title: "¡Hecho!",
                text: res.message,
                icon: "success",
                timer: 1500,
                showConfirmButton: false,
              }).then(() => location.reload());
            } else {
              Swal.fire("Error", res.message, "error");
            }
          })
          .catch((error) => {
            console.error("Error:", error);
            Swal.fire(
              "Error",
              "Problema al comunicarse con el servidor.",
              "error",
            );
          });
      }
    });
  };
});
