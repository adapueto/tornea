<?php
session_start();
require_once __DIR__ . '/../models/torneo.php';
require_once __DIR__ . '/../models/participante.php';
require_once __DIR__ . '/../models/equipo.php';
require_once __DIR__ . '/../helpers/formato.php';

// Detalle de un torneo: una sola vista para los tres tipos (liga, eliminación y suizo)

$modelo = new Torneo();
$id = (int) ($_GET['id'] ?? 0);
$torneo = $modelo->buscarPorId($id);

// Quién mira: los organizadores y los administradores pueden gestionar el torneo
$usuario_id = $_SESSION['usuario']['id'] ?? 0;
$puede_gestionar = $torneo && $modelo->puedeGestionar($id, $usuario_id);
// Admin que no organiza este torneo: se le aclara en el panel
$gestiona_como_admin = $puede_gestionar && !$modelo->esOrganizador($id, $usuario_id);

// Los borradores solo los ven quienes pueden gestionarlos
if ($torneo && $torneo['estado'] === 'borrador' && !$puede_gestionar) {
    $torneo = null;
}

// Un borrador no aparece en el listado público: el organizador vuelve a "Mis torneos"
if ($torneo && $torneo['estado'] === 'borrador' && !$gestiona_como_admin) {
    $volver = ['url' => '/tornea/app/views/perfil.php', 'texto' => 'Volver a mis torneos'];
} else {
    $volver = ['url' => '/tornea/app/views/torneos.php', 'texto' => 'Volver a torneos'];
}

