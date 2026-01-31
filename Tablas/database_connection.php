<?php
// Conexión a base de datos MySQL
$servername = "localhost"; // Generalmente localhost en Hostinger
$username = "u174025152_Administrador"; // Usuario de Hostinger
$password = "0066jv_A2"; // Contraseña de Hostinger
$dbname = "u174025152_EIR"; // Nombre de la base de datos

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    // Mostrar mensaje de error solo en desarrollo
    if ($_ENV['APP_ENV'] === 'development' || isset($_GET['debug'])) {
        die("Conexión fallida: " . $conn->connect_error);
    } else {
        die("Error de conexión a la base de datos. Por favor, intenta más tarde.");
    }
}

// Configurar charset UTF-8
$conn->set_charset("utf8mb4");

?>
