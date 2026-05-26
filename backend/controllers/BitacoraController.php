<?php
declare(strict_types=1);

class BitacoraController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function response(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index(): void
    {
        // En un futuro, aquí se podrían añadir filtros por fecha, empleado, etc.
        $query = "
            SELECT b.id_bitacora AS id, b.fecha, b.accion, e.nombre AS empleado
            FROM BITACORA b
            INNER JOIN EMPLEADO e ON e.id_empleado = b.id_empleado
            ORDER BY b.fecha DESC, b.id_bitacora DESC
            LIMIT 250
        ";
        $stmt = $this->pdo->query($query);
        $this->response($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}