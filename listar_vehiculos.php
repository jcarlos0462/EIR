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
    <?php
    // Modern, responsive listar_vehiculos.php
    include 'database_connection.php';
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Lista de Vehículos</title>
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
            .modern-btn-warning {
                background: linear-gradient(90deg, #f7971e 0%, #ffd200 100%);
                color: #333;
                border: none;
            }
            .modern-btn-warning:hover {
                background: linear-gradient(90deg, #ffd200 0%, #f7971e 100%);
                color: #222;
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
            .modern-modal-header-primary {
                background: linear-gradient(90deg, #426dc9 60%, #6a82fb 100%);
                color: #fff;
                border-top-left-radius: 18px;
                border-top-right-radius: 18px;
            }
            .modern-modal-content {
                border-radius: 18px;
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
                    <div class="modern-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="modern-label">Lista de Vehículos</span>
                            <a href="agregar_vehiculo.php" class="modern-btn modern-btn-primary"><i class="bi bi-plus-lg"></i> Agregar Vehículo</a>
                        </div>
                        <div class="modern-table-card">
                            <div class="table-responsive">
                                <table class="table modern-table mb-2">
                                    <thead>
                                        <tr>
                                            <th>VIN</th>
                                            <th>Marca</th>
                                            <th>Modelo</th>
                                            <th>Color</th>
                                            <th style="width:120px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $result = $conn->query("SELECT VIN, Marca, Modelo, Color FROM vehiculo ORDER BY VIN DESC");
                                        if ($result && $result->num_rows > 0):
                                            while ($row = $result->fetch_assoc()):
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['VIN']); ?></td>
                                            <td><?php echo htmlspecialchars($row['Marca']); ?></td>
                                            <td><?php echo htmlspecialchars($row['Modelo']); ?></td>
                                            <td><?php echo htmlspecialchars($row['Color']); ?></td>
                                            <td>
                                                <button type="button" class="modern-btn modern-btn-warning btn-sm me-1" title="Editar" data-bs-toggle="modal" data-bs-target="#modalEditarVehiculo<?php echo $row['VIN']; ?>">
                                                    <span class="bi bi-pencil-square"></span>
                                                </button>
                                                <form method="post" action="eliminar_vehiculo.php" style="display:inline;">
                                                    <input type="hidden" name="vin" value="<?php echo htmlspecialchars($row['VIN']); ?>">
                                                    <button type="submit" class="modern-btn modern-btn-danger btn-sm" title="Eliminar" onclick="return confirm('¿Seguro que deseas eliminar este vehículo?');">
                                                        <span class="bi bi-trash-fill"></span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <!-- Modal editar vehículo -->
                                        <div class="modal fade" id="modalEditarVehiculo<?php echo $row['VIN']; ?>" tabindex="-1" aria-labelledby="modalEditarVehiculoLabel<?php echo $row['VIN']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content modern-modal-content">
                                                    <div class="modal-header modern-modal-header-primary">
                                                        <h5 class="modal-title" id="modalEditarVehiculoLabel<?php echo $row['VIN']; ?>">Editar Vehículo</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form method="post" action="editar_vehiculo.php" class="d-flex flex-column gap-3">
                                                            <input type="hidden" name="vin" value="<?php echo htmlspecialchars($row['VIN']); ?>">
                                                            <div>
                                                                <label class="modern-label">Marca</label>
                                                                <input type="text" name="marca" class="form-control modern-input" value="<?php echo htmlspecialchars($row['Marca']); ?>" required>
                                                            </div>
                                                            <div>
                                                                <label class="modern-label">Modelo</label>
                                                                <input type="text" name="modelo" class="form-control modern-input" value="<?php echo htmlspecialchars($row['Modelo']); ?>" required>
                                                            </div>
                                                            <div>
                                                                <label class="modern-label">Color</label>
                                                                <input type="text" name="color" class="form-control modern-input" value="<?php echo htmlspecialchars($row['Color']); ?>" required>
                                                            </div>
                                                            <div class="d-grid gap-2 mt-2">
                                                                <button type="submit" class="modern-btn modern-btn-warning">Guardar Cambios</button>
                                                                <button type="button" class="modern-btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; else: ?>
                                        <tr><td colspan="5" class="text-center">Sin vehículos registrados</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
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
