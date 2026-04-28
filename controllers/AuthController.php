<?php
require_once '../config/db.php';
require_once '../includes/Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? ''; 

    $result = $auth->login($username, $password);

    if ($result === "OK") {
        if ($role === 'admin') {
            header("Location: pos.php"); // Redirigimos al nuevo admin estilizado
        } else {
            header("Location: pos.php");
        }
        exit;
    } elseif ($result === "SUSPENDED") {
        $error = "🚫 Cuenta suspendida. Contacte al proveedor.";
    } elseif ($result === "EXPIRED") {
        $error = "⚠️ Su licencia ha caducado. Por favor renueve.";
    } else {
        $error = "Credenciales incorrectas.";
    }
}
// Variables de apoyo para el layout

?>