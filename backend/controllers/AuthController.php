<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/session.php';

class AuthController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = require __DIR__ . '/../config/database.php';
    }

    public function login()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $correo = trim($data['correo'] ?? '');
        $password = trim($data['password'] ?? '');

        if (!$correo || !$password) {

            jsonResponse(false, null, 'Correo y contraseña requeridos', 400);
        }

        $sql = "
            SELECT 
                e.id_empleado,
                e.nombre,
                e.correo,
                e.contraseña,
                r.nombre_rol
            FROM EMPLEADO e
            INNER JOIN ROL r 
                ON e.id_rol = r.id_rol
            WHERE e.correo = :correo
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':correo' => $correo
        ]);

        $user = $stmt->fetch();

        if (!$user) {

            jsonResponse(false, null, 'Usuario no encontrado', 404);
        }

        if (!password_verify($password, $user['contraseña'])) {

            jsonResponse(false, null, 'Contraseña incorrecta', 401);
        }

        unset($user['contraseña']);

        $_SESSION['user'] = $user;

        jsonResponse(true, [
            'user' => $user
        ], 'Login exitoso');
    }

    public function session()
    {
        if (!isset($_SESSION['user'])) {

            jsonResponse(false, null, 'No autenticado', 401);
        }

        jsonResponse(true, [
            'user' => $_SESSION['user']
        ]);
    }

    public function logout()
    {
        session_destroy();

        jsonResponse(true, null, 'Sesión cerrada');
    }
}