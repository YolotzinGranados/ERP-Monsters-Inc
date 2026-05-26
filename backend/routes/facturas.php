<?php

require_once __DIR__ . '/../config/cors.php';

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/permissions.php';
require_once __DIR__ . '/../controllers/FacturaController.php';

requireRole([
    'Administrador General',
    'Administrador Regional',
    'Gerente Sucursal'
]);

$controller = new FacturaController();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':

        $controller->index();

        break;

    case 'POST':

        $controller->create();

        break;

    default:

        jsonResponse(
            false,
            null,
            'Método no permitido',
            405
        );
}