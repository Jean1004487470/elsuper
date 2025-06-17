<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Verificar permisos
verificarAcceso('crear_productos');

$db = getDBConnection();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitizeInput($_POST['nombre'] ?? '');
    $descripcion = sanitizeInput($_POST['descripcion'] ?? '');
    $precio = filter_var($_POST['precio'] ?? '', FILTER_VALIDATE_FLOAT);
    $stock = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);

    // Validaciones básicas
    if (empty($nombre) || $precio === false || $precio < 0 || $stock === false || $stock < 0) {
        $message = 'Todos los campos obligatorios (Nombre, Precio, Stock) deben ser válidos y no negativos.';
        $message_type = 'danger';
    } else {
        try {
            // Verificar si ya existe un producto con el mismo nombre
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM productos WHERE nombre = :nombre");
            $stmt_check->bindParam(':nombre', $nombre);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) {
                $message = 'Ya existe un producto con este nombre.';
                $message_type = 'warning';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO productos (nombre, descripcion, precio, stock, fecha_registro)
                    VALUES (:nombre, :descripcion, :precio, :stock, NOW())
                ");
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':descripcion', $descripcion);
                $stmt->bindParam(':precio', $precio);
                $stmt->bindParam(':stock', $stock);

                if ($stmt->execute()) {
                    $message = 'Producto creado exitosamente.';
                    $message_type = 'success';
                    registrarActividad('Producto creado', 'Producto ID: ' . $db->lastInsertId() . ', Nombre: ' . $nombre);
                    // Limpiar campos después de un éxito
                    $nombre = '';
                    $descripcion = '';
                    $precio = '';
                    $stock = '';
                } else {
                    $message = 'Error al crear el producto.';
                    $message_type = 'danger';
                }
            }
        } catch (PDOException $e) {
            $message = 'Error de base de datos: ' . $e->getMessage();
            $message_type = 'danger';
            registrarActividad('Error al crear producto', 'Error: ' . $e->getMessage(), 'error');
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Producto - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><?php echo APP_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="consulta.php">Productos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="crear.php">Nuevo Producto</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <h2 class="mb-4">Crear Nuevo Producto</h2>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form action="crear.php" method="POST">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre del Producto <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($descripcion ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="precio" class="form-label">Precio <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="precio" name="precio" step="0.01" value="<?php echo htmlspecialchars($precio ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="stock" class="form-label">Stock <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="stock" name="stock" value="<?php echo htmlspecialchars($stock ?? ''); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="bi bi-plus-circle"></i> Crear Producto</button>
                    <a href="consulta.php" class="btn btn-secondary ms-2"><i class="bi bi-arrow-left-circle"></i> Volver</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 