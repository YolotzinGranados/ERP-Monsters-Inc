<?php

require_once __DIR__ . '/../config/cors.php';

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/response.php';

$pdo = require __DIR__ . '/../config/database.php';

$query = "
    SELECT 
        id_producto,
        nombre,
        sku
    FROM PRODUCTO
    ORDER BY nombre
";

$stmt = $pdo->query($query);

$data = $stmt->fetchAll();

jsonResponse(true, $data, 'Productos');