document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels, // Usamos la variable global
            datasets: [{
                label: 'Ventas USD',
                data: chartValues, // Usamos la variable global
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 3,
                pointBackgroundColor: '#0d6efd',
                pointRadius: 4,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: '#1e2b37',
                    padding: 12
                }
            },
            scales: {
                y: { 
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { 
                        color: '#adb5bd', 
                        callback: v => '$' + v 
                    }
                },
                x: { 
                    grid: { display: false },
                    ticks: { color: '#adb5bd' }
                }
            }
        }
    });
});