<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header("Location: index.html");
    exit();
}

// Conexión a la base de datos
$servername = "localhost";
$username = "u174025152_Administrador";
$password = "0066jv_A2";
$dbname = "u174025152_EIR";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

require_once 'access_control.php';
require_module_access($conn, 'vehiculos');

// Obtener todos los vehículos
$sql = "SELECT * FROM vehiculo ORDER BY ID DESC";
$result = $conn->query($sql);

// Helper: parse simple XLSX (sheet1) into array of rows (supports sharedStrings)
function parse_xlsx_simple($file) {
    $zip = new ZipArchive();
    $res = $zip->open($file);
    if ($res !== true) return false;
    // read shared strings
    $shared = [];
    if (($idx = $zip->locateName('xl/sharedStrings.xml')) !== false) {
        $sxml = simplexml_load_string($zip->getFromIndex($idx));
        $sxml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($sxml->si as $si) {
            if (isset($si->t)) $shared[] = (string)$si->t;
            else {
                $text = '';
                foreach ($si->r as $r) $text .= (string)$r->t;
                $shared[] = $text;
            }
        }
    }
    // read sheet1
    $rows = [];
    if (($idx = $zip->locateName('xl/worksheets/sheet1.xml')) !== false) {
        $sheet = simplexml_load_string($zip->getFromIndex($idx));
        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($sheet->sheetData->row as $r) {
            $row = [];
            foreach ($r->c as $c) {
                $v = '';
                $t = (string)$c['t'];
                if ($t === 's') {
                    $idxs = (int)$c->v;
                    $v = isset($shared[$idxs]) ? (string)$shared[$idxs] : '';
                } else {
                    if (isset($c->v)) $v = (string)$c->v;
                    else $v = '';
                }
                $row[] = $v;
            }
            $rows[] = $row;
        }
    }
    $zip->close();
    return $rows;
}
// Procesar importación de CSV/XLSX (exportado desde Excel)
$import_summary = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_vehiculos'])) {
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $import_summary = ['error' => 'No se seleccionó archivo o hubo un error al subirlo.'];
    } else {
        $tmp = $_FILES['import_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));

        // columnas esperadas (claves a mapear)
        $expected = [
            'terminal' => null,
            'buque' => null,
            'viaje' => null,
            'vin' => null,
            'marca' => null,
            'modelo' => null,
            'color' => null,
            'año' => null,
            'ano' => null,
            'puerto' => null
        ];

        $cols = [];
        $dataRows = [];

        if ($ext === 'xlsx') {
            $rows = parse_xlsx_simple($tmp);
            if ($rows === false || count($rows) === 0) {
                $import_summary = ['error' => 'No se pudo parsear el archivo XLSX o está vacío.'];
                $cols = array_map(function($c){ return mb_strtolower(trim($c)); }, $rows[0]);
                $dataRows = array_slice($rows, 1);
            }
        } else {
            // CSV
            $firstLine = file($tmp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$firstLine) {
                $import_summary = ['error' => 'Archivo vacío o no legible.'];
            } else {
                $sample = $firstLine[0];
                $delimiter = (substr_count($sample, ',') >= substr_count($sample, ';')) ? ',' : ';';
                $handle = fopen($tmp, 'r');
                if ($handle === false) {
                    $import_summary = ['error' => 'No se pudo abrir el archivo.'];
                } else {
                    $header = fgetcsv($handle, 0, $delimiter);
                    if (!$header) {
                        $import_summary = ['error' => 'No se pudo leer cabecera del archivo.'];
                        fclose($handle);
                    } else {
                        $cols = array_map(function($c){ return mb_strtolower(trim($c)); }, $header);
                        // dejar $handle abierto para lectura secuencial más abajo
                    }
                }
            }
        }

        // si tenemos columnas, mapear y procesar
        if (!empty($cols)) {
            foreach ($cols as $i => $name) {
                if (array_key_exists($name, $expected)) $expected[$name] = $i;
            }

            $rowCount = 0; $inserted = 0; $skipped = 0; $errors = []; $duplicates = [];
            $sql_insert = "INSERT INTO vehiculo (Buque, Viaje, VIN, Marca, Modelo, Color, Año, Puerto, Terminal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            if (!$stmt_insert) {
                $import_summary = ['error' => 'Error preparando la inserción: '.$conn->error];
                if (isset($handle) && is_resource($handle)) fclose($handle);
            } else {
                if ($ext === 'xlsx') {
                    foreach ($dataRows as $data) {
                        $rowCount++;
                        $allEmpty = true; foreach ($data as $v) if (trim($v) !== '') { $allEmpty = false; break; }
                        if ($allEmpty) continue;
                        $get = function($keys) use ($data, $expected) {
                            foreach ((array)$keys as $k) {
                                if (isset($expected[$k]) && $expected[$k] !== null && isset($data[$expected[$k]])) return trim($data[$expected[$k]]);
                            }
                            return '';
                        };
                        $terminal = $get('terminal');
                        $buque = $get('buque');
                        $viaje = $get('viaje');
                        $vin = $get('vin');
                        $marca = $get('marca');
                        $modelo = $get('modelo');
                        $color = $get('color');
                        $ano = $get(['año','ano']);
                        $puerto = $get('puerto');
                        if ($vin === '') { $skipped++; $errors[] = "Fila {$rowCount}: VIN vacío"; continue; }
                        $stmt_check = $conn->prepare("SELECT ID FROM vehiculo WHERE VIN = ? LIMIT 1");
                        $stmt_check->bind_param('s', $vin);
                        $stmt_check->execute();
                        $resc = $stmt_check->get_result();
                        if ($resc && $resc->num_rows > 0) { $skipped++; $duplicates[] = "Fila {$rowCount}: {$vin}"; $stmt_check->close(); continue; }
                        $stmt_check->close();
                        $stmt_insert->bind_param('sssssssss', $buque, $viaje, $vin, $marca, $modelo, $color, $ano, $puerto, $terminal);
                        if ($stmt_insert->execute()) { $inserted++; } else { $errors[] = "Fila {$rowCount}: error insertando VIN {$vin} - " . $stmt_insert->error; }
                    }
                } else {
                    // CSV - continuar leyendo desde el handle abierto
                    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                        $rowCount++;
                        $allEmpty = true; foreach ($data as $v) if (trim($v) !== '') { $allEmpty = false; break; }
                        if ($allEmpty) continue;
                        $get = function($keys) use ($data, $expected) {
                            foreach ((array)$keys as $k) {
                                if (isset($expected[$k]) && $expected[$k] !== null && isset($data[$expected[$k]])) return trim($data[$expected[$k]]);
                            }
                            return '';
                        };
                        $terminal = $get('terminal');
                        $buque = $get('buque');
                        $viaje = $get('viaje');
                        $vin = $get('vin');
                        $marca = $get('marca');
                        $modelo = $get('modelo');
                        $color = $get('color');
                        $ano = $get(['año','ano']);
                        $puerto = $get('puerto');
                        if ($vin === '') { $skipped++; $errors[] = "Fila {$rowCount}: VIN vacío"; continue; }
                        $stmt_check = $conn->prepare("SELECT ID FROM vehiculo WHERE VIN = ? LIMIT 1");
                        $stmt_check->bind_param('s', $vin);
                        $stmt_check->execute();
                        $resc = $stmt_check->get_result();
                        if ($resc && $resc->num_rows > 0) { $skipped++; $duplicates[] = "Fila {$rowCount}: {$vin}"; $stmt_check->close(); continue; }
                        $stmt_check->close();
                        $stmt_insert->bind_param('sssssssss', $buque, $viaje, $vin, $marca, $modelo, $color, $ano, $puerto, $terminal);
                        if ($stmt_insert->execute()) { $inserted++; } else { $errors[] = "Fila {$rowCount}: error insertando VIN {$vin} - " . $stmt_insert->error; }
                    }
                }

                $stmt_insert->close();
                if (isset($handle) && is_resource($handle)) fclose($handle);
                $import_summary = [
                    'rows' => $rowCount,
                    'inserted' => $inserted,
                    'skipped' => $skipped,
                    'errors' => $errors,
                    'duplicates' => $duplicates
                ];
            }
        }
    }
}

