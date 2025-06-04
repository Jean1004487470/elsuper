<?php
// --- Configuración de la Conexión ---
$servername = "localhost";
$username = "root"; // Reemplaza con tu usuario de MySQL (ej. root en XAMPP)
$password = "";          // Reemplaza con tu contraseña (vacía por defecto en XAMPP para root)
$dbname = "elsuper";
$tableName = "clientes";
$primaryKeyColumn = "id_cliente"; // Clave primaria según tu imagen

$mensaje = ""; // Para mostrar mensajes de éxito o error
$cliente = null; // Para almacenar los datos del cliente a editar
$id_cliente_a_procesar = null; // ID del cliente que se está editando o cargando

// --- Crear Conexión ---
$conn = new mysqli($servername, $username, $password, $dbname);

// --- Verificar Conexión ---
if ($conn->connect_error) {
    die("<div class='error'><strong>Error de conexión:</strong> " . htmlspecialchars($conn->connect_error) . "</div>");
}

// --- Determinar el ID del cliente a procesar ---
// Priorizar ID de POST (envío de formulario para actualizar)
// Luego, ID de GET (carga de formulario para editar)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST[$primaryKeyColumn])) {
    $id_cliente_a_procesar = $_POST[$primaryKeyColumn];
} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $id_cliente_a_procesar = $_GET['id'];
}

// --- LÓGICA PARA PROCESAR EL FORMULARIO (CUANDO SE ENVÍA POR POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Se requiere un ID de cliente y el campo nombre para actualizar
    if ($id_cliente_a_procesar && isset($_POST['nombre'])) {
        $nombre = $_POST['nombre'];
        // Usamos el operador de coalescencia nula para manejar campos opcionales
        $telefono = !empty($_POST['telefono']) ? $_POST['telefono'] : null;
        $correo = !empty($_POST['correo']) ? $_POST['correo'] : null;

        // Validación básica: el nombre es obligatorio
        if (empty($nombre)) {
            $mensaje = "<div class='error'>El nombre es obligatorio.</div>";
            // Si el nombre está vacío, $id_cliente_a_procesar sigue establecido,
            // lo que permitirá que los datos del cliente se recarguen en el formulario más abajo.
        } else {
            // Preparar la consulta UPDATE para evitar inyección SQL
            $sql_update = "UPDATE $tableName SET nombre = ?, telefono = ?, correo = ? WHERE $primaryKeyColumn = ?";
            $stmt_update = $conn->prepare($sql_update);

            if ($stmt_update) {
                // Vincular parámetros: sssi -> string, string, string, integer
                $stmt_update->bind_param("sssi", $nombre, $telefono, $correo, $id_cliente_a_procesar);

                if ($stmt_update->execute()) {
                    $mensaje = "<div class='success'>Cliente actualizado correctamente. <a href='consulta_cliente.php'>Volver a la lista</a></div>";
                    // Después de una actualización exitosa, evitamos que el formulario se vuelva a mostrar
                    // estableciendo $cliente y $id_cliente_a_procesar a null.
                    $cliente = null;
                    $id_cliente_a_procesar = null; // Previene la recarga de datos en el siguiente bloque
                } else {
                    $mensaje = "<div class='error'>Error al actualizar el cliente: " . htmlspecialchars($stmt_update->error) . "</div>";
                    // Hubo un error, $id_cliente_a_procesar sigue establecido para recargar datos.
                }
                $stmt_update->close();
            } else {
                $mensaje = "<div class='error'>Error al preparar la consulta de actualización: " . htmlspecialchars($conn->error) . "</div>";
                 // Hubo un error, $id_cliente_a_procesar sigue establecido para recargar datos.
            }
        }
    } elseif (isset($_POST['nombre']) && empty($_POST['nombre'])) {
        // Este caso es redundante si el chequeo anterior de empty($nombre) se ejecuta,
        // pero se mantiene por si el ID no estuviera por alguna razón.
        $mensaje = "<div class='error'>El nombre es obligatorio.</div>";
    }
    // No hay mensaje "Faltan datos para actualizar" si $id_cliente_a_procesar o 'nombre' no están.
    // La actualización simplemente no ocurrirá o fallará si $id_cliente_a_procesar es nulo.
}

