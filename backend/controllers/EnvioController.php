<?php

require_once __DIR__ . '/../helpers/response.php';

class EnvioController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = require __DIR__ . '/../config/database.php';
    }

    // =========================
    // LISTAR ENVÍOS
    // =========================

    public function index()
    {
        try {

            $query = "
    SELECT
        e.id_envio,
        e.id_venta,
        e.fecha_estimada_entrega,
        e.numero_guia,
        es.nombre,
        c.nombre_razon_social AS cliente
    FROM ENVIO e
    INNER JOIN ESTADO_ENVIO es
        ON e.id_estado_envio = es.id_estado_envio
    INNER JOIN VENTA v
        ON e.id_venta = v.id_venta
    INNER JOIN CLIENTE c
        ON v.id_cliente = c.id_cliente
    ORDER BY e.id_envio DESC
";

            $stmt = $this->pdo->query($query);

            $data = $stmt->fetchAll();

            jsonResponse(true, $data, 'Envíos obtenidos');

        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }

    // =========================
    // CREAR ENVÍO
    // =========================

    public function create()
    {
        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $idVenta = $data['id_venta'] ?? null;
            $queryCliente = "
    SELECT id_cliente
    FROM VENTA
    WHERE id_venta = :id_venta
    LIMIT 1
";

$stmtCliente = $this->pdo->prepare($queryCliente);

$stmtCliente->execute([
    ':id_venta' => $idVenta
]);

$venta = $stmtCliente->fetch();

if (!$venta) {

    jsonResponse(
        false,
        null,
        'Venta no encontrada',
        404
    );
}

$queryDireccion = "
    SELECT id_direccion
    FROM DIRECCION_CLIENTE
    WHERE id_cliente = :id_cliente
    LIMIT 1
";

$stmtDireccion = $this->pdo->prepare($queryDireccion);

$stmtDireccion->execute([
    ':id_cliente' => $venta['id_cliente']
]);

$direccion = $stmtDireccion->fetch();

if (!$direccion) {

    jsonResponse(
        false,
        null,
        'El cliente no tiene dirección',
        400
    );
}

            if (!$idVenta) {

                jsonResponse(
                    false,
                    null,
                    'Venta requerida',
                    400
                );
            }

            $query = "
    INSERT INTO ENVIO (
    id_venta,
    id_direccion,
    id_estado_envio,
    fecha_estimada_entrega,
    numero_guia
)VALUES (
    :id_venta,
    :id_direccion,
    1,
    DATE_ADD(CURDATE(), INTERVAL 5 DAY),
    :numero_guia
)
";

            $stmt = $this->pdo->prepare($query);

            $stmt->execute([
    ':id_venta' => $idVenta,

    ':id_direccion' =>
        $direccion['id_direccion'],

    ':numero_guia' =>
        'GUIA-' .
        strtoupper(substr(md5(uniqid()), 0, 8))
]);

            jsonResponse(
                true,
                null,
                'Envío creado'
            );

        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }

    // =========================
    // ACTUALIZAR ESTADO
    // =========================

    public function updateStatus($id)
    {
        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $query = "
                UPDATE ENVIO
                SET id_estado_envio = :estado
                WHERE id_envio = :id
            ";

            $stmt = $this->pdo->prepare($query);

            $stmt->execute([
                ':estado' => $data['id_estado_envio'],
                ':id' => $id
            ]);

            jsonResponse(
                true,
                null,
                'Estado actualizado'
            );

        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }
}