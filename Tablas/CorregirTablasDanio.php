<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include '../database_connection.php';

$errores = [];

// 1. Agregar campo ID autoincremental y PRIMARY KEY a las tablas base
$tablas = ['areadano', 'tipodano', 'severidaddano'];
foreach ($tablas as $tabla) {
    $sql = "ALTER TABLE $tabla ADD COLUMN ID INT AUTO_INCREMENT PRIMARY KEY FIRST;";
    if (!$conn->query($sql)) {
        if (strpos($conn->error, 'Duplicate column name') === false) {
            $errores[] = "Error en $tabla: " . $conn->error;
        }
    }
}

// 2. Asegurar que VIN en vehiculo sea VARCHAR(50) NOT NULL y UNIQUE
$sql = "ALTER TABLE vehiculo MODIFY COLUMN VIN VARCHAR(50) NOT NULL;";
if (!$conn->query($sql)) {
    $errores[] = "Error modificando VIN: " . $conn->error;
}
$sql = "ALTER TABLE vehiculo ADD UNIQUE (VIN);";
if (!$conn->query($sql)) {
    if (strpos($conn->error, 'Duplicate key name') === false && strpos($conn->error, 'Duplicate entry') === false) {
        $errores[] = "Error agregando UNIQUE VIN: " . $conn->error;
    }
}

// 3. Crear la tabla RegistroDanio
$sql = "CREATE TABLE IF NOT EXISTS RegistroDanio (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    VIN VARCHAR(50) NOT NULL,
    AreaID INT NOT NULL,
    TipoID INT NOT NULL,
    SeveridadID INT NOT NULL,
    UsuarioID INT NOT NULL,
    TipoOperacion VARCHAR(100) NOT NULL,
    Origen VARCHAR(100),
    FechaRegistro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (VIN) REFERENCES vehiculo(VIN),
    FOREIGN KEY (AreaID) REFERENCES areadano(ID),
    FOREIGN KEY (TipoID) REFERENCES tipodano(ID),
    FOREIGN KEY (SeveridadID) REFERENCES severidaddano(ID),
    FOREIGN KEY (UsuarioID) REFERENCES usuario(ID)
) ENGINE=InnoDB;";
if (!$conn->query($sql)) {
    $errores[] = "Error creando RegistroDanio: " . $conn->error;
}

if ($errores) {
    echo "<b>Errores encontrados:</b><br>" . implode('<br>', $errores);
} else {
    echo "Tablas corregidas y RegistroDanio creada correctamente.";
}
$conn->close();
