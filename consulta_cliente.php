<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes de "elsuper"</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }
        h1 {
            color: #333;
            text-align: center;
        }
        table {
            width: 90%;
            margin: 20px auto;
            border-collapse: collapse;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            background-color: white;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .error {
            color: red;
            text-align: center;
            padding: 10px;
            border: 1px solid red;
            background-color: #ffebeb;
        }
        .no-records {
            color: #555;
            text-align: center;
            padding: 10px;
            border: 1px solid #ccc;
            background-color: #e9e9e9;
        }
    </style>
</head>
<body>
     <header>
        <h1>📋 Listado de Clientes - Base de Datos "elsuper"</h1>
    </header>

    <nav>
        <ul>
            <li><a href="index.html">Inicio</a></li>
            <li><a href="consulta.php">Consulta</a></li>
            <li><a href="crear_cliente.php">Crear Cliente</a></li>
        </ul>
    </nav>

   

    <?php
    // --- Configuración de la Conexión ---
    $servername = "localhost"; // o la IP/dominio de tu servidor MySQL
    $username = "root"; // Reemplaza con tu usuario de MySQL
    $password = ""; // Reemplaza con tu contraseña de MySQL
    $dbname = "elsuper";
    $tableName = "clientes";
    $primaryKeyColumn = "id_cliente"; // IMPORTANTE: Cambia "id" si tu clave primaria se llama diferente

 // --- Crear Conexión ---
    $conn = new mysqli($servername, $username, $password, $dbname);

    // --- Verificar Conexión ---
    if ($conn->connect_error) {
        echo "<div class='error'><strong>Error de conexión:</strong> " . htmlspecialchars($conn->connect_error) . "</div>";
        die();
    }

    // --- Preparar la Consulta SQL ---
    $sql = "SELECT * FROM " . $tableName;
    $result = $conn->query($sql);

    if ($result === false) {
        echo "<div class='error'><strong>Error al ejecutar la consulta:</strong> " . htmlspecialchars($conn->error) . "</div>";
    } elseif ($result->num_rows > 0) {
        echo "<table>";
        echo "<tr>";
        $fieldinfo = $result->fetch_fields();
        foreach ($fieldinfo as $val) {
            echo "<th>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $val->name))) . "</th>";
        }
        echo "<th>Editar</th>"; // Nueva columna para el botón Editar
        echo "<th>Eliminar</th>"; // Nueva columna para el botón Eliminar
        echo "</tr>";

        while($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach($row as $cell) {
                echo "<td>" . htmlspecialchars($cell ?? '') . "</td>";
            }
            // Obtener el ID del cliente para los enlaces
            // ASEGÚRATE de que $primaryKeyColumn coincida con el nombre de tu columna de clave primaria
            $clientId = isset($row[$primaryKeyColumn]) ? htmlspecialchars($row[$primaryKeyColumn]) : '';

            if ($clientId === '') {
                // Manejar el caso donde el ID no está presente o es nulo, si es necesario
                echo "<td>ID no disponible</td>";
                echo "<td>ID no disponible</td>";
            } else {
                // Botón Editar
                echo "<td><a href='editar_cliente.php?id=" . $clientId . "' class='btn btn-edit'>Editar</a></td>";
                // Botón Eliminar con confirmación JavaScript
                echo "<td><a href='eliminar_cliente.php?id=" . $clientId . "' class='btn btn-delete' onclick='return confirm(\"¿Estás realmente seguro de que deseas eliminar este cliente? Esta acción no se puede deshacer.\");'>Eliminar</a></td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='no-records'>ℹ️ No se encontraron registros en la tabla '" . htmlspecialchars($tableName) . "'.</div>";
    }

    $conn->close();
    ?>

</body>
</html>