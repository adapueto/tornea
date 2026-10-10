<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/torneo.php';
require_once __DIR__ . '/configuracion.php';

// Rondas, enfrentamientos, resultados y tabla de posiciones (RF-28 a RF-47).
//
// Cómo se arma cada tipo de torneo:
// - Liga: al generar se crea el fixture completo, todos contra todos (método del círculo).
//   Se juega una fecha por vez: al cerrar una fecha se habilita la siguiente.
// - Eliminación directa: la primera ronda se sortea. Si la cantidad de participantes no es
//   potencia de 2, algunos pasan directo. Al cerrar una ronda, los ganadores se cruzan en la siguiente.
// - Sistema suizo: la primera ronda se sortea y las siguientes enfrentan a quienes tienen
//   puntajes parecidos, sin repetir rival. El organizador decide cuántas rondas se juegan.
//
// Un enfrentamiento sin visitante es un "libre": ese participante no juega en la ronda.
// En liga descansa, en eliminación pasa directo y en suizo suma una victoria.
// Los puntos por victoria y por empate, y el mínimo de participantes, los define el
// administrador en la configuración (RF-62).
class Ronda {
    const SCORE_MAXIMO = 999;

    private $pdo;
    private $torneos;

    public function __construct() {
        $this->pdo = conectar();
        $this->torneos = new Torneo();
    }

    // ===== Consultas =====

