<?php

require_once __DIR__ . '/../../config/database.php';

// Consultas y acciones del panel de administración (RF-03 a RF-07, RF-10, RF-11, RF-61, RF-66).
// Quién puede usarlo se controla en el controlador y en la vista con Torneo::esAdmin().
class Admin {
    const ROLES = ['admin', 'organizador', 'participante'];
    const POR_PAGINA = 40;

    private $pdo;

    public function __construct() {
        $this->pdo = conectar();
    }

    // ===== Reportes (RF-61) =====

    public function reportes() {
        $q = function ($sql) {
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
        };
        $uno = function ($sql) {
            return (int) $this->pdo->query($sql)->fetchColumn();
        };

        return [
            'usuarios' => $uno('SELECT COUNT(*) FROM usuarios'),
            'usuarios_por_rol' => $q("
                SELECT r.nombre, COUNT(ur.usuario_id) FROM roles r
                LEFT JOIN usuario_roles ur ON ur.rol_id = r.id
                GROUP BY r.id, r.nombre ORDER BY r.id
            "),
            'usuarios_nuevos_30' => $uno('SELECT COUNT(*) FROM usuarios WHERE created_at >= CURDATE() - INTERVAL 30 DAY'),
            'torneos' => $uno('SELECT COUNT(*) FROM torneos'),
            'torneos_por_estado' => $q("
                SELECT estado, COUNT(*) FROM torneos GROUP BY estado
                ORDER BY FIELD(estado, 'borrador', 'publicado', 'en_curso', 'finalizado')
            "),
            'torneos_por_tipo' => $q("SELECT tipo, COUNT(*) FROM torneos GROUP BY tipo ORDER BY FIELD(tipo, 'liga', 'eliminacion', 'suizo')"),
            'torneos_por_deporte' => $q('SELECT deporte, COUNT(*) AS c FROM torneos GROUP BY deporte ORDER BY c DESC, deporte'),
            'equipos' => $uno('SELECT COUNT(*) FROM equipos'),
            'inscripciones_por_estado' => $q("
                SELECT estado, COUNT(*) FROM participantes GROUP BY estado
                ORDER BY FIELD(estado, 'pendiente', 'aprobado', 'rechazado')
            "),
            'partidos_jugados' => $uno('SELECT COUNT(*) FROM resultados'),
            'partidos_pendientes' => $uno("
                SELECT COUNT(*) FROM enfrentamientos en
                LEFT JOIN resultados res ON res.enfrentamiento_id = en.id
                WHERE en.participante_visitante_id IS NOT NULL AND res.id IS NULL
            "),
            // Cambios registrados por día en las últimas dos semanas
            'actividad' => $q("
                SELECT DATE(fecha) AS dia, COUNT(*) FROM auditoria
                WHERE fecha >= CURDATE() - INTERVAL 13 DAY
                GROUP BY dia ORDER BY dia
            "),
            'torneos_mas_inscriptos' => $this->pdo->query("
                SELECT t.id, t.nombre, t.estado, COUNT(p.id) AS inscriptos
                FROM torneos t
                JOIN participantes p ON p.torneo_id = t.id AND p.estado = 'aprobado'
                GROUP BY t.id, t.nombre, t.estado
                ORDER BY inscriptos DESC, t.nombre
                LIMIT 5
            ")->fetchAll(),
        ];
    }

    // ===== Torneos: todos, también los borradores =====

    public function listarTorneos($filtros) {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($filtros['estado'] ?? '', ['borrador', 'publicado', 'en_curso', 'finalizado'], true)) {
            $where[] = 't.estado = ?';
            $params[] = $filtros['estado'];
        }
        if (in_array($filtros['tipo'] ?? '', ['liga', 'eliminacion', 'suizo'], true)) {
            $where[] = 't.tipo = ?';
            $params[] = $filtros['tipo'];
        }
        if (trim($filtros['buscar'] ?? '') !== '') {
            $where[] = 't.nombre LIKE ?';
            $params[] = '%' . trim($filtros['buscar']) . '%';
        }

        $stmt = $this->pdo->prepare("
            SELECT t.id, t.nombre, t.deporte, t.tipo, t.modalidad, t.estado, t.fecha_inicio, t.fecha_fin,
                   (SELECT GROUP_CONCAT(CONCAT(u.nombre, ' ', u.apellido) ORDER BY u.nombre SEPARATOR ', ')
                    FROM torneo_organizadores o JOIN usuarios u ON u.id = o.usuario_id
                    WHERE o.torneo_id = t.id) AS organizadores,
                   (SELECT COUNT(*) FROM participantes p WHERE p.torneo_id = t.id AND p.estado = 'aprobado') AS inscriptos,
                   (SELECT COUNT(*) FROM participantes p WHERE p.torneo_id = t.id AND p.estado = 'pendiente') AS pendientes
            FROM torneos t
            WHERE " . implode(' AND ', $where) . "
            ORDER BY FIELD(t.estado, 'en_curso', 'publicado', 'borrador', 'finalizado'), t.fecha_inicio DESC, t.id DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ===== Usuarios (RF-03 a RF-06, RF-10, RF-11) =====

    // Usuarios con su rol y su actividad. "puede_eliminarse" dice si no tiene historial
    // que se perdería al borrarlo (ver motivoNoPuedeEliminar).
    public function listarUsuarios($filtros, $pagina) {
        [$where, $params] = $this->filtroUsuarios($filtros);

        $stmt = $this->pdo->prepare("
            SELECT u.id, u.nombre, u.apellido, u.email, u.created_at, r.nombre AS rol,
                   (SELECT COUNT(*) FROM torneo_organizadores o WHERE o.usuario_id = u.id) AS organiza,
                   (SELECT COUNT(*) FROM participantes p WHERE p.usuario_id = u.id) AS inscripciones,
                   (SELECT COUNT(*) FROM equipo_miembros m WHERE m.usuario_id = u.id) AS equipos
            FROM usuarios u
            LEFT JOIN usuario_roles ur ON ur.usuario_id = u.id
            LEFT JOIN roles r ON r.id = ur.rol_id
            WHERE $where
            ORDER BY FIELD(r.nombre, 'admin', 'organizador', 'participante'), u.apellido, u.nombre
            LIMIT " . self::POR_PAGINA . ' OFFSET ' . (($pagina - 1) * self::POR_PAGINA)
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function contarUsuarios($filtros) {
        [$where, $params] = $this->filtroUsuarios($filtros);
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM usuarios u
            LEFT JOIN usuario_roles ur ON ur.usuario_id = u.id
            LEFT JOIN roles r ON r.id = ur.rol_id
            WHERE $where
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function filtroUsuarios($filtros) {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($filtros['rol'] ?? '', self::ROLES, true)) {
            $where[] = 'r.nombre = ?';
            $params[] = $filtros['rol'];
        }
        if (trim($filtros['buscar'] ?? '') !== '') {
            $where[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE ? OR u.email LIKE ?)";
            $texto = '%' . trim($filtros['buscar']) . '%';
            array_push($params, $texto, $texto);
        }
        return [implode(' AND ', $where), $params];
    }

    // Crea una cuenta con el rol elegido, por ejemplo otro administrador (RF-03)
    public function crearUsuario($datos, $admin_id) {
        $nombre = trim($datos['nombre'] ?? '');
        $apellido = trim($datos['apellido'] ?? '');
        $email = trim($datos['email'] ?? '');
        $password = $datos['password'] ?? '';
        $rol = $datos['rol'] ?? '';

        $errores = [];
        if ($nombre === '' || $apellido === '') {
            $errores[] = 'Completá el nombre y el apellido';
        }
        if (mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) {
            $errores[] = 'El nombre y el apellido pueden tener hasta 100 caracteres';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Ingresá un email válido, por ejemplo nombre@dominio.com';
        }
        if (strlen($password) < 8) {
            $errores[] = 'La contraseña tiene que tener al menos 8 caracteres';
        }
        if (!in_array($rol, self::ROLES, true)) {
            $errores[] = 'Elegí un rol de la lista';
        }
        if ($errores) {
            return ['exito' => false, 'mensaje' => implode('. ', $errores)];
        }

        $stmt = $this->pdo->prepare('SELECT 1 FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['exito' => false, 'mensaje' => 'Ya hay una cuenta con ese email'];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('INSERT INTO usuarios (nombre, apellido, email, password) VALUES (?, ?, ?, ?)');
            $stmt->execute([$nombre, $apellido, $email, password_hash($password, PASSWORD_BCRYPT)]);
            $usuario_id = $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare('INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = ?');
            $stmt->execute([$usuario_id, $rol]);

            $this->auditar($admin_id, 'INSERT', 'usuarios', $usuario_id);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo crear la cuenta, intentá de nuevo'];
        }

        return ['exito' => true, 'mensaje' => "Cuenta creada: $nombre $apellido ($rol)"];
    }

    // Cambia el rol de un usuario (RF-04, RF-06). Así también se le saca el rol de
    // administrador a alguien (RF-05) sin borrar su cuenta ni su historial.
    public function cambiarRol($usuario_id, $rol, $admin_id) {
        if (!in_array($rol, self::ROLES, true)) {
            return ['exito' => false, 'mensaje' => 'Elegí un rol de la lista'];
        }
        $usuario = $this->buscarUsuario($usuario_id);
        if (!$usuario) {
            return ['exito' => false, 'mensaje' => 'El usuario no existe'];
        }
        if ($usuario['rol'] === $rol) {
            return ['exito' => false, 'mensaje' => "{$usuario['nombre']} ya tiene el rol $rol"];
        }
        if ($usuario['rol'] === 'admin' && (int) $usuario_id === (int) $admin_id) {
            return ['exito' => false, 'mensaje' => 'No te podés sacar a vos mismo el rol de administrador: pedíselo a otro administrador'];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM usuario_roles WHERE usuario_id = ?');
            $stmt->execute([$usuario_id]);
            $stmt = $this->pdo->prepare('INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = ?');
            $stmt->execute([$usuario_id, $rol]);

            $this->auditar($admin_id, 'UPDATE', 'usuario_roles', $usuario_id);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo cambiar el rol, intentá de nuevo'];
        }

        return ['exito' => true, 'mensaje' => "{$usuario['nombre']} {$usuario['apellido']} ahora es $rol"];
    }

    // Una cuenta solo se borra si no dejó rastro en torneos o equipos: si no, al borrarla
    // se perderían inscripciones, equipos y resultados de torneos que ya se jugaron.
    public function motivoNoPuedeEliminar($usuario, $admin_id) {
        if ((int) $usuario['id'] === (int) $admin_id) {
            return 'No podés eliminar tu propia cuenta desde el panel';
        }
        if ($usuario['rol'] === 'admin') {
            return 'Es administrador: primero cambiale el rol';
        }
        if ($usuario['organiza'] > 0) {
            return 'Organiza torneos';
        }
        if ($usuario['inscripciones'] > 0) {
            return 'Tiene inscripciones en torneos';
        }
        if ($usuario['equipos'] > 0) {
            return 'Forma parte de equipos';
        }
        return null;
    }

    public function eliminarUsuario($usuario_id, $admin_id) {
        $usuario = $this->buscarUsuario($usuario_id);
        if (!$usuario) {
            return ['exito' => false, 'mensaje' => 'El usuario no existe'];
        }
        $motivo = $this->motivoNoPuedeEliminar($usuario, $admin_id);
        if ($motivo) {
            return ['exito' => false, 'mensaje' => "No se puede eliminar a {$usuario['nombre']} {$usuario['apellido']}: " . mb_strtolower(mb_substr($motivo, 0, 1)) . mb_substr($motivo, 1) . '.'];
        }

        $this->pdo->beginTransaction();
        try {
            // usuario_roles e invitaciones se borran solas (ON DELETE CASCADE)
            $stmt = $this->pdo->prepare('DELETE FROM usuarios WHERE id = ?');
            $stmt->execute([$usuario_id]);
            $this->auditar($admin_id, 'DELETE', 'usuarios', $usuario_id);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo eliminar la cuenta, intentá de nuevo'];
        }

        return ['exito' => true, 'mensaje' => "Eliminaste la cuenta de {$usuario['nombre']} {$usuario['apellido']}"];
    }

    private function buscarUsuario($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.nombre, u.apellido, r.nombre AS rol,
                   (SELECT COUNT(*) FROM torneo_organizadores o WHERE o.usuario_id = u.id) AS organiza,
                   (SELECT COUNT(*) FROM participantes p WHERE p.usuario_id = u.id) AS inscripciones,
                   (SELECT COUNT(*) FROM equipo_miembros m WHERE m.usuario_id = u.id) AS equipos
            FROM usuarios u
            LEFT JOIN usuario_roles ur ON ur.usuario_id = u.id
            LEFT JOIN roles r ON r.id = ur.rol_id
            WHERE u.id = ?
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetch();
    }

    // ===== Historial de cambios (RF-63 a RF-66) =====

    // Tablas que aparecen en la auditoría, para el filtro
    public function tablasAuditadas() {
        return $this->pdo->query('SELECT DISTINCT tabla_afectada FROM auditoria ORDER BY tabla_afectada')->fetchAll(PDO::FETCH_COLUMN);
    }

    // Cambios registrados, los más nuevos primero. Para los registros que pertenecen a un
    // torneo se busca cuál es, así el historial se puede leer sin ir a la base.
    public function listarAuditoria($filtros, $pagina) {
        [$where, $params] = $this->filtroAuditoria($filtros);
        $stmt = $this->pdo->prepare("
            SELECT a.id, a.accion, a.tabla_afectada, a.registro_id, a.fecha, a.usuario_id,
                   CONCAT(u.nombre, ' ', u.apellido) AS usuario, u.email,
                   COALESCE(t1.id, t2.id, t3.id, t4.id) AS torneo_id,
                   COALESCE(t1.nombre, t2.nombre, t3.nombre, t4.nombre) AS torneo
            FROM auditoria a
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            -- torneos y torneo_organizadores guardan el id del torneo
            LEFT JOIN torneos t1 ON a.tabla_afectada IN ('torneos', 'torneo_organizadores') AND t1.id = a.registro_id
            LEFT JOIN rondas ro ON a.tabla_afectada = 'rondas' AND ro.id = a.registro_id
            LEFT JOIN torneos t2 ON t2.id = ro.torneo_id
            LEFT JOIN participantes pa ON a.tabla_afectada = 'participantes' AND pa.id = a.registro_id
            LEFT JOIN torneos t3 ON t3.id = pa.torneo_id
            LEFT JOIN resultados re ON a.tabla_afectada = 'resultados' AND re.id = a.registro_id
            LEFT JOIN enfrentamientos en ON en.id = re.enfrentamiento_id
            LEFT JOIN rondas ro2 ON ro2.id = en.ronda_id
            LEFT JOIN torneos t4 ON t4.id = ro2.torneo_id
            WHERE $where
            ORDER BY a.fecha DESC, a.id DESC
            LIMIT " . self::POR_PAGINA . ' OFFSET ' . (($pagina - 1) * self::POR_PAGINA)
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function contarAuditoria($filtros) {
        [$where, $params] = $this->filtroAuditoria($filtros);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function filtroAuditoria($filtros) {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($filtros['accion'] ?? '', ['INSERT', 'UPDATE', 'DELETE'], true)) {
            $where[] = 'a.accion = ?';
            $params[] = $filtros['accion'];
        }
        if (($filtros['tabla'] ?? '') !== '') {
            $where[] = 'a.tabla_afectada = ?';
            $params[] = $filtros['tabla'];
        }
        if (trim($filtros['usuario'] ?? '') !== '') {
            if (trim($filtros['usuario']) === 'sistema') {
                $where[] = 'a.usuario_id IS NULL';
            } else {
                $where[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE ? OR u.email LIKE ?)";
                $texto = '%' . trim($filtros['usuario']) . '%';
                array_push($params, $texto, $texto);
            }
        }
        foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
            $fecha = DateTime::createFromFormat('!Y-m-d', $filtros[$campo] ?? '');
            if ($fecha && $fecha->format('Y-m-d') === $filtros[$campo]) {
                $where[] = "DATE(a.fecha) $operador ?";
                $params[] = $filtros[$campo];
            }
        }
        return [implode(' AND ', $where), $params];
    }

    private function auditar($usuario_id, $accion, $tabla, $registro_id) {
        $stmt = $this->pdo->prepare("
            INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $accion, $tabla, $registro_id]);
    }
}
