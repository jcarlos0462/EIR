<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header("Location: index.html");
    exit();
}

// Conexión a la base de datos
$servername = "localhost";
$username = "u174025152_Administrador";
$password = "0066jv_A2";
$dbname = "u174025152_EIR";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$error = '';
$exito = '';

// Validar fortaleza de contraseña
function validar_contraseña($password) {
    $errores = [];
    
    if (strlen($password) < 8) {
        $errores[] = "La contraseña debe tener al menos 8 caracteres";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errores[] = "La contraseña debe contener al menos una mayúscula";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errores[] = "La contraseña debe contener al menos una minúscula";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errores[] = "La contraseña debe contener al menos un número";
    }
    if (!preg_match('/[!@#$%^&*]/', $password)) {
        $errores[] = "La contraseña debe contener al menos un carácter especial (!@#$%^&*)";
    }
    
    return $errores;
}

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);
    $password_confirm = trim($_POST['password_confirm']);
    
    if (empty($nombre) || empty($usuario) || empty($password) || empty($password_confirm)) {
        $error = "Todos los campos son requeridos";
    } elseif ($password !== $password_confirm) {
        $error = "Las contraseñas no coinciden";
    } else {
        // Validar fortaleza
        $errores_password = validar_contraseña($password);
        if (!empty($errores_password)) {
            $error = "Contraseña débil:<br>" . implode("<br>", $errores_password);
        } else {
            // Verificar si el usuario ya existe
            $sql_check = "SELECT ID FROM usuario WHERE Usuario = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("s", $usuario);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                $error = "El usuario ya existe";
            } else {
                // Hashear contraseña
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                
                // Insertar usuario
                $sql = "INSERT INTO usuario (Nombre, Usuario, Contraseña) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $nombre, $usuario, $password_hash);
                
                if ($stmt->execute()) {
                    $exito = "Usuario creado exitosamente";
                    $nombre = $usuario = $password = $password_confirm = '';
                } else {
                    $error = "Error al crear el usuario: " . $stmt->error;
                }
                $stmt->close();
            }
            $stmt_check->close();
        }
    }
}

// Obtener lista de usuarios
$sql_usuarios = "SELECT ID, Nombre, Usuario FROM usuario ORDER BY ID DESC";
$result_usuarios = $conn->query($sql_usuarios);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios - EIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="navbar_styles.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .main-content {
            padding: 30px 20px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 600;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
        }
        .password-requirements {
            background-color: #f0f0f0;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            font-size: 13px;
            margin-top: 10px;
        }
        .req-item {
            margin: 5px 0;
        }
        .req-item.done {
            color: #28a745;
        }
        .req-item.pending {
            color: #dc3545;
        }
        .table {
            background: white;
        }
        @media (max-width: 768px) {
            .main-content {
                padding: 15px 10px;
            }
            .card {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar_toggler.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col-md-9 col-lg-10 main-content">

    <div class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h2>Gestionar Usuarios</h2>
                        <a href="Administrar.php" class="btn btn-secondary">Volver</a>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Formulario Agregar Usuario -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Crear Nuevo Usuario</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($exito): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($exito); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="nombre" class="form-label">Nombre Completo *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej: Juan Pérez" required>
                                </div>

                                <div class="mb-3">
                                    <label for="usuario" class="form-label">Usuario *</label>
                                    <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Ej: jperez" required>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Contraseña *</label>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Ingrese contraseña" required>
                                </div>

                                <div class="mb-3">
                                    <label for="password_confirm" class="form-label">Confirmar Contraseña *</label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Confirme contraseña" required>
                                </div>

                                <div class="password-requirements">
                                    <strong>Requisitos de contraseña fuerte:</strong>
                                    <div class="req-item pending" id="req-length">✗ Mínimo 8 caracteres</div>
                                    <div class="req-item pending" id="req-upper">✗ Al menos una mayúscula</div>
                                    <div class="req-item pending" id="req-lower">✗ Al menos una minúscula</div>
                                    <div class="req-item pending" id="req-number">✗ Al menos un número</div>
                                    <div class="req-item pending" id="req-special">✗ Al menos un carácter especial (!@#$%^&*)</div>
                                </div>

                                <button type="submit" class="btn btn-submit w-100 mt-4">Crear Usuario</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Lista de Usuarios -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Usuarios Registrados</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Usuario</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result_usuarios->num_rows > 0): ?>
                                            <?php while ($row = $result_usuarios->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['ID']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['Nombre']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['Usuario']); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-4">No hay usuarios</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const passwordInput = document.getElementById('password');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            
            // Validar longitud
            document.getElementById('req-length').classList.toggle('done', password.length >= 8);
            document.getElementById('req-length').classList.toggle('pending', password.length < 8);
            
            // Validar mayúscula
            document.getElementById('req-upper').classList.toggle('done', /[A-Z]/.test(password));
            document.getElementById('req-upper').classList.toggle('pending', !/[A-Z]/.test(password));
            
            // Validar minúscula
            document.getElementById('req-lower').classList.toggle('done', /[a-z]/.test(password));
            document.getElementById('req-lower').classList.toggle('pending', !/[a-z]/.test(password));
            
            // Validar número
            document.getElementById('req-number').classList.toggle('done', /[0-9]/.test(password));
            document.getElementById('req-number').classList.toggle('pending', !/[0-9]/.test(password));
            
            // Validar carácter especial
            document.getElementById('req-special').classList.toggle('done', /[!@#$%^&*]/.test(password));
            document.getElementById('req-special').classList.toggle('pending', !/[!@#$%^&*]/.test(password));
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