    // Rondas de un torneo con cuántos partidos tienen y cuántos resultados hay cargados
    public function listarResumen($torneo_id) {
        $stmt = $this->pdo->prepare("
            SELECT r.id, r.numero, r.estado,
                   COUNT(en.participante_visitante_id) AS partidos,
                   COUNT(res.id) AS cargados
            FROM rondas r
            LEFT JOIN enfrentamientos en ON en.ronda_id = r.id
            LEFT JOIN resultados res ON res.enfrentamiento_id = en.id
            WHERE r.torneo_id = ?
            GROUP BY r.id, r.numero, r.estado
            ORDER BY r.numero
        ");
        $stmt->execute([$torneo_id]);
        return $stmt->fetchAll();
    }

    // La ronda que se está jugando (a la que se le cargan resultados), o null
    public static function rondaAbierta($resumen) {
        foreach ($resumen as $r) {
            if ($r['estado'] === 'en_curso') {
                return $r;
            }
        }
        return null;
    }

    // Rondas que se recomiendan en un suizo: las necesarias para que quede un solo puntero
    public static function rondasRecomendadasSuizo($cantidad) {
        return $cantidad >= 2 ? (int) ceil(log($cantidad, 2)) : 0;
    }

    // Más rondas que estas no se pueden jugar sin que alguien repita rival
    public static function rondasMaximasSuizo($cantidad) {
        return $cantidad % 2 === 0 ? $cantidad - 1 : $cantidad;
    }

    // Motivo por el que no se puede generar una ronda nueva, o null si se puede
    public function motivoNoPuedeGenerar($torneo) {
        if ($torneo['estado'] !== 'en_curso') {
            return 'Las rondas se generan cuando el torneo está en curso.';
        }
        $cantidad = count($this->idsAprobados($torneo['id']));
        $resumen = $this->listarResumen($torneo['id']);
        // El mínimo se exige para arrancar: si el admin lo sube con el torneo empezado, el torneo sigue
        $minimo = $resumen ? 2 : Configuracion::valor('minimo_participantes');
        if ($cantidad < $minimo) {
            return "Hacen falta al menos $minimo participantes aprobados para armar los enfrentamientos.";
        }

        if ($resumen && $torneo['tipo'] === 'liga') {
            return 'El fixture de la liga ya está generado.';
        }
        if ($resumen && $torneo['tipo'] === 'eliminacion') {
            return 'Las rondas siguientes de la llave se arman solas al cerrar cada ronda.';
        }
        foreach ($resumen as $r) {
            if ($r['estado'] !== 'finalizada') {
                return 'Cerrá la Ronda ' . $r['numero'] . ' antes de generar la siguiente.';
            }
        }
        if (count($resumen) >= self::rondasMaximasSuizo($cantidad)) {
            return 'Ya se jugaron todas las rondas posibles sin que nadie repita rival.';
        }
        return null;
    }

    // Motivo por el que todavía no se puede finalizar el torneo, o null si se puede
    public function motivoNoPuedeFinalizar($torneo) {
        if ($torneo['estado'] !== 'en_curso') {
            return 'Solo se puede finalizar un torneo en curso.';
        }
        $resumen = $this->listarResumen($torneo['id']);
        if (!$resumen) {
            return 'Todavía no se jugó ninguna ronda.';
        }
        $prefijo = $torneo['tipo'] === 'liga' ? 'Fecha' : 'Ronda';
        foreach ($resumen as $r) {
            if ($r['estado'] !== 'finalizada') {
                return "Todavía falta jugar y cerrar la $prefijo {$r['numero']}.";
            }
        }
        // En eliminación el torneo termina con la final (la última ronda tiene un solo cruce)
        $ultima = end($resumen);
        if ($torneo['tipo'] === 'eliminacion' && $ultima['partidos'] > 1) {
            return 'Todavía no se jugó la final.';
        }
        return null;
    }

    // Campeón del torneo: el ganador de la final, o el primero de la tabla. null si todavía no hay.
    public function campeon($torneo) {
        if ($torneo['tipo'] !== 'eliminacion') {
            $posiciones = $this->torneos->listarPosiciones($torneo['id']);
            return $posiciones && $posiciones[0]['pj'] > 0 ? $posiciones[0]['nombre'] : null;
        }

        // Partidos de la última ronda generada: si hay uno solo, es la final
        $stmt = $this->pdo->prepare("
            SELECT en.participante_local_id, en.participante_visitante_id, res.score_local, res.score_visitante
            FROM enfrentamientos en
            LEFT JOIN resultados res ON res.enfrentamiento_id = en.id
            WHERE en.ronda_id = (SELECT id FROM rondas WHERE torneo_id = ? ORDER BY numero DESC LIMIT 1)
        ");
        $stmt->execute([$torneo['id']]);
        $partidos = $stmt->fetchAll();
        if (count($partidos) !== 1) {
            return null;
        }
        $ganador = $this->ganador($partidos[0]);
        return $ganador ? $this->nombreParticipante($ganador) : null;
    }

    // ===== Generar rondas (RF-28, RF-32, RF-36, RF-37, RF-39) =====

    public function generar($torneo_id, $usuario_id) {
        $torneo = $this->torneos->buscarPorId($torneo_id);
        if (!$torneo) {
            return ['exito' => false, 'mensaje' => 'El torneo no existe'];
        }
        if (!$this->torneos->puedeGestionar($torneo_id, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo un organizador del torneo o un administrador puede generar rondas'];
        }
        $motivo = $this->motivoNoPuedeGenerar($torneo);
        if ($motivo) {
            return ['exito' => false, 'mensaje' => $motivo];
        }

        $ids = $this->idsAprobados($torneo_id);
        $numero = count($this->listarResumen($torneo_id)) + 1;

        $this->pdo->beginTransaction();
        try {
            if ($torneo['tipo'] === 'liga') {
                shuffle($ids);
                foreach ($this->fixtureLiga($ids) as $i => $cruces) {
                    // Se juega la primera fecha; las demás quedan como próximas
                    $this->insertarRonda($torneo_id, $i + 1, $i === 0 ? 'en_curso' : 'pendiente', $cruces, $usuario_id);
                }
                $mensaje = 'Fixture generado: ya podés cargar los resultados de la Fecha 1.';
            } elseif ($torneo['tipo'] === 'eliminacion') {
                shuffle($ids);
                $this->insertarRonda($torneo_id, 1, 'en_curso', $this->primeraRondaEliminacion($ids), $usuario_id);
                $mensaje = 'Llave sorteada: ya podés cargar los resultados de la primera ronda.';
            } else {
                $cruces = $this->emparejarSuizo($torneo, $ids, $numero);
                if ($cruces === null) {
                    $this->pdo->rollBack();
                    return ['exito' => false, 'mensaje' => 'No se pudo armar una ronda sin que alguien repita rival. Podés finalizar el torneo.'];
                }
                $this->insertarRonda($torneo_id, $numero, 'en_curso', $cruces, $usuario_id);
                $mensaje = "Ronda $numero generada: ya podés cargar sus resultados.";
            }

            // Los libres del suizo suman una victoria: la tabla se actualiza ya
            $this->recalcularTabla($torneo);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudieron generar las rondas, intentá de nuevo'];
        }

        return ['exito' => true, 'mensaje' => $mensaje];
    }

    // Todos contra todos por el método del círculo: uno queda fijo y los demás rotan.
    // Con cantidad impar se agrega un 0 ("libre"): a quien le toca, descansa esa fecha.
    private function fixtureLiga($ids) {
        if (count($ids) % 2) {
            $ids[] = 0;
        }
        $n = count($ids);
        $fechas = [];
        for ($r = 0; $r < $n - 1; $r++) {
            $cruces = [];
            for ($i = 0; $i < $n / 2; $i++) {
                $a = $ids[$i];
                $b = $ids[$n - 1 - $i];
                if ($a === 0 || $b === 0) {
                    $cruces[] = [$a ?: $b, null];
                } else {
                    // Se alterna quién es local de una fecha a la otra
                    $cruces[] = $r % 2 === 0 ? [$a, $b] : [$b, $a];
                }
            }
            $fechas[] = $cruces;
            // Rotación: el primero queda fijo y el último pasa al segundo lugar
            $ids = array_merge([$ids[0], $ids[$n - 1]], array_slice($ids, 1, $n - 2));
        }
        return $fechas;
    }

    // Primera ronda de la llave. Se completa hasta la potencia de 2 siguiente con pases
    // directos, repartidos a lo largo de la llave para que no queden todos juntos.
    private function primeraRondaEliminacion($ids) {
        $tamano = 1;
        while ($tamano < count($ids)) {
            $tamano *= 2;
        }
        $cantidad_cruces = $tamano / 2;
        $libres = $tamano - count($ids);

        $cruces = array_fill(0, $cantidad_cruces, null);
        for ($k = 0; $k < $libres; $k++) {
            $cruces[(int) floor($k * $cantidad_cruces / $libres)] = [array_shift($ids), null];
        }
        foreach ($cruces as $i => $cruce) {
            if ($cruce === null) {
                $cruces[$i] = [array_shift($ids), array_shift($ids)];
            }
        }
        return $cruces;
    }

    // Emparejamientos del suizo. La primera ronda se sortea; después se ordena por puntos
    // y cada uno enfrenta al siguiente que todavía no fue su rival. Si un cruce deja a
    // otros sin rival posible, se prueba con el siguiente (backtracking).
    // Con cantidad impar, el último de la tabla que todavía no tuvo libre queda libre.
    private function emparejarSuizo($torneo, $ids, $numero) {
        if ($numero === 1) {
            shuffle($ids);
            $jugados = [];
        } else {
            $stats = $this->calcularEstadisticas($torneo);
            usort($ids, function ($a, $b) use ($stats) {
                return [$stats[$b]['puntos'], $stats[$b]['pg'], $a] <=> [$stats[$a]['puntos'], $stats[$a]['pg'], $b];
            });
            $jugados = $this->rivalesPrevios($torneo['id']);
        }
        if (count($ids) % 2) {
            $ids[] = 0; // el "libre" va al final para que le toque a los de abajo
        }

        $pasos = 0;
        $resolver = function ($restantes) use (&$resolver, &$pasos, $jugados) {
            if (!$restantes) {
                return [];
            }
            // Corte de seguridad por si no hay solución y el backtracking se hace eterno
            if (++$pasos > 100000) {
                return null;
            }
            $a = array_shift($restantes);
            foreach ($restantes as $i => $b) {
                if (isset($jugados[$a][$b])) {
                    continue;
                }
                $resto = $restantes;
                unset($resto[$i]);
                $solucion = $resolver(array_values($resto));
                if ($solucion !== null) {
                    array_unshift($solucion, [$a, $b ?: null]);
                    return $solucion;
                }
            }
            return null;
        };
        return $resolver($ids);
    }

    // Para cada participante, a quiénes ya enfrentó: [id => [rival => true]]. El libre cuenta como rival 0.
    private function rivalesPrevios($torneo_id) {
        $stmt = $this->pdo->prepare("
            SELECT en.participante_local_id AS l, en.participante_visitante_id AS v
            FROM enfrentamientos en
            JOIN rondas r ON r.id = en.ronda_id
            WHERE r.torneo_id = ?
        ");
        $stmt->execute([$torneo_id]);
        $jugados = [];
        foreach ($stmt->fetchAll() as $en) {
            $l = (int) $en['l'];
            $v = (int) $en['v'];
            $jugados[$l][$v] = true;
            $jugados[$v][$l] = true;
        }
        return $jugados;
    }

    private function insertarRonda($torneo_id, $numero, $estado, $cruces, $usuario_id) {
        $stmt = $this->pdo->prepare('INSERT INTO rondas (torneo_id, numero, estado) VALUES (?, ?, ?)');
        $stmt->execute([$torneo_id, $numero, $estado]);
        $ronda_id = $this->pdo->lastInsertId();
        $this->auditar($usuario_id, 'INSERT', 'rondas', $ronda_id);

        $stmt = $this->pdo->prepare("
            INSERT INTO enfrentamientos (ronda_id, participante_local_id, participante_visitante_id, estado)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($cruces as [$local, $visitante]) {
            // El libre no se juega: queda finalizado desde el principio
            $stmt->execute([$ronda_id, $local, $visitante, $visitante ? 'pendiente' : 'finalizado']);
        }
        return $ronda_id;
    }

    // ===== Resultados (RF-43, RF-44, RF-45, RF-47) =====

    // Carga o corrige el resultado de un partido de la ronda que se está jugando
    public function cargarResultado($enfrentamiento_id, $score_local, $score_visitante, $usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT en.*, r.estado AS estado_ronda, r.torneo_id, res.id AS resultado_id
            FROM enfrentamientos en
            JOIN rondas r ON r.id = en.ronda_id
            LEFT JOIN resultados res ON res.enfrentamiento_id = en.id
            WHERE en.id = ?
        ");
        $stmt->execute([$enfrentamiento_id]);
        $en = $stmt->fetch();
        if (!$en) {
            return ['exito' => false, 'mensaje' => 'El partido no existe'];
        }
        if (!$this->torneos->puedeGestionar($en['torneo_id'], $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo un organizador del torneo o un administrador puede cargar resultados'];
        }
        $torneo = $this->torneos->buscarPorId($en['torneo_id']);
        if ($torneo['estado'] !== 'en_curso') {
            return ['exito' => false, 'mensaje' => 'Solo se cargan resultados mientras el torneo está en curso'];
        }
        if ($en['estado_ronda'] === 'finalizada') {
            return ['exito' => false, 'mensaje' => 'Esa ronda ya se cerró: sus resultados no se pueden modificar'];
        }
        if ($en['estado_ronda'] !== 'en_curso') {
            return ['exito' => false, 'mensaje' => 'Esa ronda todavía no se está jugando'];
        }
        if (!$en['participante_local_id'] || !$en['participante_visitante_id']) {
            return ['exito' => false, 'mensaje' => 'Ese cruce no se juega: es un pase libre'];
        }

        // Validación del resultado (RF-45): números enteros, no negativos
        foreach ([$score_local, $score_visitante] as $score) {
            if (!preg_match('/^\d{1,3}$/', (string) $score)) {
                return ['exito' => false, 'mensaje' => 'El resultado tiene que ser un número entero entre 0 y ' . self::SCORE_MAXIMO];
            }
        }
        $score_local = (int) $score_local;
        $score_visitante = (int) $score_visitante;
        if ($torneo['tipo'] === 'eliminacion' && $score_local === $score_visitante) {
            return ['exito' => false, 'mensaje' => 'En eliminación directa no puede haber empate: cargá el resultado que define quién pasa (por ejemplo, con los penales).'];
        }

        $this->pdo->beginTransaction();
        try {
            if ($en['resultado_id']) {
                $stmt = $this->pdo->prepare('UPDATE resultados SET score_local = ?, score_visitante = ?, registrado_por = ? WHERE id = ?');
                $stmt->execute([$score_local, $score_visitante, $usuario_id, $en['resultado_id']]);
                $this->auditar($usuario_id, 'UPDATE', 'resultados', $en['resultado_id']);
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO resultados (enfrentamiento_id, score_local, score_visitante, registrado_por) VALUES (?, ?, ?, ?)');
                $stmt->execute([$enfrentamiento_id, $score_local, $score_visitante, $usuario_id]);
                $this->auditar($usuario_id, 'INSERT', 'resultados', $this->pdo->lastInsertId());
            }

            $stmt = $this->pdo->prepare("UPDATE enfrentamientos SET estado = 'finalizado' WHERE id = ?");
            $stmt->execute([$enfrentamiento_id]);

            // La tabla se recalcula entera: así una corrección también queda bien (RF-30, RF-38)
            $this->recalcularTabla($torneo);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo guardar el resultado, intentá de nuevo'];
        }

        $local = $this->nombreParticipante($en['participante_local_id']);
        $visitante = $this->nombreParticipante($en['participante_visitante_id']);
        return [
            'exito' => true,
            'mensaje' => ($en['resultado_id'] ? 'Resultado corregido' : 'Resultado guardado') . ": $local $score_local - $score_visitante $visitante",
        ];
    }

    // ===== Cerrar rondas (RF-41) y avanzar la llave (RF-33, RF-34) =====

    public function cerrar($ronda_id, $usuario_id) {
        $stmt = $this->pdo->prepare('SELECT * FROM rondas WHERE id = ?');
        $stmt->execute([$ronda_id]);
        $ronda = $stmt->fetch();
        if (!$ronda) {
            return ['exito' => false, 'mensaje' => 'La ronda no existe'];
        }
        if (!$this->torneos->puedeGestionar($ronda['torneo_id'], $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo un organizador del torneo o un administrador puede cerrar rondas'];
        }
        $torneo = $this->torneos->buscarPorId($ronda['torneo_id']);
        $prefijo = $torneo['tipo'] === 'liga' ? 'Fecha' : 'Ronda';
        $nombre = "$prefijo {$ronda['numero']}";

        if ($torneo['estado'] !== 'en_curso' || $ronda['estado'] !== 'en_curso') {
            return ['exito' => false, 'mensaje' => "La $nombre no se está jugando, así que no se puede cerrar"];
        }

        // Todos los partidos (menos los libres) tienen que tener resultado
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM enfrentamientos en
            LEFT JOIN resultados res ON res.enfrentamiento_id = en.id
            WHERE en.ronda_id = ? AND en.participante_visitante_id IS NOT NULL AND res.id IS NULL
        ");
        $stmt->execute([$ronda_id]);
        $faltan = (int) $stmt->fetchColumn();
        if ($faltan > 0) {
            $texto = $faltan === 1 ? 'Falta cargar 1 resultado' : "Faltan cargar $faltan resultados";
            return ['exito' => false, 'mensaje' => "$texto para poder cerrar la $nombre"];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE rondas SET estado = 'finalizada' WHERE id = ? AND estado = 'en_curso'");
            $stmt->execute([$ronda_id]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return ['exito' => false, 'mensaje' => "La $nombre ya estaba cerrada"];
            }
            $this->auditar($usuario_id, 'UPDATE', 'rondas', $ronda_id);

            if ($torneo['tipo'] === 'liga') {
                $mensaje = $this->habilitarSiguienteFecha($torneo['id'], $ronda['numero'], $usuario_id);
            } elseif ($torneo['tipo'] === 'eliminacion') {
                $mensaje = $this->avanzarLlave($torneo, $ronda, $usuario_id);
            } else {
                $mensaje = "Cerraste la Ronda {$ronda['numero']}. Podés generar la siguiente o finalizar el torneo.";
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => "No se pudo cerrar la $nombre, intentá de nuevo"];
        }

        return ['exito' => true, 'mensaje' => $mensaje];
    }

    // Liga: la fecha siguiente del fixture pasa a jugarse
    private function habilitarSiguienteFecha($torneo_id, $numero, $usuario_id) {
        $stmt = $this->pdo->prepare("SELECT id FROM rondas WHERE torneo_id = ? AND numero = ? AND estado = 'pendiente'");
        $stmt->execute([$torneo_id, $numero + 1]);
        $siguiente = $stmt->fetchColumn();
        if (!$siguiente) {
            return "Cerraste la Fecha $numero, la última de la liga. Ya podés finalizar el torneo.";
        }

        $stmt = $this->pdo->prepare("UPDATE rondas SET estado = 'en_curso' WHERE id = ?");
        $stmt->execute([$siguiente]);
        $this->auditar($usuario_id, 'UPDATE', 'rondas', $siguiente);
        return "Cerraste la Fecha $numero. Ya se pueden cargar los resultados de la Fecha " . ($numero + 1) . '.';
    }

    // Eliminación: los ganadores se cruzan de a dos, en el orden de la llave
    // (el ganador del cruce 1 contra el del 2, el del 3 contra el del 4, ...)
    private function avanzarLlave($torneo, $ronda, $usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT en.participante_local_id, en.participante_visitante_id, res.score_local, res.score_visitante
            FROM enfrentamientos en
            LEFT JOIN resultados res ON res.enfrentamiento_id = en.id
            WHERE en.ronda_id = ?
            ORDER BY en.id
        ");
        $stmt->execute([$ronda['id']]);
        $ganadores = array_map([$this, 'ganador'], $stmt->fetchAll());

        if (count($ganadores) === 1) {
            $campeon = $this->nombreParticipante($ganadores[0]);
            return "Cerraste la final: $campeon es el campeón. Ya podés finalizar el torneo.";
        }

        $cruces = [];
        for ($i = 0; $i < count($ganadores); $i += 2) {
            $cruces[] = [$ganadores[$i], $ganadores[$i + 1]];
        }
        $this->insertarRonda($torneo['id'], $ronda['numero'] + 1, 'en_curso', $cruces, $usuario_id);

        return 'Cerraste la ronda: los ganadores ya están cruzados en ' . self::articulo(count($cruces)) . '.';
    }

    // "la final", "las semifinales", "los cuartos de final", "la ronda de 32"
    private static function articulo($cruces) {
        $nombres = [1 => 'la final', 2 => 'las semifinales', 4 => 'los cuartos de final', 8 => 'los octavos de final'];
        return $nombres[$cruces] ?? 'la ronda de ' . ($cruces * 2);
    }

    // Quién ganó un cruce: el local si pasó libre, si no el de más puntos. null si no se jugó o empataron.
    private function ganador($en) {
        if (!$en['participante_visitante_id']) {
            return (int) $en['participante_local_id'];
        }
        if ($en['score_local'] === null || $en['score_local'] == $en['score_visitante']) {
            return null;
        }
        return (int) ($en['score_local'] > $en['score_visitante'] ? $en['participante_local_id'] : $en['participante_visitante_id']);
    }

    // ===== Finalizar el torneo (RF-23) =====

    public function finalizarTorneo($torneo_id, $usuario_id) {
        $torneo = $this->torneos->buscarPorId($torneo_id);
        if (!$torneo) {
            return ['exito' => false, 'mensaje' => 'El torneo no existe'];
        }
        if (!$this->torneos->puedeGestionar($torneo_id, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo un organizador del torneo o un administrador puede finalizarlo'];
        }
        $motivo = $this->motivoNoPuedeFinalizar($torneo);
        if ($motivo) {
            return ['exito' => false, 'mensaje' => $motivo];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE torneos SET estado = 'finalizado' WHERE id = ? AND estado = 'en_curso'");
            $stmt->execute([$torneo_id]);
            $this->auditar($usuario_id, 'UPDATE', 'torneos', $torneo_id);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo finalizar el torneo, intentá de nuevo'];
        }

        $campeon = $this->campeon($torneo);
        return ['exito' => true, 'mensaje' => 'Torneo finalizado' . ($campeon ? ". Campeón: $campeon" : '')];
    }

    // ===== Tabla de posiciones (RF-29, RF-30, RF-38) =====

    // PJ, PG, PP y puntos de cada participante aprobado, calculados desde los resultados
    private function calcularEstadisticas($torneo) {
        $config = (new Configuracion())->todos();
        $victoria = $config['puntos_victoria'];
        $empate = $config['puntos_empate'];
        $stats = [];
        foreach ($this->idsAprobados($torneo['id']) as $id) {
            $stats[$id] = ['pj' => 0, 'pg' => 0, 'pp' => 0, 'puntos' => 0];
        }

        $stmt = $this->pdo->prepare("
            SELECT en.participante_local_id AS l, en.participante_visitante_id AS v,
                   res.score_local AS a, res.score_visitante AS b
            FROM enfrentamientos en
            JOIN rondas r ON r.id = en.ronda_id
            LEFT JOIN resultados res ON res.enfrentamiento_id = en.id
            WHERE r.torneo_id = ?
        ");
        $stmt->execute([$torneo['id']]);

        foreach ($stmt->fetchAll() as $en) {
            $l = (int) $en['l'];
            $v = (int) $en['v'];
            if (!isset($stats[$l]) || ($v && !isset($stats[$v]))) {
                continue; // participante dado de baja
            }
            if (!$v) {
                // Libre: en suizo cuenta como victoria; en liga solo descansa
                if ($torneo['tipo'] === 'suizo') {
                    $stats[$l]['pj']++;
                    $stats[$l]['pg']++;
                    $stats[$l]['puntos'] += $victoria;
                }
                continue;
            }
            if ($en['a'] === null) {
                continue; // todavía no se jugó
            }
            $a = (int) $en['a'];
            $b = (int) $en['b'];
            $stats[$l]['pj']++;
            $stats[$v]['pj']++;
            if ($a === $b) {
                $stats[$l]['puntos'] += $empate;
                $stats[$v]['puntos'] += $empate;
            } else {
                [$gana, $pierde] = $a > $b ? [$l, $v] : [$v, $l];
                $stats[$gana]['pg']++;
                $stats[$gana]['puntos'] += $victoria;
                $stats[$pierde]['pp']++;
            }
        }
        return $stats;
    }

    // Cuando el admin cambia los puntos, las tablas de los torneos en juego se rearman
    // con los valores nuevos, así todos los partidos de un mismo torneo cuentan igual.
    // Los torneos finalizados conservan su tabla tal como terminó. Devuelve cuántos se recalcularon.
    public function recalcularTorneosEnCurso() {
        $torneos = $this->pdo->query("SELECT * FROM torneos WHERE estado = 'en_curso' AND tipo IN ('liga', 'suizo')")->fetchAll();
        $this->pdo->beginTransaction();
        try {
            foreach ($torneos as $torneo) {
                $this->recalcularTabla($torneo);
            }
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return 0;
        }
        return count($torneos);
    }

    // Rearma la tabla del torneo desde cero. La eliminación directa no tiene tabla.
    private function recalcularTabla($torneo) {
        if ($torneo['tipo'] === 'eliminacion') {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM tabla_posiciones WHERE torneo_id = ?');
        $stmt->execute([$torneo['id']]);

        $stmt = $this->pdo->prepare("
            INSERT INTO tabla_posiciones (torneo_id, participante_id, pj, pg, pp, puntos)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($this->calcularEstadisticas($torneo) as $id => $s) {
            $stmt->execute([$torneo['id'], $id, $s['pj'], $s['pg'], $s['pp'], $s['puntos']]);
        }
    }

    // ===== Auxiliares =====

    private function idsAprobados($torneo_id) {
        $stmt = $this->pdo->prepare("SELECT id FROM participantes WHERE torneo_id = ? AND estado = 'aprobado' ORDER BY id");
        $stmt->execute([$torneo_id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
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

    // Torneo al que pertenece una ronda o un enfrentamiento (para volver al detalle)
    public function torneoDeRonda($ronda_id) {
        $stmt = $this->pdo->prepare('SELECT torneo_id FROM rondas WHERE id = ?');
        $stmt->execute([$ronda_id]);
        return $stmt->fetchColumn();
    }

    public function torneoDeEnfrentamiento($enfrentamiento_id) {
        $stmt = $this->pdo->prepare("
            SELECT r.torneo_id FROM enfrentamientos en JOIN rondas r ON r.id = en.ronda_id WHERE en.id = ?
        ");
        $stmt->execute([$enfrentamiento_id]);
        return $stmt->fetchColumn();
    }

    private function auditar($usuario_id, $accion, $tabla, $registro_id) {
        $stmt = $this->pdo->prepare("
            INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $accion, $tabla, $registro_id]);
    }
}
