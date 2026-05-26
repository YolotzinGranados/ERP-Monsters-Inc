<?php

require_once __DIR__ . '/../helpers/response.php';

class InventarioController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = require __DIR__ . '/../config/database.php';
    }

    // INVENTARIO GENERAL

    public function index()
    {
        try {

            $query = "
                SELECT 
                    i.id_sucursal,
                    s.nombre AS sucursal,
                    p.id_producto,
                    p.nombre AS producto,
                    p.sku,
                    i.cantidad_disponible
                FROM INVENTARIO i
                INNER JOIN PRODUCTO p
                    ON i.id_producto = p.id_producto
                INNER JOIN SUCURSAL s
                    ON i.id_sucursal = s.id_sucursal
                ORDER BY s.nombre, p.nombre
            ";

            $stmt = $this->pdo->query($query);

            $data = $stmt->fetchAll();

            jsonResponse(true, $data, 'Inventario obtenido');
        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }

    // INVENTARIO POR SUCURSAL

    public function bySucursal($idSucursal)
    {
        try {

            $query = "
                SELECT 
                    p.id_producto,
                    p.nombre,
                    p.sku,
                    i.cantidad_disponible
                FROM INVENTARIO i
                INNER JOIN PRODUCTO p
                    ON i.id_producto = p.id_producto
                WHERE i.id_sucursal = :id_sucursal
            ";

            $stmt = $this->pdo->prepare($query);

            $stmt->execute([
                ':id_sucursal' => $idSucursal
            ]);

            $data = $stmt->fetchAll();

            jsonResponse(true, $data, 'Inventario sucursal');
        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }

    // INVENTARIO CRÍTICO

    public function critical()
    {
        try {

            $query = "
                SELECT 
                    s.nombre AS sucursal,
                    p.nombre AS producto,
                    i.cantidad_disponible
                FROM INVENTARIO i
                INNER JOIN PRODUCTO p
                    ON i.id_producto = p.id_producto
                INNER JOIN SUCURSAL s
                    ON i.id_sucursal = s.id_sucursal
                WHERE i.cantidad_disponible <= 5
                ORDER BY i.cantidad_disponible ASC
            ";

            $stmt = $this->pdo->query($query);

            $data = $stmt->fetchAll();

            jsonResponse(true, $data, 'Inventario crítico');
        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }

    // ACTUALIZAR STOCK

    public function updateStock()
    {
        try {

            $data = json_decode(file_get_contents('php://input'), true);

            $idSucursal = $data['id_sucursal'] ?? null;
            $idProducto = $data['id_producto'] ?? null;
            $cantidad = $data['cantidad_disponible'] ?? null;

            if (!$idSucursal || !$idProducto || $cantidad === null) {

                jsonResponse(false, null, 'Datos incompletos', 400);
            }

            $query = "
                UPDATE INVENTARIO
                SET cantidad_disponible = :cantidad
                WHERE id_sucursal = :id_sucursal
                AND id_producto = :id_producto
            ";

            $stmt = $this->pdo->prepare($query);

            $stmt->execute([
                ':cantidad' => $cantidad,
                ':id_sucursal' => $idSucursal,
                ':id_producto' => $idProducto
            ]);

            jsonResponse(true, null, 'Stock actualizado');
        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }
}
