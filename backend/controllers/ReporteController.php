<?php

class ReporteController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = require __DIR__ . '/../config/database.php';
    }

    // =========================
    // REPORTE VENTAS
    // =========================

    public function ventasCSV()
    {
        $query = "
            SELECT
                v.id_venta,
                v.fecha_hora,
                c.nombre_razon_social AS cliente,
                s.nombre AS sucursal,
                v.canal_venta,
                v.total
            FROM VENTA v
            INNER JOIN CLIENTE c
                ON v.id_cliente = c.id_cliente
            INNER JOIN SUCURSAL s
                ON v.id_sucursal = s.id_sucursal
            ORDER BY v.fecha_hora DESC
        ";

        $stmt = $this->pdo->query($query);

        $ventas = $stmt->fetchAll();

        header('Content-Type: text/csv');

        header(
            'Content-Disposition: attachment; filename="reporte_ventas.csv"'
        );

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'ID Venta',
            'Fecha',
            'Cliente',
            'Sucursal',
            'Canal',
            'Total'
        ]);

        foreach ($ventas as $venta) {

            fputcsv($output, $venta);
        }

        fclose($output);

        exit;
    }

    // =========================
    // REPORTE INVENTARIO
    // =========================

    public function inventarioCSV()
    {
        $query = "
            SELECT
                s.nombre AS sucursal,
                p.sku,
                p.nombre AS producto,
                i.cantidad_disponible
            FROM INVENTARIO i
            INNER JOIN PRODUCTO p
                ON i.id_producto = p.id_producto
            INNER JOIN SUCURSAL s
                ON i.id_sucursal = s.id_sucursal
            ORDER BY s.nombre, p.nombre
        ";

        $stmt = $this->pdo->query($query);

        $inventario = $stmt->fetchAll();

        header('Content-Type: text/csv');

        header(
            'Content-Disposition: attachment; filename="reporte_inventario.csv"'
        );

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'Sucursal',
            'SKU',
            'Producto',
            'Stock'
        ]);

        foreach ($inventario as $item) {

            fputcsv($output, $item);
        }

        fclose($output);

        exit;
    }

    // =========================
    // REPORTE CLIENTES
    // =========================

    public function clientesCSV()
    {
        $query = "
            SELECT
                tipo_cliente,
                nombre_razon_social,
                rfc,
                correo,
                telefono
            FROM CLIENTE
            ORDER BY nombre_razon_social
        ";

        $stmt = $this->pdo->query($query);

        $clientes = $stmt->fetchAll();

        header('Content-Type: text/csv');

        header(
            'Content-Disposition: attachment; filename="reporte_clientes.csv"'
        );

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'Tipo',
            'Nombre',
            'RFC',
            'Correo',
            'Teléfono'
        ]);

        foreach ($clientes as $cliente) {

            fputcsv($output, $cliente);
        }

        fclose($output);

        exit;
    }
}