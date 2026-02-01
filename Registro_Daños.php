<?php
session_start();
include 'database_connection.php';

// Inicializar variables
$vin = $marca = $modelo = $color = '';
$errores = [];
$danios = [];
$show_form = false;

// Buscar VIN (por POST, GET o contexto de acción)
if (isset($_POST['buscar_vin'])) {
    $vin = trim($_POST['vin']);
} elseif (isset($_GET['vin'])) {
    $vin = trim($_GET['vin']);
} elseif (isset($_POST['vin'])) {
    $vin = trim($_POST['vin']);
}

if ($vin) {
    $stmt = $conn->prepare("SELECT Marca, Modelo, Color FROM vehiculo WHERE VIN = ?");
    $stmt->bind_param('s', $vin);
    $stmt->execute();
    $stmt->bind_result($marca, $modelo, $color);
    if ($stmt->fetch()) {
        // Buscar daños registrados con descripciones
        $stmt->close();
        $sql = "SELECT r.ID, a.NomAreaDano, t.NomTipoDano, s.NomSeveridadDano FROM RegistroDanio r
                JOIN areadano a ON r.CodAreaDano = a.CodAreaDano
                JOIN tipodano t ON r.CodTipoDano = t.CodTipoDano
                JOIN severidaddano s ON r.CodSeveridadDano = s.CodSeveridadDano
                WHERE r.VIN = ? ORDER BY r.ID DESC";
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
    $vin = isset($_POST['vin']) ? trim($_POST['vin']) : '';
    $stmt = $conn->prepare("DELETE FROM RegistroDanio WHERE ID = ?");
    $stmt->bind_param('i', $id_danio);
    $stmt->execute();
    $stmt->close();
    // Redirigir para evitar reenvío y mantener contexto VIN
    if ($vin) {
        header("Location: Registro_Daños.php?vin=" . urlencode($vin));
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
            // Redirigir para evitar duplicado al refrescar (PRG) y mantener contexto VIN
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
// Editar daño
if (isset($_POST['editar_danio']) && isset($_POST['id_danio'])) {
    $id_danio = intval($_POST['id_danio']);
    $vin = isset($_POST['vin']) ? trim($_POST['vin']) : '';
    $area = isset($_POST['area']) ? intval($_POST['area']) : 0;
    $tipo = isset($_POST['tipo']) ? intval($_POST['tipo']) : 0;
    $severidad = isset($_POST['severidad']) ? intval($_POST['severidad']) : 0;
    if ($area && $tipo && $severidad) {
        $stmt = $conn->prepare("UPDATE RegistroDanio SET CodAreaDano = ?, CodTipoDano = ?, CodSeveridadDano = ? WHERE ID = ?");
        $stmt->bind_param('iiii', $area, $tipo, $severidad, $id_danio);
        $stmt->execute();
        $stmt->close();
        // Redirigir para evitar reenvío y mantener contexto VIN
        if ($vin) {
            header("Location: Registro_Daños.php?vin=" . urlencode($vin));
        } else {
            header("Location: Registro_Daños.php");
        }
        exit();
    } else {
        $errores[] = 'Todos los campos son obligatorios.';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(120deg, #6a82fb 0%, #fc5c7d 100%);
            min-height: 100vh;
        }
        .modern-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px 0 rgba(60,60,120,0.10);
            padding: 2.5rem 2rem 2rem 2rem;
            margin: 40px auto 0 auto;
            max-width: 600px;
        }
        .modern-label {
            font-weight: 700;
            color: #426dc9;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        .modern-input {
            font-size: 1.4rem;
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            border: 2px solid #e0e6f7;
            text-align: center;
            letter-spacing: 2px;
            box-shadow: 0 2px 8px 0 rgba(60,60,120,0.04);
        }
        .modern-btn {
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 0.7rem 1.5rem;
            box-shadow: 0 2px 8px 0 rgba(60,60,120,0.08);
        }
        .modern-btn-primary {
            background: linear-gradient(90deg, #426dc9 60%, #6a82fb 100%);
            color: #fff;
            border: none;
        }
        .modern-btn-primary:hover {
            background: linear-gradient(90deg, #2d4e8c 60%, #426dc9 100%);
            color: #fff;
        }
        .modern-btn-success {
            background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
            color: #fff;
            border: none;
        }
        .modern-btn-success:hover {
            background: linear-gradient(90deg, #38f9d7 0%, #43e97b 100%);
            color: #fff;
        }
        .modern-modal-header {
            background: linear-gradient(90deg, #426dc9 60%, #6a82fb 100%);
            color: #fff;
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .modern-modal-icon {
            font-size: 2rem;
            margin-right: 0.5rem;
        }
        .modern-modal-content {
            border-radius: 18px;
        }
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
            <div class="row mb-4 justify-content-center">
                <div class="col-12 d-flex justify-content-center">
                    <div class="modern-card">
                        <form method="post" class="d-flex flex-row gap-3 align-items-end" style="width: 100%;">
                            <div class="flex-grow-1">
                                <label class="modern-label">VIN</label>
                                <input type="text" id="vinInput" name="vin" class="modern-input" value="<?php echo htmlspecialchars($vin); ?>" required autofocus placeholder="Escanea o ingresa el VIN">
                            </div>
                            <div>
                                <button type="button" class="modern-btn modern-btn-success" id="btnScanQR" title="Escanear QR"><i class="bi bi-qr-code-scan"></i></button>
                            </div>
                            <div>
                                <button type="submit" name="buscar_vin" class="modern-btn modern-btn-primary">Buscar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Modal QR -->
            <div class="modal fade" id="modalQR" tabindex="-1" aria-labelledby="modalQRLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content modern-modal-content">
                        <div class="modal-header modern-modal-header">
                            <span class="modern-modal-icon bi bi-qr-code-scan"></span>
                            <h5 class="modal-title mb-0" id="modalQRLabel">Escanear VIN (QR)</h5>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div id="qr-reader" style="width:100%; min-height:300px;"></div>
                        </div>
                    </div>
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
                                            <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
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
    <script src="https://unpkg.com/html5-qrcode@2.3.10/html5-qrcode.min.js"></script>
    <script>
    // Abrir modal QR
    document.getElementById('btnScanQR').addEventListener('click', function() {
        var qrModal = new bootstrap.Modal(document.getElementById('modalQR'));
        qrModal.show();
        setTimeout(startQRScanner, 400);
    });

    let qrScanner;
    function startQRScanner() {
        if (qrScanner) {
            qrScanner.clear();
        }
        qrScanner = new Html5Qrcode("qr-reader");
        qrScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            qrCodeMessage => {
                document.getElementById('vinInput').value = qrCodeMessage;
                bootstrap.Modal.getInstance(document.getElementById('modalQR')).hide();
                qrScanner.stop();
            },
            errorMessage => {}
        ).catch(err => {});
    }
    // Limpiar QR al cerrar modal
    document.getElementById('modalQR').addEventListener('hidden.bs.modal', function () {
        if (qrScanner) qrScanner.stop();
    });
    </script>
</body>
</html>