if (!$torneo) {
    http_response_code(404);
} else {
    $organizadores = $modelo->listarOrganizadores($id);
    $participantes = $modelo->listarParticipantes($id);
    $rondas = $modelo->listarRondas($id);

    $es_por_equipos = $torneo['modalidad'] === 'equipo';
    $modalidad = $es_por_equipos ? 'Por equipos' : 'Individual';
    $prefijo_ronda = $torneo['tipo'] === 'liga' ? 'Fecha' : 'Ronda';

    $mensajes = [
        'borrador' => 'El torneo todavía es un borrador: publicalo para abrir la inscripción.',
        'publicado' => 'Las rondas se generan cuando arranca el torneo.',
        'en_curso' => 'Todavía no se generaron las rondas.',
        'finalizado' => 'Este torneo no tiene rondas registradas.',
    ];
    $mensaje_sin_rondas = $mensajes[$torneo['estado']];

    // Qué puede hacer el organizador en cada etapa
    $ayudas = [
        'borrador' => 'Este torneo todavía no es público. Revisá los datos y publicalo para abrir la inscripción.',
        'publicado' => 'La inscripción está abierta. Podés corregir el nombre, la descripción y las fechas; el deporte, el tipo y la modalidad ya no se pueden cambiar.',
        'en_curso' => 'El torneo está en juego, así que sus datos ya no se pueden modificar.',
        'finalizado' => 'El torneo terminó. Sus datos y resultados quedan como registro.',
    ];
    $ayuda_gestion = $ayudas[$torneo['estado']];

    // ===== Inscripción (RF-12) =====
    $modeloParticipante = new Participante();
    $es_organizador = $modelo->esOrganizador($id, $usuario_id);

    // Lo que ve quien quiere anotarse (no se muestra a los organizadores del torneo)
    $mi_inscripcion = $usuario_id ? $modeloParticipante->buscarInscripcion($id, $usuario_id) : false;
    $motivo_no_inscribir = $usuario_id && !$mi_inscripcion
        ? $modeloParticipante->motivoNoPuedeInscribirse($torneo, $usuario_id)
        : null;
    // Torneo por equipos: se inscribe un equipo, que arma su líder (RF-13)
    $mi_equipo = false;
    $motivo_no_equipo = null;
    if ($torneo['modalidad'] === 'equipo' && $usuario_id) {
        $modeloEquipo = new Equipo();
        $equipo_en_torneo = $modeloEquipo->equipoEnTorneo($id, $usuario_id);
        $mi_equipo = $equipo_en_torneo ? $modeloEquipo->buscarPorId($equipo_en_torneo['id']) : false;
        $motivo_no_equipo = $mi_equipo ? null : $modeloEquipo->motivoNoPuedeCrear($torneo, $usuario_id);
    }

    $mostrar_inscripcion = !$es_organizador && ($torneo['estado'] === 'publicado' || $mi_inscripcion || $mi_equipo);

    // Lo que ve quien gestiona: las inscripciones a revisar
    if ($puede_gestionar && $torneo['estado'] === 'publicado') {
        $pendientes = $modeloParticipante->listarPorEstado($id, 'pendiente');
        $conteo = $modeloParticipante->contarPorEstado($id);
    }

    if ($torneo['tipo'] === 'eliminacion') {
        // Llave completa: las rondas ya generadas y, para las que faltan, cruces "Por definir".
        // Con n participantes hay log2(n) rondas, y cada una tiene la mitad de cruces que la anterior.
        $llave = [];
        $total_rondas = count($participantes) >= 2 ? (int) ceil(log(count($participantes), 2)) : count($rondas);
        for ($k = 1; $k <= max($total_rondas, count($rondas)); $k++) {
            if (isset($rondas[$k - 1])) {
                $enfrentamientos = $rondas[$k - 1]['enfrentamientos'];
            } else {
                $enfrentamientos = array_fill(0, (int) pow(2, $total_rondas - $k), null);
            }
            $llave[] = ['cruces' => count($enfrentamientos), 'enfrentamientos' => $enfrentamientos];
        }

        // Campeón: el ganador de la final, si ya se jugó
        $campeon = null;
        $ultima = end($llave);
        if ($ultima && $ultima['cruces'] === 1 && $ultima['enfrentamientos'][0]) {
            $final = $ultima['enfrentamientos'][0];
            if ($final['score_local'] !== null && $final['score_local'] != $final['score_visitante']) {
                $campeon = $final['score_local'] > $final['score_visitante'] ? $final['local'] : $final['visitante'];
            }
        }
    } else {
        $posiciones = $modelo->listarPosiciones($id);
        // Antes de la primera ronda no hay tabla: se muestra a todos en cero
        if (!$posiciones && $participantes) {
            foreach ($participantes as $p) {
                $posiciones[] = ['nombre' => $p['nombre'], 'pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0, 'puntos' => 0];
            }
        }

        // Última ronda con algún resultado cargado (puede estar a medio jugar)
        $ultima_jugada = null;
        foreach ($rondas as $r) {
            foreach ($r['enfrentamientos'] as $en) {
                if ($en['score_local'] !== null) {
                    $ultima_jugada = $r['numero'];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $torneo ? e($torneo['nombre']) : 'Torneo no encontrado' ?> — Tornea</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/tornea/css/style.css" />
  <link rel="stylesheet" href="/tornea/css/torneos.css?v=3" />
  <link rel="stylesheet" href="/tornea/css/torneo-detalle.css?v=7" />
  <link rel="stylesheet" href="/tornea/css/auth.css?v=3" />
  <link rel="stylesheet" href="/tornea/css/equipos.css?v=1" />
</head>
<body>

  <?php $pagina_actual = 'torneos'; include __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="torneo-detalle-hero">
      <div class="container">
        <a href="<?= $volver['url'] ?>" class="volver-link">← <?= $volver['texto'] ?></a>

        <?php if (!$torneo): ?>
          <h1 class="torneo-detalle-nombre">Torneo no encontrado</h1>
          <p class="torneo-detalle-descripcion">El torneo que buscás no existe o todavía no fue publicado.</p>
        <?php else: ?>
          <div class="torneo-detalle-top">
            <span class="torneo-badge"><?= iconoDeporte($torneo['deporte']) ?> <?= e($torneo['deporte']) ?></span>
            <span class="torneo-estado <?= claseEstado($torneo['estado']) ?>"><?= etiquetaEstado($torneo['estado']) ?></span>
          </div>

          <h1 class="torneo-detalle-nombre"><?= e($torneo['nombre']) ?></h1>

          <ul class="torneo-detalle-datos">
            <li><strong>Tipo:</strong> <?= etiquetaTipo($torneo['tipo']) ?></li>
            <li><strong>Fechas:</strong> <?= formatearFechas($torneo['fecha_inicio'], $torneo['fecha_fin']) ?></li>
            <li><strong>Modalidad:</strong> <?= $modalidad ?></li>
            <li><strong>Organiza:</strong> <?= e(implode(', ', array_column($organizadores, 'nombre'))) ?></li>
          </ul>

          <?php if (trim((string) $torneo['descripcion']) !== ''): ?>
            <p class="torneo-detalle-descripcion"><?= nl2br(e($torneo['descripcion'])) ?></p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($torneo): ?>

      <?php if (isset($_SESSION['error']) || isset($_SESSION['exito'])): ?>
        <div class="container detalle-mensajes">
          <?php if (isset($_SESSION['error'])): ?>
            <p class="mensaje mensaje-error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></p>
          <?php endif; ?>
          <?php if (isset($_SESSION['exito'])): ?>
            <p class="mensaje mensaje-exito"><?= e($_SESSION['exito']); unset($_SESSION['exito']); ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($mostrar_inscripcion): ?>
        <!-- ===== Inscripción del participante ===== -->
        <section class="gestion-section">
          <div class="container">
            <div class="gestion-panel inscripcion-panel">
              <h2 class="gestion-titulo">Inscripción</h2>

              <?php if (!$usuario_id): ?>
                <p class="gestion-ayuda">La inscripción está abierta.
                  <a href="/tornea/app/views/login.php" class="form-link form-link-strong">Iniciá sesión</a> para anotarte.</p>

              <?php elseif ($torneo['modalidad'] === 'equipo'): ?>
                <?php if ($mi_equipo): ?>
                  <p class="gestion-ayuda">
                    <?php if ($mi_equipo['estado_inscripcion']): ?>
                      <span class="inscripcion-estado inscripcion-<?= e($mi_equipo['estado_inscripcion']) ?>"><?= ucfirst(e($mi_equipo['estado_inscripcion'])) ?></span>
                    <?php endif; ?>
                    Estás en el equipo <strong><?= e($mi_equipo['nombre']) ?></strong>.
                  </p>
                  <div class="gestion-acciones">
                    <a href="/tornea/app/views/equipo.php?id=<?= (int) $mi_equipo['id'] ?>" class="btn btn-outline">Ver mi equipo</a>
                  </div>
                <?php elseif ($motivo_no_equipo): ?>
                  <p class="gestion-ayuda"><?= e($motivo_no_equipo) ?></p>
                <?php else: ?>
                  <p class="gestion-ayuda">Este torneo es por equipos. Creá tu equipo: vas a ser el líder y después invitás a tus compañeros por email. El organizador aprueba la inscripción.</p>
                  <form class="auth-form equipo-form" action="/tornea/app/controllers/EquipoController.php?accion=crear" method="post">
                    <input type="hidden" name="torneo_id" value="<?= $id ?>" />
                    <div class="form-group">
                      <label for="nombre-equipo">Nombre del equipo</label>
                      <input type="text" id="nombre-equipo" name="nombre" maxlength="150" placeholder="Ej: Los Halcones" required />
                    </div>
                    <button type="submit" class="btn btn-gradient">Inscribir mi equipo</button>
                  </form>
                <?php endif; ?>

              <?php elseif ($mi_inscripcion): ?>
                <?php
                  $textos = [
                      'pendiente' => 'Estás anotado. Tu inscripción está pendiente hasta que el organizador la revise.',
                      'aprobado' => 'Tu inscripción fue aprobada: estás participando en este torneo.',
                      'rechazado' => 'El organizador rechazó tu inscripción.',
                  ];
                ?>
                <p class="gestion-ayuda">
                  <span class="inscripcion-estado inscripcion-<?= e($mi_inscripcion['estado']) ?>"><?= ucfirst(e($mi_inscripcion['estado'])) ?></span>
                  <?= $textos[$mi_inscripcion['estado']] ?>
                </p>
                <?php if ($torneo['estado'] === 'publicado' && $mi_inscripcion['estado'] !== 'rechazado'): ?>
                  <div class="gestion-acciones">
                    <form action="/tornea/app/controllers/InscripcionController.php?accion=cancelar" method="post"
                          onsubmit="return confirm('¿Seguro que querés cancelar tu inscripción?');">
                      <input type="hidden" name="torneo_id" value="<?= $id ?>" />
                      <button type="submit" class="btn btn-peligro">Cancelar inscripción</button>
                    </form>
                  </div>
                <?php endif; ?>

              <?php elseif ($motivo_no_inscribir): ?>
                <p class="gestion-ayuda"><?= e($motivo_no_inscribir) ?></p>

              <?php else: ?>
                <p class="gestion-ayuda">La inscripción está abierta. Cuando te anotes, el organizador tiene que aprobarte.</p>
                <div class="gestion-acciones">
                  <form action="/tornea/app/controllers/InscripcionController.php?accion=inscribirse" method="post">
                    <input type="hidden" name="torneo_id" value="<?= $id ?>" />
                    <button type="submit" class="btn btn-gradient">Inscribirme</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($puede_gestionar): ?>
        <!-- ===== Panel de gestión: solo lo ven sus organizadores y los administradores (RF-07) ===== -->
        <section class="gestion-section">
          <div class="container">
            <div class="gestion-panel">
              <h2 class="gestion-titulo">Gestionar torneo</h2>

              <?php if ($gestiona_como_admin): ?>
                <p class="gestion-admin">Estás gestionando este torneo como administrador. Los cambios quedan registrados a tu nombre.</p>
              <?php endif; ?>

              <p class="gestion-ayuda"><?= $ayuda_gestion ?></p>

              <?php if (Torneo::sePuedeEditar($torneo)): ?>
                <div class="gestion-acciones">
                  <a href="/tornea/app/views/editar-torneo.php?id=<?= $id ?>" class="btn btn-outline">Editar datos</a>

                  <?php if ($torneo['estado'] === 'borrador'): ?>
                    <form action="/tornea/app/controllers/TorneoController.php?accion=publicar" method="post">
                      <input type="hidden" name="id" value="<?= $id ?>" />
                      <input type="hidden" name="volver" value="detalle" />
                      <button type="submit" class="btn btn-gradient">Publicar</button>
                    </form>
                    <form action="/tornea/app/controllers/TorneoController.php?accion=eliminar" method="post"
                          onsubmit="return confirm('¿Estás seguro de que querés eliminar este torneo? Esta acción no se puede deshacer.');">
                      <input type="hidden" name="id" value="<?= $id ?>" />
                      <input type="hidden" name="volver" value="detalle" />
                      <button type="submit" class="btn btn-peligro">Eliminar</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php if ($torneo['estado'] === 'publicado'): ?>
                <div class="gestion-inscripciones">
                  <h3 class="gestion-subtitulo">Inscripciones</h3>
                  <p class="gestion-ayuda">
                    <?= $conteo['aprobado'] ?> aprobadas · <?= $conteo['pendiente'] ?> pendientes · <?= $conteo['rechazado'] ?> rechazadas
                  </p>

                  <?php if ($pendientes): ?>
                    <ul class="inscripciones-lista">
                      <?php foreach ($pendientes as $p): ?>
                        <li class="inscripcion-item">
                          <div class="inscripcion-info">
                            <span class="participante-nombre"><?= e($p['nombre']) ?></span>
                            <span class="participante-extra">
                              <?php if ($p['miembros'] !== null): ?><?= (int) $p['miembros'] ?> integrantes · <?php endif; ?>
                              Se anotó el <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                            </span>
                          </div>
                          <div class="inscripcion-botones">
                            <form action="/tornea/app/controllers/InscripcionController.php?accion=aprobar" method="post">
                              <input type="hidden" name="participante_id" value="<?= (int) $p['id'] ?>" />
                              <button type="submit" class="btn btn-gradient">Aprobar</button>
                            </form>
                            <form action="/tornea/app/controllers/InscripcionController.php?accion=rechazar" method="post"
                                  onsubmit="return confirm('¿Rechazar la inscripción de <?= e(addslashes($p['nombre'])) ?>?');">
                              <input type="hidden" name="participante_id" value="<?= (int) $p['id'] ?>" />
                              <button type="submit" class="btn btn-peligro">Rechazar</button>
                            </form>
                          </div>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php else: ?>
                    <p class="gestion-ayuda">No hay inscripciones pendientes de revisar.</p>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($torneo['tipo'] === 'eliminacion'): ?>
        <!-- ===== Eliminación directa: llaves ===== -->
        <section class="bracket-section">
          <div class="container">
            <h2 class="section-title">Llaves del torneo</h2>
            <?php if ($campeon): ?>
              <p class="section-subtitle">🏆 Campeón: <strong><?= e($campeon) ?></strong></p>
            <?php elseif ($rondas): ?>
              <p class="section-subtitle">Resultado y estado de cada cruce, ronda por ronda.</p>
            <?php endif; ?>

            <?php if ($llave): ?>
              <div class="bracket">
                <?php foreach ($llave as $ronda): ?>
                  <div class="bracket-round">
                    <h3 class="bracket-round-title"><?= nombreRondaEliminacion($ronda['cruces']) ?></h3>
                    <div class="bracket-matches">
                      <?php $es_final = $ronda['cruces'] === 1; ?>
                      <?php foreach ($ronda['enfrentamientos'] as $en): ?>
                        <?php include __DIR__ . '/partials/match-card.php'; ?>
                      <?php endforeach; ?>
                      <?php $es_final = false; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="detalle-vacio"><?= $mensaje_sin_rondas ?></p>
            <?php endif; ?>
          </div>
        </section>

      <?php else: ?>
        <!-- ===== Liga y sistema suizo: tabla + rondas ===== -->
        <section class="posiciones-section">
          <div class="container">
            <h2 class="section-title"><?= $torneo['tipo'] === 'liga' ? 'Tabla de posiciones' : 'Clasificación' ?></h2>
            <?php if ($ultima_jugada): ?>
              <p class="section-subtitle">Con los resultados cargados hasta la <?= $prefijo_ronda ?> <?= $ultima_jugada ?>.</p>
            <?php endif; ?>

            <?php if ($posiciones): ?>
              <div class="tabla-posiciones-wrap">
                <table class="tabla-posiciones">
                  <thead>
                    <tr>
                      <th>Pos</th>
                      <th><?= $es_por_equipos ? 'Equipo' : 'Jugador' ?></th>
                      <th>PJ</th>
                      <th>PG</th>
                      <th>PE</th>
                      <th>PP</th>
                      <th>Pts</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($posiciones as $i => $fila): ?>
                      <tr<?= $i === 0 && $fila['pj'] > 0 ? ' class="fila-lider"' : '' ?>>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($fila['nombre']) ?></td>
                        <td><?= (int) $fila['pj'] ?></td>
                        <td><?= (int) $fila['pg'] ?></td>
                        <td><?= (int) $fila['pe'] ?></td>
                        <td><?= (int) $fila['pp'] ?></td>
                        <td><?= (int) $fila['puntos'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="detalle-vacio"><?= $mensaje_sin_rondas ?></p>
            <?php endif; ?>
          </div>
        </section>

        <?php if ($rondas): ?>
          <section class="calendario-section">
            <div class="container">
              <h2 class="section-title"><?= $torneo['tipo'] === 'liga' ? 'Calendario y resultados' : 'Emparejamientos' ?></h2>
              <p class="section-subtitle">
                <?= $torneo['tipo'] === 'liga'
                    ? 'Todos contra todos: partidos jugados y próximas fechas.'
                    : 'Cada ronda se arma enfrentando a quienes tienen puntajes parecidos, sin repetir rival.' ?>
              </p>

              <div class="calendario-fechas">
                <?php foreach ($rondas as $ronda): ?>
                  <div class="fecha-grupo">
                    <h3 class="bracket-round-title"><?= etiquetaRonda($prefijo_ronda, $ronda['numero'], $ronda['estado']) ?></h3>
                    <div class="bracket-matches">
                      <?php foreach ($ronda['enfrentamientos'] as $en): ?>
                        <?php include __DIR__ . '/partials/match-card.php'; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </section>
        <?php endif; ?>
      <?php endif; ?>

      <!-- ===== Participantes (todos los tipos) ===== -->
      <section class="participantes-section">
        <div class="container">
          <h2 class="section-title">Participantes</h2>
          <p class="section-subtitle">
            <?= count($participantes) ?> <?= $es_por_equipos ? 'equipos' : 'jugadores' ?> con la inscripción aprobada.
          </p>

          <?php if ($participantes): ?>
            <ul class="participantes-lista">
              <?php foreach ($participantes as $p): ?>
                <li class="participante-item">
                  <span class="participante-nombre"><?= e($p['nombre']) ?></span>
                  <?php if ($p['tipo'] === 'equipo'): ?>
                    <span class="participante-extra"><?= (int) $p['miembros'] ?> integrantes</span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="detalle-vacio">Todavía no hay participantes aprobados.</p>
          <?php endif; ?>
        </div>
      </section>

    <?php endif; ?>
  </main>

  <footer class="site-footer">
    <div class="container footer-inner">
      <div class="social-links">
        <a href="#" aria-label="Facebook" class="social-icon">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M13.5 21v-7.7h2.6l.4-3h-3v-1.9c0-.9.2-1.5 1.5-1.5h1.6V4.2C15.9 4.1 15 4 14 4c-2.4 0-4 1.5-4 4.1V10H7.4v3h2.6v8h3.5z"/></svg>
        </a>
        <a href="#" aria-label="Instagram" class="social-icon">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
        </a>
        <a href="#" aria-label="YouTube" class="social-icon">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M22 12s0-3.2-.4-4.7a2.5 2.5 0 0 0-1.8-1.8C18.3 5 12 5 12 5s-6.3 0-7.8.5a2.5 2.5 0 0 0-1.8 1.8C2 8.8 2 12 2 12s0 3.2.4 4.7a2.5 2.5 0 0 0 1.8 1.8C5.7 19 12 19 12 19s6.3 0 7.8-.5a2.5 2.5 0 0 0 1.8-1.8c.4-1.5.4-4.7.4-4.7zM10 15.3V8.7l5.8 3.3L10 15.3z"/></svg>
        </a>
      </div>
      <p class="copyright">© 2026 Terobyte</p>
    </div>
  </footer>

</body>
</html>
