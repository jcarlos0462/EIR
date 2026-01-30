<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar - EIR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="navbar_styles.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .sidebar {
            background-color: white;
            border-right: 1px solid #e0e0e0;
            min-height: calc(100vh - 56px);
            padding: 20px 0;
        }
        .sidebar a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .sidebar a:hover {
            background-color: #f5f5f5;
            border-left-color: #667eea;
            color: #667eea;
        }
        .sidebar a.active {
            background-color: #f0f0f0;
            border-left-color: #667eea;
            color: #667eea;
            font-weight: 600;
        }
        .main-content {
            padding: 30px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        @media (max-width: 768px) {
            .sidebar {
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
                min-height: auto;
                display: flex;
                flex-wrap: wrap;
            }
            .sidebar a {
                flex: 1 1 auto;
                min-width: 100px;
                text-align: center;
                border-left: none;
                border-bottom: 1px solid #e0e0e0;
            }
            .main-content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="row">
                    <div class="col-12">
                        <h2>Dashboard</h2>
                        <p class="text-muted">Bienvenido al sistema EIR</p>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Vehículos</h5>
                                <p class="card-text display-6">0</p>
                                <a href="listar_vehiculos.php" class="btn btn-sm btn-primary">Ver</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Daños Registrados</h5>
                                <p class="card-text display-6">0</p>
                                <a href="#" class="btn btn-sm btn-primary">Ver</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Usuarios</h5>
                                <p class="card-text display-6">0</p>
                                <a href="gestionar_usuarios.php" class="btn btn-sm btn-primary">Ver</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
