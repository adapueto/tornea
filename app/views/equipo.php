<?php
session_start();
require_once __DIR__ . '/../models/equipo.php';
require_once __DIR__ . '/../helpers/formato.php';

// Página de un equipo: integrantes, torneos en los que está inscripto, invitaciones
// y acciones del líder (RF-13 a RF-17). La ven sus integrantes, los administradores
// y los organizadores de los torneos en los que juega.

if (!isset($_SESSION['usuario'])) {
    $_SESSION['error'] = 'Tenés que iniciar sesión para ver un equipo';
    header('Location: /tornea/app/views/login.php');
    exit;
}

$usuario_id = $_SESSION['usuario']['id'];
$modelo = new Equipo();
$equipo = $modelo->buscarPorId((int) ($_GET['id'] ?? 0));

if (!$equipo || !$modelo->puedeVer($equipo, $usuario_id)) {
    $_SESSION['error'] = 'Ese equipo no existe o no sos parte de él';
    header('Location: /tornea/app/views/equipos.php');
    exit;
}

$equipo_id = (int) $equipo['id'];
$miembros = $modelo->listarMiembros($equipo_id);
$inscripciones = $modelo->listarInscripciones($equipo_id);
$es_lider = Equipo::esLider($equipo, $usuario_id);
$es_miembro = $modelo->esMiembro($equipo_id, $usuario_id);
$invitaciones = $es_lider ? $modelo->listarInvitacionesPendientes($equipo_id) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($equipo['nombre']) ?> — Tornea</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/tornea/css/style.css?v=5" />
  <link rel="stylesheet" href="/tornea/css/torneos.css?v=3" />
  <link rel="stylesheet" href="/tornea/css/torneo-detalle.css?v=7" />
  <link rel="stylesheet" href="/tornea/css/auth.css?v=3" />
  <link rel="stylesheet" href="/tornea/css/equipos.css?v=2" />
