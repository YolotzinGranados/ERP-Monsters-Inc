<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';

header('Content-Type: application/json; charset=utf-8');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$pdo = require_once __DIR__ . '/../config/database.php';
$action = $_GET['action'] ?? '';

function response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function input(): array
{
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

require_once __DIR__ . '/../helpers/audit.php';

function actorId(array $data): int
{
    return max(1, (int)($data['empleado'] ?? 1));
}

function ensureRole(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare('SELECT id_rol FROM ROL WHERE nombre_rol = ? LIMIT 1');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int)$id;

    $stmt = $pdo->prepare('INSERT INTO ROL (nombre_rol) VALUES (?)');
    $stmt->execute([$name]);
    return (int)$pdo->lastInsertId();
}

function ensureEmployee(PDO $pdo, string $name, string $email, string $password, int $roleId, ?int $branchId, ?int $regionId): void
{
    $stmt = $pdo->prepare('SELECT id_empleado FROM EMPLEADO WHERE correo = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() !== false) return;

    $stmt = $pdo->prepare('INSERT INTO EMPLEADO (nombre, correo, `contraseña`, id_rol, id_sucursal, id_region, activo) VALUES (?, ?, ?, ?, ?, ?, TRUE)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $roleId, $branchId, $regionId]);
}

function ensureRolePermissions(PDO $pdo, int $roleId, array $permissions): void
{
    $findPermission = $pdo->prepare('SELECT id_permiso FROM PERMISO WHERE nombre_permiso = ? LIMIT 1');
    $insertPermission = $pdo->prepare('INSERT INTO PERMISO (nombre_permiso) VALUES (?)');
    $linkPermission = $pdo->prepare('INSERT IGNORE INTO ROL_PERMISO (id_rol, id_permiso) VALUES (?, ?)');

    foreach ($permissions as $permission) {
        $findPermission->execute([$permission]);
        $permissionId = $findPermission->fetchColumn();
        if ($permissionId === false) {
            $insertPermission->execute([$permission]);
            $permissionId = (int)$pdo->lastInsertId();
        }
        $linkPermission->execute([$roleId, (int)$permissionId]);
    }
}

function ensureCatalogs(PDO $pdo): void
{
    $pdo->exec("INSERT IGNORE INTO REGION (id_region, nombre_region) VALUES (1, 'Centro'), (2, 'Norte'), (3, 'Corporativo')");
    $pdo->exec("INSERT IGNORE INTO SUCURSAL (id_sucursal, nombre, id_region, direccion, es_centro_distribucion) VALUES
        (1, 'Centro de Distribucion Online', 1, 'Almacen web Monster Inc.', TRUE),
        (2, 'Sucursal Norte', 2, 'Punto de venta fisico norte', FALSE),
        (3, 'Atencion Corporativa', 3, 'Oficina de clientes corporativos', FALSE)");
    $pdo->exec("INSERT INTO ESTADO_ENVIO (id_estado_envio, nombre) VALUES (1, 'Pendiente'), (2, 'En Ruta'), (3, 'Entregado'), (4, 'Fallido')
        ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS CLIENTE_CANAL_GESTION (
        id_cliente INT PRIMARY KEY,
        canal ENUM('Linea','Fisica','Corporativo') NOT NULL,
        id_sucursal INT NOT NULL,
        fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_cliente) REFERENCES CLIENTE(id_cliente),
        FOREIGN KEY (id_sucursal) REFERENCES SUCURSAL(id_sucursal)
    )");
    $pdo->exec("INSERT IGNORE INTO CLIENTE_CANAL_GESTION (id_cliente, canal, id_sucursal)
        SELECT c.id_cliente,
            COALESCE(
                (SELECT v.canal_venta FROM VENTA v WHERE v.id_cliente = c.id_cliente ORDER BY v.fecha_hora DESC, v.id_venta DESC LIMIT 1),
                CASE WHEN c.tipo_cliente = 'Corporativo' THEN 'Corporativo' ELSE 'Linea' END
            ) AS canal,
            CASE
                WHEN COALESCE((SELECT v.canal_venta FROM VENTA v WHERE v.id_cliente = c.id_cliente ORDER BY v.fecha_hora DESC, v.id_venta DESC LIMIT 1), CASE WHEN c.tipo_cliente = 'Corporativo' THEN 'Corporativo' ELSE 'Linea' END) = 'Corporativo' THEN 3
                WHEN COALESCE((SELECT v.canal_venta FROM VENTA v WHERE v.id_cliente = c.id_cliente ORDER BY v.fecha_hora DESC, v.id_venta DESC LIMIT 1), 'Linea') = 'Fisica' THEN 2
                ELSE 1
            END AS id_sucursal
        FROM CLIENTE c");

    $admin = ensureRole($pdo, 'Administrador');
    $manager = ensureRole($pdo, 'Gerente de sucursal');
    $seller = ensureRole($pdo, 'Vendedor');
    $warehouse = ensureRole($pdo, 'Almacenista');
    $accountant = ensureRole($pdo, 'Contador');

    $allPermissions = ['Ver tablero', 'Procesar ventas', 'Gestionar clientes', 'Gestionar inventario', 'Emitir CFDI', 'Gestionar envios', 'Gestionar accesos', 'Consultar bitacora', 'Generar cortes'];
    ensureRolePermissions($pdo, $admin, $allPermissions);
    ensureRolePermissions($pdo, $manager, ['Ver tablero', 'Procesar ventas', 'Gestionar clientes', 'Gestionar inventario', 'Emitir CFDI', 'Gestionar envios', 'Consultar bitacora', 'Generar cortes']);
    ensureRolePermissions($pdo, $seller, ['Ver tablero', 'Procesar ventas', 'Gestionar clientes']);
    ensureRolePermissions($pdo, $warehouse, ['Ver tablero', 'Gestionar inventario', 'Gestionar envios']);
    ensureRolePermissions($pdo, $accountant, ['Ver tablero', 'Emitir CFDI', 'Generar cortes', 'Consultar bitacora']);

    ensureEmployee($pdo, 'Admin General', 'admin@monsters.com', 'Admin123*', $admin, 1, 1);
    ensureEmployee($pdo, 'Celia Mae', 'gerente@monsters.com', 'Gerente123*', $manager, 1, 1);
    ensureEmployee($pdo, 'Mike Wazowski', 'vendedor@monsters.com', 'Vendedor123*', $seller, 2, null);
    ensureEmployee($pdo, 'Roz Okonkwo', 'almacen@monsters.com', 'Almacen123*', $warehouse, 1, null);
    ensureEmployee($pdo, 'Henry Waternoose', 'contador@monsters.com', 'Contador123*', $accountant, null, 1);

    $products = [
        ['Laptop Lenovo ThinkPad E16', 'PRD-001', 12500.00, 'Computo', 18999.00],
        ['Monitor Samsung 27 pulgadas FHD', 'PRD-002', 2300.00, 'Electronica', 3999.00],
        ['Impresora HP LaserJet Pro M404dn', 'PRD-003', 3100.00, 'Oficina', 5299.00],
        ['Silla ergonomica Herman Miller Sayl', 'PRD-004', 8700.00, 'Mobiliario', 13200.00],
        ['Cafetera Nespresso Vertuo Pop', 'PRD-005', 1450.00, 'Electrodomesticos', 2499.00],
    ];

    $find = $pdo->prepare('SELECT id_producto FROM PRODUCTO WHERE sku = ? LIMIT 1');
    $insertProduct = $pdo->prepare('INSERT INTO PRODUCTO (nombre, sku, costo_base) VALUES (?, ?, ?)');
    $categoryExists = $pdo->prepare('SELECT COUNT(*) FROM CATEGORIA_PRODUCTO WHERE id_producto = ? AND fecha_fin IS NULL');
    $insertCategory = $pdo->prepare('INSERT INTO CATEGORIA_PRODUCTO (id_producto, categoria, fecha_inicio) VALUES (?, ?, CURDATE())');
    $priceExists = $pdo->prepare('SELECT COUNT(*) FROM PRECIO_CANAL WHERE id_producto = ? AND canal = ? AND fecha_vigencia_fin IS NULL');
    $insertPrice = $pdo->prepare('INSERT INTO PRECIO_CANAL (id_producto, canal, precio_venta, fecha_vigencia_inicio) VALUES (?, ?, ?, CURDATE())');
    $insertStock = $pdo->prepare('INSERT INTO INVENTARIO (id_sucursal, id_producto, cantidad_disponible) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE cantidad_disponible = cantidad_disponible');

    foreach ($products as [$name, $sku, $cost, $category, $linePrice]) {
        $find->execute([$sku]);
        $id = $find->fetchColumn();
        if ($id === false) {
            $insertProduct->execute([$name, $sku, $cost]);
            $id = (int)$pdo->lastInsertId();
        } else {
            $id = (int)$id;
            $pdo->prepare('UPDATE PRODUCTO SET nombre = ?, costo_base = ? WHERE id_producto = ?')->execute([$name, $cost, $id]);
        }

        $categoryExists->execute([$id]);
        if ((int)$categoryExists->fetchColumn() === 0) {
            $insertCategory->execute([$id, $category]);
        } else {
            $pdo->prepare('UPDATE CATEGORIA_PRODUCTO SET categoria = ? WHERE id_producto = ? AND fecha_fin IS NULL')->execute([$category, $id]);
        }

        foreach (['Linea' => $linePrice, 'Fisica' => round($linePrice * 1.05, 2), 'Corporativo' => round($linePrice * 0.90, 2)] as $channel => $price) {
            $priceExists->execute([$id, $channel]);
            if ((int)$priceExists->fetchColumn() === 0) {
                $insertPrice->execute([$id, $channel, $price]);
            } else {
                $pdo->prepare('UPDATE PRECIO_CANAL SET precio_venta = ? WHERE id_producto = ? AND canal = ? AND fecha_vigencia_fin IS NULL')->execute([$price, $id, $channel]);
            }
        }

        $insertStock->execute([1, $id, 20]);
        $insertStock->execute([2, $id, 12]);
        $insertStock->execute([3, $id, 8]);
    }

    $channelCount = (int)$pdo->query('SELECT COUNT(DISTINCT canal_venta) FROM VENTA')->fetchColumn();
    if ($channelCount < 3) {
        seedBusinessActivity($pdo);
    }
}

function seedBusinessActivity(PDO $pdo): void
{
    $clients = [
        ['Individual', 'Boo', 'BOO260101AAA', 'boo@monsters.com', '5550001001', 'Linea'],
        ['Individual', 'Mike Wazowski', 'MIKE790304AAB', 'mike@monsters.com', '5550001002', 'Fisica'],
        ['Corporativo', 'Fear Tech S.A. de C.V.', 'FTE260101FT1', 'compras@feartech.com', '5550001003', 'Corporativo'],
    ];

    $clientStmt = $pdo->prepare('INSERT INTO CLIENTE (tipo_cliente, nombre_razon_social, rfc, correo, telefono) VALUES (?, ?, ?, ?, ?)');
    $addressStmt = $pdo->prepare('INSERT INTO DIRECCION_CLIENTE (id_cliente, direccion, ciudad, estado, codigo_postal) VALUES (?, ?, ?, ?, ?)');
    $saleStmt = $pdo->prepare("INSERT INTO VENTA (id_cliente, id_empleado, id_sucursal, canal_venta, estado_venta, subtotal, total) VALUES (?, 1, ?, ?, 'Pagada', ?, ?)");
    $detailStmt = $pdo->prepare('INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, precio_unitario_historico) VALUES (?, ?, ?, ?)');
    $invoiceStmt = $pdo->prepare('INSERT INTO FACTURA (id_venta, uso_cfdi, sello_digital) VALUES (?, ?, ?)');
    $shippingStmt = $pdo->prepare('INSERT INTO ENVIO (id_venta, id_direccion, id_estado_envio, fecha_estimada_entrega, numero_guia) VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 3 DAY), ?)');

    $productIds = $pdo->query("SELECT sku, id_producto FROM PRODUCTO WHERE sku IN ('PRD-001','PRD-002','PRD-003')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $skus = ['PRD-001', 'PRD-002', 'PRD-003'];

    foreach ($clients as $index => [$type, $name, $rfc, $email, $phone, $channel]) {
        $clientStmt->execute([$type, $name, $rfc, $email, $phone]);
        $clientId = (int)$pdo->lastInsertId();
        $branch = $channel === 'Corporativo' ? 3 : ($channel === 'Fisica' ? 2 : 1);
        $pdo->prepare('INSERT INTO CLIENTE_CANAL_GESTION (id_cliente, canal, id_sucursal) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE canal = VALUES(canal), id_sucursal = VALUES(id_sucursal)')->execute([$clientId, $channel, $branch]);
        $addressStmt->execute([$clientId, 'Calle Integracion ' . ($index + 1), 'Monstropolis', 'Centro', '0000' . ($index + 1)]);
        $addressId = (int)$pdo->lastInsertId();

        $productId = (int)$productIds[$skus[$index]];
        $price = currentPrice($pdo, $productId, $channel);
        $quantity = $index + 1;
        $total = $price * $quantity;
        $saleStmt->execute([$clientId, $branch, $channel, $total, $total]);
        $saleId = (int)$pdo->lastInsertId();
        $detailStmt->execute([$saleId, $productId, $quantity, $price]);
        $pdo->prepare('UPDATE INVENTARIO SET cantidad_disponible = GREATEST(cantidad_disponible - ?, 0) WHERE id_sucursal = ? AND id_producto = ?')->execute([$quantity, $branch, $productId]);

        $invoiceStmt->execute([$saleId, 'G03', 'SEAL-MONSTER-' . $saleId]);
        if ($channel === 'Linea') {
            $shippingStmt->execute([$saleId, $addressId, 2, 'GUA-MI-' . str_pad((string)$saleId, 5, '0', STR_PAD_LEFT)]);
        }
    }

    logAction($pdo, 'Datos iniciales creados para comparar Linea, Fisica y Corporativo', 1);
}

function currentPrice(PDO $pdo, int $productId, string $channel): float
{
    $stmt = $pdo->prepare("SELECT precio_venta FROM PRECIO_CANAL WHERE id_producto = ? AND canal = ? AND fecha_vigencia_inicio <= CURDATE() AND (fecha_vigencia_fin IS NULL OR fecha_vigencia_fin >= CURDATE()) ORDER BY fecha_vigencia_inicio DESC, id_precio DESC LIMIT 1");
    $stmt->execute([$productId, $channel]);
    $price = $stmt->fetchColumn();
    if ($price === false) throw new RuntimeException('No hay precio vigente para el canal seleccionado.');
    return (float)$price;
}

function getOrCreateClient(PDO $pdo, ?int $id = null): int
{
    if ($id) {
        $stmt = $pdo->prepare('SELECT id_cliente FROM CLIENTE WHERE id_cliente = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn()) return $id;
    }

    // Busca un cliente genérico, si no existe, lo crea.
    $stmt = $pdo->prepare("SELECT id_cliente FROM CLIENTE WHERE correo = ? LIMIT 1");
    $stmt->execute(['mostrador@monsters.com']);
    $found = $stmt->fetchColumn();
    if ($found) return (int)$found;

    $pdo->prepare("INSERT INTO CLIENTE (tipo_cliente, nombre_razon_social, correo) VALUES ('Individual', 'Cliente Mostrador', 'mostrador@monsters.com')")->execute();
    return (int)$pdo->lastInsertId();
}

function firstAddressForClient(PDO $pdo, int $clientId): int
{
    $stmt = $pdo->prepare('SELECT id_direccion FROM DIRECCION_CLIENTE WHERE id_cliente = ? ORDER BY id_direccion ASC LIMIT 1');
    $stmt->execute([$clientId]);
    $addressId = $stmt->fetchColumn();
    if ($addressId !== false) return (int)$addressId;

    $stmt = $pdo->prepare('INSERT INTO DIRECCION_CLIENTE (id_cliente, direccion, ciudad, estado, codigo_postal) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$clientId, 'Direccion pendiente de confirmar', 'Monterrey', 'Nuevo Leon', '64000']);
    return (int)$pdo->lastInsertId();
}

function invoiceAndShippingForSale(PDO $pdo, int $saleId, int $clientId, string $channel): void
{
    $stmt = $pdo->prepare('INSERT INTO FACTURA (id_venta, uso_cfdi, sello_digital) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE uso_cfdi = VALUES(uso_cfdi)');
    $stmt->execute([$saleId, $channel === 'Corporativo' ? 'G03' : 'G01', 'SEAL-MONSTER-' . $saleId]);

    if ($channel === 'Linea' || $channel === 'Corporativo') {
        $addressId = firstAddressForClient($pdo, $clientId);
        $guide = 'GUA-MI-' . str_pad((string)$saleId, 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('INSERT INTO ENVIO (id_venta, id_direccion, id_estado_envio, fecha_estimada_entrega, numero_guia) VALUES (?, ?, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), ?) ON DUPLICATE KEY UPDATE numero_guia = VALUES(numero_guia)');
        $stmt->execute([$saleId, $addressId, $guide]);
    }
}

function dashboardData(PDO $pdo): array
{
    $products = $pdo->query("SELECT p.id_producto AS id, p.nombre, p.sku,
        (SELECT JSON_OBJECTAGG(canal, precio_venta) FROM PRECIO_CANAL pc_inner WHERE pc_inner.id_producto = p.id_producto AND pc_inner.fecha_vigencia_fin IS NULL) as precios,
        COALESCE(cp.categoria, 'General') AS categoria,
        COALESCE(SUM(i.cantidad_disponible), 0) AS stock
        FROM PRODUCTO p
        LEFT JOIN CATEGORIA_PRODUCTO cp ON cp.id_producto = p.id_producto AND cp.fecha_fin IS NULL
        LEFT JOIN INVENTARIO i ON i.id_producto = p.id_producto
        GROUP BY p.id_producto, p.nombre, p.sku, cp.categoria
        ORDER BY p.id_producto ASC")->fetchAll();

    $inventory = $pdo->query("SELECT i.id_sucursal, s.nombre AS sucursal, p.id_producto AS id, p.sku, p.nombre, i.cantidad_disponible AS stock
        FROM INVENTARIO i
        INNER JOIN SUCURSAL s ON s.id_sucursal = i.id_sucursal
        INNER JOIN PRODUCTO p ON p.id_producto = i.id_producto
        ORDER BY s.id_sucursal, p.sku")->fetchAll();

    $clients = $pdo->query("SELECT c.id_cliente AS id, c.nombre_razon_social AS nombre, c.rfc, c.tipo_cliente AS tipo, c.correo, c.telefono,
        ccg.canal AS canal_gestion,
        sg.nombre AS lugar_gestion,
        COALESCE(SUM(CASE WHEN v.canal_venta = 'Linea' THEN v.total END), 0) AS total_linea,
        COALESCE(SUM(CASE WHEN v.canal_venta = 'Fisica' THEN v.total END), 0) AS total_fisica,
        COALESCE(SUM(CASE WHEN v.canal_venta = 'Corporativo' THEN v.total END), 0) AS total_corporativo
        FROM CLIENTE c
        LEFT JOIN CLIENTE_CANAL_GESTION ccg ON ccg.id_cliente = c.id_cliente
        LEFT JOIN SUCURSAL sg ON sg.id_sucursal = ccg.id_sucursal
        LEFT JOIN VENTA v ON v.id_cliente = c.id_cliente
        GROUP BY c.id_cliente
        ORDER BY c.id_cliente DESC")->fetchAll();

    $employees = $pdo->query("SELECT e.id_empleado AS id, e.nombre, e.correo, e.activo, r.nombre_rol AS rol, s.nombre AS sucursal
        FROM EMPLEADO e
        INNER JOIN ROL r ON r.id_rol = e.id_rol
        LEFT JOIN SUCURSAL s ON s.id_sucursal = e.id_sucursal
        ORDER BY e.id_empleado ASC")->fetchAll();

    $roles = $pdo->query('SELECT id_rol AS id, nombre_rol AS nombre FROM ROL ORDER BY id_rol')->fetchAll();
    $rolePermissions = $pdo->query("SELECT rp.id_rol, p.nombre_permiso
        FROM ROL_PERMISO rp
        INNER JOIN PERMISO p ON p.id_permiso = rp.id_permiso
        ORDER BY rp.id_rol, p.nombre_permiso")->fetchAll();
    $branches = $pdo->query('SELECT id_sucursal AS id, nombre FROM SUCURSAL ORDER BY id_sucursal')->fetchAll();

    $salesByChannel = $pdo->query("SELECT canal_venta AS canal, COUNT(*) AS ventas, COALESCE(SUM(total),0) AS total FROM VENTA GROUP BY canal_venta")->fetchAll();
    $salesByRegion = $pdo->query("SELECT r.nombre_region AS region, COUNT(v.id_venta) AS ventas, COALESCE(SUM(v.total),0) AS total
        FROM REGION r
        LEFT JOIN SUCURSAL s ON s.id_region = r.id_region
        LEFT JOIN VENTA v ON v.id_sucursal = s.id_sucursal
        GROUP BY r.id_region, r.nombre_region")->fetchAll();

    $clientsByLocation = $pdo->query("SELECT COALESCE(s.nombre, 'Sin asignar') AS lugar, COUNT(c.id_cliente) AS clientes
        FROM CLIENTE c
        LEFT JOIN CLIENTE_CANAL_GESTION ccg ON ccg.id_cliente = c.id_cliente
        LEFT JOIN SUCURSAL s ON s.id_sucursal = ccg.id_sucursal
        GROUP BY s.id_sucursal, s.nombre
        ORDER BY clientes DESC")->fetchAll();

    $shipments = $pdo->query("SELECT en.id_envio AS id, en.id_venta, dc.direccion, ee.nombre AS estado, en.fecha_estimada_entrega, en.numero_guia
        FROM ENVIO en
        INNER JOIN ESTADO_ENVIO ee ON ee.id_estado_envio = en.id_estado_envio
        INNER JOIN DIRECCION_CLIENTE dc ON dc.id_direccion = en.id_direccion
        ORDER BY en.id_envio DESC")->fetchAll();

    $invoices = $pdo->query("SELECT f.id_factura AS id, f.id_venta, f.fecha_emision, f.uso_cfdi, v.total, v.canal_venta AS canal, c.id_cliente, c.nombre_razon_social AS cliente, c.rfc
        FROM FACTURA f
        INNER JOIN VENTA v ON v.id_venta = f.id_venta
        INNER JOIN CLIENTE c ON c.id_cliente = v.id_cliente
        ORDER BY f.id_factura DESC")->fetchAll();

    $sales = $pdo->query("SELECT v.id_venta AS id, v.fecha_hora, v.canal_venta AS canal, v.subtotal, v.total, c.nombre_razon_social AS cliente, s.nombre AS sucursal
        FROM VENTA v
        INNER JOIN CLIENTE c ON c.id_cliente = v.id_cliente
        INNER JOIN SUCURSAL s ON s.id_sucursal = v.id_sucursal
        ORDER BY v.fecha_hora DESC, v.id_venta DESC")->fetchAll();

    $logs = $pdo->query("SELECT b.id_bitacora AS id, b.fecha, b.accion, e.nombre AS empleado
        FROM BITACORA b
        INNER JOIN EMPLEADO e ON e.id_empleado = b.id_empleado
        ORDER BY b.fecha DESC, b.id_bitacora DESC
        LIMIT 50")->fetchAll();

    return compact('products', 'inventory', 'clients', 'employees', 'roles', 'rolePermissions', 'branches', 'salesByChannel', 'salesByRegion', 'clientsByLocation', 'shipments', 'invoices', 'sales');
}

try {
    ensureCatalogs($pdo);

    switch ($action) {
        case 'login':
            $data = input();
            $stmt = $pdo->prepare("SELECT e.id_empleado, e.nombre, e.correo, e.`contraseña` AS password_hash, e.activo, r.nombre_rol, s.nombre AS sucursal FROM EMPLEADO e INNER JOIN ROL r ON r.id_rol = e.id_rol LEFT JOIN SUCURSAL s ON s.id_sucursal = e.id_sucursal WHERE e.correo = ? LIMIT 1");
            $stmt->execute([strtolower(trim($data['correo'] ?? ''))]);
            $employee = $stmt->fetch();
            if (!$employee || !password_verify((string)($data['password'] ?? ''), $employee['password_hash'])) response(['error' => 'Correo o contraseña incorrectos.'], 401);
            if (!(bool)$employee['activo']) response(['error' => 'El empleado esta inactivo.'], 403);
            logAction($pdo, 'Sesion iniciada: ' . $employee['correo'], (int)$employee['id_empleado']);
            response(['success' => true, 'empleado' => ['id' => (int)$employee['id_empleado'], 'nombre' => $employee['nombre'], 'correo' => $employee['correo'], 'rol' => $employee['nombre_rol'], 'sucursal' => $employee['sucursal'] ?? 'Sin sucursal']]);

        case 'initial_data':
        case 'dashboard_data':
            response(dashboardData($pdo));

        case 'create_product':
            $data = input();
            $employeeId = actorId($data);
            $name = trim($data['nombre'] ?? '');
            $sku = strtoupper(trim($data['sku'] ?? ''));
            $price = (float)($data['precio'] ?? 0);
            $category = trim($data['categoria'] ?? 'General');
            if ($name === '' || $sku === '' || $price <= 0) response(['error' => 'Datos de producto invalidos.'], 400);
            $duplicate = $pdo->prepare('SELECT COUNT(*) FROM PRODUCTO WHERE sku = ? OR LOWER(nombre) = LOWER(?)');
            $duplicate->execute([$sku, $name]);
            if ((int)$duplicate->fetchColumn() > 0) response(['error' => 'El producto ya existe'], 409);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO PRODUCTO (nombre, sku, costo_base) VALUES (?, ?, ?)');
            $stmt->execute([$name, $sku, round($price * .60, 2)]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO CATEGORIA_PRODUCTO (id_producto, categoria, fecha_inicio) VALUES (?, ?, CURDATE())')->execute([$id, $category]);
            $prices = $pdo->prepare('INSERT INTO PRECIO_CANAL (id_producto, canal, precio_venta, fecha_vigencia_inicio) VALUES (?, ?, ?, CURDATE())');
            foreach (['Linea' => $price, 'Fisica' => round($price * 1.05, 2), 'Corporativo' => round($price * .90, 2)] as $channel => $channelPrice) $prices->execute([$id, $channel, $channelPrice]);
            foreach ([1, 2, 3] as $branch) $pdo->prepare('INSERT INTO INVENTARIO (id_sucursal, id_producto, cantidad_disponible) VALUES (?, ?, 0)')->execute([$branch, $id]);
            logAction($pdo, 'Producto creado: ' . $sku, $employeeId);
            $pdo->commit();
            response(['success' => true, 'id' => $id]);

        case 'update_product':
            $data = input();
            $employeeId = actorId($data);
            $id = (int)($data['id'] ?? 0);
            $branch = (int)($data['sucursal'] ?? 1);
            $name = trim($data['nombre'] ?? '');
            $price = (float)($data['precio'] ?? 0);
            $stock = (int)($data['stock'] ?? 0);
            if ($id <= 0 || $name === '' || $price <= 0 || $stock < 0) response(['error' => 'Datos de producto invalidos.'], 400);
            $duplicate = $pdo->prepare('SELECT COUNT(*) FROM PRODUCTO WHERE id_producto <> ? AND LOWER(nombre) = LOWER(?)');
            $duplicate->execute([$id, $name]);
            if ((int)$duplicate->fetchColumn() > 0) response(['error' => 'El producto ya existe'], 409);
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE PRODUCTO SET nombre = ?, costo_base = ? WHERE id_producto = ?')->execute([$name, round($price * .60, 2), $id]);
            foreach (['Linea' => $price, 'Fisica' => round($price * 1.05, 2), 'Corporativo' => round($price * .90, 2)] as $channel => $channelPrice) {
                $pdo->prepare("UPDATE PRECIO_CANAL SET fecha_vigencia_fin = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id_producto = ? AND canal = ? AND fecha_vigencia_fin IS NULL")->execute([$id, $channel]);
                $pdo->prepare('INSERT INTO PRECIO_CANAL (id_producto, canal, precio_venta, fecha_vigencia_inicio) VALUES (?, ?, ?, CURDATE())')->execute([$id, $channel, $channelPrice]);
            }
            $pdo->prepare('INSERT INTO INVENTARIO (id_sucursal, id_producto, cantidad_disponible) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE cantidad_disponible = VALUES(cantidad_disponible)')->execute([$branch, $id, $stock]);
            logAction($pdo, 'Producto actualizado: #' . $id, $employeeId);
            $pdo->commit();
            response(['success' => true]);

        case 'create_client':
            $data = input();
            $employeeId = actorId($data);
            $type = ($data['tipo'] ?? '') === 'persona_moral' ? 'Corporativo' : 'Individual';
            $channel = in_array(($data['canal'] ?? 'Fisica'), ['Fisica', 'Linea', 'Corporativo'], true) ? $data['canal'] : 'Fisica';
            $branchId = max(1, (int)($data['sucursal'] ?? ($channel === 'Corporativo' ? 3 : ($channel === 'Fisica' ? 2 : 1))));
            $stmt = $pdo->prepare('INSERT INTO CLIENTE (tipo_cliente, nombre_razon_social, rfc, correo, telefono) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$type, trim($data['nombre'] ?? ''), strtoupper(trim($data['rfc'] ?? '')), trim($data['correo'] ?? ''), trim($data['telefono'] ?? '')]);
            $clientId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO CLIENTE_CANAL_GESTION (id_cliente, canal, id_sucursal) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE canal = VALUES(canal), id_sucursal = VALUES(id_sucursal)')->execute([$clientId, $channel, $branchId]);
            if (!empty($data['direccion'])) {
                $pdo->prepare('INSERT INTO DIRECCION_CLIENTE (id_cliente, direccion, ciudad, estado, codigo_postal) VALUES (?, ?, ?, ?, ?)')->execute([$clientId, $data['direccion'], $data['ciudad'] ?? '', $data['estado'] ?? '', $data['cp'] ?? '']);
            }
            logAction($pdo, 'Cliente creado en canal ' . $channel . ': ' . ($data['nombre'] ?? ''), $employeeId);
            response(['success' => true, 'id' => $clientId]);

        case 'delete_client':
            $data = input();
            $id = (int)($data['id'] ?? 0);
            $hasSales = $pdo->prepare('SELECT COUNT(*) FROM VENTA WHERE id_cliente = ?');
            $hasSales->execute([$id]);
            if ((int)$hasSales->fetchColumn() > 0) response(['error' => 'No se puede eliminar un cliente con ventas registradas.'], 409);
            $pdo->prepare('DELETE FROM CLIENTE_CANAL_GESTION WHERE id_cliente = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM DIRECCION_CLIENTE WHERE id_cliente = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM CLIENTE WHERE id_cliente = ?')->execute([$id]);
            logAction($pdo, 'Cliente eliminado: ' . $id, actorId($data));
            response(['success' => true]);

        case 'create_employee':
            require_once __DIR__ . '/../controllers/EmpleadoController.php';
            (new EmpleadoController($pdo))->create();
            break;

        case 'create_role':
            require_once __DIR__ . '/../controllers/RolController.php';
            (new RolController($pdo))->create();
            break;

        case 'get_role_permissions':
            require_once __DIR__ . '/../controllers/RolController.php';
            (new RolController($pdo))->getPermissions();
            break;

        case 'save_role_permissions':
            require_once __DIR__ . '/../controllers/RolController.php';
            (new RolController($pdo))->savePermissions();
            break;

        case 'delete_employee':
            require_once __DIR__ . '/../controllers/EmpleadoController.php';
            (new EmpleadoController($pdo))->delete();
            break;

        case 'toggle_employee':
            $data = input();
            $id = (int)($data['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT activo FROM EMPLEADO WHERE id_empleado = ?');
            $stmt->execute([$id]);
            $active = $stmt->fetchColumn();
            if ($active === false) response(['error' => 'Empleado no encontrado.'], 404);
            $newState = (int)!((bool)$active);
            $pdo->prepare('UPDATE EMPLEADO SET activo = ? WHERE id_empleado = ?')->execute([$newState, $id]);
            logAction($pdo, ($newState ? 'Empleado reactivado: ' : 'Empleado desactivado: ') . $id, actorId($data));
            response(['success' => true, 'activo' => $newState]);

        case 'adjust_stock':
            $data = input();
            $branch = (int)($data['sucursal'] ?? 1);
            $sku = strtoupper(trim($data['sku'] ?? ''));
            $qty = (int)($data['cantidad'] ?? 0);
            $stmt = $pdo->prepare('SELECT id_producto FROM PRODUCTO WHERE sku = ?');
            $stmt->execute([$sku]);
            $productId = $stmt->fetchColumn();
            if (!$productId) response(['error' => 'SKU no encontrado.'], 404);
            $pdo->prepare('INSERT INTO INVENTARIO (id_sucursal, id_producto, cantidad_disponible) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE cantidad_disponible = GREATEST(cantidad_disponible + VALUES(cantidad_disponible), 0)')->execute([$branch, $productId, $qty]);
            logAction($pdo, 'Inventario ajustado: ' . $sku . ' ' . $qty, actorId($data));
            response(['success' => true]);

        case 'create_sale':
            $data = input();
            $employeeId = actorId($data);
            $channel = in_array(($data['canal'] ?? 'Fisica'), ['Fisica', 'Linea', 'Corporativo'], true) ? $data['canal'] : 'Fisica';
            $items = $data['items'] ?? [];
            if (!$items) response(['error' => 'La venta no tiene productos.'], 400);
            $pdo->beginTransaction();
            $clientId = getOrCreateClient($pdo, (int)($data['cliente'] ?? 0));
            $subtotal = 0;
            $validated = [];
            $branchId = $channel === 'Corporativo' ? 3 : ($channel === 'Fisica' ? 2 : 1);
            foreach ($items as $item) {
                $productId = (int)$item['id'];
                $qty = (int)$item['cantidad'];
                $stock = $pdo->prepare('SELECT cantidad_disponible FROM INVENTARIO WHERE id_sucursal = ? AND id_producto = ? FOR UPDATE');
                $stock->execute([$branchId, $productId]);
                if ((int)$stock->fetchColumn() < $qty) throw new RuntimeException('Stock insuficiente.');
                $price = currentPrice($pdo, $productId, $channel);
                $subtotal += $price * $qty;
                $validated[] = [$productId, $qty, $price];
            }
            $stmt = $pdo->prepare("INSERT INTO VENTA (id_cliente, id_empleado, id_sucursal, canal_venta, estado_venta, subtotal, total) VALUES (?, ?, ?, ?, 'Pagada', ?, ?)");
            $stmt->execute([$clientId, $employeeId, $branchId, $channel, $subtotal, $subtotal]);
            $saleId = (int)$pdo->lastInsertId();
            foreach ($validated as [$productId, $qty, $price]) {
                $pdo->prepare('INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, precio_unitario_historico) VALUES (?, ?, ?, ?)')->execute([$saleId, $productId, $qty, $price]);
                $pdo->prepare('UPDATE INVENTARIO SET cantidad_disponible = cantidad_disponible - ? WHERE id_sucursal = ? AND id_producto = ?')->execute([$qty, $branchId, $productId]);
            }
            invoiceAndShippingForSale($pdo, $saleId, $clientId, $channel);
            logAction($pdo, 'Venta en ERP registrada en canal ' . $channel . ': #' . $saleId, $employeeId);
            $pdo->commit();
            response(['success' => true, 'venta_id' => $saleId, 'total' => $subtotal]);

        case 'create_manual_invoice':
            $data = input();
            $employeeId = actorId($data);
            $clientId = getOrCreateClient($pdo, (int)($data['cliente'] ?? 0));
            $channel = in_array(($data['canal'] ?? 'Fisica'), ['Fisica', 'Linea', 'Corporativo'], true) ? $data['canal'] : 'Fisica';
            $amount = max(1, (float)($data['importe'] ?? 0));
            $branchId = $channel === 'Corporativo' ? 3 : ($channel === 'Fisica' ? 2 : 1);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO VENTA (id_cliente, id_empleado, id_sucursal, canal_venta, estado_venta, subtotal, total) VALUES (?, ?, ?, ?, 'Pagada', ?, ?)");
            $stmt->execute([$clientId, $employeeId, $branchId, $channel, $amount, $amount * 1.16]);
            $saleId = (int)$pdo->lastInsertId();
            invoiceAndShippingForSale($pdo, $saleId, $clientId, $channel);
            logAction($pdo, 'Factura manual emitida en canal ' . $channel . ': venta #' . $saleId, $employeeId);
            $pdo->commit();
            response(['success' => true, 'venta_id' => $saleId, 'total' => $amount * 1.16]);

        case 'update_shipment':
            $data = input();
            $id = (int)($data['id'] ?? 0);
            $stateName = trim($data['estado'] ?? 'Preparando');
            $stmt = $pdo->prepare('SELECT id_estado_envio FROM ESTADO_ENVIO WHERE nombre = ? LIMIT 1');
            $stmt->execute([$stateName]);
            $stateId = $stmt->fetchColumn();
            if ($stateId === false) response(['error' => 'Estado de envio invalido.'], 400);
            $pdo->prepare('UPDATE ENVIO SET id_estado_envio = ? WHERE id_envio = ?')->execute([(int)$stateId, $id]);
            logAction($pdo, 'Envio actualizado: #' . $id . ' a ' . $stateName, actorId($data));
            response(['success' => true]);

        case 'get_logs':
            require_once __DIR__ . '/../controllers/BitacoraController.php';
            (new BitacoraController($pdo))->index();
            break;

        default:
            response(['error' => 'Accion no reconocida.'], 404);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    response(['success' => false, 'error' => $e->getMessage()], 500);
}
