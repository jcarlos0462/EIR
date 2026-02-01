<?php
session_start();
include 'database_connection.php';

// Inicializar variables
$vin = $marca = $modelo = $color = '';
$errores = [];
$danios = [];
$show_form = false;

// Buscar VIN
// Buscar VIN (por POST o GET)
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

// Eliminar daño
if (isset($_POST['eliminar_danio']) && isset($_POST['id_danio'])) {
    $id_danio = intval($_POST['id_danio']);
    $stmt = $conn->prepare("DELETE FROM RegistroDanio WHERE ID = ?");
    $stmt->bind_param('i', $id_danio);
    $stmt->execute();
    $stmt->close();
    // Redirigir para evitar reenvío
    if (isset($_GET['vin'])) {
        header("Location: Registro_Daños.php?vin=" . urlencode($_GET['vin']));
    } else {
        header("Location: Registro_Daños.php");
    }
    exit();
}
// Guardar daño
if (isset($_POST['guardar_danio'])) {
    $vin = trim($_POST['vin']);
    $area = isset($_POST['area']) ? intval($_POST['area']) : 0;
    $tipo = isset($_POST['tipo']) ? intval($_POST['tipo']) : 0;
    $severidad = isset($_POST['severidad']) ? intval($_POST['severidad']) : 0;
    if ($vin && $area && $tipo && $severidad) {
        $stmt = $conn->prepare("INSERT INTO RegistroDanio (VIN, CodAreaDano, CodTipoDano, CodSeveridadDano) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('siii', $vin, $area, $tipo, $severidad);
        if ($stmt->execute()) {
            // Redirigir para evitar duplicado al refrescar (PRG)
            header("Location: Registro_Daños.php?vin=" . urlencode($vin));
            exit();
        } else {
            $errores[] = 'Error al guardar daño.';
        }
        $stmt->close();
    } else {
        $errores[] = 'Todos los campos son obligatorios.';
        $show_form = true;
    }
}

// Cargar listas para selects
// Cargar listas para selects con descripciones
$areas = $conn->query("SELECT CodAreaDano, NomAreaDano FROM areadano ORDER BY CodAreaDano");
$tipos = $conn->query("SELECT CodTipoDano, NomTipoDano FROM tipodano ORDER BY CodTipoDano");
$severidades = $conn->query("SELECT CodSeveridadDano, NomSeveridadDano FROM severidaddano ORDER BY CodSeveridadDano");
?>
    <?php
    $areas = $conn->query("SELECT CodAreaDano, NomAreaDano FROM areadano ORDER BY CodAreaDano");
    $tipos = $conn->query("SELECT CodTipoDano, NomTipoDano FROM tipodano ORDER BY CodTipoDano");
    $severidades = $conn->query("SELECT CodSeveridadDano, NomSeveridadDano FROM severidaddano ORDER BY CodSeveridadDano");
    ?>
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
</br>
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
                        <!-- Botón para abrir el modal arriba de la tabla -->
                        <div class="mb-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalDanio">
                                <i class="bi bi-plus-lg"></i> Agregar Daño
                            </button>
                        </div>
                        <table class="table table-bordered table-sm mb-2">
                            <thead class="table-primary">
                                <tr>
                                    <th>Área</th>
                                    <th>Tipo</th>
                                    <th>Severidad</th>
                                    <th style="width:120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($danios)): foreach ($danios as $d): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($d['NomAreaDano']); ?></td>
                                    <td><?php echo htmlspecialchars($d['NomTipoDano']); ?></td>
                                    <td><?php echo htmlspecialchars($d['NomSeveridadDano']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning me-1" title="Editar" data-bs-toggle="modal" data-bs-target="#modalEditarDanio<?php echo $d['ID']; ?>">
                                            <span class="bi bi-pencil-square"></span>
                                        </button>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id_danio" value="<?php echo $d['ID']; ?>">
                                            <button type="submit" name="eliminar_danio" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Seguro que deseas eliminar este daño?');">
                                                <span class="bi bi-trash-fill"></span>
                                            </button>
                                        </form>
                                    </td>
                                                                </tr>
                                                                <!-- Modal editar daño -->
                                                                <div class="modal fade" id="modalEditarDanio<?php echo $d['ID']; ?>" tabindex="-1" aria-labelledby="modalEditarLabel<?php echo $d['ID']; ?>" aria-hidden="true">
                                                                    <div class="modal-dialog modal-dialog-centered">
                                                                        <div class="modal-content">
                                                                            <div class="modal-header bg-warning text-dark">
                                                                                <h5 class="modal-title" id="modalEditarLabel<?php echo $d['ID']; ?>">Editar Daño</h5>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <form method="post" class="d-flex flex-column gap-3">
                                                                                    <input type="hidden" name="id_danio" value="<?php echo $d['ID']; ?>">
                                                                                    <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                                                                    <div>
                                                                                        <label class="label fw-bold text-primary">Tipo de Daño</label>
                                                                                        <select name="tipo" class="form-control border-primary" required>
                                                                                            <option value="">Seleccione</option>
                                                                                            <?php foreach ($tipos as $t) echo '<option value="'.$t['CodTipoDano'].'"'.($t['NomTipoDano']==$d['NomTipoDano']?' selected':'').'>'.$t['NomTipoDano'].'</option>'; ?>
                                                                                        </select>
                                                                                    </div>
                                                                                    <div>
                                                                                        <label class="label fw-bold text-primary">Área de Daño</label>
                                                                                        <select name="area" class="form-control border-primary" required>
                                                                                            <option value="">Seleccione</option>
                                                                                            <?php foreach ($areas as $a) echo '<option value="'.$a['CodAreaDano'].'"'.($a['NomAreaDano']==$d['NomAreaDano']?' selected':'').'>'.$a['NomAreaDano'].'</option>'; ?>
                                                                                        </select>
                                                                                    </div>
                                                                                    <div>
                                                                                        <label class="label fw-bold text-primary">Severidad</label>
                                                                                        <select name="severidad" class="form-control border-primary" required>
                                                                                            <option value="">Seleccione</option>
                                                                                            <?php foreach ($severidades as $s) echo '<option value="'.$s['CodSeveridadDano'].'"'.($s['NomSeveridadDano']==$d['NomSeveridadDano']?' selected':'').'>'.$s['NomSeveridadDano'].'</option>'; ?>
                                                                                        </select>
                                                                                    </div>
                                                                                    <div class="d-grid gap-2 mt-2">
                                                                                        <button type="submit" name="editar_danio" class="btn btn-warning">Guardar Cambios</button>
                                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                                    </div>
                                                                                </form>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                            // Editar daño
                            if (isset($_POST['editar_danio']) && isset($_POST['id_danio'])) {
                                $id_danio = intval($_POST['id_danio']);
                                $area = isset($_POST['area']) ? intval($_POST['area']) : 0;
                                $tipo = isset($_POST['tipo']) ? intval($_POST['tipo']) : 0;
                                $severidad = isset($_POST['severidad']) ? intval($_POST['severidad']) : 0;
                                if ($area && $tipo && $severidad) {
                                    $stmt = $conn->prepare("UPDATE RegistroDanio SET CodAreaDano=?, CodTipoDano=?, CodSeveridadDano=? WHERE ID=?");
                                    $stmt->bind_param('iiii', $area, $tipo, $severidad, $id_danio);
                                    $stmt->execute();
                                    $stmt->close();
                                    // Redirigir para evitar reenvío
                                    if (isset($_GET['vin'])) {
                                        header("Location: Registro_Daños.php?vin=" . urlencode($_GET['vin']));
                                    } else {
                                        header("Location: Registro_Daños.php");
                                    }
                                    exit();
                                } else {
                                    $errores[] = 'Todos los campos son obligatorios.';
                                }
                            }
                            <?php endforeach; else: ?>
                                <tr><td colspan="4" class="text-center">Sin daños registrados</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                                <!-- Modal para registrar daño -->
                                <div class="modal fade" id="modalDanio" tabindex="-1" aria-labelledby="modalDanioLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title" id="modalDanioLabel">Registrar Daño</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="post" class="d-flex flex-column gap-3">
                                                    <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                                    <div>
                                                        <label class="label fw-bold text-primary">Tipo de Daño</label>
                                                        <select name="tipo" class="form-control border-primary" required>
                                                            <option value="">Seleccione</option>
                                                            <?php foreach ($tipos as $t) echo '<option value="'.$t['CodTipoDano'].'">'.$t['NomTipoDano'].'</option>'; ?>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="label fw-bold text-primary">Área de Daño</label>
                                                        <select name="area" class="form-control border-primary" required>
                                                            <option value="">Seleccione</option>
                                                            <?php foreach ($areas as $a) echo '<option value="'.$a['CodAreaDano'].'">'.$a['NomAreaDano'].'</option>'; ?>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="label fw-bold text-primary">Severidad</label>
                                                        <select name="severidad" class="form-control border-primary" required>
                                                            <option value="">Seleccione</option>
                                                            <?php foreach ($severidades as $s) echo '<option value="'.$s['CodSeveridadDano'].'">'.$s['NomSeveridadDano'].'</option>'; ?>
                                                        </select>
                                                    </div>
                                                    <div class="d-grid gap-2 mt-2">
                                                        <button type="submit" name="guardar_danio" class="btn btn-primary">Guardar</button>
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Regresar</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>