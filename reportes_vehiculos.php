<?php
session_start();
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header("Location: index.html");
    exit();
}

// Enable errors for debugging (remove or disable on production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB connection (same credentials used elsewhere)
$servername = "localhost";
$username = "u174025152_Administrador";
$password = "0066jv_A2";
$dbname = "u174025152_EIR";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Conexión fallida: " . $conn->connect_error);

// Fetch filter lists
$buques = [];
$res = $conn->query("SELECT DISTINCT Buque FROM vehiculo WHERE IFNULL(Buque,'')<>'' ORDER BY Buque");
if ($res) { while ($r = $res->fetch_assoc()) $buques[] = $r['Buque']; }

$areas = [];
$res = $conn->query("SELECT CodAreaDano, NomAreaDano FROM areadano ORDER BY NomAreaDano");
if ($res) { while ($r = $res->fetch_assoc()) $areas[] = $r; }

// Origen values from RegistroDanio if available
$origenes = [];
// table RegistroDanio does not have column 'Origen' on this schema — use TipoOperacion values instead
$res = $conn->query("SELECT DISTINCT IFNULL(TipoOperacion,'') AS Origen FROM RegistroDanio WHERE IFNULL(TipoOperacion,'')<>'' ORDER BY Origen");
if ($res) { while ($r = $res->fetch_assoc()) $origenes[] = $r['Origen']; }

// Check if RegistroDanio has any rows; if empty, we will force queries to return no results
$rd_count = 0;
$tmp = $conn->query("SELECT COUNT(*) AS c FROM RegistroDanio");
if ($tmp) {
    $rowc = $tmp->fetch_assoc();
    $rd_count = intval($rowc['c']);
}
$registro_danio_empty = ($rd_count === 0);

// read inputs
$vin = isset($_REQUEST['vin']) ? trim($_REQUEST['vin']) : '';
$buque = isset($_REQUEST['buque']) ? trim($_REQUEST['buque']) : '';
$date_from = isset($_REQUEST['date_from']) ? trim($_REQUEST['date_from']) : '';
$date_to = isset($_REQUEST['date_to']) ? trim($_REQUEST['date_to']) : '';
$area = isset($_REQUEST['area']) ? trim($_REQUEST['area']) : '';
$maniobra = isset($_REQUEST['maniobra']) ? trim($_REQUEST['maniobra']) : '';
$origen = isset($_REQUEST['origen']) ? trim($_REQUEST['origen']) : '';

// build WHERE separately for vehicle filters and damage filters so each can work independently
$where_v = []; // conditions on vehiculo
$where_rd = []; // conditions on RegistroDanio

if ($vin !== '') $where_v[] = "v.VIN LIKE '%" . $conn->real_escape_string($vin) . "%'";
if ($buque !== '') $where_v[] = "v.Buque = '" . $conn->real_escape_string($buque) . "'";
if ($date_from !== '') {
    $d = $conn->real_escape_string($date_from) . " 00:00:00";
    $where_rd[] = "rd.FechaRegistro >= '" . $d . "'";
}
if ($date_to !== '') {
    // include entire day for date_to by setting time to 23:59:59
    $d = $conn->real_escape_string($date_to) . " 23:59:59";
    $where_rd[] = "rd.FechaRegistro <= '" . $d . "'";
}
if ($area !== '') $where_rd[] = "rd.CodAreaDano = '" . $conn->real_escape_string($area) . "'";
if ($maniobra !== '') $where_rd[] = "rd.TipoOperacion LIKE '%" . $conn->real_escape_string($maniobra) . "%'";
if ($origen !== '') $where_rd[] = "rd.TipoOperacion = '" . $conn->real_escape_string($origen) . "'";

// Decide which base to use:
// - If only vehicle filters provided (VIN/Buque) and no damage filters, start from vehiculo LEFT JOIN RegistroDanio
// - Otherwise, start from RegistroDanio (existing behaviour)

$has_v = count($where_v) > 0;
$has_rd = count($where_rd) > 0;

if ($has_v && !$has_rd) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_v);
    $sql = "SELECT rd.FechaRegistro, v.VIN, v.Marca, v.Modelo, v.Color, v.`Año` AS Ano, v.Puerto, v.Terminal, v.Buque, v.Viaje,
                rd.CodAreaDano AS CodAreaDano, rd.CodTipoDano AS CodTipoDano, rd.CodSeveridadDano AS CodSeveridadDano, rd.TipoOperacion AS Origen, rd.TipoOperacion
            FROM vehiculo v
            LEFT JOIN RegistroDanio rd ON v.VIN = rd.VIN
            LEFT JOIN areadano a ON rd.CodAreaDano = a.CodAreaDano
            LEFT JOIN tipodano t ON rd.CodTipoDano = t.CodTipoDano
            LEFT JOIN severidaddano s ON rd.CodSeveridadDano = s.CodSeveridadDano
            " . $where_sql . "
            ORDER BY rd.FechaRegistro DESC";
} else {
    // merge both sets so filters combine when user provides multiple filter types
    $all_where = array_merge($where_rd, $where_v);
    $where_sql = '';
    if (count($all_where) > 0) $where_sql = 'WHERE ' . implode(' AND ', $all_where);
    $sql = "SELECT rd.FechaRegistro, rd.VIN, v.Marca, v.Modelo, v.Color, v.`Año` AS Ano, v.Puerto, v.Terminal, v.Buque, v.Viaje,
                rd.CodAreaDano AS CodAreaDano, rd.CodTipoDano AS CodTipoDano, rd.CodSeveridadDano AS CodSeveridadDano, rd.TipoOperacion AS Origen, rd.TipoOperacion
            FROM RegistroDanio rd
            LEFT JOIN vehiculo v ON rd.VIN = v.VIN
            LEFT JOIN areadano a ON rd.CodAreaDano = a.CodAreaDano
            LEFT JOIN tipodano t ON rd.CodTipoDano = t.CodTipoDano
            LEFT JOIN severidaddano s ON rd.CodSeveridadDano = s.CodSeveridadDano
            " . $where_sql . "
            ORDER BY rd.FechaRegistro DESC";
}

