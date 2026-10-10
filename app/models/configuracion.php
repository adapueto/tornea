<?php

require_once __DIR__ . '/../../config/database.php';

// Configuración general del sistema (RF-62): valores que el administrador ajusta
// desde el panel, en lugar de estar fijos en el código.
class Configuracion {
    // Cada valor con su texto para el formulario, el valor por defecto y su rango válido
    const VALORES = [
        'puntos_victoria' => [
            'nombre' => 'Puntos por victoria',
            'ayuda' => 'Lo que suma quien gana un partido en liga y sistema suizo (también el pase libre del suizo).',
            'defecto' => 3, 'min' => 1, 'max' => 10,
        ],
        'puntos_empate' => [
            'nombre' => 'Puntos por empate',
            'ayuda' => 'Lo que suma cada uno cuando empatan. Tiene que ser menos que una victoria.',
            'defecto' => 1, 'min' => 0, 'max' => 9,
        ],
        'minimo_participantes' => [
            'nombre' => 'Mínimo de participantes',
            'ayuda' => 'Cuántos inscriptos aprobados necesita un torneo para empezar y armar sus rondas.',
            'defecto' => 2, 'min' => 2, 'max' => 64,
        ],
    ];

    // Se lee una sola vez por pedido
    private static $cache = null;

    private $pdo;

    public function __construct() {
        $this->pdo = conectar();
    }

    // Todos los valores: ['puntos_victoria' => 3, ...]. Si falta alguno en la base, vale el de por defecto.
    public function todos() {
        if (self::$cache === null) {
            $guardados = $this->pdo->query('SELECT clave, valor FROM configuracion')->fetchAll(PDO::FETCH_KEY_PAIR);
            self::$cache = [];
            foreach (self::VALORES as $clave => $def) {
                self::$cache[$clave] = isset($guardados[$clave]) ? (int) $guardados[$clave] : $def['defecto'];
            }
        }
        return self::$cache;
    }

    public static function valor($clave) {
        return (new self())->todos()[$clave];
    }

    // Guarda los valores del formulario. Devuelve también si cambiaron los puntos,
    // para que se recalculen las tablas de los torneos en curso.
    public function guardar($datos, $admin_id) {
        $nuevos = [];
        $errores = [];
        foreach (self::VALORES as $clave => $def) {
            $texto = trim((string) ($datos[$clave] ?? ''));
            if (!preg_match('/^\d{1,3}$/', $texto) || (int) $texto < $def['min'] || (int) $texto > $def['max']) {
                $errores[] = "{$def['nombre']}: tiene que ser un número entero entre {$def['min']} y {$def['max']}";
                continue;
            }
            $nuevos[$clave] = (int) $texto;
        }
        if (!$errores && $nuevos['puntos_empate'] >= $nuevos['puntos_victoria']) {
            $errores[] = 'Un empate tiene que valer menos puntos que una victoria';
        }
        if ($errores) {
            return ['exito' => false, 'mensaje' => implode('. ', $errores)];
        }

        $actuales = $this->todos();
        $cambios = array_keys(array_diff_assoc($nuevos, $actuales));
        if (!$cambios) {
            return ['exito' => false, 'mensaje' => 'No cambiaste ningún valor'];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO configuracion (clave, valor) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)
            ');
            $auditoria = $this->pdo->prepare("
                INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
                SELECT ?, 'UPDATE', 'configuracion', id FROM configuracion WHERE clave = ?
            ");
            foreach ($cambios as $clave) {
                $stmt->execute([$clave, $nuevos[$clave]]);
                $auditoria->execute([$admin_id, $clave]);
            }
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo guardar la configuración, intentá de nuevo'];
        }
        self::$cache = null;

        $nombres = array_map(function ($clave) {
            return mb_strtolower(self::VALORES[$clave]['nombre']);
        }, $cambios);
        return [
            'exito' => true,
            'mensaje' => 'Guardaste los cambios: ' . implode(', ', $nombres),
            'cambiaron_puntos' => (bool) array_intersect($cambios, ['puntos_victoria', 'puntos_empate']),
        ];
    }
}
