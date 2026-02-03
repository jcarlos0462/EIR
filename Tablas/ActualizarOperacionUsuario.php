<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include '../database_connection.php';

$errores = [];

// Crear tabla usuario_operacion si no existe
$sql_create = "CREATE TABLE IF NOT EXISTS usuario_operacion (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    UsuarioID INT NOT NULL,
    TipoOperacion VARCHAR(100) NOT NULL,
    FechaRegistro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UsuarioID) REFERENCES usuario(ID)
) ENGINE=InnoDB;";
if (!$conn->query($sql_create)) {
    $errores[] = "Error creando usuario_operacion: " . $conn->error;
}

// Agregar columnas a RegistroDanio si no existen
$sql_add_usuario = "ALTER TABLE RegistroDanio ADD COLUMN UsuarioID INT NULL";
if (!$conn->query($sql_add_usuario)) {
    if (strpos($conn->error, 'Duplicate column name') === false) {
        $errores[] = "Error agregando UsuarioID: " . $conn->error;
    }
}

$sql_add_operacion = "ALTER TABLE RegistroDanio ADD COLUMN TipoOperacion VARCHAR(100) NULL";
if (!$conn->query($sql_add_operacion)) {
    if (strpos($conn->error, 'Duplicate column name') === false) {
        $errores[] = "Error agregando TipoOperacion: " . $conn->error;
    }
}

// Agregar FK a usuario si no existe
$sql_fk = "ALTER TABLE RegistroDanio ADD CONSTRAINT fk_registrodanio_usuario FOREIGN KEY (UsuarioID) REFERENCES usuario(ID)";
if (!$conn->query($sql_fk)) {
    if (strpos($conn->error, 'Duplicate key name') === false && strpos($conn->error, 'errno: 121') === false) {
        $errores[] = "Error agregando FK UsuarioID: " . $conn->error;
    }
}

if ($errores) {
    echo "<b>Errores encontrados:</b><br>" . implode('<br>', $errores);
} else {
    echo "Actualización completada correctamente.";
}

$conn->close();
?>