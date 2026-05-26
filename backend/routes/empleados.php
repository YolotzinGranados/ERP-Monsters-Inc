<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/session.php';

if (!isset($_SESSION['user'])) {
    jsonResponse(false, null, 'No autenticado', 401);
    exit;
}

$pdo    = require __DIR__ . '/../config/database.php';
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'list':
            $stmt = $pdo->query("
                SELECT
                    e.id_empleado,
                    e.nombre,
                    e.correo,
                    e.activo,
                    r.nombre_rol AS rol,
                    s.nombre     AS sucursal
                FROM EMPLEADO e
                INNER JOIN ROL r ON e.id_rol = r.id_rol
                LEFT JOIN  SUCURSAL s ON e.id_sucursal = s.id_sucursal
                ORDER BY e.id_empleado ASC
            ");
            jsonResponse(true, $stmt->fetchAll(), 'Empleados obtenidos');
            break;

        case 'create':
            if ($method !== 'POST') { jsonResponse(false, null, 'Método no permitido', 405); }
            $data      = json_decode(file_get_contents('php://input'), true);
            $nombre    = trim($data['nombre']     ?? '');
            $correo    = trim($data['correo']     ?? '');
            $rolId     = (int)($data['rol_id']    ?? 0);
            $sucursalId = ($data['sucursal_id'] ?? null) ? (int)$data['sucursal_id'] : null;
            $password  = 'Monster123*';

            if (!$nombre || !$correo || !$rolId) {
                jsonResponse(false, null, 'Nombre, correo y rol son obligatorios', 400);
            }

            $check = $pdo->prepare('SELECT id_empleado FROM EMPLEADO WHERE correo = ?');
            $check->execute([$correo]);
            if ($check->fetch()) { jsonResponse(false, null, 'El correo ya está registrado', 409); }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO EMPLEADO (nombre, correo, `contraseña`, id_rol, id_sucursal, activo) VALUES (?, ?, ?, ?, ?, TRUE)');
            $stmt->execute([$nombre, $correo, $hash, $rolId, $sucursalId]);
            jsonResponse(true, ['id_empleado' => $pdo->lastInsertId()], 'Empleado creado. Contraseña: Monster123*');
            break;

        case 'update':
            if ($method !== 'PUT') { jsonResponse(false, null, 'Método no permitido', 405); }
            $data       = json_decode(file_get_contents('php://input'), true);
            $id         = (int)($data['id_empleado'] ?? 0);
            $nombre     = trim($data['nombre']        ?? '');
            $rolId      = (int)($data['rol_id']       ?? 0);
            $sucursalId = ($data['sucursal_id'] ?? null) ? (int)$data['sucursal_id'] : null;
            $activo     = isset($data['activo']) ? (int)$data['activo'] : 1;

            if ($id <= 0 || !$nombre || !$rolId) { jsonResponse(false, null, 'Datos inválidos', 400); }
            $stmt = $pdo->prepare('UPDATE EMPLEADO SET nombre=?, id_rol=?, id_sucursal=?, activo=? WHERE id_empleado=?');
            $stmt->execute([$nombre, $rolId, $sucursalId, $activo, $id]);
            jsonResponse(true, null, 'Empleado actualizado');
            break;

        case 'toggle':
            if ($method !== 'POST') { jsonResponse(false, null, 'Método no permitido', 405); }
            $data = json_decode(file_get_contents('php://input'), true);
            $id   = (int)($data['id_empleado'] ?? 0);
            if ($id <= 1) { jsonResponse(false, null, 'No se puede modificar al administrador principal', 403); }
            $check = $pdo->prepare('SELECT activo FROM EMPLEADO WHERE id_empleado = ?');
            $check->execute([$id]);
            $actual = $check->fetchColumn();
            if ($actual === false) { jsonResponse(false, null, 'Empleado no encontrado', 404); }
            $nuevo = $actual ? 0 : 1;
            $pdo->prepare('UPDATE EMPLEADO SET activo = ? WHERE id_empleado = ?')->execute([$nuevo, $id]);
            jsonResponse(true, ['activo' => $nuevo], $nuevo ? 'Empleado activado' : 'Empleado desactivado');
            break;

        default:
            jsonResponse(false, null, 'Acción no encontrada', 404);
    }
} catch (Throwable $e) {
    jsonResponse(false, null, $e->getMessage(), 500);
}