<?php
// Script para crear usuario de prueba
// ELIMINARLO DESPUÉS DE USARLO

$servername = "localhost";
$username = "u174025152_Administrador";
$password = "0066jv_A2";
$dbname = "u174025152_EIR";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Datos de prueba
$nombre = "Administrador";
$usuario = "admin";
$password_texto = "Admin@1234"; // Contraseña: Admin@1234
$password_hash = password_hash($password_texto, PASSWORD_BCRYPT);

// Verificar si el usuario ya existe
$sql_check = "SELECT ID FROM usuario WHERE Usuario = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("s", $usuario);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    echo "<h3>El usuario ya existe</h3>";
    echo "Si deseas actualizar la contraseña, ejecuta actualizar_contraseñas.php<br>";
} else {
    // Crear usuario de prueba
    $sql = "INSERT INTO usuario (Nombre, Usuario, Contraseña) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $nombre, $usuario, $password_hash);
    
    if ($stmt->execute()) {
        echo "<h2 style='color:green;'>✓ Usuario de prueba creado exitosamente</h2>";
        echo "<p><strong>Usuario:</strong> admin</p>";
        echo "<p><strong>Contraseña:</strong> Admin@1234</p>";
        echo "<br><a href='index.html'>Ir a inicio de sesión</a>";
        echo "<br><br><strong>IMPORTANTE:</strong> Después de probar, elimina este archivo (crear_usuario_prueba.php) del servidor";
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

$stmt_check->close();
$conn->close();
?>
