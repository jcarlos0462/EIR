<?php
// Script para crear la tabla RegistroDanio en la base de datos
include '../database_connection.php';

$sql = "CREATE TABLE IF NOT EXISTS RegistroDanio (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    VIN VARCHAR(50) NOT NULL,
    AreaID INT NOT NULL,
    TipoID INT NOT NULL,
    SeveridadID INT NOT NULL,
    FechaRegistro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (VIN) REFERENCES vehiculo(VIN),
    FOREIGN KEY (AreaID) REFERENCES areadano(ID),
    FOREIGN KEY (TipoID) REFERENCES tipodano(ID),
    FOREIGN KEY (SeveridadID) REFERENCES severidaddano(ID)
) ENGINE=InnoDB;";

if ($conn->query($sql) === TRUE) {
    echo "Tabla RegistroDanio creada correctamente.";
} else {
    echo "Error al crear la tabla: " . $conn->error;
}
$conn->close();