// Export CSV if requested
if (isset($_POST['export_csv'])) {
    $res = $conn->query($sql);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=report_vehiculos.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['FechaRegistro','VIN','Marca','Modelo','Color','Año','Puerto','Terminal','Buque','Viaje','CodAreaDano','CodTipoDano','CodSeveridadDano','Origen','Maniobra']);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [
                $r['FechaRegistro'] ?? '',
                $r['VIN'] ?? '',
                $r['Marca'] ?? '',
                $r['Modelo'] ?? '',
                $r['Color'] ?? '',
                $r['Ano'] ?? '',
                $r['Puerto'] ?? '',
                $r['Terminal'] ?? '',
                $r['Buque'] ?? '',
                $r['Viaje'] ?? '',
                $r['CodAreaDano'] ?? '',
                $r['CodTipoDano'] ?? '',
                $r['CodSeveridadDano'] ?? '',
                $r['Origen'] ?? '',
                $r['TipoOperacion'] ?? ''
            ]);
        }
    }
    fclose($out);
    exit();
}

// Decide whether to run the display query: only when user applied any filter or requested export
$res = null;
$has_filter = false;
if (
    $vin !== '' || $buque !== '' || $date_from !== '' || $date_to !== '' ||
    $area !== '' || $maniobra !== '' || $origen !== '' || isset($_POST['export_csv'])
) {
    $has_filter = true;
}

if ($has_filter) {
    // run query for display (limit to 200 rows to avoid heavy pages)
    if ($registro_danio_empty) {
        // force empty result set
        $display_sql = "SELECT rd.FechaRegistro, rd.VIN FROM RegistroDanio rd WHERE 0 LIMIT 0";
        $res = $conn->query($display_sql);
    } else {
        $display_sql = $sql . " LIMIT 200";
        $res = $conn->query($display_sql);
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reportes Vehículos - EIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="navbar_styles.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="card">
                <div class="card-body">
                    <h4>Reportes - Vehículos y Daños</h4>
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">VIN</label>
                            <input class="form-control" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Buque</label>
                            <select name="buque" class="form-select">
                                <option value="">(todos)</option>
                                <?php foreach ($buques as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>" <?php if ($b===$buque) echo 'selected'; ?>><?php echo htmlspecialchars($b); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha desde</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha hasta</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Maniobra / TipoOperacion</label>
                            <input class="form-control" name="maniobra" value="<?php echo htmlspecialchars($maniobra); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Área (Maniobra)</label>
                            <select name="area" class="form-select">
                                <option value="">(todas)</option>
                                <?php foreach ($areas as $a): ?>
                                    <option value="<?php echo htmlspecialchars($a['CodAreaDano']); ?>" <?php if ($a['CodAreaDano']===$area) echo 'selected'; ?>><?php echo htmlspecialchars($a['NomAreaDano']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Origen</label>
                            <select name="origen" class="form-select">
                                <option value="">(todos)</option>
                                <?php foreach ($origenes as $o): ?>
                                    <option value="<?php echo htmlspecialchars($o); ?>" <?php if ($o===$origen) echo 'selected'; ?>><?php echo htmlspecialchars($o); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-primary">Generar reporte</button>
                            <button type="submit" name="export_csv" value="1" formaction="reportes_vehiculos.php" formmethod="post" class="btn btn-success ms-2">Exportar CSV (completo)</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <h5>Resultados (muestra hasta 200 filas)</h5>
                    <?php if (!$has_filter): ?>
                        <div class="alert alert-secondary">No se muestran datos. Aplique filtros y presione "Generar reporte".</div>
                    <?php elseif ($registro_danio_empty): ?>
                        <div class="alert alert-warning">La tabla <strong>RegistroDanio</strong> no contiene registros. No se mostrarán resultados.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>VIN</th>
                                    <th>Marca</th>
                                    <th>Modelo</th>
                                    <th>Color</th>
                                    <th>Año</th>
                                    <th>Puerto</th>
                                    <th>Terminal</th>
                                    <th>Buque</th>
                                    <th>Viaje</th>
                                    <th>CodAreaDano</th>
                                    <th>CodTipoDano</th>
                                    <th>CodSeveridadDano</th>
                                    <th>Origen</th>
                                    <th>Maniobra</th>
                                </tr>
                            </thead>
                            <tbody>
<?php if ($res && $res->num_rows>0): while($row = $res->fetch_assoc()): ?>
    <tr>
        <td><?php echo htmlspecialchars($row['FechaRegistro'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['VIN'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Marca'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Modelo'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Color'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Ano'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Puerto'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Terminal'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Buque'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Viaje'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['CodAreaDano'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['CodTipoDano'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['CodSeveridadDano'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['Origen'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($row['TipoOperacion'] ?? ''); ?></td>
    </tr>
<?php endwhile; else: ?>
    <tr><td colspan="15" class="text-center">No se encontraron resultados</td></tr>
<?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
