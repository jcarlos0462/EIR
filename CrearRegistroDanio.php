<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include $_SERVER['DOCUMENT_ROOT'] . '/database_connection.php';

$sql = "CREATE TABLE IF NOT EXISTS RegistroDanio (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    VIN VARCHAR(50) NOT NULL,
    CodAreaDano INT NOT NULL,
    CodTipoDano INT NOT NULL,
    CodSeveridadDano INT NOT NULL,
    Descripcion TEXT,
    FechaRegistro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (VIN) REFERENCES vehiculo(VIN),
    FOREIGN KEY (CodAreaDano) REFERENCES areadano(CodAreaDano),
    FOREIGN KEY (CodTipoDano) REFERENCES tipodano(CodTipoDano),
    FOREIGN KEY (CodSeveridadDano) REFERENCES severidaddano(CodSeveridadDano)
) ENGINE=InnoDB;";

if ($conn->query($sql) === TRUE) {
    echo "Tabla RegistroDanio creada correctamente.";
} else {
    echo "Error al crear la tabla: " . $conn->error;
}
$conn->close();