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

// Crear tabla vehiculo
$sql = "CREATE TABLE IF NOT EXISTS vehiculo (
    ID INT(10) PRIMARY KEY AUTO_INCREMENT,
    Buque VARCHAR(25) NOT NULL,
    Viaje VARCHAR(25) NOT NULL,
    VIN VARCHAR(50) NOT NULL UNIQUE,
    Marca VARCHAR(20) NOT NULL,
    Modelo VARCHAR(15) NOT NULL,
    Color VARCHAR(20),
    Año VARCHAR(10),
    Puerto VARCHAR(30),
    Terminal VARCHAR(20)
)";

if ($conn->query($sql) === TRUE) {
    echo "Tabla 'vehiculo' creada exitosamente";
} else {
    echo "Error al crear tabla: " . $conn->error;
}

$conn->close();
?>