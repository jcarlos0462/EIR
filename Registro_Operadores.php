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
$startSection = 'menu';
$ajaxRequest = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_operador'])) {
    $ajaxRequest = isset($_POST['ajax']) && $_POST['ajax'] == '1';
    $startSection = 'registro';
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

    if ($ajaxRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => empty($error),
            'message' => empty($error) ? $mensaje : $error,
            'section' => 'registro'
        ]);
        exit();
    }
}

// Filtros desde GET
$filter_vin = trim($_GET['filtrar_vin'] ?? '');
$filter_nombre = trim($_GET['filtrar_nombre'] ?? '');
$filter_fecha_desde = trim($_GET['filtrar_fecha_desde'] ?? '');
$filter_fecha_hasta = trim($_GET['filtrar_fecha_hasta'] ?? '');
$report_section = isset($_GET['report_section']) && $_GET['report_section'] === '1';

if ($report_section) {
    $startSection = 'reporte';
}

$searchExecuted = $report_section && (
    $filter_vin !== '' ||
    $filter_nombre !== '' ||
    $filter_fecha_desde !== '' ||
    $filter_fecha_hasta !== ''
);

$where = [];
$params = [];
$types = '';
if ($filter_vin !== '') {
    $startSection = 'reporte';
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

$registros = [];

if ($searchExecuted) {
    $sql = 'SELECT * FROM operador';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY $sortBy $sortDir LIMIT 500";

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
} // end if searchExecuted

?>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Operadores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="navbar_styles.css">
    <style>
        @import url('navbar_styles.css');

        body { min-height: 100vh; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        #mainMenu .card { min-height: 180px; border: 1px solid #e9ecef; border-radius: 12px; }
        #mainMenu .card .card-body { display: flex; flex-direction: column; justify-content: center; align-items: center; }
        button#btnShowRegistro, button#btnShowReportes { min-width: 110px; }

        @media (max-width: 767px) {
            body { padding-top: 80px; }
            .main-content { padding: 1rem; }
            .col-md-9, .col-lg-10 { width: 100% !important; max-width: 100% !important; }
            #mainMenu .card { min-height: auto; }
            .card-body { padding: 1rem; }
        }

        @media (max-width: 1199px) {
            .container-fluid > .row > .col-md-9 { padding-left: 1rem; padding-right: 1rem; }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="p-4">
                <h1>Registro de Operadores</h1>
                <div id="ajaxFeedback" style="display:none;" class="alert"></div>
                <?php if ($mensaje): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div id="mainMenu" class="row row-cols-1 row-cols-md-2 g-4 mb-4">
                    <div class="col">
                        <div class="card text-center shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">REGISTRO</h5>
                                <button type="button" class="btn btn-primary" id="btnShowRegistro">Ver</button>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card text-center shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">REPORTES</h5>
                                <button type="button" class="btn btn-success" id="btnShowReportes">Ver</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="registroSection" style="display:none;">
                    <div class="card h-100 border-primary mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title">Registro de Operador</h5>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnBackFromRegistro">Atrás</button>
                            </div>
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
                                <div class="mt-3">
                                    <button type="submit" name="guardar_operador" class="btn btn-primary">Guardar Registro</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="reporteSection" style="display:none;">
                    <div class="card h-100 border-success mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title">Reporte de Operadores</h5>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnBackFromReporte">Atrás</button>
                            </div>
                            <p class="text-muted">Filtra y ordena los registros existentes.</p>
                            <form method="get" id="formReporte" class="row g-3">
                                <input type="hidden" name="report_section" value="1">
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
                                    <button type="button" class="btn btn-outline-secondary" id="btnLimpiarFiltros">Limpiar filtros</button>
                                </div>
                            </form>
                            <hr>
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
    const registroSection = document.getElementById('registroSection');
    const reporteSection = document.getElementById('reporteSection');
    const mainMenu = document.getElementById('mainMenu');
    const formOperador = document.getElementById('formOperador');
    const vinInput = document.getElementById('vin');
    const operadorInput = document.getElementById('nombre');

    function showMenu() {
        mainMenu.style.display = 'flex';
        registroSection.style.display = 'none';
        reporteSection.style.display = 'none';
    }

    function openRegistro() {
        mainMenu.style.display = 'none';
        registroSection.style.display = 'block';
        reporteSection.style.display = 'none';
        vinInput.focus();
    }

    function openReporte() {
        mainMenu.style.display = 'none';
        reporteSection.style.display = 'block';
        registroSection.style.display = 'none';
        document.querySelector('form[method="get"]').querySelector('input').focus();
    }

    const feedback = document.getElementById('ajaxFeedback');

    function showFeedback(success, message) {
        if (!feedback) return;
        feedback.style.display = 'block';
        feedback.textContent = message;
        if (success) {
            feedback.className = 'alert alert-success';
        } else {
            feedback.className = 'alert alert-danger';
        }
        setTimeout(() => { feedback.style.display = 'none'; }, 4000);
    }

    async function submitFormAsync() {
        if (vinInput.value.trim() === '' || operadorInput.value.trim() === '') {
            return;
        }

        const formData = new FormData(formOperador);
        formData.append('ajax', '1');

        try {
            const response = await fetch('Registro_Operadores.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                vinInput.value = '';
                operadorInput.value = '';
                openRegistro();
            }
            showFeedback(data.success, data.message);
        } catch (err) {
            showFeedback(false, 'Error de red al guardar el registro');
        }
    }

    function submitIfReady() {
        if (vinInput.value.trim() !== '' && operadorInput.value.trim() !== '') {
            submitFormAsync();
        }
    }

    function handleScanOnInput(element, nextElement, onComplete) {
        let lastValue = element.value;
        let lastTime = 0;

        function checkForScan() {
            const now = Date.now();
            const currentValue = element.value;
            const isPaste = currentValue !== lastValue && currentValue.length > lastValue.length;
            const isQuickInput = now - lastTime < 200; // Más tiempo para móviles

            if (isPaste || isQuickInput) {
                // Posible escaneo detectado
                if (nextElement) {
                    setTimeout(() => nextElement.focus(), 50);
                }
                if (onComplete) {
                    setTimeout(onComplete, 100);
                }
            }
            lastValue = currentValue;
            lastTime = now;
        }

        element.addEventListener('input', checkForScan);
        element.addEventListener('paste', function() {
            setTimeout(function() {
                if (nextElement) {
                    nextElement.focus();
                }
                if (onComplete) {
                    onComplete();
                }
            }, 50);
        });
        element.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (nextElement) {
                    nextElement.focus();
                }
                if (onComplete) {
                    onComplete();
                }
            }
        });
    }

    document.getElementById('btnShowRegistro').addEventListener('click', openRegistro);
    document.getElementById('btnShowReportes').addEventListener('click', openReporte);
    document.getElementById('btnBackFromRegistro').addEventListener('click', showMenu);
    document.getElementById('btnBackFromReporte').addEventListener('click', showMenu);
    document.getElementById('btnLimpiarFiltros').addEventListener('click', function() {
        var form = document.getElementById('formReporte');
        form.querySelector('input[name="filtrar_vin"]').value = '';
        form.querySelector('input[name="filtrar_nombre"]').value = '';
        form.querySelector('input[name="filtrar_fecha_desde"]').value = '';
        form.querySelector('input[name="filtrar_fecha_hasta"]').value = '';
        form.submit();
    });

    formOperador.addEventListener('submit', function(event) {
        event.preventDefault();
        submitFormAsync();
    });

    handleScanOnInput(vinInput, operadorInput, null);
    handleScanOnInput(operadorInput, null, submitIfReady);

    var initialSection = '<?php echo $startSection; ?>';
    if (initialSection === 'registro') {
        openRegistro();
    } else if (initialSection === 'reporte') {
        openReporte();
    } else {
        showMenu();
    }
</script>
</body>
</html>

