<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$controller = new AuthController();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'login':

        if ($method !== 'POST') {
            jsonResponse(false, null, 'Método no permitido', 405);
        }

        $controller->login();

        break;

    case 'session':

        $controller->session();

        break;

    case 'logout':

        $controller->logout();

        break;

    default:

        jsonResponse(false, null, 'Ruta no encontrada', 404);
}