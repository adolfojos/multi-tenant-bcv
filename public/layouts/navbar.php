<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>
        
        <ul class="navbar-nav ms-auto">
            <li class="nav-item">
                <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                    <i data-lte-icon="maximize" class="fas fa-expand-arrows-alt"></i>
                    <i data-lte-icon="minimize" class="fas fa-compress-arrows-alt" style="display: none"></i>
                </a>
            </li>
            
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="bg-primary bg-opacity-25 text-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <span class="d-none d-md-inline ms-2">
                        <?= htmlspecialchars($_SESSION['username'] ?? 'Usuario') ?>
                    </span>
                </a>
                
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <li class="user-header text-bg-primary">
                        <i class="fas fa-user-circle fa-4x mb-2 text-light"></i>
                        <p>
                            <span class="fw-bold"><?= htmlspecialchars($_SESSION['username'] ?? 'Usuario') ?></span> 
                            - <?= htmlspecialchars($_SESSION['role'] ?? 'Administrador') ?>
                            
                            <small class="mt-1">
                                <i class="fas fa-store me-1"></i> 
                                <?= htmlspecialchars($tenant_name ?? 'Mi Tienda') ?>
                            </small>
                        </p>
                    </li>
                    <li class="user-footer">
                        <a href="configuration.php" class="btn btn-default btn-flat"><i class="fas fa-cog me-1"></i> Ajustes</a>
                        <a href="logout.php" class="btn btn-danger btn-flat float-end"><i class="fas fa-sign-out-alt me-1"></i> Cerrar Sesión</a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>