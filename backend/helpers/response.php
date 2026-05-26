<?php

function jsonResponse($success, $data = null, $message = '', $status = 200)
{
    http_response_code($status);

    header('Content-Type: application/json');

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}