<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registrarse — Tornea</title>
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
        <img src="/tornea/img/TORNEA_logo.png" alt="Tornea" class="logo-wordmark" />
      </a>
      <nav class="main-nav">
        <a href="/tornea/index.php" class="nav-link">Inicio</a>
        <a href="/tornea/torneos.php" class="nav-link">Torneos</a>
        <a href="/tornea/app/views/login.php" class="btn btn-outline">Iniciar Sesión</a>
        <a href="/tornea/app/views/register.php" class="btn btn-gradient nav-active">Registrarse</a>
      </nav>
    </div>
  </header>

  <main>
    <section class="auth-section">
      <div class="auth-card">
        <img src="/tornea/img/logo.png" alt="Tornea" class="auth-logo-icon" />

        <h1 class="auth-title">¡Creá tu cuenta!</h1>
        <p class="auth-subtitle">Registrate para empezar a seguir tus torneos</p>

        <?php if (isset($_SESSION['error'])): ?>
          <p style="color:red; margin-bottom: 12px;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
        <?php endif; ?>

        <form class="auth-form" action="/tornea/app/controllers/UsuarioController.php?accion=registrar" method="post">
          <div class="form-grid-2">
            <div class="form-group">
              <label for="nombre">Nombre</label>
              <input type="text" id="nombre" name="nombre" placeholder="Tu nombre" required />
            </div>
            <div class="form-group">
              <label for="apellido">Apellido</label>
              <input type="text" id="apellido" name="apellido" placeholder="Tu apellido" required />
            </div>
          </div>

          <div class="form-group">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" placeholder="tuemail@ejemplo.com" required />
          </div>

          <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required />
          </div>

          <div class="form-group">
            <label for="password-repeat">Repetir contraseña</label>
            <input type="password" id="password-repeat" name="password-repeat" placeholder="••••••••" required />
          </div>

          <button type="submit" class="btn btn-gradient btn-lg btn-block">REGISTRARSE</button>
        </form>

        <p class="auth-footer-text">
          ¿Ya tenés cuenta? <a href="/tornea/app/views/login.php" class="form-link form-link-strong">Iniciá sesión</a>
        </p>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container footer-inner">
      <p class="copyright">© 2026 Tornea</p>
    </div>
  </footer>

</body>
</html>