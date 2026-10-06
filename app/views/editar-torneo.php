<?php
session_start();
require_once __DIR__ . '/../models/torneo.php';
require_once __DIR__ . '/../helpers/formato.php';

// Editar un torneo (RF-19): solo sus organizadores, y solo si es borrador o publicado

if (!isset($_SESSION['usuario'])) {
    $_SESSION['error'] = 'Tenés que iniciar sesión para editar un torneo';
    header('Location: /tornea/app/views/login.php');
    exit;
}

$modelo = new Torneo();
$torneo_id = (int) ($_GET['id'] ?? 0);
$torneo = $modelo->buscarPorId($torneo_id);

if (!$torneo || !$modelo->puedeGestionar($torneo_id, $_SESSION['usuario']['id'])) {
    $_SESSION['error'] = 'Solo un organizador del torneo o un administrador puede editarlo';
    header('Location: /tornea/app/views/perfil.php');
    exit;
}

if (!Torneo::sePuedeEditar($torneo)) {
    $_SESSION['error'] = 'Un torneo en curso o finalizado ya no se puede modificar';
    header('Location: /tornea/app/views/torneo-detalle.php?id=' . $torneo_id);
    exit;
}

// Si hubo un error se muestran los datos que se habían escrito; si no, los guardados
$form = $_SESSION['form_torneo'] ?? $torneo;
unset($_SESSION['form_torneo']);

$bloquear_formato = Torneo::formatoBloqueado($torneo);
if ($bloquear_formato) {
    $form['deporte'] = $torneo['deporte'];
    $form['tipo'] = $torneo['tipo'];
    $form['modalidad'] = $torneo['modalidad'];
}

$hoy = $modelo->fechaHoy();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Editar <?= e($torneo['nombre']) ?> — Tornea</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/tornea/css/style.css" />
  <link rel="stylesheet" href="/tornea/css/auth.css?v=3" />
</head>
<body>

  <?php $pagina_actual = 'torneos'; include __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="auth-section">
      <div class="auth-card auth-card-wide">
        <img src="/tornea/img/logo.png" alt="Tornea" class="auth-logo-icon" />

        <h1 class="auth-title">Editar torneo</h1>
        <p class="auth-subtitle"><?= e($torneo['nombre']) ?> · <?= etiquetaEstado($torneo['estado']) ?></p>

        <?php if (isset($_SESSION['error'])): ?>
          <p style="color:red; margin-bottom: 12px;"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></p>
        <?php endif; ?>

        <?php if (isset($_SESSION['exito'])): ?>
          <p style="color:green; margin-bottom: 12px;"><?= $_SESSION['exito']; unset($_SESSION['exito']); ?></p>
        <?php endif; ?>

        <?php
          $accion_form = '/tornea/app/controllers/TorneoController.php?accion=editar';
          $texto_boton = 'GUARDAR CAMBIOS';
          include __DIR__ . '/partials/form-torneo.php';
        ?>

        <p class="auth-footer-text">
          <a href="/tornea/app/views/torneo-detalle.php?id=<?= $torneo_id ?>" class="form-link">Cancelar y volver al torneo</a>
        </p>
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
