<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MultiPOS | Gestión Unificada para tu Negocio</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="MTB App">
    <!-- Metadatos SEO -->
    <meta name="description" content="La solución integral para TPV, inventarios y gestión de mesas. Prueba MultiPOS gratis por 30 días.">
    <meta name="keywords" content="POS, Punto de Venta, Software de Ventas, Inventario, Gestión de Restaurantes">
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AdminLTE 4 (Bootstrap 5.3 Framework) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(reg => console.log('Service Worker registrado', reg))
            .catch(err => console.log('Error al registrar SW', err));
        });
    }
    </script>
    <!-- Estilos Personalizados -->
    <style>
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --secondary: #7C3AED;
            --text-main: #111827;
            --text-muted: #6B7280;
            --bg-light: #f8fafc;
        }

        body {
            font-family: 'Inter', sans-serif;
            scroll-behavior: smooth;
            color: var(--text-main);
        }

        /* Navbar - Glassmorphism */
        .navbar-landing {
            background: rgba(255, 255, 255, 0.90) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        /* Botones y Textos Gradient */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            padding: 12px 28px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
            color: white;
        }
        .text-gradient {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f3e8ff 100%);
            position: relative;
            overflow: hidden;
            padding: 80px 0;
        }
        .floating-mockup {
            animation: float 6s ease-in-out infinite;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border-radius: 12px;
            border: 4px solid white;
            background-color: #e2e8f0;
            width: 100%;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }

        /* Tarjetas Generales */
        .hover-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,0,0,0.05) !important;
            background: white;
        }
        .hover-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
            border-color: rgba(79, 70, 229, 0.2) !important;
        }
        .icon-box { transition: transform 0.3s ease; }
        .hover-card:hover .icon-box { transform: scale(1.1) rotate(5deg); }

        /* Pasos (How it works) */
        .step-number {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            margin: 0 auto 1rem auto;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
        }
        .step-connector {
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: dashed 2px #cbd5e1;
            z-index: -1;
        }
        @media (max-width: 767px) { .step-connector { display: none; } }

        /* Comparativa */
        .comparison-bad { background-color: var(--bg-light); border: 1px solid #e2e8f0; }
        .comparison-good {
            background: white;
            border: 2px solid transparent;
            background-clip: padding-box;
            position: relative;
            border-radius: 1rem;
        }
        .comparison-good::before {
            content: ''; position: absolute; top: -2px; right: -2px; bottom: -2px; left: -2px;
            z-index: -1; border-radius: 1.1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        /* Tags Módulos */
        .text-purple { color: var(--secondary); }
        .module-tag {
            font-weight: 500; font-size: 0.95rem; color: var(--text-muted);
            transition: all 0.3s ease; cursor: default;
        }
        .module-tag:hover {
            background-color: #f3e8ff !important; border-color: var(--secondary) !important; color: var(--secondary);
            transform: translateY(-3px) scale(1.02); box-shadow: 0 10px 15px -3px rgba(124, 58, 237, 0.1);
        }

        /* Testimonios */
        .testimonial-avatar { width: 60px; height: 60px; object-fit: cover; border-radius: 50%; }
        .stars { color: #fbbf24; }

        /* Precios */
        .pricing-card-pro { border: 2px solid var(--primary); transform: scale(1.05); z-index: 10; }
        @media (max-width: 991px) { .pricing-card-pro { transform: scale(1); margin-top: 20px; margin-bottom: 20px; } }
        .badge-pulse { animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(79, 70, 229, 0); }
            100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); }
        }

        /* FAQ Accordion */
        .accordion-button:not(.collapsed) {
            background-color: #f3e8ff; color: var(--primary); font-weight: 600; box-shadow: none;
        }
        .accordion-button:focus { border-color: var(--primary); box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25); }

        /* Final CTA */
        .final-cta {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; border-radius: 24px;
        }
    </style>
