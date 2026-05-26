<?php

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/session.php';

class VentaController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = require __DIR__ . '/../config/database.php';
    }

    // CREAR VENTA

    public function create()
    {
        try {

            $data = json_decode(file_get_contents('php://input'), true);

            $idCliente = $data['id_cliente'] ?? null;
            $idSucursal = $data['id_sucursal'] ?? null;
            $canalVenta = $data['canal_venta'] ?? null;
            $productos = $data['productos'] ?? [];

            if (
                !$idCliente ||
                !$idSucursal ||
                !$canalVenta ||
                empty($productos)
            ) {

                jsonResponse(false, null, 'Datos incompletos', 400);
            }

            $this->pdo->beginTransaction();

            $subtotal = 0;

            // VALIDAR INVENTARIO

            foreach ($productos as $producto) {

                $queryStock = "
                    SELECT cantidad_disponible
                    FROM INVENTARIO
                    WHERE id_sucursal = :id_sucursal
                    AND id_producto = :id_producto
                ";

                $stmtStock = $this->pdo->prepare($queryStock);

                $stmtStock->execute([
                    ':id_sucursal' => $idSucursal,
                    ':id_producto' => $producto['id_producto']
                ]);

                $stock = $stmtStock->fetch();

                if (!$stock) {

                    throw new Exception(
                        'Producto sin inventario'
                    );
                }

                if (
                    $stock['cantidad_disponible']
                    < $producto['cantidad']
                ) {

                    throw new Exception(
                        'Stock insuficiente'
                    );
                }
            }

            // CALCULAR SUBTOTAL

            foreach ($productos as $producto) {

                $queryPrecio = "
                    SELECT precio_venta
                    FROM PRECIO_CANAL
                    WHERE id_producto = :id_producto
                    AND canal = :canal
                    LIMIT 1
                ";

                $stmtPrecio = $this->pdo->prepare($queryPrecio);

                $stmtPrecio->execute([
                    ':id_producto' => $producto['id_producto'],
                    ':canal' => $canalVenta
                ]);

                $precio = $stmtPrecio->fetch();

                if (!$precio) {

                    throw new Exception(
                        'Precio no encontrado'
                    );
                }

                $subtotal +=
                    $precio['precio_venta']
                    * $producto['cantidad'];
            }

            $total = $subtotal;

            // CREAR VENTA

            $queryVenta = "
                INSERT INTO VENTA (
                    id_cliente,
                    id_empleado,
                    id_sucursal,
                    canal_venta,
                    estado_venta,
                    subtotal,
                    total
                )
                VALUES (
                    :id_cliente,
                    :id_empleado,
                    :id_sucursal,
                    :canal_venta,
                    'Pagada',
                    :subtotal,
                    :total
                )
            ";

            $stmtVenta = $this->pdo->prepare($queryVenta);

            $stmtVenta->execute([
                ':id_cliente' => $idCliente,
                ':id_empleado' => $_SESSION['user']['id_empleado'],
                ':id_sucursal' => $idSucursal,
                ':canal_venta' => $canalVenta,
                ':subtotal' => $subtotal,
                ':total' => $total
            ]);

            $idVenta = $this->pdo->lastInsertId();

            // DETALLE VENTA + DESCONTAR INVENTARIO

            foreach ($productos as $producto) {

                $queryPrecio = "
                    SELECT precio_venta
                    FROM PRECIO_CANAL
                    WHERE id_producto = :id_producto
                    AND canal = :canal
                    LIMIT 1
                ";

                $stmtPrecio = $this->pdo->prepare($queryPrecio);

                $stmtPrecio->execute([
                    ':id_producto' => $producto['id_producto'],
                    ':canal' => $canalVenta
                ]);

                $precio = $stmtPrecio->fetch();

                // DETALLE

                $queryDetalle = "
                    INSERT INTO DETALLE_VENTA (
                        id_venta,
                        id_producto,
                        cantidad,
                        precio_unitario_historico
                    )
                    VALUES (
                        :id_venta,
                        :id_producto,
                        :cantidad,
                        :precio
                    )
                ";

                $stmtDetalle = $this->pdo->prepare($queryDetalle);

                $stmtDetalle->execute([
                    ':id_venta' => $idVenta,
                    ':id_producto' => $producto['id_producto'],
                    ':cantidad' => $producto['cantidad'],
                    ':precio' => $precio['precio_venta']
                ]);

                // DESCONTAR INVENTARIO

                $queryUpdate = "
                    UPDATE INVENTARIO
                    SET cantidad_disponible =
                        cantidad_disponible - :cantidad
                    WHERE id_sucursal = :id_sucursal
                    AND id_producto = :id_producto
                ";

                $stmtUpdate = $this->pdo->prepare($queryUpdate);

                $stmtUpdate->execute([
                    ':cantidad' => $producto['cantidad'],
                    ':id_sucursal' => $idSucursal,
                    ':id_producto' => $producto['id_producto']
                ]);
            }

            // BITÁCORA

            $queryBitacora = "
                INSERT INTO BITACORA (
                    id_empleado,
                    accion
                )
                VALUES (
                    :id_empleado,
                    :accion
                )
            ";

            $stmtBitacora = $this->pdo->prepare($queryBitacora);

            $stmtBitacora->execute([
                ':id_empleado' => $_SESSION['user']['id_empleado'],
                ':accion' => 'Nueva venta registrada ID ' . $idVenta
            ]);

            $this->pdo->commit();

            jsonResponse(true, [
                'id_venta' => $idVenta,
                'subtotal' => $subtotal,
                'total' => $total
            ], 'Venta realizada');
        } catch (Exception $e) {

            $this->pdo->rollBack();

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }
}
