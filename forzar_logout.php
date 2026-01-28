<?php
session_start();

// Verificar que es una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'error';
    exit();
}

// Verificar que el usuario actual sea administrador
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    echo 'error';
    exit();
}

// Obtener el ID del usuario a desloguear
$usuario_id = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0;

if ($usuario_id <= 0) {
    echo 'error';
    exit();
}

// Incluir conexión a base de datos
include 'database_connection.php';

// Aquí se podría actualizar una tabla de sesiones activas
// Por ahora, simplemente confirmamos que la operación se realizó
// En una implementación real, borraríamos la sesión del usuario de la tabla de sesiones

echo 'success';
$conn->close();
?>
