<?php
session_start();
// Habilitar reporte de errores para depuración temporal
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$debug = true; // poner a false en producción
include 'database_connection.php';
// Helper: buscar ID existente de 'Revisado' en una tabla de referencia
function findRevisadoId($conn, $table, $codeCol, $nameCol) {
    $name = 'Revisado';
    $nameEsc = $conn->real_escape_string($name);
    $sql = "SELECT $codeCol AS id FROM $table WHERE $nameCol = '$nameEsc' LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_assoc()) return $row['id'];
    return null;
}

// Helper: crear fila 'Revisado' y devolver su ID
function createRevisadoIfMissing($conn, $table, $codeCol, $nameCol) {
    $existing = findRevisadoId($conn, $table, $codeCol, $nameCol);
    if ($existing !== null) return intval($existing);
    $name = 'Revisado';
    $nameEsc = $conn->real_escape_string($name);
    $sql = "INSERT INTO $table ($nameCol) VALUES ('$nameEsc')";
    if ($conn->query($sql)) {
        return intval($conn->insert_id);
    }
    return null;
}

// Cargar IDs sentinel (si existen) para mapearlos a 0 en la vista
$sentinelAreaId = findRevisadoId($conn, 'areadano', 'CodAreaDano', 'NomAreaDano');
$sentinelTipoId = findRevisadoId($conn, 'tipodano', 'CodTipoDano', 'NomTipoDano');
$sentinelSeveridadId = findRevisadoId($conn, 'severidaddano', 'CodSeveridadDano', 'NomSeveridadDano');

