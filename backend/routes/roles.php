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
            $stmt = $pdo->query('SELECT id_rol, nombre_rol FROM ROL ORDER BY nombre_rol ASC');
            jsonResponse(true, $stmt->fetchAll(), 'Roles obtenidos');
            break;

        case 'create':
            if ($method !== 'POST') { jsonResponse(false, null, 'Método no permitido', 405); }
            $data = json_decode(file_get_contents('php://input'), true);
            $nombre = trim($data['nombre_rol'] ?? '');
            if (!$nombre) { jsonResponse(false, null, 'Nombre requerido', 400); }
            $stmt = $pdo->prepare('INSERT INTO ROL (nombre_rol) VALUES (?)');
            $stmt->execute([$nombre]);
            jsonResponse(true, ['id_rol' => $pdo->lastInsertId()], 'Rol creado');
            break;

        case 'delete':
            if ($method !== 'DELETE') { jsonResponse(false, null, 'Método no permitido', 405); }
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id_rol'] ?? 0);
            if ($id <= 0) { jsonResponse(false, null, 'ID inválido', 400); }
            $pdo->prepare('DELETE FROM ROL_PERMISO WHERE id_rol = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM ROL WHERE id_rol = ?')->execute([$id]);
            jsonResponse(true, null, 'Rol eliminado');
            break;
            case 'get_permissions':
    $roleId = (int)($_GET['role_id'] ?? 0);
    if ($roleId <= 0) { jsonResponse(false, null, 'ID inválido', 400); }
    $stmt = $pdo->prepare("
        SELECT
            p.id_permiso,
            p.nombre_permiso,
            CASE WHEN rp.id_rol IS NOT NULL THEN 1 ELSE 0 END AS asignado
        FROM PERMISO p
        LEFT JOIN ROL_PERMISO rp ON p.id_permiso = rp.id_permiso AND rp.id_rol = ?
        ORDER BY p.nombre_permiso
    ");
    $stmt->execute([$roleId]);
    $data = $stmt->fetchAll();
    echo json_encode($data);
    exit;
    break;  
    case 'save_permissions':
    if ($method !== 'POST') { jsonResponse(false, null, 'Método no permitido', 405); }
    $data = json_decode(file_get_contents('php://input'), true);
    $roleId = (int)($data['rol_id'] ?? 0);
    $permisos = $data['permisos'] ?? [];
    if ($roleId <= 0) { jsonResponse(false, null, 'ID de rol requerido', 400); }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM ROL_PERMISO WHERE id_rol = ?')->execute([$roleId]);
        if (!empty($permisos)) {
            $stmt = $pdo->prepare('INSERT INTO ROL_PERMISO (id_rol, id_permiso) VALUES (?, ?)');
            foreach ($permisos as $permId) {
                $stmt->execute([$roleId, (int)$permId]);
            }
        }
        $pdo->commit();
        jsonResponse(true, null, 'Permisos actualizados');
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(false, null, $e->getMessage(), 500);
    }
    break;

        default:
            jsonResponse(false, null, 'Acción no encontrada', 404);
    }
} catch (Throwable $e) {
    jsonResponse(false, null, $e->getMessage(), 500);
}