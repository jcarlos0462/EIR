<?php
// Script para actualizar contraseñas existentes con hash
// Ejecutar UNA SOLA VEZ

include 'database_connection.php';

// Obtener todos los usuarios
$sql = "SELECT ID, Contraseña FROM usuario";
$result = $conn->query($sql);

$actualizado = 0;
$error = 0;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Verificar si ya está hasheado (bcrypt comienza con $2)
        if (!preg_match('/^\$2[aby]\$/', $row['Contraseña'])) {
            // No está hasheado, hacerlo ahora
            $hash = password_hash($row['Contraseña'], PASSWORD_BCRYPT);
            $id = $row['ID'];
            
            $sql_update = "UPDATE usuario SET Contraseña = ? WHERE ID = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("si", $hash, $id);
            
            if ($stmt->execute()) {
                $actualizado++;
            } else {
                $error++;
            }
            $stmt->close();
        }
    }
}

echo "Script de actualización de contraseñas completado<br>";
echo "Contraseñas actualizadas: " . $actualizado . "<br>";
echo "Errores: " . $error . "<br>";
echo "<br><a href='index.html'>Volver al login</a>";

$conn->close();
?>
