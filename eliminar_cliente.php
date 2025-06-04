<?php
// --- Configuración de la Conexión ---
$servername = "localhost"; // o la IP/dominio de tu servidor MySQL
$username = "root"; // Reemplaza con tu usuario de MySQL
$password = ""; // Reemplaza con tu contraseña de MySQL
$dbname = "elsuper";
$tableName = "clientes";
$primaryKeyColumn = "id_cliente"; // Asegúrate de que coincida con tu columna de clave primaria

// --- Crear Conexión ---
$conn = new mysqli($servername, $username, $password, $dbname);

// --- Verificar Conexión ---
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// --- Verificar si se recibió un ID de cliente para eliminar ---
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $clientId = $_GET['id'];

    // --- Preparar la consulta SQL para eliminar ---
    // Usamos una consulta preparada para seguridad básica
    $sql = "DELETE FROM " . $tableName . " WHERE " . $primaryKeyColumn . " = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $clientId); // "s" significa que el parámetro es un string (para id_cliente)

        // --- Ejecutar la consulta ---
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Notificación de éxito
                echo "<script>alert('¡Cliente eliminado exitosamente!'); window.location.href = 'consulta_cliente.php';</script>";
            } else {
                // Notificación si no se encontró el cliente
                echo "<script>alert('No se encontró el cliente con ID: " . htmlspecialchars($clientId) . "'); window.location.href = 'consulta_cliente.php';</script>";
            }
        } else {
            // Notificación de error en la ejecución
            echo "<script>alert('Error al intentar eliminar el cliente: " . htmlspecialchars($stmt->error) . "'); window.location.href = 'consulta_cliente.php';</script>";
        }
        $stmt->close();
    } else {
        // Notificación de error en la preparación de la consulta
        echo "<script>alert('Error al preparar la consulta de eliminación: " . htmlspecialchars($conn->error) . "'); window.location.href = 'consulta_cliente.php';</script>";
    }
} else {
    // Notificación si no se proporcionó un ID
    echo "<script>alert('No se ha especificado un ID de cliente para eliminar.'); window.location.href = 'consulta_cliente.php';</script>";
}

$conn->close();
?>