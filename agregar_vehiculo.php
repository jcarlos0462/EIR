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
    <style>
        body {
            background-color: #f5f5f5;
        }
        .main-content {
            padding: 30px;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2">
                <?php include 'sidebar.php'; ?>
            </div>
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Agregar Nuevo Vehículo</h2>
                    
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

        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="buque" class="form-label">Buque *</label>
                            <input type="text" class="form-control" id="buque" name="buque" placeholder="Nombre del buque" value="<?php echo isset($buque) ? htmlspecialchars($buque) : ''; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="viaje" class="form-label">Viaje *</label>
                            <input type="text" class="form-control" id="viaje" name="viaje" placeholder="Número de viaje" value="<?php echo isset($viaje) ? htmlspecialchars($viaje) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="vin" class="form-label">VIN (Número de Identificación) *</label>
                            <input type="text" class="form-control" id="vin" name="vin" placeholder="VIN único del vehículo" value="<?php echo isset($vin) ? htmlspecialchars($vin) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="marca" class="form-label">Marca *</label>
                            <input type="text" class="form-control" id="marca" name="marca" placeholder="Ej: Toyota, Ford" value="<?php echo isset($marca) ? htmlspecialchars($marca) : ''; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="modelo" class="form-label">Modelo *</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" placeholder="Ej: Corolla, Fiesta" value="<?php echo isset($modelo) ? htmlspecialchars($modelo) : ''; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="ano" class="form-label">Año</label>
                            <input type="text" class="form-control" id="ano" name="ano" placeholder="Ej: 2023" value="<?php echo isset($ano) ? htmlspecialchars($ano) : ''; ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="color" class="form-label">Color</label>
                            <input type="text" class="form-control" id="color" name="color" placeholder="Color del vehículo" value="<?php echo isset($color) ? htmlspecialchars($color) : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="puerto" class="form-label">Puerto</label>
                            <input type="text" class="form-control" id="puerto" name="puerto" placeholder="Puerto de origen" value="<?php echo isset($puerto) ? htmlspecialchars($puerto) : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="terminal" class="form-label">Terminal</label>
                            <input type="text" class="form-control" id="terminal" name="terminal" placeholder="Terminal asignada" value="<?php echo isset($terminal) ? htmlspecialchars($terminal) : ''; ?>">
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-submit">Registrar Vehículo</button>
                            <a href="listar_vehiculos.php" class="btn btn-secondary ms-2">Volver</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
