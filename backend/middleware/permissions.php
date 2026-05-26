<?php

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/session.php';

function requireRole(array $rolesPermitidos)
{
    if (!isset($_SESSION['user'])) {

        jsonResponse(false, null, 'No autenticado', 401);
    }

    $rol = $_SESSION['user']['nombre_rol'];

    if (!in_array($rol, $rolesPermitidos)) {

        jsonResponse(false, null, 'No autorizado', 403);
    }
}