<?php
// Página que se muestra cuando algo falla sin que nadie lo ataje.
// Espera $mensaje y, opcional, $detalle. No usa el header porque el header consulta la base,
// y el error podría ser justamente de la base.
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Algo salió mal — Tornea</title>
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/tornea/css/style.css?v=5" />
  <link rel="stylesheet" href="/tornea/css/auth.css?v=3" />
</head>
<body>
  <main>
    <section class="auth-section">
      <div class="auth-card">
        <img src="/tornea/img/logo.png" alt="Tornea" class="auth-logo-icon" />
        <h1 class="auth-title">Algo salió mal</h1>
        <p class="auth-subtitle"><?= e($mensaje) ?></p>

        <?php // En desarrollo (errores visibles) se muestra qué pasó, para poder arreglarlo ?>
        <?php if ($detalle !== '' && ini_get('display_errors')): ?>
          <pre style="white-space: pre-wrap; text-align: left; font-size: 13px; margin-bottom: 16px;"><?= e($detalle) ?></pre>
        <?php endif; ?>

        <a href="/tornea/index.php" class="btn btn-gradient">Volver al inicio</a>
      </div>
    </section>
  </main>
</body>
</html>
