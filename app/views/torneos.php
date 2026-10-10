<?php
session_start();
require_once __DIR__ . '/../models/torneo.php';
require_once __DIR__ . '/../helpers/formato.php';

// Búsqueda y filtros (llegan también desde el buscador de la página principal)
$filtros = [
    'buscar' => mb_substr(trim($_GET['buscar'] ?? ''), 0, 100),
    'tipo' => $_GET['tipo'] ?? '',
    'estado' => $_GET['estado'] ?? '',
];
$filtrando = $filtros['buscar'] !== '' || $filtros['tipo'] !== '' || $filtros['estado'] !== '';
$torneos = (new Torneo())->listarPublicos($filtros);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Torneos — Tornea</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/tornea/css/style.css?v=5" />
  <link rel="stylesheet" href="/tornea/css/torneos.css?v=4" />
</head>
<body>

  <?php $pagina_actual = 'torneos'; include __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="torneos-hero">
      <div class="container">
        <h1 class="torneos-title">Torneos publicados</h1>
        <p class="torneos-subtitle">Explorá los torneos activos y próximos de cualquier deporte.</p>

        <form class="torneos-buscador" method="get" role="search">
          <div class="search-bar">
            <button type="submit" class="search-icon" aria-label="Buscar torneos">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            </button>
            <input type="search" name="buscar" class="search-placeholder" maxlength="100" value="<?= e($filtros['buscar']) ?>"
                   placeholder="Nombre, lugar o deporte..." aria-label="Buscar torneos" />
          </div>
          <div class="torneos-filtros">
            <select name="tipo" aria-label="Tipo de torneo" onchange="this.form.submit()">
              <option value="">Todos los tipos</option>
              <?php foreach (Torneo::TIPOS as $tipo): ?>
                <option value="<?= $tipo ?>"<?= $filtros['tipo'] === $tipo ? ' selected' : '' ?>><?= etiquetaTipo($tipo) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="estado" aria-label="Estado" onchange="this.form.submit()">
              <option value="">Todos los estados</option>
              <?php foreach (['publicado' => 'Inscripción abierta', 'en_curso' => 'En curso', 'finalizado' => 'Finalizados'] as $valor => $texto): ?>
                <option value="<?= $valor ?>"<?= $filtros['estado'] === $valor ? ' selected' : '' ?>><?= $texto ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <?php if ($filtrando): ?>
          <p class="torneos-resultado">
            <?= count($torneos) === 1 ? '1 torneo encontrado' : count($torneos) . ' torneos encontrados' ?><?= $filtros['buscar'] !== '' ? ' para «' . e($filtros['buscar']) . '»' : '' ?>.
            <a href="/tornea/app/views/torneos.php" class="form-link form-link-strong">Ver todos</a>
          </p>
        <?php endif; ?>
      </div>
    </section>

    <section class="torneos-list">
      <div class="container torneos-grid">

        <?php foreach ($torneos as $t): ?>
          <?php include __DIR__ . '/partials/torneo-card.php'; ?>
        <?php endforeach; ?>

        <?php if (!$torneos && $filtrando): ?>
          <p class="torneos-vacio">No encontramos torneos con esa búsqueda. Probá con otra palabra, por ejemplo el deporte o el barrio.</p>
        <?php elseif (!$torneos): ?>
          <p class="torneos-vacio">Todavía no hay torneos publicados.</p>
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
