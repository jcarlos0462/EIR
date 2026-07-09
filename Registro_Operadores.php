<?php
ob_start(); // Inicia output buffering para limpiar cualquier salida accidental
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Mexico_City');
session_start();

try {
    include 'database_connection.php';
    require_once 'access_control.php';
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al conectar base de datos: ' . $e->getMessage()]);
    exit();
}
// Deshabilitar temporalmente control de acceso a módulo para evitar bloqueos HTTP 500
require_module_access($conn, 'operadores');

if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header('Location: index.php');
    exit();
}

$current_user_id = intval($_SESSION['id'] ?? 0);
$is_read_only = user_has_role_name($conn, $current_user_id, 'lector');
$can_write_operadores = $current_user_id > 0 && can_user_write_module($conn, $current_user_id, 'operadores');

// Crear tabla y columnas necesarias si no existen
$createSql = "CREATE TABLE IF NOT EXISTS operador (
    ID INT(10) PRIMARY KEY AUTO_INCREMENT,
    VIN VARCHAR(50) NOT NULL,
    Nombre VARCHAR(100) NOT NULL,
    operacion VARCHAR(100) DEFAULT '',
    Fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    puerto VARCHAR(100) DEFAULT ''
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
addColumnIfNotExists($conn, 'operador', 'operacion', "VARCHAR(100) DEFAULT ''");
addColumnIfNotExists($conn, 'operador', 'Fecha', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
addColumnIfNotExists($conn, 'operador', 'puerto', "VARCHAR(100) DEFAULT ''");

$mensaje = '';
$error = '';
$startSection = 'menu';
$ajaxRequest = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_operador'])) {
    $ajaxRequest = isset($_POST['ajax']) && $_POST['ajax'] == '1';
    $startSection = 'registro';
    $vin = trim($_POST['vin'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    // Tomar valores desde la sesión (mostrados en la navbar)
    $tipo_operacion = trim($_SESSION['tipo_operacion'] ?? '');
    $puerto_sesion = trim($_SESSION['puerto'] ?? '');

    if (!$can_write_operadores) {
        $error = 'Tu rol es solo lectura. No puedes guardar registros.';
    } elseif ($vin === '' || $nombre === '') {
        $error = 'Debe completar ambos campos: VIN y Operador.';
    } else {
        $fecha = date('Y-m-d H:i:s');
        $nombre_a_guardar = $nombre;
        $operacion_a_guardar = $tipo_operacion;

        $stmt = $conn->prepare("INSERT INTO operador (VIN, Nombre, operacion, Fecha, puerto) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            $error = 'Error al preparar consulta: ' . $conn->error;
        } else {
            $stmt->bind_param('sssss', $vin, $nombre_a_guardar, $operacion_a_guardar, $fecha, $puerto_sesion);
            if ($stmt->execute()) {
                $mensaje = 'Registro guardado correctamente.';
                $vin = '';
                $nombre = '';
            } else {
                $error = 'Error al guardar el registro: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    if ($ajaxRequest) {
        ob_clean(); // Limpia cualquier salida accidental
        header('Content-Type: application/json');
        http_response_code(200);
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
$filter_operacion = trim($_GET['filtrar_operacion'] ?? '');
$filter_puerto = trim($_GET['filtrar_puerto'] ?? '');
$filter_fecha_desde = trim($_GET['filtrar_fecha_desde'] ?? '');
$filter_fecha_hasta = trim($_GET['filtrar_fecha_hasta'] ?? '');
$report_section = isset($_GET['report_section']) && $_GET['report_section'] === '1';
$page = max(1, intval($_GET['page'] ?? 1));
$rowsPerPage = 50;

if ($report_section) {
    $startSection = 'reporte';
}

$searchExecuted = $report_section;

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
if ($filter_operacion !== '') {
    $where[] = 'operacion = ?';
    $params[] = $filter_operacion;
    $types .= 's';
}
if ($filter_puerto !== '') {
    $where[] = 'puerto = ?';
    $params[] = $filter_puerto;
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
$allowedSorts = ['ID', 'VIN', 'Nombre', 'operacion', 'puerto', 'Fecha'];
if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'Fecha';
}
$exportExcel = isset($_GET['export_excel']) && $_GET['export_excel'] === '1';

$registros = [];
$conteoOperadores = [];
$totalRegistros = 0;
$totalPages = 1;

if ($searchExecuted) {
    $countSql = 'SELECT COUNT(*) AS total FROM operador';
    if (!empty($where)) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }

    if ($countStmt = $conn->prepare($countSql)) {
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalRegistros = intval($countResult->fetch_assoc()['total'] ?? 0);
        $countStmt->close();
    }

    $sql = 'SELECT ID, VIN, Nombre, operacion, puerto, Fecha FROM operador';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY $sortBy $sortDir";
    if (!$exportExcel) {
        $offset = ($page - 1) * $rowsPerPage;
        $sql .= " LIMIT $rowsPerPage OFFSET $offset";
        $totalPages = max(1, intval(ceil($totalRegistros / $rowsPerPage)));
    }

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

    $sqlConteo = 'SELECT Nombre, COUNT(*) AS total_movimientos FROM operador';
    if (!empty($where)) {
        $sqlConteo .= ' WHERE ' . implode(' AND ', $where);
    }
    $sqlConteo .= ' GROUP BY Nombre ORDER BY total_movimientos DESC, Nombre ASC';

    if ($stmtConteo = $conn->prepare($sqlConteo)) {
        if (!empty($params)) {
            $stmtConteo->bind_param($types, ...$params);
        }
        $stmtConteo->execute();
        $resultConteo = $stmtConteo->get_result();
        while ($rowConteo = $resultConteo->fetch_assoc()) {
            $conteoOperadores[] = $rowConteo;
        }
        $stmtConteo->close();
    } else {
        die('Error al cargar conteo por operador: ' . $conn->error);
    }
} // end if searchExecuted

if ($exportExcel) {
    ob_clean();
    $baseName = 'reporte_operadores_' . date('Ymd_His');

    if (!class_exists('ZipArchive')) {
        // Fallback si el servidor no soporta crear XLSX.
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['ID', 'VIN', 'Operador', 'Operacion', 'Puerto', 'Fecha']);
        foreach ($registros as $row) {
            fputcsv($output, [
                $row['ID'] ?? '',
                $row['VIN'] ?? '',
                $row['Nombre'] ?? '',
                $row['operacion'] ?? '',
                $row['puerto'] ?? '',
                $row['Fecha'] ?? ''
            ]);
        }
        fclose($output);
        exit();
    }

    $rows = [];
    $rows[] = ['ID', 'VIN', 'Operador', 'Operacion', 'Puerto', 'Fecha'];
    foreach ($registros as $row) {
        $rows[] = [
            (string)($row['ID'] ?? ''),
            (string)($row['VIN'] ?? ''),
            (string)($row['Nombre'] ?? ''),
            (string)($row['operacion'] ?? ''),
            (string)($row['puerto'] ?? ''),
            (string)($row['Fecha'] ?? '')
        ];
    }

    $cellRef = function($colIndex, $rowIndex) {
        $col = '';
        $n = $colIndex;
        while ($n > 0) {
            $mod = ($n - 1) % 26;
            $col = chr(65 + $mod) . $col;
            $n = intval(($n - $mod) / 26);
        }
        return $col . $rowIndex;
    };

    $sheetRowsXml = '';
    $rIndex = 1;
    foreach ($rows as $rowData) {
        $sheetRowsXml .= '<row r="' . $rIndex . '">';
        $cIndex = 1;
        foreach ($rowData as $cellValue) {
            $ref = $cellRef($cIndex, $rIndex);
            $escaped = htmlspecialchars($cellValue, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $sheetRowsXml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
            $cIndex++;
        }
        $sheetRowsXml .= '</row>';
        $rIndex++;
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $sheetRowsXml . '</sheetData>'
        . '</worksheet>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';

    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';

    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo 'No se pudo generar el archivo XLSX.';
        exit();
    }

    $zip->addFromString('[Content_Types].xml', $contentTypesXml);
    $zip->addFromString('_rels/.rels', $relsXml);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $baseName . '.xlsx"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    @unlink($tmpFile);
    exit();
}

?>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Operadores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="navbar_styles.css">
    <style>

        body { min-height: 100vh; }
        .main-content { background: #f8f9fa; min-height: 100vh; }
        #mainMenu .card { min-height: 180px; border: 1px solid #e9ecef; border-radius: 12px; }
        #mainMenu .card .card-body { display: flex; flex-direction: column; justify-content: center; align-items: center; }
        button#btnShowRegistro, button#btnShowReportes { min-width: 110px; }
        .scan-hint { color: #5b6b8a; font-size: 0.92rem; font-weight: 500; }

        @media (max-width: 767px) {
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
                <?php if (!$can_write_operadores): ?>
                    <div class="alert alert-warning">Modo solo lectura: puedes consultar y filtrar reportes, pero no guardar registros.</div>
                <?php endif; ?>
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
                                        <input type="text" id="vin" name="vin" class="form-control" value="<?php echo htmlspecialchars($vin ?? ''); ?>" placeholder="Escanea o ingresa VIN" <?php echo $can_write_operadores ? 'required' : 'disabled'; ?> inputmode="none" autocomplete="off" autocapitalize="off" spellcheck="false">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nombre" class="form-label">Operador</label>
                                        <input type="text" id="nombre" name="nombre" class="form-control" value="<?php echo htmlspecialchars($nombre ?? ''); ?>" placeholder="Escanea o ingresa QR de operador" <?php echo $can_write_operadores ? 'required' : 'disabled'; ?> inputmode="none" autocomplete="off" autocapitalize="off" spellcheck="false">
                                    </div>
                                    <div class="col-12">
                                        <div class="scan-hint">Escanea VIN y luego operador. En móvil el teclado no debe abrirse automáticamente.</div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" name="guardar_operador" class="btn btn-primary" <?php echo $can_write_operadores ? '' : 'disabled'; ?>>Guardar Registro</button>
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
                                <input type="hidden" name="page" value="<?php echo intval($page); ?>">
                                <div class="col-md-6">
                                    <label class="form-label">VIN</label>
                                    <input type="text" class="form-control" name="filtrar_vin" value="<?php echo htmlspecialchars($filter_vin); ?>" placeholder="Filtro por VIN">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Operador</label>
                                    <input type="text" class="form-control" name="filtrar_nombre" value="<?php echo htmlspecialchars($filter_nombre); ?>" placeholder="Filtro por operador">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tipo de operación</label>
                                    <select class="form-select" name="filtrar_operacion">
                                        <option value="">Todos</option>
                                        <option value="Descarga Buque" <?php echo $filter_operacion === 'Descarga Buque' ? 'selected' : ''; ?>>Descarga Buque</option>
                                        <option value="Carga Buque" <?php echo $filter_operacion === 'Carga Buque' ? 'selected' : ''; ?>>Carga Buque</option>
                                        <option value="Descarga FFCC" <?php echo $filter_operacion === 'Descarga FFCC' ? 'selected' : ''; ?>>Descarga FFCC</option>
                                        <option value="Carga FFCC" <?php echo $filter_operacion === 'Carga FFCC' ? 'selected' : ''; ?>>Carga FFCC</option>
                                        <option value="Salida GATE" <?php echo $filter_operacion === 'Salida GATE' ? 'selected' : ''; ?>>Salida GATE</option>
                                        <option value="Ingreso GATE" <?php echo $filter_operacion === 'Ingreso GATE' ? 'selected' : ''; ?>>Ingreso GATE</option>
                                        <option value="Almacenaje - Patio" <?php echo $filter_operacion === 'Almacenaje - Patio' ? 'selected' : ''; ?>>Almacenaje - Patio</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Puerto</label>
                                    <select class="form-select" name="filtrar_puerto">
                                        <option value="">Todos</option>
                                        <option value="COA" <?php echo $filter_puerto === 'COA' ? 'selected' : ''; ?>>COA</option>
                                        <option value="SCX" <?php echo $filter_puerto === 'SCX' ? 'selected' : ''; ?>>SCX</option>
                                        <option value="PA" <?php echo $filter_puerto === 'PA' ? 'selected' : ''; ?>>PA</option>
                                    </select>
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
                                    <button type="submit" class="btn btn-success" name="export_excel" value="1">Exportar Excel</button>
                                </div>
                            </form>
                            <hr>
                            <?php if ($searchExecuted): ?>
                                <div class="mb-4">
                                    <h6 class="mb-3">Conteo de movimientos por operador</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Operador</th>
                                                    <th>Total movimientos</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($conteoOperadores)): ?>
                                                    <?php foreach ($conteoOperadores as $item): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($item['Nombre']); ?></td>
                                                            <td><?php echo intval($item['total_movimientos']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="2" class="text-center text-muted">Sin datos para mostrar</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($report_section && !$exportExcel): ?>
                                <div class="mb-3">
                                    <small class="text-muted">
                                        Mostrando <?php echo $totalRegistros > 0 ? (($page - 1) * $rowsPerPage + 1) : 0; ?> - <?php echo min($totalRegistros, $page * $rowsPerPage); ?> de <?php echo $totalRegistros; ?> registros
                                    </small>
                                </div>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>VIN</th>
                                            <th>Operador</th>
                                            <th>Operacion</th>
                                            <th>Puerto</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registros as $row): ?>
                                        <tr>
                                            <td><?php echo intval($row['ID']); ?></td>
                                            <td><?php echo htmlspecialchars($row['VIN']); ?></td>
                                            <td><?php echo htmlspecialchars($row['Nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($row['operacion'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['puerto'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['Fecha']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($report_section && !$exportExcel && $totalPages > 1): ?>
                            <div class="overflow-auto mt-3">
                                <nav aria-label="Paginación de reportes">
                                    <ul class="pagination pagination-sm justify-content-center mb-0" style="white-space: nowrap;">
                                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?report_section=1&page=<?php echo $p; ?><?php echo $filter_vin !== '' ? '&filtrar_vin=' . urlencode($filter_vin) : ''; ?><?php echo $filter_nombre !== '' ? '&filtrar_nombre=' . urlencode($filter_nombre) : ''; ?><?php echo $filter_operacion !== '' ? '&filtrar_operacion=' . urlencode($filter_operacion) : ''; ?><?php echo $filter_puerto !== '' ? '&filtrar_puerto=' . urlencode($filter_puerto) : ''; ?><?php echo $filter_fecha_desde !== '' ? '&filtrar_fecha_desde=' . urlencode($filter_fecha_desde) : ''; ?><?php echo $filter_fecha_hasta !== '' ? '&filtrar_fecha_hasta=' . urlencode($filter_fecha_hasta) : ''; ?><?php echo isset($_GET['ordenar']) ? '&ordenar=' . urlencode($_GET['ordenar']) : ''; ?><?php echo isset($_GET['orden_dir']) ? '&orden_dir=' . urlencode($_GET['orden_dir']) : ''; ?>"><?php echo $p; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
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
    const formReporte = document.getElementById('formReporte');
    const vinInput = document.getElementById('vin');
    const operadorInput = document.getElementById('nombre');
    const isMobileViewport = window.matchMedia('(max-width: 768px)').matches || window.matchMedia('(pointer: coarse)').matches;
    let isSubmitting = false;
    let pendingSubmit = false;

    function focusField(element) {
        if (!element) return;
        element.focus({ preventScroll: true });
        try {
            element.setSelectionRange(element.value.length, element.value.length);
        } catch (error) {
        }
    }

    function showMenu() {
        mainMenu.style.display = 'flex';
        registroSection.style.display = 'none';
        reporteSection.style.display = 'none';
    }

    function openRegistro() {
        mainMenu.style.display = 'none';
        registroSection.style.display = 'block';
        reporteSection.style.display = 'none';
        focusField(vinInput);
    }

    function openReporte() {
        if (!window.location.search.includes('report_section=1')) {
            const params = new URLSearchParams(window.location.search);
            params.set('report_section', '1');
            window.location.search = params.toString();
            return;
        }
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
        if (!<?php echo $can_write_operadores ? 'true' : 'false'; ?>) {
            showFeedback(false, 'Tu rol es solo lectura. No puedes guardar registros.');
            return;
        }

        if (vinInput.value.trim() === '' || operadorInput.value.trim() === '') {
            return;
        }

        if (isSubmitting) {
            pendingSubmit = true;
            return;
        }

        isSubmitting = true;

        const formData = new FormData(formOperador);
        formData.append('ajax', '1');
        formData.append('guardar_operador', '1');

        try {
            const response = await fetch('Registro_Operadores.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }

            const text = await response.text();
            console.log('Respuesta del servidor:', text.substring(0, 500));
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (jsonError) {
                console.error('Error al parsear JSON:', jsonError);
                console.error('Respuesta recibida:', text);
                showFeedback(false, 'Error del servidor: respuesta no válida');
                return;
            }

            if (data.success) {
                vinInput.value = '';
                operadorInput.value = '';
                vinInput.dataset.lastProcessed = '';
                operadorInput.dataset.lastProcessed = '';
                openRegistro();
            }
            showFeedback(data.success, data.message);
        } catch (err) {
            console.error('submitFormAsync error completo:', err);
            console.error('Stack:', err.stack);
            showFeedback(false, 'Error de red: ' + err.message);
        } finally {
            isSubmitting = false;
            if (pendingSubmit) {
                pendingSubmit = false;
                submitIfReady();
            }
        }
    }

    function submitIfReady() {
        if (vinInput.value.trim() !== '' && operadorInput.value.trim() !== '') {
            submitFormAsync();
        }
    }

    function handleScanOnInput(element, nextElement, onComplete, minLength) {
        let lastTime = 0;
        const threshold = typeof minLength === 'number' ? minLength : 1;
        element.dataset.lastProcessed = element.dataset.lastProcessed || '';

        function checkForScan() {
            const now = Date.now();
            const currentValue = element.value;
            const currentTrim = currentValue.trim();
            const isPaste = currentValue.length > 0;
            const isQuickInput = now - lastTime < 200; // Más tiempo para móviles
            const hasEnoughLength = currentTrim.length >= threshold;
            const alreadyProcessed = currentTrim !== '' && currentTrim === element.dataset.lastProcessed;

            if (!alreadyProcessed && currentTrim !== '' && (isPaste || isQuickInput || hasEnoughLength)) {
                element.dataset.lastProcessed = currentTrim;
                // Posible escaneo detectado
                if (nextElement) {
                    setTimeout(() => focusField(nextElement), 50);
                }
                if (onComplete) {
                    setTimeout(onComplete, 100);
                }
            }

            if (currentTrim === '') {
                element.dataset.lastProcessed = '';
            }
            lastTime = now;
        }

        element.addEventListener('input', checkForScan);
        element.addEventListener('paste', function() {
            setTimeout(function() {
                if (nextElement) {
                    focusField(nextElement);
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
                    focusField(nextElement);
                }
                if (onComplete) {
                    onComplete();
                }
            }
        });

        if (isMobileViewport) {
            element.addEventListener('touchstart', function() {
                focusField(element);
            }, { passive: true });
        }

        element.addEventListener('blur', function() {
            if (element.value.trim() === '') {
                element.dataset.lastProcessed = '';
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
        form.querySelector('select[name="filtrar_operacion"]').value = '';
        form.querySelector('select[name="filtrar_puerto"]').value = '';
        form.querySelector('input[name="filtrar_fecha_desde"]').value = '';
        form.querySelector('input[name="filtrar_fecha_hasta"]').value = '';
        form.querySelector('input[name="page"]').value = '1';
        form.submit();
    });

    if (formReporte) {
        formReporte.addEventListener('submit', function() {
            var pageInput = formReporte.querySelector('input[name="page"]');
            if (pageInput) {
                pageInput.value = '1';
            }
        });
    }

    formOperador.addEventListener('submit', function(event) {
        event.preventDefault();
        submitFormAsync();
    });

    handleScanOnInput(vinInput, operadorInput, null, 17);
    handleScanOnInput(operadorInput, null, submitIfReady, 2);

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

