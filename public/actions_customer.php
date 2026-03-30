<?php
require_once '../includes/Middleware.php';
require_once '../config/db.php';
require_once '../includes/Customer.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();

$database = new Database();
$db = $database->getConnection();
$tenant_id = $_SESSION['tenant_id'];

$action = $_POST['action'] ?? '';
$customerObj = new Customer($db, $tenant_id);

try {
    if ($action === 'search') {
        $term = $_POST['term'] ?? '';
        $sql = "SELECT id, name, document FROM customers 
                WHERE tenant_id = :tid 
                AND (name LIKE :term1 OR document LIKE :term2) 
                ORDER BY name ASC LIMIT 10";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':tid' => $tenant_id,
            ':term1' => "%$term%",
            ':term2' => "%$term%"
        ]);
        echo json_encode(['status' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    elseif ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $document = trim($_POST['document'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name)) throw new Exception("El nombre es obligatorio.");

        if($customerObj->create($name, $document, $phone)) {
            echo json_encode([
                'status' => true, 
                'message' => 'Cliente creado exitosamente',
                'customer' => ['id' => $db->lastInsertId(), 'name' => $name, 'document' => $document]
            ]);
        } else {
            throw new Exception("Error al guardar en la base de datos.");
        }
    } 
    elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $document = trim($_POST['document'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($id) || empty($name)) throw new Exception("Datos insuficientes.");

        if($customerObj->update($id, $name, $document, $phone)) {
            echo json_encode(['status' => true, 'message' => 'Cliente actualizado exitosamente']);
        } else {
            throw new Exception("Error al actualizar.");
        }
    }
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (empty($id)) throw new Exception("ID no válido.");

        // Aquí podrías envolver el borrado en un try-catch por si falla por Foreign Keys (ventas asociadas)
        try {
            $customerObj->delete($id);
            echo json_encode(['status' => true, 'message' => 'Cliente eliminado exitosamente']);
        } catch (PDOException $e) {
            // Error 23000 es violación de restricción de clave foránea
            if ($e->getCode() == '23000') {
                throw new Exception("No se puede eliminar el cliente porque tiene ventas o créditos asociados.");
            }
            throw $e;
        }
    }
    else {
        throw new Exception('Acción no válida.');
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>