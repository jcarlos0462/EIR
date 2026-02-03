<?php
$current = basename($_SERVER['PHP_SELF']);
function active_link($file) {
    global $current;
    return $current === $file ? 'active' : '';
}
?>
<!-- Botón hamburguesa para sidebar (solo móviles) -->
<button class="sidebar-toggler" type="button" id="sidebarToggler">
    <span class="sidebar-toggler-icon"></span>
</button>

<!-- Overlay para cerrar sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<div class="sidebar d-flex flex-column p-3 h-100" id="sidebarMenu" style="width: 220px; min-width: 180px; max-width: 260px;">
    <h5 class="fw-bold mb-4 pb-2 border-bottom"><i class="bi bi-gear"></i> MENÚ</h5>
    <a href="Administrar.php" class="sidebar-link mb-2 <?php echo active_link('Administrar.php'); ?>">Dashboard</a>
    <a href="listar_vehiculos.php" class="sidebar-link mb-2 <?php echo active_link('listar_vehiculos.php'); ?>">Vehículos</a>
    <a href="Registro_Daños.php" class="sidebar-link mb-2 <?php echo active_link('Registro_Daños.php'); ?>">Daños</a>
    <a href="#" class="sidebar-link mb-2">Reportes</a>
    <a href="configuracion.php" class="sidebar-link mb-2 <?php echo active_link('configuracion.php'); ?>">Configuración</a>
</div>

<script>
// Toggle del sidebar en móviles
document.getElementById('sidebarToggler').addEventListener('click', function() {
    var sidebar = document.getElementById('sidebarMenu');
    var overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
});

// Cerrar el sidebar al hacer clic en el overlay
document.getElementById('sidebarOverlay').addEventListener('click', function() {
    var sidebar = document.getElementById('sidebarMenu');
    var overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
});

// Cerrar el sidebar al hacer clic en un enlace (en móviles)
document.querySelectorAll('.sidebar-link').forEach(function(link) {
    link.addEventListener('click', function() {
        if (window.innerWidth <= 1024) {
            var sidebar = document.getElementById('sidebarMenu');
            var overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }
    });
});
</script>
