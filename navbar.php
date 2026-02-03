<!-- Navbar -->
<nav class="navbar navbar-dark">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">
            <strong>EIR</strong> - Sistema de Inspección de Daños
        </span>
        <div class="user-info">
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
            <a href="logout.php" class="btn btn-sm btn-light ms-3">Cerrar Sesión</a>
        </div>
    </div>
</nav>
