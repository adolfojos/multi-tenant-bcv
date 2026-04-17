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

                <li class="nav-header text-uppercase opacity-75 small fw-bold mt-3">Sistema</li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt text-danger"></i>
                        <p class="text-danger">Cerrar Sesión</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>