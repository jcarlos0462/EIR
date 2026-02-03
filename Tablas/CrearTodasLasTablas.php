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

echo "<h2>Creando Tablas...</h2>";

// Array de tabla y sus datos
$tablas = array(

    // Tabla vehiculo
    array(
        "nombre" => "vehiculo",
        "sql" => "CREATE TABLE IF NOT EXISTS vehiculo (
            ID INT(10) PRIMARY KEY AUTO_INCREMENT,
            Buque VARCHAR(25) NOT NULL,
            Viaje VARCHAR(25) NOT NULL,
            VIN VARCHAR(50) NOT NULL UNIQUE,
            Marca VARCHAR(20) NOT NULL,
            Modelo VARCHAR(15) NOT NULL,
            Color VARCHAR(20),
            Año VARCHAR(10),
            Puerto VARCHAR(30),
            Terminal VARCHAR(20)
        )"
    ),

    // Tabla usuario
    array(
        "nombre" => "usuario",
        "sql" => "CREATE TABLE IF NOT EXISTS usuario (
            ID INT(10) PRIMARY KEY AUTO_INCREMENT,
            Nombre VARCHAR(50) NOT NULL,
            Usuario VARCHAR(30) NOT NULL UNIQUE,
            Contraseña VARCHAR(100) NOT NULL
        )"
    ),

    // Tabla usuario_operacion
    array(
        "nombre" => "usuario_operacion",
        "sql" => "CREATE TABLE IF NOT EXISTS usuario_operacion (
            ID INT(10) PRIMARY KEY AUTO_INCREMENT,
            UsuarioID INT(10) NOT NULL,
            TipoOperacion VARCHAR(100) NOT NULL,
            FechaRegistro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (UsuarioID) REFERENCES usuario(ID)
        )"
    ),

    // Tabla operador
    array(
        "nombre" => "operador",
        "sql" => "CREATE TABLE IF NOT EXISTS operador (
            ID INT(10) PRIMARY KEY AUTO_INCREMENT,
            Nombre VARCHAR(50) NOT NULL,
            Vehículo VARCHAR(50)
        )"
    ),

    // Tabla severidaddano
    array(
        "nombre" => "severidaddano",
        "sql" => "CREATE TABLE IF NOT EXISTS severidaddano (
            CodSeveridadDano INT(10) PRIMARY KEY AUTO_INCREMENT,
            NomSeveridadDano VARCHAR(255) NOT NULL
        )",
        "datos" => "INSERT INTO severidaddano (NomSeveridadDano) VALUES
            ('Menos de 1pulg incluyendo 1pulg / menos de 2.5 cm'),
            ('Más de 1pulg hasta 3 incluyendo 3pulg longitud / diámetro – 2.5 cm hasta 7.5 cm'),
            ('Más de 3pulg hasta 6 incluyendo 6pulg longitud / diámetro – 7.5 cm hasta 15 cm'),
            ('Más de 6pulg hasta 12 incluyendo 12pulg longitud / diámetro – 15 cm hasta 30 cm'),
            ('Más de 12pulg longitud / diámetro – 30 cm'),
            ('Falta/daño mayor')"
    ),

    // Tabla tipodano
    array(
        "nombre" => "tipodano",
        "sql" => "CREATE TABLE IF NOT EXISTS tipodano (
            CodTipoDano INT(10) PRIMARY KEY AUTO_INCREMENT,
            NomTipoDano VARCHAR(100) NOT NULL
        )",
        "datos" => "INSERT INTO tipodano (NomTipoDano) VALUES
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
            ('Hardware - suelto, perdido')"
    ),

    // Tabla areadano
    array(
        "nombre" => "areadano",
        "sql" => "CREATE TABLE IF NOT EXISTS areadano (
            CodAreaDano INT(10) PRIMARY KEY AUTO_INCREMENT,
            NomAreaDano VARCHAR(100) NOT NULL
        )",
        "datos" => "INSERT INTO areadano (NomAreaDano) VALUES
            ('ANTENA/ANTENA BASE'),
            ('BATERÍA'),
            ('PARACHOQUES/CUBIERTA/EXTERIOR-DELANTERO'),
            ('PARACHOQUES/CUBIERTA/EXTERIOR-TRASERO'),
            ('PROTECTOR DE PARACHOQUES/STRIP-DELANTERO'),
            ('PARACHOQUES PROTECTOR/STRIP-REAR'),
            ('PUERTA TRASERA DE CARGA-DERECHA'),
            ('PUERTA TRASERA DE CARGA–IZQUIERDA'),
            ('PUERTA CORREDIZA IZQUIERDA/DERECHA TRASERA'),
            ('PUERTA-FRENTE IZQUIERDO'),
            ('PUERTA-TRASERA IZQUIERDA'),
            ('PUERTA-FRENTE DERECHO'),
            ('PUERTA-DERECHA TRASERA'),
            ('SALPICADERA-DELANTERO IZQUIERDO'),
            ('OTR PANEL/PICK UP CAJA-IZQUIERDA'),
            ('SALPICADERA-DELANTERO DERECHO'),
            ('OTR PANEL/PICK UP CAJA-DERECHA'),
            ('ALFOMBRAS DE PISO-DELANTERO'),
            ('ALFOMBRAS DE PISO-TRASERAS'),
            ('PARABRISA'),
            ('VIDRIO-TRASERO'),
            ('REJILLA'),
            ('ACELERADOR/PEDAL-TRANSMISION DEL VEHICULO/O-BOLSA/CAJA'),
            ('FARO/TAPA/SEÑAL DE ORO'),
            ('LAMPARAS-NIEBLA/CONDUCCION/LUZ PUNTUAL'),
            ('FORRO O COBERTOR INTERIOR DE TECHO'),
            ('CAPO'),
            ('LLAVES'),
            ('CONTROL REMOTO SIN LLAVE'),
            ('ESPEJO-EXTERIOR IZQUIERDO'),
            ('ESPEJO-EXTERIOR DERECHO'),
            ('DAÑO MAYOR/ (Paro uso del OEM)'),
            ('REPRODUCTOR MULTIMEDIA FRONTAL'),
            ('REPRODUCTOR MULTIMEDIA TRASERO'),
            ('ROCKER PANEL/SOLERA EXTERIOR-IZQUIERDA'),
            ('ROCKER PANEL/SOLERA EXTERIOR-RIGHT'),
            ('TECHO'),
            ('CARRERA-PASO A LA IZQUIERDA'),
            ('TABLERO DE CORREIR/PASO-DERECHO'),
            ('NEUMÁTICO DE REPUESTO'),
            ('Cable para cargar vehículo eléctrico'),
            ('PANEL SPLASH/SPOILER-DELANTERO'),
            ('ABIERTO'),
            ('TANQUE DE GASOLINA'),
            ('LUZ DE COLA/HARDWARE'),
            ('CABINA DE CAMION, TRASERA'),
            ('ABIERTO'),
            ('PANEL DE CUBIERTA DE PUERTA-DELANTERO IZQUIERDO'),
            ('ABIERTO'),
            ('PANEL DE CUBIERTA DE PUERTA-DELANTERO DERECHO'),
            ('TUNJEAU'),
            ('TAPA DE LA CUBIERTA/PORTÓN TRASERO/HATCHBACK'),
            ('TECHO CORREDIZO/TECHO SOLAR'),
            ('ÁREA DEBAJO DEL VEHÍCULO'),
            ('ÁREA DE CARGA-OTROS'),
            ('CONVERTIBLE SUPERIOR'),
            ('TAPAS/GORRAS DE RUEDAS'),
            ('ALTAVOCES DE RADIO'),
            ('LIMPIAPARBRISAS-TODOS'),
            ('CHOCKS SALTADO'),
            ('CAJA DE RECOGIDA-INTERIOR'),
            ('TODO EL VEHÍCULO'),
            ('REJEC-CUBIERTA DE CAMA DEL CAMION/BARRA DE LUZ'),
            ('SPOILER/DEFLECTOR-TRASERO'),
            ('PORTAEQUIPAJES (TIRAS)/RIEL DE GOTEO'),
            ('DASH/PANEL DE INSTRUMENTOS'),
            ('ENCENDEDOR DE CIGARRILLOS/BANDEJA DE CENICERA'),
            ('ALFOMBRA - DELANTERO'),
            ('ABIERTO'),
            ('POSTE CENTRAL -IZQUIERDA'),
            ('POSTE DE ESQUINA'),
            ('NEUMÁTICO DELANTERO IZQUIERDO'),
            ('RIM/RUEDA DELANTERA IZQUIERDA'),
            ('NEUMÁTICO TRASERO IZQUIERDO'),
            ('RIM/RUEDA TRASERA IZQUIERDA'),
            ('NEUMÁTICO TRASERO DERECHO'),
            ('RIM/RUEDA TRASERA DERECHA'),
            ('NEUMÁTICO DELANTERO DERECHO'),
            ('RIM/RUEDA DELANTERA DERECHA'),
            ('CONVERTIBLE ENTRE EL HOOD Y CRISTAL DELANTERO.'),
            ('PUERTA/TAPA DE GASOLINA/PUERTA DE CARGA DE BATERÍA'),
            ('SALPICADERA-TRASERA IZQUIERDA'),
            ('SALPICADERA-TRASERA DERECHA'),
            ('HERRAMIENTAS /ACK JUEGO PARA Cambio de llantas & LOCK'),
            ('KIT DE TARJETA MULTIMEDIA'),
            ('SENSORES/SISTEMA SONAR DE PARQUEO'),
            ('ABIERTO'),
            ('ABIERTO'),
            ('ENGANCHE DE REMOLQUE/ARNÉS DE CABLEADO/GANCHOS DE REMOLQUE'),
            ('MARCO'),
            ('TUBO DE ESCAPE'),
            ('SOPORTE DE PLACA DE MATRÍCULA DEL VEHÍCULO'),
            ('VOLANTE/AIRBAG'),
            ('ASIENTO-DELANTERO IZQUIERDO'),
            ('ASIENTO-DELANTERO DERECHO'),
            ('ASIENTO-TRASERO'),
            ('ALFOMBRA-TRASERO'),
            ('INTERIOR'),
            ('COMPARTIMIENTO DEL MOTOR-OTROS')"
    )
);

// Ejecutar creación de tablas
foreach($tablas as $tabla) {
    if ($conn->query($tabla["sql"]) === TRUE) {
        echo "✓ Tabla '{$tabla['nombre']}' creada exitosamente<br>";
        
        // Si hay datos para insertar
        if (isset($tabla["datos"])) {
            // Primero verificar si ya existen datos
            $checkData = $conn->query("SELECT COUNT(*) as count FROM {$tabla['nombre']}");
            $result = $checkData->fetch_assoc();
            
            if ($result['count'] == 0) {
                if ($conn->query($tabla["datos"]) === TRUE) {
                    echo "✓ Datos insertados en '{$tabla['nombre']}'<br>";
                } else {
                    echo "✗ Error al insertar datos en '{$tabla['nombre']}': " . $conn->error . "<br>";
                }
            } else {
                echo "ℹ La tabla '{$tabla['nombre']}' ya contiene datos<br>";
            }
        }
    } else {
        echo "✗ Error al crear tabla '{$tabla['nombre']}': " . $conn->error . "<br>";
    }
}

echo "<hr><h3>Proceso completado!</h3>";

$conn->close();
?>
