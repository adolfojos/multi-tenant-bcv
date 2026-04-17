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
    // 2. RENOVAR LICENCIA Y REGISTRAR PAGO
    elseif ($action === 'renew') {
        $id = (int)$_POST['id'];
        $months = (int)$_POST['months'];
        $amount = (float)$_POST['amount'];
        $method = trim($_POST['payment_method'] ?? '');
        $reference = trim($_POST['reference'] ?? '');

        if ($amount <= 0 || empty($method)) {
            throw new Exception("Debes ingresar el monto y el método de pago.");
        }

        $conn->beginTransaction();

        // A. Actualizar expiración de la tienda
        $sql = "UPDATE tenants SET expiration_date = DATE_ADD(expiration_date, INTERVAL ? MONTH), status='active' WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$months, $id]);

        // B. Registrar el pago en el historial
        $sqlPay = "INSERT INTO tenant_payments (tenant_id, amount_usd, payment_method, reference, months_added) VALUES (?, ?, ?, ?, ?)";
        $stmtPay = $conn->prepare($sqlPay);
        $stmtPay->execute([$id, $amount, $method, $reference, $months]);

        // Obtener el ID del pago para generar el recibo
        $paymentId = $conn->lastInsertId();

        $conn->commit();

        echo json_encode([
            'status' => true,
            'message' => 'Pago registrado y suscripción renovada.',
            'payment_id' => $paymentId
        ]);
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
    }
    // ==========================================
    // MÓDULO DE PLANES (SaaS)
    // ==========================================

    // 8. CREAR O EDITAR PLAN
    elseif ($action === 'save_plan') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price_usd'] ?? 0);
        $max_users = (int)($_POST['max_users'] ?? 0);
        $max_products = (int)($_POST['max_products'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) throw new Exception("El nombre del plan es obligatorio.");

        if ($id) {
            // Actualizar plan existente
            $sql = "UPDATE plans SET name=?, price_usd=?, max_users=?, max_products=?, description=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$name, $price, $max_users, $max_products, $description, $id]);
            $msg = "Plan actualizado correctamente.";
        } else {
            // Crear nuevo plan
            $sql = "INSERT INTO plans (name, price_usd, max_users, max_products, description) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$name, $price, $max_users, $max_products, $description]);
            $msg = "Nuevo plan creado exitosamente.";
        }
        
        echo json_encode(['status' => true, 'message' => $msg]);
    }

    // 9. OBTENER DATOS DE UN PLAN (Para el Modal de Edición)
    elseif ($action === 'get_plan') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("SELECT * FROM plans WHERE id = ?");
        $stmt->execute([$id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($plan) echo json_encode(['status' => true, 'data' => $plan]);
        else throw new Exception("Plan no encontrado.");
    }

    // 10. ELIMINAR PLAN
    elseif ($action === 'delete_plan') {
        $id = (int)$_POST['id'];
        
        // Evitar borrar el plan si hay tiendas usándolo
        $check = $conn->prepare("SELECT COUNT(*) FROM tenants WHERE plan_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            throw new Exception("No puedes eliminar este plan porque hay tiendas usándolo. Cambia el plan de esas tiendas primero.");
        }

        $conn->prepare("DELETE FROM plans WHERE id=?")->execute([$id]);
        echo json_encode(['status' => true, 'message' => 'Plan eliminado.']);
    }

    // 11. CAMBIAR PLAN A UNA TIENDA (Upsell / Downsell)
    elseif ($action === 'change_tenant_plan') {
        $tenant_id = (int)$_POST['tenant_id'];
        $plan_id = (int)$_POST['plan_id'];

        $sql = "UPDATE tenants SET plan_id=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$plan_id, $tenant_id]);

        echo json_encode(['status' => true, 'message' => 'Plan actualizado. Los nuevos límites ya están activos para este cliente.']);
    }
    else {
        throw new Exception("Acción no reconocida.");
    }
    
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
