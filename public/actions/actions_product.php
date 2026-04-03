<?php
require_once '../../includes/Middleware.php';
require_once '../../config/db.php';

// Asegurar que la respuesta sea JSON
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
Middleware::checkAuth();
Middleware::onlyAdmin(); 

$database = new Database();
$db = $database->getConnection();

$action = $_REQUEST['action'] ?? '';
$tenant_id = $_SESSION['tenant_id'];

try {
    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) throw new Exception("El nombre del producto es obligatorio.");

            $sku = trim($_POST['sku'] ?? '');
            $barcode = trim($_POST['barcode'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $catId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 1;
            $price = floatval($_POST['price'] ?? 0);
            $margin = floatval($_POST['margin'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $image = trim($_POST['image'] ?? '');
            $description = trim($_POST['description'] ?? '');

            $sql = "INSERT INTO products 
                    (tenant_id, category_id, name, sku, barcode, brand, description, image, price_base_usd, profit_margin, stock, created_at) 
                    VALUES 
                    (:tid, :catid, :name, :sku, :barcode, :brand, :desc, :img, :price, :margin, :stock, NOW())";
            
            $stmt = $db->prepare($sql);
            $res = $stmt->execute([
                ':tid' => $tenant_id,
                ':catid' => $catId,
                ':name' => $name,
                ':sku' => $sku,
                ':barcode' => $barcode,
                ':brand' => $brand,
                ':desc' => $description,
                ':img' => $image,
                ':price' => $price,
                ':margin' => $margin,
                ':stock' => $stock
            ]);

            if ($res) {
                echo json_encode(['status' => true, 'message' => 'Producto creado con éxito.']);
                exit;
            } else {
                throw new Exception("No se pudo crear el producto en la base de datos.");
            }
            break;

        case 'update':
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) throw new Exception("ID de producto inválido para actualizar.");

            $name = trim($_POST['name'] ?? '');
            if (empty($name)) throw new Exception("El nombre del producto es obligatorio.");

            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 1;
            $sku = trim($_POST['sku'] ?? '');
            $barcode = trim($_POST['barcode'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $margin = floatval($_POST['margin'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $image = trim($_POST['image'] ?? '');
            $description = trim($_POST['description'] ?? '');

            $sql = "UPDATE products 
                    SET name = :name,
                        category_id = :category_id,
                        sku = :sku,
                        barcode = :barcode,
                        brand = :brand,
                        description = :desc,
                        image = :img,
                        price_base_usd = :price, 
                        profit_margin = :margin, 
                        stock = :stock 
                    WHERE id = :id AND tenant_id = :tid";
            
            $stmt = $db->prepare($sql);
            $res = $stmt->execute([
                ':name' => $name,
                ':category_id' => $category_id,
                ':sku' => $sku,
                ':barcode' => $barcode,
                ':brand' => $brand,
                ':desc' => $description,
                ':img' => $image,
                ':price' => $price,
                ':margin' => $margin,
                ':stock' => $stock,
                ':id' => $id,
                ':tid' => $tenant_id
            ]);

            if ($res) {
                echo json_encode(['status' => true, 'message' => 'Producto actualizado con éxito.']);
                exit;
            } else {
                throw new Exception("Error al ejecutar la actualización.");
            }
            break;

        case 'delete':
            $id = !empty($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
            if ($id <= 0) throw new Exception("ID no proporcionado para eliminar.");

            $sql = "DELETE FROM products WHERE id = :id AND tenant_id = :tid";
            $stmt = $db->prepare($sql);
            $res = $stmt->execute([
                ':id' => $id,
                ':tid' => $tenant_id
            ]);

            if ($res) {
                echo json_encode(['status' => true, 'message' => 'Producto eliminado correctamente.']);
                exit;
            } else {
                throw new Exception("Error al intentar eliminar el producto.");
            }
            break;

        default:
            throw new Exception("Acción no válida.");
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    exit;
}
?>