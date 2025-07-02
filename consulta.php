<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Verificar permisos
verificarAcceso('ver_inventario');

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
    $search_query = " WHERE (p.nombre LIKE :search OR e.nombre LIKE :search_empleado OR e.apellido LIKE :search_apellido)";
    $search_params[':search'] = '%' . $search . '%';
    $search_params[':search_empleado'] = '%' . $search . '%';
    $search_params[':search_apellido'] = '%' . $search . '%';
}

// Obtener el total de movimientos para la paginación
$stmt_total = $db->prepare("
    SELECT COUNT(*) as total
    FROM inventario i
    JOIN productos p ON i.id_producto = p.id
    JOIN empleados e ON i.id_empleado_responsable = e.id
    " . $search_query
);

foreach ($search_params as $key => &$val) {
    $stmt_total->bindParam($key, $val, PDO::PARAM_STR);
}
$stmt_total->execute();
$total_records = $stmt_total->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Obtener movimientos de inventario con paginación y búsqueda
$stmt = $db->prepare("
    SELECT i.id, p.nombre as producto_nombre, i.tipo_movimiento, i.cantidad, i.fecha_movimiento,
           e.nombre as empleado_nombre, e.apellido as empleado_apellido
    FROM inventario i
    JOIN productos p ON i.id_producto = p.id
    JOIN empleados e ON i.id_empleado_responsable = e.id
    " . $search_query . "
    ORDER BY i.fecha_movimiento DESC
    LIMIT :limit OFFSET :offset
");

foreach ($search_params as $key => &$val) {
    $stmt->bindParam($key, $val, PDO::PARAM_STR);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$movimientos = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Inventario - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link active" href="consulta.php">Inventario</a>
                    </li>
                    <?php if (hasPermission('registrar_entrada_inventario') || hasPermission('registrar_salida_inventario')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Movimiento
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <?php if (hasPermission('registrar_entrada_inventario')): ?>
                            <li><a class="dropdown-item" href="crear_entrada.php">Registrar Entrada</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('registrar_salida_inventario')): ?>
                            <li><a class="dropdown-item" href="crear_salida.php">Registrar Salida</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <h2 class="mb-4">Historial de Movimientos de Inventario</h2>

        <div id="react-inventario"></div>

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
                        <input type="text" class="form-control" name="search" placeholder="Buscar por producto o empleado" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
                        <a href="consulta.php" class="btn btn-secondary ms-2"><i class="bi bi-arrow-clockwise"></i> Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($movimientos)): ?>
            <div class="alert alert-info">No se encontraron movimientos de inventario.</div>
        <?php else: ?>
            <!-- Tabla PHP de inventario y paginación ocultadas para mostrar solo la tabla React -->
            <!--
            <div class="table-responsive">
                <table class="table table-hover table-striped border">
                    <thead class="table-primary">
                        <tr>
                            <th>ID Movimiento</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Fecha</th>
                            <th>Responsable</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (
                            $movimientos as $movimiento): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($movimiento['id']); ?></td>
                            <td><?php echo htmlspecialchars($movimiento['producto_nombre']); ?></td>
                            <td>
                                <?php 
                                    $badge_class = ($movimiento['tipo_movimiento'] == 'ENTRADA') ? 'bg-success' : 'bg-danger';
                                    echo '<span class="badge ' . $badge_class . '">' . htmlspecialchars($movimiento['tipo_movimiento']) . '</span>';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($movimiento['cantidad']); ?></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($movimiento['fecha_movimiento']))); ?></td>
                            <td><?php echo htmlspecialchars($movimiento['empleado_nombre'] . ' ' . $movimiento['empleado_apellido']); ?></td>
                            <td>
                                <?php if (hasPermission('editar_movimientos_inventario')): ?>
                                <a href="editar.php?id=<?php echo $movimiento['id']; ?>" class="btn btn-sm btn-warning me-1" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (hasPermission('eliminar_movimientos_inventario')): ?>
                                <button type="button" class="btn btn-sm btn-danger" title="Eliminar" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-id="<?php echo $movimiento['id']; ?>" data-producto="<?php echo htmlspecialchars($movimiento['producto_nombre']); ?>" data-cantidad="<?php echo htmlspecialchars($movimiento['cantidad']); ?>" data-tipo="<?php echo htmlspecialchars($movimiento['tipo_movimiento']); ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

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
            -->
        <?php endif; ?>
    </div>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Eliminación de Movimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    ¿Está seguro de que desea eliminar el movimiento ID <strong id="movimientoId"></strong>?
                    Este movimiento de tipo <strong id="movimientoTipo"></strong> de <strong id="movimientoCantidad"></strong> unidades del producto <strong id="movimientoProducto"></strong> será revertido en el stock.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="confirmDeleteButton" class="btn btn-danger">Eliminar</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/react@18/umd/react.development.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js" crossorigin></script>
    <script>
    function InventarioTable() {
      const [movimientos, setMovimientos] = React.useState([]);
      const [loading, setLoading] = React.useState(true);

      React.useEffect(() => {
        fetch('../api/inventario.php')
          .then(res => res.json())
          .then(data => {
            setMovimientos(data);
            setLoading(false);
          });
      }, []);

      if (loading) return React.createElement('div', null, 'Cargando...');

      return React.createElement('table', { className: 'table table-hover table-striped border' },
        React.createElement('thead', { className: 'table-primary' },
          React.createElement('tr', null,
            React.createElement('th', null, 'ID Movimiento'),
            React.createElement('th', null, 'Producto'),
            React.createElement('th', null, 'Tipo'),
            React.createElement('th', null, 'Cantidad'),
            React.createElement('th', null, 'Fecha'),
            React.createElement('th', null, 'Responsable')
          )
        ),
        React.createElement('tbody', null,
          movimientos.map(m =>
            React.createElement('tr', { key: m.id },
              React.createElement('td', null, m.id),
              React.createElement('td', null, m.producto_nombre),
              React.createElement('td', null, m.tipo_movimiento),
              React.createElement('td', null, m.cantidad),
              React.createElement('td', null, m.fecha_movimiento),
              React.createElement('td', null, m.empleado_nombre + ' ' + m.empleado_apellido)
            )
          )
        )
      );
    }

    const root = ReactDOM.createRoot(document.getElementById('react-inventario'));
    root.render(React.createElement(InventarioTable));
    </script>
</body>
</html>