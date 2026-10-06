<?php

require_once __DIR__ . '/../../config/database.php';

class Torneo {
    private $pdo;

    const TIPOS = ['liga', 'eliminacion', 'suizo'];
    const DEPORTES = ['Fútbol', 'Básquet', 'eSports', 'Ajedrez', 'Pádel', 'Vóley', 'Otro'];

    public function __construct() {
        $this->pdo = conectar();
        $this->actualizarEstadosPorFecha();
    }

    // Un torneo publicado pasa solo a "en curso" cuando llega su fecha de inicio.
    // Se usa la fecha de MySQL (la del sistema). Los borradores no cambian porque
    // todavía no se publicaron, y la finalización la decide el organizador.
    private function actualizarEstadosPorFecha() {
        $stmt = $this->pdo->query("
            SELECT id FROM torneos
            WHERE estado = 'publicado' AND fecha_inicio <= CURDATE()
        ");
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("UPDATE torneos SET estado = 'en_curso' WHERE id IN ($marcas)");
            $stmt->execute($ids);

            // usuario_id NULL: el cambio lo hizo el sistema, no una persona
            $stmt = $this->pdo->prepare("
                INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
                VALUES (NULL, 'UPDATE', 'torneos', ?)
            ");
            foreach ($ids as $id) {
                $stmt->execute([$id]);
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
        }
    }

    // Fecha de hoy según MySQL (la del sistema), la misma que usa actualizarEstadosPorFecha
    public function fechaHoy() {
        return $this->pdo->query('SELECT CURDATE()')->fetchColumn();
    }

    // Devuelve un array con los errores encontrados (vacío si todo está bien)
    public function validar($datos) {
        $errores = [];

        if (trim($datos['nombre']) === '') {
            $errores[] = 'El nombre del torneo es obligatorio';
        } elseif (mb_strlen($datos['nombre']) > 150) {
            $errores[] = 'El nombre no puede tener más de 150 caracteres';
        }

        if (!in_array($datos['deporte'], self::DEPORTES, true)) {
            $errores[] = 'Seleccioná un deporte válido';
        }

        if (!in_array($datos['tipo'], self::TIPOS, true)) {
            $errores[] = 'Seleccioná un tipo de torneo válido';
        }

        // Con '!' las horas quedan en 0 y se comparan solo los días
        $inicio = DateTime::createFromFormat('!Y-m-d', $datos['fecha_inicio']);
        $fin = DateTime::createFromFormat('!Y-m-d', $datos['fecha_fin']);
        $hoy = DateTime::createFromFormat('!Y-m-d', $this->fechaHoy());

        // Si la fecha no existe (ej. 2026-02-31) PHP la corre a otro día: al volver
        // a formatearla no coincide con lo ingresado
        $inicio_ok = $inicio && $inicio->format('Y-m-d') === $datos['fecha_inicio'];
        $fin_ok = $fin && $fin->format('Y-m-d') === $datos['fecha_fin'];

        if (!$inicio_ok || !$fin_ok) {
            $errores[] = 'Las fechas no son válidas';
        } elseif ($inicio < $hoy) {
            $errores[] = 'La fecha de inicio no puede ser anterior a hoy';
        } elseif ($fin < $inicio) {
            $errores[] = 'La fecha de finalización no puede ser anterior a la de inicio';
        }

        return $errores;
    }

    public function crear($datos, $usuario_id) {
        $errores = $this->validar($datos);
        if ($errores) {
            return ['exito' => false, 'mensaje' => implode('. ', $errores)];
        }

        // El torneo, su organizador y la auditoría se guardan juntos o no se guarda nada
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO torneos (nombre, descripcion, deporte, tipo, fecha_inicio, fecha_fin, estado)
                VALUES (?, ?, ?, ?, ?, ?, \'borrador\')
            ');
            $stmt->execute([
                trim($datos['nombre']),
                trim($datos['descripcion']),
                $datos['deporte'],
                $datos['tipo'],
                $datos['fecha_inicio'],
                $datos['fecha_fin'],
            ]);
            $torneo_id = $this->pdo->lastInsertId();

            // Quien crea el torneo queda como organizador
            $stmt = $this->pdo->prepare('
                INSERT INTO torneo_organizadores (torneo_id, usuario_id)
                VALUES (?, ?)
            ');
            $stmt->execute([$torneo_id, $usuario_id]);

            $stmt = $this->pdo->prepare('
                INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
                VALUES (?, \'INSERT\', \'torneos\', ?)
            ');
            $stmt->execute([$usuario_id, $torneo_id]);

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo crear el torneo, intentá de nuevo'];
        }

        return ['exito' => true, 'mensaje' => 'Torneo creado correctamente', 'id' => $torneo_id];
    }

    // Torneos visibles para cualquiera (RF-48): todo menos los borradores.
    // Primero los en curso, después los próximos y al final los finalizados.
    public function listarPublicos() {
        $stmt = $this->pdo->query("
            SELECT * FROM torneos
            WHERE estado <> 'borrador'
            ORDER BY FIELD(estado, 'en_curso', 'publicado', 'finalizado'),
                     CASE WHEN estado = 'finalizado' THEN NULL ELSE fecha_inicio END ASC,
                     fecha_inicio DESC
        ");
        return $stmt->fetchAll();
    }

    // Para el index: los que están en curso o arrancan más pronto
    public function listarDestacados($limite = 3) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM torneos
            WHERE estado IN ('en_curso', 'publicado')
            ORDER BY FIELD(estado, 'en_curso', 'publicado'), fecha_inicio ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, (int) $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Torneos que organiza un usuario, incluidos los borradores
    public function listarPorOrganizador($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT t.* FROM torneos t
            JOIN torneo_organizadores o ON o.torneo_id = t.id
            WHERE o.usuario_id = ?
            ORDER BY FIELD(t.estado, 'borrador', 'en_curso', 'publicado', 'finalizado'), t.created_at DESC
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll();
    }

    // Torneos en los que el usuario está inscripto, solo o con un equipo (RF-54)
    public function listarParticipaciones($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.nombre, t.tipo, t.estado AS estado_torneo,
                   p.estado AS estado_inscripcion, e.nombre AS equipo
            FROM participantes p
            JOIN torneos t ON t.id = p.torneo_id
            LEFT JOIN equipos e ON e.id = p.equipo_id
            WHERE p.usuario_id = ?
               OR p.equipo_id IN (SELECT equipo_id FROM equipo_miembros WHERE usuario_id = ?)
            ORDER BY FIELD(t.estado, 'en_curso', 'publicado', 'finalizado', 'borrador'), t.fecha_inicio DESC
        ");
        $stmt->execute([$usuario_id, $usuario_id]);
        return $stmt->fetchAll();
    }

    public function esOrganizador($torneo_id, $usuario_id) {
        $stmt = $this->pdo->prepare('SELECT 1 FROM torneo_organizadores WHERE torneo_id = ? AND usuario_id = ?');
        $stmt->execute([$torneo_id, $usuario_id]);
        return (bool) $stmt->fetch();
    }

    // Pasa un torneo de borrador a publicado (RF-22). Solo puede hacerlo un organizador.
    public function publicar($torneo_id, $usuario_id) {
        if (!$this->esOrganizador($torneo_id, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo un organizador del torneo puede publicarlo'];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE torneos SET estado = 'publicado' WHERE id = ? AND estado = 'borrador'");
            $stmt->execute([$torneo_id]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return ['exito' => false, 'mensaje' => 'El torneo ya estaba publicado'];
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
                VALUES (?, 'UPDATE', 'torneos', ?)
            ");
            $stmt->execute([$usuario_id, $torneo_id]);

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo publicar el torneo, intentá de nuevo'];
        }

        // Si la fecha de inicio ya llegó, pasa directo a "en curso"
        $this->actualizarEstadosPorFecha();

        return ['exito' => true, 'mensaje' => 'Torneo publicado: ya aparece en el listado de torneos'];
    }

    // Elimina un torneo (RF-20). Solo se permite mientras es borrador, porque
    // después ya puede tener inscriptos. Solo puede hacerlo un organizador.
    public function eliminar($torneo_id, $usuario_id) {
        if (!$this->esOrganizador($torneo_id, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo un organizador del torneo puede eliminarlo'];
        }

        $this->pdo->beginTransaction();
        try {
            // torneo_organizadores se borra solo por el ON DELETE CASCADE
            $stmt = $this->pdo->prepare("DELETE FROM torneos WHERE id = ? AND estado = 'borrador'");
            $stmt->execute([$torneo_id]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return ['exito' => false, 'mensaje' => 'Solo se pueden eliminar torneos en borrador'];
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
                VALUES (?, 'DELETE', 'torneos', ?)
            ");
            $stmt->execute([$usuario_id, $torneo_id]);

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo eliminar el torneo, intentá de nuevo'];
        }

        return ['exito' => true, 'mensaje' => 'Torneo eliminado'];
    }
}
