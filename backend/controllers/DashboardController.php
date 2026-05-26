<?php

require_once __DIR__ . '/../helpers/response.php';

class DashboardController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = require __DIR__ . '/../config/database.php';
    }

    public function stats()
    {
        try {

            // TOTAL VENTAS

            $ventasQuery = "
                SELECT 
                    COUNT(*) as total_ventas,
                    COALESCE(SUM(total), 0) as ingresos
                FROM VENTA
            ";

            $ventasStmt = $this->pdo->query($ventasQuery);

            $ventas = $ventasStmt->fetch();

            // TOTAL CLIENTES

            $clientesQuery = "
                SELECT COUNT(*) as total_clientes
                FROM CLIENTE
            ";

            $clientesStmt = $this->pdo->query($clientesQuery);

            $clientes = $clientesStmt->fetch();

            // INVENTARIO CRÍTICO

            $inventarioQuery = "
                SELECT COUNT(*) as inventario_critico
                FROM INVENTARIO
                WHERE cantidad_disponible<= 5
            ";

            $inventarioStmt = $this->pdo->query($inventarioQuery);

            $inventario = $inventarioStmt->fetch();

            jsonResponse(true, [

                'ventas_totales' => (int)$ventas['total_ventas'],

                'ingresos_totales' => (float)$ventas['ingresos'],

                'clientes_totales' => (int)$clientes['total_clientes'],

                'inventario_critico' => (int)$inventario['inventario_critico']

            ], 'KPIs cargados');
        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }

    public function salesByChannel()
    {
        try {

            $query = "
            SELECT 
                canal_venta,
                COUNT(*) as total_ventas,
                SUM(total) as ingresos
            FROM VENTA
            GROUP BY canal_venta
        ";

            $stmt = $this->pdo->query($query);

            $data = $stmt->fetchAll();

            jsonResponse(true, $data, 'Ventas por canal');
        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }
    public function salesByRegion()
    {
        try {

            $query = "
            SELECT 
                r.nombre_region,
                COUNT(v.id_venta) as total_ventas,
                COALESCE(SUM(v.total), 0) as ingresos
            FROM VENTA v
            INNER JOIN SUCURSAL s
                ON v.id_sucursal = s.id_sucursal
            INNER JOIN REGION r
                ON s.id_region = r.id_region
            GROUP BY r.id_region
        ";

            $stmt = $this->pdo->query($query);

            $data = $stmt->fetchAll();

            jsonResponse(true, $data, 'Ventas por región');
        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }
    public function topProducts()
    {
        try {

            $query = "
            SELECT 
                p.nombre,
                SUM(dv.cantidad) as total_vendido
            FROM DETALLE_VENTA dv
            INNER JOIN PRODUCTO p
                ON dv.id_producto = p.id_producto
            GROUP BY p.id_producto
            ORDER BY total_vendido DESC
            LIMIT 5
        ";

            $stmt = $this->pdo->query($query);

            $data = $stmt->fetchAll();

            jsonResponse(true, $data, 'Top productos');
        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }
    public function monthlySales()
    {
        try {

            $query = "
            SELECT 
                MONTH(fecha_hora) as mes,
                COUNT(*) as ventas,
                SUM(total) as ingresos
            FROM VENTA
            GROUP BY MONTH(fecha_hora)
            ORDER BY mes
        ";

            $stmt = $this->pdo->query($query);

            $data = $stmt->fetchAll();

            jsonResponse(true, $data, 'Ventas mensuales');
        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }
}
