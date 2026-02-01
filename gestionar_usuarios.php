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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(120deg, #6a82fb 0%, #fc5c7d 100%);
            min-height: 100vh;
        }
        .modern-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px 0 rgba(60,60,120,0.10);
            padding: 2.5rem 2rem 2rem 2rem;
            margin: 40px auto 0 auto;
            max-width: 900px;
        }
        .modern-table-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px 0 rgba(60,60,120,0.13);
            padding: 2rem 1.5rem 1.5rem 1.5rem;
            margin-bottom: 2rem;
        }
        .modern-table {
            border-radius: 12px;
            overflow: hidden;
            background: #f4f7fb;
            box-shadow: 0 2px 8px 0 rgba(60,60,120,0.07);
        }
        .modern-table thead {
            background: linear-gradient(90deg, #426dc9 60%, #6a82fb 100%);
            color: #fff;
            font-size: 1.08rem;
            letter-spacing: 0.5px;
        }
        .modern-table th, .modern-table td {
            vertical-align: middle;
            font-size: 1.08rem;
        }
        .modern-table th {
            border: none;
        }
        .modern-table td {
            background: #fff;
            border-top: 1px solid #e0e6f7;
        }
        .modern-table tbody tr:hover {
            background: #f0f4ff;
            transition: background 0.2s;
        }
        .modern-btn {
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 0.7rem 1.5rem;
            box-shadow: 0 2px 8px 0 rgba(60,60,120,0.08);
        }
        .modern-btn-primary {
            background: linear-gradient(90deg, #426dc9 60%, #6a82fb 100%);
            color: #fff;
            border: none;
        }
        .modern-btn-primary:hover {
            background: linear-gradient(90deg, #2d4e8c 60%, #426dc9 100%);
            color: #fff;
        }
        .modern-btn-success {
            background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
            color: #fff;
            border: none;
        }
        .modern-btn-success:hover {
            background: linear-gradient(90deg, #38f9d7 0%, #43e97b 100%);
            color: #fff;
        }
        .modern-label {
            font-weight: 700;
            color: #426dc9;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        .modern-input {
            font-size: 1.1rem;
            border-radius: 12px;
            padding: 0.7rem 1.2rem;
            border: 2px solid #e0e6f7;
            box-shadow: 0 2px 8px 0 rgba(60,60,120,0.04);
        }
        .password-requirements {
            background-color: #f0f4ff;
            border-left: 4px solid #426dc9;
            padding: 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 10px;
        }
        .req-item {
            margin: 5px 0;
        }
        .req-item.done {
            color: #43e97b;
        }
        .req-item.pending {
            color: #f857a6;
        }
        @media (max-width: 768px) {
            .modern-card, .modern-table-card {
                padding: 1.2rem 0.7rem 1rem 0.7rem;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col-md-9 col-lg-10 main-content">

    <div class="modern-card mb-4 d-flex flex-column flex-md-row justify-content-between align-items-center">
        <h2 class="mb-3 mb-md-0">Gestionar Usuarios</h2>
        <a href="Administrar.php" class="modern-btn btn-secondary">Volver</a>
    </div>

    <div class="row">
        <!-- Formulario Agregar Usuario -->
        <div class="col-lg-6 mb-4">
            <div class="modern-card h-100">
                <h5 class="mb-3">Crear Nuevo Usuario</h5>
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
                <form method="POST" action="" class="d-flex flex-column gap-3">
                    <div>
                        <label for="nombre" class="modern-label">Nombre Completo *</label>
                        <input type="text" class="form-control modern-input" id="nombre" name="nombre" placeholder="Ej: Juan Pérez" required>
                    </div>
                    <div>
                        <label for="usuario" class="modern-label">Usuario *</label>
                        <input type="text" class="form-control modern-input" id="usuario" name="usuario" placeholder="Ej: jperez" required>
                    </div>
                    <div>
                        <label for="password" class="modern-label">Contraseña *</label>
                        <input type="password" class="form-control modern-input" id="password" name="password" placeholder="Ingrese contraseña" required>
                    </div>
                    <div>
                        <label for="password_confirm" class="modern-label">Confirmar Contraseña *</label>
                        <input type="password" class="form-control modern-input" id="password_confirm" name="password_confirm" placeholder="Confirme contraseña" required>
                    </div>
                    <div class="password-requirements">
                        <strong>Requisitos de contraseña fuerte:</strong>
                        <div class="req-item pending" id="req-length">✗ Mínimo 8 caracteres</div>
                        <div class="req-item pending" id="req-upper">✗ Al menos una mayúscula</div>
                        <div class="req-item pending" id="req-lower">✗ Al menos una minúscula</div>
                        <div class="req-item pending" id="req-number">✗ Al menos un número</div>
                        <div class="req-item pending" id="req-special">✗ Al menos un carácter especial (!@#$%^&*)</div>
                    </div>
                    <button type="submit" class="modern-btn modern-btn-primary w-100 mt-2">Crear Usuario</button>
                </form>
            </div>
        </div>
        <!-- Lista de Usuarios -->
        <div class="col-lg-6 mb-4">
            <div class="modern-table-card h-100">
                <h5 class="mb-3">Usuarios Registrados</h5>
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
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
