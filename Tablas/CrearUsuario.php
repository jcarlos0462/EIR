<?php
// Conexión a la base de datos en Hostinger
include '../database_connection.php';

// Crear tabla usuario
$sql = "CREATE TABLE IF NOT EXISTS usuario (
    ID INT(10) PRIMARY KEY AUTO_INCREMENT,
    Nombre VARCHAR(50) NOT NULL,
    Usuario VARCHAR(30) NOT NULL UNIQUE,
    Contraseña VARCHAR(100) NOT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Tabla 'usuario' creada exitosamente";
} else {
    echo "Error al crear tabla: " . $conn->error;
}

$conn->close();
?>
