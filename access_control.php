<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function module_to_page($module) {
    static $map = [
        'danos' => 'Registro_Daños.php',
        'vehiculos' => 'listar_vehiculos.php',
        'reportes' => 'reportes_vehiculos.php',
        'operadores' => 'Registro_Operadores.php',
        'configuracion' => 'configuracion.php',
    ];

    return $map[$module] ?? null;
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
    $stmt = $conn->prepare("SELECT modulo FROM usuario_acceso WHERE usuario_id = ?");
    if (!$stmt) return $map;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $map[$row['modulo']] = true;
    }
    $stmt->close();
    return $map;
}

function user_has_module_access($accessMap, $module) {
    return isset($accessMap[$module]);
}

function user_has_role_name($conn, $userId, $roleName) {
    $userId = intval($userId);
    if ($userId <= 0) {
        return false;
    }

    $normalizedRole = mb_strtolower(trim((string)$roleName), 'UTF-8');
    if ($normalizedRole === '') {
        return false;
    }

    $stmt = $conn->prepare("SELECT 1 FROM usuario_rol ur JOIN roles r ON ur.rol_id = r.id WHERE ur.usuario_id = ? AND LOWER(r.nombre) = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $userId, $normalizedRole);
    $stmt->execute();
    $stmt->store_result();
    $hasRole = $stmt->num_rows > 0;
    $stmt->close();

    return $hasRole;
}

function get_user_module_permissions($conn, $userId, $module) {
    ensure_usuario_acceso_table($conn);

    $permissions = [
        'lectura' => false,
        'escritura' => false,
        'eliminacion' => false,
    ];

    $userId = intval($userId);
    $module = trim((string)$module);
    if ($userId <= 0 || $module === '') {
        return $permissions;
    }

    $stmt = $conn->prepare("SELECT lectura, escritura, eliminacion FROM usuario_acceso WHERE usuario_id = ? AND modulo = ? LIMIT 1");
    if (!$stmt) {
        return $permissions;
    }

    $stmt->bind_param('is', $userId, $module);
    $stmt->execute();
    $stmt->bind_result($lectura, $escritura, $eliminacion);
    if ($stmt->fetch()) {
        $permissions['lectura'] = intval($lectura) === 1;
        $permissions['escritura'] = intval($escritura) === 1;
        $permissions['eliminacion'] = intval($eliminacion) === 1;
    }
    $stmt->close();

    return $permissions;
}

function can_user_write_module($conn, $userId, $module) {
    if (user_has_role_name($conn, $userId, 'lector')) {
        return false;
    }

    $permissions = get_user_module_permissions($conn, $userId, $module);
    return !empty($permissions['escritura']);
}

function can_user_delete_module($conn, $userId, $module) {
    if (user_has_role_name($conn, $userId, 'lector')) {
        return false;
    }

    $permissions = get_user_module_permissions($conn, $userId, $module);
    return !empty($permissions['eliminacion']);
}

function get_home_page_for_access_map($accessMap) {
    if (empty($accessMap)) {
        return 'index.php';
    }

    // Prioridad de módulos para definir la página inicial cuando hay varios accesos.
    $priority = ['danos', 'vehiculos', 'reportes', 'operadores', 'configuracion'];
    foreach ($priority as $module) {
        if (isset($accessMap[$module])) {
            $page = module_to_page($module);
            if ($page !== null) {
                return $page;
            }
        }
    }

    foreach (array_keys($accessMap) as $module) {
        $page = module_to_page($module);
        if ($page !== null) {
            return $page;
        }
    }

    return 'index.php';
}

function get_user_home_page($conn, $userId) {
    $userId = intval($userId);
    if ($userId <= 0) {
        return 'index.php';
    }

    $accessMap = get_user_access_map($conn, $userId);
    return get_home_page_for_access_map($accessMap);
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
    if (empty($accessMap) || !user_has_module_access($accessMap, $module)) {
        $homePage = get_home_page_for_access_map($accessMap);
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso denegado</title>' .
             '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">' .
             '</head><body class="bg-light"><div class="container py-5">' .
             '<div class="alert alert-danger">No tienes acceso a este modulo.</div>' .
             '<a href="' . htmlspecialchars($homePage, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">Ir a inicio</a>' .
             '</div></body></html>';
        exit();
    }
}

function require_admin_role($conn) {
    if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
        header('Location: index.php');
        exit();
    }
    $userId = intval($_SESSION['id'] ?? 0);
    if ($userId <= 0) {
        header('Location: index.php');
        exit();
    }

    $stmt = $conn->prepare("SELECT 1 FROM usuario_rol ur JOIN roles r ON ur.rol_id = r.id WHERE ur.usuario_id = ? AND LOWER(r.nombre) = 'administrador' LIMIT 1");
    if (!$stmt) {
           $homePage = get_user_home_page($conn, $userId);
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso denegado</title>' .
             '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">' .
             '</head><body class="bg-light"><div class="container py-5">' .
             '<div class="alert alert-danger">No tienes acceso a este modulo.</div>' .
               '<a href="' . htmlspecialchars($homePage, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">Ir a inicio</a>' .
             '</div></body></html>';
        exit();
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->store_result();
    $isAdmin = $stmt->num_rows > 0;
    $stmt->close();

    if (!$isAdmin) {
        $homePage = get_user_home_page($conn, $userId);
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso denegado</title>' .
             '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">' .
             '</head><body class="bg-light"><div class="container py-5">' .
             '<div class="alert alert-danger">No tienes acceso a este modulo.</div>' .
             '<a href="' . htmlspecialchars($homePage, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">Ir a inicio</a>' .
             '</div></body></html>';
        exit();
    }
}
?>