<?php
session_start();
require_once __DIR__ . '/../models/torneo.php';
require_once __DIR__ . '/../helpers/formato.php';

// Detalle de un torneo: una sola vista para los tres tipos (liga, eliminación y suizo)

$modelo = new Torneo();
$id = (int) ($_GET['id'] ?? 0);
$torneo = $modelo->buscarPorId($id);

// Los borradores solo los ven sus organizadores
$usuario_id = $_SESSION['usuario']['id'] ?? 0;
if ($torneo && $torneo['estado'] === 'borrador' && !$modelo->esOrganizador($id, $usuario_id)) {
    $torneo = null;
}

if (!$torneo) {
    http_response_code(404);
} else {
    $organizadores = $modelo->listarOrganizadores($id);
    $participantes = $modelo->listarParticipantes($id);
    $rondas = $modelo->listarRondas($id);

    $es_por_equipos = in_array('equipo', array_column($participantes, 'tipo'), true);
    $modalidad = !$participantes ? 'A definir' : ($es_por_equipos ? 'Por equipos' : 'Individual');
    $prefijo_ronda = $torneo['tipo'] === 'liga' ? 'Fecha' : 'Ronda';

    $mensajes = [
        'borrador' => 'El torneo todavía es un borrador: publicalo para abrir la inscripción.',
        'publicado' => 'Las rondas se generan cuando arranca el torneo.',
        'en_curso' => 'Todavía no se generaron las rondas.',
        'finalizado' => 'Este torneo no tiene rondas registradas.',
    ];
    $mensaje_sin_rondas = $mensajes[$torneo['estado']];

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
  <link rel="stylesheet" href="/tornea/css/torneos.css?v=2" />
  <link rel="stylesheet" href="/tornea/css/torneo-detalle.css?v=3" />
</head>
<body>

  <header class="site-header">
    <div class="container header-inner">
      <a href="/tornea/index.php" class="logo">
        <img src="/tornea/img/logo.png" alt="Tornea" class="logo-icon" />
        <img src="/tornea/img/TORNEA_logo.png" alt="Tornea" class="logo-wordmark" />
      </a>

      <nav class="main-nav">
        <a href="/tornea/index.php" class="nav-link">Inicio</a>
        <a href="/tornea/app/views/torneos.php" class="nav-link">Torneos</a>
        <?php if (isset($_SESSION['usuario'])): ?>
          <a href="/tornea/app/views/perfil.php" class="btn btn-outline">
            <?= $_SESSION['usuario']['nombre'] ?>
          </a>
          <a href="/tornea/app/controllers/UsuarioController.php?accion=logout" class="btn btn-gradient">Cerrar Sesión</a>
        <?php else: ?>
          <a href="/tornea/app/views/login.php" class="btn btn-outline">Iniciar Sesión</a>
          <a href="/tornea/app/views/register.php" class="btn btn-gradient">Registrarse</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main>
    <section class="torneo-detalle-hero">
      <div class="container">
        <a href="/tornea/app/views/torneos.php" class="volver-link">← Volver a torneos</a>

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
