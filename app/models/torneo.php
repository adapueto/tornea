<?php

require_once __DIR__ . '/../../config/database.php';

class Torneo {
    private $pdo;

    const TIPOS = ['liga', 'eliminacion', 'suizo'];
    const DEPORTES = ['Fútbol', 'Básquet', 'eSports', 'Ajedrez', 'Pádel', 'Vóley', 'Otro'];

    public function __construct() {
        $this->pdo = conectar();
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

        $inicio = DateTime::createFromFormat('Y-m-d', $datos['fecha_inicio']);
        $fin = DateTime::createFromFormat('Y-m-d', $datos['fecha_fin']);

        if (!$inicio || !$fin) {
            $errores[] = 'Las fechas no son válidas';
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
}
