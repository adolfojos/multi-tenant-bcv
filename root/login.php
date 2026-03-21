<?php
session_start();
// CONFIGURACIÓN DE ACCESO MAESTRO
define('MASTER_USER', 'root');     // Cambia esto
define('MASTER_PASS', 'root');     // Cambia esto por una contraseña fuerte

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    if ($user === MASTER_USER && $pass === MASTER_PASS) {
        $_SESSION['is_superadmin'] = true;
        header("Location: panel.php");
        exit;
    } else {
        $error = "Credenciales incorrectas.";
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Dueño SaaS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
    <style>
        /* Ajuste sutil para centrar perfectamente en pantallas altas */
        .login-page {
            align-items: center;
            display: flex;
            flex-direction: column;
            height: 100vh;
            justify-content: center;
        }
    </style>
</head>
<body class="login-page bg-body-secondary">

    <div class="login-box">
        <div class="login-logo">
            <a href="#" class="text-decoration-none fw-bold text-light">
                <i class="fas fa-crown text-warning me-2"></i><b>Super</b>Admin
            </a>
        </div>

        <div class="card card-outline card-warning shadow-lg">
            <div class="card-body login-card-body p-4">
                <p class="login-box-msg text-secondary text-center mb-3">Ingresa tus credenciales maestras</p>

                <?php if($error): ?>
                    <div class="alert alert-danger p-2 small text-center shadow-sm">
                        <i class="fas fa-exclamation-circle me-1"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input type="text" name="user" id="userInput" class="form-control" placeholder="Usuario Maestro" required autofocus>
                            <label for="userInput">Usuario Maestro</label>
                        </div>
                        <div class="input-group-text bg-dark border-secondary">
                            <i class="fas fa-user-shield text-muted"></i>
                        </div>
                    </div>

                    <div class="input-group mb-4">
                        <div class="form-floating">
                            <input type="password" name="pass" id="passInput" class="form-control" placeholder="Contraseña" required>
                            <label for="passInput">Contraseña</label>
                        </div>
                        <div class="input-group-text bg-dark border-secondary">
                            <i class="fas fa-lock text-muted"></i>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning w-100 fw-bold shadow-sm">
                                <i class="fas fa-sign-in-alt me-2"></i> Entrar al Panel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>
</body>
</html>