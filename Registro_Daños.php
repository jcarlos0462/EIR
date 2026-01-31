<?php
session_start();
include 'database_connection.php';

// Inicializar variables
$vin = $marca = $modelo = $color = '';
$errores = [];
$danios = [];
$show_form = false;

// Buscar VIN
if (isset($_POST['buscar_vin'])) {
    $vin = trim($_POST['vin']);
    $stmt = $conn->prepare("SELECT Marca, Modelo, Color FROM vehiculo WHERE VIN = ?");
    $stmt->bind_param('s', $vin);
    $stmt->execute();
    $stmt->bind_result($marca, $modelo, $color);
    if ($stmt->fetch()) {
        // Buscar daños registrados
        $stmt->close();
        $sql = "SELECT ID, CodAreaDano, CodTipoDano, CodSeveridadDano FROM RegistroDanio WHERE VIN = ? ORDER BY ID DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $vin);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $danios[] = $row;
        }
    } else {
        $errores[] = 'VIN no encontrado.';
    }
    $stmt->close();
}

// Guardar daño
if (isset($_POST['guardar_danio'])) {
    $vin = trim($_POST['vin']);
    $area = intval($_POST['area']);
    $tipo = intval($_POST['tipo']);
    $severidad = intval($_POST['severidad']);
    if ($vin && $area && $tipo && $severidad) {
        $stmt = $conn->prepare("INSERT INTO RegistroDanio (VIN, CodAreaDano, CodTipoDano, CodSeveridadDano) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('siii', $vin, $area, $tipo, $severidad);
        if ($stmt->execute()) {
            $show_form = false;
        } else {
            $errores[] = 'Error al guardar daño.';
        }
        $stmt->close();
    } else {
        $errores[] = 'Todos los campos son obligatorios.';
        $show_form = true;
    }
    // Refrescar daños
    $stmt = $conn->prepare("SELECT Marca, Modelo, Color FROM vehiculo WHERE VIN = ?");
    $stmt->bind_param('s', $vin);
    $stmt->execute();
    $stmt->bind_result($marca, $modelo, $color);
    $stmt->fetch();
    $stmt->close();
    $sql = "SELECT ID, CodAreaDano, CodTipoDano, CodSeveridadDano FROM RegistroDanio WHERE VIN = ? ORDER BY ID DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $vin);
    $stmt->execute();
    $result = $stmt->get_result();
    $danios = [];
    while ($row = $result->fetch_assoc()) {
        $danios[] = $row;
    }
    $stmt->close();
}

// Cargar listas para selects
$areas = $conn->query("SELECT CodAreaDano FROM areadano ORDER BY CodAreaDano");
$tipos = $conn->query("SELECT CodTipoDano FROM tipodano ORDER BY CodTipoDano");
$severidades = $conn->query("SELECT CodSeveridadDano FROM severidaddano ORDER BY CodSeveridadDano");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Daños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="navbar_styles.css">
    <style>
        body { background: #fff; }
        .main-box { max-width: 400px; margin: 40px auto; border-radius: 0; box-shadow: 0 0 0 0; }
        .main-box, .form-control, .btn { border-radius: 8px; }
        .table { font-size: 1rem; }
        .btn { background: #426dc9; color: #fff; border: none; font-weight: 500; }
        .btn:hover { background: #2d4e8c; color: #fff; }
        .btn-add { width: 100%; margin-top: 16px; }
        .btn-back { width: 100%; margin-top: 16px; background: #426dc9; }
        .label { font-weight: 600; color: #426dc9; }
        .form-section { display: <?php echo $show_form ? 'block' : 'none'; ?>; }
        .table-section { display: <?php echo $show_form ? 'none' : 'block'; ?>; }
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
            <div class="row mb-4">
                <div class="col-12">
                    <form method="post" class="d-flex flex-row gap-2 align-items-end">
                        <div>
                            <label class="label">VIN</label>
                            <input type="text" name="vin" class="form-control" value="<?php echo htmlspecialchars($vin); ?>" required>
                        </div>
                        <div>
                            <button type="submit" name="buscar_vin" class="btn">Buscar</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php if ($errores): ?>
                <div class="alert alert-danger py-2"><?php echo implode('<br>', $errores); ?></div>
            <?php endif; ?>
            <?php if ($marca): ?>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="label">Marca</div>
                        <div><?php echo htmlspecialchars($marca); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="label">Modelo</div>
                        <div><?php echo htmlspecialchars($modelo); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="label">Color</div>
                        <div><?php echo htmlspecialchars($color); ?></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <table class="table table-bordered table-sm mb-2">
                            <thead class="table-primary">
                                <tr>
                                    <th>Área</th>
                                    <th>Tipo</th>
                                    <th>Severidad</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($danios): foreach ($danios as $d): ?>
                                <tr>
                                    <td><?php echo $d['CodAreaDano']; ?></td>
                                    <td><?php echo $d['CodTipoDano']; ?></td>
                                    <td><?php echo $d['CodSeveridadDano']; ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="3" class="text-center">Sin daños registrados</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                            <button type="submit" name="show_form" class="btn btn-primary">Agregar Daño</button>
                        </form>
                    </div>
                </div>
                <?php if (isset($_POST['show_form']) || $show_form): ?>
                <div class="row mt-4">
                    <div class="col-md-6 col-lg-5">
                        <div class="card p-3">
                            <form method="post" class="d-flex flex-column gap-2">
                                <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                <label class="label">Área de Daño</label>
                                <select name="area" class="form-control" required>
                                    <option value="">Seleccione</option>
                                    <?php foreach ($areas as $a) echo '<option value="'.$a['CodAreaDano'].'">'.$a['CodAreaDano'].'</option>'; ?>
                                </select>
                                <label class="label">Tipo de Daño</label>
                                <select name="tipo" class="form-control" required>
                                    <option value="">Seleccione</option>
                                    <?php foreach ($tipos as $t) echo '<option value="'.$t['CodTipoDano'].'">'.$t['CodTipoDano'].'</option>'; ?>
                                </select>
                                <label class="label">Severidad</label>
                                <select name="severidad" class="form-control" required>
                                    <option value="">Seleccione</option>
                                    <?php foreach ($severidades as $s) echo '<option value="'.$s['CodSeveridadDano'].'">'.$s['CodSeveridadDano'].'</option>'; ?>
                                </select>
                                <button type="submit" name="guardar_danio" class="btn btn-success">Guardar</button>
                            </form>
                            <form method="post" class="mt-2">
                                <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                <button type="submit" name="buscar_vin" class="btn btn-secondary">Regresar</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>