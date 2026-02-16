<!-- Navbar -->
<nav class="navbar navbar-dark">
    <div class="container-fluid navbar-with-toggler">
        <!-- Botón hamburguesa para sidebar (solo móviles) -->
        <button class="sidebar-toggler" type="button" id="sidebarToggler" aria-label="Abrir menú" title="Menú">
            <span class="sidebar-toggler-icon" aria-hidden="true">☰</span>
        </button>

        <span class="navbar-brand mb-0 h1">
            <strong>EIR</strong> - Sistema de Inspección de Daños
        </span>
        <div class="user-info">
            <span class="operacion-tipo"><?php echo htmlspecialchars($_SESSION['tipo_operacion'] ?? 'N/A'); ?></span>
            <span class="operacion-tipo"><?php echo htmlspecialchars($_SESSION['puerto'] ?? 'N/A'); ?></span>
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
            <a href="logout.php" class="btn btn-sm btn-light ms-3">Cerrar Sesión</a>
        </div>
    </div>
</nav>

<!-- Overlay para cerrar sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
// Esperar a que el DOM cargue completamente
document.addEventListener('DOMContentLoaded', function() {
    var sidebarToggler = document.getElementById('sidebarToggler');
    var sidebarOverlay = document.getElementById('sidebarOverlay');
    var sidebarMenu = document.getElementById('sidebarMenu');

    if (sidebarToggler && sidebarMenu && sidebarOverlay) {
        sidebarToggler.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var isOpen = sidebarMenu.classList.contains('show');
            sidebarMenu.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');

            if (!isOpen) {
                sidebarMenu.style.transform = 'translateX(0)';
                sidebarOverlay.style.display = 'block';
                sidebarToggler.style.opacity = '0';
                sidebarToggler.style.pointerEvents = 'none';
            } else {
                sidebarMenu.style.transform = 'translateX(-100%)';
                sidebarOverlay.style.display = 'none';
                sidebarToggler.style.opacity = '1';
                sidebarToggler.style.pointerEvents = 'auto';
            }
        });

        sidebarOverlay.addEventListener('click', function() {
            sidebarMenu.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            sidebarMenu.style.transform = 'translateX(-100%)';
            sidebarOverlay.style.display = 'none';
            sidebarToggler.style.opacity = '1';
            sidebarToggler.style.pointerEvents = 'auto';
        });

        document.querySelectorAll('.sidebar-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    sidebarMenu.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    sidebarMenu.style.transform = 'translateX(-100%)';
                    sidebarOverlay.style.display = 'none';
                    sidebarToggler.style.opacity = '1';
                    sidebarToggler.style.pointerEvents = 'auto';
                }
            });
        });
    }

    function ensureSidebarToggler() {
        if (!sidebarToggler) return;
        if (window.innerWidth <= 1024) {
            sidebarToggler.style.display = 'flex';
            sidebarToggler.style.visibility = 'visible';
            if (!sidebarMenu.classList.contains('show')) {
                sidebarToggler.style.opacity = '1';
                sidebarToggler.style.pointerEvents = 'auto';
            }
        } else {
            sidebarToggler.style.display = '';
            sidebarToggler.style.visibility = '';
            sidebarToggler.style.opacity = '';
            sidebarToggler.style.pointerEvents = '';
        }
    }

    window.addEventListener('resize', ensureSidebarToggler);
    window.addEventListener('orientationchange', ensureSidebarToggler);
    ensureSidebarToggler();
});
</script>
