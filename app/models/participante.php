<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/torneo.php';

// Inscripciones de participantes en torneos (tabla participantes, RF-12).
// Por ahora solo inscripción individual; la de equipos se agrega después.
class Participante {
    private $pdo;
    private $torneos;

    public function __construct() {
        $this->pdo = conectar();
        $this->torneos = new Torneo();
    }

    // Inscripción de un usuario en un torneo, o false si no está anotado
    public function buscarInscripcion($torneo_id, $usuario_id) {
        $stmt = $this->pdo->prepare('SELECT * FROM participantes WHERE torneo_id = ? AND usuario_id = ?');
        $stmt->execute([$torneo_id, $usuario_id]);
        return $stmt->fetch();
    }

    // Motivo por el que un usuario no se puede inscribir, o null si puede
    public function motivoNoPuedeInscribirse($torneo, $usuario_id) {
        if ($torneo['estado'] !== 'publicado') {
            return 'La inscripción solo está abierta mientras el torneo está publicado y todavía no empezó.';
        }
        if ($torneo['modalidad'] !== 'individual') {
            return 'Este torneo es por equipos: la inscripción de equipos todavía no está disponible.';
        }
        if ($this->torneos->esOrganizador($torneo['id'], $usuario_id)) {
            return 'No te podés inscribir en un torneo que organizás.';
        }
        if ($this->buscarInscripcion($torneo['id'], $usuario_id)) {
            return 'Ya estás inscripto en este torneo.';
        }
        return null;
    }

    public function inscribir($torneo_id, $usuario_id) {
        $torneo = $this->torneos->buscarPorId($torneo_id);
        if (!$torneo || $torneo['estado'] === 'borrador') {
            return ['exito' => false, 'mensaje' => 'El torneo no existe o todavía no fue publicado'];
        }

        $motivo = $this->motivoNoPuedeInscribirse($torneo, $usuario_id);
        if ($motivo) {
            return ['exito' => false, 'mensaje' => $motivo];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO participantes (torneo_id, usuario_id, tipo, estado)
                VALUES (?, ?, 'individual', 'pendiente')
            ");
            $stmt->execute([$torneo_id, $usuario_id]);
            $id = $this->pdo->lastInsertId();

            $this->auditar($usuario_id, 'INSERT', $id);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            // 23000: violación del índice único (dos clics seguidos, por ejemplo)
            $mensaje = $e->getCode() === '23000'
                ? 'Ya estás inscripto en este torneo.'
                : 'No se pudo completar la inscripción, intentá de nuevo';
            return ['exito' => false, 'mensaje' => $mensaje];
        }

        return ['exito' => true, 'mensaje' => 'Te inscribiste. Tu inscripción queda pendiente hasta que el organizador la apruebe.'];
    }

    // El propio participante se baja del torneo mientras todavía no empezó
    public function cancelar($torneo_id, $usuario_id) {
        $torneo = $this->torneos->buscarPorId($torneo_id);
        $inscripcion = $this->buscarInscripcion($torneo_id, $usuario_id);

        if (!$torneo || !$inscripcion) {
            return ['exito' => false, 'mensaje' => 'No estás inscripto en este torneo'];
        }
        if ($torneo['estado'] !== 'publicado') {
            return ['exito' => false, 'mensaje' => 'El torneo ya empezó: la inscripción no se puede cancelar'];
        }
        if ($inscripcion['estado'] === 'rechazado') {
            return ['exito' => false, 'mensaje' => 'Tu inscripción ya fue rechazada'];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM participantes WHERE id = ?');
            $stmt->execute([$inscripcion['id']]);

            $this->auditar($usuario_id, 'DELETE', $inscripcion['id']);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo cancelar la inscripción, intentá de nuevo'];
        }

        return ['exito' => true, 'mensaje' => 'Cancelaste tu inscripción'];
    }

    // El organizador (o un admin) aprueba o rechaza una inscripción pendiente
    public function resolver($participante_id, $nuevo_estado, $usuario_id) {
        if (!in_array($nuevo_estado, ['aprobado', 'rechazado'], true)) {
            return ['exito' => false, 'mensaje' => 'Acción no válida'];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM participantes WHERE id = ?');
        $stmt->execute([$participante_id]);
        $inscripcion = $stmt->fetch();
        if (!$inscripcion) {
            return ['exito' => false, 'mensaje' => 'La inscripción no existe'];
        }

        if (!$this->torneos->puedeGestionar($inscripcion['torneo_id'], $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo un organizador del torneo o un administrador puede revisar inscripciones'];
        }

        $torneo = $this->torneos->buscarPorId($inscripcion['torneo_id']);
        if ($torneo['estado'] !== 'publicado') {
            return ['exito' => false, 'mensaje' => 'El torneo ya empezó: las inscripciones ya no se pueden cambiar'];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE participantes SET estado = ? WHERE id = ? AND estado = 'pendiente'");
            $stmt->execute([$nuevo_estado, $participante_id]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return ['exito' => false, 'mensaje' => 'Esa inscripción ya había sido revisada'];
            }

            $this->auditar($usuario_id, 'UPDATE', $participante_id);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo guardar la decisión, intentá de nuevo'];
        }

        $nombre = $this->nombreParticipante($participante_id);
        return [
            'exito' => true,
            'mensaje' => $nuevo_estado === 'aprobado' ? "Aprobaste la inscripción de $nombre" : "Rechazaste la inscripción de $nombre",
            'torneo_id' => $inscripcion['torneo_id'],
        ];
    }

    // Torneo al que pertenece una inscripción (para volver al detalle)
    public function torneoDeInscripcion($participante_id) {
        $stmt = $this->pdo->prepare('SELECT torneo_id FROM participantes WHERE id = ?');
        $stmt->execute([$participante_id]);
        return $stmt->fetchColumn();
    }

    // Inscripciones de un torneo con un estado dado, las más viejas primero
    public function listarPorEstado($torneo_id, $estado) {
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.created_at,
                   COALESCE(e.nombre, CONCAT(u.nombre, ' ', u.apellido)) AS nombre
            FROM participantes p
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            LEFT JOIN equipos e ON e.id = p.equipo_id
            WHERE p.torneo_id = ? AND p.estado = ?
            ORDER BY p.created_at, p.id
        ");
        $stmt->execute([$torneo_id, $estado]);
        return $stmt->fetchAll();
    }

    // Cantidad de inscripciones por estado: ['pendiente' => 2, 'aprobado' => 5, 'rechazado' => 0]
    public function contarPorEstado($torneo_id) {
        $stmt = $this->pdo->prepare('SELECT estado, COUNT(*) FROM participantes WHERE torneo_id = ? GROUP BY estado');
        $stmt->execute([$torneo_id]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) + ['pendiente' => 0, 'aprobado' => 0, 'rechazado' => 0];
    }

    private function nombreParticipante($participante_id) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(e.nombre, CONCAT(u.nombre, ' ', u.apellido))
            FROM participantes p
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            LEFT JOIN equipos e ON e.id = p.equipo_id
            WHERE p.id = ?
        ");
        $stmt->execute([$participante_id]);
        return $stmt->fetchColumn();
    }

    private function auditar($usuario_id, $accion, $participante_id) {
        $stmt = $this->pdo->prepare("
            INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
            VALUES (?, ?, 'participantes', ?)
        ");
        $stmt->execute([$usuario_id, $accion, $participante_id]);
    }
}
