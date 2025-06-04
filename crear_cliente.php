<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nuevo Cliente</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
            color: #333;
        }
        h1 {
            color: #007bff;
            text-align: center;
            margin-bottom: 30px;
        }
        form {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 20px auto;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="email"],
        input[type="tel"] {
            width: calc(100% - 22px); /* Ancho total menos padding y borde */
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box; /* Incluir padding y borde en el ancho total */
        }
        input[type="submit"] {
            background-color: #28a745; /* Verde para el botón de guardar */
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 44px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
            width: 100%;
        }
        input[type="submit"]:hover {
            background-color: #218838; /* Verde más oscuro al pasar el ratón */
        }
        .message {
            text-align: center;
            padding: 10px;
            margin-top: 20px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        nav ul {
            list-style-type: none;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: #333;
            text-align: center; /* Centrar los elementos de la navegación */
        }
        nav li {
            display: inline-block; /* Elementos en línea para la navegación horizontal */
        }
        nav li a {
            display: block;
            color: white;
            text-align: center;
            padding: 14px 16px;
            text-decoration: none;
        }
        nav li a:hover {
            background-color: #111;
        }
    </style>
</head>
<body>
    <header>
        <h1>➕ Crear Nuevo Cliente</h1>
    </header>

    <nav>
        <ul>
            <li><a href="index.html">Inicio</a></li>
            <li><a href="consulta_cliente.php">Ver Clientes</a></li> <li><a href="editar.php">Servicios</a></li>
        </ul>
    </nav>

    <?php
    // --- Configuración de la Conexión ---
    $servername = "localhost"; // o la IP/dominio de tu servidor MySQL
    $username = "root"; // Reemplaza con tu usuario de MySQL
    $password = ""; // Reemplaza con tu contraseña de MySQL
    $dbname = "elsuper";
    $tableName = "clientes";

    // Inicializamos una variable para los mensajes de éxito o error
    $message = '';
    $messageType = ''; // 'success' o 'error'

    // --- Verificar si se envió el formulario (cuando el usuario hace clic en "Guardar Cliente") ---
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // --- Crear Conexión ---
        $conn = new mysqli($servername, $username, $password, $dbname);

        // --- Verificar Conexión ---
        if ($conn->connect_error) {
            $message = "Error de conexión: " . htmlspecialchars($conn->connect_error);
            $messageType = 'error';
        } else {
            // --- Recoger los datos del formulario ---
            // Usamos htmlspecialchars para prevenir ataques XSS básicos
            // NOTA: Los nombres en $_POST[] deben coincidir con el 'name' de tus inputs en el formulario HTML.
            $nombre = htmlspecialchars($_POST['nombre'] ?? '');
            $correo = htmlspecialchars($_POST['correo'] ?? ''); // Campo 'correo'
            $telefono = htmlspecialchars($_POST['telefono'] ?? ''); // Campo 'telefono'
            
            // --- Preparar la consulta SQL para insertar datos ---
            // Las columnas listadas aquí deben coincidir exactamente con los nombres de las columnas
            // de tu tabla 'clientes' (excepto 'id_cliente' si es AUTO_INCREMENT).
            // El número de '?' debe coincidir con el número de columnas.
            $sql = "INSERT INTO " . $tableName . " (nombre, correo, telefono) VALUES (?, ?, ?)";
            
            // --- Preparamos la declaración para mayor seguridad ---
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                // --- Vincular parámetros (asociar las variables con los signos de interrogación) ---
                // "sss" indica que todos los 3 parámetros son strings (nombre, correo, telefono).
                // El número de tipos ('s' en este caso) debe coincidir con el número de '?' y variables.
                $stmt->bind_param("sss", $nombre, $correo, $telefono);

                // --- Ejecutar la consulta ---
                if ($stmt->execute()) {
                    $message = "¡Cliente guardado exitosamente!";
                    $messageType = 'success';
                } else {
                    $message = "Error al guardar el cliente: " . htmlspecialchars($stmt->error);
                    $messageType = 'error';
                }
                // --- Cerrar la declaración ---
                $stmt->close();
            } else {
                $message = "Error al preparar la consulta: " . htmlspecialchars($conn->error);
                $messageType = 'error';
            }
            // --- Cerrar la conexión ---
            $conn->close();
        }
    }
    ?>

    <?php if ($message): // Mostramos el mensaje si existe ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form action="crear_cliente.php" method="post">
        <label for="nombre">Nombre:</label>
        <input type="text" id="nombre" name="nombre" required>

        <label for="correo">Correo Electrónico:</label>
        <input type="email" id="correo" name="correo">

        <label for="telefono">Teléfono:</label>
        <input type="tel" id="telefono" name="telefono">

        <input type="submit" value="Guardar Cliente">
    </form>
</body>
</html>