<?php
class Middleware {
    public static function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Verificar si hay sesión de usuario y de tienda
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
            header("Location: login.php");
            exit();
        }

        // 2. Verificar vencimiento de licencia (Opcional: Capa extra de seguridad)
        if (isset($_SESSION['expiration_date'])) {
            $today = date('Y-m-d');
            if ($_SESSION['expiration_date'] < $today) {
                session_destroy();
                header("Location: login.php?error=expired_session");
                exit();
            }
        }
    }
       // Nueva función para restringir páginas solo a Administradores
    public static function onlyAdmin() {
        self::checkAuth();
        if ($_SESSION['role'] !== 'admin') {
            // Si es vendedor y trata de entrar a Admin, lo mandamos al POS
            header("Location: pos.php?error=unauthorized");
            exit;
        }
    }
}
?>