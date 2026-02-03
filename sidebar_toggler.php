<!-- Botón hamburguesa para sidebar (solo móviles) -->
<button class="sidebar-toggler" type="button" id="sidebarToggler" aria-label="Abrir menú" title="Menú">
    <span class="sidebar-toggler-icon" aria-hidden="true">☰</span>
</button>

<!-- Overlay para cerrar sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
// Esperar a que el DOM cargue completamente
document.addEventListener('DOMContentLoaded', function() {
    // Toggle del sidebar en móviles
    var sidebarToggler = document.getElementById('sidebarToggler');
    var sidebarOverlay = document.getElementById('sidebarOverlay');
    var sidebarMenu = document.getElementById('sidebarMenu');
    
    console.log('Sidebar elements found:', {
        toggler: !!sidebarToggler,
        overlay: !!sidebarOverlay,
        menu: !!sidebarMenu
    });
    
    if (sidebarToggler && sidebarMenu && sidebarOverlay) {
        sidebarToggler.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Toggle clicked, current classes:', sidebarMenu.className);
            sidebarMenu.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
            
            // Ocultar el botón cuando el sidebar está abierto
            if (sidebarMenu.classList.contains('show')) {
                sidebarToggler.style.opacity = '0';
                sidebarToggler.style.pointerEvents = 'none';
            } else {
                sidebarToggler.style.opacity = '1';
                sidebarToggler.style.pointerEvents = 'auto';
            }
            
            console.log('After toggle, classes:', sidebarMenu.className);
            console.log('Transform applied:', window.getComputedStyle(sidebarMenu).transform);
        });

        // Cerrar el sidebar al hacer clic en el overlay
        sidebarOverlay.addEventListener('click', function() {
            console.log('Overlay clicked, closing sidebar');
            sidebarMenu.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            sidebarToggler.style.opacity = '1';
            sidebarToggler.style.pointerEvents = 'auto';
        });

        // Cerrar el sidebar al hacer clic en un enlace (en móviles)
        document.querySelectorAll('.sidebar-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    console.log('Link clicked in mobile view');
                    sidebarMenu.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    sidebarToggler.style.opacity = '1';
                    sidebarToggler.style.pointerEvents = 'auto';
                }
            });
        });
    } else {
        console.error('Sidebar elements not found!');
    }

    // Asegurar visibilidad del botón en móviles/tablets
    function ensureSidebarToggler() {
        var toggler = document.getElementById('sidebarToggler');
        if (!toggler) return;
        if (window.innerWidth <= 1024) {
            toggler.style.display = 'flex';
            toggler.style.visibility = 'visible';
            if (!document.getElementById('sidebarMenu').classList.contains('show')) {
                toggler.style.opacity = '1';
                toggler.style.pointerEvents = 'auto';
            }
        } else {
            toggler.style.display = '';
            toggler.style.visibility = '';
            toggler.style.opacity = '';
            toggler.style.pointerEvents = '';
        }
    }

    window.addEventListener('resize', ensureSidebarToggler);
    window.addEventListener('orientationchange', ensureSidebarToggler);
    ensureSidebarToggler();
});
</script>
