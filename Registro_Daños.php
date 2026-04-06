<?php
session_start();
date_default_timezone_set('America/Mexico_City');
// Habilitar reporte de errores para depuración temporal
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$debug = true; // poner a false en producción
include 'database_connection.php';
require_once 'access_control.php';
require_module_access($conn, 'danos');
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

function formatDamageOptionLabel($code, $name) {
    return trim((string)$code) . ' - ' . trim((string)$name);
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
$puerto_sesion = $_SESSION['puerto'] ?? '';
$puerto_vehiculo = '';
$is_admin = false;

if (!empty($usuario_id)) {
    $stmt_admin = $conn->prepare("SELECT 1 FROM usuario_rol ur JOIN roles r ON ur.rol_id = r.id WHERE ur.usuario_id = ? AND LOWER(r.nombre) = 'administrador' LIMIT 1");
    if ($stmt_admin) {
        $uid = intval($usuario_id);
        $stmt_admin->bind_param('i', $uid);
        $stmt_admin->execute();
        $stmt_admin->store_result();
        $is_admin = $stmt_admin->num_rows > 0;
        $stmt_admin->close();
    }
}

// Buscar VIN (por POST, GET o contexto de acción)
if (isset($_POST['buscar_vin'])) {
    $vin = trim($_POST['vin']);
} elseif (isset($_GET['vin'])) {
    $vin = trim($_GET['vin']);
} elseif (isset($_POST['vin'])) {
    $vin = trim($_POST['vin']);
}

if ($vin) {
    $stmt = $conn->prepare("SELECT Marca, Modelo, Color, Puerto FROM vehiculo WHERE VIN = ?");
    $stmt->bind_param('s', $vin);
    $stmt->execute();
    $stmt->bind_result($marca, $modelo, $color, $puerto_db);
    if ($stmt->fetch()) {
        $puerto_vehiculo = $puerto_db;
        // Buscar daños registrados con descripciones
        $stmt->close();
        $sql = "SELECT r.ID, r.TipoOperacion AS Origen, r.Puerto AS Puerto, a.CodAreaDano, a.NomAreaDano, t.CodTipoDano, t.NomTipoDano, s.CodSeveridadDano, s.NomSeveridadDano FROM RegistroDanio r
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
    if (!$is_admin) {
        $errores[] = 'No tienes permisos para eliminar danos.';
    } else {
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
            $stmt = $conn->prepare("INSERT INTO RegistroDanio (VIN, CodAreaDano, CodTipoDano, CodSeveridadDano, UsuarioID, TipoOperacion, Puerto) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('siiiiss', $vin_revisado, $areaId, $tipoId, $severId, $usuario_val, $tipo_operacion_to_use, $puerto_sesion);
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
            $stmt = $conn->prepare("INSERT INTO RegistroDanio (VIN, CodAreaDano, CodTipoDano, CodSeveridadDano, UsuarioID, TipoOperacion, Puerto) VALUES (?, ?, ?, ?, NULL, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('siiiss', $vin_revisado, $areaId, $tipoId, $severId, $tipo_operacion_to_use, $puerto_sesion);
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
        $stmt = $conn->prepare("INSERT INTO RegistroDanio (VIN, CodAreaDano, CodTipoDano, CodSeveridadDano, UsuarioID, TipoOperacion, Puerto) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('siiiiss', $vin, $area, $tipo, $severidad, $usuario_id, $tipo_operacion, $puerto_sesion);
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
    if (!$is_admin) {
        $errores[] = 'No tienes permisos para editar danos.';
    } else {
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
}

// Cargar listas para selects con descripciones
$areasRes = $conn->query("SELECT CodAreaDano, NomAreaDano FROM areadano ORDER BY CodAreaDano");
$tiposRes = $conn->query("SELECT CodTipoDano, NomTipoDano FROM tipodano ORDER BY CodTipoDano");
$severidadesRes = $conn->query("SELECT CodSeveridadDano, NomSeveridadDano FROM severidaddano ORDER BY CodSeveridadDano");

$areasList = $areasRes ? $areasRes->fetch_all(MYSQLI_ASSOC) : [];
$tiposList = $tiposRes ? $tiposRes->fetch_all(MYSQLI_ASSOC) : [];
$severidadesList = $severidadesRes ? $severidadesRes->fetch_all(MYSQLI_ASSOC) : [];
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
                    margin-bottom: 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 2rem;
                }
                .vehiculo-info {
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                    width: 100%;
                }
                .vehiculo-row {
                    display: flex;
                    gap: 2.5rem;
                    width: 100%;
                    justify-content: space-between;
                }
                .vehiculo-row > div {
                    flex: 1 1 50%;
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
                    margin-bottom: 0;
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
        .main-content {
            padding-top: 0;
            margin-top: 0;
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
        .vin-hint {
            margin-top: 0.45rem;
            color: #5b6b8a;
            font-size: 0.92rem;
            font-weight: 500;
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
            margin-bottom: 0;
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
        .searchable-dropdown {
            position: relative;
        }
        .searchable-dropdown-input {
            font-size: 1rem;
            padding: 0.72rem 2.85rem 0.72rem 0.95rem;
            border-radius: 12px;
            border: 1px solid #d8e1f3;
            letter-spacing: 0.2px;
            text-align: left;
            background-image: linear-gradient(45deg, transparent 50%, #4f628d 50%), linear-gradient(135deg, #4f628d 50%, transparent 50%);
            background-position: calc(100% - 22px) calc(50% - 3px), calc(100% - 16px) calc(50% - 3px);
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
        }
        .searchable-dropdown-input::placeholder {
            color: #7483a8;
        }
        .searchable-dropdown.is-open .searchable-dropdown-input,
        .searchable-dropdown-input:focus {
            border-color: #8db6ff;
            box-shadow: 0 0 0 0.2rem rgba(66, 109, 201, 0.12);
            outline: none;
        }
        .searchable-dropdown-menu {
            position: absolute;
            top: calc(100% + 0.45rem);
            left: 0;
            right: 0;
            z-index: 1080;
            display: none;
            max-height: 220px;
            overflow-y: auto;
            padding: 0.45rem;
            border-radius: 14px;
            background: #fff;
            border: 1px solid #d8e1f3;
            box-shadow: 0 14px 32px rgba(40, 64, 120, 0.18);
        }
        .searchable-dropdown.is-open .searchable-dropdown-menu {
            display: block;
        }
        .searchable-dropdown-option {
            width: 100%;
            border: none;
            background: transparent;
            text-align: left;
            padding: 0.8rem 0.85rem;
            border-radius: 10px;
            color: #233150;
            font-size: 0.97rem;
            line-height: 1.35;
        }
        .searchable-dropdown-option:hover,
        .searchable-dropdown-option.is-active {
            background: #edf3ff;
            color: #244f9c;
        }
        .searchable-dropdown-empty {
            display: none;
            padding: 0.8rem 0.85rem;
            color: #6a7596;
            font-size: 0.92rem;
        }
        .searchable-dropdown-empty.is-visible {
            display: block;
        }
        .searchable-dropdown-help {
            margin-top: 0.45rem;
            color: #6a7596;
            font-size: 0.88rem;
            font-weight: 500;
        }
        .searchable-dropdown-invalid .searchable-dropdown-input {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.12);
        }
        .same-size-btn {
            width: 160px !important;
            padding: 0.6rem 0.8rem !important;
            font-size: 1.05rem !important;
            border-radius: 12px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            height: 44px !important;
            gap: 0.4rem !important;
        }
        .modern-btn.same-size-btn {
            box-shadow: 0 2px 8px 0 rgba(60,60,120,0.08) !important;
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
            .vehiculo-row {
                flex-direction: row;
                gap: 1.25rem;
            }
            .searchable-dropdown-input {
                font-size: 1rem;
            }
            .searchable-dropdown-menu {
                max-height: 190px;
            }
            .searchable-dropdown-help {
                font-size: 0.84rem;
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
        <div class="col-md-9 col-lg-10 main-content">
            <div class="row mb-0 justify-content-center">
                <div class="col-12 d-flex justify-content-center">
                    <div class="modern-card">
                        <form method="post" id="formBuscar" class="d-flex flex-row gap-3 align-items-end" style="width: 100%;">
                            <div class="flex-grow-1">
                                <label class="modern-label">VIN</label>
                                <input type="text" id="qrInput" name="vin" class="modern-input" value="<?php echo htmlspecialchars($vin); ?>" required placeholder="Escanea o ingresa el VIN" inputmode="none" autocomplete="off" autocapitalize="off" spellcheck="false">
                                <div id="scanStatus" style="margin-top:0.4rem; color:#236fa1; font-weight:600; font-size:0.94rem; display:none;">Escaneo detectado: buscando...</div>
                                <div id="vinHint" class="vin-hint">Escanea el VIN. En móvil el teclado no debe abrirse automáticamente.</div>
                            </div>
                            <div class="d-flex align-items-end gap-2 vin-actions"></div>
                            <div class="vin-submit">
                                <button type="button" id="finalizarRegistr" class="modern-btn modern-btn-warning">Finalizar y nuevo VIN</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php if ($errores): ?>
                <div class="alert alert-danger py-2"><?php echo implode('<br>', $errores); ?></div>
            <?php endif; ?>
            <?php if ($marca): ?>
                <div class="vehiculo-card">
                    <div class="vehiculo-info">
                        <div class="vehiculo-row">
                            <div>
                                <div class="vehiculo-label">Marca</div>
                                <div class="vehiculo-value"><?php echo htmlspecialchars($marca); ?></div>
                            </div>
                            <div>
                                <div class="vehiculo-label">Puerto</div>
                                <div class="vehiculo-value"><?php echo htmlspecialchars($puerto_vehiculo ?: 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="vehiculo-row">
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
                </div>
                <div class="modern-table-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0">Daños Registrados</h4>
                        <div class="d-flex gap-2">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                <button type="submit" name="marcar_revisado" class="btn btn-sm modern-btn modern-btn-success same-size-btn">
                                    <i class="bi bi-check2-circle"></i> Revisado
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm modern-btn modern-btn-danger same-size-btn" data-bs-toggle="modal" data-bs-target="#modalAgregarDanio">
                                <i class="bi bi-plus-lg"></i> Agregar Daño
                            </button>
                        </div>
                    </div>
                    <?php if (!empty($danios)): ?>
                        <div class="table-responsive modern-table">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Origen</th>
                                        <th>Puerto</th>
                                        <th>Área</th>
                                        <th>Tipo</th>
                                        <th>Severidad</th>
                                        <?php if ($is_admin): ?>
                                            <th class="text-end">Acciones</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($danios as $danio): ?>
                                        <tr>
                                            <?php
                                                $areaDisplay = (intval($danio['CodAreaDano']) === 100) ? 0 : $danio['CodAreaDano'];
                                                $tipoDisplay = (intval($danio['CodTipoDano']) === 21) ? 0 : $danio['CodTipoDano'];
                                                $sevDisplay = (intval($danio['CodSeveridadDano']) === 7) ? 0 : $danio['CodSeveridadDano'];
                                            ?>
                                            <td><?php echo htmlspecialchars($danio['Origen'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($danio['Puerto'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($areaDisplay); ?></td>
                                            <td><?php echo htmlspecialchars($tipoDisplay); ?></td>
                                            <td><?php echo htmlspecialchars($sevDisplay); ?></td>
                                            <?php if ($is_admin): ?>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm modern-btn modern-btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditarDanio<?php echo intval($danio['ID']); ?>">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="id_danio" value="<?php echo intval($danio['ID']); ?>">
                                                        <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                                        <button type="submit" name="eliminar_danio" class="btn btn-sm modern-btn modern-btn-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($is_admin): ?>
                            <?php foreach ($danios as $danio): ?>
                                <div class="modal fade" id="modalEditarDanio<?php echo intval($danio['ID']); ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content modern-modal-content">
                                            <div class="modal-header modern-modal-header-warning">
                                                <h5 class="modal-title">Editar Daño</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="id_danio" value="<?php echo intval($danio['ID']); ?>">
                                                    <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label modern-label">Área</label>
                                                        <div class="searchable-dropdown" data-searchable-dropdown>
                                                            <?php $selectedAreaLabel = ''; ?>
                                                            <?php foreach ($areasList as $area): ?>
                                                                <?php if (intval($danio['CodAreaDano']) === intval($area['CodAreaDano'])) { $selectedAreaLabel = formatDamageOptionLabel($area['CodAreaDano'], $area['NomAreaDano']); } ?>
                                                            <?php endforeach; ?>
                                                            <input type="hidden" name="area" value="<?php echo intval($danio['CodAreaDano']); ?>" required>
                                                            <input type="text" class="form-control modern-input searchable-dropdown-input" data-searchable-input placeholder="Seleccione área" value="<?php echo htmlspecialchars($selectedAreaLabel); ?>" autocomplete="off" spellcheck="false">
                                                            <div class="searchable-dropdown-menu">
                                                                <div class="searchable-dropdown-empty">No se encontraron coincidencias.</div>
                                                                <?php foreach ($areasList as $area): ?>
                                                                    <?php $areaLabel = formatDamageOptionLabel($area['CodAreaDano'], $area['NomAreaDano']); ?>
                                                                    <button type="button" class="searchable-dropdown-option" data-value="<?php echo intval($area['CodAreaDano']); ?>" data-label="<?php echo htmlspecialchars($areaLabel); ?>"><?php echo htmlspecialchars($areaLabel); ?></button>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="searchable-dropdown-help">Escribe para filtrar y toca una opción de la misma lista.</div>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label modern-label">Tipo</label>
                                                        <div class="searchable-dropdown" data-searchable-dropdown>
                                                            <?php $selectedTipoLabel = ''; ?>
                                                            <?php foreach ($tiposList as $tipo): ?>
                                                                <?php if (intval($danio['CodTipoDano']) === intval($tipo['CodTipoDano'])) { $selectedTipoLabel = formatDamageOptionLabel($tipo['CodTipoDano'], $tipo['NomTipoDano']); } ?>
                                                            <?php endforeach; ?>
                                                            <input type="hidden" name="tipo" value="<?php echo intval($danio['CodTipoDano']); ?>" required>
                                                            <input type="text" class="form-control modern-input searchable-dropdown-input" data-searchable-input placeholder="Seleccione tipo" value="<?php echo htmlspecialchars($selectedTipoLabel); ?>" autocomplete="off" spellcheck="false">
                                                            <div class="searchable-dropdown-menu">
                                                                <div class="searchable-dropdown-empty">No se encontraron coincidencias.</div>
                                                                <?php foreach ($tiposList as $tipo): ?>
                                                                    <?php $tipoLabel = formatDamageOptionLabel($tipo['CodTipoDano'], $tipo['NomTipoDano']); ?>
                                                                    <button type="button" class="searchable-dropdown-option" data-value="<?php echo intval($tipo['CodTipoDano']); ?>" data-label="<?php echo htmlspecialchars($tipoLabel); ?>"><?php echo htmlspecialchars($tipoLabel); ?></button>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="searchable-dropdown-help">Escribe para filtrar y toca una opción de la misma lista.</div>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label modern-label">Severidad</label>
                                                        <div class="searchable-dropdown" data-searchable-dropdown>
                                                            <?php $selectedSeveridadLabel = ''; ?>
                                                            <?php foreach ($severidadesList as $severidad): ?>
                                                                <?php if (intval($danio['CodSeveridadDano']) === intval($severidad['CodSeveridadDano'])) { $selectedSeveridadLabel = formatDamageOptionLabel($severidad['CodSeveridadDano'], $severidad['NomSeveridadDano']); } ?>
                                                            <?php endforeach; ?>
                                                            <input type="hidden" name="severidad" value="<?php echo intval($danio['CodSeveridadDano']); ?>" required>
                                                            <input type="text" class="form-control modern-input searchable-dropdown-input" data-searchable-input placeholder="Seleccione severidad" value="<?php echo htmlspecialchars($selectedSeveridadLabel); ?>" autocomplete="off" spellcheck="false">
                                                            <div class="searchable-dropdown-menu">
                                                                <div class="searchable-dropdown-empty">No se encontraron coincidencias.</div>
                                                                <?php foreach ($severidadesList as $severidad): ?>
                                                                    <?php $severidadLabel = formatDamageOptionLabel($severidad['CodSeveridadDano'], $severidad['NomSeveridadDano']); ?>
                                                                    <button type="button" class="searchable-dropdown-option" data-value="<?php echo intval($severidad['CodSeveridadDano']); ?>" data-label="<?php echo htmlspecialchars($severidadLabel); ?>"><?php echo htmlspecialchars($severidadLabel); ?></button>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="searchable-dropdown-help">Escribe para filtrar y toca una opción de la misma lista.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn modern-btn" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" name="editar_danio" class="btn modern-btn modern-btn-warning">Guardar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">No hay daños registrados para este VIN.</div>
                    <?php endif; ?>
                </div>
                <div class="modal fade" id="modalAgregarDanio" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content modern-modal-content">
                            <div class="modal-header modern-modal-header-primary">
                                <h5 class="modal-title">Agregar Daño</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="vin" value="<?php echo htmlspecialchars($vin); ?>">
                                    <div class="mb-3">
                                        <label class="form-label modern-label">Área</label>
                                        <div class="searchable-dropdown" data-searchable-dropdown>
                                            <input type="hidden" name="area" value="" required>
                                            <input type="text" class="form-control modern-input searchable-dropdown-input" data-searchable-input placeholder="Seleccione área" value="" autocomplete="off" spellcheck="false">
                                            <div class="searchable-dropdown-menu">
                                                <div class="searchable-dropdown-empty">No se encontraron coincidencias.</div>
                                                <?php foreach ($areasList as $area): ?>
                                                    <?php $areaLabel = formatDamageOptionLabel($area['CodAreaDano'], $area['NomAreaDano']); ?>
                                                    <button type="button" class="searchable-dropdown-option" data-value="<?php echo intval($area['CodAreaDano']); ?>" data-label="<?php echo htmlspecialchars($areaLabel); ?>"><?php echo htmlspecialchars($areaLabel); ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="searchable-dropdown-help">Escribe para filtrar y toca una opción de la misma lista.</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label modern-label">Tipo</label>
                                        <div class="searchable-dropdown" data-searchable-dropdown>
                                            <input type="hidden" name="tipo" value="" required>
                                            <input type="text" class="form-control modern-input searchable-dropdown-input" data-searchable-input placeholder="Seleccione tipo" value="" autocomplete="off" spellcheck="false">
                                            <div class="searchable-dropdown-menu">
                                                <div class="searchable-dropdown-empty">No se encontraron coincidencias.</div>
                                                <?php foreach ($tiposList as $tipo): ?>
                                                    <?php $tipoLabel = formatDamageOptionLabel($tipo['CodTipoDano'], $tipo['NomTipoDano']); ?>
                                                    <button type="button" class="searchable-dropdown-option" data-value="<?php echo intval($tipo['CodTipoDano']); ?>" data-label="<?php echo htmlspecialchars($tipoLabel); ?>"><?php echo htmlspecialchars($tipoLabel); ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="searchable-dropdown-help">Escribe para filtrar y toca una opción de la misma lista.</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label modern-label">Severidad</label>
                                        <div class="searchable-dropdown" data-searchable-dropdown>
                                            <input type="hidden" name="severidad" value="" required>
                                            <input type="text" class="form-control modern-input searchable-dropdown-input" data-searchable-input placeholder="Seleccione severidad" value="" autocomplete="off" spellcheck="false">
                                            <div class="searchable-dropdown-menu">
                                                <div class="searchable-dropdown-empty">No se encontraron coincidencias.</div>
                                                <?php foreach ($severidadesList as $severidad): ?>
                                                    <?php $severidadLabel = formatDamageOptionLabel($severidad['CodSeveridadDano'], $severidad['NomSeveridadDano']); ?>
                                                    <button type="button" class="searchable-dropdown-option" data-value="<?php echo intval($severidad['CodSeveridadDano']); ?>" data-label="<?php echo htmlspecialchars($severidadLabel); ?>"><?php echo htmlspecialchars($severidadLabel); ?></button>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="searchable-dropdown-help">Escribe para filtrar y toca una opción de la misma lista.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn modern-btn" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" name="guardar_danio" class="btn modern-btn modern-btn-primary">Guardar</button>
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
    <script>
        (function(){
            const vinInput = document.getElementById('qrInput');
            const formBuscar = document.getElementById('formBuscar');
            const scanStatus = document.getElementById('scanStatus');
            const vinHint = document.getElementById('vinHint');
            const MIN_VIN_LENGTH = 17;
            const isMobileViewport = window.matchMedia('(max-width: 768px)').matches || window.matchMedia('(pointer: coarse)').matches;
            let submitTimeout = null;
            let lastVinSubmitted = vinInput.value.trim();

            function focusVinInput() {
                if (!vinInput) return;
                vinInput.focus({ preventScroll: true });
                try {
                    vinInput.setSelectionRange(vinInput.value.length, vinInput.value.length);
                } catch (error) {
                }
            }

            focusVinInput();

            function clearStatus() {
                if (scanStatus) {
                    scanStatus.style.display = 'none';
                }
            }

            function autoSubmit() {
                const vinValue = vinInput.value.trim();
                if (!vinValue || vinValue === lastVinSubmitted || vinValue.length < MIN_VIN_LENGTH) return;

                if (scanStatus) {
                    scanStatus.textContent = 'Escaneo detectado: buscando...';
                    scanStatus.style.display = 'block';
                }

                lastVinSubmitted = vinValue;
                formBuscar.submit();
            }

            vinInput.addEventListener('input', function() {
                const vinValue = vinInput.value.trim();
                if (submitTimeout) clearTimeout(submitTimeout);

                if (vinValue.length >= MIN_VIN_LENGTH) {
                    submitTimeout = setTimeout(autoSubmit, 80);
                } else {
                    clearStatus();
                }
            });

            vinInput.addEventListener('paste', function() {
                if (submitTimeout) clearTimeout(submitTimeout);
                submitTimeout = setTimeout(autoSubmit, 80);
            });

            vinInput.addEventListener('touchstart', function() {
                if (isMobileViewport) {
                    focusVinInput();
                }
            }, { passive: true });

            const finalizarBtn = document.getElementById('finalizarRegistr');
            if (finalizarBtn) {
                finalizarBtn.addEventListener('click', function() {
                    if (submitTimeout) clearTimeout(submitTimeout);
                    vinInput.value = '';
                    clearStatus();
                    lastVinSubmitted = '';
                    // Retornar a pantalla inicial para buscar otro VIN
                    window.location.href = 'Registro_Daños.php';
                });
            }

            vinInput.addEventListener('focus', clearStatus);
            vinInput.addEventListener('blur', clearStatus);

            if (isMobileViewport && vinHint) {
                vinHint.textContent = 'Escanea el VIN. Si tu navegador abre teclado, vuelve a tocar fuera del campo y escanea.';
            }

            function normalizeSearchValue(value) {
                return value
                    .toString()
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .trim();
            }

            function closeAllSearchableDropdowns(exceptDropdown) {
                document.querySelectorAll('[data-searchable-dropdown]').forEach(function(dropdown) {
                    if (dropdown !== exceptDropdown) {
                        dropdown.classList.remove('is-open');
                    }
                });
            }

            function initSearchableDropdown(dropdown) {
                if (!dropdown || dropdown.dataset.searchableReady === '1') return;

                const hiddenInput = dropdown.querySelector('input[type="hidden"]');
                const textInput = dropdown.querySelector('[data-searchable-input]');
                const emptyState = dropdown.querySelector('.searchable-dropdown-empty');
                const options = Array.from(dropdown.querySelectorAll('.searchable-dropdown-option'));

                if (!hiddenInput || !textInput || options.length === 0) return;

                let selectedLabel = textInput.value.trim();

                function filterOptions(query) {
                    const normalizedQuery = normalizeSearchValue(query);
                    let visibleCount = 0;

                    options.forEach(function(option, index) {
                        const label = option.dataset.label || option.textContent || '';
                        const matches = normalizedQuery === '' || normalizeSearchValue(label).includes(normalizedQuery);
                        option.style.display = matches ? '' : 'none';
                        option.classList.toggle('is-active', matches && visibleCount === 0);
                        if (matches) {
                            visibleCount += 1;
                        }
                    });

                    if (emptyState) {
                        emptyState.classList.toggle('is-visible', visibleCount === 0);
                    }
                }

                function selectOption(option) {
                    hiddenInput.value = option.dataset.value || '';
                    textInput.value = option.dataset.label || option.textContent || '';
                    selectedLabel = textInput.value;
                    dropdown.classList.remove('searchable-dropdown-invalid');
                    filterOptions(textInput.value);
                    dropdown.classList.remove('is-open');
                }

                function resetToSelection() {
                    textInput.value = selectedLabel;
                    filterOptions(textInput.value);
                }

                textInput.addEventListener('focus', function() {
                    closeAllSearchableDropdowns(dropdown);
                    dropdown.classList.add('is-open');
                    filterOptions(textInput.value);
                });

                textInput.addEventListener('input', function() {
                    if (hiddenInput.value && textInput.value.trim() !== selectedLabel) {
                        hiddenInput.value = '';
                    }
                    closeAllSearchableDropdowns(dropdown);
                    dropdown.classList.add('is-open');
                    dropdown.classList.remove('searchable-dropdown-invalid');
                    filterOptions(textInput.value);
                });

                textInput.addEventListener('keydown', function(event) {
                    const visibleOptions = options.filter(function(option) {
                        return option.style.display !== 'none';
                    });

                    if (event.key === 'Enter') {
                        event.preventDefault();
                        if (visibleOptions[0]) {
                            selectOption(visibleOptions[0]);
                        }
                    }

                    if (event.key === 'Escape') {
                        dropdown.classList.remove('is-open');
                        resetToSelection();
                    }
                });

                options.forEach(function(option) {
                    option.addEventListener('click', function() {
                        selectOption(option);
                    });
                });

                const form = dropdown.closest('form');
                if (form && !form.dataset.searchableValidationReady) {
                    form.addEventListener('submit', function(event) {
                        let formIsValid = true;

                        form.querySelectorAll('[data-searchable-dropdown]').forEach(function(formDropdown) {
                            const formHiddenInput = formDropdown.querySelector('input[type="hidden"]');
                            if (!formHiddenInput || formHiddenInput.value) {
                                formDropdown.classList.remove('searchable-dropdown-invalid');
                                return;
                            }
                            formDropdown.classList.add('searchable-dropdown-invalid');
                            formIsValid = false;
                        });

                        if (!formIsValid) {
                            event.preventDefault();
                        }
                    });

                    form.dataset.searchableValidationReady = '1';
                }

                const modal = dropdown.closest('.modal');
                if (modal && !modal.dataset.searchableModalReady) {
                    modal.addEventListener('hidden.bs.modal', function() {
                        modal.querySelectorAll('[data-searchable-dropdown]').forEach(function(modalDropdown) {
                            modalDropdown.classList.remove('is-open');
                            modalDropdown.classList.remove('searchable-dropdown-invalid');

                            const modalHiddenInput = modalDropdown.querySelector('input[type="hidden"]');
                            const modalTextInput = modalDropdown.querySelector('[data-searchable-input]');
                            const selectedOption = Array.from(modalDropdown.querySelectorAll('.searchable-dropdown-option')).find(function(option) {
                                return option.dataset.value === modalHiddenInput.value;
                            });

                            if (modalTextInput) {
                                modalTextInput.value = selectedOption ? (selectedOption.dataset.label || selectedOption.textContent || '') : '';
                            }
                        });
                    });

                    modal.dataset.searchableModalReady = '1';
                }

                filterOptions(textInput.value);
                dropdown.dataset.searchableReady = '1';
            }

            document.addEventListener('click', function(event) {
                const dropdown = event.target.closest('[data-searchable-dropdown]');
                if (!dropdown) {
                    closeAllSearchableDropdowns(null);
                }
            });

            document.querySelectorAll('[data-searchable-dropdown]').forEach(initSearchableDropdown);
        })();
    </script>
</body>
</html>