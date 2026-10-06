<?php
session_start();
require_once __DIR__ . '/../models/torneo.php';
require_once __DIR__ . '/../helpers/formato.php';

// Solo un usuario con sesión iniciada puede crear torneos
if (!isset($_SESSION['usuario'])) {
    $_SESSION['error'] = 'Tenés que iniciar sesión para crear un torneo';
    header('Location: /tornea/app/views/login.php');
    exit;
}

// Datos del intento anterior (si hubo un error) para volver a llenar el formulario
$form = $_SESSION['form_torneo'] ?? [];
unset($_SESSION['form_torneo']);

// Fecha mínima para los campos de fecha: no se puede crear un torneo que empiece en el pasado
$hoy = (new Torneo())->fechaHoy();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Crear Torneo — Tornea</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/tornea/css/style.css" />
  <link rel="stylesheet" href="/tornea/css/auth.css" />
</head>
<body>

  <header class="site-header">
    <div class="container header-inner">
      <a href="/tornea/index.php" class="logo">
        <img src="/tornea/img/logo.png" alt="Tornea" class="logo-icon" />
        <img src="/tornea/img/TORNEA_logo.png" alt="Tornea" class="logo-wordmark">
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
    <section class="auth-section">
      <div class="auth-card auth-card-wide">
        <img src="/tornea/img/logo.png" alt="Tornea" class="auth-logo-icon" />

        <h1 class="auth-title">Creá tu torneo</h1>
        <p class="auth-subtitle">Completá los datos básicos para arrancar</p>

        <?php if (isset($_SESSION['error'])): ?>
          <p style="color:red; margin-bottom: 12px;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
        <?php endif; ?>

        <?php if (isset($_SESSION['exito'])): ?>
          <p style="color:green; margin-bottom: 12px;"><?= $_SESSION['exito']; unset($_SESSION['exito']); ?></p>
        <?php endif; ?>

        <form class="auth-form" action="/tornea/app/controllers/TorneoController.php?accion=crear" method="post">
          <div class="form-group">
            <label for="nombre">Nombre del torneo</label>
            <input type="text" id="nombre" name="nombre" maxlength="150" placeholder="Ej: Liga Amateur de Fútbol 5" value="<?= e($form['nombre'] ?? '') ?>" required />
          </div>

          <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" placeholder="Contá de qué se trata el torneo..."><?= e($form['descripcion'] ?? '') ?></textarea>
          </div>

          <div class="form-group">
            <label for="deporte">Deporte</label>
            <select id="deporte" name="deporte" required>
              <option value="" disabled <?= empty($form['deporte']) ? 'selected' : '' ?>>Seleccioná un deporte</option>
              <?php foreach (Torneo::DEPORTES as $deporte): ?>
                <option value="<?= e($deporte) ?>" <?= ($form['deporte'] ?? '') === $deporte ? 'selected' : '' ?>><?= e($deporte) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="tipo">Tipo de torneo</label>
            <select id="tipo" name="tipo" required>
              <option value="" disabled <?= empty($form['tipo']) ? 'selected' : '' ?>>Seleccioná un tipo</option>
              <?php foreach (['liga' => 'Liga', 'eliminacion' => 'Eliminación directa', 'suizo' => 'Sistema suizo'] as $valor => $texto): ?>
                <option value="<?= $valor ?>" <?= ($form['tipo'] ?? '') === $valor ? 'selected' : '' ?>><?= $texto ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label for="fecha_inicio">Fecha de inicio</label>
              <input type="date" id="fecha_inicio" name="fecha_inicio" min="<?= $hoy ?>" value="<?= e($form['fecha_inicio'] ?? '') ?>" required />
            </div>

            <div class="form-group">
              <label for="fecha_fin">Fecha de finalización</label>
              <input type="date" id="fecha_fin" name="fecha_fin" min="<?= $hoy ?>" value="<?= e($form['fecha_fin'] ?? '') ?>" required />
            </div>
          </div>

          <button type="submit" class="btn btn-gradient btn-lg btn-block">CREAR TORNEO</button>
        </form>
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
