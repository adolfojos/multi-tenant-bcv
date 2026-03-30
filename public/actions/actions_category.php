<?php
require_once '../../config/db.php';
require_once '../../includes/Category.php';
require_once '../../includes/Middleware.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Forzar respuesta JSON
header('Content-Type: application/json');

// 2. Seguridad: Solo admins autenticados pueden ejecutar estas acciones
try {
    Middleware::checkAuth();
    Middleware::onlyAdmin();

    $database = new Database();
    $db = $database->getConnection();
    
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    if (!$tenant_id) {
        throw new Exception("Sesión de comercio no válida.");
    }

    $catObj = new Category($db, $tenant_id);
    $action = $_POST['action'] ?? '';
    $id     = $_POST['id'] ?? null;
    $name   = trim($_POST['name'] ?? '');

    $response = ['status' => false, 'message' => 'Acción no reconocida'];

    // 3. Lógica de acciones
    switch ($action) {
        case 'create':
            if (empty($name)) throw new Exception("El nombre es obligatorio.");
            if ($catObj->create($name)) {
                $response = ['status' => true, 'message' => 'Creado con éxito'];
            }
            break;

        case 'update':
            if (empty($id) || empty($name)) throw new Exception("Datos insuficientes para actualizar.");
            if ($catObj->update($id, $name)) {
                $response = ['status' => true, 'message' => 'Actualizado con éxito'];
            }
            break;

        case 'delete':
            if (empty($id)) throw new Exception("ID no proporcionado.");
            if ($catObj->delete($id)) {
                $response = ['status' => true, 'message' => 'Eliminado con éxito'];
            }
            break;
    }

    echo json_encode($response);

} catch (Exception $e) {
    // Captura cualquier error de la DB o de validación
    http_response_code(400); // Opcional: indica un error de solicitud
    echo json_encode([
        'status' => false, 
        'message' => $e->getMessage()
    ]);
}