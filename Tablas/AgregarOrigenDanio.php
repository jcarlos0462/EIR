<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include '../database_connection.php';

$errores = [];

// Agregar columna Origen a RegistroDanio si no existe
$sql_add_origen = "ALTER TABLE RegistroDanio ADD COLUMN Origen VARCHAR(100) NULL";
if (!$conn->query($sql_add_origen)) {
    if (strpos($conn->error, 'Duplicate column name') === false) {
        $errores[] = "Error agregando Origen: " . $conn->error;
    }
}

if ($errores) {
    echo "<b>Errores encontrados:</b><br>" . implode('<br>', $errores);
} else {
    echo "Actualización completada correctamente.";
}

$conn->close();
?>