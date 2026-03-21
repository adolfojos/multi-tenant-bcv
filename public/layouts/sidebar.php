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
        
        <li class="nav-header text-primary fw-bold">
          <i class="bi bi-shop me-2"></i> ADMIN
        </li>
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo ($pagina_actual == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="nav-icon bi bi-circle"></i>
                <p>Dashboard</p>
             </a>
        </li>

        <li class="nav-item">
          <a href="pos.php" class="nav-link <?php echo ($pagina_actual == 'pos.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-cash-stack"></i>
            <p>Venta</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="admin.php" class="nav-link <?php echo ($pagina_actual == 'admin.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-box-seam"></i>
            <p>Inventario</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="categories.php" class="nav-link <?php echo ($pagina_actual == 'categories.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-tags"></i>
            <p>Categorías</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="sales.php" class="nav-link <?php echo ($pagina_actual == 'sales.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-wallet2"></i>
            <p>Flujo de Caja</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="sales_history.php" class="nav-link <?php echo ($pagina_actual == 'sales_history.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-clock-history"></i>
            <p>Historial</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="users.php" class="nav-link <?php echo ($pagina_actual == 'users.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-people"></i>
            <p>Usuarios</p>
          </a>
        </li>
      </ul>
    </nav>

    <div class="sidebar-footer border-top border-secondary pt-2 pb-2">
      <ul class="nav sidebar-menu flex-column">
        <li class="nav-item">
          <a href="configuration.php" class="nav-link <?php echo ($pagina_actual == 'configuration.php') ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-gear"></i>
            <p>Configuración</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="logout.php" class="nav-link text-danger">
            <i class="nav-icon bi bi-box-arrow-right"></i>
            <p>Salir</p>
          </a>
        </li>
      </ul>
    </div>
  </div>
  </aside>