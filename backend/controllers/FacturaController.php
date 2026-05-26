<?php

require_once __DIR__ . '/../helpers/response.php';

class FacturaController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = require __DIR__ . '/../config/database.php';
    }

    // =========================
    // GENERAR FACTURA
    // =========================

    public function create()
    {
        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $idVenta = $data['id_venta'] ?? null;

            if (!$idVenta) {

                jsonResponse(
                    false,
                    null,
                    'ID venta requerido',
                    400
                );
            }

            // VALIDAR EXISTENCIA VENTA

            $queryVenta = "
                SELECT *
                FROM VENTA
                WHERE id_venta = :id
            ";

            $stmtVenta = $this->pdo->prepare($queryVenta);

            $stmtVenta->execute([
                ':id' => $idVenta
            ]);

            $venta = $stmtVenta->fetch();

            if (!$venta) {

                jsonResponse(
                    false,
                    null,
                    'Venta no encontrada',
                    404
                );
            }

            // VALIDAR SI YA EXISTE FACTURA

            $queryExiste = "
                SELECT *
                FROM FACTURA
                WHERE id_venta = :id
            ";

            $stmtExiste = $this->pdo->prepare($queryExiste);

            $stmtExiste->execute([
                ':id' => $idVenta
            ]);

            $facturaExiste = $stmtExiste->fetch();

            if ($facturaExiste) {

                jsonResponse(
                    false,
                    null,
                    'La venta ya tiene factura',
                    400
                );
            }

            // GENERAR FOLIO

            $folio =
                'FAC-' .
                date('Ymd') .
                '-' .
                rand(1000, 9999);

            // INSERT FACTURA

            $query = "
    INSERT INTO FACTURA (
        id_venta,
        fecha_emision,
        sello_digital,
        uso_cfdi
    )
    VALUES (
        :id_venta,
        NOW(),
        :sello,
        :uso_cfdi
    )
";

            $stmt = $this->pdo->prepare($query);

            $stmt->execute([
                ':id_venta' => $idVenta,
                ':sello' => $folio,
                ':uso_cfdi' => 'G03'
            ]);

            jsonResponse(true, [

                'folio' => $folio

            ], 'Factura generada');
        } catch (Exception $e) {

            jsonResponse(
                false,
                null,
                $e->getMessage(),
                500
            );
        }
    }

    // =========================
    // LISTAR FACTURAS
    // =========================

    public function index()
    {
        try {

            $query = "
                SELECT
    f.id_factura,
    f.fecha_emision,
    f.sello_digital,
    f.uso_cfdi,
    v.total,
    c.nombre_razon_social AS cliente,
                    v.fecha_hora
                FROM FACTURA f
                INNER JOIN VENTA v
                    ON f.id_venta = v.id_venta
                INNER JOIN CLIENTE c
                    ON v.id_cliente = c.id_cliente
                ORDER BY f.id_factura DESC
            ";

            $stmt = $this->pdo->query($query);

            $data = $stmt->fetchAll();

            jsonResponse(
                true,
                $data,
                'Facturas obtenidas'
            );
        } catch (Exception $e) {

            jsonResponse(
                false,
                null,
                $e->getMessage(),
                500
            );
        }
    }
}
