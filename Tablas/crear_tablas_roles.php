<?php
// Incluir archivo de conexión a base de datos
include 'database_connection.php';

// Crear tabla de roles si no existe
$sql_roles = "CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Crear tabla de accesos/permisos si no existe
$sql_accesos = "CREATE TABLE IF NOT EXISTS accesos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Crear tabla de relación rol-acceso si no existe
$sql_rol_acceso = "CREATE TABLE IF NOT EXISTS rol_acceso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rol_id INT NOT NULL,
    acceso_id INT NOT NULL,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (acceso_id) REFERENCES accesos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rol_acceso (rol_id, acceso_id)
)";

// Crear tabla de relación usuario-rol si no existe
$sql_usuario_rol = "CREATE TABLE IF NOT EXISTS usuario_rol (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    rol_id INT NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuario(ID) ON DELETE CASCADE,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_rol (usuario_id, rol_id)
)";

// Crear tabla de auditoría si no existe
$sql_auditoria = "CREATE TABLE IF NOT EXISTS auditoria (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    accion VARCHAR(255) NOT NULL,
    tabla_afectada VARCHAR(100),
    registro_id INT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    detalles TEXT,
    FOREIGN KEY (usuario_id) REFERENCES usuario(ID) ON DELETE SET NULL
)";

// Ejecutar queries
if ($conn->query($sql_roles) === TRUE) {
    echo "Tabla 'roles' creada o ya existe.<br>";
} else {
    echo "Error creando tabla 'roles': " . $conn->error . "<br>";
}

if ($conn->query($sql_accesos) === TRUE) {
    echo "Tabla 'accesos' creada o ya existe.<br>";
} else {
    echo "Error creando tabla 'accesos': " . $conn->error . "<br>";
}

if ($conn->query($sql_rol_acceso) === TRUE) {
    echo "Tabla 'rol_acceso' creada o ya existe.<br>";
} else {
    echo "Error creando tabla 'rol_acceso': " . $conn->error . "<br>";
}

if ($conn->query($sql_usuario_rol) === TRUE) {
    echo "Tabla 'usuario_rol' creada o ya existe.<br>";
} else {
    echo "Error creando tabla 'usuario_rol': " . $conn->error . "<br>";
}

if ($conn->query($sql_auditoria) === TRUE) {
    echo "Tabla 'auditoria' creada o ya existe.<br>";
} else {
    echo "Error creando tabla 'auditoria': " . $conn->error . "<br>";
}

// Insertar roles predeterminados
$roles_predeterminados = [
    ['Administrador', 'Acceso total al sistema'],
    ['Inspector', 'Inspecciona vehículos y registra daños'],
    ['Operador', 'Opera el sistema bajo supervisión'],
    ['Lector', 'Solo lectura de información']
];

foreach ($roles_predeterminados as $rol) {
    $nombre = $conn->real_escape_string($rol[0]);
    $descripcion = $conn->real_escape_string($rol[1]);
    
    $check = $conn->query("SELECT id FROM roles WHERE nombre = '$nombre'");
    if ($check->num_rows == 0) {
        $insert = $conn->query("INSERT INTO roles (nombre, descripcion) VALUES ('$nombre', '$descripcion')");
        if ($insert) {
            echo "Rol '$nombre' insertado.<br>";
        } else {
            echo "Error insertando rol '$nombre': " . $conn->error . "<br>";
        }
    } else {
        echo "Rol '$nombre' ya existe.<br>";
    }
}

// Insertar accesos/permisos predeterminados
$accesos_predeterminados = [
    ['ver_vehiculos', 'Ver lista de vehículos'],
    ['crear_vehiculos', 'Crear nuevos vehículos'],
    ['editar_vehiculos', 'Editar información de vehículos'],
    ['eliminar_vehiculos', 'Eliminar vehículos'],
    ['ver_danos', 'Ver daños registrados'],
    ['registrar_danos', 'Registrar nuevos daños'],
    ['editar_danos', 'Editar información de daños'],
    ['eliminar_danos', 'Eliminar daños'],
    ['ver_reportes', 'Ver reportes del sistema'],
    ['generar_reportes', 'Generar nuevos reportes'],
    ['gestionar_usuarios', 'Gestionar usuarios del sistema'],
    ['ver_usuarios_conectados', 'Ver usuarios activos'],
    ['configuracion', 'Acceder a configuración del sistema'],
    ['ver_auditoria', 'Ver logs de auditoría']
];

foreach ($accesos_predeterminados as $acceso) {
    $nombre = $conn->real_escape_string($acceso[0]);
    $descripcion = $conn->real_escape_string($acceso[1]);
    
    $check = $conn->query("SELECT id FROM accesos WHERE nombre = '$nombre'");
    if ($check->num_rows == 0) {
        $insert = $conn->query("INSERT INTO accesos (nombre, descripcion) VALUES ('$nombre', '$descripcion')");
        if ($insert) {
            echo "Acceso '$nombre' insertado.<br>";
        } else {
            echo "Error insertando acceso '$nombre': " . $conn->error . "<br>";
        }
    }
}

echo "<br><strong>Base de datos configurada correctamente para roles y accesos.</strong>";

$conn->close();
?>
