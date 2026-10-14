<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/modulo.php';
require_once __DIR__ . '/configuracion.php';

class Torneo {
    private $pdo;

    const TIPOS = ['liga', 'eliminacion', 'suizo'];
    const MODALIDADES = ['individual', 'equipo'];
    // Misma lista que usa database/tools/generar_seed.py para los datos de prueba
    const DEPORTES = [
        'Fútbol 5', 'Fútbol 11', 'Básquet', 'Vóley', 'Handball', 'Ajedrez', 'Tenis', 'Pádel',
        'Ping Pong', 'eSports - FIFA', 'eSports - Rocket League', 'eSports - League of Legends', 'Otro',
    ];

    public function __construct() {
        $this->pdo = conectar();
    }

    // Un torneo publicado pasa solo a "en curso" cuando llega su fecha de inicio.
    // Se usa la fecha de MySQL (la del sistema). Los borradores no cambian porque
    // todavía no se publicaron, y la finalización la decide el organizador.
    // Se llama al principio de las páginas y controladores que usan el estado.
    public function actualizarEstadosPorFecha() {
        $buscar = "
            SELECT id FROM torneos
            WHERE estado = 'publicado' AND fecha_inicio <= CURDATE()
        ";
        // Casi siempre no hay ninguno: se revisa rápido y sin bloquear nada
        if (!$this->pdo->query($buscar)->fetchAll(PDO::FETCH_COLUMN)) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            // Se vuelve a buscar bloqueando las filas: si otra visita llegó al mismo
            // tiempo y ya los cambió, no se anotan dos veces en la auditoría
            $ids = $this->pdo->query($buscar . ' FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
            if (!$ids) {
                $this->pdo->commit();
                return;
            }

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

    // Devuelve un array con los errores encontrados (vacío si todo está bien).
    // Con $validar_formato = false no se revisan deporte y tipo (torneo publicado:
    // esos datos no se pueden cambiar y vienen de la base).
    // $tipo_actual: al editar, el tipo que ya tenía el torneo. Un borrador puede conservar
    // un tipo deshabilitado, pero ningún torneo puede pasar a uno (RF-60).
    public function validar($datos, $validar_formato = true, $tipo_actual = null) {
        $errores = [];

        if (trim($datos['nombre']) === '') {
            $errores[] = 'El nombre del torneo es obligatorio';
        } elseif (mb_strlen($datos['nombre']) > 150) {
            $errores[] = 'El nombre no puede tener más de 150 caracteres';
        }

        if ($validar_formato && !in_array($datos['deporte'], self::DEPORTES, true)) {
            $errores[] = 'Seleccioná un deporte válido';
        }

        if ($validar_formato && !in_array($datos['tipo'], self::TIPOS, true)) {
            $errores[] = 'Seleccioná un tipo de torneo válido';
        } elseif ($validar_formato && $datos['tipo'] !== $tipo_actual && !(new Modulo())->estaHabilitado($datos['tipo'])) {
            $errores[] = 'Ese tipo de torneo está deshabilitado por el administrador: elegí otro';
        }

        if ($validar_formato && !in_array($datos['modalidad'], self::MODALIDADES, true)) {
            $errores[] = 'Elegí si el torneo es individual o por equipos';
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
                INSERT INTO torneos (nombre, descripcion, deporte, tipo, modalidad, fecha_inicio, fecha_fin, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, \'borrador\')
            ');
            $stmt->execute([
                trim($datos['nombre']),
                trim($datos['descripcion']),
                $datos['deporte'],
                $datos['tipo'],
                $datos['modalidad'],
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

            $nuevo_rol = $this->asignarRolOrganizador($usuario_id);

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo crear el torneo, intentá de nuevo'];
        }

        return ['exito' => true, 'mensaje' => 'Torneo creado correctamente', 'id' => $torneo_id, 'nuevo_rol' => $nuevo_rol];
    }

    // El rol lo asigna el sistema según lo que hace cada uno (RF-06): quien crea su primer
    // torneo pasa de participante a organizador, sin tener que pedírselo a nadie.
    // Los administradores conservan su rol. Devuelve el rol nuevo, o null si no cambió.
    // Se llama dentro de la transacción de crear().
    private function asignarRolOrganizador($usuario_id) {
        $stmt = $this->pdo->prepare("
            UPDATE usuario_roles
            SET rol_id = (SELECT id FROM roles WHERE nombre = 'organizador')
            WHERE usuario_id = ? AND rol_id = (SELECT id FROM roles WHERE nombre = 'participante')
        ");
        $stmt->execute([$usuario_id]);
        if ($stmt->rowCount() === 0) {
            return null;
        }

        // usuario_id NULL: el cambio lo hizo el sistema, no una persona
        $stmt = $this->pdo->prepare("
            INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
            VALUES (NULL, 'UPDATE', 'usuario_roles', ?)
        ");
        $stmt->execute([$usuario_id]);
        return 'organizador';
    }

    // Qué se puede editar según el estado: todo en borrador; en publicado solo
    // nombre, descripción y fechas (la gente ya se anotó con ese deporte, tipo y modalidad);
    // en curso o finalizado, nada.
    public static function sePuedeEditar($torneo) {
        return in_array($torneo['estado'], ['borrador', 'publicado'], true);
    }

    public static function formatoBloqueado($torneo) {
        return $torneo['estado'] !== 'borrador';
    }

    // Modifica un torneo (RF-19, RF-21). Solo puede hacerlo un organizador.
    public function actualizar($torneo_id, $datos, $usuario_id) {
        if (!$this->puedeGestionar($torneo_id, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo un organizador del torneo o un administrador puede modificarlo'];
        }

        $torneo = $this->buscarPorId($torneo_id);
        if (!self::sePuedeEditar($torneo)) {
            return ['exito' => false, 'mensaje' => 'Un torneo en curso o finalizado ya no se puede modificar'];
        }

        // En un torneo publicado se ignora lo que llegue de deporte, tipo y modalidad
        $bloqueado = self::formatoBloqueado($torneo);
        if ($bloqueado) {
            $datos['deporte'] = $torneo['deporte'];
            $datos['tipo'] = $torneo['tipo'];
            $datos['modalidad'] = $torneo['modalidad'];
        }

        $errores = $this->validar($datos, !$bloqueado, $torneo['tipo']);
        if ($errores) {
            return ['exito' => false, 'mensaje' => implode('. ', $errores)];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('
                UPDATE torneos
                SET nombre = ?, descripcion = ?, deporte = ?, tipo = ?, modalidad = ?, fecha_inicio = ?, fecha_fin = ?
                WHERE id = ?
            ');
            $stmt->execute([
                trim($datos['nombre']),
                trim($datos['descripcion']),
                $datos['deporte'],
                $datos['tipo'],
                $datos['modalidad'],
                $datos['fecha_inicio'],
                $datos['fecha_fin'],
                $torneo_id,
            ]);

            $stmt = $this->pdo->prepare("
                INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
                VALUES (?, 'UPDATE', 'torneos', ?)
            ");
            $stmt->execute([$usuario_id, $torneo_id]);

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudieron guardar los cambios, intentá de nuevo'];
        }

        // Si era publicado y se movió la fecha de inicio a hoy, pasa a "en curso"
        $this->actualizarEstadosPorFecha();

        return ['exito' => true, 'mensaje' => 'Cambios guardados'];
    }

    // Torneos visibles para cualquiera (RF-48): todo menos los borradores.
    // Primero los en curso, después los próximos y al final los finalizados.
    // Torneos públicos (RF-48), con búsqueda y filtros opcionales:
    // ['buscar' => 'futbol buceo', 'tipo' => 'liga', 'estado' => 'en_curso'].
    // Cada palabra buscada tiene que aparecer en el nombre, el deporte o la descripción.
    // La comparación no distingue tildes ni mayúsculas (collation utf8mb4_unicode_ci).
    public function listarPublicos($filtros = []) {
        $where = ["estado <> 'borrador'"];
        $params = [];

        $palabras = preg_split('/\s+/', trim($filtros['buscar'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        foreach (array_slice($palabras, 0, 8) as $palabra) {
            // % y _ son comodines de LIKE: se escapan para buscarlos como texto
            $texto = '%' . addcslashes($palabra, '%_\\') . '%';
            $where[] = '(nombre LIKE ? OR deporte LIKE ? OR descripcion LIKE ?)';
            array_push($params, $texto, $texto, $texto);
        }
        if (in_array($filtros['tipo'] ?? '', self::TIPOS, true)) {
            $where[] = 'tipo = ?';
            $params[] = $filtros['tipo'];
        }
        if (in_array($filtros['estado'] ?? '', ['publicado', 'en_curso', 'finalizado'], true)) {
            $where[] = 'estado = ?';
            $params[] = $filtros['estado'];
        }

        $stmt = $this->pdo->prepare("
            SELECT * FROM torneos
            WHERE " . implode(' AND ', $where) . "
            ORDER BY FIELD(estado, 'en_curso', 'publicado', 'finalizado'),
                     CASE WHEN estado = 'finalizado' THEN NULL ELSE fecha_inicio END ASC,
                     fecha_inicio DESC
        ");
        $stmt->execute($params);
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

    // ===== Mi actividad (lo que juega el usuario, solo o con sus equipos) =====

    // Partidos del usuario en torneos en curso o finalizados, separados en
    // ['proximos' => sin resultado todavía, 'resultados' => ya jugados].
    // "mio" es la inscripción del usuario (o de su equipo) y "rival" la del otro lado.
    public function listarMisPartidos($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT t.id AS torneo_id, t.nombre AS torneo, t.tipo, t.estado AS estado_torneo,
                   r.numero, r.estado AS estado_ronda,
                   (SELECT COUNT(*) FROM enfrentamientos x WHERE x.ronda_id = r.id) AS cruces,
                   COALESCE(em.nombre, CONCAT(um.nombre, ' ', um.apellido)) AS mio,
                   COALESCE(er.nombre, CONCAT(ur.nombre, ' ', ur.apellido)) AS rival,
                   en.participante_visitante_id IS NULL AS libre,
                   -- En suizo el libre cuenta como partido ganado (ver ronda.php)
                   (res.id IS NOT NULL OR (t.tipo = 'suizo' AND en.participante_visitante_id IS NULL)) AS jugado,
                   CASE WHEN en.participante_local_id = mio.id THEN res.score_local ELSE res.score_visitante END AS mis_puntos,
                   CASE WHEN en.participante_local_id = mio.id THEN res.score_visitante ELSE res.score_local END AS puntos_rival
            FROM participantes mio
            JOIN enfrentamientos en ON mio.id IN (en.participante_local_id, en.participante_visitante_id)
            JOIN rondas r ON r.id = en.ronda_id
            JOIN torneos t ON t.id = r.torneo_id
            LEFT JOIN equipos em ON em.id = mio.equipo_id
            LEFT JOIN usuarios um ON um.id = mio.usuario_id
            -- El rival es el que no soy yo (NULL si me tocó libre)
            LEFT JOIN participantes rival ON rival.id = CASE
                WHEN en.participante_local_id = mio.id THEN en.participante_visitante_id
                ELSE en.participante_local_id END
            LEFT JOIN equipos er ON er.id = rival.equipo_id
            LEFT JOIN usuarios ur ON ur.id = rival.usuario_id
            LEFT JOIN resultados res ON res.enfrentamiento_id = en.id
            WHERE mio.estado = 'aprobado'
              AND t.estado IN ('en_curso', 'finalizado')
              AND (mio.usuario_id = ?
                   OR mio.equipo_id IN (SELECT equipo_id FROM equipo_miembros WHERE usuario_id = ?))
            -- Dentro de cada torneo: lo que falta jugar de la fecha más cercana a la más lejana,
            -- y lo jugado de la última fecha a la primera (por eso el número en negativo)
            ORDER BY FIELD(t.estado, 'en_curso', 'finalizado'), t.fecha_inicio DESC, t.id,
                     CASE WHEN res.id IS NOT NULL OR (t.tipo = 'suizo' AND en.participante_visitante_id IS NULL)
                          THEN -r.numero ELSE r.numero END
        ");
        $stmt->execute([$usuario_id, $usuario_id]);

        $proximos = [];
        $resultados = [];
        foreach ($stmt->fetchAll() as $partido) {
            if ($partido['jugado']) {
                $resultados[] = $partido;
            } elseif ($partido['estado_torneo'] === 'en_curso' && $partido['estado_ronda'] !== 'finalizada') {
                // Los libres de rondas ya cerradas no se muestran: no hay nada que jugar
                $proximos[] = $partido;
            }
        }
        // Primero lo que se juega ahora y después las fechas que vienen (el orden de cada grupo se mantiene)
        usort($proximos, function ($a, $b) {
            return ($b['estado_ronda'] === 'en_curso') <=> ($a['estado_ronda'] === 'en_curso');
        });
        return ['proximos' => $proximos, 'resultados' => $resultados];
    }

    // Cómo le va al usuario en cada torneo en curso o finalizado.
    // Liga y suizo: su puesto en la tabla, con el mismo orden que listarPosiciones().
    // Eliminación: no hay tabla, se cuentan sus derrotas para saber si sigue en carrera.
    public function listarMisPosiciones($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.nombre, t.tipo, t.estado,
                   pos.puesto, pos.total, pos.pj, pos.puntos, pos.jugados,
                   (SELECT COUNT(*)
                    FROM enfrentamientos en
                    JOIN resultados res ON res.enfrentamiento_id = en.id
                    WHERE (en.participante_local_id = mio.id AND res.score_local < res.score_visitante)
                       OR (en.participante_visitante_id = mio.id AND res.score_visitante < res.score_local)
                   ) AS derrotas,
                   (SELECT COUNT(*) FROM rondas r WHERE r.torneo_id = t.id) AS rondas
            FROM participantes mio
            JOIN torneos t ON t.id = mio.torneo_id
            -- Puesto de cada participante en la tabla de su torneo (1, 2, 3...)
            LEFT JOIN (
                SELECT tp.participante_id, tp.pj, tp.puntos,
                       -- RANK: si dos quedan iguales en todo, comparten el puesto
                       RANK() OVER (
                           PARTITION BY tp.torneo_id
                           ORDER BY tp.puntos DESC, tp.gf - tp.gc DESC, tp.gf DESC, tp.pg DESC
                       ) AS puesto,
                       COUNT(*) OVER (PARTITION BY tp.torneo_id) AS total,
                       -- Si nadie jugó todavía, la tabla es puro orden alfabético
                       MAX(tp.pj) OVER (PARTITION BY tp.torneo_id) AS jugados
                FROM tabla_posiciones tp
                JOIN participantes p ON p.id = tp.participante_id
                LEFT JOIN equipos e ON e.id = p.equipo_id
                LEFT JOIN usuarios u ON u.id = p.usuario_id
            ) pos ON pos.participante_id = mio.id
            WHERE mio.estado = 'aprobado'
              AND t.estado IN ('en_curso', 'finalizado')
              AND (mio.usuario_id = ?
                   OR mio.equipo_id IN (SELECT equipo_id FROM equipo_miembros WHERE usuario_id = ?))
            ORDER BY FIELD(t.estado, 'en_curso', 'finalizado'), t.fecha_inicio DESC, t.id
        ");
        $stmt->execute([$usuario_id, $usuario_id]);
        return $stmt->fetchAll();
    }

    // ===== Detalle de un torneo =====

    public function buscarPorId($id) {
        $stmt = $this->pdo->prepare('SELECT * FROM torneos WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function listarOrganizadores($torneo_id) {
        $stmt = $this->pdo->prepare("
            SELECT u.id, CONCAT(u.nombre, ' ', u.apellido) AS nombre
            FROM torneo_organizadores o
            JOIN usuarios u ON u.id = o.usuario_id
            WHERE o.torneo_id = ?
            ORDER BY u.nombre
        ");
        $stmt->execute([$torneo_id]);
        return $stmt->fetchAll();
    }

    // Participantes aprobados. "nombre" es el del equipo o el de la persona, según el tipo.
    public function listarParticipantes($torneo_id) {
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.tipo,
                   COALESCE(e.nombre, CONCAT(u.nombre, ' ', u.apellido)) AS nombre,
                   (SELECT COUNT(*) FROM equipo_miembros m WHERE m.equipo_id = p.equipo_id) AS miembros
            FROM participantes p
            LEFT JOIN equipos e ON e.id = p.equipo_id
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            WHERE p.torneo_id = ? AND p.estado = 'aprobado'
            ORDER BY nombre
        ");
        $stmt->execute([$torneo_id]);
        return $stmt->fetchAll();
    }

    // Tabla de posiciones ordenada: más puntos, mejor diferencia de goles, más goles a favor,
    // más partidos ganados y, si siguen iguales en todo, por nombre.
    // Los empates (pe) no están en la tabla: son los jugados que no se ganaron ni perdieron.
    public function listarPosiciones($torneo_id) {
        $stmt = $this->pdo->prepare("
            SELECT tp.pj, tp.pg, tp.pj - tp.pg - tp.pp AS pe, tp.pp, tp.puntos,
                   tp.gf, tp.gc, tp.gf - tp.gc AS dif,
                   COALESCE(e.nombre, CONCAT(u.nombre, ' ', u.apellido)) AS nombre
            FROM tabla_posiciones tp
            JOIN participantes p ON p.id = tp.participante_id
            LEFT JOIN equipos e ON e.id = p.equipo_id
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            WHERE tp.torneo_id = ?
            ORDER BY tp.puntos DESC, dif DESC, tp.gf DESC, tp.pg DESC, nombre
        ");
        $stmt->execute([$torneo_id]);
        return $stmt->fetchAll();
    }

    // Rondas con sus enfrentamientos y resultados, agrupadas.
    // Un enfrentamiento sin visitante es un pase libre (ver app/models/ronda.php).
    // [ ['id' => 7, 'numero' => 1, 'estado' => ..., 'enfrentamientos' => [...]], ... ]
    public function listarRondas($torneo_id) {
        $stmt = $this->pdo->prepare("
            SELECT r.id AS ronda_id, r.numero, r.estado AS estado_ronda,
                   en.id, en.estado, en.participante_local_id, en.participante_visitante_id,
                   COALESCE(el.nombre, CONCAT(ul.nombre, ' ', ul.apellido)) AS local,
                   COALESCE(ev.nombre, CONCAT(uv.nombre, ' ', uv.apellido)) AS visitante,
                   res.score_local, res.score_visitante
            FROM rondas r
            LEFT JOIN enfrentamientos en ON en.ronda_id = r.id
            LEFT JOIN participantes pl ON pl.id = en.participante_local_id
            LEFT JOIN equipos el ON el.id = pl.equipo_id
            LEFT JOIN usuarios ul ON ul.id = pl.usuario_id
            LEFT JOIN participantes pv ON pv.id = en.participante_visitante_id
            LEFT JOIN equipos ev ON ev.id = pv.equipo_id
            LEFT JOIN usuarios uv ON uv.id = pv.usuario_id
            LEFT JOIN resultados res ON res.enfrentamiento_id = en.id
            WHERE r.torneo_id = ?
            ORDER BY r.numero, en.id
        ");
        $stmt->execute([$torneo_id]);

        $rondas = [];
        foreach ($stmt->fetchAll() as $fila) {
            $n = $fila['numero'];
            if (!isset($rondas[$n])) {
                $rondas[$n] = ['id' => $fila['ronda_id'], 'numero' => $n, 'estado' => $fila['estado_ronda'], 'enfrentamientos' => []];
            }
            if ($fila['id'] !== null) {
                $rondas[$n]['enfrentamientos'][] = $fila;
            }
        }
        return array_values($rondas);
    }

    // El rol se consulta en la base (no en la sesión) para que no se pueda falsear
    public function esAdmin($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM usuario_roles ur
            JOIN roles r ON r.id = ur.rol_id
            WHERE ur.usuario_id = ? AND r.nombre = 'admin'
        ");
        $stmt->execute([$usuario_id]);
        return (bool) $stmt->fetch();
    }

    // Puede gestionar un torneo (ver su borrador, editar, publicar, eliminar):
    // sus organizadores y los administradores (RF-07, RNF-18). Las reglas de cada
    // acción según el estado valen igual para todos, y la auditoría guarda quién fue.
    public function puedeGestionar($torneo_id, $usuario_id) {
        return $this->esOrganizador($torneo_id, $usuario_id) || $this->esAdmin($usuario_id);
    }

    public function esOrganizador($torneo_id, $usuario_id) {
        $stmt = $this->pdo->prepare('SELECT 1 FROM torneo_organizadores WHERE torneo_id = ? AND usuario_id = ?');
        $stmt->execute([$torneo_id, $usuario_id]);
        return (bool) $stmt->fetch();
    }

    // Pasa un torneo de borrador a publicado (RF-22). Solo puede hacerlo un organizador.
    public function publicar($torneo_id, $usuario_id) {
        if (!$this->puedeGestionar($torneo_id, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo un organizador del torneo o un administrador puede publicarlo'];
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

    // Arranca un torneo publicado antes de su fecha de inicio (RF-21, RF-24): se cierra la
    // inscripción y la fecha de inicio pasa a ser hoy. Las inscripciones que siguen
    // pendientes quedan afuera, porque solo juegan los aprobados.
    public function iniciar($torneo_id, $usuario_id) {
        if (!$this->puedeGestionar($torneo_id, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo un organizador del torneo o un administrador puede iniciarlo'];
        }
        $torneo = $this->buscarPorId($torneo_id);
        if (!$torneo || $torneo['estado'] !== 'publicado') {
            return ['exito' => false, 'mensaje' => 'Solo se puede iniciar un torneo publicado que todavía no empezó'];
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM participantes WHERE torneo_id = ? AND estado = 'aprobado'");
        $stmt->execute([$torneo_id]);
        $minimo = Configuracion::valor('minimo_participantes');
        if ($stmt->fetchColumn() < $minimo) {
            return ['exito' => false, 'mensaje' => "Hacen falta al menos $minimo participantes aprobados para iniciar el torneo"];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE torneos SET estado = 'en_curso', fecha_inicio = LEAST(fecha_inicio, CURDATE())
                WHERE id = ? AND estado = 'publicado'
            ");
            $stmt->execute([$torneo_id]);

            $stmt = $this->pdo->prepare("
                INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
                VALUES (?, 'UPDATE', 'torneos', ?)
            ");
            $stmt->execute([$usuario_id, $torneo_id]);

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo iniciar el torneo, intentá de nuevo'];
        }

        return ['exito' => true, 'mensaje' => 'El torneo empezó: ya podés generar las rondas'];
    }

    // Elimina un torneo (RF-20). Solo se permite mientras es borrador, porque
    // después ya puede tener inscriptos. Solo puede hacerlo un organizador.
    public function eliminar($torneo_id, $usuario_id) {
        if (!$this->puedeGestionar($torneo_id, $usuario_id)) {
            return ['exito' => false, 'mensaje' => 'Solo un organizador del torneo o un administrador puede eliminarlo'];
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
