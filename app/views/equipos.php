<?php
session_start();
require_once __DIR__ . '/../models/equipo.php';
require_once __DIR__ . '/../helpers/formato.php';

// "Mis equipos": invitaciones recibidas, crear un equipo y los equipos del usuario (RF-13, RF-17)

if (!isset($_SESSION['usuario'])) {
    $_SESSION['error'] = 'Tenés que iniciar sesión para ver tus equipos';
    header('Location: /tornea/app/views/login.php');
    exit;
}

$usuario_id = $_SESSION['usuario']['id'];
$modelo = new Equipo();
$invitaciones = $modelo->listarInvitacionesDeUsuario($usuario_id);
$equipos = $modelo->listarDeUsuario($usuario_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mis equipos — Tornea</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/tornea/css/style.css?v=4" />
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
        <h1 class="torneo-detalle-nombre">Mis equipos</h1>
        <p class="torneo-detalle-descripcion">
          Armás el equipo una sola vez e invitás a tus compañeros. Después lo inscribís en todos los torneos
          por equipos que quieras, sin volver a invitar a nadie.
        </p>
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

    <?php if ($invitaciones): ?>
      <section class="gestion-section">
        <div class="container">
          <div class="gestion-panel invitaciones-panel">
            <h2 class="gestion-titulo">Te invitaron (<?= count($invitaciones) ?>)</h2>
            <ul class="inscripciones-lista">
              <?php foreach ($invitaciones as $inv): ?>
                <li class="inscripcion-item">
                  <div class="inscripcion-info">
                    <span class="participante-nombre"><?= e($inv['equipo_nombre']) ?></span>
                    <span class="participante-extra">
                      Te invitó <?= e($inv['lider_nombre']) ?> · <?= (int) $inv['miembros'] ?> integrantes
                    </span>
                  </div>
                  <div class="inscripcion-botones">
                    <form action="/tornea/app/controllers/EquipoController.php?accion=aceptar" method="post">
                      <input type="hidden" name="invitacion_id" value="<?= (int) $inv['id'] ?>" />
                      <button type="submit" class="btn btn-gradient">Aceptar</button>
                    </form>
                    <form action="/tornea/app/controllers/EquipoController.php?accion=rechazar" method="post">
                      <input type="hidden" name="invitacion_id" value="<?= (int) $inv['id'] ?>" />
                      <button type="submit" class="btn btn-peligro">Rechazar</button>
                    </form>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <section class="gestion-section">
      <div class="container">
        <div class="gestion-panel">
          <h2 class="gestion-titulo">Crear un equipo</h2>
          <p class="gestion-ayuda">Vas a ser el líder: invitás a los integrantes y lo inscribís en los torneos.</p>
          <form class="auth-form equipo-form" action="/tornea/app/controllers/EquipoController.php?accion=crear" method="post">
            <div class="form-group">
              <label for="nombre">Nombre del equipo</label>
              <input type="text" id="nombre" name="nombre" maxlength="150" placeholder="Ej: Las Gurisas Vóley" required />
            </div>
            <button type="submit" class="btn btn-gradient">Crear equipo</button>
          </form>
        </div>
      </div>
    </section>

    <section class="participantes-section">
      <div class="container">
        <h2 class="section-title">Equipos en los que estoy</h2>

        <?php if ($equipos): ?>
          <ul class="participantes-lista equipos-lista">
            <?php foreach ($equipos as $eq): ?>
              <li class="participante-item">
                <div class="inscripcion-info">
                  <a href="/tornea/app/views/equipo.php?id=<?= (int) $eq['id'] ?>" class="participante-nombre"><?= e($eq['nombre']) ?></a>
                  <span class="participante-extra">
                    <?= (int) $eq['lider_id'] === (int) $usuario_id ? 'Sos el líder' : 'Sos integrante' ?> · <?= (int) $eq['miembros'] ?> integrantes
                  </span>
                </div>
                <span class="participante-extra">
                  <?= (int) $eq['torneos_activos'] === 1 ? '1 torneo activo' : (int) $eq['torneos_activos'] . ' torneos activos' ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="detalle-vacio">Todavía no estás en ningún equipo. Creá uno o pedile al líder de tu equipo que te invite.</p>
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
