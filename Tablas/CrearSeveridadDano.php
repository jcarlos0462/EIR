<?php
// Conexión a la base de datos en Hostinger
include '../database_connection.php';

// Crear tabla severidad de daño
$sql = "CREATE TABLE IF NOT EXISTS severidaddano (
    CodSeveridadDano INT(10) PRIMARY KEY AUTO_INCREMENT,
    NomSeveridadDano VARCHAR(255) NOT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Tabla 'severidaddano' creada exitosamente";
    
    // Insertar datos iniciales
    $insertData = "INSERT INTO severidaddano (NomSeveridadDano) VALUES
    ('Menos de 1pulg incluyendo 1pulg / menos de 2.5 cm'),
    ('Más de 1pulg hasta 3 incluyendo 3pulg longitud / diámetro – 2.5 cm hasta 7.5 cm'),
    ('Más de 3pulg hasta 6 incluyendo 6pulg longitud / diámetro – 7.5 cm hasta 15 cm'),
    ('Más de 6pulg hasta 12 incluyendo 12pulg longitud / diámetro – 15 cm hasta 30 cm'),
    ('Más de 12pulg longitud / diámetro – 30 cm'),
    ('Falta/daño mayor')";
    
    if ($conn->query($insertData) === TRUE) {
        echo " y datos insertados exitosamente";
    } else {
        echo " pero error al insertar datos: " . $conn->error;
    }
} else {
    echo "Error al crear tabla: " . $conn->error;
}

$conn->close();
?>
