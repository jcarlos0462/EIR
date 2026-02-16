<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header("Location: index.php");
    exit();
}

// Conexión a base de datos
include 'database_connection.php';
require_once 'access_control.php';
require_module_access($conn, 'configuracion');

// Mensajes para operaciones de roles
$rol_msg_success = '';
$rol_msg_error = '';

// Manejo de creación/edición/eliminación de roles
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Guardar (crear o actualizar)
    if (isset($_POST['guardar_rol'])) {
        $rid = isset($_POST['rol_id']) && $_POST['rol_id'] !== '' ? intval($_POST['rol_id']) : null;
        $nombre = trim($_POST['rol_nombre'] ?? '');
        $descripcion = trim($_POST['rol_descripcion'] ?? '');

        if ($nombre === '') {
            $rol_msg_error = 'El nombre del rol no puede estar vacío.';
        } else {
            if ($rid) {
                $stmt = $conn->prepare("UPDATE roles SET nombre = ?, descripcion = ? WHERE id = ?");
                $stmt->bind_param('ssi', $nombre, $descripcion, $rid);
                if ($stmt->execute()) {
                    $rol_msg_success = 'Rol actualizado correctamente.';
                } else {
                    $rol_msg_error = 'Error al actualizar rol: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO roles (nombre, descripcion) VALUES (?, ?)");
                $stmt->bind_param('ss', $nombre, $descripcion);
                if ($stmt->execute()) {
                    $rol_msg_success = 'Rol creado correctamente.';
                } else {
                    $rol_msg_error = 'Error al crear rol: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    // Eliminar rol
    if (isset($_POST['eliminar_rol']) && isset($_POST['rol_id'])) {
        $rid = intval($_POST['rol_id']);
        // comprobar usuarios asignados
        $chk = $conn->prepare("SELECT COUNT(*) FROM usuario_rol WHERE rol_id = ?");
        $chk->bind_param('i', $rid);
        $chk->execute();
        $chk->bind_result($ucount); $chk->fetch(); $chk->close();
        if ($ucount > 0) {
            $rol_msg_error = 'No se puede eliminar el rol: existen usuarios asignados.';
        } else {
            $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->bind_param('i', $rid);
            if ($stmt->execute()) {
                $rol_msg_success = 'Rol eliminado correctamente.';
            } else {
                $rol_msg_error = 'Error al eliminar rol: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Crear usuario desde Configuración
$user_create_error = '';
$user_create_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_usuario'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nombre === '' || $usuario === '' || $password === '') {
        $user_create_error = 'Todos los campos obligatorios deben completarse.';
    } elseif (strlen($password) < 8) {
        $user_create_error = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        $stmt_check = $conn->prepare("SELECT ID FROM usuario WHERE Usuario = ? LIMIT 1");
        $stmt_check->bind_param('s', $usuario);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $user_create_error = 'El usuario ya existe.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt_insert = $conn->prepare("INSERT INTO usuario (Nombre, Usuario, Contraseña) VALUES (?, ?, ?)");
            $stmt_insert->bind_param('sss', $nombre, $usuario, $password_hash);

            if ($stmt_insert->execute()) {
                $user_create_success = 'Usuario creado exitosamente.';
            } else {
                $user_create_error = 'Error al crear el usuario: ' . $stmt_insert->error;
            }

            $stmt_insert->close();
        }

        $stmt_check->close();
    }
}

// Mensajes generales para usuarios (editar/eliminar)
$user_msg_error = '';
$user_msg_success = '';

// Editar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_usuario'])) {
    $uid = intval($_POST['usuario_id'] ?? 0);
    $nombre_u = trim($_POST['nombre_u'] ?? '');
    $usuario_u = trim($_POST['usuario_u'] ?? '');
    $password_u = $_POST['password_u'] ?? '';
    $role_u = isset($_POST['rol_id']) && $_POST['rol_id'] !== '' ? intval($_POST['rol_id']) : null;

    if ($uid <= 0 || $nombre_u === '' || $usuario_u === '') {
        $user_msg_error = 'Todos los campos obligatorios deben completarse.';
    } else {
        // verificar usuario único (excepto este id)
        $stmtc = $conn->prepare("SELECT ID FROM usuario WHERE Usuario = ? AND ID <> ? LIMIT 1");
        $stmtc->bind_param('si', $usuario_u, $uid);
        $stmtc->execute();
        $stmtc->store_result();
        if ($stmtc->num_rows > 0) {
            $user_msg_error = 'El nombre de usuario ya está en uso por otro usuario.';
            $stmtc->close();
        } else {
            $stmtc->close();
            if ($password_u !== '') {
                $ph = password_hash($password_u, PASSWORD_DEFAULT);
                $st = $conn->prepare("UPDATE usuario SET Nombre = ?, Usuario = ?, Contraseña = ? WHERE ID = ?");
                $st->bind_param('sssi', $nombre_u, $usuario_u, $ph, $uid);
            } else {
                $st = $conn->prepare("UPDATE usuario SET Nombre = ?, Usuario = ? WHERE ID = ?");
                $st->bind_param('ssi', $nombre_u, $usuario_u, $uid);
            }
            if ($st->execute()) {
                $user_msg_success = 'Usuario actualizado correctamente.';
            } else {
                $user_msg_error = 'Error al actualizar usuario: ' . $st->error;
            }
            $st->close();

            // actualizar asignación de rol (usuario_rol): eliminar existentes y asignar si se seleccionó uno
            if ($role_u !== null) {
                $del = $conn->prepare("DELETE FROM usuario_rol WHERE usuario_id = ?");
                $del->bind_param('i', $uid);
                $del->execute();
                $del->close();

                if ($role_u > 0) {
                    $ins = $conn->prepare("INSERT INTO usuario_rol (usuario_id, rol_id) VALUES (?, ?)");
                    $ins->bind_param('ii', $uid, $role_u);
                    $ins->execute();
                    $ins->close();
                }
            }
        }
    }
}

// Eliminar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_usuario'])) {
    $uid = intval($_POST['usuario_id'] ?? 0);
    if ($uid <= 0) {
        $user_msg_error = 'Usuario inválido.';
    } else {
        $st = $conn->prepare("DELETE FROM usuario WHERE ID = ?");
        $st->bind_param('i', $uid);
        if ($st->execute()) {
            $user_msg_success = 'Usuario eliminado correctamente.';
        } else {
            $user_msg_error = 'Error al eliminar usuario: ' . $st->error;
        }
        $st->close();
    }
}

// Obtener estadísticas
$totalUsuarios = $conn->query("SELECT COUNT(*) as count FROM usuario")->fetch_assoc()['count'];
$totalRoles = $conn->query("SELECT COUNT(*) as count FROM roles")->fetch_assoc()['count'];
// Asegurar tabla de accesos por usuario y contar registros
$conn->query("CREATE TABLE IF NOT EXISTS usuario_acceso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    modulo VARCHAR(100) NOT NULL,
    lectura TINYINT(1) DEFAULT 0,
    escritura TINYINT(1) DEFAULT 0,
    eliminacion TINYINT(1) DEFAULT 0,
    UNIQUE KEY ux_usuario_modulo (usuario_id, modulo),
    FOREIGN KEY (usuario_id) REFERENCES usuario(ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$totalAccesos = 0;
$resAccesos = $conn->query("SELECT COUNT(*) as count FROM usuario_acceso");
if ($resAccesos && ($rowAccesos = $resAccesos->fetch_assoc())) {
    $totalAccesos = $rowAccesos['count'];
}
// Contadores de dashboard
$vehiculos_count = 0;
$danios_count = 0;
$usuarios_count = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM vehiculo");
if ($res && $row = $res->fetch_assoc()) $vehiculos_count = $row['total'];
$res = $conn->query("SELECT COUNT(*) AS total FROM RegistroDanio");
if ($res && $row = $res->fetch_assoc()) $danios_count = $row['total'];
$usuarios_count = $totalUsuarios;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - EIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="navbar_styles.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            padding: 20px;
        }
        .sidebar h5 {
            font-weight: 700;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(255,255,255,0.2);
            padding-bottom: 15px;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 8px;
            display: block;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: rgba(255,255,255,0.2);
            padding-left: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .stat-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
            margin: 15px 0;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        .stat-icon {
            font-size: 32px;
            color: #764ba2;
        }
        .btn-ver {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-ver:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(102, 126, 234, 0.4);
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header h2 {
            color: #333;
            font-weight: 700;
            margin: 0;
        }
        .header-subtitle {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .content-section {
            display: none;
        }
        .content-section.active {
            display: block;
        }
        .table-responsive {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .table {
            margin: 0;
        }
        .table thead {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        .table thead th {
            font-weight: 600;
            color: #667eea;
            border: none;
        }
        .form-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-card h4 {
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .btn-primary-custom:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(102, 126, 234, 0.4);
        }
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .sidebar {
                padding: 10px;
            }
            .sidebar a {
                padding: 8px 10px;
                font-size: 13px;
            }
            .stat-number {
                font-size: 24px;
            }
            .stat-icon {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="row" style="min-height: 100vh;">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>
            <div class="col-md-9 col-lg-10 main-content" style="padding: 25px;">

                <!-- SECCIÓN: ESTADÍSTICAS -->
                <div id="estadisticas" class="content-section active">
                    <div class="header">
                        <h2><i class="bi bi-graph-up"></i> Estadísticas del Sistema</h2>
                        <div class="header-subtitle">Resumen general de configuración y usuarios</div>
                    </div>

                    <!-- Tarjeta 'Daños Registrados' removida por solicitud del usuario -->

                    <div class="row">

                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card">
                                <div class="stat-icon"><i class="bi bi-shield-badge"></i></div>
                                <div class="stat-label">Roles Definidos</div>
                                <div class="stat-number"><?php echo $totalRoles; ?></div>
                                <button class="btn btn-ver" onclick="mostrarSeccion('roles')">Ver</button>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-6">
                            <div class="stat-card text-start">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                                    <div>
                                        <div class="stat-label">Usuarios Registrados</div>
                                        <div class="stat-number"><?php echo $totalUsuarios; ?></div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 mb-3 pt-2 border-top">
                                    <div class="stat-icon"><i class="bi bi-shield-lock"></i></div>
                                    <div>
                                        <div class="stat-label">Accesos Configurados</div>
                                        <div class="stat-number"><?php echo $totalAccesos; ?></div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-ver" onclick="mostrarSeccion('usuarios')">Ver Usuarios</button>
                                    <button class="btn btn-ver" onclick="mostrarSeccion('accesos')">Ver Accesos</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN: GESTIÓN DE USUARIOS -->
                <div id="usuarios" class="content-section">
                    <div class="header">
                        <h2><i class="bi bi-people"></i> Gestión de Usuarios</h2>
                        <div class="header-subtitle">Crear, editar y eliminar usuarios del sistema</div>
                    </div>

                    <div class="form-card">
                        <h4>Agregar Nuevo Usuario</h4>
                        <?php if ($user_create_error): ?>
                            <div class="alert alert-danger py-2 mb-3"><?php echo htmlspecialchars($user_create_error); ?></div>
                        <?php endif; ?>
                        <?php if ($user_create_success): ?>
                            <div class="alert alert-success py-2 mb-3"><?php echo htmlspecialchars($user_create_success); ?></div>
                        <?php endif; ?>
                        <form action="configuracion.php#usuarios" method="POST">
                            <input type="hidden" name="crear_usuario" value="1">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label">Nombre Completo</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej: Juan García" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="usuario" class="form-label">Usuario (Login)</label>
                                    <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Ej: jgarcia" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Contraseña</label>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Mínimo 8 caracteres" required>
                                    <small class="text-muted d-block mt-2">
                                        <i class="bi bi-info-circle"></i> 
                                        Debe contener: mayúscula, minúscula, número y carácter especial
                                    </small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="rol" class="form-label">Rol</label>
                                    <select class="form-select" id="rol" name="rol" required>
                                        <option value="">Seleccionar rol...</option>
                                        <option value="administrador">Administrador</option>
                                        <option value="inspector">Inspector</option>
                                        <option value="operador">Operador</option>
                                        <option value="lector">Lector</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Ej: juan@example.com">
                            </div>
                            <button type="submit" class="btn btn-primary-custom">
                                <i class="bi bi-plus-circle"></i> Crear Usuario
                            </button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <h4>Usuarios del Sistema</h4>
                        <?php if ($user_msg_error): ?>
                            <div class="alert alert-danger py-2 mb-3"><?php echo htmlspecialchars($user_msg_error); ?></div>
                        <?php endif; ?>
                        <?php if ($user_msg_success): ?>
                            <div class="alert alert-success py-2 mb-3"><?php echo htmlspecialchars($user_msg_success); ?></div>
                        <?php endif; ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // obtener lista de roles para los select en los modales
                                $roles_list = [];
                                $rr = $conn->query("SELECT id, nombre FROM roles ORDER BY nombre");
                                if ($rr) {
                                    while ($rrow = $rr->fetch_assoc()) $roles_list[] = $rrow;
                                }

                                $result = $conn->query("SELECT * FROM usuario ORDER BY ID ASC LIMIT 100");
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $uid = intval($row['ID']);
                                        $uname = htmlspecialchars($row['Nombre']);
                                        $ulogin = htmlspecialchars($row['Usuario']);
                                        // obtener rol asignado (si existe)
                                        $assigned_role_id = null;
                                        $assigned_role_name = '';
                                        $sr = $conn->prepare("SELECT r.id, r.nombre FROM usuario_rol ur JOIN roles r ON ur.rol_id = r.id WHERE ur.usuario_id = ? LIMIT 1");
                                        $sr->bind_param('i', $uid);
                                        $sr->execute();
                                        $sr->bind_result($arid, $arname);
                                        if ($sr->fetch()) { $assigned_role_id = $arid; $assigned_role_name = $arname; }
                                        $sr->close();

                                        ?>
                                        <tr>
                                            <td><?php echo $uid; ?></td>
                                            <td><?php echo $uname; ?></td>
                                            <td><?php echo $ulogin; ?></td>
                                            <td><?php echo htmlspecialchars($assigned_role_name); ?></td>
                                            <td>
                                                <button class='btn btn-sm btn-outline-primary' title='Editar' data-bs-toggle='modal' data-bs-target='#modalEditarUsuario<?php echo $uid; ?>'>
                                                    <i class='bi bi-pencil'></i>
                                                </button>
                                                <form method='post' style='display:inline;' onsubmit="return confirm('¿Eliminar usuario <?php echo addslashes($uname); ?>?');">
                                                    <input type='hidden' name='usuario_id' value='<?php echo $uid; ?>'>
                                                    <button type='submit' name='eliminar_usuario' value='1' class='btn btn-sm btn-outline-danger' title='Eliminar'>
                                                        <i class='bi bi-trash'></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>

                                        <!-- Modal editar usuario -->
                                        <div class='modal fade' id='modalEditarUsuario<?php echo $uid; ?>' tabindex='-1' aria-labelledby='modalEditarUsuarioLabel<?php echo $uid; ?>' aria-hidden='true'>
                                            <div class='modal-dialog'>
                                                <div class='modal-content'>
                                                    <div class='modal-header'>
                                                        <h5 class='modal-title' id='modalEditarUsuarioLabel<?php echo $uid; ?>'>Editar Usuario: <?php echo $ulogin; ?></h5>
                                                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Cerrar'></button>
                                                    </div>
                                                    <div class='modal-body'>
                                                        <form method='post' action='configuracion.php#usuarios'>
                                                            <input type='hidden' name='editar_usuario' value='1'>
                                                            <input type='hidden' name='usuario_id' value='<?php echo $uid; ?>'>
                                                            <div class='mb-3'>
                                                                <label class='form-label'>Nombre</label>
                                                                <input type='text' name='nombre_u' class='form-control' value='<?php echo $uname; ?>' required>
                                                            </div>
                                                            <div class='mb-3'>
                                                                <label class='form-label'>Usuario (login)</label>
                                                                <input type='text' name='usuario_u' class='form-control' value='<?php echo $ulogin; ?>' required>
                                                            </div>
                                                            <div class='mb-3'>
                                                                <label class='form-label'>Rol asignado</label>
                                                                <select name='rol_id' class='form-select'>
                                                                    <option value=''>(ninguno)</option>
                                                                    <?php foreach ($roles_list as $rl): ?>
                                                                        <option value='<?php echo $rl['id']; ?>' <?php echo ($rl['id']==$assigned_role_id)?'selected':''; ?>><?php echo htmlspecialchars($rl['nombre']); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class='mb-3'>
                                                                <label class='form-label'>Contraseña (dejar vacío para no cambiar)</label>
                                                                <input type='password' name='password_u' class='form-control' placeholder='Nueva contraseña'>
                                                            </div>
                                                            <div class='d-flex justify-content-end'>
                                                                <button type='button' class='btn btn-secondary me-2' data-bs-dismiss='modal'>Cancelar</button>
                                                                <button type='submit' class='btn btn-primary'>Guardar</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='text-center text-muted'>No hay usuarios en el sistema</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECCIÓN: ADMINISTRAR ACCESOS -->
                <div id="accesos" class="content-section">
                    <div class="header">
                        <h2><i class="bi bi-shield-lock"></i> Administrar Accesos</h2>
                        <div class="header-subtitle">Configurar permisos y accesos del sistema</div>
                    </div>
                    <?php if (!empty($_GET['msg'])): ?>
                        <div class="alert alert-info">
                            <?php echo htmlspecialchars($_GET['msg']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-card">
                        <h4>Asignar Acceso a Usuario</h4>
                        <form action="asignar_acceso.php" method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="usuario_id" class="form-label">Usuario</label>
                                    <select class="form-select" id="usuario_id" name="usuario_id" required>
                                        <option value="">Seleccionar usuario...</option>
                                        <?php
                                        $usuarios = $conn->query("SELECT ID, Nombre FROM usuario");
                                        while ($user = $usuarios->fetch_assoc()) {
                                            echo "<option value='{$user['ID']}'>{$user['Nombre']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="modulo" class="form-label">Módulo</label>
                                    <select class="form-select" id="modulo" name="modulo" required>
                                        <option value="">Seleccionar módulo...</option>
                                        <option value="vehiculos">Vehículos</option>
                                        <option value="danos">Daños</option>
                                        <option value="reportes">Reportes</option>
                                        <option value="usuarios">Gestión de Usuarios</option>
                                        <option value="configuracion">Configuración</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary-custom">
                                <i class="bi bi-check-circle"></i> Asignar Acceso
                            </button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <h4>Accesos Configurados</h4>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Módulo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
<?php
// Mostrar accesos configurados por usuario (tabla usuario_acceso)
// Crear tabla si no existe (no destructivo)
$conn->query("CREATE TABLE IF NOT EXISTS usuario_acceso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    modulo VARCHAR(100) NOT NULL,
    lectura TINYINT(1) DEFAULT 0,
    escritura TINYINT(1) DEFAULT 0,
    eliminacion TINYINT(1) DEFAULT 0,
    UNIQUE KEY ux_usuario_modulo (usuario_id, modulo)
)");

$sql_ac = "SELECT ua.id, ua.usuario_id, u.Nombre AS usuario_nombre, ua.modulo
           FROM usuario_acceso ua
           LEFT JOIN usuario u ON ua.usuario_id = u.ID
           ORDER BY usuario_nombre, ua.modulo";
$res_ac = $conn->query($sql_ac);
if ($res_ac && $res_ac->num_rows > 0) {
    while ($row_ac = $res_ac->fetch_assoc()) {
        $uid = intval($row_ac['usuario_id']);
        $uname = htmlspecialchars($row_ac['usuario_nombre'] ?: 'Usuario #' . $uid);
        $mod = htmlspecialchars($row_ac['modulo']);
        $aid = intval($row_ac['id']);
        echo "<tr>";
        echo "<td>$uname</td>";
        echo "<td>$mod</td>";
        echo "<td class='text-end'>";
        echo "<form method='POST' action='asignar_acceso.php' style='display:inline'>";
        echo "<input type='hidden' name='acceso_id' value='$aid'>";
        echo "<input type='hidden' name='eliminar_acceso' value='1'>";
        echo "<button class='btn btn-sm btn-outline-danger' type='submit'>Eliminar</button>";
        echo "</form>";
        echo "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='3' class='text-center text-muted'>No hay accesos configurados aún</td></tr>";
}
?>
                            </tbody>
                        </table>
                    </div>
                </div>


                <!-- SECCIÓN: ADMINISTRAR ROLES -->
                <div id="roles" class="content-section">
                    <div class="header">
                        <h2><i class="bi bi-shield-badge"></i> Administrar Roles</h2>
                        <div class="header-subtitle">Crear y configurar roles de usuario con permisos específicos</div>
                    </div>

                    <div class="form-card">
                        <h4>Crear Nuevo Rol</h4>
                        <?php if ($rol_msg_error): ?>
                            <div class="alert alert-danger py-2 mb-3"><?php echo htmlspecialchars($rol_msg_error); ?></div>
                        <?php endif; ?>
                        <?php if ($rol_msg_success): ?>
                            <div class="alert alert-success py-2 mb-3"><?php echo htmlspecialchars($rol_msg_success); ?></div>
                        <?php endif; ?>
                        <form action="configuracion.php#roles" method="POST">
                            <input type="hidden" name="guardar_rol" value="1">
                            <div class="mb-3">
                                <label for="rol_nombre" class="form-label">Nombre del Rol</label>
                                <input type="text" class="form-control" id="rol_nombre" name="rol_nombre" placeholder="Ej: Inspector Jefe" required>
                            </div>
                            <div class="mb-3">
                                <label for="rol_descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="rol_descripcion" name="rol_descripcion" rows="3" placeholder="Descripción del rol y responsabilidades"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary-custom">
                                <i class="bi bi-plus-circle"></i> Crear Rol
                            </button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <h4>Roles del Sistema</h4>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Rol</th>
                                    <th>Descripción</th>
                                    <th>Permisos</th>
                                    <th>Usuarios</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $roles_res = $conn->query("SELECT * FROM roles ORDER BY id ASC");
                                if ($roles_res && $roles_res->num_rows > 0) {
                                    while ($r = $roles_res->fetch_assoc()) {
                                        $rid = intval($r['id']);
                                        $rnombre = htmlspecialchars($r['nombre']);
                                        $rdesc = htmlspecialchars($r['descripcion']);
                                        // Count users with this role
                                        $uc = $conn->prepare("SELECT COUNT(*) as c FROM usuario_rol WHERE rol_id = ?");
                                        $uc->bind_param('i', $rid);
                                        $uc->execute();
                                        $uc->bind_result($c); $uc->fetch(); $user_count = $c; $uc->close();
                                        ?>
                                        <tr>
                                            <td><?php echo $rnombre; ?></td>
                                            <td><?php echo $rdesc; ?></td>
                                            <td><span class='badge bg-secondary'>--</span></td>
                                            <td><?php echo $user_count; ?></td>
                                            <td>
                                                <button class='btn btn-sm btn-outline-primary' title='Editar' data-bs-toggle='modal' data-bs-target='#modalEditarRol<?php echo $rid; ?>'>
                                                    <i class='bi bi-pencil'></i>
                                                </button>
                                                <form method='post' style='display:inline;' onsubmit="return confirm('¿Eliminar rol <?php echo addslashes($rnombre); ?>? Esta acción no se puede deshacer.');">
                                                    <input type='hidden' name='rol_id' value='<?php echo $rid; ?>'>
                                                    <button type='submit' name='eliminar_rol' value='1' class='btn btn-sm btn-outline-danger' title='Eliminar'>
                                                        <i class='bi bi-trash'></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>

                                        <!-- Modal editar rol -->
                                        <div class='modal fade' id='modalEditarRol<?php echo $rid; ?>' tabindex='-1' aria-labelledby='modalEditarRolLabel<?php echo $rid; ?>' aria-hidden='true'>
                                            <div class='modal-dialog'>
                                                <div class='modal-content'>
                                                    <div class='modal-header'>
                                                        <h5 class='modal-title' id='modalEditarRolLabel<?php echo $rid; ?>'>Editar Rol: <?php echo $rnombre; ?></h5>
                                                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Cerrar'></button>
                                                    </div>
                                                    <div class='modal-body'>
                                                        <form method='post' action='configuracion.php#roles'>
                                                            <input type='hidden' name='guardar_rol' value='1'>
                                                            <input type='hidden' name='rol_id' value='<?php echo $rid; ?>'>
                                                            <div class='mb-3'>
                                                                <label class='form-label'>Nombre del Rol</label>
                                                                <input type='text' name='rol_nombre' class='form-control' value='<?php echo $rnombre; ?>' required>
                                                            </div>
                                                            <div class='mb-3'>
                                                                <label class='form-label'>Descripción</label>
                                                                <textarea name='rol_descripcion' class='form-control' rows='3'><?php echo $rdesc; ?></textarea>
                                                            </div>
                                                            <div class='d-flex justify-content-end'>
                                                                <button type='button' class='btn btn-secondary me-2' data-bs-dismiss='modal'>Cancelar</button>
                                                                <button type='submit' class='btn btn-primary'>Guardar</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='text-center text-muted'>No hay roles definidos</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function mostrarSeccion(seccion) {
            // Ocultar todas las secciones
            const secciones = document.querySelectorAll('.content-section');
            secciones.forEach(s => s.classList.remove('active'));

            // Mostrar la sección seleccionada
            const seccionActiva = document.getElementById(seccion);
            if (seccionActiva) {
                seccionActiva.classList.add('active');
            }

            // Actualizar nav activo
            const links = document.querySelectorAll('.sidebar a.nav-link');
            links.forEach(link => link.classList.remove('active'));
            event.target.closest('a').classList.add('active');

            // Prevenir scroll al inicio
            return false;
        }

        function forzarLogout(usuarioId) {
            if (confirm('¿Estás seguro de que deseas forzar el cierre de sesión de este usuario?')) {
                // Enviar petición al servidor
                fetch('forzar_logout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'usuario_id=' + usuarioId
                })
                .then(response => response.text())
                .then(data => {
                    if (data === 'success') {
                        alert('Sesión cerrada correctamente');
                        location.reload();
                    } else {
                        alert('Error al cerrar la sesión');
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }
    </script>
</body>
</html>
