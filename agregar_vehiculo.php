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

// Procesar formulario para agregar vehículo
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $buque = trim($_POST['buque']);
    $viaje = trim($_POST['viaje']);
    $vin = trim($_POST['vin']);
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $color = trim($_POST['color']);
    $ano = trim($_POST['ano']);
    $puerto = trim($_POST['puerto']);
    $terminal = trim($_POST['terminal']);
    
    // Validar que no estén vacíos los campos requeridos
    if (empty($buque) || empty($viaje) || empty($vin) || empty($marca) || empty($modelo)) {
        $error = "Los campos Buque, Viaje, VIN, Marca y Modelo son requeridos";
    } else {
        // Insertar en la BD
        $sql = "INSERT INTO vehiculo (Buque, Viaje, VIN, Marca, Modelo, Color, Año, Puerto, Terminal) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssss", $buque, $viaje, $vin, $marca, $modelo, $color, $ano, $puerto, $terminal);
        
        if ($stmt->execute()) {
            $exito = "Vehículo registrado exitosamente";
            // Limpiar el formulario
            $buque = $viaje = $vin = $marca = $modelo = $color = $ano = $puerto = $terminal = '';
        } else {
            if (strpos($stmt->error, 'Duplicate entry') !== false) {
                $error = "El VIN ya existe en la base de datos";
            } else {
                $error = "Error al registrar el vehículo: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Vehículo - EIR</title>
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
        @media (max-width: 768px) {
            .modern-card {
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
                    <h2 class="mb-3 mb-md-0">Agregar Nuevo Vehículo</h2>
                    <a href="listar_vehiculos.php" class="modern-btn btn-secondary">Ver Lista de Vehículos</a>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($exito): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($exito); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <div class="modern-card">
                    <form method="POST" action="" class="d-flex flex-column gap-3">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="buque" class="modern-label">Buque *</label>
                                <input type="text" class="form-control modern-input" id="buque" name="buque" placeholder="Nombre del buque" value="<?php echo isset($buque) ? htmlspecialchars($buque) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="viaje" class="modern-label">Viaje *</label>
                                <input type="text" class="form-control modern-input" id="viaje" name="viaje" placeholder="Número de viaje" value="<?php echo isset($viaje) ? htmlspecialchars($viaje) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="vin" class="modern-label">VIN (Número de Identificación) *</label>
                                <input type="text" class="form-control modern-input" id="vin" name="vin" placeholder="VIN único del vehículo" value="<?php echo isset($vin) ? htmlspecialchars($vin) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="marca" class="modern-label">Marca *</label>
                                <input type="text" class="form-control modern-input" id="marca" name="marca" placeholder="Ej: Toyota, Ford" value="<?php echo isset($marca) ? htmlspecialchars($marca) : ''; ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="modelo" class="modern-label">Modelo *</label>
                                <input type="text" class="form-control modern-input" id="modelo" name="modelo" placeholder="Ej: Corolla, Fiesta" value="<?php echo isset($modelo) ? htmlspecialchars($modelo) : ''; ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="ano" class="modern-label">Año</label>
                                <input type="text" class="form-control modern-input" id="ano" name="ano" placeholder="Ej: 2023" value="<?php echo isset($ano) ? htmlspecialchars($ano) : ''; ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="color" class="modern-label">Color</label>
                                <input type="text" class="form-control modern-input" id="color" name="color" placeholder="Color del vehículo" value="<?php echo isset($color) ? htmlspecialchars($color) : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="puerto" class="modern-label">Puerto</label>
                                <input type="text" class="form-control modern-input" id="puerto" name="puerto" placeholder="Puerto de origen" value="<?php echo isset($puerto) ? htmlspecialchars($puerto) : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="terminal" class="modern-label">Terminal</label>
                                <input type="text" class="form-control modern-input" id="terminal" name="terminal" placeholder="Terminal asignada" value="<?php echo isset($terminal) ? htmlspecialchars($terminal) : ''; ?>">
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="modern-btn modern-btn-primary">Registrar Vehículo</button>
                                <a href="Administrar.php" class="modern-btn btn-secondary">Volver</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
