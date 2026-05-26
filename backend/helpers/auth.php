<?php

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/session.php';

// Acepta sesión PHP O usuario enviado desde localStorage via header
if (!isset($_SESSION['user'])) {

    $userHeader = $_SERVER['HTTP_X_USER'] ?? '';

    if ($userHeader) {
        $user = json_decode(base64_decode($userHeader), true);
        if ($user && isset($user['id_empleado'])) {
            $_SESSION['user'] = $user;
        }
    }
}

if (!isset($_SESSION['user'])) {
    jsonResponse(false, null, 'No autenticado', 401);
    exit;
}