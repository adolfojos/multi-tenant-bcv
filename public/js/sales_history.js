    // Búsqueda en la tabla (Ya lo tenías, optimizado)
document.getElementById('tableSearch').addEventListener('keyup', function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll('#historyBody tr');
    rows.forEach(row => {
        // Ignorar la fila de "No hay ventas"
        if(row.cells.length > 1) {
            row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
        }
    });
});

// Cargar detalles en el modalView
function loadSaleDetails(id) {
    const modalContent = document.getElementById('modalViewContent');
    const modalTitle = document.getElementById('modalTicketNumber');
    
    modalTitle.textContent = `#${id}`;
    modalContent.innerHTML = `<div class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando detalles...
                              </div>`;

    // Reemplaza 'get_sale_details.php' con la ruta real de tu endpoint
    fetch(`../controllers/get_sale_details.php?id=${id}`)
        .then(response => response.text()) // o .json() si devuelves JSON y construyes el HTML aquí
        .then(data => {
            modalContent.innerHTML = data;
        })
        .catch(error => {
            modalContent.innerHTML = `<div class="alert alert-danger">Error al cargar los detalles de la venta.</div>`;
            console.error('Error:', error);
        });
}

// Imprimir Ticket
function printTicket(id) {
    // Abre el ticket en una nueva pestaña y opcionalmente puede disparar print() desde allá
    window.open(`ticket.php?id=${id}`, '_blank');
}

// Anular Venta
function cancelSale(id) {
    if (confirm(`¿Estás completamente seguro de que deseas ANULAR la venta #${id}? Esta acción no se puede deshacer.`)) {
        // Reemplaza con la ruta de tu controlador de anulación
        fetch(`../controllers/cancel_sale.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Venta anulada con éxito.');
                location.reload(); // Recargar para reflejar cambios
            } else {
                alert('Error al anular: ' + (data.message || 'Error desconocido'));
            }
        })
        .catch(error => console.error('Error:', error));
    }
}

// Exportar Tabla a Excel (CSV)
document.getElementById('btnExportExcel').addEventListener('click', function() {
    let table = document.querySelector(".table");
    let rows = table.querySelectorAll("tr");
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        
        // Evitamos exportar la última columna (Acciones)
        let colsLength = i === 0 ? cols.length - 1 : cols.length; 
        if(cols.length === 1) continue; // Saltar fila de "sin registros"

        for (let j = 0; j < colsLength; j++) {
            // Limpiar saltos de línea y espacios extras
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
            row.push(`"${data}"`);
        }
        csv.push(row.join(","));
    }

    let csvFile = new Blob(["\uFEFF" + csv.join("\n")], {type: "text/csv;charset=utf-8;"});
    let downloadLink = document.createElement("a");
    downloadLink.download = `historial_ventas_${new Date().toISOString().split('T')[0]}.csv`;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
});