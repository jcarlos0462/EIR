<?php
session_start();
include 'database_connection.php';
// Verificar si el usuario está logueado
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header("Location: index.php");
    exit();
}
// Consultas para contadores dinámicos
$vehiculos_count = 0;
$danios_count = 0;
$usuarios_count = 0;
// Vehículos
$res = $conn->query("SELECT COUNT(*) AS total FROM vehiculo");
if ($res && $row = $res->fetch_assoc()) $vehiculos_count = $row['total'];
// Daños
$res = $conn->query("SELECT COUNT(*) AS total FROM RegistroDanio");
if ($res && $row = $res->fetch_assoc()) $danios_count = $row['total'];
// Usuarios
$res = $conn->query("SELECT COUNT(*) AS total FROM usuario");
if ($res && $row = $res->fetch_assoc()) $usuarios_count = $row['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar - EIR</title>
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
            max-width: 600px;
        }
        .modern-dashboard-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px 0 rgba(60,60,120,0.13);
            padding: 2rem 1.5rem 1.5rem 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
            transition: box-shadow 0.2s;
        }
        .modern-dashboard-card:hover {
            box-shadow: 0 8px 32px 0 rgba(60,60,120,0.18);
        }
        .modern-dashboard-icon {
            font-size: 2.5rem;
            color: #426dc9;
            margin-bottom: 0.5rem;
        }
        .modern-dashboard-title {
            font-weight: 700;
            color: #426dc9;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        .modern-dashboard-count {
            font-size: 2.5rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 0.5rem;
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
            .modern-dashboard-card {
                padding: 1.2rem 0.7rem 1rem 0.7rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar_toggler.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2">
                <?php include 'sidebar.php'; ?>
            </div>
            <div class="col-md-9 col-lg-10 main-content">
                <div class="modern-card mb-4 text-center">
                    <h2 class="mb-2">Dashboard</h2>
                    <p class="text-muted mb-0">Bienvenido al sistema EIR</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
