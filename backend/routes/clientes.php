<?php

require_once __DIR__ . '/../config/cors.php';

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/permissions.php';
require_once __DIR__ . '/../controllers/ClienteController.php';

requireRole([
    'Administrador General',
    'Administrador Regional',
    'Gerente Sucursal'
]);

$controller = new ClienteController();

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

        $controller->update($id);

        break;

    case 'DELETE':

        $id = $_GET['id'] ?? null;

        $controller->delete($id);

        break;

    default:

        jsonResponse(false, null, 'Método no permitido', 405);
}