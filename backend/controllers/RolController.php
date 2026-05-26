<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/audit.php';

class RolController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function input(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?: [];
    }

    private function response(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function create(): void
    {
        authorize($this->pdo, 'Gestionar accesos');

        $data = $this->input();
        $name = trim($data['nombre'] ?? '');
        if ($name === '') {
            $this->response(['error' => 'El nombre del rol no puede estar vacío.'], 400);
        }

        $stmt = $this->pdo->prepare('SELECT id_rol FROM ROL WHERE nombre_rol = ?');
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $this->response(['error' => 'El rol ya existe.'], 409);
        }

        $stmt = $this->pdo->prepare('INSERT INTO ROL (nombre_rol) VALUES (?)');
        $stmt->execute([$name]);
        $roleId = (int)$this->pdo->lastInsertId();

        logAction($this->pdo, "Rol creado: {$name}", (int)($data['empleado']));

        $this->response(['success' => true, 'id' => $roleId, 'message' => 'Rol creado con éxito.']);
    }

    public function getPermissions(): void
    {
        $roleId = (int)($_GET['role_id'] ?? 0);
        if ($roleId <= 0) {
            $this->response(['error' => 'ID de rol inválido.'], 400);
        }

        $stmt = $this->pdo->prepare("
            SELECT
                p.id_permiso,
                p.nombre_permiso,
                (CASE WHEN rp.id_rol IS NOT NULL THEN TRUE ELSE FALSE END) AS asignado
            FROM PERMISO p
            LEFT JOIN ROL_PERMISO rp ON p.id_permiso = rp.id_permiso AND rp.id_rol = ?
            ORDER BY p.nombre_permiso
        ");
        $stmt->execute([$roleId]);

        $this->response($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function savePermissions(): void
    {
        authorize($this->pdo, 'Gestionar accesos');

        $data = $this->input();
        $roleId = (int)($data['rol_id'] ?? 0);
        $permissionIds = $data['permisos'] ?? [];

        if ($roleId <= 0) {
            $this->response(['error' => 'ID de rol requerido.'], 400);
        }

        $this->pdo->beginTransaction();
        try {
            // Limpiar permisos actuales
            $stmt = $this->pdo->prepare('DELETE FROM ROL_PERMISO WHERE id_rol = ?');
            $stmt->execute([$roleId]);

            // Insertar nuevos permisos
            if (!empty($permissionIds)) {
                $stmt = $this->pdo->prepare('INSERT INTO ROL_PERMISO (id_rol, id_permiso) VALUES (?, ?)');
                foreach ($permissionIds as $permissionId) {
                    $stmt->execute([$roleId, (int)$permissionId]);
                }
            }

            $this->pdo->commit();
            logAction($this->pdo, "Permisos actualizados para rol ID: {$roleId}", (int)($data['empleado']));

            $this->response(['success' => true, 'message' => 'Permisos actualizados correctamente.']);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->response(['error' => 'No se pudieron guardar los permisos: ' . $e->getMessage()], 500);
        }
    }
}