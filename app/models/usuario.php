<?php

require_once __DIR__ . '/../../config/database.php';

class Usuario {
    private $pdo;

    public function __construct() {
        $this->pdo = conectar();
    }

    public function registrar($nombre, $apellido, $email, $password) {
        // Verificar si el email ya existe
        $stmt = $this->pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['exito' => false, 'mensaje' => 'El email ya está registrado'];
        }

        // Hashear la contraseña
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // Insertar usuario
        $stmt = $this->pdo->prepare('
            INSERT INTO usuarios (nombre, apellido, email, password) 
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$nombre, $apellido, $email, $hash]);
        $usuario_id = $this->pdo->lastInsertId();

        // Asignar rol participante por defecto (id 3)
        $stmt = $this->pdo->prepare('
            INSERT INTO usuario_roles (usuario_id, rol_id) 
            VALUES (?, 3)
        ');
        $stmt->execute([$usuario_id]);

        return ['exito' => true, 'mensaje' => 'Usuario registrado correctamente'];
    }

    public function login($email, $password) {
        $stmt = $this->pdo->prepare('
            SELECT u.*, r.nombre as rol 
            FROM usuarios u
            JOIN usuario_roles ur ON u.id = ur.usuario_id
            JOIN roles r ON ur.rol_id = r.id
            WHERE u.email = ?
        ');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return ['exito' => false, 'mensaje' => 'Email o contraseña incorrectos'];
        }

        if (!password_verify($password, $usuario['password'])) {
            return ['exito' => false, 'mensaje' => 'Email o contraseña incorrectos'];
        }

        return ['exito' => true, 'usuario' => $usuario];
    }
}