// Si se realizó una importación, volver a consultar los vehículos para mostrar los nuevos registros
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_vehiculos'])) {
    $sql = "SELECT * FROM vehiculo ORDER BY ID DESC";
    $result = $conn->query($sql);
}

// Procesar actualización si se envía
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id'])) {
    $id = $_POST['id'];
    $buque = trim($_POST['buque']);
    $viaje = trim($_POST['viaje']);
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $color = trim($_POST['color']);
    $ano = trim($_POST['ano']);
    $puerto = trim($_POST['puerto']);
    $terminal = trim($_POST['terminal']);
    
    $sql_update = "UPDATE vehiculo SET Buque=?, Viaje=?, Marca=?, Modelo=?, Color=?, Año=?, Puerto=?, Terminal=? WHERE ID=?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("ssssssssi", $buque, $viaje, $marca, $modelo, $color, $ano, $puerto, $terminal, $id);
    
    if ($stmt->execute()) {
        header("Location: listar_vehiculos.php?exito=1");
        exit();
    }
    $stmt->close();
}

// Procesar eliminación si se solicita
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $sql_delete = "DELETE FROM vehiculo WHERE ID = ?";
    $stmt = $conn->prepare($sql_delete);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: listar_vehiculos.php?exito=2");
        exit();
    }
    $stmt->close();
}

