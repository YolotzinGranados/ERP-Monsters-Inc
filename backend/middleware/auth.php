<?php

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/session.php';

if (!isset($_SESSION['user'])) {
    jsonResponse(false, null, 'No autenticado', 401);
    exit;
}