document.addEventListener("DOMContentLoaded", function() {
    // 1. Inicializar Scrollbars de AdminLTE
    if (typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== "undefined") {
        OverlayScrollbarsGlobal.OverlayScrollbars(document.querySelector(".sidebar-wrapper"), {
            scrollbars: { theme: "os-theme-light", autoHide: "leave", clickScroll: true }
        });
    }

    // 2. Configurar y renderizar el gráfico de ApexCharts
    const chartLabels = window.CHART_LABELS || [];
    const chartSeries = window.CHART_SERIES || [];

    // Formatear las etiquetas de mes (Ej: "2026-04" -> "Abril 2026")
    const formattedLabels = chartLabels.map(label => {
        const [year, month] = label.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleString('es-ES', { month: 'short', year: 'numeric' }).replace(/^\w/, c => c.toUpperCase());
    });

    const options = {
        series: [{
            name: 'Nuevas Tiendas',
            data: chartSeries
        }],
        chart: {
            height: 320,
            type: 'area',
            toolbar: { show: false },
            fontFamily: 'inherit',
            background: 'transparent'
        },
        colors: ['#198754'], // Verde Success
        dataLabels: {
            enabled: true,
            style: { colors: ['#fff'] },
            background: { enabled: true, foreColor: '#198754', borderRadius: 4 }
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.05,
                stops: [0, 90, 100]
            }
        },
        xaxis: {
            categories: formattedLabels,
            labels: { style: { colors: '#adb5bd' } },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: { style: { colors: '#adb5bd' } },
            min: 0,
            forceNiceScale: true
        },
        grid: {
            borderColor: 'rgba(255, 255, 255, 0.05)',
            strokeDashArray: 4,
        },
        theme: { mode: 'dark' },
        tooltip: {
            theme: 'dark',
            y: { formatter: function (val) { return val + " Tiendas" } }
        }
    };

    const chart = new ApexCharts(document.querySelector("#growthChart"), options);
    chart.render();
});