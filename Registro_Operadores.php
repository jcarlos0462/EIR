<?php
session_start();
include 'database_connection.php';
require_once 'access_control.php';
require_module_access($conn, 'operadores');

$usuario_id = $_SESSION['id'] ?? null;

// Aquí puedes agregar la lógica de búsqueda/registro de operadores, listas, etc.

?>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Operadores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="col-md-9 col-lg-10">
            <div class="p-4">
                <h1>Registro de Operadores</h1>
                <p>Implementa aquí la pantalla y funcionalidades para registrar o buscar operadores.</p>
                <!-- Formulario y tabla de operadores -->
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
