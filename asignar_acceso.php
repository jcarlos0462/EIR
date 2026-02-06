<?php
session_start();
include 'database_connection.php';

function redirect_back($msg = null) {
    $url = 'configuracion.php#accesos';
    if ($msg) $url .= (strpos($url, '?') === false ? '?' : '&') . 'msg=' . urlencode($msg);
    header('Location: ' . $url);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect_back();

// Ensure table exists
$sql = "CREATE TABLE IF NOT EXISTS usuario_acceso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    modulo VARCHAR(100) NOT NULL,
    lectura TINYINT(1) DEFAULT 0,
    escritura TINYINT(1) DEFAULT 0,
    eliminacion TINYINT(1) DEFAULT 0,
    UNIQUE KEY ux_usuario_modulo (usuario_id, modulo),
    FOREIGN KEY (usuario_id) REFERENCES usuario(ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

// Assign or update
if (isset($_POST['eliminar_acceso']) && isset($_POST['acceso_id'])) {
    $aid = intval($_POST['acceso_id']);
    $st = $conn->prepare("DELETE FROM usuario_acceso WHERE id = ?");
    $st->bind_param('i', $aid);
    if ($st->execute()) {
        $st->close();
        redirect_back('Acceso eliminado');
    } else {
        $err = $st->error;
        $st->close();
        redirect_back('Error eliminando acceso: ' . $err);
    }
}

if (!isset($_POST['usuario_id']) || !isset($_POST['modulo'])) redirect_back('Parámetros incompletos');

$usuario_id = intval($_POST['usuario_id']);
$modulo = trim($_POST['modulo']);
$lectura = isset($_POST['lectura']) ? 1 : 0;
$escritura = isset($_POST['escritura']) ? 1 : 0;
$eliminacion = isset($_POST['eliminacion']) ? 1 : 0;

// upsert
$stmt = $conn->prepare("SELECT id FROM usuario_acceso WHERE usuario_id = ? AND modulo = ? LIMIT 1");
$stmt->bind_param('is', $usuario_id, $modulo);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->bind_result($existing_id); $stmt->fetch();
    $stmt->close();
    $up = $conn->prepare("UPDATE usuario_acceso SET lectura = ?, escritura = ?, eliminacion = ? WHERE id = ?");
    $up->bind_param('iiii', $lectura, $escritura, $eliminacion, $existing_id);
    if ($up->execute()) {
        $up->close();
        redirect_back('Acceso actualizado');
    } else {
        $err = $up->error; $up->close();
        redirect_back('Error actualizando acceso: ' . $err);
    }
} else {
    $stmt->close();
    $ins = $conn->prepare("INSERT INTO usuario_acceso (usuario_id, modulo, lectura, escritura, eliminacion) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param('isiii', $usuario_id, $modulo, $lectura, $escritura, $eliminacion);
    if ($ins->execute()) {
        $ins->close();
        redirect_back('Acceso asignado');
    } else {
        $err = $ins->error; $ins->close();
        redirect_back('Error asignando acceso: ' . $err);
    }
}

