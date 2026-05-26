<?php

declare(strict_types=1);

/**
 * Modelo para gestionar las operaciones de la tabla ROL.
 * NOTA: Este archivo usa MySQLi, según el error reportado ('mysqli_fetch_assoc').
 * El resto de la aplicación parece usar PDO, lo cual es una inconsistencia a revisar.
 */
class Roles
{
    /** @var mysqli */
    private $conn;

    public function __construct(mysqli $db_connection)
    {
        $this->conn = $db_connection;
    }

    public function getAll(): array
    {
        // Corregido: La tabla se llama ROL.
        $query = "SELECT id_rol, nombre_rol FROM ROL ORDER BY nombre_rol ASC";

        $result = $this->conn->query($query);

        if ($result === false) {
            // Si la consulta falla, lanza una excepción con el error de MySQL.
            throw new Exception("Error al consultar los roles: " . $this->conn->error);
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }
}