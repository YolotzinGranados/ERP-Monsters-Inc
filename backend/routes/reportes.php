<?php

require_once __DIR__ . '/../config/cors.php';

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/permissions.php';
require_once __DIR__ . '/../controllers/ReporteController.php';
require_once __DIR__ . '/../helpers/response.php';

requireRole([
    'Administrador General',
    'Administrador Regional'
]);

$controller = new ReporteController();

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'ventas':

        $controller->ventasCSV();

        break;

    case 'inventario':

        $controller->inventarioCSV();

        break;

    case 'clientes':

        $controller->clientesCSV();

        break;

    default:

        jsonResponse(false, null, 'Reporte no encontrado', 404);
}