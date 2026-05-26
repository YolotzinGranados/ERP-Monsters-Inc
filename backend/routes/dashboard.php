<?php

require_once __DIR__ . '/../config/cors.php';

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/permissions.php';
require_once __DIR__ . '/../controllers/DashboardController.php';

requireRole([
    'Administrador General'
]);

$controller = new DashboardController();

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'stats':

        $controller->stats();

        break;
    case 'sales-by-channel':

        $controller->salesByChannel();

        break;

    case 'sales-by-region':

        $controller->salesByRegion();

        break;

    case 'top-products':

        $controller->topProducts();

        break;

    case 'monthly-sales':

        $controller->monthlySales();

        break;

    default:

        jsonResponse(false, null, 'Ruta no encontrada', 404);
}
