<?php
// Obtener el nombre del archivo actual para marcar la pestaña activa
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="app-sidebar bg-body-tertiary shadow" data-bs-theme="dark">
    <div class="sidebar-brand border-bottom border-secondary">
        <a href="dashboard.php" class="brand-link text-decoration-none">
            <i class="fas fa-crown text-warning mx-2"></i>
            <span class="brand-text fw-bold">SuperAdmin</span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                
                <li class="nav-header text-uppercase opacity-75 small fw-bold mt-2">Analíticas</li>
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-chart-pie text-success"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                
                <li class="nav-header text-uppercase opacity-75 small fw-bold mt-3">Gestión</li>
                <li class="nav-item">
                    <a href="panel.php" class="nav-link <?= $currentPage == 'panel.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-store text-info"></i>
                        <p>Tiendas (Tenants)</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="payments.php" class="nav-link <?= $currentPage == 'payments.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-file-invoice-dollar text-warning"></i>
                        <p>Historial de Pagos</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="plans.php" class="nav-link <?= $currentPage == 'plans.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fas fa-box-open text-warning"></i>
                        <p>Paquetes y Límites</p>
                    </a>
                </li>
                <li class="nav-header text-uppercase opacity-75 small fw-bold mt-3">Sistema</li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link <?= $currentPage == 'settings.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-cogs text-primary"></i>
                        <p>Configuración</p>
                    </a>
                
                <li class="nav-item">
                    <a href="backup.php" class="nav-link" onclick="setTimeout(() => alert('Descarga iniciada. El archivo SQL se guardará en tu dispositivo.'), 500);">
                        <i class="nav-icon fas fa-download text-success"></i>
                        <p>Respaldar BD</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="restore.php" class="nav-link">
                        <i class="nav-icon fas fa-upload text-warning"></i>
                        <p>Restaurar BD</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt text-danger"></i>
                        <p>Cerrar Sesión</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>