// --- LÓGICA PARA CARGAR DATOS DEL CLIENTE (SI UN ID ESTÁ DISPONIBLE Y NO HUBO ÉXITO EN POST) ---
// Este bloque se ejecuta si:
// 1. Es una solicitud GET con un ID.
// 2. Es una solicitud POST, había un ID, pero ocurrió un error (ej. nombre vacío, error SQL).
// NO se ejecutará si una solicitud POST fue exitosa (porque $id_cliente_a_procesar se establece a null).
if ($id_cliente_a_procesar) {
    $sql_select = "SELECT * FROM $tableName WHERE $primaryKeyColumn = ?";
    $stmt_select = $conn->prepare($sql_select);

    if ($stmt_select) {
        $stmt_select->bind_param("i", $id_cliente_a_procesar); // i para integer
        $stmt_select->execute();
        $result = $stmt_select->get_result();

        if ($result->num_rows === 1) {
            $cliente = $result->fetch_assoc();
        } else {
            // Cliente no encontrado. $cliente permanecerá null.
            // No se muestra un mensaje de error específico aquí para reducir validaciones.
            // El HTML manejará el caso de $cliente null.
            $cliente = null;
        }
        $stmt_select->close();
    } else {
        $mensaje = "<div class='error'>Error al preparar la consulta de selección: " . htmlspecialchars($conn->error) . "</div>";
        $cliente = null; // Asegurar que el formulario no se muestre si hay error de BD
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - "elsuper"</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            background-color: #f4f7f6;
            color: #333;
        }
        .container {
            width: 60%;
            margin: 30px auto;
            padding: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: 600;
        }
        input[type="text"], input[type="email"], input[type="tel"] {
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"], .btn-back {
            display: inline-block;
            padding: 12px 20px;
            margin-top: 20px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-size: 1em;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
        }
        input[type="submit"] {
            background-color: #28a745; /* Verde */
        }
        input[type="submit"]:hover {
            background-color: #218838;
        }
        .btn-back {
            background-color: #6c757d; /* Gris */
            margin-left: 10px;
        }
        .btn-back:hover {
            background-color: #5a6268;
        }
        /* Estilos para los mensajes (ya están incluidos en el string $mensaje) */
        .error, .success, .mensaje {
            text-align: center;
            padding: 12px;
            margin: 15px 0;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .mensaje { /* Estilo genérico si no es error ni success */
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📝 Editar Cliente</h1>

        <?php
        // Muestra el mensaje si existe (ya viene con formato HTML de <div class='error/success'>)
        if (!empty($mensaje)) {
            echo $mensaje;
        }
        ?>

        <?php if ($cliente): // Solo mostrar el formulario si se cargaron datos del cliente ?>
        <form action="editar_cliente.php" method="POST">
            <input type="hidden" name="<?php echo $primaryKeyColumn; ?>" value="<?php echo htmlspecialchars($cliente[$primaryKeyColumn]); ?>">

            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>

            <label for="telefono">Teléfono:</label>
            <input type="tel" id="telefono" name="telefono" value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>">

            <label for="correo">Correo:</label>
            <input type="email" id="correo" name="correo" value="<?php echo htmlspecialchars($cliente['correo'] ?? ''); ?>">

            <input type="submit" value="Guardar Cambios">
            <a href="consulta_cliente.php" class="btn-back">Cancelar / Volver</a>
        </form>
        <?php elseif (empty($mensaje) && !$cliente): // Si no hay cliente Y no hay un mensaje de error/éxito previo ?>
            <?php
            // Este mensaje se muestra si se accede por GET sin un ID válido (o ID no encontrado)
            // y no hubo otros errores (como error de conexión o preparación de consulta).
            if ($_SERVER["REQUEST_METHOD"] == "GET" && !$id_cliente_a_procesar) {
                 echo "<div class='mensaje'>No se especificó un ID de cliente para editar o el cliente no existe. <a href='consulta_cliente.php'>Volver a la lista</a></div>";
            }
            // Si fue un POST exitoso, $mensaje no estaría vacío (mensaje de éxito) y $cliente sería null,
            // por lo que este bloque no se ejecutaría.
            // Si fue un POST con error, $mensaje no estaría vacío (mensaje de error).
            ?>
        <?php endif; ?>

    </div>
</body>
</html>