<?php
session_start();
require_once '../config/db.php';

// Forzar respuesta JSON
header('Content-Type: application/json');

if (!isset($_SESSION['is_superadmin'])) {
    echo json_encode(['status' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$action = $_POST['action'] ?? '';

try {
    // 1. CREAR NUEVA TIENDA
    if ($action === 'create_tenant') {
        $name = trim($_POST['name'] ?? '');
        $rif = trim($_POST['rif'] ?? '');
        $user = trim($_POST['admin_user'] ?? '');
        $passInput = $_POST['admin_pass'] ?? '';
        $months = (int)($_POST['months'] ?? 1);

        if (empty($name) || empty($user) || empty($passInput)) {
            throw new Exception("Faltan datos obligatorios.");
        }

        $pass = password_hash($passInput, PASSWORD_DEFAULT);
        $expiration = date('Y-m-d', strtotime("+$months months"));
        $license = strtoupper(substr(md5(uniqid()), 0, 10));

        $conn->beginTransaction();

        $sql = "INSERT INTO tenants (business_name, rif, license_key, expiration_date, status) VALUES (?, ?, ?, ?, 'active')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $rif, $license, $expiration]);
        $tenantId = $conn->lastInsertId();

        $sqlUser = "INSERT INTO users (username, password, tenant_id, role) VALUES (?, ?, ?, 'admin')";
        $stmtUser = $conn->prepare($sqlUser);
        $stmtUser->execute([$user, $pass, $tenantId]);

        $conn->commit();
        echo json_encode(['status' => true, 'message' => 'Tienda y administrador creados con éxito.']);
    }

    // 2. RENOVAR LICENCIA
    elseif ($action === 'renew') {
        $id = (int)$_POST['id'];
        $months = (int)$_POST['months'];
        $sql = "UPDATE tenants SET expiration_date = DATE_ADD(expiration_date, INTERVAL ? MONTH), status='active' WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$months, $id]);

        echo json_encode(['status' => true, 'message' => 'Suscripción renovada correctamente.']);
    }

    // 3. CAMBIAR ESTADO (Activar / Suspender)
    elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status']; // 'active' o 'suspended'
        $sql = "UPDATE tenants SET status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$status, $id]);

        $msg = $status === 'active' ? 'Tienda activada nuevamente.' : 'Tienda suspendida.';
        echo json_encode(['status' => true, 'message' => $msg]);
    }

    // 4. ACTUALIZAR BCV
    elseif ($action === 'update_bcv') {
        $rate = (float)$_POST['rate'];
        if ($rate <= 0) throw new Exception("La tasa debe ser mayor a 0.");

        $sql = "UPDATE system_settings SET bcv_rate=?, last_update=NOW() WHERE id=1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$rate]);

        echo json_encode(['status' => true, 'message' => 'Tasa BCV global actualizada.']);
    }
    // 5. OBTENER DETALLES (VER / EDITAR)
    elseif ($action === 'get_tenant') {
        $id = (int)$_POST['id'];

        // Obtenemos los datos de la tienda y su usuario administrador principal
        $sql = "SELECT t.*, u.username as admin_user FROM tenants t 
                LEFT JOIN users u ON t.id = u.tenant_id AND u.role = 'admin' 
                WHERE t.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tenant) {
            echo json_encode(['status' => true, 'data' => $tenant]);
        } else {
            throw new Exception("Tienda no encontrada.");
        }
    }

    // 6. EDITAR TIENDA
    elseif ($action === 'edit_tenant') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $rif = trim($_POST['rif'] ?? '');

        if (empty($name)) throw new Exception("El nombre del negocio no puede estar vacío.");

        $conn->beginTransaction();

        $sql = "UPDATE tenants SET business_name=?, rif=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $rif, $id]);

        // Si el SuperAdmin escribió una nueva contraseña, se actualiza
        if (!empty($_POST['new_password'])) {
            $pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $sqlUser = "UPDATE users SET password=? WHERE tenant_id=? AND role='admin'";
            $stmtUser = $conn->prepare($sqlUser);
            $stmtUser->execute([$pass, $id]);
        }

        $conn->commit();
        echo json_encode(['status' => true, 'message' => 'Datos de la tienda actualizados.']);
    }

    // 7. ELIMINAR DEFINITIVAMENTE (HARD DELETE)
    elseif ($action === 'delete_tenant') {
        $id = (int)$_POST['id'];

        $conn->beginTransaction();

        // NOTA: Si tu base de datos no tiene "ON DELETE CASCADE", debes eliminar los registros 
        // relacionados primero (productos, categorías, ventas, etc.)
        $conn->prepare("DELETE FROM users WHERE tenant_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM tenants WHERE id=?")->execute([$id]);

        $conn->commit();
        echo json_encode(['status' => true, 'message' => 'Tienda eliminada permanentemente del sistema.']);
    } else {
        throw new Exception("Acción no reconocida.");
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
