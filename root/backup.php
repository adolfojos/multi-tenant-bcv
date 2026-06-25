<?php
// Archivo: ./root/backup.php
session_start();
require_once '../config/db.php';

// Seguridad: Solo el SuperAdmin puede descargar la base de datos completa
if (!isset($_SESSION['is_superadmin'])) {
    header("Location: login.php");
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

// Desactivar límites de tiempo y memoria para bases de datos grandes
ini_set('memory_limit', '-1');
set_time_limit(0);

// 1. Obtener todas las tablas
$tables = [];
$query = $pdo->query("SHOW TABLES");
while ($row = $query->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

// 2. Cabecera del archivo SQL
$sql = "-- Respaldo de Base de Datos MultiPOS\n";
$sql .= "-- Generado el: " . date('Y-m-d H:i:s') . "\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

// 3. Recorrer cada tabla para obtener su estructura y datos
foreach ($tables as $table) {
    $sql .= "-- Estructura y datos para la tabla `$table`\n";
    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
    
    // Obtener la estructura (CREATE TABLE)
    $row2 = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
    $sql .= $row2[1] . ";\n\n";

    // Obtener los datos
    $query = $pdo->query("SELECT * FROM `$table`");
    $num_rows = $query->rowCount();
    $num_fields = $query->columnCount();

    if ($num_rows > 0) {
        $sql .= "INSERT INTO `$table` VALUES \n";
        $rowCounter = 0;
        
        while ($row = $query->fetch(PDO::FETCH_NUM)) {
            $sql .= "(";
            for ($j = 0; $j < $num_fields; $j++) {
                $val = $row[$j];
                if (is_null($val)) {
                    $sql .= "NULL";
                } else {
                    // PDO::quote() escapa las comillas y caracteres especiales de forma segura
                    $sql .= $pdo->quote($val);
                }
                
                if ($j < ($num_fields - 1)) {
                    $sql .= ',';
                }
            }
            $rowCounter++;
            $sql .= ($rowCounter < $num_rows) ? "),\n" : ");\n\n";
        }
    }
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

// 4. Configurar las cabeceras HTTP para forzar la descarga del archivo
$filename = 'backup_multipos_' . date('Y-m-d_H-i-s') . '.sql';

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Imprimir el contenido y salir
echo $sql;
exit;
?>