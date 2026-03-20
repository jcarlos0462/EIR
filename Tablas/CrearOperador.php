<?php
// Conexión a la base de datos en Hostinger
include '../database_connection.php';

// Crear tabla operador
$sql = "CREATE TABLE IF NOT EXISTS operador (
    ID INT(10) PRIMARY KEY AUTO_INCREMENT,
    Nombre VARCHAR(50) NOT NULL,
    Vehículo VARCHAR(50)
)";

if ($conn->query($sql) === TRUE) {
    echo "Tabla 'operador' creada exitosamente";
} else {
    echo "Error al crear tabla: " . $conn->error;
}

$conn->close();
?>
