<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include '../database_connection.php';

$tablas = ['areadano', 'tipodano', 'severidaddano'];
$errores = [];

foreach ($tablas as $tabla) {
    // 1. Obtener la PRIMARY KEY existente (si la hay)
    $res = $conn->query("SHOW KEYS FROM $tabla WHERE Key_name = 'PRIMARY'");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $columnaPK = $row['Column_name'];
        // 2. Eliminar la PRIMARY KEY existente
        $sql = "ALTER TABLE $tabla DROP PRIMARY KEY;";
        if (!$conn->query($sql)) {
            $errores[] = "Error eliminando PK en $tabla: " . $conn->error;
            continue;
        }
    }
    // 3. Verificar si ya existe columna ID
    $res = $conn->query("SHOW COLUMNS FROM $tabla LIKE 'ID'");
    if ($res && $res->num_rows > 0) {
        // Si existe, intentar convertirla en AUTO_INCREMENT PRIMARY KEY
        $sql = "ALTER TABLE $tabla MODIFY COLUMN ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY;";
    } else {
        // Si no existe, agregarla
        $sql = "ALTER TABLE $tabla ADD COLUMN ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;";
    }
    if (!$conn->query($sql)) {
        $errores[] = "Error agregando/modificando ID en $tabla: " . $conn->error;
    }
}

if ($errores) {
    echo "<b>Errores encontrados:</b><br>" . implode('<br>', $errores);
} else {
    echo "Tablas corregidas: ID AUTO_INCREMENT PRIMARY KEY en areadano, tipodano y severidaddano.";
}
$conn->close();
