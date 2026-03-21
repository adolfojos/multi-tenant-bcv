    document.addEventListener("DOMContentLoaded", function() {
        // Obtenemos los datos inyectados desde PHP
        const chartSales = window.APP_JS_CHARTSALE;
        const chartDates = window.APP_JS_CHARTDATES;

        const sales_chart_options = {
            series: [{
                name: 'Ventas (USD)',
                data: chartSales
            }],
            chart: {
                height: 300,
                type: 'area',
                toolbar: { show: false },
                fontFamily: 'inherit',
                background: 'transparent'
            },
            colors: ['#0d6efd'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.3,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            xaxis: {
                type: 'category',
                categories: chartDates,
                labels: { style: { colors: '#adb5bd' } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: { 
                    style: { colors: '#adb5bd' },
                    formatter: function(val) { return "$" + val.toFixed(2); }
                }
            },
            grid: {
                borderColor: 'rgba(255, 255, 255, 0.05)',
                strokeDashArray: 4,
            },
            tooltip: {
                theme: 'dark',
                y: { formatter: function (val) { return "$" + val.toFixed(2) } }
            }
        };

        const sales_chart = new ApexCharts(document.querySelector('#revenue-chart'), sales_chart_options);
        sales_chart.render();
    });