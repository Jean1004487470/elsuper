<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Verificar permisos
verificarAcceso('ver_ventas');

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
    $search_query = " WHERE (c.nombre LIKE :search_nombre OR c.apellido LIKE :search_apellido OR v.id LIKE :search_id)";
    $search_params[':search_nombre'] = '%' . $search . '%';
    $search_params[':search_apellido'] = '%' . $search . '%';
    $search_params[':search_id'] = '%' . $search . '%';
}

// Obtener el total de ventas para la paginación
$stmt_total = $db->prepare("
    SELECT COUNT(*) as total
    FROM ventas v
    JOIN clientes c ON v.id_cliente = c.id
    " . $search_query
);

foreach ($search_params as $key => &$val) {
    $stmt_total->bindParam($key, $val, PDO::PARAM_STR);
}
$stmt_total->execute();
$total_records = $stmt_total->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Obtener ventas con paginación y búsqueda
$stmt = $db->prepare("
    SELECT v.id, c.nombre as cliente_nombre, c.apellido as cliente_apellido, v.fecha_venta, v.total
    FROM ventas v
    JOIN clientes c ON v.id_cliente = c.id
    " . $search_query . "
    ORDER BY v.fecha_venta DESC
    LIMIT :limit OFFSET :offset
");

foreach ($search_params as $key => &$val) {
    $stmt->bindParam($key, $val, PDO::PARAM_STR);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$ventas = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ventas - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link active" href="consulta.php">Ventas</a>
                    </li>
                    <?php if (hasPermission('crear_ventas')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="crear.php">Nueva Venta</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <h2 class="mb-4">Gestión de Ventas</h2>

        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_GET['type']); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="search" placeholder="Buscar por cliente o ID de venta" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
                        <a href="consulta.php" class="btn btn-secondary ms-2"><i class="bi bi-arrow-clockwise"></i> Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($ventas)): ?>
            <div class="alert alert-info">No se encontraron ventas.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped border">
                    <thead class="table-primary">
                        <tr>
                            <th>ID Venta</th>
                            <th>Cliente</th>
                            <th>Fecha Venta</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas as $venta): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($venta['id']); ?></td>
                            <td><?php echo htmlspecialchars($venta['cliente_nombre'] . ' ' . $venta['cliente_apellido']); ?></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($venta['fecha_venta']))); ?></td>
                            <td><?php echo htmlspecialchars(number_format($venta['total'], 2)); ?></td>
                            <td>
                                <a href="detalle.php?id=<?php echo $venta['id']; ?>" class="btn btn-sm btn-info me-1" title="Ver Detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (hasPermission('editar_ventas')): ?>
                                <a href="editar.php?id=<?php echo $venta['id']; ?>" class="btn btn-sm btn-warning me-1" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (hasPermission('eliminar_ventas')): ?>
                                <button type="button" class="btn btn-sm btn-danger" title="Eliminar" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="<?php echo $venta['id']; ?>" data-cliente="<?php echo htmlspecialchars($venta['cliente_nombre'] . ' ' . $venta['cliente_apellido']); ?>">
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
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Eliminación de Venta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    ¿Está seguro de que desea eliminar la venta <strong id="ventaId"></strong> del cliente <strong id="clienteNombre"></strong>?
                    Esta acción es irreversible y afectará el inventario asociado.
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
            var ventaId = button.getAttribute('data-id');
            var clienteNombre = button.getAttribute('data-cliente');

            var modalBodyVentaId = confirmDeleteModal.querySelector('#ventaId');
            var modalBodyClienteNombre = confirmDeleteModal.querySelector('#clienteNombre');
            var confirmDeleteButton = confirmDeleteModal.querySelector('#confirmDeleteButton');

            modalBodyVentaId.textContent = ventaId;
            modalBodyClienteNombre.textContent = clienteNombre;
            confirmDeleteButton.href = 'eliminar.php?id=' + ventaId;
        });
    </script>
</body>
</html> 