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

// Obtener estadísticas
$totalUsuarios = $conn->query("SELECT COUNT(*) as count FROM usuario")->fetch_assoc()['count'];
$usuariosConectados = $conn->query("SELECT COUNT(*) as count FROM usuario WHERE ultima_actividad > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetch_assoc()['count'];
$totalRoles = $conn->query("SELECT COUNT(*) as count FROM roles")->fetch_assoc()['count'];
$totalAccesos = $conn->query("SELECT COUNT(*) as count FROM accesos")->fetch_assoc()['count'];
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
            background: linear-gradient(120deg, #6a82fb 0%, #fc5c7d 100%);
            min-height: 100vh;
        }
        .modern-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px 0 rgba(60,60,120,0.10);
            padding: 2.5rem 2rem 2rem 2rem;
            margin: 40px auto 0 auto;
            max-width: 900px;
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
        .modern-label {
            font-weight: 700;
            color: #426dc9;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        .modern-input {
            font-size: 1.1rem;
            border-radius: 12px;
            padding: 0.7rem 1.2rem;
            border: 2px solid #e0e6f7;
            box-shadow: 0 2px 8px 0 rgba(60,60,120,0.04);
        }
        .modern-modal-header-primary {
            background: linear-gradient(90deg, #426dc9 60%, #6a82fb 100%);
            color: #fff;
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
        }
        @media (max-width: 768px) {
            .modern-card, .modern-table-card {
                padding: 1.2rem 0.7rem 1rem 0.7rem;
                max-width: 100%;
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
            <div class="col-md-9 col-lg-10 main-content">
                <div class="modern-card mb-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <h2 class="mb-3 mb-md-0">Configuración del Sistema</h2>
                        <a href="Administrar.php" class="modern-btn btn-secondary">Volver</a>
                    </div>
                    <div class="d-flex flex-wrap gap-3 justify-content-center mt-4 mb-2" id="configTabs">
                        <button class="modern-btn modern-btn-primary" id="tab-estadisticas" onclick="mostrarSeccion('estadisticas', this)"><i class="bi bi-graph-up"></i> Estadísticas</button>
                        <button class="modern-btn modern-btn-primary" id="tab-usuarios" onclick="mostrarSeccion('usuarios', this)"><i class="bi bi-people"></i> Usuarios</button>
                        <button class="modern-btn modern-btn-primary" id="tab-roles" onclick="mostrarSeccion('roles', this)"><i class="bi bi-shield-badge"></i> Roles</button>
                        <button class="modern-btn modern-btn-primary" id="tab-accesos" onclick="mostrarSeccion('accesos', this)"><i class="bi bi-shield-lock"></i> Accesos</button>
                        <button class="modern-btn modern-btn-primary" id="tab-conectados" onclick="mostrarSeccion('conectados', this)"><i class="bi bi-person-check"></i> Conectados</button>
                    </div>
                </div>
                <div class="modern-card" style="padding: 1.5rem 1rem;">

                <!-- SECCIÓN: ESTADÍSTICAS -->
                <div id="estadisticas" class="content-section active">
                    <div class="header">
                        <h2><i class="bi bi-graph-up"></i> Estadísticas del Sistema</h2>
                        <div class="header-subtitle">Resumen general de configuración y usuarios</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card">
                                <div class="stat-icon"><i class="bi bi-people"></i></div>
                                <div class="stat-label">Usuarios Registrados</div>
                                <div class="stat-number"><?php echo $totalUsuarios; ?></div>
                                <button class="btn btn-ver" onclick="mostrarSeccion('usuarios')">Ver</button>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card">
                                <div class="stat-icon"><i class="bi bi-person-check"></i></div>
                                <div class="stat-label">Usuarios Conectados</div>
                                <div class="stat-number"><?php echo $usuariosConectados; ?></div>
                                <button class="btn btn-ver" onclick="mostrarSeccion('conectados')">Ver</button>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card">
                                <div class="stat-icon"><i class="bi bi-shield-badge"></i></div>
                                <div class="stat-label">Roles Definidos</div>
                                <div class="stat-number"><?php echo $totalRoles; ?></div>
                                <button class="btn btn-ver" onclick="mostrarSeccion('roles')">Ver</button>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card">
                                <div class="stat-icon"><i class="bi bi-shield-lock"></i></div>
                                <div class="stat-label">Accesos Configurados</div>
                                <div class="stat-number"><?php echo $totalAccesos; ?></div>
                                <button class="btn btn-ver" onclick="mostrarSeccion('accesos')">Ver</button>
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
                        <form action="crear_usuario.php" method="POST">
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
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Correo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $result = $conn->query("SELECT * FROM usuario LIMIT 10");
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>
                                            <td>{$row['ID']}</td>
                                            <td>{$row['Nombre']}</td>
                                            <td>{$row['Usuario']}</td>
                                            <td><span class='badge bg-info'>Usuario</span></td>
                                            <td>-</td>
                                            <td>
                                                <button class='btn btn-sm btn-outline-primary' title='Editar'>
                                                    <i class='bi bi-pencil'></i>
                                                </button>
                                                <button class='btn btn-sm btn-outline-danger' title='Eliminar'>
                                                    <i class='bi bi-trash'></i>
                                                </button>
                                            </td>
                                        </tr>";
                                    }
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
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="lectura" name="lectura">
                                        <label class="form-check-label" for="lectura">
                                            Lectura
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="escritura" name="escritura">
                                        <label class="form-check-label" for="escritura">
                                            Escritura
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="eliminacion" name="eliminacion">
                                        <label class="form-check-label" for="eliminacion">
                                            Eliminación
                                        </label>
                                    </div>
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
                                    <th>Lectura</th>
                                    <th>Escritura</th>
                                    <th>Eliminación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No hay accesos configurados aún</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECCIÓN: USUARIOS CONECTADOS -->
                <div id="conectados" class="content-section">
                    <div class="header">
                        <h2><i class="bi bi-person-check"></i> Usuarios Conectados</h2>
                        <div class="header-subtitle">Monitoreo de sesiones activas en el sistema</div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Conectado Desde</th>
                                    <th>Última Actividad</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Obtener usuarios con sesiones activas (últimos 10 minutos)
                                $conectados = $conn->query("SELECT * FROM usuario ORDER BY ID DESC");
                                if ($conectados && $conectados->num_rows > 0) {
                                    while ($user = $conectados->fetch_assoc()) {
                                        // Verificar si hay sesión activa (simulado con tiempo)
                                        $esActivo = true; // En producción, usar tabla de sesiones
                                        $estadoBadge = $esActivo ? '<span class="badge bg-success"><i class="bi bi-circle-fill"></i> Activo</span>' : '<span class="badge bg-secondary"><i class="bi bi-circle"></i> Inactivo</span>';
                                        
                                        echo "<tr>
                                            <td><strong>{$user['Usuario']}</strong></td>
                                            <td>{$user['Nombre']}</td>
                                            <td>{$estadoBadge}</td>
                                            <td>Hace 2 minutos</td>
                                            <td>Hace 30 segundos</td>
                                            <td>
                                                <button class='btn btn-sm btn-outline-info' title='Ver detalles'>
                                                    <i class='bi bi-eye'></i>
                                                </button>
                                                <button class='btn btn-sm btn-outline-warning' title='Forzar logout' onclick='forzarLogout({$user['ID']})'>
                                                    <i class='bi bi-door-open'></i>
                                                </button>
                                            </td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center text-muted'>No hay usuarios en el sistema</td></tr>";
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
                        <form action="crear_rol.php" method="POST">
                            <div class="mb-3">
                                <label for="rol_nombre" class="form-label">Nombre del Rol</label>
                                <input type="text" class="form-control" id="rol_nombre" name="rol_nombre" placeholder="Ej: Inspector Jefe" required>
                            </div>
                            <div class="mb-3">
                                <label for="rol_descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="rol_descripcion" name="rol_descripcion" rows="3" placeholder="Descripción del rol y responsabilidades"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Permisos del Rol</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="perm_ver_vehiculos" name="permisos[]" value="ver_vehiculos">
                                            <label class="form-check-label" for="perm_ver_vehiculos">
                                                Ver Vehículos
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="perm_crear_vehiculos" name="permisos[]" value="crear_vehiculos">
                                            <label class="form-check-label" for="perm_crear_vehiculos">
                                                Crear Vehículos
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="perm_editar_vehiculos" name="permisos[]" value="editar_vehiculos">
                                            <label class="form-check-label" for="perm_editar_vehiculos">
                                                Editar Vehículos
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="perm_eliminar_vehiculos" name="permisos[]" value="eliminar_vehiculos">
                                            <label class="form-check-label" for="perm_eliminar_vehiculos">
                                                Eliminar Vehículos
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="perm_ver_danos" name="permisos[]" value="ver_danos">
                                            <label class="form-check-label" for="perm_ver_danos">
                                                Ver Daños
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="perm_registrar_danos" name="permisos[]" value="registrar_danos">
                                            <label class="form-check-label" for="perm_registrar_danos">
                                                Registrar Daños
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="perm_ver_reportes" name="permisos[]" value="ver_reportes">
                                            <label class="form-check-label" for="perm_ver_reportes">
                                                Ver Reportes
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="perm_admin" name="permisos[]" value="administrador">
                                            <label class="form-check-label" for="perm_admin">
                                                <strong>Acceso Administrativo</strong>
                                            </label>
                                        </div>
                                    </div>
                                </div>
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
                                <tr>
                                    <td>Administrador</td>
                                    <td>Acceso total al sistema</td>
                                    <td><span class="badge bg-success">Todos</span></td>
                                    <td>1</td>
                                    <td>
                                        <button class='btn btn-sm btn-outline-primary' title='Editar'>
                                            <i class='bi bi-pencil'></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Inspector</td>
                                    <td>Inspecciona vehículos y registra daños</td>
                                    <td><span class="badge bg-info">5 permisos</span></td>
                                    <td>0</td>
                                    <td>
                                        <button class='btn btn-sm btn-outline-primary' title='Editar'>
                                            <i class='bi bi-pencil'></i>
                                        </button>
                                        <button class='btn btn-sm btn-outline-danger' title='Eliminar'>
                                            <i class='bi bi-trash'></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Operador</td>
                                    <td>Opera el sistema bajo supervisión</td>
                                    <td><span class="badge bg-info">3 permisos</span></td>
                                    <td>0</td>
                                    <td>
                                        <button class='btn btn-sm btn-outline-primary' title='Editar'>
                                            <i class='bi bi-pencil'></i>
                                        </button>
                                        <button class='btn btn-sm btn-outline-danger' title='Eliminar'>
                                            <i class='bi bi-trash'></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tabs de navegación para configuración
        function mostrarSeccion(seccion, btn) {
            // Ocultar todas las secciones
            const secciones = document.querySelectorAll('.content-section');
            secciones.forEach(s => s.classList.remove('active'));
            // Mostrar la sección seleccionada
            const seccionActiva = document.getElementById(seccion);
            if (seccionActiva) {
                seccionActiva.classList.add('active');
            }
            // Quitar activo de todos los tabs
            document.querySelectorAll('#configTabs button').forEach(b => b.classList.remove('active'));
            // Poner activo al tab actual
            if (btn) btn.classList.add('active');
        }
        // Activar tab por defecto
        document.addEventListener('DOMContentLoaded', function() {
            mostrarSeccion('estadisticas', document.getElementById('tab-estadisticas'));
        });

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
