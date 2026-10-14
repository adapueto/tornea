<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/validacion.php';

class Usuario {
    // Después de tantos fallos seguidos, la cuenta espera unos minutos
    const MAX_INTENTOS = 5;
    const MINUTOS_BLOQUEO = 15;

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

        $error = $this->comprobarClave($usuario['id'], $usuario['password'], $password, false);
        if ($error === 'incorrecta') {
            return ['exito' => false, 'mensaje' => 'Email o contraseña incorrectos'];
        }
        if ($error) {
            return ['exito' => false, 'mensaje' => $error];
        }

        // La contraseña ya no hace falta: no se guarda en la sesión
        unset($usuario['password']);
        return ['exito' => true, 'usuario' => $usuario];
    }

    // Prueba la contraseña de una cuenta, con el límite de intentos.
    // La usan el login y editar perfil ($en_perfil), con el mismo contador.
    // Devuelve null si está bien, 'incorrecta', o el mensaje de por qué no se pudo probar.
    private function comprobarClave($usuario_id, $hash, $password, $en_perfil) {
        // De a un intento por cuenta: si llegan muchos juntos, esperan en fila.
        // Así no pueden pasar todos antes de que se anote el primer fallo.
        // El candado es el mismo para el login y para editar perfil.
        $candado = 'tornea_login_' . $usuario_id;
        $stmt = $this->pdo->prepare('SELECT GET_LOCK(?, 10)');
        $stmt->execute([$candado]);
        if ((int) $stmt->fetchColumn() !== 1) {
            return 'No pudimos revisar tus datos. Probá de nuevo en unos segundos';
        }

        try {
            // Si ya se equivocaron muchas veces, no se prueba la contraseña
            if ($this->fallosRecientes($usuario_id) >= self::MAX_INTENTOS) {
                return 'Demasiados intentos fallidos con esta cuenta. Esperá '
                    . self::MINUTOS_BLOQUEO . ' minutos y probá de nuevo';
            }

            if (!password_verify($password, $hash)) {
                if ($en_perfil) {
                    // Fue alguien con la sesión de esta cuenta abierta
                    $this->auditar($usuario_id, 'CLAVE_FALLIDA', $usuario_id);
                } else {
                    // No se sabe quién fue: queda sin usuario, sobre la cuenta que intentaron usar
                    $this->auditar(null, 'LOGIN_FALLIDO', $usuario_id);
                }
                return 'incorrecta';
            }

            // Entró bien: queda anotado y los fallos anteriores dejan de contar.
            // En el perfil se anota después, cuando se guarda la clave nueva.
            if (!$en_perfil) {
                $this->auditar($usuario_id, 'LOGIN', $usuario_id);
            }
            return null;
        } finally {
            $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$candado]);
        }
    }

    // Cuántas veces se equivocaron con esta cuenta en los últimos minutos,
    // sin contar los fallos de antes de entrar bien o de cambiar la contraseña.
    // Cambiar solo el nombre o el email no reinicia nada.
    private function fallosRecientes($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM auditoria
            WHERE tabla_afectada = 'usuarios' AND registro_id = ? AND accion IN ('LOGIN_FALLIDO', 'CLAVE_FALLIDA')
              AND fecha > NOW() - INTERVAL " . self::MINUTOS_BLOQUEO . " MINUTE
              AND id > (
                  SELECT COALESCE(MAX(id), 0) FROM auditoria
                  WHERE tabla_afectada = 'usuarios' AND registro_id = ? AND accion IN ('LOGIN', 'CLAVE')
              )
        ");
        $stmt->execute([$usuario_id, $usuario_id]);
        return (int) $stmt->fetchColumn();
    }

    public function buscarPorId($id) {
    $stmt = $this->pdo->prepare('
        SELECT u.id, u.nombre, u.apellido, u.email, u.fecha_nac, u.perfil_publico, u.created_at,
               r.nombre as rol
        FROM usuarios u
        JOIN usuario_roles ur ON u.id = ur.usuario_id
        JOIN roles r ON ur.rol_id = r.id
        WHERE u.id = ?
    ');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

    // Las tres contraseñas son opcionales: si no escribió una nueva, solo cambian los datos
    public function actualizar($id, $nombre, $apellido, $email, $fecha_nac, $perfil_publico,
                               $actual = '', $nueva = '', $repetir = '') {
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

    // Solo cuenta si escribió una nueva (la actual la puede autocompletar el navegador)
    $cambia_clave = $nueva !== '' || $repetir !== '';
    if ($cambia_clave) {
        if ($actual === '') {
            $errores[] = 'Escribí tu contraseña actual para poder cambiarla';
        }
        if ($nueva !== $repetir) {
            $errores[] = 'Las contraseñas nuevas no coinciden';
        } elseif (!passwordValida($nueva)) {
            $errores[] = 'La contraseña nueva tiene que tener al menos 8 caracteres';
        } elseif ($nueva === $actual) {
            $errores[] = 'La contraseña nueva tiene que ser distinta de la actual';
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

    // Para cambiar la contraseña hay que saber la actual (y cuenta para el límite de intentos)
    if ($cambia_clave) {
        $stmt = $this->pdo->prepare('SELECT password FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        $error = $this->comprobarClave($id, (string) $stmt->fetchColumn(), $actual, true);
        if ($error === 'incorrecta') {
            return ['exito' => false, 'mensaje' => 'La contraseña actual no es correcta'];
        }
        if ($error) {
            return ['exito' => false, 'mensaje' => $error];
        }
    }

    $sql = 'UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, fecha_nac = ?, perfil_publico = ?';
    $valores = [$nombre, $apellido, $email, $fecha_nac, $perfil_publico];
    if ($cambia_clave) {
        // Se guarda el hash, nunca la contraseña escrita
        $sql .= ', password = ?';
        $valores[] = password_hash($nueva, PASSWORD_BCRYPT);
    }
    $sql .= ' WHERE id = ?';
    $valores[] = $id;

    try {
        $this->pdo->prepare($sql)->execute($valores);
    } catch (PDOException $e) {
        if (esDuplicado($e)) {
            return ['exito' => false, 'mensaje' => 'Ese email ya lo usa otra cuenta. Escribí otro o volvé a poner el tuyo'];
        }
        throw $e;
    }
    $this->auditar($id, 'UPDATE', $id);
    if ($cambia_clave) {
        // Queda aparte en el historial, y los fallos anteriores dejan de contar
        $this->auditar($id, 'CLAVE', $id);
    }
    return [
        'exito' => true,
        'mensaje' => $cambia_clave ? 'Perfil y contraseña actualizados correctamente' : 'Perfil actualizado correctamente',
        'clave_cambiada' => $cambia_clave,
    ];
}

    private function auditar($usuario_id, $accion, $registro_id) {
        $stmt = $this->pdo->prepare("
            INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
            VALUES (?, ?, 'usuarios', ?)
        ");
        $stmt->execute([$usuario_id, $accion, $registro_id]);
    }
}