<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Iniciar Sesión — Tornea</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/tornea/css/style.css" />
  <link rel="stylesheet" href="/tornea/css/auth.css" />
</head>
<body>

  <header class="site-header">
    <div class="container header-inner">
      <a href="/tornea/index.html" class="logo">
        <img src="/tornea/img/logo.png" alt="Tornea" class="logo-icon" />
        <span class="logo-text">Tornea</span>
      </a>
      <nav class="main-nav">
        <a href="/tornea/index.html" class="nav-link">Inicio</a>
        <a href="/tornea/torneos.html" class="nav-link">Torneos</a>
        <a href="/tornea/app/views/login.php" class="btn btn-outline nav-active">Iniciar Sesión</a>
        <a href="/tornea/app/views/register.php" class="btn btn-gradient">Registrarse</a>
      </nav>
    </div>
  </header>

  <main>
    <section class="auth-section">
      <div class="auth-card">
        <img src="/tornea/img/logo.png" alt="Tornea" class="auth-logo-icon" />

        <h1 class="auth-title">¡Bienvenido de nuevo!</h1>
        <p class="auth-subtitle">Iniciá sesión para seguir tus torneos</p>

        <?php if (isset($_SESSION['error'])): ?>
          <p style="color:red; margin-bottom: 12px;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
        <?php endif; ?>

        <?php if (isset($_SESSION['exito'])): ?>
          <p style="color:green; margin-bottom: 12px;"><?= $_SESSION['exito']; unset($_SESSION['exito']); ?></p>
        <?php endif; ?>

        <form class="auth-form" action="/tornea/app/controllers/UsuarioController.php?accion=login" method="post">
          <div class="form-group">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" placeholder="tuemail@ejemplo.com" required />
          </div>

          <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required />
          </div>

          <button type="submit" class="btn btn-gradient btn-lg btn-block">INICIAR SESIÓN</button>
        </form>

        <p class="auth-footer-text">
          ¿No tenés cuenta? <a href="/tornea/app/views/register.php" class="form-link form-link-strong">Registrate</a>
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