<?php
// 1. Cargas la lógica (El Controlador)
require_once '../controllers/AuthController.php';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Iniciar Sesión - Sistema POS</title>

  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
  <meta name="color-scheme" content="light dark" />
  <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
  <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
  <meta name="title" content="Iniciar Sesión - Sistema POS" />
  <meta name="author" content="ColorlibHQ" />
  <meta name="keywords" content="adminlte, pos, login" />
  <link rel="preload" href="css/adminlte.css" as="style" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
  <link rel="stylesheet" href="css/adminlte.css" />
  <link rel="stylesheet" href="./css/custom.css" />
</head>

<body class="login-page bg-body-secondary">
  
  <div class="login-box">
    <div class="card card-outline card-primary">
      <div class="card-header">
        <a href="#" class="link-dark text-center link-offset-2 link-opacity-100 link-opacity-50-hover">
          <h1 class="mb-0"><b>Sistema POS</b>v2.0</h1>
        </a>
      </div>
      <div class="card-body login-card-body">
        <p class="login-box-msg">Identifícate para iniciar sesión</p>
        <?php if ($error): ?>
          <div class="alert alert-danger" role="alert">
            <?= $error ?>
          </div>
        <?php endif; ?>
        <form  method="post">
          <div class="input-group mb-3">
            <div class="form-floating">
              <input id="username" name="username" type="text" class="form-control" placeholder="Usuario" required />
              <label for="username">Usuario</label>
            </div>
            <div class="input-group-text">
              <span class="bi bi-person-square"></span>
            </div>
          </div>

          <div class="input-group mb-3">
            <div class="form-floating">
              <input id="loginPassword" name="password" type="password" class="form-control" placeholder="Password" required />
              <label for="loginPassword">Contraseña</label>
            </div>
            <div class="input-group-text">
              <span class="bi bi-lock-fill"></span>
            </div>
          </div>

          <div class="input-group mb-3">
            <div class="form-floating">
              <select id="role" name="role" class="form-select" required>
                <option value="" selected disabled>Seleccione rol...</option>
                <option value="admin">Administrador</option>
                <option value="seller">Vendedor</option>
              </select>
              <label for="role">Tipo de Usuario</label>
            </div>
            <div class="input-group-text">
              <span class="bi bi-shield-lock"></span>
            </div>
          </div>

          <div class="row">
            <div class="col-8 d-inline-flex align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="flexCheckDefault" />
                <label class="form-check-label" for="flexCheckDefault"> Recordarme </label>
              </div>
            </div>
            <div class="col-4">
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Ingresar</button>
              </div>
            </div>
          </div>
        </form>

        <p class="mb-1 mt-3">
          <a href="forgot-password.html">Olvidé mi contraseña</a>
        </p>
        <p class="mb-0">
          <a href="register.html" class="text-center"> Registrar nueva cuenta </a>
        </p>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
  <script src="js/adminlte.js"></script>
  <script src="js/login.js"></script>
</body>
</html>