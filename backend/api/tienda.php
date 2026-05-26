<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';

header('Content-Type: application/json; charset=utf-8');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$pdo = require_once __DIR__ . '/../config/database.php';
$action = $_GET['action'] ?? '';

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureBaseData(PDO $pdo): void
{
    $pdo->exec("INSERT IGNORE INTO REGION (id_region, nombre_region) VALUES (1, 'Centro')");
    $pdo->exec("
        INSERT IGNORE INTO SUCURSAL (id_sucursal, nombre, id_region, direccion, es_centro_distribucion)
        VALUES (1, 'Sucursal Online', 1, 'Plataforma en linea Monster Inc.', TRUE)
    ");
    $pdo->exec("INSERT IGNORE INTO ESTADO_ENVIO (id_estado_envio, nombre) VALUES (1, 'Preparando'), (2, 'En camino'), (3, 'Entregado')");

    $products = [
        ['Laptop Lenovo ThinkPad E16', 'PRD-001', 12500.00, 'Computo', 18999.00, 20],
        ['Monitor Samsung 27 pulgadas FHD', 'PRD-002', 2300.00, 'Electronica', 3999.00, 12],
        ['Impresora HP LaserJet Pro M404dn', 'PRD-003', 3100.00, 'Oficina', 5299.00, 30],
        ['Silla ergonomica Herman Miller Sayl', 'PRD-004', 8700.00, 'Mobiliario', 13200.00, 25],
        ['Cafetera Nespresso Vertuo Pop', 'PRD-005', 1450.00, 'Electrodomesticos', 2499.00, 15],
    ];

    $findProductStmt = $pdo->prepare('SELECT id_producto FROM PRODUCTO WHERE sku = ? LIMIT 1');
    $productStmt = $pdo->prepare('INSERT INTO PRODUCTO (nombre, sku, costo_base) VALUES (?, ?, ?)');
    $categoryStmt = $pdo->prepare('INSERT INTO CATEGORIA_PRODUCTO (id_producto, categoria, fecha_inicio) VALUES (?, ?, CURDATE())');
    $priceStmt = $pdo->prepare('INSERT INTO PRECIO_CANAL (id_producto, canal, precio_venta, fecha_vigencia_inicio) VALUES (?, ?, ?, CURDATE())');
    $priceExistsStmt = $pdo->prepare('SELECT COUNT(*) FROM PRECIO_CANAL WHERE id_producto = ? AND canal = ? AND fecha_vigencia_fin IS NULL');
    $categoryExistsStmt = $pdo->prepare('SELECT COUNT(*) FROM CATEGORIA_PRODUCTO WHERE id_producto = ? AND fecha_fin IS NULL');
    $stockStmt = $pdo->prepare('INSERT INTO INVENTARIO (id_sucursal, id_producto, cantidad_disponible) VALUES (1, ?, ?) ON DUPLICATE KEY UPDATE cantidad_disponible = cantidad_disponible');

    foreach ($products as [$name, $sku, $cost, $category, $linePrice, $stock]) {
        $findProductStmt->execute([$sku]);
        $productId = $findProductStmt->fetchColumn();

        if ($productId === false) {
            $productStmt->execute([$name, $sku, $cost]);
            $productId = (int)$pdo->lastInsertId();
        } else {
            $productId = (int)$productId;
            $pdo->prepare('UPDATE PRODUCTO SET nombre = ?, costo_base = ? WHERE id_producto = ?')->execute([$name, $cost, $productId]);
        }

        $categoryExistsStmt->execute([$productId]);
        if ((int)$categoryExistsStmt->fetchColumn() === 0) {
            $categoryStmt->execute([$productId, $category]);
        } else {
            $pdo->prepare('UPDATE CATEGORIA_PRODUCTO SET categoria = ? WHERE id_producto = ? AND fecha_fin IS NULL')->execute([$category, $productId]);
        }

        foreach (['Linea' => $linePrice, 'Fisica' => round($linePrice * 1.05, 2), 'Corporativo' => round($linePrice * 0.90, 2)] as $channel => $price) {
            $priceExistsStmt->execute([$productId, $channel]);
            if ((int)$priceExistsStmt->fetchColumn() === 0) {
                $priceStmt->execute([$productId, $channel, $price]);
            } else {
                $pdo->prepare('UPDATE PRECIO_CANAL SET precio_venta = ? WHERE id_producto = ? AND canal = ? AND fecha_vigencia_fin IS NULL')->execute([$price, $productId, $channel]);
            }
        }

        $stockStmt->execute([$productId, $stock]);
    }
}

function firstAddressForClient(PDO $pdo, int $clientId): int
{
    $stmt = $pdo->prepare('SELECT id_direccion FROM DIRECCION_CLIENTE WHERE id_cliente = ? ORDER BY id_direccion ASC LIMIT 1');
    $stmt->execute([$clientId]);
    $addressId = $stmt->fetchColumn();
    if ($addressId !== false) {
        return (int)$addressId;
    }

    $stmt = $pdo->prepare('INSERT INTO DIRECCION_CLIENTE (id_cliente, direccion, ciudad, estado, codigo_postal) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$clientId, 'Direccion online pendiente de confirmar', 'Monterrey', 'Nuevo Leon', '64000']);
    return (int)$pdo->lastInsertId();
}

function registerOnlineAfterSale(PDO $pdo, int $saleId, int $clientId): void
{
    $pdo->prepare('INSERT INTO FACTURA (id_venta, uso_cfdi, sello_digital) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE uso_cfdi = VALUES(uso_cfdi)')
        ->execute([$saleId, 'G01', 'SEAL-ONLINE-' . $saleId]);

    $addressId = firstAddressForClient($pdo, $clientId);
    $pdo->prepare('INSERT INTO ENVIO (id_venta, id_direccion, id_estado_envio, fecha_estimada_entrega, numero_guia) VALUES (?, ?, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), ?) ON DUPLICATE KEY UPDATE numero_guia = VALUES(numero_guia)')
        ->execute([$saleId, $addressId, 'GUA-WEB-' . str_pad((string)$saleId, 6, '0', STR_PAD_LEFT)]);

    $employeeId = (int)($pdo->query('SELECT id_empleado FROM EMPLEADO ORDER BY id_empleado ASC LIMIT 1')->fetchColumn() ?: 1);
    $pdo->prepare('INSERT INTO BITACORA (id_empleado, accion) VALUES (?, ?)')->execute([$employeeId, 'Pedido Online registrado: venta #' . $saleId]);
}

function normalizeSaleStatus(string $status): string
{
    return match ($status) {
        'Pendiente' => 'Pendiente',
        'Cancelado', 'Cancelada' => 'Cancelada',
        default => 'Pagada',
    };
}

function getCurrentPrice(PDO $pdo, int $productId, string $channel = 'Linea'): float
{
    $stmt = $pdo->prepare("
        SELECT precio_venta
        FROM PRECIO_CANAL
        WHERE id_producto = ?
          AND canal = ?
          AND fecha_vigencia_inicio <= CURDATE()
          AND (fecha_vigencia_fin IS NULL OR fecha_vigencia_fin >= CURDATE())
        ORDER BY fecha_vigencia_inicio DESC, id_precio DESC
        LIMIT 1
    ");
    $stmt->execute([$productId, $channel]);
    $price = $stmt->fetchColumn();

    if ($price === false) {
        throw new RuntimeException('El producto no tiene precio vigente para el canal ' . $channel . '.');
    }

    return (float)$price;
}

function findOrCreateClient(PDO $pdo, string $name, string $email): int
{
    $stmt = $pdo->prepare('SELECT id_cliente FROM CLIENTE WHERE correo = ? LIMIT 1');
    $stmt->execute([$email]);
    $clientId = $stmt->fetchColumn();

    if ($clientId !== false) {
        return (int)$clientId;
    }

    $stmt = $pdo->prepare("
        INSERT INTO CLIENTE (tipo_cliente, nombre_razon_social, correo)
        VALUES ('Individual', ?, ?)
    ");
    $stmt->execute([$name, $email]);

    return (int)$pdo->lastInsertId();
}

try {
    ensureBaseData($pdo);

    switch ($action) {
        case 'get_products':
            $stmt = $pdo->query("
                SELECT
                    p.id_producto AS id,
                    p.nombre AS name,
                    p.sku,
                    COALESCE(pc.precio_venta, p.costo_base) AS price,
                    cp.categoria AS cat,
                    COALESCE(i.cantidad_disponible, 0) AS stock
                FROM PRODUCTO p
                LEFT JOIN INVENTARIO i
                    ON i.id_producto = p.id_producto AND i.id_sucursal = 1
                LEFT JOIN CATEGORIA_PRODUCTO cp
                    ON cp.id_producto = p.id_producto AND cp.fecha_fin IS NULL
                LEFT JOIN PRECIO_CANAL pc
                    ON pc.id_producto = p.id_producto
                    AND pc.canal = 'Linea'
                    AND pc.fecha_vigencia_inicio <= CURDATE()
                    AND (pc.fecha_vigencia_fin IS NULL OR pc.fecha_vigencia_fin >= CURDATE())
                ORDER BY p.id_producto ASC
            ");

            $products = array_map(static function (array $product): array {
                return [
                    'id' => (int)$product['id'],
                    'name' => $product['name'],
                    'sku' => $product['sku'],
                    'brand' => 'Monster Inc.',
                    'price' => (float)$product['price'],
                    'was' => null,
                    'icon' => 'MI',
                    'cat' => $product['cat'] ?? 'General',
                    'tag' => 'Linea',
                    'tagClass' => 'tag-dark',
                    'stock' => (int)$product['stock'],
                ];
            }, $stmt->fetchAll());

            echo json_encode($products, JSON_UNESCAPED_UNICODE);
            break;

        case 'create_sale':
            if ($requestMethod !== 'POST') {
                jsonResponse(['error' => 'Metodo no permitido. Debe ser POST.'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $items = $input['items'] ?? [];

            if (!is_array($input) || empty($items)) {
                jsonResponse(['error' => 'El carrito esta vacio o los datos son invalidos.'], 400);
            }

            $clientName = trim($input['cliente_nombre'] ?? 'Cliente anonimo');
            $clientEmail = trim($input['cliente_email'] ?? 'anonimo@monsters.com');

            $pdo->beginTransaction();

            $clientId = findOrCreateClient($pdo, $clientName, $clientEmail);
            $subtotal = 0.0;
            $validatedItems = [];

            foreach ($items as $item) {
                $productId = (int)($item['id'] ?? 0);
                $quantity = (int)($item['qty'] ?? 0);

                if ($productId <= 0 || $quantity <= 0) {
                    throw new RuntimeException('El carrito contiene productos invalidos.');
                }

                $stmt = $pdo->prepare("
                    SELECT p.nombre, i.cantidad_disponible
                    FROM PRODUCTO p
                    INNER JOIN INVENTARIO i
                        ON i.id_producto = p.id_producto AND i.id_sucursal = 1
                    WHERE p.id_producto = ?
                    FOR UPDATE
                ");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();

                if (!$product) {
                    throw new RuntimeException('El producto solicitado no existe en inventario.');
                }

                if ((int)$product['cantidad_disponible'] < $quantity) {
                    throw new RuntimeException("Stock insuficiente para {$product['nombre']}.");
                }

                $price = getCurrentPrice($pdo, $productId, 'Linea');
                $subtotal += $price * $quantity;
                $validatedItems[] = [$productId, $quantity, $price];
            }

            $saleStmt = $pdo->prepare("
                INSERT INTO VENTA (id_cliente, id_empleado, id_sucursal, canal_venta, estado_venta, subtotal, total)
                VALUES (?, NULL, 1, 'Linea', 'Pagada', ?, ?)
            ");
            $saleStmt->execute([$clientId, $subtotal, $subtotal]);
            $saleId = (int)$pdo->lastInsertId();

            $detailStmt = $pdo->prepare("
                INSERT INTO DETALLE_VENTA (id_venta, id_producto, id_promocion, cantidad, precio_unitario_historico)
                VALUES (?, ?, NULL, ?, ?)
            ");
            $stockStmt = $pdo->prepare("
                UPDATE INVENTARIO
                SET cantidad_disponible = cantidad_disponible - ?
                WHERE id_sucursal = 1 AND id_producto = ?
            ");

            foreach ($validatedItems as [$productId, $quantity, $price]) {
                $detailStmt->execute([$saleId, $productId, $quantity, $price]);
                $stockStmt->execute([$quantity, $productId]);
            }

            registerOnlineAfterSale($pdo, $saleId, $clientId);

            $pdo->commit();

            jsonResponse([
                'success' => true,
                'message' => 'Compra registrada correctamente.',
                'venta_id' => $saleId,
                'total' => $subtotal,
            ]);

        case 'get_sales':
            $stmt = $pdo->query("
                SELECT
                    v.id_venta AS id,
                    v.fecha_hora AS fecha,
                    c.nombre_razon_social AS cliente_nombre,
                    c.correo AS cliente_email,
                    v.total,
                    v.estado_venta AS estado,
                    v.canal_venta
                FROM VENTA v
                INNER JOIN CLIENTE c ON c.id_cliente = v.id_cliente
                ORDER BY v.fecha_hora DESC
            ");
            $sales = $stmt->fetchAll();

            $detailStmt = $pdo->prepare("
                SELECT
                    dv.cantidad,
                    dv.precio_unitario_historico AS precio_unitario,
                    p.nombre AS name,
                    p.sku,
                    'MI' AS icon,
                    'Monster Inc.' AS brand
                FROM DETALLE_VENTA dv
                INNER JOIN PRODUCTO p ON p.id_producto = dv.id_producto
                WHERE dv.id_venta = ?
            ");

            foreach ($sales as &$sale) {
                $sale['id'] = (int)$sale['id'];
                $sale['total'] = (float)$sale['total'];
                $sale['metodo_pago'] = $sale['canal_venta'];
                $sale['estado'] = $sale['estado'] === 'Pagada' ? 'Completado' : $sale['estado'];
                $detailStmt->execute([$sale['id']]);
                $sale['items'] = $detailStmt->fetchAll();
            }

            echo json_encode($sales, JSON_UNESCAPED_UNICODE);
            break;

        case 'update_sale':
            if ($requestMethod !== 'POST') {
                jsonResponse(['error' => 'Metodo no permitido.'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $saleId = (int)($input['id'] ?? 0);
            $clientName = trim($input['cliente_nombre'] ?? '');
            $status = normalizeSaleStatus(trim($input['estado'] ?? 'Pagada'));

            if ($saleId <= 0) {
                jsonResponse(['error' => 'ID de venta requerido.'], 400);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id_cliente FROM VENTA WHERE id_venta = ?');
            $stmt->execute([$saleId]);
            $clientId = $stmt->fetchColumn();

            if ($clientId === false) {
                throw new RuntimeException('La venta no existe.');
            }

            if ($clientName !== '') {
                $stmt = $pdo->prepare('UPDATE CLIENTE SET nombre_razon_social = ? WHERE id_cliente = ?');
                $stmt->execute([$clientName, $clientId]);
            }

            $stmt = $pdo->prepare('UPDATE VENTA SET estado_venta = ? WHERE id_venta = ?');
            $stmt->execute([$status, $saleId]);

            $pdo->commit();
            jsonResponse(['success' => true, 'message' => 'Venta actualizada correctamente.']);

        case 'delete_sale':
            if ($requestMethod !== 'POST') {
                jsonResponse(['error' => 'Metodo no permitido.'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $saleId = (int)($input['id'] ?? 0);

            if ($saleId <= 0) {
                jsonResponse(['error' => 'ID de venta requerido.'], 400);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id_producto, cantidad FROM DETALLE_VENTA WHERE id_venta = ?');
            $stmt->execute([$saleId]);
            $items = $stmt->fetchAll();

            $restoreStmt = $pdo->prepare("
                UPDATE INVENTARIO
                SET cantidad_disponible = cantidad_disponible + ?
                WHERE id_sucursal = 1 AND id_producto = ?
            ");

            foreach ($items as $item) {
                $restoreStmt->execute([(int)$item['cantidad'], (int)$item['id_producto']]);
            }

            $pdo->prepare('DELETE FROM ENVIO WHERE id_venta = ?')->execute([$saleId]);
            $pdo->prepare('DELETE FROM FACTURA WHERE id_venta = ?')->execute([$saleId]);
            $pdo->prepare('DELETE FROM DETALLE_VENTA WHERE id_venta = ?')->execute([$saleId]);
            $pdo->prepare('DELETE FROM VENTA WHERE id_venta = ?')->execute([$saleId]);

            $pdo->commit();
            jsonResponse(['success' => true, 'message' => 'Venta cancelada e inventario restaurado.']);

        default:
            jsonResponse(['error' => 'Accion no reconocida.'], 404);
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse([
        'success' => false,
        'error' => $exception->getMessage(),
    ], 500);
}
