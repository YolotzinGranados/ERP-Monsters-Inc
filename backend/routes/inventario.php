<?php

require_once __DIR__ . '/../config/cors.php';

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/permissions.php';
require_once __DIR__ . '/../controllers/InventarioController.php';

requireRole([
    'Administrador General',
    'Administrador Regional'
]);

$controller = new InventarioController();

$method = $_SERVER['REQUEST_METHOD'];

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'list':

        $controller->index();

        break;

    case 'critical':

        $controller->critical();

        break;

    case 'by-sucursal':

        $idSucursal = $_GET['id_sucursal'] ?? null;

        $controller->bySucursal($idSucursal);

        break;

    case 'update-stock':

        if ($method !== 'PUT') {

            jsonResponse(false, null, 'Método no permitido', 405);
        }

        $controller->updateStock();

        break;

    default:

        jsonResponse(false, null, 'Ruta no encontrada', 404);
}
