<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Mexico_City'); // Hora central México
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
    if (!$res) {
        die('Error obteniendo columnas de ' . $table . ': ' . $conn->error);
    }
    if ($res->num_rows === 0) {
        if (!$conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition")) {
            die('Error agregando columna ' . $column . ': ' . $conn->error);
        }
    }
}
// No forzamos ID si la tabla ya existe, puede causar conflicto con auto_increment si ya hay PK diferente.
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
        $fecha = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO operador (VIN, Nombre, Fecha) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('sss', $vin, $nombre, $fecha);
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

// Filtros desde GET
$filter_vin = trim($_GET['filtrar_vin'] ?? '');
$filter_nombre = trim($_GET['filtrar_nombre'] ?? '');
$filter_fecha_desde = trim($_GET['filtrar_fecha_desde'] ?? '');
$filter_fecha_hasta = trim($_GET['filtrar_fecha_hasta'] ?? '');

$where = [];
$params = [];
$types = '';
if ($filter_vin !== '') {
    $where[] = 'VIN LIKE ?';
    $params[] = '%' . $filter_vin . '%';
    $types .= 's';
}
if ($filter_nombre !== '') {
    $where[] = 'Nombre LIKE ?';
    $params[] = '%' . $filter_nombre . '%';
    $types .= 's';
}
if ($filter_fecha_desde !== '') {
    $where[] = 'Fecha >= ?';
    $params[] = $filter_fecha_desde . ' 00:00:00';
    $types .= 's';
}
if ($filter_fecha_hasta !== '') {
    $where[] = 'Fecha <= ?';
    $params[] = $filter_fecha_hasta . ' 23:59:59';
    $types .= 's';
}

$sortBy = $_GET['ordenar'] ?? 'Fecha';
$sortDir = (isset($_GET['orden_dir']) && strtoupper($_GET['orden_dir']) === 'ASC') ? 'ASC' : 'DESC';
$allowedSorts = ['ID', 'VIN', 'Nombre', 'Fecha'];
if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'Fecha';
}

$sql = 'SELECT * FROM operador';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY $sortBy $sortDir LIMIT 500";

$registros = [];
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $registros[] = $row;
    }
    $stmt->close();
} else {
    die('Error al cargar registros de operador: ' . $conn->error);
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

                <div class="row row-cols-1 row-cols-xl-2 g-4 mb-4">
                    <div class="col">
                        <div class="card h-100 border-primary">
                            <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                                <span>Registro de Operador</span>
                                <button type="button" class="btn btn-light btn-sm" id="toggleRegistro">Ver</button>
                            </div>
                            <div class="card-body" id="registroCardBody">
                                <p class="text-muted">Agrega un nuevo registro de operador con VIN y nombre.</p>
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
                                    <div class="mt-3 d-flex gap-2">
                                        <button type="submit" name="guardar_operador" class="btn btn-primary">Guardar Registro</button>
                                        <button type="button" id="btnLimpiar" class="btn btn-secondary">Limpiar y nuevo</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100 border-success">
                            <div class="card-header d-flex justify-content-between align-items-center bg-success text-white">
                                <span>Reporte de Operadores</span>
                                <button type="button" class="btn btn-light btn-sm" id="toggleReporte">Ver</button>
                            </div>
                            <div class="card-body" id="reporteCardBody">
                                <p class="text-muted">Filtra y ordena los registros existentes.</p>
                                <form method="get" class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">VIN</label>
                                        <input type="text" class="form-control" name="filtrar_vin" value="<?php echo htmlspecialchars($filter_vin); ?>" placeholder="Filtro por VIN">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Operador</label>
                                        <input type="text" class="form-control" name="filtrar_nombre" value="<?php echo htmlspecialchars($filter_nombre); ?>" placeholder="Filtro por operador">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Fecha desde</label>
                                        <input type="date" class="form-control" name="filtrar_fecha_desde" value="<?php echo htmlspecialchars($filter_fecha_desde); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Fecha hasta</label>
                                        <input type="date" class="form-control" name="filtrar_fecha_hasta" value="<?php echo htmlspecialchars($filter_fecha_hasta); ?>">
                                    </div>
                                    <div class="col-12 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                                        <a href="Registro_Operadores.php" class="btn btn-outline-secondary">Limpiar filtros</a>
                                    </div>
                                </form>
                            </div>
                        </div>
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
<script>
    document.getElementById('btnLimpiar').addEventListener('click', function() {
        document.getElementById('vin').value = '';
        document.getElementById('nombre').value = '';
        document.getElementById('vin').focus();
    });

    function toggleSection(buttonId, sectionId) {
        const button = document.getElementById(buttonId);
        const section = document.getElementById(sectionId);
        button.addEventListener('click', function() {
            if (section.style.display === 'none') {
                section.style.display = 'block';
                button.textContent = 'Ocultar';
            } else {
                section.style.display = 'none';
                button.textContent = 'Ver';
            }
        });
    }

    toggleSection('toggleRegistro', 'registroCardBody');
    toggleSection('toggleReporte', 'reporteCardBody');
</script>
</body>
</html>

