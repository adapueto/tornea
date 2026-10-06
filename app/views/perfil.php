<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: /tornea/app/views/login.php');
    exit;
}

require_once __DIR__ . '/../models/torneo.php';
require_once __DIR__ . '/../helpers/formato.php';

$usuario = $_SESSION['usuario'];

$modeloTorneo = new Torneo();
$mis_torneos = $modeloTorneo->listarPorOrganizador($usuario['id']);
$participaciones = $modeloTorneo->listarParticipaciones($usuario['id']);
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
  <link rel="stylesheet" href="/tornea/css/torneos.css?v=3" />
  <link rel="stylesheet" href="/tornea/css/perfil.css?v=4" />
</head>
<body>

  <?php $pagina_actual = 'perfil'; include __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="perfil-section">
      <div class="container perfil-layout">

        <div class="perfil-card">

          <div class="perfil-avatar">
            <?php // mb_: cortar por letras y no por bytes, para iniciales con tilde (Á, É, Ñ...) ?>
            <?= e(mb_strtoupper(mb_substr($usuario['nombre'], 0, 1) . mb_substr($usuario['apellido'], 0, 1))) ?>
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
          <?php // En celular el menú oculta los links de texto: acceso directo a los equipos ?>
          <a href="/tornea/app/views/equipos.php" class="btn btn-outline btn-lg btn-block perfil-btn-equipos">MIS EQUIPOS</a>
        </div>

        <div class="perfil-torneos">
          <?php if (isset($_SESSION['error'])): ?>
            <p style="color:red; margin-bottom: 12px;"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></p>
          <?php endif; ?>

          <?php if (isset($_SESSION['exito'])): ?>
            <p style="color:green; margin-bottom: 12px;"><?= e($_SESSION['exito']); unset($_SESSION['exito']); ?></p>
          <?php endif; ?>

          <div class="perfil-torneos-header">
            <h2 class="perfil-torneos-titulo">Mis torneos</h2>
            <p class="perfil-torneos-subtitle">Los torneos que organizás. Los borradores no son públicos hasta que los publiques.</p>
          </div>

          <div class="perfil-torneos-grid">
            <?php $mostrar_publicar = true; ?>
            <?php foreach ($mis_torneos as $t): ?>
              <?php include __DIR__ . '/partials/torneo-card.php'; ?>
            <?php endforeach; ?>
            <?php $mostrar_publicar = false; ?>

            <?php if (!$mis_torneos): ?>
              <p class="torneos-vacio">
                Todavía no organizás ningún torneo.
                <a href="/tornea/app/views/crear-torneo.php" class="form-link form-link-strong">Creá el primero</a>
              </p>
            <?php endif; ?>
          </div>

          <div class="perfil-participaciones">
            <div class="perfil-torneos-header">
              <h2 class="perfil-torneos-titulo">Torneos en los que participo</h2>
              <p class="perfil-torneos-subtitle">Tus inscripciones, individuales o con tu equipo.</p>
            </div>

            <?php if ($participaciones): ?>
              <ul class="participacion-list">
                <?php foreach ($participaciones as $p): ?>
                  <li class="participacion-item">
                    <div class="participacion-info">
                      <a href="/tornea/app/views/torneo-detalle.php?id=<?= (int) $p['id'] ?>" class="participacion-nombre"><?= e($p['nombre']) ?></a>
                      <span class="participacion-tipo">
                        <?= etiquetaTipo($p['tipo']) ?> · <?= etiquetaEstado($p['estado_torneo']) ?>
                        <?php if ($p['equipo']): ?> · con <?= e($p['equipo']) ?><?php endif; ?>
                      </span>
                    </div>
                    <span class="participacion-resultado participacion-<?= e($p['estado_inscripcion']) ?>">
                      Inscripción <?= e($p['estado_inscripcion']) ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="perfil-torneos-subtitle">Todavía no te inscribiste en ningún torneo.</p>
            <?php endif; ?>
          </div>
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