// Modo edición
$edit_mode = false;
$vehiculo_edit = null;
if (isset($_GET['editar'])) {
    $edit_mode = true;
    $id_edit = $_GET['editar'];
    $sql_edit = "SELECT * FROM vehiculo WHERE ID = ?";
    $stmt = $conn->prepare($sql_edit);
    $stmt->bind_param("i", $id_edit);
    $stmt->execute();
    $vehiculo_edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Vehículos - EIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="navbar_styles.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .main-content {
            padding: 30px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .user-info {
            color: white;
            font-size: 14px;
        }
        .table {
            background: white;
        }
        .btn-edit {
            background-color: #667eea;
            color: white;
            border: none;
        }
        .btn-edit:hover {
            background-color: #764ba2;
            color: white;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Lista de Vehículos</h2>
                    <div class="d-flex gap-2 align-items-center">
                        <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                            <input type="file" name="import_file" accept=".csv,.xlsx" class="form-control form-control-sm">
                            <button type="submit" name="import_vehiculos" class="btn btn-success btn-sm">Importar archivo</button>
                        </form>
                    </div>
                </div>
                <?php if ($import_summary !== null): ?>
                    <div class="alert alert-info">
                        <strong>Importación:</strong>
                        <?php if (isset($import_summary['error'])): ?>
                            <div><?php echo htmlspecialchars($import_summary['error']); ?></div>
                        <?php else: ?>
                            <div>Filas leídas: <?php echo $import_summary['rows']; ?></div>
                            <div>Insertados: <?php echo $import_summary['inserted']; ?></div>
                            <div>Omitidos (duplicados/errores): <?php echo $import_summary['skipped']; ?></div>
                            <?php if (!empty($import_summary['errors'])): ?>
                                <div class="mt-2">Errores:</div>
                                <ul>
                                <?php foreach ($import_summary['errors'] as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (!empty($import_summary['duplicates'])): ?>
                                <div class="mt-2 alert alert-warning p-2">Duplicados omitidos:
                                    <ul class="mb-0">
                                        <?php foreach ($import_summary['duplicates'] as $d): ?><li><?php echo htmlspecialchars($d); ?></li><?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['exito'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_GET['exito'] == 2 ? 'Vehículo eliminado exitosamente' : 'Vehículo actualizado exitosamente'; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>                        
                                <th>Buque</th>
                                <th>Viaje</th>
                                <th>VIN</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Año</th>
                                <th>Color</th>
                                <th>Puerto</th>
                                <th>Terminal</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr >                                      
                                        <td><?php echo htmlspecialchars($row['Buque']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Viaje']); ?></td>
                                        <td><?php echo htmlspecialchars($row['VIN']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Marca']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Modelo']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Año']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Color']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Puerto']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Terminal']); ?></td>
                                        <td>
                                            <a href="?editar=<?php echo $row['ID']; ?>" class="btn btn-sm btn-edit">Editar</a>
                                            <a href="?eliminar=<?php echo $row['ID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de que desea eliminar este vehículo?')">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">No hay vehículos registrados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Editar -->
    <?php if ($edit_mode && $vehiculo_edit): ?>
        <div class="modal fade show" id="editModal" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Vehículo</h5>
                        <a href="listar_vehiculos.php" class="btn-close"></a>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="">
                            <input type="hidden" name="id" value="<?php echo $vehiculo_edit['ID']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="buque" class="form-label">Buque</label>
                                    <input type="text" class="form-control" name="buque" value="<?php echo htmlspecialchars($vehiculo_edit['Buque']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="viaje" class="form-label">Viaje</label>
                                    <input type="text" class="form-control" name="viaje" value="<?php echo htmlspecialchars($vehiculo_edit['Viaje']); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Marca</label>
                                    <input type="text" class="form-control" name="marca" value="<?php echo htmlspecialchars($vehiculo_edit['Marca']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Modelo</label>
                                    <input type="text" class="form-control" name="modelo" value="<?php echo htmlspecialchars($vehiculo_edit['Modelo']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Año</label>
                                    <input type="text" class="form-control" name="ano" value="<?php echo htmlspecialchars($vehiculo_edit['Año']); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Color</label>
                                    <input type="text" class="form-control" name="color" value="<?php echo htmlspecialchars($vehiculo_edit['Color']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Puerto</label>
                                    <input type="text" class="form-control" name="puerto" value="<?php echo htmlspecialchars($vehiculo_edit['Puerto']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Terminal</label>
                                    <input type="text" class="form-control" name="terminal" value="<?php echo htmlspecialchars($vehiculo_edit['Terminal']); ?>">
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                                    <a href="listar_vehiculos.php" class="btn btn-secondary ms-2">Cancelar</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
