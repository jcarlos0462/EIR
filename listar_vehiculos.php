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
        .modern-btn-danger {
            background: linear-gradient(90deg, #f857a6 0%, #ff5858 100%);
            color: #fff;
            border: none;
        }
        .modern-btn-danger:hover {
            background: linear-gradient(90deg, #ff5858 0%, #f857a6 100%);
            color: #fff;
        }
        .modern-btn-warning {
            background: linear-gradient(90deg, #f7971e 0%, #ffd200 100%);
            color: #333;
            border: none;
        }
        .modern-btn-warning:hover {
            background: linear-gradient(90deg, #ffd200 0%, #f7971e 100%);
            color: #222;
        }
        .modern-modal-header-primary {
            background: linear-gradient(90deg, #426dc9 60%, #6a82fb 100%);
            color: #fff;
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
        }
        .modern-modal-content {
            border-radius: 18px;
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
            <div class="col-md-3 col-lg-2">
                <?php include 'sidebar.php'; ?>
            </div>
            <div class="col-md-9 col-lg-10 main-content">
                <div class="modern-card mb-4 d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <h2 class="mb-3 mb-md-0">Lista de Vehículos</h2>
                    <div class="d-flex gap-2">
                        <a href="agregar_vehiculo.php" class="modern-btn modern-btn-primary"><i class="bi bi-plus-lg"></i> Agregar Vehículo</a>
                        <a href="Administrar.php" class="modern-btn btn-secondary">Volver</a>
                    </div>
                </div>

                <?php if (isset($_GET['exito'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_GET['exito'] == 2 ? 'Vehículo eliminado exitosamente' : 'Vehículo actualizado exitosamente'; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="modern-table-card">
                    <div class="table-responsive">
                        <table class="table modern-table mb-0">
                            <thead>
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
                                        <tr>
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
                                                <a href="?editar=<?php echo $row['ID']; ?>" class="modern-btn modern-btn-warning btn-sm me-1"><i class="bi bi-pencil-square"></i></a>
                                                <a href="?eliminar=<?php echo $row['ID']; ?>" class="modern-btn modern-btn-danger btn-sm" onclick="return confirm('¿Está seguro de que desea eliminar este vehículo?')"><i class="bi bi-trash-fill"></i></a>
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

    <!-- Modal para Editar -->
    <?php if ($edit_mode && $vehiculo_edit): ?>
        <div class="modal fade show" id="editModal" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content modern-modal-content">
                    <div class="modal-header modern-modal-header-primary">
                        <h5 class="modal-title">Editar Vehículo</h5>
                        <a href="listar_vehiculos.php" class="btn-close"></a>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="" class="d-flex flex-column gap-3">
                            <input type="hidden" name="id" value="<?php echo $vehiculo_edit['ID']; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="buque" class="modern-label">Buque</label>
                                    <input type="text" class="form-control modern-input" name="buque" value="<?php echo htmlspecialchars($vehiculo_edit['Buque']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="viaje" class="modern-label">Viaje</label>
                                    <input type="text" class="form-control modern-input" name="viaje" value="<?php echo htmlspecialchars($vehiculo_edit['Viaje']); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="modern-label">Marca</label>
                                    <input type="text" class="form-control modern-input" name="marca" value="<?php echo htmlspecialchars($vehiculo_edit['Marca']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="modern-label">Modelo</label>
                                    <input type="text" class="form-control modern-input" name="modelo" value="<?php echo htmlspecialchars($vehiculo_edit['Modelo']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="modern-label">Año</label>
                                    <input type="text" class="form-control modern-input" name="ano" value="<?php echo htmlspecialchars($vehiculo_edit['Año']); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="modern-label">Color</label>
                                    <input type="text" class="form-control modern-input" name="color" value="<?php echo htmlspecialchars($vehiculo_edit['Color']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="modern-label">Puerto</label>
                                    <input type="text" class="form-control modern-input" name="puerto" value="<?php echo htmlspecialchars($vehiculo_edit['Puerto']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="modern-label">Terminal</label>
                                    <input type="text" class="form-control modern-input" name="terminal" value="<?php echo htmlspecialchars($vehiculo_edit['Terminal']); ?>">
                                </div>
                            </div>
                            <div class="row mt-4">
                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="modern-btn modern-btn-primary">Guardar Cambios</button>
                                    <a href="listar_vehiculos.php" class="modern-btn btn-secondary">Cancelar</a>
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
