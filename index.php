<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EIR - Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-box {
            max-width: 450px;
            width: 100%;
            padding: 15px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 25px;
            text-align: center;
        }
        .card-header h3 {
            margin: 0;
            font-weight: 700;
            font-size: 24px;
        }
        .card-body {
            padding: 30px;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            color: white;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            color: white;
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }
        .eye-icon {
            width: 18px;
            height: 18px;
            vertical-align: text-bottom;
        }
        .password-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .vis-toggle-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .vis-toggle-label {
            width: 40px;
            height: 36px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background-color: #ffffff;
            transition: border-color 0.2s, background-color 0.2s, color 0.2s;
        }
        .vis-toggle-input:checked + .vis-toggle-label {
            border-color: #667eea;
            background-color: #eef2ff;
            color: #4b5bdc;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="card">
            <div class="card-header">
                <h3>Iniciar Sesión - EIR</h3>
            </div>
            <div class="card-body">
                <?php 
                if (isset($_SESSION['error']) && !empty($_SESSION['error'])): 
                    $error = $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form action="validar_login.php" method="POST">
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Ingrese su usuario" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Ingrese su contraseña" required>
                    </div>

                    <div class="form-check mb-3 password-toggle">
                        <input class="form-check-input vis-toggle-input" type="checkbox" id="verPassword">
                        <label class="form-check-label vis-toggle-label" for="verPassword" aria-label="Ver contraseña">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 5c-5 0-9.27 3.11-11 7 1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/>
                            </svg>
                            <span class="visually-hidden">Ver contraseña</span>
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="tipo_operacion" class="form-label">Tipo de operación</label>
                        <select class="form-select" id="tipo_operacion" name="tipo_operacion" required>
                            <option value="" selected disabled>Seleccione una opción</option>
                            <option value="Descarga Buque">Descarga Buque</option>
                            <option value="Carga Buque">Carga Buque</option>
                            <option value="Descarga FFCC">Descarga FFCC</option>
                            <option value="Carga FFCC">Carga FFCC</option>
                            <option value="Salida GATE">Salida GATE</option>
                            <option value="Ingreso GATE">Ingreso GATE</option>
                            <option value="Almacenaje - Patio">Almacenaje - Patio</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="puerto" class="form-label">Puerto</label>
                        <select class="form-select" id="puerto" name="puerto" required>
                            <option value="" selected disabled>Seleccione una opción</option>
                            <option value="COA">COA - Coatzacoalcos</option>
                            <option value="SCX">SCX - Salina Cruz, Oaxaca</option>
                        </select>
                    </div>
                    
                    
                    <button type="submit" class="btn btn-login w-100">Iniciar Sesión</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const passwordInput = document.getElementById('password');
        const verPasswordInput = document.getElementById('verPassword');

        verPasswordInput.addEventListener('change', function() {
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    </script>
</body>
</html>
