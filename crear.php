<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Verificar permisos
verificarAcceso('crear_ventas');

$db = getDBConnection();

$message = '';
$message_type = '';

// Obtener el ID del empleado logueado
$id_empleado_logueado = null;
if (isset($_SESSION['user_id'])) {
    $stmt_empleado = $db->prepare("SELECT id FROM empleados WHERE id_usuario = :user_id");
    $stmt_empleado->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt_empleado->execute();
    $id_empleado_logueado = $stmt_empleado->fetchColumn();
}

if (!$id_empleado_logueado) {
    // Si no se encuentra el ID del empleado, es un error crítico para registrar una venta
    registrarActividad('Error al crear venta', 'No se pudo obtener id_empleado para user_id: ' . ($_SESSION['user_id'] ?? 'N/A'), 'error');
    header('Location: consulta.php?message=Error: No se pudo identificar al empleado que registra la venta.&type=danger');
    exit();
}

// Obtener clientes para el dropdown
$clientes = $db->query("SELECT id, nombre, apellido FROM clientes ORDER BY nombre ASC")->fetchAll();

// Obtener productos para el selector de productos
$productos_disponibles = $db->query("SELECT id, nombre, precio, stock FROM productos WHERE stock > 0 ORDER BY nombre ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cliente = filter_var($_POST['id_cliente'] ?? '', FILTER_VALIDATE_INT);
    $items = json_decode($_POST['items_json'] ?? '[]', true);

    if (!$id_cliente) {
        $message = 'Por favor, seleccione un cliente.';
        $message_type = 'danger';
    } elseif (empty($items)) {
        $message = 'Debe añadir al menos un producto a la venta.';
        $message_type = 'danger';
    } else {
        $db->beginTransaction();
        try {
            $total_venta = 0;
            $detalles_para_insertar = [];

            foreach ($items as $item) {
                $producto_id = filter_var($item['producto_id'] ?? '', FILTER_VALIDATE_INT);
                $cantidad = filter_var($item['cantidad'] ?? '', FILTER_VALIDATE_INT);
                $precio_unitario = filter_var($item['precio_unitario'] ?? '', FILTER_VALIDATE_FLOAT);

                if (!$producto_id || $cantidad === false || $cantidad <= 0 || $precio_unitario === false || $precio_unitario <= 0) {
                    throw new Exception('Datos de producto inválidos en la venta.');
                }

                // Verificar stock disponible
                $stmt_stock = $db->prepare("SELECT stock FROM productos WHERE id = :producto_id");
                $stmt_stock->bindParam(':producto_id', $producto_id, PDO::PARAM_INT);
                $stmt_stock->execute();
                $current_stock = $stmt_stock->fetchColumn();

                if ($current_stock < $cantidad) {
                    throw new Exception('Stock insuficiente para el producto ID: ' . $producto_id . '. Stock disponible: ' . $current_stock);
                }

                $subtotal_item = $cantidad * $precio_unitario;
                $total_venta += $subtotal_item;
                $detalles_para_insertar[] = [
                    'producto_id' => $producto_id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio_unitario,
                    'subtotal' => $subtotal_item
                ];
            }

            // Insertar la venta principal, incluyendo id_empleado
            $stmt_venta = $db->prepare("
                INSERT INTO ventas (id_cliente, id_empleado, fecha_venta, total)
                VALUES (:id_cliente, :id_empleado, NOW(), :total)
            ");
            $stmt_venta->bindParam(':id_cliente', $id_cliente, PDO::PARAM_INT);
            $stmt_venta->bindParam(':id_empleado', $id_empleado_logueado, PDO::PARAM_INT);
            $stmt_venta->bindParam(':total', $total_venta, PDO::PARAM_STR); // Usar STR para FLOAT
            $stmt_venta->execute();
            $venta_id = $db->lastInsertId();

            // Insertar detalles de la venta y actualizar stock
            $stmt_detalle = $db->prepare("
                INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario, subtotal)
                VALUES (:id_venta, :id_producto, :cantidad, :precio_unitario, :subtotal)
            ");
            $stmt_update_stock = $db->prepare("UPDATE productos SET stock = stock - :cantidad WHERE id = :id_producto");

            foreach ($detalles_para_insertar as $detalle) {
                $stmt_detalle->bindParam(':id_venta', $venta_id, PDO::PARAM_INT);
                $stmt_detalle->bindParam(':id_producto', $detalle['producto_id'], PDO::PARAM_INT);
                $stmt_detalle->bindParam(':cantidad', $detalle['cantidad'], PDO::PARAM_INT);
                $stmt_detalle->bindParam(':precio_unitario', $detalle['precio_unitario'], PDO::PARAM_STR);
                $stmt_detalle->bindParam(':subtotal', $detalle['subtotal'], PDO::PARAM_STR);
                $stmt_detalle->execute();

                $stmt_update_stock->bindParam(':cantidad', $detalle['cantidad'], PDO::PARAM_INT);
                $stmt_update_stock->bindParam(':id_producto', $detalle['producto_id'], PDO::PARAM_INT);
                $stmt_update_stock->execute();
            }

            $db->commit();
            $message = 'Venta registrada exitosamente con ID: ' . $venta_id . '.';
            $message_type = 'success';
            registrarActividad('Venta creada', 'Venta ID: ' . $venta_id . ', Cliente ID: ' . $id_cliente . ', Empleado ID: ' . $id_empleado_logueado . ', Total: ' . $total_venta);
            // Redirigir para limpiar el POST y mostrar el mensaje
            header('Location: consulta.php?message=' . urlencode($message) . '&type=' . urlencode($message_type));
            exit();

        } catch (Exception $e) {
            $db->rollBack();
            $message = 'Error al registrar la venta: ' . $e->getMessage();
            $message_type = 'danger';
            registrarActividad('Error al crear venta', 'Error: ' . $e->getMessage(), 'error');
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Venta - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link active" href="crear.php">Nueva Venta</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <h2 class="mb-4">Registrar Nueva Venta</h2>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form id="ventaForm" action="crear.php" method="POST">
                    <div class="mb-3">
                        <label for="id_cliente" class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_cliente" name="id_cliente" required>
                            <option value="">Seleccione un cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo htmlspecialchars($cliente['id']); ?>">
                                    <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <h4 class="mt-4">Detalles de la Venta</h4>
                    <div id="productos-container" class="mb-3">
                        <!-- Los productos se añadirán aquí dinámicamente -->
                    </div>

                    <div class="row mb-3 align-items-end">
                        <div class="col-md-6">
                            <label for="producto_selector" class="form-label">Añadir Producto</label>
                            <select class="form-select" id="producto_selector">
                                <option value="">Seleccione un producto</option>
                                <?php foreach ($productos_disponibles as $prod): ?>
                                    <option 
                                        value="<?php echo htmlspecialchars($prod['id']); ?>"
                                        data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                                        data-precio="<?php echo htmlspecialchars($prod['precio']); ?>"
                                        data-stock="<?php echo htmlspecialchars($prod['stock']); ?>"
                                    >
                                        <?php echo htmlspecialchars($prod['nombre'] . ' (Stock: ' . $prod['stock'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="cantidad_producto" class="form-label">Cantidad</label>
                            <input type="number" class="form-control" id="cantidad_producto" value="1" min="1">
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-info w-100" id="add_product_btn"><i class="bi bi-plus-circle"></i> Añadir</button>
                        </div>
                    </div>

                    <div class="mb-3 text-end">
                        <h4>Total: $<span id="total_venta">0.00</span></h4>
                        <input type="hidden" name="items_json" id="items_json">
                    </div>

                    <button type="submit" class="btn btn-success"><i class="bi bi-currency-dollar"></i> Registrar Venta</button>
                    <a href="consulta.php" class="btn btn-secondary ms-2"><i class="bi bi-arrow-left-circle"></i> Volver</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedProducts = {}; // {producto_id: {nombre, precio, cantidad, stock_original}}

        document.getElementById('add_product_btn').addEventListener('click', function() {
            const selector = document.getElementById('producto_selector');
            const cantidadInput = document.getElementById('cantidad_producto');
            
            const productId = selector.value;
            const cantidad = parseInt(cantidadInput.value);

            if (!productId || cantidad <= 0) {
                alert('Por favor, seleccione un producto y especifique una cantidad válida.');
                return;
            }

            const selectedOption = selector.options[selector.selectedIndex];
            const productName = selectedOption.getAttribute('data-nombre');
            const productPrice = parseFloat(selectedOption.getAttribute('data-precio'));
            const productStock = parseInt(selectedOption.getAttribute('data-stock'));

            if (cantidad > productStock) {
                alert(`No hay suficiente stock para ${productName}. Disponible: ${productStock}.`);
                return;
            }

            if (selectedProducts[productId]) {
                // Si el producto ya está en la lista, actualizar cantidad
                const newCantidad = selectedProducts[productId].cantidad + cantidad;
                if (newCantidad > productStock) {
                    alert(`No se puede añadir ${cantidad} unidades más de ${productName}. Excede el stock disponible. Total deseado: ${newCantidad}, Stock: ${productStock}.`);
                    return;
                }
                selectedProducts[productId].cantidad = newCantidad;
            } else {
                // Añadir nuevo producto
                selectedProducts[productId] = {
                    nombre: productName,
                    precio_unitario: productPrice,
                    cantidad: cantidad,
                    stock_original: productStock // Guardar stock original para referencia
                };
            }

            renderSelectedProducts();
            updateTotal();
            selector.value = ''; // Limpiar selección
            cantidadInput.value = 1; // Resetear cantidad
        });

        function renderSelectedProducts() {
            const container = document.getElementById('productos-container');
            container.innerHTML = '';

            for (const productId in selectedProducts) {
                const product = selectedProducts[productId];
                const itemTotal = (product.cantidad * product.precio_unitario).toFixed(2);
                const productDiv = document.createElement('div');
                productDiv.classList.add('d-flex', 'align-items-center', 'mb-2', 'p-2', 'border', 'rounded');
                productDiv.innerHTML = `
                    <div class="flex-grow-1">
                        <strong>${product.nombre}</strong> <br/>
                        Precio Unitario: $${product.precio_unitario.toFixed(2)} | Cantidad: ${product.cantidad} 
                    </div>
                    <div>
                        Total Item: $${itemTotal}
                        <button type="button" class="btn btn-sm btn-danger ms-2" data-product-id="${productId}">X</button>
                    </div>
                `;
                container.appendChild(productDiv);
            }

            // Añadir listener para eliminar producto
            container.querySelectorAll('button.btn-danger').forEach(button => {
                button.addEventListener('click', function() {
                    const productIdToDelete = this.getAttribute('data-product-id');
                    delete selectedProducts[productIdToDelete];
                    renderSelectedProducts();
                    updateTotal();
                });
            });
        }

        function updateTotal() {
            let total = 0;
            for (const productId in selectedProducts) {
                const product = selectedProducts[productId];
                total += product.cantidad * product.precio_unitario;
            }
            document.getElementById('total_venta').textContent = total.toFixed(2);
            document.getElementById('items_json').value = JSON.stringify(Object.keys(selectedProducts).map(id => ({
                producto_id: id,
                cantidad: selectedProducts[id].cantidad,
                precio_unitario: selectedProducts[id].precio_unitario
            })));
        }

        // Llamar para asegurar que el total se actualiza si hay datos preexistentes (aunque no los habrá en crear.php)
        updateTotal();
    </script>
</body>
</html>