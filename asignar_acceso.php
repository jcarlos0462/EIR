<?php
session_start();
include 'database_connection.php';
require_once 'access_control.php';
require_module_access($conn, 'configuracion');

function render_access_rows($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS usuario_acceso (
        id INT PRIMARY KEY AUTO_INCREMENT,
        usuario_id INT NOT NULL,
        modulo VARCHAR(100) NOT NULL,
        lectura TINYINT(1) DEFAULT 0,
        escritura TINYINT(1) DEFAULT 0,
        eliminacion TINYINT(1) DEFAULT 0,
        UNIQUE KEY ux_usuario_modulo (usuario_id, modulo)
    )");

    $sql = "SELECT ua.id, ua.usuario_id, u.Nombre AS usuario_nombre, ua.modulo
            FROM usuario_acceso ua
            LEFT JOIN usuario u ON ua.usuario_id = u.ID
            ORDER BY usuario_nombre, ua.modulo";
    $res = $conn->query($sql);
    if (!$res || $res->num_rows === 0) {
        return "<tr><td colspan='3' class='text-center text-muted'>No hay accesos configurados aún</td></tr>";
    }

    $rows = '';
    while ($row = $res->fetch_assoc()) {
        $uid = intval($row['usuario_id']);
        $uname = htmlspecialchars($row['usuario_nombre'] ?: 'Usuario #' . $uid);
        $mod = htmlspecialchars($row['modulo']);
        $aid = intval($row['id']);
        $rows .= "<tr>";
        $rows .= "<td>{$uname}</td>";
        $rows .= "<td>{$mod}</td>";
        $rows .= "<td class='text-end'>";
        $rows .= "<form method='POST' action='asignar_acceso.php' class='acceso-delete-form d-inline'>";
        $rows .= "<input type='hidden' name='acceso_id' value='{$aid}'>";
        $rows .= "<input type='hidden' name='eliminar_acceso' value='1'>";
        $rows .= "<button class='btn btn-sm btn-outline-danger' type='submit'>Eliminar</button>";
        $rows .= "</form>";
        $rows .= "</td>";
        $rows .= "</tr>";
    }
    return $rows;
}

function respond_json($conn, $ok, $msg) {
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => $ok,
        'message' => $msg,
        'rows_html' => render_access_rows($conn)
    ]);
    exit();
}

function redirect_back($msg = null) {
    $url = 'configuracion.php#accesos';
    if ($msg) $url .= (strpos($url, '?') === false ? '?' : '&') . 'msg=' . urlencode($msg);
    header('Location: ' . $url);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect_back();

$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

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
        if ($is_ajax) respond_json($conn, true, 'Acceso eliminado');
        redirect_back('Acceso eliminado');
    } else {
        $err = $st->error;
        $st->close();
        if ($is_ajax) respond_json($conn, false, 'Error eliminando acceso: ' . $err);
        redirect_back('Error eliminando acceso: ' . $err);
    }
}

if (!isset($_POST['usuario_id']) || !isset($_POST['modulo'])) {
    if ($is_ajax) respond_json($conn, false, 'Parámetros incompletos');
    redirect_back('Parámetros incompletos');
}

$usuario_id = intval($_POST['usuario_id']);
$modulo = trim($_POST['modulo']);
$one = 1;

// upsert
$stmt = $conn->prepare("SELECT id FROM usuario_acceso WHERE usuario_id = ? AND modulo = ? LIMIT 1");
$stmt->bind_param('is', $usuario_id, $modulo);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->bind_result($existing_id); $stmt->fetch();
    $stmt->close();
    $up = $conn->prepare("UPDATE usuario_acceso SET lectura = ?, escritura = ?, eliminacion = ? WHERE id = ?");
    $up->bind_param('iiii', $one, $one, $one, $existing_id);
    if ($up->execute()) {
        $up->close();
        if ($is_ajax) respond_json($conn, true, 'Acceso actualizado');
        redirect_back('Acceso actualizado');
    } else {
        $err = $up->error; $up->close();
        if ($is_ajax) respond_json($conn, false, 'Error actualizando acceso: ' . $err);
        redirect_back('Error actualizando acceso: ' . $err);
    }
} else {
    $stmt->close();
    $ins = $conn->prepare("INSERT INTO usuario_acceso (usuario_id, modulo, lectura, escritura, eliminacion) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param('isiii', $usuario_id, $modulo, $one, $one, $one);
    if ($ins->execute()) {
        $ins->close();
        if ($is_ajax) respond_json($conn, true, 'Acceso asignado');
        redirect_back('Acceso asignado');
    } else {
        $err = $ins->error; $ins->close();
        if ($is_ajax) respond_json($conn, false, 'Error asignando acceso: ' . $err);
        redirect_back('Error asignando acceso: ' . $err);
    }
}

