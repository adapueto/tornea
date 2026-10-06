<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/torneo.php';

// Equipos permanentes (RF-13 a RF-17). Un equipo se arma una sola vez: quien lo
// crea es el líder e invita a los demás, que aceptan una sola vez. Después el
// líder lo inscribe en todos los torneos por equipos que quiera (cada inscripción
// es una fila de participantes con equipo_id), sin volver a invitar a nadie.
//
// Reglas que se controlan siempre:
// - Nadie juega en dos equipos del mismo torneo.
// - Los organizadores de un torneo no juegan en él.
class Equipo {
    private $pdo;
    private $torneos;

    public function __construct() {
        $this->pdo = conectar();
        $this->torneos = new Torneo();
    }

    // ===== Consultas =====

    public function buscarPorId($equipo_id) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, CONCAT(u.nombre, ' ', u.apellido) AS lider_nombre
            FROM equipos e
            JOIN usuarios u ON u.id = e.lider_id
            WHERE e.id = ?
        ");
        $stmt->execute([$equipo_id]);
        return $stmt->fetch();
    }

    public function listarMiembros($equipo_id) {
        $stmt = $this->pdo->prepare("
            SELECT u.id, CONCAT(u.nombre, ' ', u.apellido) AS nombre, (u.id = e.lider_id) AS es_lider
            FROM equipo_miembros m
            JOIN usuarios u ON u.id = m.usuario_id
            JOIN equipos e ON e.id = m.equipo_id
            WHERE m.equipo_id = ?
            ORDER BY es_lider DESC, u.nombre, u.apellido
        ");
        $stmt->execute([$equipo_id]);
        return $stmt->fetchAll();
    }

    public function listarInvitacionesPendientes($equipo_id) {
        $stmt = $this->pdo->prepare("
            SELECT i.id, i.created_at, CONCAT(u.nombre, ' ', u.apellido) AS nombre
            FROM invitaciones i
            JOIN usuarios u ON u.id = i.usuario_invitado_id
            WHERE i.equipo_id = ? AND i.estado = 'pendiente'
            ORDER BY i.created_at
        ");
        $stmt->execute([$equipo_id]);
        return $stmt->fetchAll();
    }

    // Torneos en los que está inscripto el equipo, los activos primero
    public function listarInscripciones($equipo_id) {
        $stmt = $this->pdo->prepare("
            SELECT t.id AS torneo_id, t.nombre AS torneo_nombre, t.estado AS torneo_estado,
                   t.fecha_inicio, t.fecha_fin, p.id AS participante_id, p.estado AS estado_inscripcion
            FROM participantes p
            JOIN torneos t ON t.id = p.torneo_id
            WHERE p.equipo_id = ?
            ORDER BY FIELD(t.estado, 'publicado', 'en_curso', 'finalizado', 'borrador'), t.fecha_inicio DESC
        ");
        $stmt->execute([$equipo_id]);
        return $stmt->fetchAll();
    }

    // Equipos de los que el usuario es miembro (página "Mis equipos")
    public function listarDeUsuario($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT e.id, e.nombre, e.lider_id,
                   (SELECT COUNT(*) FROM equipo_miembros m2 WHERE m2.equipo_id = e.id) AS miembros,
                   (SELECT COUNT(*) FROM participantes p JOIN torneos t ON t.id = p.torneo_id
                    WHERE p.equipo_id = e.id AND p.estado <> 'rechazado'
                      AND t.estado IN ('publicado', 'en_curso')) AS torneos_activos
            FROM equipo_miembros m
            JOIN equipos e ON e.id = m.equipo_id
            WHERE m.usuario_id = ?
            ORDER BY (e.lider_id = ?) DESC, e.nombre
        ");
        $stmt->execute([$usuario_id, $usuario_id]);
        return $stmt->fetchAll();
    }

    // Equipos que lidera el usuario (para elegir cuál inscribir en un torneo)
    public function listarComoLider($usuario_id) {
        $stmt = $this->pdo->prepare('SELECT id, nombre FROM equipos WHERE lider_id = ? ORDER BY nombre');
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll();
    }

    public function listarInvitacionesDeUsuario($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT i.id, i.created_at, e.id AS equipo_id, e.nombre AS equipo_nombre,
                   CONCAT(u.nombre, ' ', u.apellido) AS lider_nombre,
                   (SELECT COUNT(*) FROM equipo_miembros m WHERE m.equipo_id = e.id) AS miembros
            FROM invitaciones i
            JOIN equipos e ON e.id = i.equipo_id
            JOIN usuarios u ON u.id = e.lider_id
            WHERE i.usuario_invitado_id = ? AND i.estado = 'pendiente'
            ORDER BY i.created_at DESC
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll();
    }

    // Para el aviso del menú: cuántas invitaciones tiene sin responder
    public function contarInvitacionesPendientes($usuario_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM invitaciones WHERE usuario_invitado_id = ? AND estado = 'pendiente'");
        $stmt->execute([$usuario_id]);
        return (int) $stmt->fetchColumn();
    }

    // Equipo del usuario inscripto en un torneo (con el estado de esa inscripción), o false.
    // Si tuviera uno rechazado y otro no, se devuelve primero el que sigue en carrera.
    public function equipoInscriptoDelUsuario($torneo_id, $usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, p.estado AS estado_inscripcion
            FROM participantes p
            JOIN equipos e ON e.id = p.equipo_id
            JOIN equipo_miembros m ON m.equipo_id = e.id
            WHERE p.torneo_id = ? AND m.usuario_id = ?
            ORDER BY p.estado = 'rechazado'
            LIMIT 1
        ");
        $stmt->execute([$torneo_id, $usuario_id]);
        return $stmt->fetch();
    }

    public function esMiembro($equipo_id, $usuario_id) {
        $stmt = $this->pdo->prepare('SELECT 1 FROM equipo_miembros WHERE equipo_id = ? AND usuario_id = ?');
        $stmt->execute([$equipo_id, $usuario_id]);
        return (bool) $stmt->fetch();
    }

    public static function esLider($equipo, $usuario_id) {
        return $equipo && (int) $equipo['lider_id'] === (int) $usuario_id;
    }

    // Ven la página del equipo: sus miembros, los administradores y los
    // organizadores de los torneos en los que está inscripto
    public function puedeVer($equipo, $usuario_id) {
        if ($this->esMiembro($equipo['id'], $usuario_id) || $this->torneos->esAdmin($usuario_id)) {
            return true;
        }
        $stmt = $this->pdo->prepare('
            SELECT 1 FROM participantes p
            JOIN torneo_organizadores o ON o.torneo_id = p.torneo_id
            WHERE p.equipo_id = ? AND o.usuario_id = ?
        ');
        $stmt->execute([$equipo['id'], $usuario_id]);
        return (bool) $stmt->fetch();
    }

    // ===== Crear, renombrar y dar de baja =====

    public function crear($nombre, $usuario_id) {
        $nombre = trim($nombre);
        $error = $this->validarNombre($nombre, $usuario_id);
        if ($error) {
            return ['exito' => false, 'mensaje' => $error];
        }

        // El equipo y su líder como primer miembro: todo junto o nada
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('INSERT INTO equipos (nombre, lider_id) VALUES (?, ?)');
            $stmt->execute([$nombre, $usuario_id]);
            $equipo_id = $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare('INSERT INTO equipo_miembros (equipo_id, usuario_id) VALUES (?, ?)');
            $stmt->execute([$equipo_id, $usuario_id]);

            $this->auditar($usuario_id, 'INSERT', 'equipos', $equipo_id);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo crear el equipo, intentá de nuevo'];
        }

        return [
            'exito' => true,
            'mensaje' => "Creaste el equipo $nombre. Ahora invitá a tus compañeros y después inscribilo en un torneo.",
            'equipo_id' => $equipo_id,
        ];
    }

    public function renombrar($equipo_id, $nombre, $usuario_id) {
        $nombre = trim($nombre);
        $equipo = $this->buscarPorId($equipo_id);
        if (!self::esLider($equipo, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo el líder del equipo puede cambiar el nombre'];
        }
        $error = $this->validarNombre($nombre, $usuario_id, $equipo_id);
        if (!$error) {
            // Dos equipos con el mismo nombre en un torneo confundirían la tabla
            $stmt = $this->pdo->prepare("
                SELECT t.nombre FROM participantes mio
                JOIN participantes otro ON otro.torneo_id = mio.torneo_id AND otro.equipo_id <> mio.equipo_id
                JOIN equipos e ON e.id = otro.equipo_id
                JOIN torneos t ON t.id = mio.torneo_id
                WHERE mio.equipo_id = ? AND e.nombre = ? AND t.estado <> 'finalizado'
                LIMIT 1
            ");
            $stmt->execute([$equipo_id, $nombre]);
            $torneo = $stmt->fetchColumn();
            if ($torneo) {
                $error = "Ya hay un equipo llamado $nombre en el torneo $torneo: elegí otro nombre";
            }
        }
        if ($error) {
            return ['exito' => false, 'mensaje' => $error];
        }

        $stmt = $this->pdo->prepare('UPDATE equipos SET nombre = ? WHERE id = ?');
        $stmt->execute([$nombre, $equipo_id]);
        $this->auditar($usuario_id, 'UPDATE', 'equipos', $equipo_id);

        return ['exito' => true, 'mensaje' => 'Nombre del equipo actualizado'];
    }

    // El líder borra el equipo. Si ya jugó (torneos en curso o terminados) no se
    // puede, para no perder el historial de resultados; las inscripciones en
    // torneos que todavía no empezaron se borran junto con el equipo.
    public function darDeBaja($equipo_id, $usuario_id) {
        $equipo = $this->buscarPorId($equipo_id);
        if (!self::esLider($equipo, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo el líder puede dar de baja el equipo'];
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM participantes p JOIN torneos t ON t.id = p.torneo_id
            WHERE p.equipo_id = ? AND t.estado IN ('en_curso', 'finalizado') AND p.estado = 'aprobado'
        ");
        $stmt->execute([$equipo_id]);
        if ($stmt->fetchColumn() > 0) {
            return ['exito' => false, 'mensaje' => 'El equipo ya jugó torneos: no se puede borrar porque se perderían sus resultados'];
        }

        $this->pdo->beginTransaction();
        try {
            // participantes.equipo_id es ON DELETE SET NULL: las inscripciones se borran antes a mano
            $stmt = $this->pdo->prepare('SELECT id FROM participantes WHERE equipo_id = ?');
            $stmt->execute([$equipo_id]);
            $inscripciones = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $this->pdo->prepare('DELETE FROM participantes WHERE equipo_id = ?');
            $stmt->execute([$equipo_id]);
            $stmt = $this->pdo->prepare('DELETE FROM equipos WHERE id = ?');
            $stmt->execute([$equipo_id]);

            foreach ($inscripciones as $participante_id) {
                $this->auditar($usuario_id, 'DELETE', 'participantes', $participante_id);
            }
            $this->auditar($usuario_id, 'DELETE', 'equipos', $equipo_id);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo dar de baja el equipo, intentá de nuevo'];
        }

        return ['exito' => true, 'mensaje' => "Diste de baja el equipo {$equipo['nombre']}"];
    }

    // ===== Inscripción en torneos =====

    // Motivo por el que el equipo no se puede inscribir en el torneo, o null si puede
    public function motivoNoPuedeInscribir($equipo_id, $torneo) {
        if ($torneo['estado'] !== 'publicado') {
            return 'La inscripción solo está abierta mientras el torneo está publicado y todavía no empezó.';
        }
        if ($torneo['modalidad'] !== 'equipo') {
            return 'Este torneo es individual.';
        }

        $stmt = $this->pdo->prepare('SELECT 1 FROM participantes WHERE torneo_id = ? AND equipo_id = ?');
        $stmt->execute([$torneo['id'], $equipo_id]);
        if ($stmt->fetch()) {
            return 'El equipo ya está inscripto en este torneo.';
        }

        $equipo = $this->buscarPorId($equipo_id);
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM participantes p JOIN equipos e ON e.id = p.equipo_id
            WHERE p.torneo_id = ? AND e.nombre = ? AND p.estado <> 'rechazado'
        ");
        $stmt->execute([$torneo['id'], $equipo['nombre']]);
        if ($stmt->fetch()) {
            return "Ya hay otro equipo llamado {$equipo['nombre']} en este torneo: cambiale el nombre al tuyo para inscribirlo.";
        }

        foreach ($this->listarMiembros($equipo_id) as $miembro) {
            $choque = $this->choqueEnTorneo($miembro['id'], $torneo['id'], $equipo_id);
            if ($choque) {
                return self::textoChoque($choque, $miembro['nombre']);
            }
        }
        return null;
    }

    public function inscribir($equipo_id, $torneo_id, $usuario_id) {
        $equipo = $this->buscarPorId($equipo_id);
        if (!self::esLider($equipo, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo el líder puede inscribir al equipo'];
        }
        $torneo = $this->torneos->buscarPorId($torneo_id);
        if (!$torneo || $torneo['estado'] === 'borrador') {
            return ['exito' => false, 'mensaje' => 'El torneo no existe o todavía no fue publicado'];
        }
        $motivo = $this->motivoNoPuedeInscribir($equipo_id, $torneo);
        if ($motivo) {
            return ['exito' => false, 'mensaje' => $motivo];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO participantes (torneo_id, equipo_id, tipo, estado)
                VALUES (?, ?, 'equipo', 'pendiente')
            ");
            $stmt->execute([$torneo_id, $equipo_id]);
            $this->auditar($usuario_id, 'INSERT', 'participantes', $this->pdo->lastInsertId());
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            $mensaje = $e->getCode() === '23000'
                ? 'El equipo ya está inscripto en este torneo.'
                : 'No se pudo inscribir al equipo, intentá de nuevo';
            return ['exito' => false, 'mensaje' => $mensaje];
        }

        return ['exito' => true, 'mensaje' => "Inscribiste a {$equipo['nombre']}. Queda pendiente hasta que el organizador la apruebe."];
    }

    // El líder saca al equipo de un torneo que todavía no empezó
    public function cancelarInscripcion($equipo_id, $torneo_id, $usuario_id) {
        $equipo = $this->buscarPorId($equipo_id);
        if (!self::esLider($equipo, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo el líder puede cancelar la inscripción del equipo'];
        }
        $torneo = $this->torneos->buscarPorId($torneo_id);
        if (!$torneo || $torneo['estado'] !== 'publicado') {
            return ['exito' => false, 'mensaje' => 'El torneo ya empezó: la inscripción no se puede cancelar'];
        }

        $stmt = $this->pdo->prepare('SELECT id FROM participantes WHERE torneo_id = ? AND equipo_id = ?');
        $stmt->execute([$torneo_id, $equipo_id]);
        $participante_id = $stmt->fetchColumn();
        if (!$participante_id) {
            return ['exito' => false, 'mensaje' => 'El equipo no está inscripto en ese torneo'];
        }

        $stmt = $this->pdo->prepare('DELETE FROM participantes WHERE id = ?');
        $stmt->execute([$participante_id]);
        $this->auditar($usuario_id, 'DELETE', 'participantes', $participante_id);

        return ['exito' => true, 'mensaje' => "Sacaste a {$equipo['nombre']} del torneo {$torneo['nombre']}"];
    }

    // ===== Invitaciones =====

    public function invitar($equipo_id, $email, $usuario_id) {
        $equipo = $this->buscarPorId($equipo_id);
        if (!self::esLider($equipo, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo el líder del equipo puede invitar'];
        }

        $stmt = $this->pdo->prepare("SELECT id, CONCAT(nombre, ' ', apellido) AS nombre FROM usuarios WHERE email = ?");
        $stmt->execute([trim($email)]);
        $invitado = $stmt->fetch();
        if (!$invitado) {
            return ['exito' => false, 'mensaje' => 'No hay ningún usuario registrado con ese email. Pedile que se registre en Tornea y volvé a invitarlo.'];
        }

        $choque = $this->motivoNoPuedeUnirse($equipo_id, $invitado['id']);
        if ($choque) {
            return ['exito' => false, 'mensaje' => self::textoChoque($choque, $invitado['nombre'])];
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM invitaciones WHERE equipo_id = ? AND usuario_invitado_id = ? AND estado = 'pendiente'");
        $stmt->execute([$equipo_id, $invitado['id']]);
        if ($stmt->fetch()) {
            return ['exito' => false, 'mensaje' => "{$invitado['nombre']} ya tiene una invitación pendiente para este equipo"];
        }

        $stmt = $this->pdo->prepare('INSERT INTO invitaciones (equipo_id, usuario_invitado_id) VALUES (?, ?)');
        $stmt->execute([$equipo_id, $invitado['id']]);
        $this->auditar($usuario_id, 'INSERT', 'invitaciones', $this->pdo->lastInsertId());

        return ['exito' => true, 'mensaje' => "Invitaste a {$invitado['nombre']}. Le va a aparecer en la sección Equipos."];
    }

    public function responderInvitacion($invitacion_id, $aceptar, $usuario_id) {
        $invitacion = $this->buscarInvitacion($invitacion_id);
        if (!$invitacion || (int) $invitacion['usuario_invitado_id'] !== (int) $usuario_id) {
            return ['exito' => false, 'mensaje' => 'Esa invitación no existe'];
        }
        if ($invitacion['estado'] !== 'pendiente') {
            return ['exito' => false, 'mensaje' => 'Esa invitación ya fue respondida'];
        }

        $equipo = $this->buscarPorId($invitacion['equipo_id']);
        if ($aceptar) {
            $choque = $this->motivoNoPuedeUnirse($equipo['id'], $usuario_id);
            if ($choque) {
                return ['exito' => false, 'mensaje' => self::textoChoque($choque)];
            }
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE invitaciones SET estado = ? WHERE id = ?');
            $stmt->execute([$aceptar ? 'aceptada' : 'rechazada', $invitacion_id]);
            if ($aceptar) {
                $stmt = $this->pdo->prepare('INSERT INTO equipo_miembros (equipo_id, usuario_id) VALUES (?, ?)');
                $stmt->execute([$equipo['id'], $usuario_id]);
            }
            $this->auditar($usuario_id, 'UPDATE', 'invitaciones', $invitacion_id);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo responder la invitación, intentá de nuevo'];
        }

        return [
            'exito' => true,
            'mensaje' => $aceptar ? "Te sumaste al equipo {$equipo['nombre']}" : "Rechazaste la invitación de {$equipo['nombre']}",
            'equipo_id' => $equipo['id'],
        ];
    }

    public function cancelarInvitacion($invitacion_id, $usuario_id) {
        $invitacion = $this->buscarInvitacion($invitacion_id);
        if (!$invitacion || !self::esLider($this->buscarPorId($invitacion['equipo_id']), $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo el líder del equipo puede cancelar invitaciones'];
        }
        if ($invitacion['estado'] !== 'pendiente') {
            return ['exito' => false, 'mensaje' => 'Esa invitación ya fue respondida'];
        }

        $stmt = $this->pdo->prepare('DELETE FROM invitaciones WHERE id = ?');
        $stmt->execute([$invitacion_id]);
        $this->auditar($usuario_id, 'DELETE', 'invitaciones', $invitacion_id);

        return ['exito' => true, 'mensaje' => 'Cancelaste la invitación'];
    }

    // ===== Miembros =====

    public function quitarMiembro($equipo_id, $miembro_id, $usuario_id) {
        $equipo = $this->buscarPorId($equipo_id);
        if (!self::esLider($equipo, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo el líder del equipo puede sacar integrantes'];
        }
        if ((int) $miembro_id === (int) $usuario_id) {
            return ['exito' => false, 'mensaje' => 'Sos el líder: si no querés seguir, das de baja el equipo'];
        }
        return $this->borrarMiembro($equipo_id, $miembro_id, $usuario_id, 'Sacaste al integrante del equipo');
    }

    public function salir($equipo_id, $usuario_id) {
        $equipo = $this->buscarPorId($equipo_id);
        if (!$equipo || !$this->esMiembro($equipo_id, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'No sos parte de ese equipo'];
        }
        if (self::esLider($equipo, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Sos el líder: si no querés seguir, das de baja el equipo'];
        }
        return $this->borrarMiembro($equipo_id, $usuario_id, $usuario_id, "Saliste del equipo {$equipo['nombre']}");
    }

    // ===== Auxiliares =====

    private function borrarMiembro($equipo_id, $miembro_id, $usuario_id, $mensaje) {
        $stmt = $this->pdo->prepare('DELETE FROM equipo_miembros WHERE equipo_id = ? AND usuario_id = ?');
        $stmt->execute([$equipo_id, $miembro_id]);
        if ($stmt->rowCount() === 0) {
            return ['exito' => false, 'mensaje' => 'Esa persona no es parte del equipo'];
        }
        // equipo_miembros no tiene id propio: se registra el equipo afectado
        $this->auditar($usuario_id, 'DELETE', 'equipo_miembros', $equipo_id);
        return ['exito' => true, 'mensaje' => $mensaje];
    }

    // Por qué un usuario no puede sumarse al equipo (ver textoChoque), o null si puede
    private function motivoNoPuedeUnirse($equipo_id, $usuario_id) {
        if ($this->esMiembro($equipo_id, $usuario_id)) {
            return ['tipo' => 'miembro'];
        }
        // Torneos activos del equipo: en ninguno puede chocar con el nuevo integrante
        $stmt = $this->pdo->prepare("
            SELECT p.torneo_id FROM participantes p JOIN torneos t ON t.id = p.torneo_id
            WHERE p.equipo_id = ? AND p.estado <> 'rechazado' AND t.estado IN ('publicado', 'en_curso')
        ");
        $stmt->execute([$equipo_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $torneo_id) {
            $choque = $this->choqueEnTorneo($usuario_id, $torneo_id, $equipo_id);
            if ($choque) {
                return $choque;
            }
        }
        return null;
    }

    // Si el usuario no puede jugar el torneo con este equipo, el motivo; si puede, null
    private function choqueEnTorneo($usuario_id, $torneo_id, $equipo_id) {
        $torneo = $this->torneos->buscarPorId($torneo_id);
        if ($this->torneos->esOrganizador($torneo_id, $usuario_id)) {
            return ['tipo' => 'organiza', 'torneo' => $torneo['nombre']];
        }
        $stmt = $this->pdo->prepare("
            SELECT e.nombre FROM participantes p
            JOIN equipos e ON e.id = p.equipo_id
            JOIN equipo_miembros m ON m.equipo_id = e.id
            WHERE p.torneo_id = ? AND m.usuario_id = ? AND e.id <> ? AND p.estado <> 'rechazado'
        ");
        $stmt->execute([$torneo_id, $usuario_id, $equipo_id]);
        $otro = $stmt->fetchColumn();
        if ($otro) {
            return ['tipo' => 'otro_equipo', 'torneo' => $torneo['nombre'], 'equipo' => $otro];
        }
        return null;
    }

    // Texto del motivo: sobre otra persona ("Ana ya juega...") o hablándole al
    // propio usuario ("Ya jugás...") cuando no se pasa nombre
    private static function textoChoque($choque, $nombre = null) {
        $torneo = $choque['torneo'] ?? '';
        $equipo = $choque['equipo'] ?? '';
        if ($nombre === null) {
            $textos = [
                'miembro' => 'Ya sos parte del equipo',
                'organiza' => "No te podés sumar: organizás el torneo $torneo, en el que está inscripto este equipo",
                'otro_equipo' => "No te podés sumar: ya jugás el torneo $torneo con el equipo $equipo, y este equipo también está inscripto",
            ];
        } else {
            $textos = [
                'miembro' => "$nombre ya es parte del equipo",
                'organiza' => "$nombre organiza el torneo $torneo, así que no puede jugarlo",
                'otro_equipo' => "$nombre ya juega el torneo $torneo con el equipo $equipo",
            ];
        }
        return $textos[$choque['tipo']];
    }

    // Nombre obligatorio, hasta 150 caracteres y distinto de los otros equipos del mismo líder
    private function validarNombre($nombre, $lider_id, $sin_equipo_id = 0) {
        if ($nombre === '') {
            return 'Escribí un nombre para el equipo';
        }
        if (mb_strlen($nombre) > 150) {
            return 'El nombre del equipo no puede tener más de 150 caracteres';
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM equipos WHERE lider_id = ? AND nombre = ? AND id <> ?');
        $stmt->execute([$lider_id, $nombre, $sin_equipo_id]);
        if ($stmt->fetch()) {
            return 'Ya tenés un equipo con ese nombre: elegí otro';
        }
        return null;
    }

    private function buscarInvitacion($invitacion_id) {
        $stmt = $this->pdo->prepare('SELECT * FROM invitaciones WHERE id = ?');
        $stmt->execute([$invitacion_id]);
        return $stmt->fetch();
    }

    private function auditar($usuario_id, $accion, $tabla, $registro_id) {
        $stmt = $this->pdo->prepare('
            INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$usuario_id, $accion, $tabla, $registro_id]);
    }
}
