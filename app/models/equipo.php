<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/torneo.php';

// Equipos (RF-13 a RF-17). Cada equipo se arma para un torneo por equipos:
// quien lo crea es el líder, lo inscribe en el torneo e invita a los demás.
// Todo se puede cambiar solo mientras el torneo está publicado (antes de empezar).
class Equipo {
    private $pdo;
    private $torneos;

    public function __construct() {
        $this->pdo = conectar();
        $this->torneos = new Torneo();
    }

    // ===== Consultas =====

    // Equipo con los datos de su torneo y el estado de su inscripción
    public function buscarPorId($equipo_id) {
        $stmt = $this->pdo->prepare("
            SELECT e.*, t.nombre AS torneo_nombre, t.estado AS torneo_estado, t.deporte,
                   p.id AS participante_id, p.estado AS estado_inscripcion,
                   CONCAT(u.nombre, ' ', u.apellido) AS lider_nombre
            FROM equipos e
            JOIN torneos t ON t.id = e.torneo_id
            JOIN usuarios u ON u.id = e.lider_id
            LEFT JOIN participantes p ON p.equipo_id = e.id
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
            SELECT i.id, i.created_at, CONCAT(u.nombre, ' ', u.apellido) AS nombre, u.email
            FROM invitaciones i
            JOIN usuarios u ON u.id = i.usuario_invitado_id
            WHERE i.equipo_id = ? AND i.estado = 'pendiente'
            ORDER BY i.created_at
        ");
        $stmt->execute([$equipo_id]);
        return $stmt->fetchAll();
    }

    // Equipos de los que el usuario es miembro (para la página "Mis equipos")
    public function listarDeUsuario($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT e.id, e.nombre, e.lider_id, t.id AS torneo_id, t.nombre AS torneo_nombre,
                   t.estado AS torneo_estado, p.estado AS estado_inscripcion,
                   (SELECT COUNT(*) FROM equipo_miembros m2 WHERE m2.equipo_id = e.id) AS miembros
            FROM equipo_miembros m
            JOIN equipos e ON e.id = m.equipo_id
            JOIN torneos t ON t.id = e.torneo_id
            LEFT JOIN participantes p ON p.equipo_id = e.id
            WHERE m.usuario_id = ?
            ORDER BY FIELD(t.estado, 'publicado', 'en_curso', 'finalizado', 'borrador'), t.fecha_inicio DESC
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll();
    }

    // Invitaciones pendientes que recibió el usuario
    public function listarInvitacionesDeUsuario($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT i.id, i.created_at, e.id AS equipo_id, e.nombre AS equipo_nombre,
                   t.id AS torneo_id, t.nombre AS torneo_nombre, t.estado AS torneo_estado,
                   CONCAT(u.nombre, ' ', u.apellido) AS lider_nombre
            FROM invitaciones i
            JOIN equipos e ON e.id = i.equipo_id
            JOIN torneos t ON t.id = e.torneo_id
            JOIN usuarios u ON u.id = e.lider_id
            WHERE i.usuario_invitado_id = ? AND i.estado = 'pendiente'
            ORDER BY i.created_at DESC
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll();
    }

    // Equipo del que el usuario es miembro en un torneo, o false
    public function equipoEnTorneo($torneo_id, $usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT e.* FROM equipos e
            JOIN equipo_miembros m ON m.equipo_id = e.id
            WHERE e.torneo_id = ? AND m.usuario_id = ?
        ");
        $stmt->execute([$torneo_id, $usuario_id]);
        return $stmt->fetch();
    }

    public function esMiembro($equipo_id, $usuario_id) {
        $stmt = $this->pdo->prepare('SELECT 1 FROM equipo_miembros WHERE equipo_id = ? AND usuario_id = ?');
        $stmt->execute([$equipo_id, $usuario_id]);
        return (bool) $stmt->fetch();
    }

    // Ven la página del equipo: sus miembros y quienes gestionan el torneo
    public function puedeVer($equipo, $usuario_id) {
        return $this->esMiembro($equipo['id'], $usuario_id)
            || $this->torneos->puedeGestionar($equipo['torneo_id'], $usuario_id);
    }

    // El equipo se puede modificar solo antes de que empiece el torneo
    public static function esModificable($equipo) {
        return $equipo['torneo_estado'] === 'publicado' && $equipo['estado_inscripcion'] !== 'rechazado';
    }

    // ===== Crear el equipo e inscribirlo en el torneo =====

    // Motivo por el que el usuario no puede inscribir un equipo, o null si puede
    public function motivoNoPuedeCrear($torneo, $usuario_id) {
        if ($torneo['estado'] !== 'publicado') {
            return 'La inscripción solo está abierta mientras el torneo está publicado y todavía no empezó.';
        }
        if ($torneo['modalidad'] !== 'equipo') {
            return 'Este torneo es individual.';
        }
        if ($this->torneos->esOrganizador($torneo['id'], $usuario_id)) {
            return 'No te podés inscribir en un torneo que organizás.';
        }
        if ($this->equipoEnTorneo($torneo['id'], $usuario_id)) {
            return 'Ya formás parte de un equipo en este torneo.';
        }
        return null;
    }

    public function crear($torneo_id, $nombre, $usuario_id) {
        $nombre = trim($nombre);
        $torneo = $this->torneos->buscarPorId($torneo_id);
        if (!$torneo || $torneo['estado'] === 'borrador') {
            return ['exito' => false, 'mensaje' => 'El torneo no existe o todavía no fue publicado'];
        }

        $motivo = $this->motivoNoPuedeCrear($torneo, $usuario_id);
        if ($motivo) {
            return ['exito' => false, 'mensaje' => $motivo];
        }
        $error = $this->validarNombre($nombre, $torneo_id);
        if ($error) {
            return ['exito' => false, 'mensaje' => $error];
        }

        // Equipo, líder como primer miembro e inscripción: todo junto o nada
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('INSERT INTO equipos (nombre, torneo_id, lider_id) VALUES (?, ?, ?)');
            $stmt->execute([$nombre, $torneo_id, $usuario_id]);
            $equipo_id = $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare('INSERT INTO equipo_miembros (equipo_id, usuario_id) VALUES (?, ?)');
            $stmt->execute([$equipo_id, $usuario_id]);

            $stmt = $this->pdo->prepare("
                INSERT INTO participantes (torneo_id, equipo_id, tipo, estado)
                VALUES (?, ?, 'equipo', 'pendiente')
            ");
            $stmt->execute([$torneo_id, $equipo_id]);
            $participante_id = $this->pdo->lastInsertId();

            $this->auditar($usuario_id, 'INSERT', 'equipos', $equipo_id);
            $this->auditar($usuario_id, 'INSERT', 'participantes', $participante_id);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo crear el equipo, intentá de nuevo'];
        }

        return [
            'exito' => true,
            'mensaje' => "Creaste el equipo $nombre y quedó inscripto (pendiente de aprobación). Ahora invitá a tus compañeros.",
            'equipo_id' => $equipo_id,
        ];
    }

    public function renombrar($equipo_id, $nombre, $usuario_id) {
        $nombre = trim($nombre);
        $equipo = $this->buscarPorId($equipo_id);
        $error = $this->controlarLider($equipo, $usuario_id) ?: $this->validarNombre($nombre, $equipo['torneo_id'], $equipo_id);
        if ($error) {
            return ['exito' => false, 'mensaje' => $error];
        }

        $stmt = $this->pdo->prepare('UPDATE equipos SET nombre = ? WHERE id = ?');
        $stmt->execute([$nombre, $equipo_id]);
        $this->auditar($usuario_id, 'UPDATE', 'equipos', $equipo_id);

        return ['exito' => true, 'mensaje' => 'Nombre del equipo actualizado'];
    }

    // El líder da de baja el equipo: se borra su inscripción y el equipo con sus miembros e invitaciones
    public function darDeBaja($equipo_id, $usuario_id) {
        $equipo = $this->buscarPorId($equipo_id);
        // Un equipo rechazado también se puede dar de baja, por eso no se usa esModificable
        if (!$equipo || (int) $equipo['lider_id'] !== (int) $usuario_id) {
            return ['exito' => false, 'mensaje' => 'Solo el líder puede dar de baja el equipo'];
        }
        if ($equipo['torneo_estado'] !== 'publicado') {
            return ['exito' => false, 'mensaje' => 'El torneo ya empezó: el equipo no se puede dar de baja'];
        }

        $this->pdo->beginTransaction();
        try {
            // participantes.equipo_id es ON DELETE SET NULL: la inscripción se borra antes a mano
            $stmt = $this->pdo->prepare('DELETE FROM participantes WHERE equipo_id = ?');
            $stmt->execute([$equipo_id]);
            $stmt = $this->pdo->prepare('DELETE FROM equipos WHERE id = ?');
            $stmt->execute([$equipo_id]);

            if ($equipo['participante_id']) {
                $this->auditar($usuario_id, 'DELETE', 'participantes', $equipo['participante_id']);
            }
            $this->auditar($usuario_id, 'DELETE', 'equipos', $equipo_id);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo dar de baja el equipo, intentá de nuevo'];
        }

        return ['exito' => true, 'mensaje' => "Diste de baja el equipo {$equipo['nombre']}", 'torneo_id' => $equipo['torneo_id']];
    }

    // ===== Invitaciones =====

    public function invitar($equipo_id, $email, $usuario_id) {
        $equipo = $this->buscarPorId($equipo_id);
        $error = $this->controlarLider($equipo, $usuario_id);
        if ($error) {
            return ['exito' => false, 'mensaje' => $error];
        }

        $stmt = $this->pdo->prepare("SELECT id, CONCAT(nombre, ' ', apellido) AS nombre FROM usuarios WHERE email = ?");
        $stmt->execute([trim($email)]);
        $invitado = $stmt->fetch();
        if (!$invitado) {
            return ['exito' => false, 'mensaje' => 'No hay ningún usuario registrado con ese email. Pedile que se registre en Tornea y volvé a invitarlo.'];
        }

        $motivo = $this->motivoNoPuedeUnirse($equipo, $invitado['id']);
        if ($motivo) {
            return ['exito' => false, 'mensaje' => $motivo];
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

    // El invitado acepta o rechaza
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
            if (!self::esModificable($equipo)) {
                return ['exito' => false, 'mensaje' => 'El torneo ya empezó o el equipo fue rechazado: ya no te podés sumar'];
            }
            $motivo = $this->motivoNoPuedeUnirse($equipo, $usuario_id);
            if ($motivo) {
                return ['exito' => false, 'mensaje' => $motivo];
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

    // El líder retira una invitación que todavía no fue respondida
    public function cancelarInvitacion($invitacion_id, $usuario_id) {
        $invitacion = $this->buscarInvitacion($invitacion_id);
        if (!$invitacion) {
            return ['exito' => false, 'mensaje' => 'Esa invitación no existe'];
        }
        $error = $this->controlarLider($this->buscarPorId($invitacion['equipo_id']), $usuario_id);
        if ($error) {
            return ['exito' => false, 'mensaje' => $error];
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

    // El líder saca a un miembro
    public function quitarMiembro($equipo_id, $miembro_id, $usuario_id) {
        $equipo = $this->buscarPorId($equipo_id);
        $error = $this->controlarLider($equipo, $usuario_id);
        if ($error) {
            return ['exito' => false, 'mensaje' => $error];
        }
        if ((int) $miembro_id === (int) $usuario_id) {
            return ['exito' => false, 'mensaje' => 'Sos el líder: si no querés seguir, das de baja el equipo'];
        }
        return $this->borrarMiembro($equipo_id, $miembro_id, $usuario_id, 'Sacaste al miembro del equipo');
    }

    // Un miembro (que no es el líder) se va del equipo
    public function salir($equipo_id, $usuario_id) {
        $equipo = $this->buscarPorId($equipo_id);
        if (!$equipo || !$this->esMiembro($equipo_id, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'No sos parte de ese equipo'];
        }
        if ((int) $equipo['lider_id'] === (int) $usuario_id) {
            return ['exito' => false, 'mensaje' => 'Sos el líder: si no querés seguir, das de baja el equipo'];
        }
        if ($equipo['torneo_estado'] !== 'publicado') {
            return ['exito' => false, 'mensaje' => 'El torneo ya empezó: no podés salir del equipo'];
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

    // Motivo por el que un usuario no puede sumarse a un equipo, o null si puede
    private function motivoNoPuedeUnirse($equipo, $usuario_id) {
        if ($this->esMiembro($equipo['id'], $usuario_id)) {
            return 'Esa persona ya es parte del equipo';
        }
        if ($this->equipoEnTorneo($equipo['torneo_id'], $usuario_id)) {
            return 'Esa persona ya está en otro equipo de este torneo';
        }
        if ($this->torneos->esOrganizador($equipo['torneo_id'], $usuario_id)) {
            return 'Los organizadores del torneo no pueden jugar en él';
        }
        return null;
    }

    // Error si el usuario no es el líder o el equipo ya no se puede modificar, o null
    private function controlarLider($equipo, $usuario_id) {
        if (!$equipo || (int) $equipo['lider_id'] !== (int) $usuario_id) {
            return 'Solo el líder del equipo puede hacer esto';
        }
        if (!self::esModificable($equipo)) {
            return 'El torneo ya empezó o el equipo fue rechazado: el equipo ya no se puede modificar';
        }
        return null;
    }

    private function validarNombre($nombre, $torneo_id, $sin_equipo_id = 0) {
        if ($nombre === '') {
            return 'Escribí un nombre para el equipo';
        }
        if (mb_strlen($nombre) > 150) {
            return 'El nombre del equipo no puede tener más de 150 caracteres';
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM equipos WHERE torneo_id = ? AND nombre = ? AND id <> ?');
        $stmt->execute([$torneo_id, $nombre, $sin_equipo_id]);
        if ($stmt->fetch()) {
            return 'Ya hay un equipo con ese nombre en este torneo: elegí otro';
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
