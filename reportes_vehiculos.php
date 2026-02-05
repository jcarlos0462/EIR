<?php
session_start();
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header("Location: index.html");
    exit();
}

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
$res = $conn->query("SELECT DISTINCT IFNULL(Origen,'') AS Origen FROM RegistroDanio WHERE IFNULL(Origen,'')<>'' ORDER BY Origen");
if ($res) { while ($r = $res->fetch_assoc()) $origenes[] = $r['Origen']; }

// read inputs
$vin = isset($_REQUEST['vin']) ? trim($_REQUEST['vin']) : '';
$buque = isset($_REQUEST['buque']) ? trim($_REQUEST['buque']) : '';
$date_from = isset($_REQUEST['date_from']) ? trim($_REQUEST['date_from']) : '';
$date_to = isset($_REQUEST['date_to']) ? trim($_REQUEST['date_to']) : '';
$area = isset($_REQUEST['area']) ? trim($_REQUEST['area']) : '';
$maniobra = isset($_REQUEST['maniobra']) ? trim($_REQUEST['maniobra']) : '';
$origen = isset($_REQUEST['origen']) ? trim($_REQUEST['origen']) : '';

// build WHERE
$where = [];
if ($vin !== '') $where[] = "rd.VIN = '" . $conn->real_escape_string($vin) . "'";
if ($buque !== '') $where[] = "v.Buque = '" . $conn->real_escape_string($buque) . "'";
if ($date_from !== '') {
    $d = $conn->real_escape_string($date_from);
    $where[] = "rd.FechaRegistro >= '" . $d . "'";
}
if ($date_to !== '') {
    $d = $conn->real_escape_string($date_to);
    $where[] = "rd.FechaRegistro <= '" . $d . "'";
}
if ($area !== '') $where[] = "rd.CodAreaDano = '" . $conn->real_escape_string($area) . "'";
if ($maniobra !== '') $where[] = "rd.TipoOperacion LIKE '%" . $conn->real_escape_string($maniobra) . "%'";
if ($origen !== '') $where[] = "rd.Origen = '" . $conn->real_escape_string($origen) . "'";

$where_sql = '';
if (count($where) > 0) $where_sql = 'WHERE ' . implode(' AND ', $where);

// main query returning vehicle + damage details
$sql = "SELECT rd.FechaRegistro, rd.VIN, v.Marca, v.Modelo, v.Color, v.`Año` AS Ano, v.Puerto, v.Terminal, v.Buque, v.Viaje,
            a.NomAreaDano AS Area, t.NomTipoDano AS Tipo, s.NomSeveridadDano AS Severidad, rd.Origen, rd.TipoOperacion
        FROM RegistroDanio rd
        LEFT JOIN vehiculo v ON rd.VIN = v.VIN
        LEFT JOIN areadano a ON rd.CodAreaDano = a.CodAreaDano
        LEFT JOIN tipodano t ON rd.CodTipoDano = t.CodTipoDano
        LEFT JOIN severidaddano s ON rd.CodSeveridadDano = s.CodSeveridadDano
        " . $where_sql . "
        ORDER BY rd.FechaRegistro DESC";

// Export CSV if requested
if (isset($_POST['export_csv'])) {
    $res = $conn->query($sql);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=report_vehiculos.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['FechaRegistro','VIN','Marca','Modelo','Color','Año','Puerto','Terminal','Buque','Viaje','Area','Tipo','Severidad','Origen','Maniobra']);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [$r['FechaRegistro'],$r['VIN'],$r['Marca'],$r['Modelo'],$r['Color'],$r['Ano'],$r['Puerto'],$r['Terminal'],$r['Buque'],$r['Viaje'],$r['Area'],$r['Tipo'],$r['Severidad'],$r['Origen'],$r['TipoOperacion']]);
        }
    }
    fclose($out);
    exit();
}

// run query for display (limit to 200 rows to avoid heavy pages)
$display_sql = $sql . " LIMIT 200";
$res = $conn->query($display_sql);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reportes Vehículos - EIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="col-md-9">
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
                            <button type="submit" name="export" value="1" formaction="reportes_vehiculos.php" formmethod="post" class="btn btn-success ms-2">Exportar CSV (completo)</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <h5>Resultados (muestra hasta 200 filas)</h5>
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
                                    <th>Area</th>
                                    <th>Tipo</th>
                                    <th>Severidad</th>
                                    <th>Origen</th>
                                    <th>Maniobra</th>
                                </tr>
                            </thead>
                            <tbody>
<?php if ($res && $res->num_rows>0): while($row = $res->fetch_assoc()): ?>
    <tr>
        <td><?php echo htmlspecialchars($row['FechaRegistro']); ?></td>
        <td><?php echo htmlspecialchars($row['VIN']); ?></td>
        <td><?php echo htmlspecialchars($row['Marca']); ?></td>
        <td><?php echo htmlspecialchars($row['Modelo']); ?></td>
        <td><?php echo htmlspecialchars($row['Color']); ?></td>
        <td><?php echo htmlspecialchars($row['Ano']); ?></td>
        <td><?php echo htmlspecialchars($row['Puerto']); ?></td>
        <td><?php echo htmlspecialchars($row['Terminal']); ?></td>
        <td><?php echo htmlspecialchars($row['Buque']); ?></td>
        <td><?php echo htmlspecialchars($row['Viaje']); ?></td>
        <td><?php echo htmlspecialchars($row['Area']); ?></td>
        <td><?php echo htmlspecialchars($row['Tipo']); ?></td>
        <td><?php echo htmlspecialchars($row['Severidad']); ?></td>
        <td><?php echo htmlspecialchars($row['Origen']); ?></td>
        <td><?php echo htmlspecialchars($row['TipoOperacion']); ?></td>
    </tr>
<?php endwhile; else: ?>
    <tr><td colspan="15" class="text-center">No se encontraron resultados</td></tr>
<?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
