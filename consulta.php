<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Verificar permisos
verificarAcceso('ver_productos');

$db = getDBConnection();

// Paginación
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Búsqueda
$search = sanitizeInput($_GET['search'] ?? '');
$search_query = '';
$search_params = [];

if (!empty($search)) {
    $search_query = " WHERE (nombre LIKE :search OR descripcion LIKE :search)";
    $search_params[':search'] = '%' . $search . '%';
}

// Obtener el total de productos para la paginación
$stmt_total = $db->prepare("SELECT COUNT(*) as total FROM productos" . $search_query);
$stmt_total->execute($search_params);
$total_records = $stmt_total->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Obtener productos con paginación y búsqueda
$stmt = $db->prepare("
    SELECT id, nombre, descripcion, precio, stock, fecha_registro
    FROM productos
    " . $search_query . "
    ORDER BY nombre ASC
    LIMIT :limit OFFSET :offset
");

foreach ($search_params as $key => &$val) {
    $stmt->bindParam($key, $val, PDO::PARAM_STR);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$productos = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link active" href="consulta.php">Productos</a>
                    </li>
                    <?php if (hasPermission('crear_productos')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="crear.php">Nuevo Producto</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <h2 class="mb-4">Gestión de Productos</h2>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="search" placeholder="Buscar por nombre o descripción" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
                        <a href="consulta.php" class="btn btn-secondary ms-2"><i class="bi bi-arrow-clockwise"></i> Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($productos)): ?>
            <div class="alert alert-info">No se encontraron productos.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped border">
                    <thead class="table-primary">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Fecha Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($producto['id']); ?></td>
                            <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($producto['descripcion'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(number_format($producto['precio'], 2)); ?></td>
                            <td><?php echo htmlspecialchars($producto['stock']); ?></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($producto['fecha_registro']))); ?></td>
                            <td>
                                <?php if (hasPermission('editar_productos')): ?>
                                <a href="editar.php?id=<?php echo $producto['id']; ?>" class="btn btn-sm btn-warning me-1" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (hasPermission('eliminar_productos')): ?>
                                <button type="button" class="btn btn-sm btn-danger" title="Eliminar" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="<?php echo $producto['id']; ?>" data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <nav aria-label="Page navigation example">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo htmlspecialchars($search); ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo htmlspecialchars($search); ?>"><?php echo $i; ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo htmlspecialchars($search); ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    ¿Está seguro de que desea eliminar el producto <strong id="productoNombre"></strong>?
                    Esta acción es irreversible y podría afectar registros de inventario o ventas asociadas.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="confirmDeleteButton" class="btn btn-danger">Eliminar</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var confirmDeleteModal = document.getElementById('confirmDeleteModal');
        confirmDeleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var productoId = button.getAttribute('data-id');
            var productoNombre = button.getAttribute('data-nombre');

            var modalBodyProductoNombre = confirmDeleteModal.querySelector('#productoNombre');
            var confirmDeleteButton = confirmDeleteModal.querySelector('#confirmDeleteButton');

            modalBodyProductoNombre.textContent = productoNombre;
            confirmDeleteButton.href = 'eliminar.php?id=' + productoId;
        });
    </script>
</body>
</html> 