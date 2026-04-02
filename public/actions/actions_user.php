<?php
//Archivo: ./public/actions/actions_user.php

require_once '../../includes/Middleware.php';
require_once '../../config/db.php';
require_once '../../includes/User.php';

session_start();
Middleware::onlyAdmin(); // Solo admins tocan esto

header('Content-Type: application/json');

$db = (new Database())->getConnection();
$userObj = new User($db, $_SESSION['tenant_id']);
$action = $_POST['action'] ?? '';

try {

    // En actions/actions_user.php
    if ($action === 'create') {
        $res = $userObj->create($_POST['username'], $_POST['full_name'], $_POST['password'], $_POST['role']);
        // Si el modelo devolvió true pero no traía mensaje, lo asignamos aquí
        if ($res['status']) $res['message'] = "El usuario ha sido registrado correctamente.";
        echo json_encode($res);
    } elseif ($action === 'update') {
        $pass = !empty($_POST['password']) ? $_POST['password'] : null;
        $res = $userObj->update($_POST['id'], $_POST['username'], $_POST['full_name'], $_POST['role'], $pass);
        if ($res['status']) $res['message'] = "Los datos se actualizaron con éxito.";
        echo json_encode($res);
    } elseif ($action === 'delete') {
        // 1. Validar que el ID exista y sea numérico
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id) {
            echo json_encode(['status' => false, 'message' => 'ID de usuario no válido.']);
            exit;
        }

        // 2. Intentar la eliminación
        try {
            $res = $userObj->delete($id);

            // 3. Personalizar el mensaje de éxito si el modelo no lo trae
            if ($res['status'] && !isset($res['message'])) {
                $res['message'] = "Usuario eliminado correctamente.";
            }

            echo json_encode($res);
        } catch (Exception $e) {
            // Log del error real para el desarrollador y mensaje genérico para el usuario
            error_log("Error en delete user: " . $e->getMessage());
            echo json_encode(['status' => false, 'message' => 'No se pudo eliminar el usuario debido a un error del servidor.']);
        }
    } else {
        echo json_encode(['status' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}