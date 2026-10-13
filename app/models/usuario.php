<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/validacion.php';

class Usuario {
    private $pdo;

    public function __construct() {
        $this->pdo = conectar();
    }

    public function registrar($nombre, $apellido, $email, $password) {
        $nombre = trim($nombre);
        $apellido = trim($apellido);
        $email = trim($email);

        // El navegador también revisa, pero se puede saltear, por eso se revisa acá
        $errores = validarDatosUsuario($nombre, $apellido, $email, $password);
        if ($errores) {
            return ['exito' => false, 'mensaje' => implode('. ', $errores)];
        }

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
        try {
            $stmt->execute([$nombre, $apellido, $email, $hash]);
        } catch (PDOException $e) {
            // Por si dos personas se registran con el mismo email al mismo tiempo
            if (esDuplicado($e)) {
                return ['exito' => false, 'mensaje' => 'El email ya está registrado'];
            }
            throw $e;
        }
        $usuario_id = $this->pdo->lastInsertId();

        // Asignar rol participante por defecto (id 3)
        $stmt = $this->pdo->prepare('
            INSERT INTO usuario_roles (usuario_id, rol_id) 
            VALUES (?, 3)
        ');
        $stmt->execute([$usuario_id]);

        // Auditoría (RF-63): la cuenta la creó la propia persona al registrarse
        $this->auditar($usuario_id, 'INSERT', $usuario_id);

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

    public function buscarPorId($id) {
    $stmt = $this->pdo->prepare('
        SELECT u.*, r.nombre as rol 
        FROM usuarios u
        JOIN usuario_roles ur ON u.id = ur.usuario_id
        JOIN roles r ON ur.rol_id = r.id
        WHERE u.id = ?
    ');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

    public function actualizar($id, $nombre, $apellido, $email, $fecha_nac, $perfil_publico) {
    $nombre = trim($nombre);
    $apellido = trim($apellido);
    $email = trim($email);

    $errores = validarDatosUsuario($nombre, $apellido, $email);
    if ($fecha_nac !== null) {
        // La fecha tiene que existir y no puede ser del futuro
        $fecha = DateTime::createFromFormat('!Y-m-d', $fecha_nac);
        if (!$fecha || $fecha->format('Y-m-d') !== $fecha_nac || $fecha > new DateTime() || $fecha->format('Y') < 1900) {
            $errores[] = 'Revisá la fecha de nacimiento: tiene que ser una fecha real y no puede ser futura';
        }
    }
    if ($errores) {
        return ['exito' => false, 'mensaje' => implode('. ', $errores)];
    }

    // Revisa que el email no lo tenga otra persona
    $stmt = $this->pdo->prepare('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?');
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        return ['exito' => false, 'mensaje' => 'Ese email ya lo usa otra cuenta. Escribí otro o volvé a poner el tuyo'];
    }

    $stmt = $this->pdo->prepare('
        UPDATE usuarios 
        SET nombre = ?, apellido = ?, email = ?, fecha_nac = ?, perfil_publico = ?
        WHERE id = ?
    ');
    try {
        $stmt->execute([$nombre, $apellido, $email, $fecha_nac, $perfil_publico, $id]);
    } catch (PDOException $e) {
        if (esDuplicado($e)) {
            return ['exito' => false, 'mensaje' => 'Ese email ya lo usa otra cuenta. Escribí otro o volvé a poner el tuyo'];
        }
        throw $e;
    }
    $this->auditar($id, 'UPDATE', $id);
    return ['exito' => true, 'mensaje' => 'Perfil actualizado correctamente'];
}

    private function auditar($usuario_id, $accion, $registro_id) {
        $stmt = $this->pdo->prepare("
            INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
            VALUES (?, ?, 'usuarios', ?)
        ");
        $stmt->execute([$usuario_id, $accion, $registro_id]);
    }
}