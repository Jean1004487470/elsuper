<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Verificar permisos
verificarAcceso('ver_ventas');

$db = getDBConnection();

$venta = null;
$detalles_venta = [];

// Obtener el ID de la venta de la URL
$id_venta = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);

if (!$id_venta) {
    header('Location: consulta.php?message=Venta no especificada o inválida&type=danger');
    exit();
}

// Cargar datos de la venta principal
try {
    $stmt_venta = $db->prepare("
        SELECT v.id, v.id_cliente, c.nombre as cliente_nombre, c.apellido as cliente_apellido, v.fecha_venta, v.total
        FROM ventas v
        JOIN clientes c ON v.id_cliente = c.id
        WHERE v.id = :id_venta
    ");
    $stmt_venta->bindParam(':id_venta', $id_venta, PDO::PARAM_INT);
    $stmt_venta->execute();
    $venta = $stmt_venta->fetch();

    if (!$venta) {
        header('Location: consulta.php?message=Venta no encontrada&type=danger');
        exit();
    }

    // Cargar detalles de los productos en esta venta
    $stmt_detalles = $db->prepare("
        SELECT dv.id_producto, p.nombre as producto_nombre, dv.cantidad, dv.precio_unitario, dv.subtotal
        FROM detalle_ventas dv
        JOIN productos p ON dv.id_producto = p.id
        WHERE dv.id_venta = :id_venta
    ");
    $stmt_detalles->bindParam(':id_venta', $id_venta, PDO::PARAM_INT);
    $stmt_detalles->execute();
    $detalles_venta = $stmt_detalles->fetchAll();

} catch (PDOException $e) {
    registrarActividad('Error al cargar detalles de venta', 'Venta ID: ' . $id_venta . ', Error: ' . $e->getMessage(), 'error');
    header('Location: consulta.php?message=Error de base de datos al cargar detalles de venta&type=danger');
    exit();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Venta - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link" href="consulta.php">Ventas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">Detalles de Venta</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <h2 class="mb-4">Detalles de Venta #<?php echo htmlspecialchars($venta['id']); ?></h2>

        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                Información General de la Venta
            </div>
            <div class="card-body">
                <p><strong>Cliente:</strong> <?php echo htmlspecialchars($venta['cliente_nombre'] . ' ' . $venta['cliente_apellido']); ?></p>
                <p><strong>Fecha de Venta:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($venta['fecha_venta']))); ?></p>
                <p><strong>Total de la Venta:</strong> $<span class="fw-bold fs-5"><?php echo htmlspecialchars(number_format($venta['total'], 2)); ?></span></p>
            </div>
        </div>

        <h4 class="mt-4 mb-3">Productos de la Venta</h4>
        <?php if (empty($detalles_venta)): ?>
            <div class="alert alert-info">No se encontraron productos para esta venta.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped border">
                    <thead class="table-primary">
                        <tr>
                            <th>ID Producto</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles_venta as $detalle): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($detalle['id_producto']); ?></td>
                            <td><?php echo htmlspecialchars($detalle['producto_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($detalle['cantidad']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($detalle['precio_unitario'], 2)); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($detalle['subtotal'], 2)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="consulta.php" class="btn btn-secondary"><i class="bi bi-arrow-left-circle"></i> Volver a Ventas</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 