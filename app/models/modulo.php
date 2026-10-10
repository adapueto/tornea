<?php

require_once __DIR__ . '/../../config/database.php';

// Módulos de competencia (RF-59, RF-60): cada tipo de torneo (liga, eliminación
// directa, sistema suizo) es un módulo que el administrador puede deshabilitar.
// Un módulo deshabilitado no deja crear torneos nuevos de ese tipo; los torneos
// que ya existen siguen funcionando igual, para no cortar competencias en juego.
class Modulo {
    private $pdo;

    public function __construct() {
        $this->pdo = conectar();
    }

    // Todos los módulos, con cuántos torneos de cada tipo hay
    public function listar() {
        return $this->pdo->query("
            SELECT m.id, m.codigo, m.nombre, m.descripcion, m.habilitado,
                   (SELECT COUNT(*) FROM torneos t WHERE t.tipo = m.codigo) AS torneos,
                   (SELECT COUNT(*) FROM torneos t WHERE t.tipo = m.codigo AND t.estado IN ('publicado', 'en_curso')) AS activos
            FROM modulos m
            ORDER BY m.id
        ")->fetchAll();
    }

    // Códigos de los módulos habilitados: ['liga', 'suizo']
    public function codigosHabilitados() {
        return $this->pdo->query('SELECT codigo FROM modulos WHERE habilitado = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function estaHabilitado($codigo) {
        return in_array($codigo, $this->codigosHabilitados(), true);
    }

    // Habilita o deshabilita un módulo. Siempre tiene que quedar al menos uno
    // habilitado: si no, nadie podría crear torneos.
    public function cambiar($codigo, $habilitar, $admin_id) {
        $stmt = $this->pdo->prepare('SELECT * FROM modulos WHERE codigo = ?');
        $stmt->execute([$codigo]);
        $modulo = $stmt->fetch();
        if (!$modulo) {
            return ['exito' => false, 'mensaje' => 'El módulo no existe'];
        }
        if ((bool) $modulo['habilitado'] === $habilitar) {
            return ['exito' => false, 'mensaje' => "{$modulo['nombre']} ya estaba " . ($habilitar ? 'habilitado' : 'deshabilitado')];
        }
        if (!$habilitar && count($this->codigosHabilitados()) <= 1) {
            return ['exito' => false, 'mensaje' => 'Tiene que quedar al menos un tipo de torneo habilitado: si no, nadie podría crear torneos'];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE modulos SET habilitado = ? WHERE id = ?');
            $stmt->execute([$habilitar ? 1 : 0, $modulo['id']]);

            $stmt = $this->pdo->prepare("
                INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
                VALUES (?, 'UPDATE', 'modulos', ?)
            ");
            $stmt->execute([$admin_id, $modulo['id']]);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo guardar el cambio, intentá de nuevo'];
        }

        return [
            'exito' => true,
            'mensaje' => $habilitar
                ? "Habilitaste {$modulo['nombre']}: ya se pueden crear torneos de ese tipo"
                : "Deshabilitaste {$modulo['nombre']}: no se pueden crear torneos nuevos de ese tipo. Los que ya existen siguen funcionando.",
        ];
    }
}