// Inicializar variables
$vin = $marca = $modelo = $color = '';
$errores = [];
$danios = [];
$show_form = false;
$usuario_id = $_SESSION['id'] ?? null;
$tipo_operacion = $_SESSION['tipo_operacion'] ?? '';

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
        $sql = "SELECT r.ID, a.CodAreaDano, a.NomAreaDano, t.CodTipoDano, t.NomTipoDano, s.CodSeveridadDano, s.NomSeveridadDano, r.TipoOperacion FROM RegistroDanio r
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
// Marcar como revisado: insertar un nuevo registro con area/tipo/severidad = 0 para el VIN
if (isset($_POST['marcar_revisado'])) {
    $vin_revisado = isset($_POST['vin']) ? trim($_POST['vin']) : '';
        if ($vin_revisado) {
        $tipo_operacion_to_use = !empty($tipo_operacion) ? $tipo_operacion : 'Revisado';
        // Crear o obtener las filas 'Revisado' en tablas de referencia para poder usar sus IDs (cumplir FK)
        $areaId = createRevisadoIfMissing($conn, 'areadano', 'CodAreaDano', 'NomAreaDano');
        $tipoId = createRevisadoIfMissing($conn, 'tipodano', 'CodTipoDano', 'NomTipoDano');
        $severId = createRevisadoIfMissing($conn, 'severidaddano', 'CodSeveridadDano', 'NomSeveridadDano');

        if ($areaId === null || $tipoId === null || $severId === null) {
            error_log('No se pudieron crear filas Revisado en tablas de referencia');
            if (!empty($debug)) echo '<pre>No se pudieron crear filas Revisado en tablas de referencia</pre>';
            exit();
        }

        if (!empty($usuario_id)) {
            $usuario_val = intval($usuario_id);
            $stmt = $conn->prepare("INSERT INTO RegistroDanio (VIN, CodAreaDano, CodTipoDano, CodSeveridadDano, UsuarioID, TipoOperacion) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('siiiis', $vin_revisado, $areaId, $tipoId, $severId, $usuario_val, $tipo_operacion_to_use);
                if (!$stmt->execute()) {
                    error_log('Ejecutar INSERT Revisado fallo (con usuario): ' . $stmt->error);
                    if (!empty($debug)) echo '<pre>Ejecutar INSERT Revisado fallo (con usuario): ' . htmlspecialchars($stmt->error) . '</pre>';
                    $stmt->close();
                    exit();
                }
                $stmt->close();
            } else {
                error_log('Preparar INSERT Revisado fallo (con usuario): ' . $conn->error);
                if (!empty($debug)) echo '<pre>Preparar INSERT Revisado fallo (con usuario): ' . htmlspecialchars($conn->error) . '</pre>';
                exit();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO RegistroDanio (VIN, CodAreaDano, CodTipoDano, CodSeveridadDano, UsuarioID, TipoOperacion) VALUES (?, ?, ?, ?, NULL, ?)");
            if ($stmt) {
                $stmt->bind_param('siiis', $vin_revisado, $areaId, $tipoId, $severId, $tipo_operacion_to_use);
                if (!$stmt->execute()) {
                    error_log('Ejecutar INSERT Revisado fallo (sin usuario): ' . $stmt->error);
                    if (!empty($debug)) echo '<pre>Ejecutar INSERT Revisado fallo (sin usuario): ' . htmlspecialchars($stmt->error) . '</pre>';
                    $stmt->close();
                    exit();
                }
                $stmt->close();
            } else {
                error_log('Preparar INSERT Revisado fallo (sin usuario): ' . $conn->error);
                if (!empty($debug)) echo '<pre>Preparar INSERT Revisado fallo (sin usuario): ' . htmlspecialchars($conn->error) . '</pre>';
                exit();
            }
        }
    }
    if ($vin_revisado) {
        header("Location: Registro_Daños.php?vin=" . urlencode($vin_revisado));
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
    if (!$usuario_id || $tipo_operacion === '') {
        $errores[] = 'Debe iniciar sesión y seleccionar el tipo de operación.';
        $show_form = true;
    } elseif ($vin && $area && $tipo && $severidad) {
        $stmt = $conn->prepare("INSERT INTO RegistroDanio (VIN, CodAreaDano, CodTipoDano, CodSeveridadDano, UsuarioID, TipoOperacion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('siiiis', $vin, $area, $tipo, $severidad, $usuario_id, $tipo_operacion);
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
                    box-shadow: 0 2px 8px 0 rgba(60,60,120,0.07);
                }
                .modern-table thead {
                    background: linear-gradient(90deg, #426dc9 60%, #6a82fb 100%);
                    color: #fff;
                    font-size: 1.08rem;
                    letter-spacing: 0.5px;
                }
                .modern-table th, .modern-table td {
                    vertical-align: middle;
                    font-size: 1.08rem;
                }
                .modern-table th {
                    border: none;
                }
                .modern-table td {
                    background: #fff;
                    border-top: 1px solid #e0e6f7;
                }
                .modern-table tbody tr:hover {
                    background: #f0f4ff;
                    transition: background 0.2s;
                }
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
        .modern-table-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px 0 rgba(60,60,120,0.10);
            padding: 2rem 1.5rem 1.5rem 1.5rem;
            margin-bottom: 2rem;
        }
        .modern-table {
            border-radius: 12px;
            overflow: hidden;
            background: #f8f9fa;
        }
        .modern-table thead {
            background: linear-gradient(90deg, #426dc9 60%, #6a82fb 100%);
            color: #fff;
        }
        .modern-table th, .modern-table td {
            vertical-align: middle;
            font-size: 1.08rem;
        }
        .modern-table th {
            border: none;
        }
        .modern-table td {
            background: #fff;
            border-top: 1px solid #e0e6f7;
        }
        .modern-btn-warning {
            background: linear-gradient(90deg, #f7971e 0%, #ffd200 100%);
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
        .modal-body {
            overflow: visible !important;
            padding: 1.5rem;
        }
        .modal-content {
            overflow: visible;
        }
        .modal-dialog {
            overflow: visible;
        }
        .modal.show .modal-dialog {
            overflow: visible;
        }
        .modal-body select.modern-input {
            font-size: 1rem;
            padding: 0.6rem 0.8rem;
            height: auto;
            min-height: 38px;
            position: relative;
            z-index: 1060;
        }
        .modal-body select.modern-input option {
            padding: 0.5rem;
            line-height: 1.5;
        }
        .same-size-btn {
            min-width: 140px;
            padding: 0.7rem 1.5rem;
            font-size: 1.1rem;
            border-radius: 12px;
        }
        @media (max-width: 768px) {
            #formBuscar {
                flex-direction: column !important;
                align-items: stretch !important;
            }
            #formBuscar .flex-grow-1,
            #formBuscar .vin-actions,
            #formBuscar .vin-submit {
                width: 100% !important;
            }
            #formBuscar .vin-actions {
                justify-content: space-between;
            }
            #formBuscar .modern-input {
                width: 100% !important;
            }
            #formBuscar .modern-btn {
                width: 100% !important;
            }
            .vehiculo-card {
                flex-direction: column;
                align-items: flex-start;
            }
            .vehiculo-info {
                flex-direction: column;
                gap: 1rem;
            }
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
                            <div class="d-flex align-items-end gap-2 vin-actions"></div>
                            <div class="vin-submit">
                                <button type="submit" name="buscar_vin" class="modern-btn modern-btn-primary">Buscar</button>
                            </div>
                        </form>
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
                <div class="row">
                    <div class="col-12">
                        <div class="modern-table-card">
                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                <span class="modern-label mb-0">Daños Registrados</span>
                                <div class="d-flex gap-2">
                                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Marcar como revisado? Esto pondrá área, tipo y severidad en 0 para todos los daños de este VIN.');">
                                        <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                        <button type="submit" name="marcar_revisado" class="modern-btn modern-btn-success same-size-btn">
                                            <i class="bi bi-check2-circle"></i> Revisado
                                        </button>
                                    </form>
                                    <button type="button" class="modern-btn modern-btn-primary" data-bs-toggle="modal" data-bs-target="#modalDanio">
                                        <i class="bi bi-plus-lg"></i> Agregar Daño
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table modern-table mb-2">
                                    <thead>
                                        <tr>
                                            <th>Origen</th>
                                            <th>Área</th>
                                            <th>Tipo</th>
                                            <th>Severidad</th>
                                            <th style="width:120px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($danios)): foreach ($danios as $d): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($d['TipoOperacion']); ?></td>
                                                <td><?php echo htmlspecialchars((isset($sentinelAreaId) && $sentinelAreaId !== null && $d['CodAreaDano']==$sentinelAreaId) ? '0' : $d['CodAreaDano']); ?></td>
                                                <td><?php echo htmlspecialchars((isset($sentinelTipoId) && $sentinelTipoId !== null && $d['CodTipoDano']==$sentinelTipoId) ? '0' : $d['CodTipoDano']); ?></td>
                                                <td><?php echo htmlspecialchars((isset($sentinelSeveridadId) && $sentinelSeveridadId !== null && $d['CodSeveridadDano']==$sentinelSeveridadId) ? '0' : $d['CodSeveridadDano']); ?></td>
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
                                            <div class="modal-dialog modal-dialog-scrollable">
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
                                        <tr><td colspan="5" class="text-center">Sin daños registrados</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Modal para registrar daño -->
                <div class="modal fade" id="modalDanio" tabindex="-1" aria-labelledby="modalDanioLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content modern-modal-content">
                            <div class="modal-header modern-modal-header-primary">
                                <h5 class="modal-title" id="modalDanioLabel">Registrar Daño</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <form method="post" class="d-flex flex-column gap-3">
                                    <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                    <div>
                                        <label class="modern-label">Área de Daño</label>
                                        <select name="area" class="form-control modern-input" required>
                                            <option value="">Seleccione</option>
                                            <?php foreach ($areas as $a) echo '<option value="'.$a['CodAreaDano'].'">'.$a['NomAreaDano'].'</option>'; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="modern-label">Tipo de Daño</label>
                                        <select name="tipo" class="form-control modern-input" required>
                                            <option value="">Seleccione</option>
                                            <?php foreach ($tipos as $t) echo '<option value="'.$t['CodTipoDano'].'">'.$t['NomTipoDano'].'</option>'; ?>
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
                                        <button type="submit" name="guardar_danio" class="modern-btn modern-btn-primary same-size-btn">Guardar</button>
                                        <button type="button" class="modern-btn btn-secondary" data-bs-dismiss="modal">Regresar</button>
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