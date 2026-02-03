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

// Obtener todos los vehículos
$sql = "SELECT * FROM vehiculo ORDER BY ID DESC";
$result = $conn->query($sql);

// Procesar actualización si se envía
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id'])) {
    $id = $_POST['id'];
    $buque = trim($_POST['buque']);
    $viaje = trim($_POST['viaje']);
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $color = trim($_POST['color']);
    $ano = trim($_POST['ano']);
    $puerto = trim($_POST['puerto']);
    $terminal = trim($_POST['terminal']);
    
    $sql_update = "UPDATE vehiculo SET Buque=?, Viaje=?, Marca=?, Modelo=?, Color=?, Año=?, Puerto=?, Terminal=? WHERE ID=?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("ssssssssi", $buque, $viaje, $marca, $modelo, $color, $ano, $puerto, $terminal, $id);
    
    if ($stmt->execute()) {
        header("Location: listar_vehiculos.php?exito=1");
        exit();
    }
    $stmt->close();
}

// Procesar eliminación si se solicita
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $sql_delete = "DELETE FROM vehiculo WHERE ID = ?";
    $stmt = $conn->prepare($sql_delete);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: listar_vehiculos.php?exito=2");
        exit();
    }
    $stmt->close();
}

// Modo edición
$edit_mode = false;
$vehiculo_edit = null;
if (isset($_GET['editar'])) {
    $edit_mode = true;
    $id_edit = $_GET['editar'];
    $sql_edit = "SELECT * FROM vehiculo WHERE ID = ?";
    $stmt = $conn->prepare($sql_edit);
    $stmt->bind_param("i", $id_edit);
    $stmt->execute();
    $vehiculo_edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Vehículos - EIR</title>
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
        .user-info {
            color: white;
            font-size: 14px;
        }
        .table {
            background: white;
        }
        .btn-edit {
            background-color: #667eea;
            color: white;
            border: none;
        }
        .btn-edit:hover {
            background-color: #764ba2;
            color: white;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
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
                    <h2>Lista de Vehículos</h2>
                    <div>
                        <a href="agregar_vehiculo.php" class="btn btn-primary">Agregar Vehículo</a>
                        <a href="listar_vehiculos.php" class="btn btn-secondary ms-2">Volver</a>
                    </div>
                </div>

                <?php if (isset($_GET['exito'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_GET['exito'] == 2 ? 'Vehículo eliminado exitosamente' : 'Vehículo actualizado exitosamente'; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>                        
                                <th>Buque</th>
                                <th>Viaje</th>
                                <th>VIN</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Año</th>
                                <th>Color</th>
                                <th>Puerto</th>
                                <th>Terminal</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr >                                      
                                        <td><?php echo htmlspecialchars($row['Buque']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Viaje']); ?></td>
                                        <td><?php echo htmlspecialchars($row['VIN']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Marca']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Modelo']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Año']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Color']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Puerto']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Terminal']); ?></td>
                                        <td>
                                            <a href="?editar=<?php echo $row['ID']; ?>" class="btn btn-sm btn-edit">Editar</a>
                                            <a href="?eliminar=<?php echo $row['ID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de que desea eliminar este vehículo?')">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">No hay vehículos registrados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Editar -->
    <?php if ($edit_mode && $vehiculo_edit): ?>
        <div class="modal fade show" id="editModal" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Vehículo</h5>
                        <a href="listar_vehiculos.php" class="btn-close"></a>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="">
                            <input type="hidden" name="id" value="<?php echo $vehiculo_edit['ID']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="buque" class="form-label">Buque</label>
                                    <input type="text" class="form-control" name="buque" value="<?php echo htmlspecialchars($vehiculo_edit['Buque']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="viaje" class="form-label">Viaje</label>
                                    <input type="text" class="form-control" name="viaje" value="<?php echo htmlspecialchars($vehiculo_edit['Viaje']); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Marca</label>
                                    <input type="text" class="form-control" name="marca" value="<?php echo htmlspecialchars($vehiculo_edit['Marca']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Modelo</label>
                                    <input type="text" class="form-control" name="modelo" value="<?php echo htmlspecialchars($vehiculo_edit['Modelo']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Año</label>
                                    <input type="text" class="form-control" name="ano" value="<?php echo htmlspecialchars($vehiculo_edit['Año']); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Color</label>
                                    <input type="text" class="form-control" name="color" value="<?php echo htmlspecialchars($vehiculo_edit['Color']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Puerto</label>
                                    <input type="text" class="form-control" name="puerto" value="<?php echo htmlspecialchars($vehiculo_edit['Puerto']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Terminal</label>
                                    <input type="text" class="form-control" name="terminal" value="<?php echo htmlspecialchars($vehiculo_edit['Terminal']); ?>">
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                                    <a href="listar_vehiculos.php" class="btn btn-secondary ms-2">Cancelar</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
