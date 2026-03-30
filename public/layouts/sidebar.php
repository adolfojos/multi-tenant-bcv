<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
  <div class="sidebar-brand">
    <a href="./admin.php" class="brand-link">
      <img src="./assets/img/AdminLTELogo.png" alt="Logo" class="brand-image opacity-75 shadow" />
      <span class="brand-text fw-light"><?= htmlspecialchars($tenant_name) ?></span>
    </a>
  </div>
  
  <div class="sidebar-wrapper d-flex flex-column">
    <nav class="mt-2 flex-grow-1">
      <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="Main navigation">

        <li class="nav-item">
          <a href="dashboard.php" class="nav-link <?= ($pagina_actual == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-speedometer2 text-info"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <li class="nav-header text-uppercase opacity-75 small fw-bold">Operaciones</li>
        <li class="nav-item">
          <a href="pos.php" class="nav-link <?= ($pagina_actual == 'pos.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-cart-plus-fill text-success"></i>
            <p>Punto de Venta (POS)</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="sales_history.php" class="nav-link <?= ($pagina_actual == 'sales_history.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-receipt text-warning"></i>
            <p>Historial de Ventas</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="credits.php" class="nav-link <?= ($pagina_actual == 'credits.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-calendar-check text-danger"></i>
            <p>Cuentas por Cobrar</p>
          </a>
        </li>

        <li class="nav-header text-uppercase opacity-75 small fw-bold">Almacén</li>
        <li class="nav-item">
          <a href="admin.php" class="nav-link <?= ($pagina_actual == 'admin.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-box-seam-fill text-primary"></i>
            <p>Inventario / Productos</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="categories.php" class="nav-link <?= ($pagina_actual == 'categories.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-tags-fill text-info"></i>
            <p>Categorías</p>
          </a>
        </li>

        <li class="nav-header text-uppercase opacity-75 small fw-bold">Análisis</li>
        <li class="nav-item">
          <a href="sales.php" class="nav-link <?= ($pagina_actual == 'sales.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-graph-up-arrow text-success"></i>
            <p>Reporte de Ingresos</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="customers.php" class="nav-link <?= ($pagina_actual == 'customers.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-people-fill text-primary"></i>
            <p>Cartera de Clientes</p>
          </a>
        </li>

        <li class="nav-header text-uppercase opacity-75 small fw-bold">Seguridad</li>
        <li class="nav-item">
          <a href="users.php" class="nav-link <?= ($pagina_actual == 'users.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-shield-lock-fill text-secondary"></i>
            <p>Gestión de Usuarios</p>
          </a>
        </li>
      </ul>
    </nav>

    <div class="sidebar-footer border-top border-secondary pt-2 pb-3">
      <ul class="nav sidebar-menu flex-column">
        <li class="nav-item">
          <a href="configuration.php" class="nav-link <?= ($pagina_actual == 'configuration.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-gear-fill text-light"></i>
            <p>Configuración</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="logout.php" class="nav-link">
            <i class="nav-icon bi bi-door-open-fill text-danger"></i>
            <p class="text-danger">Cerrar Sesión</p>
          </a>
        </li>
      </ul>
    </div>
  </div>
</aside>