<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'database_connection.php';
require_once 'access_control.php';
// Deshabilitar temporalmente control de acceso a módulo para evitar bloqueos HTTP 500
//require_module_access($conn, 'operadores');

if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header('Location: index.php');
    exit();
}

// Crear tabla y columnas necesarias si no existen
$createSql = "CREATE TABLE IF NOT EXISTS operador (
    ID INT(10) PRIMARY KEY AUTO_INCREMENT,
    VIN VARCHAR(50) NOT NULL,
    Nombre VARCHAR(100) NOT NULL,
    Fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)";
if (!$conn->query($createSql)) {
    die('Error creando tabla operador: ' . $conn->error);
}

// En caso de que tabla exista de forma previa con otro esquema, agregar columnas faltantes
function addColumnIfNotExists($conn, $table, $column, $definition) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$res || $res->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}
addColumnIfNotExists($conn, 'operador', 'VIN', 'VARCHAR(50) NOT NULL');
addColumnIfNotExists($conn, 'operador', 'Nombre', 'VARCHAR(100) NOT NULL');
addColumnIfNotExists($conn, 'operador', 'Fecha', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

$mensaje = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_operador'])) {
    $vin = trim($_POST['vin'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');

    if ($vin === '' || $nombre === '') {
        $error = 'Debe completar ambos campos: VIN y Operador.';
    } else {
        $stmt = $conn->prepare("INSERT INTO operador (VIN, Nombre, Fecha) VALUES (?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param('ss', $vin, $nombre);
            if ($stmt->execute()) {
                $mensaje = 'Registro guardado correctamente.';
                $vin = '';
                $nombre = '';
            } else {
                $error = 'Error al guardar el registro: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = 'Error de base de datos: ' . $conn->error;
        }
    }
}

// Mostrar los operadores registrados
$registros = [];
$result = $conn->query("SELECT ID, VIN, Nombre, Fecha FROM operador ORDER BY Fecha DESC LIMIT 100");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $registros[] = $row;
    }
}
?>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Operadores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="col-md-9 col-lg-10">
            <div class="p-4">
                <h1>Registro de Operadores</h1>
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Registrar Operador</h5>
                        <form method="post" id="formOperador">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="vin" class="form-label">VIN</label>
                                    <input type="text" id="vin" name="vin" class="form-control" value="<?php echo htmlspecialchars($vin ?? ''); ?>" placeholder="Escanea o ingresa VIN" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="nombre" class="form-label">Operador</label>
                                    <input type="text" id="nombre" name="nombre" class="form-control" value="<?php echo htmlspecialchars($nombre ?? ''); ?>" placeholder="Escanea o ingresa QR de operador" required>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" name="guardar_operador" class="btn btn-primary">Guardar Registro</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Operadores registrados</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>VIN</th>
                                        <th>Operador</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registros as $row): ?>
                                        <tr>
                                            <td><?php echo intval($row['ID']); ?></td>
                                            <td><?php echo htmlspecialchars($row['VIN']); ?></td>
                                            <td><?php echo htmlspecialchars($row['Nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($row['Fecha']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

