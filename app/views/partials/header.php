<?php
// Encabezado del sitio, compartido por todas las páginas.
// Opcional: $pagina_actual ('inicio', 'torneos', 'equipos', 'perfil', 'login', 'registro')
// para resaltar dónde está el usuario.

$pagina_actual = $pagina_actual ?? '';

// Invitaciones a equipos sin responder: se avisan al lado de "Equipos"
$invitaciones_pendientes = 0;
if (isset($_SESSION['usuario'])) {
    require_once __DIR__ . '/../../models/equipo.php';
    $invitaciones_pendientes = (new Equipo())->contarInvitacionesPendientes($_SESSION['usuario']['id']);
}
$activo = function ($pagina) use ($pagina_actual) {
    return $pagina === $pagina_actual ? ' nav-active' : '';
};
?>
  <header class="site-header">
    <div class="container header-inner">
      <a href="/tornea/index.php" class="logo">
        <img src="/tornea/img/logo.png" alt="Tornea" class="logo-icon" />
        <img src="/tornea/img/TORNEA_logo.png" alt="Tornea" class="logo-wordmark" />
      </a>

      <nav class="main-nav">
        <a href="/tornea/index.php" class="nav-link<?= $activo('inicio') ?>">Inicio</a>
        <a href="/tornea/app/views/torneos.php" class="nav-link<?= $activo('torneos') ?>">Torneos</a>
        <?php if (isset($_SESSION['usuario'])): ?>
          <a href="/tornea/app/views/equipos.php" class="nav-link<?= $activo('equipos') ?>">
            Equipos<?php if ($invitaciones_pendientes): ?> <span class="nav-aviso" title="Invitaciones sin responder"><?= $invitaciones_pendientes ?></span><?php endif; ?>
          </a>
          <a href="/tornea/app/views/perfil.php" class="btn btn-outline<?= $activo('perfil') ?>">
            <?= htmlspecialchars($_SESSION['usuario']['nombre'], ENT_QUOTES, 'UTF-8') ?>
          </a>
          <a href="/tornea/app/controllers/UsuarioController.php?accion=logout" class="btn btn-gradient">Cerrar Sesión</a>
        <?php else: ?>
          <a href="/tornea/app/views/login.php" class="btn btn-outline<?= $activo('login') ?>">Iniciar Sesión</a>
          <a href="/tornea/app/views/register.php" class="btn btn-gradient<?= $activo('registro') ?>">Registrarse</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
