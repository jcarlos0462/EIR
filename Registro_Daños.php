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
                        .qr-help {
                            text-align: center;
                            color: #426dc9;
                            font-size: 1.1rem;
                            margin-bottom: 1rem;
                            font-weight: 500;
                        }
                .vehiculo-card {
                    background: #fff;
                    border-radius: 18px;
                    box-shadow: 0 4px 24px 0 rgba(60,60,120,0.13);
                    padding: 1.5rem 2rem 1.2rem 2rem;
                    margin-bottom: 2rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 2rem;
                }
                .vehiculo-info {
                    display: flex;
                    flex-direction: row;
                    gap: 2.5rem;
                    width: 100%;
                    justify-content: space-between;
                }
                .vehiculo-label {
                    color: #6a82fb;
                    font-weight: 700;
                    font-size: 1.1rem;
                    margin-bottom: 0.2rem;
                }
                .vehiculo-value {
                    color: #222;
                    font-size: 1.25rem;
                    font-weight: 600;
                    letter-spacing: 1px;
                }
                .modern-table-card {
                    background: #fff;
                    border-radius: 18px;
                    box-shadow: 0 4px 24px 0 rgba(60,60,120,0.13);
                    padding: 2rem 1.5rem 1.5rem 1.5rem;
                    margin-bottom: 2rem;
                }
                .modern-table {
                    border-radius: 12px;
                    overflow: hidden;
                    background: #f4f7fb;
                    <div id="dashboard-cards" class="row justify-content-center" style="gap:2rem; margin-bottom:2rem;">
                        <div class="col-md-3">
                            <div class="modern-table-card text-center">
                                <div style="font-size:3rem; color:#426dc9;"><i class="bi bi-truck"></i></div>
                                <div class="modern-label" style="font-size:1.3rem;">Vehículos</div>
                                <div style="font-size:2.5rem; font-weight:700; margin:1rem 0;">1</div>
                                <button class="modern-btn modern-btn-primary w-75" onclick="mostrarSeccion('vehiculos')">Ver</button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="modern-table-card text-center">
                                <div style="font-size:3rem; color:#426dc9;"><i class="bi bi-exclamation-triangle"></i></div>
                                <div class="modern-label" style="font-size:1.3rem;">Daños Registrados</div>
                                <div style="font-size:2.5rem; font-weight:700; margin:1rem 0;"><?php echo count($danios); ?></div>
                                <button class="modern-btn modern-btn-primary w-75" onclick="mostrarSeccion('danios')">Ver</button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="modern-table-card text-center">
                                <div style="font-size:3rem; color:#426dc9;"><i class="bi bi-people"></i></div>
                                <div class="modern-label" style="font-size:1.3rem;">Usuarios</div>
                                <div style="font-size:2.5rem; font-weight:700; margin:1rem 0;">4</div>
                                <button class="modern-btn modern-btn-primary w-75" onclick="mostrarSeccion('usuarios')">Ver</button>
                            </div>
                        </div>
                    </div>
                    <div id="seccion-danios" style="display:none;">
                        <button class="modern-btn btn-secondary mb-3" onclick="volverDashboard()"><i class="bi bi-arrow-left"></i> Regresar</button>
                        <div class="vehiculo-card mb-4">
                            <div class="vehiculo-info">
                                <div>
                                    <div class="vehiculo-label">Marca</div>
                                    <div class="vehiculo-value"><?php echo htmlspecialchars($marca); ?></div>
                                </div>
                                <div>
                                    <div class="vehiculo-label">Modelo</div>
                                    <div class="vehiculo-value"><?php echo htmlspecialchars($modelo); ?></div>
                                </div>
                                <div>
                                    <div class="vehiculo-label">Color</div>
                                    <div class="vehiculo-value"><?php echo htmlspecialchars($color); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="modern-table-card">
                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                <span class="modern-label mb-0">Daños Registrados</span>
                                <button type="button" class="modern-btn modern-btn-primary" onclick="mostrarSeccion('registrar-danio')">
                                    <i class="bi bi-plus-lg"></i> Agregar Daño
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table modern-table mb-2">
                                    <thead>
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
                                                <button type="button" class="modern-btn modern-btn-warning btn-sm me-1" title="Editar" data-bs-toggle="modal" data-bs-target="#modalEditarDanio<?php echo $d['ID']; ?>">
                                                    <span class="bi bi-pencil-square"></span>
                                                </button>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="id_danio" value="<?php echo $d['ID']; ?>">
                                                    <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                                    <button type="submit" name="eliminar_danio" class="modern-btn modern-btn-danger btn-sm" title="Eliminar" onclick="return confirm('¿Seguro que deseas eliminar este daño?');">
                                                        <span class="bi bi-trash-fill"></span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <!-- Modal editar daño -->
                                        <div class="modal fade" id="modalEditarDanio<?php echo $d['ID']; ?>" tabindex="-1" aria-labelledby="modalEditarLabel<?php echo $d['ID']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content modern-modal-content">
                                                    <div class="modal-header modern-modal-header-warning">
                                                        <h5 class="modal-title" id="modalEditarLabel<?php echo $d['ID']; ?>">Editar Daño</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form method="post" class="d-flex flex-column gap-3">
                                                            <input type="hidden" name="id_danio" value="<?php echo $d['ID']; ?>">
                                                            <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                                            <div>
                                                                <label class="modern-label">Tipo de Daño</label>
                                                                <select name="tipo" class="form-control modern-input" required>
                                                                    <option value="">Seleccione</option>
                                                                    <?php foreach ($tipos as $t) echo '<option value="'.$t['CodTipoDano'].'"'.($t['NomTipoDano']==$d['NomTipoDano']?' selected':'').'>'.$t['NomTipoDano'].'</option>'; ?>
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="modern-label">Área de Daño</label>
                                                                <select name="area" class="form-control modern-input" required>
                                                                    <option value="">Seleccione</option>
                                                                    <?php foreach ($areas as $a) echo '<option value="'.$a['CodAreaDano'].'"'.($a['NomAreaDano']==$d['NomAreaDano']?' selected':'').'>'.$a['NomAreaDano'].'</option>'; ?>
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="modern-label">Severidad</label>
                                                                <select name="severidad" class="form-control modern-input" required>
                                                                    <option value="">Seleccione</option>
                                                                    <?php foreach ($severidades as $s) echo '<option value="'.$s['CodSeveridadDano'].'"'.($s['NomSeveridadDano']==$d['NomSeveridadDano']?' selected':'').'>'.$s['NomSeveridadDano'].'</option>'; ?>
                                                                </select>
                                                            </div>
                                                            <div class="d-grid gap-2 mt-2">
                                                                <button type="submit" name="editar_danio" class="modern-btn modern-btn-warning">Guardar Cambios</button>
                                                                <button type="button" class="modern-btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
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
                    </div>
                    <div id="seccion-registrar-danio" style="display:none;">
                        <button class="modern-btn btn-secondary mb-3" onclick="volverDashboard()"><i class="bi bi-arrow-left"></i> Regresar</button>
                        <div class="modern-table-card">
                            <h5 class="modern-label">Registrar Daño</h5>
                            <form method="post" class="d-flex flex-column gap-3">
                                <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                <div>
                                    <label class="modern-label">Tipo de Daño</label>
                                    <select name="tipo" class="form-control modern-input" required>
                                        <option value="">Seleccione</option>
                                        <?php foreach ($tipos as $t) echo '<option value="'.$t['CodTipoDano'].'">'.$t['NomTipoDano'].'</option>'; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="modern-label">Área de Daño</label>
                                    <select name="area" class="form-control modern-input" required>
                                        <option value="">Seleccione</option>
                                        <?php foreach ($areas as $a) echo '<option value="'.$a['CodAreaDano'].'">'.$a['NomAreaDano'].'</option>'; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="modern-label">Severidad</label>
                                    <select name="severidad" class="form-control modern-input" required>
                                        <option value="">Seleccione</option>
                                        <?php foreach ($severidades as $s) echo '<option value="'.$s['CodSeveridadDano'].'">'.$s['NomSeveridadDano'].'</option>'; ?>
                                    </select>
                                </div>
                                <div class="d-grid gap-2 mt-2">
                                    <button type="submit" name="guardar_danio" class="modern-btn modern-btn-primary">Guardar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div id="seccion-vehiculos" style="display:none;">
                        <button class="modern-btn btn-secondary mb-3" onclick="volverDashboard()"><i class="bi bi-arrow-left"></i> Regresar</button>
                        <div class="modern-table-card text-center">
                            <div style="font-size:2rem; color:#426dc9;"><i class="bi bi-truck"></i></div>
                            <div class="modern-label" style="font-size:1.3rem;">Gestión de Vehículos</div>
                            <div class="text-muted">(Aquí puedes mostrar la gestión/listado de vehículos)</div>
                        </div>
                    </div>
                    <div id="seccion-usuarios" style="display:none;">
                        <button class="modern-btn btn-secondary mb-3" onclick="volverDashboard()"><i class="bi bi-arrow-left"></i> Regresar</button>
                        <div class="modern-table-card text-center">
                            <div style="font-size:2rem; color:#426dc9;"><i class="bi bi-people"></i></div>
                            <div class="modern-label" style="font-size:1.3rem;">Gestión de Usuarios</div>
                            <div class="text-muted">(Aquí puedes mostrar la gestión/listado de usuarios)</div>
                        </div>
                    </div>
            <script>
            function mostrarSeccion(seccion) {
                document.getElementById('dashboard-cards').style.display = 'none';
                document.getElementById('seccion-danios').style.display = 'none';
                document.getElementById('seccion-registrar-danio').style.display = 'none';
                document.getElementById('seccion-vehiculos').style.display = 'none';
                document.getElementById('seccion-usuarios').style.display = 'none';
                if (seccion === 'danios') {
                    document.getElementById('seccion-danios').style.display = 'block';
                } else if (seccion === 'registrar-danio') {
                    document.getElementById('seccion-registrar-danio').style.display = 'block';
                } else if (seccion === 'vehiculos') {
                    document.getElementById('seccion-vehiculos').style.display = 'block';
                } else if (seccion === 'usuarios') {
                    document.getElementById('seccion-usuarios').style.display = 'block';
                }
            }
            function volverDashboard() {
                document.getElementById('dashboard-cards').style.display = 'flex';
                document.getElementById('seccion-danios').style.display = 'none';
                document.getElementById('seccion-registrar-danio').style.display = 'none';
                document.getElementById('seccion-vehiculos').style.display = 'none';
                document.getElementById('seccion-usuarios').style.display = 'none';
            }
            </script>
            color: #333;
            border: none;
        }
        .modern-btn-warning:hover {
            background: linear-gradient(90deg, #ffd200 0%, #f7971e 100%);
            color: #222;
        }
        .modern-btn-danger {
            background: linear-gradient(90deg, #f857a6 0%, #ff5858 100%);
            color: #fff;
            border: none;
        }
        .modern-btn-danger:hover {
            background: linear-gradient(90deg, #ff5858 0%, #f857a6 100%);
            color: #fff;
        }
        .modern-modal-header-warning {
            background: linear-gradient(90deg, #ffd200 60%, #f7971e 100%);
            color: #333;
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
        }
        .modern-modal-header-primary {
            background: linear-gradient(90deg, #426dc9 60%, #6a82fb 100%);
            color: #fff;
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
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
</br>
        <div class="col-md-9 col-lg-10 main-content">
            <div class="row mb-4 justify-content-center">
                <div class="col-12 d-flex justify-content-center">
                    <div class="modern-card">
                        <form method="post" id="formBuscar" class="d-flex flex-row gap-3 align-items-end" style="width: 100%;">
                            <div class="flex-grow-1">
                                <label class="modern-label">VIN</label>
                                <input type="text" id="qrInput" name="vin" class="modern-input" value="<?php echo htmlspecialchars($vin); ?>" required autofocus placeholder="Escanea o ingresa el VIN">
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="modern-btn modern-btn-success" data-bs-toggle="modal" data-bs-target="#modalQR" title="Escanear VIN">
                                    <i class="bi bi-camera" style="font-size: 1.5rem;"></i>
                                </button>
                            </div>
                            <div>
                                <button type="submit" name="buscar_vin" class="modern-btn modern-btn-primary">Buscar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Modal QR para escanear VIN -->
            <div class="modal fade" id="modalQR" tabindex="-1" aria-labelledby="modalQRLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content modern-modal-content">
                        <div class="modal-header modern-modal-header">
                            <span class="modern-modal-icon bi bi-qr-code-scan"></span>
                            <h5 class="modal-title mb-0" id="modalQRLabel">Escanear VIN (QR)</h5>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="qr-help" id="qr-help-msg">Apunta la cámara al código QR del VIN</div>
                            <div id="qr-reader" style="width:100%; min-height:300px;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($errores): ?>
                <div class="alert alert-danger py-2"><?php echo implode('<br>', $errores); ?></div>
            <?php endif; ?>
            <?php if ($marca): ?>
                                <div class="vehiculo-card mb-4">
                                        <div class="vehiculo-info">
                                                <div>
                                                        <div class="vehiculo-label">Marca</div>
                                                        <div class="vehiculo-value"><?php echo htmlspecialchars($marca); ?></div>
                                                </div>
                                                <div>
                                                        <div class="vehiculo-label">Modelo</div>
                                                        <div class="vehiculo-value"><?php echo htmlspecialchars($modelo); ?></div>
                                                </div>
                                                <div>
                                                        <div class="vehiculo-label">Color</div>
                                                        <div class="vehiculo-value"><?php echo htmlspecialchars($color); ?></div>
                                                </div>
                                        </div>
                                </div>
                                <!-- Tabs para registrar/ver daños -->
                                <ul class="nav nav-tabs mb-3" id="daniosTab" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="ver-danios-tab" data-bs-toggle="tab" data-bs-target="#ver-danios" type="button" role="tab" aria-controls="ver-danios" aria-selected="true">
                                            Daños Registrados
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="registrar-danio-tab" data-bs-toggle="tab" data-bs-target="#registrar-danio" type="button" role="tab" aria-controls="registrar-danio" aria-selected="false">
                                            Registrar Daño
                                        </button>
                                    </li>
                                </ul>
                                <div class="tab-content" id="daniosTabContent">
                                    <div class="tab-pane fade show active" id="ver-danios" role="tabpanel" aria-labelledby="ver-danios-tab">
                                        <div class="modern-table-card">
                                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                                <span class="modern-label mb-0">Daños Registrados</span>
                                                <button type="button" class="modern-btn modern-btn-primary" data-bs-toggle="tab" data-bs-target="#registrar-danio">
                                                    <i class="bi bi-plus-lg"></i> Agregar Daño
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table modern-table mb-2">
                                                    <thead>
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
                                                                <button type="button" class="modern-btn modern-btn-warning btn-sm me-1" title="Editar" data-bs-toggle="modal" data-bs-target="#modalEditarDanio<?php echo $d['ID']; ?>">
                                                                    <span class="bi bi-pencil-square"></span>
                                                                </button>
                                                                <form method="post" style="display:inline;">
                                                                    <input type="hidden" name="id_danio" value="<?php echo $d['ID']; ?>">
                                                                    <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                                                    <button type="submit" name="eliminar_danio" class="modern-btn modern-btn-danger btn-sm" title="Eliminar" onclick="return confirm('¿Seguro que deseas eliminar este daño?');">
                                                                        <span class="bi bi-trash-fill"></span>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                        <!-- Modal editar daño -->
                                                        <div class="modal fade" id="modalEditarDanio<?php echo $d['ID']; ?>" tabindex="-1" aria-labelledby="modalEditarLabel<?php echo $d['ID']; ?>" aria-hidden="true">
                                                            <div class="modal-dialog modal-dialog-centered">
                                                                <div class="modal-content modern-modal-content">
                                                                    <div class="modal-header modern-modal-header-warning">
                                                                        <h5 class="modal-title" id="modalEditarLabel<?php echo $d['ID']; ?>">Editar Daño</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <form method="post" class="d-flex flex-column gap-3">
                                                                            <input type="hidden" name="id_danio" value="<?php echo $d['ID']; ?>">
                                                                            <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                                                            <div>
                                                                                <label class="modern-label">Tipo de Daño</label>
                                                                                <select name="tipo" class="form-control modern-input" required>
                                                                                    <option value="">Seleccione</option>
                                                                                    <?php foreach ($tipos as $t) echo '<option value="'.$t['CodTipoDano'].'"'.($t['NomTipoDano']==$d['NomTipoDano']?' selected':'').'>'.$t['NomTipoDano'].'</option>'; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div>
                                                                                <label class="modern-label">Área de Daño</label>
                                                                                <select name="area" class="form-control modern-input" required>
                                                                                    <option value="">Seleccione</option>
                                                                                    <?php foreach ($areas as $a) echo '<option value="'.$a['CodAreaDano'].'"'.($a['NomAreaDano']==$d['NomAreaDano']?' selected':'').'>'.$a['NomAreaDano'].'</option>'; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div>
                                                                                <label class="modern-label">Severidad</label>
                                                                                <select name="severidad" class="form-control modern-input" required>
                                                                                    <option value="">Seleccione</option>
                                                                                    <?php foreach ($severidades as $s) echo '<option value="'.$s['CodSeveridadDano'].'"'.($s['NomSeveridadDano']==$d['NomSeveridadDano']?' selected':'').'>'.$s['NomSeveridadDano'].'</option>'; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="d-grid gap-2 mt-2">
                                                                                <button type="submit" name="editar_danio" class="modern-btn modern-btn-warning">Guardar Cambios</button>
                                                                                <button type="button" class="modern-btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
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
                                    </div>
                                    <div class="tab-pane fade" id="registrar-danio" role="tabpanel" aria-labelledby="registrar-danio-tab">
                                        <div class="modern-table-card">
                                            <h5 class="modern-label">Registrar Daño</h5>
                                            <form method="post" class="d-flex flex-column gap-3">
                                                <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                                <div>
                                                    <label class="modern-label">Tipo de Daño</label>
                                                    <select name="tipo" class="form-control modern-input" required>
                                                        <option value="">Seleccione</option>
                                                        <?php foreach ($tipos as $t) echo '<option value="'.$t['CodTipoDano'].'">'.$t['NomTipoDano'].'</option>'; ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="modern-label">Área de Daño</label>
                                                    <select name="area" class="form-control modern-input" required>
                                                        <option value="">Seleccione</option>
                                                        <?php foreach ($areas as $a) echo '<option value="'.$a['CodAreaDano'].'">'.$a['NomAreaDano'].'</option>'; ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="modern-label">Severidad</label>
                                                    <select name="severidad" class="form-control modern-input" required>
                                                        <option value="">Seleccione</option>
                                                        <?php foreach ($severidades as $s) echo '<option value="'.$s['CodSeveridadDano'].'">'.$s['NomSeveridadDano'].'</option>'; ?>
                                                    </select>
                                                </div>
                                                <div class="d-grid gap-2 mt-2">
                                                    <button type="submit" name="guardar_danio" class="modern-btn modern-btn-primary">Guardar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
let html5QrCode;

// Iniciar el escáner QR cuando el modal esté completamente visible
document.getElementById('modalQR').addEventListener('shown.bs.modal', function () {
    const qrReader = document.getElementById("qr-reader");
    document.getElementById('qr-help-msg').style.display = 'block';
    document.getElementById('qr-help-msg').innerText = 'Apunta la cámara al código QR del VIN';
    html5QrCode = new Html5Qrcode("qr-reader");
    html5QrCode.start(
        { facingMode: "environment" },
        {
            fps: 10,
            qrbox: { width: 250, height: 250 }
        },
        qrCodeMessage => {
            document.getElementById("qrInput").value = qrCodeMessage;
            html5QrCode.stop().then(() => {
                // Limpiar y ocultar el lector
                qrReader.innerHTML = '';
            });
            document.getElementById("modalQR").classList.remove('show');
            document.body.classList.remove('modal-open');
            document.getElementById("formBuscar").submit();
        },
        errorMessage => {
            // errores silenciosos
        }
    ).catch(err => {
        document.getElementById('qr-help-msg').innerText = 'No se pudo acceder a la cámara trasera';
        console.error(err);
    });
});

// Detener el escáner QR al cerrar el modal
document.getElementById('modalQR').addEventListener('hidden.bs.modal', function () {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            html5QrCode = null;
            document.getElementById('qr-reader').innerHTML = '';
        }).catch(() => {
            html5QrCode = null;
            document.getElementById('qr-reader').innerHTML = '';
        });
    } else {
        document.getElementById('qr-reader').innerHTML = '';
    }
});
    </script>
</body>
</html>