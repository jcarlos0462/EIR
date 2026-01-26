<?php
// Conexión a la base de datos en Hostinger
$servername = "localhost";
$username = "u174025152_Administrador";
$password = "0066jv_A2";
$dbname = "u174025152_EIR";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

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
