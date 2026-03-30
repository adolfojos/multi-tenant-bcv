<?php
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
    if ($action === 'create') {
        $res = $userObj->create($_POST['username'], $_POST['password'], $_POST['role']);
        echo json_encode($res);
    } 
    elseif ($action === 'update') {
        // Si password viene vacío, se envía null
        $pass = !empty($_POST['password']) ? $_POST['password'] : null;
        $res = $userObj->update($_POST['id'], $_POST['username'], $_POST['role'], $pass);
        echo json_encode($res);
    } 
    elseif ($action === 'delete') { // GET request normalmente para delete simple
        $id = $_POST['id'];
        $res = $userObj->delete($id);
        echo json_encode($res);
    } 
    else {
        echo json_encode(['status' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}