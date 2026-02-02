<?php
// Script de migración para agregar la columna Descripcion a la tabla RegistroDanio existente
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include $_SERVER['DOCUMENT_ROOT'] . '/database_connection.php';

echo "<h2>Migración: Agregar columna Descripcion a RegistroDanio</h2>";

// Verificar si la columna ya existe
$check = $conn->query("SHOW COLUMNS FROM RegistroDanio LIKE 'Descripcion'");
if ($check->num_rows == 0) {
    // Agregar la columna Descripcion (sin especificar posición para evitar problemas de compatibilidad)
    $sql = "ALTER TABLE RegistroDanio ADD COLUMN Descripcion TEXT";
    if ($conn->query($sql) === true) {
        echo "<p style='color: green;'>✓ Columna 'Descripcion' agregada exitosamente a la tabla RegistroDanio.</p>";
    } else {
        echo "<p style='color: red;'>✗ Error al agregar la columna: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ La columna 'Descripcion' ya existe en la tabla RegistroDanio.</p>";
}

$conn->close();
?>
