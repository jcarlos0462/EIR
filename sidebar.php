<?php
$current = basename($_SERVER['PHP_SELF']);
function active_link($file) {
    global $current;
    return $current === $file ? 'active' : '';
}
?>
<!-- Sidebar -->
<div class="sidebar d-flex flex-column p-3">
    <h5 class="fw-bold mb-4 pb-2 border-bottom"><i class="bi bi-gear"></i> MENÚ</h5>
    <a href="Administrar.php" class="sidebar-link mb-2 <?php echo active_link('Administrar.php'); ?>">Dashboard</a>
    <a href="listar_vehiculos.php" class="sidebar-link mb-2 <?php echo active_link('listar_vehiculos.php'); ?>">Vehículos</a>
    <a href="#" class="sidebar-link mb-2">Daños</a>
    <a href="#" class="sidebar-link mb-2">Reportes</a>
    <a href="configuracion.php" class="sidebar-link mb-2 <?php echo active_link('configuracion.php'); ?>">Configuración</a>
</div>
