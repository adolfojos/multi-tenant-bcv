<?php
// Archivo: ./root/restore.php
session_start();
require_once '../config/db.php';

// Seguridad: Solo el SuperAdmin puede acceder
if (!isset($_SESSION['is_superadmin'])) {
    header("Location: login.php");
    exit;
}

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        // Validar que realmente sea un archivo SQL
        if (strtolower($ext) !== 'sql') {
            $message = "Error: Solo se permiten archivos con extensión .sql";
            $status = "danger";
        } else {
            try {
                $database = new Database();
                $pdo = $database->getConnection();
                
                // Evitar límites de tiempo y memoria
                ini_set('memory_limit', '-1');
                set_time_limit(0);
                
                // Desactivar temporalmente la verificación de llaves foráneas
                $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
                
                // Abrir el archivo temporal para lectura línea por línea
                $handle = fopen($file['tmp_name'], "r");
                $templine = '';
                
                if ($handle) {
                    while (($line = fgets($handle)) !== false) {
                        // Saltar comentarios de SQL y líneas vacías
                        if (substr(trim($line), 0, 2) == '--' || trim($line) == '' || substr(trim($line), 0, 2) == '/*') {
                            continue;
                        }
                        
                        $templine .= $line;
                        
                        // Si encontramos un punto y coma al final, ejecutamos la sentencia
                        if (substr(trim($line), -1, 1) == ';') {
                            $pdo->exec($templine);
                            $templine = ''; // Limpiar variable para la siguiente consulta
                        }
                    }
                    fclose($handle);
                    
                    // Reactivar la verificación de llaves foráneas
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
                    
                    $message = "¡Base de datos restaurada con éxito desde el archivo: <strong>" . htmlspecialchars($file['name']) . "</strong>!";
                    $status = "success";
                } else {
                    $message = "Error: No se pudo abrir el archivo temporal.";
                    $status = "danger";
                }
            } catch (PDOException $e) {
                $message = "Error durante la restauración: " . $e->getMessage();
                $status = "danger";
            }
        }
    } else {
        $message = "Error al subir el archivo. Código de error: " . $file['error'];
        $status = "danger";
    }
}

// Incluir vistas de la interfaz
include 'layouts/head.php';
include 'layouts/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Mantenimiento del Sistema</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8 mx-auto">
                    
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $status; ?> alert-dismissible fade show" role="alert">
                            <h5><i class="icon fas <?php echo ($status == 'success') ? 'fa-check' : 'fa-ban'; ?>"></i> Notificación</h5>
                            <?php echo $message; ?>
                            <button type="text" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="card card-warning card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-exclamation-triangle text-warning"></i> ¡Atención! Zona Crítica</h3>
                        </div>
                        <div class="card-body">
                            <p>La restauración de la base de datos es un proceso **irreversible**. Al subir un archivo de respaldo:</p>
                            <ul>
                                <li>Se eliminarán todas las tablas y datos actuales de la aplicación.</li>
                                <li>Se sobrescribirá la información de todos los inquilinos (tenants).</li>
                                <li>Se recomienda encarecidamente realizar un **respaldo previo** de la base de datos actual antes de continuar.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Importar Respaldo (.sql)</h3>
                        </div>
                        <form action="restore.php" method="POST" enctype="multipart/form-data" onsubmit="return confirmarRestauracion();">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="backup_file">Selecciona el archivo de respaldo</label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="backup_file" name="backup_file" accept=".sql" required>
                                            <label class="custom-file-label" for="backup_file">Buscar archivo .sql...</label>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Asegúrate de que el archivo no esté corrupto y provenga de una fuente confiable.</small>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-between">
                                <a href="backup.php" class="btn btn-secondary">Generar Respaldo Primero</a>
                                <button type="submit" class="btn btn-dangerml-auto">Iniciar Restauración</button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </section>
</div>

<script>
// JavaScript para mostrar el nombre del archivo seleccionado en el input de Bootstrap
document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('backup_file');
    if(fileInput) {
        fileInput.addEventListener('change', function (e) {
            const fileName = e.target.files[0].name;
            const nextSibling = e.target.nextElementSibling;
            nextSibling.innerText = fileName;
        });
    }
});

// Doble confirmación nativa antes de procesar el formulario destructivo
function confirmarRestauracion() {
    const primeraConfirmacion = confirm("¿Estás absolutamente seguro de que deseas restaurar la base de datos? Esto borrará TODOS los datos actuales del sistema.");
    if (primeraConfirmacion) {
        return confirm("¡ÚLTIMO AVISO!\nEsta acción no se puede deshacer. ¿Deseas proceder con la importación?");
    }
    return false;
}
</script>

<?php include 'layouts/footer.php'; ?>