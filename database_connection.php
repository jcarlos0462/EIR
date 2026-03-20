<?php

// Incluir configuración segura.
// Soporta config.php en el mismo directorio o en el padre.
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} elseif (file_exists(__DIR__ . '/public_html/config.php')) {
    require_once __DIR__ . '/public_html/config.php';
} else {
    die('Error de configuración: no se encuentra config.php');
}

// Conexión a base de datos MySQL usando constantes seguras
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

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
