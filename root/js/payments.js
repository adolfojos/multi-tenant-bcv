document.addEventListener("DOMContentLoaded", function() {
    // 1. Inicializar Scrollbars de AdminLTE
    if (typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== "undefined") {
        OverlayScrollbarsGlobal.OverlayScrollbars(document.querySelector(".sidebar-wrapper"), {
            scrollbars: { theme: "os-theme-light", autoHide: "leave", clickScroll: true }
        });
    }

    // 2. Buscador en tiempo real
    const searchInput = document.getElementById('searchPayment');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#paymentsTable tbody tr');
            
            rows.forEach(row => {
                // Ignorar la fila de "Aún no hay pagos" si existe
                if (row.cells.length > 1) {
                    const text = row.innerText.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                }
            });
        });
    }

    // 3. Exportar a CSV (Excel)
    const btnExport = document.getElementById('btnExportCSV');
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            let csv = [];
            const rows = document.querySelectorAll("#paymentsTable tr");
            
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll("td, th");
                
                // Ignoramos la última columna (Acción/Botón de imprimir)
                let colsLength = cols.length - 1; 
                
                // Si es la fila de "No hay pagos", la saltamos
                if (cols.length === 1) continue;

                for (let j = 0; j < colsLength; j++) {
                    // Limpiar saltos de línea para que Excel lo lea bien
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
                    // Escapar comillas dobles
                    data = data.replace(/"/g, '""');
                    row.push(`"${data}"`);
                }
                csv.push(row.join(","));
            }

            // Descargar el archivo
            const csvFile = new Blob(["\uFEFF" + csv.join("\n")], {type: "text/csv;charset=utf-8;"});
            const downloadLink = document.createElement("a");
            downloadLink.download = `historial_pagos_multipos_${new Date().toISOString().split('T')[0]}.csv`;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        });
    }
});