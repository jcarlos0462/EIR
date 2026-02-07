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

$allowDanos = !$hasAnyAccess || user_has_module_access($accessMap, 'danos');
$allowVehiculos = !$hasAnyAccess || user_has_module_access($accessMap, 'vehiculos');
$allowReportes = !$hasAnyAccess || user_has_module_access($accessMap, 'reportes');
$allowUsuarios = !$hasAnyAccess || user_has_module_access($accessMap, 'usuarios');
$allowConfig = !$hasAnyAccess || user_has_module_access($accessMap, 'configuracion');
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
    <?php if ($allowUsuarios): ?>
        <a href="gestionar_usuarios.php" class="sidebar-link mb-2 <?php echo active_link('gestionar_usuarios.php'); ?>">Gestión de Usuarios</a>
    <?php endif; ?>
    <?php if ($allowConfig): ?>
        <a href="configuracion.php" class="sidebar-link mb-2 <?php echo active_link('configuracion.php'); ?>">Configuración</a>
    <?php endif; ?>
</div>
