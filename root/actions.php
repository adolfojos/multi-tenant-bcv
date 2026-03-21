<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['is_superadmin'])) { header("Location: login.php"); exit; }

$database = new Database();
$conn = $database->getConnection();

$action = $_POST['action'] ?? '';

try {
    // 1. CREAR NUEVA TIENDA
    if ($action === 'create_tenant') {
        $name = $_POST['name'];
        $rif = $_POST['rif'];
        $user = $_POST['admin_user'];
        $pass = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);
        $months = (int)$_POST['months'];

        // Generar datos
        $expiration = date('Y-m-d', strtotime("+$months months"));
        $license = strtoupper(substr(md5(uniqid()), 0, 10));

        $conn->beginTransaction();

        // Insertar Tienda
        $sql = "INSERT INTO tenants (business_name, rif, license_key, expiration_date, status) VALUES (?, ?, ?, ?, 'active')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $rif, $license, $expiration]);
        $tenantId = $conn->lastInsertId();

        // Insertar Usuario Admin para esa tienda
        $sqlUser = "INSERT INTO users (username, password, tenant_id) VALUES (?, ?, ?)";
        $stmtUser = $conn->prepare($sqlUser);
        $stmtUser->execute([$user, $pass, $tenantId]);

        $conn->commit();
    }

    // 2. RENOVAR LICENCIA
    if ($action === 'renew') {
        $id = $_POST['id'];
        $months = (int)$_POST['months'];
        $sql = "UPDATE tenants SET expiration_date = DATE_ADD(expiration_date, INTERVAL ? MONTH), status='active' WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$months, $id]);
    }

    // 3. CAMBIAR ESTADO
    if ($action === 'toggle_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $sql = "UPDATE tenants SET status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$status, $id]);
    }

    // 4. ACTUALIZAR BCV
    if ($action === 'update_bcv') {
        $rate = $_POST['rate'];
        $sql = "UPDATE system_settings SET bcv_rate=?, last_update=NOW() WHERE id=1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$rate]);
    }

    header("Location: panel.php?msg=success");

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    die("Error: " . $e->getMessage());
}
?>