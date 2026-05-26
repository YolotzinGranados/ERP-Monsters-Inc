<?php

require_once __DIR__ . '/../config/cors.php';

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/permissions.php';
require_once __DIR__ . '/../controllers/EnvioController.php';

requireRole([
    'Administrador General',
    'Administrador Regional',
    'Gerente Sucursal'
]);

$controller = new EnvioController();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':

        $controller->index();

        break;

    case 'POST':

        $controller->create();

        break;

    case 'PUT':

        $id = $_GET['id'] ?? null;

        $controller->updateStatus($id);

        break;

    default:

        jsonResponse(false, null, 'Método no permitido', 405);
}