</head>
<body>

  <?php $pagina_actual = 'equipos'; include __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="torneo-detalle-hero">
      <div class="container">
        <a href="/tornea/app/views/equipos.php" class="volver-link">← Mis equipos</a>

        <h1 class="torneo-detalle-nombre"><?= e($equipo['nombre']) ?></h1>

        <ul class="torneo-detalle-datos">
          <li><strong>Líder:</strong> <?= e($equipo['lider_nombre']) ?></li>
          <li><strong>Integrantes:</strong> <?= count($miembros) ?></li>
          <li><strong>Torneos:</strong> <?= count($inscripciones) ?></li>
        </ul>
      </div>
    </section>

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

    <section class="gestion-section">
      <div class="container equipo-layout">

        <div class="gestion-panel">
          <h2 class="gestion-titulo">Integrantes</h2>
          <ul class="inscripciones-lista">
            <?php foreach ($miembros as $m): ?>
              <li class="inscripcion-item">
                <div class="inscripcion-info">
                  <span class="participante-nombre"><?= e($m['nombre']) ?></span>
                  <?php if ($m['es_lider']): ?><span class="participante-extra">Líder</span><?php endif; ?>
                </div>
                <?php if ($es_lider && !$m['es_lider']): ?>
                  <form action="/tornea/app/controllers/EquipoController.php?accion=quitar" method="post"
                        onsubmit="return confirm('¿Sacar a <?= e(addslashes($m['nombre'])) ?> del equipo?');">
                    <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>" />
                    <input type="hidden" name="miembro_id" value="<?= (int) $m['id'] ?>" />
                    <button type="submit" class="btn btn-peligro">Quitar</button>
                  </form>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="gestion-panel">
          <h2 class="gestion-titulo">Torneos</h2>
          <?php if ($inscripciones): ?>
            <ul class="inscripciones-lista">
              <?php foreach ($inscripciones as $ins): ?>
                <li class="inscripcion-item">
                  <div class="inscripcion-info">
                    <a href="/tornea/app/views/torneo-detalle.php?id=<?= (int) $ins['torneo_id'] ?>" class="participante-nombre"><?= e($ins['torneo_nombre']) ?></a>
                    <span class="participante-extra">
                      <?= etiquetaEstado($ins['torneo_estado']) ?> · <?= formatearFechas($ins['fecha_inicio'], $ins['fecha_fin']) ?>
                    </span>
                  </div>
                  <div class="inscripcion-botones">
                    <span class="inscripcion-estado inscripcion-<?= e($ins['estado_inscripcion']) ?>"><?= ucfirst(e($ins['estado_inscripcion'])) ?></span>
                    <?php if ($es_lider && $ins['torneo_estado'] === 'publicado'): ?>
                      <form action="/tornea/app/controllers/EquipoController.php?accion=cancelar_inscripcion" method="post"
                            onsubmit="return confirm('¿Sacar al equipo de este torneo?');">
                        <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>" />
                        <input type="hidden" name="torneo_id" value="<?= (int) $ins['torneo_id'] ?>" />
                        <button type="submit" class="btn btn-outline">Cancelar</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="gestion-ayuda">Todavía no está inscripto en ningún torneo.</p>
          <?php endif; ?>
          <?php if ($es_lider): ?>
            <p class="gestion-ayuda equipo-subtitulo">
              Para inscribirlo, entrá a un <a href="/tornea/app/views/torneos.php" class="form-link form-link-strong">torneo por equipos</a>
              publicado y elegí este equipo.
            </p>
          <?php endif; ?>
        </div>

        <?php if ($es_lider): ?>
          <div class="gestion-panel">
            <h2 class="gestion-titulo">Invitar integrantes</h2>
            <p class="gestion-ayuda">Escribí el email con el que se registró en Tornea. La invitación le aparece en "Equipos" y la acepta una sola vez.</p>
            <form class="auth-form equipo-form" action="/tornea/app/controllers/EquipoController.php?accion=invitar" method="post">
              <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>" />
              <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="compañera@ejemplo.com" required />
              </div>
              <button type="submit" class="btn btn-gradient">Invitar</button>
            </form>

            <?php if ($invitaciones): ?>
              <h3 class="gestion-subtitulo equipo-subtitulo">Esperando respuesta</h3>
              <ul class="inscripciones-lista">
                <?php foreach ($invitaciones as $inv): ?>
                  <li class="inscripcion-item">
                    <div class="inscripcion-info">
                      <span class="participante-nombre"><?= e($inv['nombre']) ?></span>
                      <span class="participante-extra">Invitado el <?= date('d/m/Y', strtotime($inv['created_at'])) ?></span>
                    </div>
                    <form action="/tornea/app/controllers/EquipoController.php?accion=cancelar_invitacion" method="post">
                      <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>" />
                      <input type="hidden" name="invitacion_id" value="<?= (int) $inv['id'] ?>" />
                      <button type="submit" class="btn btn-outline">Cancelar</button>
                    </form>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

          <div class="gestion-panel">
            <h2 class="gestion-titulo">Nombre del equipo</h2>
            <form class="auth-form equipo-form" action="/tornea/app/controllers/EquipoController.php?accion=renombrar" method="post">
              <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>" />
              <div class="form-group">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" maxlength="150" value="<?= e($equipo['nombre']) ?>" required />
              </div>
              <button type="submit" class="btn btn-outline">Guardar nombre</button>
            </form>
          </div>
        <?php endif; ?>

        <?php if ($es_lider || $es_miembro): ?>
          <div class="gestion-panel">
            <h2 class="gestion-titulo"><?= $es_lider ? 'Dar de baja el equipo' : 'Salir del equipo' ?></h2>
            <?php if ($es_lider): ?>
              <p class="gestion-ayuda">Se borra el equipo con sus integrantes, invitaciones e inscripciones en torneos que todavía no empezaron. Si el equipo ya jugó algún torneo no se puede borrar, para no perder sus resultados.</p>
              <form action="/tornea/app/controllers/EquipoController.php?accion=baja" method="post"
                    onsubmit="return confirm('¿Seguro que querés dar de baja el equipo? Esta acción no se puede deshacer.');">
                <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>" />
                <button type="submit" class="btn btn-peligro">Dar de baja el equipo</button>
              </form>
            <?php else: ?>
              <p class="gestion-ayuda">Dejás de ser parte del equipo. El líder te puede volver a invitar.</p>
              <form action="/tornea/app/controllers/EquipoController.php?accion=salir" method="post"
                    onsubmit="return confirm('¿Seguro que querés salir del equipo?');">
                <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>" />
                <button type="submit" class="btn btn-peligro">Salir del equipo</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      </div>
    </section>
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
