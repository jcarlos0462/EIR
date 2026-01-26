<?php
session_start();

// Destruir la sesión
session_destroy();

// Redirigir al index
header("Location: index.html");
exit();
?>
