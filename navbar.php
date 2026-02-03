<!-- Navbar -->
<nav class="navbar navbar-dark">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">
            <strong>EIR</strong> - Sistema de Inspección de Daños
        </span>
        <!-- Botón hamburguesa para móviles -->
        <button class="navbar-toggler" type="button" id="navbarToggler">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="user-info" id="navbarMenu">
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
            <a href="logout.php" class="btn btn-sm btn-light ms-3">Cerrar Sesión</a>
        </div>
    </div>
</nav>

<script>
// Toggle del menú en móviles
document.getElementById('navbarToggler').addEventListener('click', function() {
    var menu = document.getElementById('navbarMenu');
    menu.classList.toggle('show');
});

// Cerrar el menú al hacer clic fuera
document.addEventListener('click', function(event) {
    var menu = document.getElementById('navbarMenu');
    var toggler = document.getElementById('navbarToggler');
    if (menu.classList.contains('show') && !menu.contains(event.target) && !toggler.contains(event.target)) {
        menu.classList.remove('show');
    }
});
</script>
