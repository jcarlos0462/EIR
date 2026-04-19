<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Conexión a la base de datos
include 'database_connection.php';
require_once 'access_control.php';

// Verificar si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);
    $tipo_operacion = trim($_POST['tipo_operacion'] ?? '');
    $puerto = trim($_POST['puerto'] ?? '');
    
    // Validar que no estén vacíos
    if (empty($usuario) || empty($password)) {
        $_SESSION['error'] = "Usuario y contraseña son requeridos";
        header("Location: index.php");
        exit();
    }

    if ($tipo_operacion === '') {
        $_SESSION['error'] = "Debe seleccionar el tipo de operación";
        header("Location: index.php");
        exit();
    }

    if ($puerto === '') {
        $_SESSION['error'] = "Debe seleccionar el puerto";
        header("Location: index.php");
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
        
        // Verificar contraseña: primero intenta con password_verify (hasheada)
        // Si falla, intenta comparación directa (sin hashear) - compatibilidad temporal
        $password_valida = false;
        
        if (preg_match('/^\$2[aby]\$/', $row['Contraseña'])) {
            // La contraseña está hasheada
            $password_valida = password_verify($password, $row['Contraseña']);
        } else {
            // La contraseña NO está hasheada - compatibilidad temporal
            $password_valida = ($password === $row['Contraseña']);
        }
        
        if ($password_valida) {
            // Contraseña correcta
            $_SESSION['id'] = $row['ID'];
            $_SESSION['nombre'] = $row['Nombre'];
            $_SESSION['usuario'] = $row['Usuario'];
            $_SESSION['logueado'] = true;
            $_SESSION['tipo_operacion'] = $tipo_operacion;
            $_SESSION['puerto'] = $puerto;

            // Asegurar tabla de operaciones por usuario
            $sql_create_operacion = "CREATE TABLE IF NOT EXISTS usuario_operacion (
                ID INT AUTO_INCREMENT PRIMARY KEY,
                UsuarioID INT NOT NULL,
                TipoOperacion VARCHAR(100) NOT NULL,
                FechaRegistro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (UsuarioID) REFERENCES usuario(ID)
            ) ENGINE=InnoDB;";
            $conn->query($sql_create_operacion);

            // Registrar operación seleccionada
            $stmt_op = $conn->prepare("INSERT INTO usuario_operacion (UsuarioID, TipoOperacion) VALUES (?, ?)");
            $stmt_op->bind_param("is", $row['ID'], $tipo_operacion);
            if (!$stmt_op->execute()) {
                $_SESSION['error'] = "No se pudo registrar la operación seleccionada";
                $stmt_op->close();
                header("Location: index.php");
                exit();
            }
            $stmt_op->close();
            
            $homePage = get_user_home_page($conn, intval($row['ID']));
            if ($homePage === 'index.php') {
                unset($_SESSION['id'], $_SESSION['nombre'], $_SESSION['usuario'], $_SESSION['logueado'], $_SESSION['tipo_operacion'], $_SESSION['puerto']);
                $_SESSION['error'] = 'Tu usuario no tiene accesos asignados. Contacta al administrador.';
                header("Location: index.php");
                exit();
            }
            header("Location: " . $homePage);
            exit();
        } else {
            $_SESSION['error'] = "Usuario o contraseña incorrectos";
            header("Location: index.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Usuario o contraseña incorrectos";
        header("Location: index.php");
        exit();
    }
    
    $stmt->close();
} else {
    // Si acceden directamente sin POST, redirigir al index
    header("Location: index.php");
    exit();
}

$conn->close();
?>
