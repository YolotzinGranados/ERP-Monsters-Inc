<?php

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/permissions.php';
require_once __DIR__ . '/../controllers/VentaController.php';
require_once __DIR__ . '/../helpers/response.php';

try {
    requireRole([
        'Administrador General',
        'Administrador Regional',
        'Gerente Sucursal'
    ]);

    $controller = new VentaController();

    $method = $_SERVER['REQUEST_METHOD'];

    $action = $_GET['action'] ?? '';

    switch ($action) {

        case 'create':

            if ($method !== 'POST') {

                jsonResponse(false, null, 'Método no permitido', 405);
            }

            $controller->create();

            break;

        default:

            jsonResponse(false, null, 'Ruta no encontrada', 404);
    }
} catch (Throwable $e) {
    // En caso de un error inesperado, devolvemos una respuesta JSON
    // en lugar de la salida de error HTML de PHP.
    $statusCode = is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    jsonResponse(false, null, $e->getMessage(), $statusCode);
}
