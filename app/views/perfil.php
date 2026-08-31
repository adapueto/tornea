<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: /tornea/app/views/login.php');
    exit;
}

$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mi Perfil — Tornea</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/tornea/css/style.css" />
  <link rel="stylesheet" href="/tornea/css/torneos.css" />
  <link rel="stylesheet" href="/tornea/css/perfil.css?v=2" />
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
        <a href="/tornea/app/controllers/UsuarioController.php?accion=logout" class="btn btn-outline">Cerrar Sesión</a>
      </nav>
    </div>
  </header>

  <main>
    <section class="perfil-section">
      <div class="container perfil-layout">

        <div class="perfil-card">

          <div class="perfil-avatar">
            <?= strtoupper(substr($usuario['nombre'], 0, 1) . substr($usuario['apellido'], 0, 1)) ?>
          </div>

          <h1 class="perfil-nombre"><?= $usuario['nombre'] . ' ' . $usuario['apellido'] ?></h1>

          <div class="perfil-badges">
            <span class="perfil-badge"><?= ucfirst($usuario['rol']) ?></span>
            <?php if ($usuario['perfil_publico']): ?>
              <span class="perfil-badge perfil-badge-publico">Perfil público</span>
            <?php else: ?>
              <span class="perfil-badge perfil-badge-privado">Perfil privado</span>
            <?php endif; ?>
          </div>

          <div class="perfil-info">
            <div class="perfil-info-item">
              <span class="perfil-info-label">Correo electrónico</span>
              <span class="perfil-info-value"><?= $usuario['email'] ?></span>
            </div>
            <?php if (!empty($usuario['fecha_nac'])): ?>
              <div class="perfil-info-item">
                <span class="perfil-info-label">Fecha de nacimiento</span>
                <span class="perfil-info-value"><?= date('d/m/Y', strtotime($usuario['fecha_nac'])) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <a href="/tornea/app/views/perfil-editar.php" class="btn btn-gradient btn-lg btn-block">EDITAR PERFIL</a>
        </div>

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