</head>
        <body class="layout-fixed">
        <nav class="navbar navbar-expand-lg navbar-light sticky-top navbar-landing py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <div class="bg-primary bg-gradient rounded p-2 me-2 d-flex align-items-center justify-content-center text-white shadow-sm">
                <i class="bi bi-box-seam-fill fs-5"></i>
            </div>
            <span class="fw-bold fs-4" style="letter-spacing: -0.5px;">MultiPOS</span>
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <i class="bi bi-list fs-1 text-primary"></i>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center fw-medium">
                <li class="nav-item"><a class="nav-link px-3" href="#features">Características</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#hardware">Equipos</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#solutions">Módulos</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#pricing">Precios</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#faq">FAQ</a></li>
                <li class="nav-item ms-lg-3 mt-3 mt-lg-0 mb-2 mb-lg-0">
                    <a class="btn btn-light border px-4 me-lg-2 w-100" href="public/login.php">Ingresar</a>
                </li>
                <li class="nav-item w-100 w-lg-auto">
                    <a class="btn btn-primary-custom w-100" href="public/registro.php">Probar Gratis</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
        <main class="container-fluid p-0">
            <section class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-75 py-5">
                <div class="col-lg-6 text-center text-lg-start mb-5 mb-lg-0 pe-lg-5">
                    <span class="badge rounded-pill bg-white text-primary border border-primary px-3 py-2 mb-4 shadow-sm fw-medium">
                        <i class="bi bi-stars me-1 text-warning"></i> La solución todo en uno para tu negocio
                    </span>
                    <h1 class="display-4 fw-bold mb-4" style="line-height: 1.2; letter-spacing: -1px;">
                        Más que un Punto de Venta, <br>
                        es el <span class="text-gradient">Centro de Control</span>
                    </h1>
                    <p class="lead text-muted mb-5 fs-5">Desde TPV y gestión de mesas hasta inventario y logística de pedidos. MultiPOS unifica todas tus operaciones en una plataforma ágil y moderna.</p>
                    
                    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center justify-content-lg-start">
                        <a href="registro.php" class="btn btn-primary-custom btn-lg px-4 d-flex align-items-center justify-content-center gap-2">
                            Empezar prueba de 30 días <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="#demo" class="btn btn-outline-secondary btn-lg px-4 d-flex align-items-center justify-content-center">
                            Ver demostración
                        </a>
                    </div>
                    <p class="small text-muted mt-3 mb-0"><i class="bi bi-shield-check text-success me-1"></i> Sin tarjeta de crédito requerida. Cancela cuando quieras.</p>
                </div>
                
                <div class="col-lg-6 position-relative">
                    <div class="floating-mockup">
                    <!-- REEMPLAZA ESTA URL CON LA CAPTURA DE TU SOFTWARE -->
                    <img src="assets/img/mi-dashboard.png" 
                         alt="Dashboard MultiPOS" 
                         class="mockup-image">
                </div>
                    <div class="position-absolute top-50 start-50 translate-middle w-100 h-100" style="background: radial-gradient(circle, rgba(124,58,237,0.1) 0%, rgba(255,255,255,0) 70%); z-index: -1;"></div>
                </div>
            </div>
        </div>
    </section>
            <section class="py-5 bg-white border-bottom">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold display-6">Empieza a vender en minutos</h2>
                <p class="text-muted fs-5">Olvídate de implementaciones de meses. MultiPOS está listo para usarse.</p>
            </div>
            
            <div class="row position-relative text-center">
                <!-- Linea conectora para escritorio -->
                <div class="step-connector"></div>
                
                <div class="col-md-4 mb-4 mb-md-0 position-relative">
                    <div class="step-number">1</div>
                    <div class="card border-0 bg-transparent">
                        <div class="card-body">
                            <div class="mb-3"><i class="bi bi-person-plus text-primary fs-1"></i></div>
                            <h5 class="fw-bold">Crea tu cuenta</h5>
                            <p class="text-muted small">Regístrate en menos de 2 minutos sin ingresar tarjetas ni contratos a largo plazo.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4 mb-md-0 position-relative">
                    <div class="step-number">2</div>
                    <div class="card border-0 bg-transparent">
                        <div class="card-body">
                            <div class="mb-3"><i class="bi bi-cloud-arrow-up text-primary fs-1"></i></div>
                            <h5 class="fw-bold">Sube tu inventario</h5>
                            <p class="text-muted small">Importa tus productos desde Excel o créalos rápidamente en nuestro panel intuitivo.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 position-relative">
                    <div class="step-number">3</div>
                    <div class="card border-0 bg-transparent">
                        <div class="card-body">
                            <div class="mb-3"><i class="bi bi-shop text-primary fs-1"></i></div>
                            <h5 class="fw-bold">Comienza a operar</h5>
                            <p class="text-muted small">Abre tu caja, atiende mesas o procesa pedidos de inmediato. Todo sincronizado.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
            <section id="features" class="py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5">
                <span class="text-primary fw-bold text-uppercase tracking-wider small">Por qué elegirnos</span>
                <h2 class="fw-bold display-5 mt-2 mb-3">Herramientas diseñadas para crecer</h2>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 hover-card">
                        <div class="icon-box rounded-circle d-flex align-items-center justify-content-center mb-4" style="width: 56px; height: 56px; background-color: #f3e8ff; color: #7c3aed;">
                            <i class="bi bi-grid-1x2-fill fs-4"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Operación Unificada</h5>
                        <p class="text-muted small mb-0">Gestiona ventas, mesas, estacionamiento y pedidos desde una sola plataforma intuitiva.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 hover-card">
                        <div class="icon-box rounded-circle d-flex align-items-center justify-content-center mb-4" style="width: 56px; height: 56px; background-color: #eff6ff; color: #4F46E5;">
                            <i class="bi bi-box-seam-fill fs-4"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Inventario Inteligente</h5>
                        <p class="text-muted small mb-0">Tu stock se actualiza automáticamente en tiempo real con cada venta o devolución.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 hover-card">
                        <div class="icon-box rounded-circle d-flex align-items-center justify-content-center mb-4" style="width: 56px; height: 56px; background-color: #fff1f2; color: #e11d48;">
                            <i class="bi bi-truck-front-fill fs-4"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Logística Simple</h5>
                        <p class="text-muted small mb-0">Genera links de seguimiento para mensajeros con info del pedido y confirmación.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 hover-card">
                        <div class="icon-box rounded-circle d-flex align-items-center justify-content-center mb-4" style="width: 56px; height: 56px; background-color: #f0fdf4; color: #16a34a;">
                            <i class="bi bi-bar-chart-fill fs-4"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Análisis Detallado</h5>
                        <p class="text-muted small mb-0">Toma decisiones con reportes detallados de ventas, métricas y rendimiento general.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
            <section class="py-5 bg-white border-bottom border-top">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold display-5">Del Caos a la Claridad</h2>
                <p class="text-muted fs-5">Deja atrás las herramientas dispersas y toma el control real.</p>
            </div>
            <!-- (Contenido de comparativa igual al anterior...) -->
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <div class="p-4 p-lg-5 rounded-4 h-100 comparison-bad">
                        <h4 class="text-center fw-bold text-muted mb-4"><i class="bi bi-x-circle me-2"></i> El Modo Antiguo</h4>
                        <div class="bg-white p-3 rounded-3 border-start border-warning border-4 mb-3 shadow-sm opacity-75 grayscale">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-bold">Ventas en Cuaderno</span><i class="bi bi-journal-text text-muted"></i>
                            </div>
                            <div class="fs-4 fw-bold mt-1">$1.2M</div>
                            <div class="small text-muted">Cierre Manual (2 horas)</div>
                        </div>
                        <div class="bg-white p-3 rounded-3 border-start border-danger border-4 mb-3 shadow-sm opacity-75">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-bold">Inventario en Excel</span><i class="bi bi-file-earmark-spreadsheet text-muted"></i>
                            </div>
                            <div class="fs-4 fw-bold mt-1">1,280</div>
                            <div class="small text-danger"><i class="bi bi-exclamation-triangle"></i> Desactualizado</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 d-none d-lg-flex justify-content-center">
                    <div class="bg-white rounded-circle shadow-sm d-flex justify-content-center align-items-center z-1" style="width: 60px; height: 60px;">
                        <i class="bi bi-arrow-right fs-3 text-primary"></i>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="p-4 p-lg-5 comparison-good shadow-lg bg-white h-100">
                        <h4 class="text-center fw-bold mb-4 text-gradient"><i class="bi bi-check-circle-fill me-2 text-success"></i> El Futuro con MultiPOS</h4>
                        <div class="card border border-light bg-light mb-4 rounded-4 shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="fw-bold mb-0">Cierre Diario Automático</h6>
                                        <small class="text-muted">Actualizado hace 1 min</small>
                                    </div>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-1">Online</span>
                                </div>
                                <div class="bg-white p-3 rounded-3 shadow-sm mb-3 d-flex justify-content-between align-items-center border border-light">
                                    <span class="fw-bold text-muted"><i class="bi bi-wallet2 text-primary me-2"></i> Ventas Totales</span>
                                    <span class="fw-bold fs-5 text-dark">$1.250.000</span>
                                </div>
                            </div>
                        </div>
                        <ul class="list-unstyled small fw-medium text-dark">
                            <li class="mb-2"><i class="bi bi-check-circle-fill  "></i> Sincronizado en cualquier dispositivo.</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-primary me-2"></i> Stock automatizado y cierres precisos.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
            <section id="hardware" class="py-5 bg-light">
        <div class="container py-5 text-center">
            <h2 class="fw-bold mb-4">Compatible con tus equipos actuales</h2>
            <p class="text-muted fs-5 mb-5 mx-auto" style="max-width: 600px;">No necesitas invertir en equipos costosos. MultiPOS funciona desde el navegador y se conecta con el hardware estándar del mercado.</p>
            
            <div class="row justify-content-center g-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 bg-white shadow-sm p-4 h-100 rounded-4">
                        <i class="bi bi-pc-display fs-1 text-primary mb-3"></i>
                        <h6 class="fw-bold">PC, Mac y Tablets</h6>
                        <span class="small text-muted">Cualquier dispositivo web</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 bg-white shadow-sm p-4 h-100 rounded-4">
                        <i class="bi bi-printer fs-1 text-primary mb-3"></i>
                        <h6 class="fw-bold">Impresoras Térmicas</h6>
                        <span class="small text-muted">USB, Bluetooth o Red</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 bg-white shadow-sm p-4 h-100 rounded-4">
                        <i class="bi bi-upc-scan fs-1 text-primary mb-3"></i>
                        <h6 class="fw-bold">Lectores de Barras</h6>
                        <span class="small text-muted">Inalámbricos y USB</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 bg-white shadow-sm p-4 h-100 rounded-4">
                        <i class="bi bi-safe fs-1 text-primary mb-3"></i>
                        <h6 class="fw-bold">Cajones de Dinero</h6>
                        <span class="small text-muted">Conexión vía impresora</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
            <section id="solutions" class="py-5 bg-white border-bottom">
        <div class="container py-5 text-center">
            <h2 class="fw-bold display-5 mb-3">Un Sistema Modular</h2>
            <p class="text-muted fs-5 mb-5 mx-auto" style="max-width: 700px;">Activa solo las herramientas que tu negocio necesita en este momento.</p>

            <div class="d-flex flex-wrap justify-content-center gap-3 gap-md-4 mx-auto mt-4" style="max-width: 1000px;">
                <!-- Etiquetas de módulos -->
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-shop text-purple me-2"></i> TPV para Tiendas</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-egg-fried text-purple me-2"></i> TPV Restaurantes</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-p-square text-purple me-2"></i> Parqueaderos</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-truck text-purple me-2"></i> Entregas / Delivery</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-box-seam text-purple me-2"></i> Control de Stock</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-people text-purple me-2"></i> CRM Clientes</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-bar-chart-line text-purple me-2"></i> Informes Avanzados</div>
                <div class="module-tag bg-white border rounded-pill px-4 py-2 shadow-sm"><i class="bi bi-file-earmark-text text-purple me-2"></i> Cotizaciones</div>
            </div>
        </div>
    </section>
            <section class="py-5" style="background-color: #f3e8ff;">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Lo que dicen nuestros clientes</h2>
                <p class="text-muted">Únete a cientos de negocios que ya optimizaron sus procesos.</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="stars mb-3">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p class="fst-italic text-muted">"Antes perdía horas cruzando ventas e inventario en Excel. Con MultiPOS, cierro caja en 2 minutos y sé exactamente qué me falta comprar."</p>
                        <div class="d-flex align-items-center mt-auto pt-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width:50px; height:50px;">CM</div>
                            <div>
                                <h6 class="fw-bold mb-0">Carlos Mendoza</h6>
                                <span class="small text-muted">Dueño de Minimarket</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="stars mb-3">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p class="fst-italic text-muted">"El módulo de restaurantes es increíble. Los meseros toman el pedido en su celular y sale directo en la cocina. Nos salvó los fines de semana."</p>
                        <div class="d-flex align-items-center mt-auto pt-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width:50px; height:50px;">AR</div>
                            <div>
                                <h6 class="fw-bold mb-0">Ana Rodríguez</h6>
                                <span class="small text-muted">Gerente de Pizzería</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="stars mb-3">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                        </div>
                        <p class="fst-italic text-muted">"Tengo 3 tiendas de ropa y ahora puedo ver las ventas de las 3 en tiempo real desde mi casa. La mejor inversión para mi negocio."</p>
                        <div class="d-flex align-items-center mt-auto pt-3">
                            <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width:50px; height:50px;">JV</div>
                            <div>
                                <h6 class="fw-bold mb-0">Jorge Vargas</h6>
                                <span class="small text-muted">Boutiques JV</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
            <section id="pricing" class="py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold display-5">Planes transparentes</h2>
                <p class="text-muted fs-5">Empieza gratis, escala cuando estés listo.</p>
            </div>

            <div class="row g-4 justify-content-center align-items-stretch">
                <!-- PLAN BÁSICO -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 rounded-4 p-4 hover-card">
                        <div class="card-body d-flex flex-column">
                            <h4 class="fw-bold">Básico</h4>
                            <p class="text-muted small">Ideal para emprendedores y tiendas pequeñas.</p>
                            <div class="my-4">
                                <span class="display-4 fw-bold text-dark">$0</span><span class="text-muted fw-medium">/mes</span>
                            </div>
                            <ul class="list-unstyled mb-5 flex-grow-1">
                                <li class="mb-3 d-flex"><i class="bi bi-check2 text-success me-2 fs-5"></i> 1 Punto de Venta (TPV)</li>
                                <li class="mb-3 d-flex"><i class="bi bi-check2 text-success me-2 fs-5"></i> Hasta 100 productos</li>
                                <li class="mb-3 d-flex"><i class="bi bi-check2 text-success me-2 fs-5"></i> Reportes básicos</li>
                            </ul>
                            <div class="d-grid mt-auto"><a href="registro.php?plan=basico" class="btn btn-light border rounded-pill py-2 fw-semibold">Empezar gratis</a></div>
                        </div>
                    </div>
                </div>

                <!-- PLAN PRO -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-lg rounded-4 p-4 position-relative pricing-card-pro bg-white">
                        <div class="position-absolute top-0 start-50 translate-middle">
                            <span class="badge rounded-pill bg-primary px-4 py-2 badge-pulse fw-bold shadow-sm"><i class="bi bi-star-fill text-warning me-1"></i> RECOMENDADO</span>
                        </div>
                        <div class="card-body d-flex flex-column mt-2">
                            <h4 class="fw-bold text-primary">Profesional</h4>
                            <p class="text-muted small">Para negocios con alta rotación y personal.</p>
                            <div class="my-4">
                                <span class="display-4 fw-bold text-dark">$29</span><span class="text-muted fw-medium">/mes</span>
                            </div>
                            <ul class="list-unstyled mb-5 flex-grow-1">
                                <li class="mb-3 d-flex fw-medium"><i class="bi bi-check-circle-fill text-primary me-2 fs-5"></i> TPVs y Productos Ilimitados</li>
                                <li class="mb-3 d-flex fw-medium"><i class="bi bi-check-circle-fill text-primary me-2 fs-5"></i> Inventario Avanzado</li>
                                <li class="mb-3 d-flex fw-medium"><i class="bi bi-check-circle-fill text-primary me-2 fs-5"></i> Módulos de Mesas/Delivery</li>
                                <li class="mb-3 d-flex fw-medium"><i class="bi bi-check-circle-fill text-primary me-2 fs-5"></i> Soporte Prioritario</li>
                            </ul>
                            <div class="d-grid mt-auto"><a href="registro.php?plan=pro" class="btn btn-primary-custom rounded-pill py-3 fw-bold fs-6">Prueba Gratis 30 Días</a></div>
                        </div>
                    </div>
                </div>

                <!-- PLAN PREMIUM -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 rounded-4 p-4 hover-card">
                        <div class="card-body d-flex flex-column">
                            <h4 class="fw-bold">Premium</h4>
                            <p class="text-muted small">Cadenas de tiendas y franquicias.</p>
                            <div class="my-4">
                                <span class="display-4 fw-bold text-dark">$59</span><span class="text-muted fw-medium">/mes</span>
                            </div>
                            <ul class="list-unstyled mb-5 flex-grow-1">
                                <li class="mb-3 d-flex"><i class="bi bi-check2 text-success me-2 fs-5"></i> Todo lo del Plan Pro</li>
                                <li class="mb-3 d-flex"><i class="bi bi-check2 text-success me-2 fs-5"></i> Multi-sucursal</li>
                                <li class="mb-3 d-flex"><i class="bi bi-check2 text-success me-2 fs-5"></i> API para integraciones</li>
                            </ul>
                            <div class="d-grid mt-auto"><a href="registro.php?plan=premium" class="btn btn-light border rounded-pill py-2 fw-semibold">Contactar Ventas</a></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section id="faq" class="py-5 bg-white border-top">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Preguntas Frecuentes</h2>
                <p class="text-muted">Resolvemos tus dudas antes de empezar.</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item border-0 mb-3 shadow-sm rounded">
                            <h2 class="accordion-header">
                                <button class="accordion-button rounded" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    ¿Puedo seguir usando el sistema si se corta el internet?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Sí. MultiPOS cuenta con un modo offline de contingencia que te permite seguir registrando ventas. Una vez que la conexión a internet se restablezca, todos los datos y el inventario se sincronizarán automáticamente con la nube.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm rounded">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    ¿Necesito comprar equipos especiales?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    No. MultiPOS funciona desde cualquier navegador web moderno (Chrome, Firefox, Safari). Puedes usar la computadora, tablet o teléfono que ya tienes. Además, somos compatibles con el 95% de impresoras térmicas y lectores de barras estándar USB o Bluetooth.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm rounded">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    ¿Qué pasa cuando terminan mis 30 días de prueba?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Antes de finalizar la prueba te enviaremos un aviso. Si decides no contratar un plan de pago, tu cuenta pasará automáticamente al Plan Básico (gratuito) con sus respectivas limitaciones, pero no perderás tu información. No cobramos automáticamente porque no pedimos tarjeta de crédito para el registro.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-3 shadow-sm rounded">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    Tengo un catálogo muy grande, ¿es difícil migrar a MultiPOS?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    En absoluto. Contamos con una herramienta de importación masiva mediante plantillas de Excel (.xlsx o .csv). Puedes subir miles de productos, con sus códigos de barras y precios, en cuestión de segundos. Si necesitas ayuda, nuestro equipo de soporte lo hace por ti.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
            <section class="py-5 bg-white mb-4">
        <div class="container">
            <div class="final-cta p-5 text-center shadow-lg position-relative overflow-hidden">
                <!-- Círculos decorativos de fondo -->
                <div class="position-absolute top-0 start-0 translate-middle rounded-circle bg-white opacity-10" style="width: 300px; height: 300px;"></div>
                <div class="position-absolute bottom-0 end-0 translate-middle-x rounded-circle bg-white opacity-10" style="width: 200px; height: 200px;"></div>
                
                <div class="position-relative z-1">
                    <h2 class="display-5 fw-bold mb-3">Toma el control de tu negocio hoy</h2>
                    <p class="fs-5 mb-4 text-white-50 mx-auto" style="max-width: 600px;">Únete a los emprendedores que ya están simplificando sus ventas, controlando su inventario y creciendo sin límites.</p>
                    <a href="registro.php" class="btn btn-light btn-lg px-5 py-3 rounded-pill fw-bold text-primary shadow">
                        Crear cuenta gratis ahora
                    </a>
                    <p class="mt-3 small text-white-50"><i class="bi bi-clock me-1"></i> Configuración en menos de 5 minutos.</p>
                </div>
            </div>
        </div>
    </section>
        </main>
        <footer class="bg-dark text-white pt-5 pb-4 border-top border-secondary">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4 pe-lg-5">
                <h5 class="fw-bold mb-3 d-flex align-items-center">
                    <div class="bg-primary rounded p-1 me-2 d-inline-flex">
                        <i class="bi bi-box-seam-fill text-white fs-6"></i>
                    </div>
                    MultiPOS
                </h5>
                <p class="text-secondary small mb-4">Transformando la gestión comercial con tecnología intuitiva y potente. Control total de tu negocio en la palma de tu mano.</p>
                <div class="d-flex gap-3">
                    <a href="#" class="btn btn-outline-secondary border-0 btn-sm rounded-circle"><i class="bi bi-facebook fs-5"></i></a>
                    <a href="#" class="btn btn-outline-secondary border-0 btn-sm rounded-circle"><i class="bi bi-instagram fs-5"></i></a>
                    <a href="#" class="btn btn-outline-secondary border-0 btn-sm rounded-circle"><i class="bi bi-linkedin fs-5"></i></a>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <h6 class="fw-bold mb-3 text-uppercase small text-white-50">Producto</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="#features" class="text-secondary text-decoration-none">Características</a></li>
                    <li><a href="#hardware" class="text-secondary text-decoration-none">Compatibilidad</a></li>
                    <li><a href="#solutions" class="text-secondary text-decoration-none">Módulos</a></li>
                    <li><a href="#pricing" class="text-secondary text-decoration-none">Precios</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-3 col-lg-3">
                <h6 class="fw-bold mb-3 text-uppercase small text-white-50">Soporte</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="#faq" class="text-secondary text-decoration-none">Preguntas Frecuentes</a></li>
                    <li><a href="#" class="text-secondary text-decoration-none">Documentación API</a></li>
                    <li><a href="https://wa.me/573148900155" target="_blank" class="text-success text-decoration-none fw-medium"><i class="bi bi-whatsapp me-1"></i> WhatsApp</a></li>
                </ul>
            </div>
            <div class="col-md-6 col-lg-3">
                <h6 class="fw-bold mb-3 text-uppercase small text-white-50">Legal</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="#" class="text-secondary text-decoration-none">Términos de Servicio</a></li>
                    <li><a href="#" class="text-secondary text-decoration-none">Política de Privacidad</a></li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary mt-5 mb-4 opacity-25">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="text-secondary mb-0 small">&copy; 2026 MultiPOS. Todos los derechos reservados.</p>
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                <p class="text-secondary mb-0 small">Hecho con <i class="bi bi-heart-fill text-danger mx-1"></i> para emprendedores.</p>
            </div>
        </div>
    </div>
</footer>
 
<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>

<!-- Glassmorphism Navbar Script -->
<script>
    window.addEventListener('scroll', function() {
        const nav = document.querySelector('.navbar-landing');
        if (window.scrollY > 50) {
            nav.classList.add('shadow-sm');
            nav.style.paddingTop = '10px';
            nav.style.paddingBottom = '10px';
        } else {
            nav.classList.remove('shadow-sm');
            nav.style.paddingTop = '16px';
            nav.style.paddingBottom = '16px';
        }
    });
</script>

</body>
</html>