<?php
$current = basename($_SERVER['PHP_SELF']);
function active_link($file) {
    global $current;
    return $current === $file ? 'active' : '';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    include 'database_connection.php';
}
require_once 'access_control.php';

$userId = intval($_SESSION['id'] ?? 0);
$accessMap = ($userId > 0) ? get_user_access_map($conn, $userId) : [];
$hasAnyAccess = !empty($accessMap);

$is_admin = false;
if ($userId > 0) {
    $stmt_admin = $conn->prepare("SELECT 1 FROM usuario_rol ur JOIN roles r ON ur.rol_id = r.id WHERE ur.usuario_id = ? AND LOWER(r.nombre) = 'administrador' LIMIT 1");
    if ($stmt_admin) {
        $stmt_admin->bind_param('i', $userId);
        $stmt_admin->execute();
        $stmt_admin->store_result();
        $is_admin = $stmt_admin->num_rows > 0;
        $stmt_admin->close();
    }
}

$allowDanos = !$hasAnyAccess || user_has_module_access($accessMap, 'danos');
$allowVehiculos = !$hasAnyAccess || user_has_module_access($accessMap, 'vehiculos');
$allowReportes = !$hasAnyAccess || user_has_module_access($accessMap, 'reportes');
$allowUsuarios = !$hasAnyAccess || user_has_module_access($accessMap, 'usuarios');
$allowConfig = !$hasAnyAccess || user_has_module_access($accessMap, 'configuracion');

if (!$is_admin) {
    $allowDanos = true;
    $allowVehiculos = false;
    $allowReportes = false;
    $allowUsuarios = false;
    $allowConfig = false;
}
?>
<!-- Sidebar -->
<div class="sidebar d-flex flex-column p-3 h-100" id="sidebarMenu" style="width: 220px; min-width: 180px; max-width: 260px;">
    <h5 class="fw-bold mb-4 pb-2 border-bottom"><i class="bi bi-gear"></i> MENÚ</h5>
    <?php if ($allowDanos): ?>
        <a href="Registro_Daños.php" class="sidebar-link mb-2 <?php echo active_link('Registro_Daños.php'); ?>">Daños</a>
    <?php endif; ?>
    <?php if ($allowVehiculos): ?>
        <a href="listar_vehiculos.php" class="sidebar-link mb-2 <?php echo active_link('listar_vehiculos.php'); ?>">Vehículos</a>
    <?php endif; ?>
    <?php if ($allowReportes): ?>
        <a href="reportes_vehiculos.php" class="sidebar-link mb-2 <?php echo active_link('reportes_vehiculos.php'); ?>">Reportes</a>
    <?php endif; ?>
    <?php if ($allowConfig): ?>
        <a href="configuracion.php" class="sidebar-link mb-2 <?php echo active_link('configuracion.php'); ?>">Configuración</a>
    <?php endif; ?>
</div>
