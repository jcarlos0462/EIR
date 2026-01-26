<?php
session_start();

// Conexión a la base de datos
$servername = "localhost";
$username = "u174025152_Administrador";
$password = "0066jv_A2";
$dbname = "u174025152_EIR";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Verificar si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);
    
    // Validar que no estén vacíos
    if (empty($usuario) || empty($password)) {
        $_SESSION['error'] = "Usuario y contraseña son requeridos";
        header("Location: index.html");
        exit();
    }
    
    // Buscar el usuario en la BD
    $sql = "SELECT ID, Nombre, Usuario, Contraseña FROM usuario WHERE Usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Verificar la contraseña usando password_verify
        if (password_verify($password, $row['Contraseña'])) {
            // Contraseña correcta
            $_SESSION['id'] = $row['ID'];
            $_SESSION['nombre'] = $row['Nombre'];
            $_SESSION['usuario'] = $row['Usuario'];
            $_SESSION['logueado'] = true;
            
            header("Location: Administrar.php");
            exit();
        } else {
            $_SESSION['error'] = "Usuario o contraseña incorrectos";
            header("Location: index.html");
            exit();
        }
    } else {
        $_SESSION['error'] = "Usuario o contraseña incorrectos";
        header("Location: index.html");
        exit();
    }
    
    $stmt->close();
} else {
    // Si acceden directamente sin POST, redirigir al index
    header("Location: index.html");
    exit();
}

$conn->close();
?>
