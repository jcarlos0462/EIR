<?php
// Conexión a la base de datos en Hostinger
$servername = "localhost";
$username = "u174025152_Administrador";
$password = "0066jv_A2";
$dbname = "u174025152_EIR";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Crear tabla tipo de daño
$sql = "CREATE TABLE IF NOT EXISTS tipodano (
    CodTipoDano INT(10) PRIMARY KEY AUTO_INCREMENT,
    NomTipoDano VARCHAR(100) NOT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Tabla 'tipodano' creada exitosamente";
    
    // Insertar datos iniciales
    $insertData = "INSERT INTO tipodano (NomTipoDano) VALUES
    ('Raspadura - no aplica a vidrios'),
    ('Rasgadura'),
    ('Pintura o superficie cromada abollada pero no dañada'),
    ('Cobertura completa de protección del vehículo - Dañada'),
    ('Evento Térmico/Fuego'),
    ('Moldura/Emblema/Sellos dañados'),
    ('Moldura/Emblema/Sellos sueltos'),
    ('Vidrio agrietado'),
    ('Vidrio Roto'),
    ('Vidrio astillado'),
    ('Vidrio Rayado'),
    ('Luz de marcado dañada'),
    ('Etiquetas or franjas de pintura o calcomanías dañadas'),
    ('Contaminación, Exterior'),
    ('Derrame de líquido, Exterior'),
    ('Robo y vandalismo'),
    ('Estillado en el Borde del Panel'),
    ('Parte incorrecta o accesorio no como facturado'),
    ('Hardware - Dañado'),
    ('Hardware - suelto, perdido')";
    
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
