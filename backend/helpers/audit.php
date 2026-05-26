<?php
declare(strict_types=1);

/**
 * Registra una acción en la tabla de bitácora.
 *
 * @param PDO $pdo Instancia de la conexión a la base de datos.
 * @param int $employeeId ID del empleado que realiza la acción.
 * @param string $action Descripción de la acción.
 */
function logAction(PDO $pdo, string $action, int $employeeId): void
{
    if ($employeeId <= 0) {
        // Fallback al admin principal si no se proporciona un ID válido.
        $employeeId = 1;
    }
    $stmt = $pdo->prepare('INSERT INTO BITACORA (id_empleado, accion) VALUES (?, ?)');
    $stmt->execute([$employeeId, $action]);
}