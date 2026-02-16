<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../database_connection.php';

$errores = [];

$sql = "ALTER TABLE RegistroDanio ADD COLUMN Puerto VARCHAR(30) NULL";
if (!$conn->query($sql)) {
    if (strpos($conn->error, 'Duplicate column name') === false) {
        $errores[] = 'Error agregando Puerto: ' . $conn->error;
    }
}

if ($errores) {
    echo '<b>Errores encontrados:</b><br>' . implode('<br>', $errores);
} else {
    echo "Columna 'Puerto' agregada correctamente (o ya existia).";
}

$conn->close();
