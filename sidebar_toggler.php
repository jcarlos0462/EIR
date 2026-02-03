<!-- Botón hamburguesa para sidebar (solo móviles) -->
<button class="sidebar-toggler" type="button" id="sidebarToggler" aria-label="Abrir menú" title="Menú">
    <span class="sidebar-toggler-icon" aria-hidden="true">☰</span>
</button>

<!-- Overlay para cerrar sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

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
