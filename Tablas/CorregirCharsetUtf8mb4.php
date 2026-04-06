<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../database_connection.php';

$errores = [];
$mensajes = [];

function runQueryOrCollectError($conn, $sql, &$errores, &$mensajes, $successMessage) {
    if ($conn->query($sql) === true) {
        $mensajes[] = $successMessage;
        return true;
    }

    $errores[] = $conn->error . ' | SQL: ' . $sql;
    return false;
}

$dbName = DB_NAME;

runQueryOrCollectError(
    $conn,
    "ALTER DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
    $errores,
    $mensajes,
    "Base de datos {$dbName} convertida a utf8mb4."
);

$tablesResult = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbName) . "'");

if ($tablesResult) {
    while ($tableRow = $tablesResult->fetch_assoc()) {
        $tableName = $tableRow['TABLE_NAME'];
        runQueryOrCollectError(
            $conn,
            "ALTER TABLE `{$tableName}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
            $errores,
            $mensajes,
            "Tabla {$tableName} convertida a utf8mb4."
        );
    }
} else {
    $errores[] = 'No se pudieron obtener las tablas de information_schema.';
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corrección de Charset UTF-8</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 24px;
            color: #1f2937;
        }
        .ok {
            color: #166534;
        }
        .error {
            color: #991b1b;
        }
        .note {
            margin-top: 20px;
            padding: 12px 14px;
            background: #fff7ed;
            border: 1px solid #fdba74;
            border-radius: 8px;
        }
        ul {
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <h2>Corrección de charset a UTF-8</h2>

    <?php if ($mensajes): ?>
        <h3 class="ok">Cambios aplicados</h3>
        <ul>
            <?php foreach ($mensajes as $mensaje): ?>
                <li class="ok"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($errores): ?>
        <h3 class="error">Errores</h3>
        <ul>
            <?php foreach ($errores as $error): ?>
                <li class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="note">
        Esta corrección evita nuevos problemas de acentos en datos futuros. Si algunos textos ya aparecen como "?" o dañados en la base, esos valores ya fueron guardados corruptos y habrá que corregirlos en los registros afectados.
    </div>
</body>
</html>
