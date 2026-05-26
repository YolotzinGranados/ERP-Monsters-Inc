<?php

require_once __DIR__ . '/../helpers/response.php';

class ClienteController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = require __DIR__ . '/../config/database.php';
    }

    // LISTAR CLIENTES

    public function index()
    {
        try {

            $query = "
                SELECT 
                    id_cliente,
                    tipo_cliente,
                    nombre_razon_social,
                    rfc,
                    correo,
                    telefono
                FROM CLIENTE
                ORDER BY nombre_razon_social
            ";

            $stmt = $this->pdo->query($query);

            $data = $stmt->fetchAll();

            jsonResponse(true, $data, 'Clientes obtenidos');

        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }

    // CREAR CLIENTE

    public function create()
    {
        try {

            $data = json_decode(file_get_contents('php://input'), true);

            $query = "
                INSERT INTO CLIENTE (
                    tipo_cliente,
                    nombre_razon_social,
                    rfc,
                    correo,
                    telefono
                )
                VALUES (
                    :tipo_cliente,
                    :nombre,
                    :rfc,
                    :correo,
                    :telefono
                )
            ";

            $stmt = $this->pdo->prepare($query);

            $stmt->execute([
                ':tipo_cliente' => $data['tipo_cliente'],
                ':nombre' => $data['nombre_razon_social'],
                ':rfc' => $data['rfc'],
                ':correo' => $data['correo'],
                ':telefono' => $data['telefono']
            ]);

            jsonResponse(true, null, 'Cliente creado');

        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }

    // ACTUALIZAR CLIENTE

    public function update($id)
    {
        try {

            $data = json_decode(file_get_contents('php://input'), true);

            $query = "
                UPDATE CLIENTE
                SET
                    tipo_cliente = :tipo_cliente,
                    nombre_razon_social = :nombre,
                    rfc = :rfc,
                    correo = :correo,
                    telefono = :telefono
                WHERE id_cliente = :id
            ";

            $stmt = $this->pdo->prepare($query);

            $stmt->execute([
                ':tipo_cliente' => $data['tipo_cliente'],
                ':nombre' => $data['nombre_razon_social'],
                ':rfc' => $data['rfc'],
                ':correo' => $data['correo'],
                ':telefono' => $data['telefono'],
                ':id' => $id
            ]);

            jsonResponse(true, null, 'Cliente actualizado');

        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }

    // ELIMINAR CLIENTE

    public function delete($id)
    {
        try {

            $query = "
                DELETE FROM CLIENTE
                WHERE id_cliente = :id
            ";

            $stmt = $this->pdo->prepare($query);

            $stmt->execute([
                ':id' => $id
            ]);

            jsonResponse(true, null, 'Cliente eliminado');

        } catch (Exception $e) {

            jsonResponse(false, null, $e->getMessage(), 500);
        }
    }
}