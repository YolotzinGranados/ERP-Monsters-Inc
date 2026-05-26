<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/audit.php';

class EmpleadoController
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
        $email = strtolower(trim($data['correo'] ?? ''));
        $password = (string)($data['password'] ?? 'Monster123*');
        $roleId = (int)($data['rol_id'] ?? 0);
        $branchId = ($data['sucursal_id'] ?? null) ? (int)$data['sucursal_id'] : null;

        if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL) || $roleId <= 0) {
            $this->response(['error' => 'Datos de empleado inválidos.'], 400);
        }

        $stmt = $this->pdo->prepare('SELECT id_empleado FROM EMPLEADO WHERE correo = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $this->response(['error' => 'El correo ya está registrado.'], 409);
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO EMPLEADO (nombre, correo, `contraseña`, id_rol, id_sucursal, activo) VALUES (?, ?, ?, ?, ?, TRUE)'
        );
        $stmt->execute([$name, $email, $hashedPassword, $roleId, $branchId]);
        $employeeId = (int)$this->pdo->lastInsertId();

        logAction($this->pdo, "Empleado creado: {$name} ({$email})", (int)($data['empleado']));

        $this->response(['success' => true, 'id' => $employeeId, 'message' => 'Empleado creado con éxito.']);
    }

    public function update(): void
    {
        authorize($this->pdo, 'Gestionar accesos');

        $data = $this->input();
        $id = (int)($data['id'] ?? 0);
        $name = trim($data['nombre'] ?? '');
        $roleId = (int)($data['rol_id'] ?? 0);
        $branchId = ($data['sucursal_id'] ?? null) ? (int)$data['sucursal_id'] : null;
        $active = (bool)($data['activo'] ?? true);

        if ($id <= 0 || empty($name) || $roleId <= 0) {
            $this->response(['error' => 'Datos de empleado inválidos.'], 400);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE EMPLEADO SET nombre = ?, id_rol = ?, id_sucursal = ?, activo = ? WHERE id_empleado = ?'
        );
        $stmt->execute([$name, $roleId, $branchId, $active, $id]);

        logAction($this->pdo, "Empleado actualizado: ID {$id}", (int)($data['empleado']));

        $this->response(['success' => true, 'message' => 'Empleado actualizado.']);
    }

    public function delete(): void
    {
        authorize($this->pdo, 'Gestionar accesos');

        $data = $this->input();
        $id = (int)($data['id'] ?? 0);

        if ($id <= 1) { // Proteger al admin principal
            $this->response(['error' => 'No se puede eliminar al administrador principal.'], 403);
        }

        // Soft delete
        $stmt = $this->pdo->prepare('UPDATE EMPLEADO SET activo = FALSE WHERE id_empleado = ?');
        $stmt->execute([$id]);

        logAction($this->pdo, "Empleado desactivado: ID {$id}", (int)($data['empleado']));

        $this->response(['success' => true, 'message' => 'Empleado desactivado.']);
    }

    public function toggleActiveState(): void
    {
        authorize($this->pdo, 'Gestionar accesos');
        $data = $this->input();
        $id = (int)($data['id'] ?? 0);

        $stmt = $this->pdo->prepare('SELECT activo FROM EMPLEADO WHERE id_empleado = ?');
        $stmt->execute([$id]);
        $active = $stmt->fetchColumn();

        if ($active === false) {
            $this->response(['error' => 'Empleado no encontrado.'], 404);
        }

        $newState = (int)!((bool)$active);
        $this->pdo->prepare('UPDATE EMPLEADO SET activo = ? WHERE id_empleado = ?')->execute([$newState, $id]);
        logAction($this->pdo, ($newState ? 'Empleado reactivado: ' : 'Empleado desactivado: ') . $id, (int)($data['empleado']));
        $this->response(['success' => true, 'activo' => $newState]);
    }
}