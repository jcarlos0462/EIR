<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function ensure_usuario_acceso_table($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS usuario_acceso (
        id INT PRIMARY KEY AUTO_INCREMENT,
        usuario_id INT NOT NULL,
        modulo VARCHAR(100) NOT NULL,
        lectura TINYINT(1) DEFAULT 0,
        escritura TINYINT(1) DEFAULT 0,
        eliminacion TINYINT(1) DEFAULT 0,
        UNIQUE KEY ux_usuario_modulo (usuario_id, modulo),
        FOREIGN KEY (usuario_id) REFERENCES usuario(ID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function get_user_access_map($conn, $userId) {
    ensure_usuario_acceso_table($conn);
    $map = [];
    $stmt = $conn->prepare("SELECT modulo, lectura, escritura, eliminacion FROM usuario_acceso WHERE usuario_id = ?");
    if (!$stmt) return $map;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $map[$row['modulo']] = [
            'lectura' => intval($row['lectura']),
            'escritura' => intval($row['escritura']),
            'eliminacion' => intval($row['eliminacion'])
        ];
    }
    $stmt->close();
    return $map;
}

function user_has_module_access($accessMap, $module) {
    if (!isset($accessMap[$module])) return false;
    $perm = $accessMap[$module];
    return ($perm['lectura'] || $perm['escritura'] || $perm['eliminacion']);
}

function require_module_access($conn, $module) {
    if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
        header('Location: index.php');
        exit();
    }
    $userId = intval($_SESSION['id'] ?? 0);
    if ($userId <= 0) {
        header('Location: index.php');
        exit();
    }

    $accessMap = get_user_access_map($conn, $userId);
    if (!empty($accessMap) && !user_has_module_access($accessMap, $module)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso denegado</title>' .
             '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">' .
             '</head><body class="bg-light"><div class="container py-5">' .
             '<div class="alert alert-danger">No tienes acceso a este modulo.</div>' .
             '<a href="Registro_Daños.php" class="btn btn-primary">Ir a Danos</a>' .
             '</div></body></html>';
        exit();
    }
}
?>