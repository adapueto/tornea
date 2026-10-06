<?php
// Tarjeta de un torneo. Espera la variable $t (una fila de la tabla torneos).
// Si $mostrar_publicar es true y el torneo es borrador, muestra los botones para publicarlo o eliminarlo.

// Las páginas de detalle todavía son una plantilla estática por tipo;
// cuando exista torneo-detalle.php?id= este link apunta ahí.
$detalle = [
    'liga' => '/tornea/torneo-detalle-liga.html',
    'eliminacion' => '/tornea/torneo-detalle-eliminacion.html',
    'suizo' => '/tornea/torneo-detalle-suizo.html',
][$t['tipo']];
?>
<article class="torneo-card">
  <div class="torneo-card-top">
    <span class="torneo-badge"><?= iconoDeporte($t['deporte']) ?> <?= e($t['deporte']) ?></span>
    <span class="torneo-estado <?= claseEstado($t['estado']) ?>"><?= etiquetaEstado($t['estado']) ?></span>
  </div>
  <h3 class="torneo-nombre"><?= e($t['nombre']) ?></h3>
  <p class="torneo-tipo"><?= etiquetaTipo($t['tipo']) ?></p>
  <p class="torneo-fechas"><?= formatearFechas($t['fecha_inicio'], $t['fecha_fin']) ?></p>

  <?php if (!empty($mostrar_publicar) && $t['estado'] === 'borrador'): ?>
    <div class="torneo-card-acciones">
      <form action="/tornea/app/controllers/TorneoController.php?accion=publicar" method="post">
        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>" />
        <button type="submit" class="btn btn-gradient">Publicar</button>
      </form>
      <form action="/tornea/app/controllers/TorneoController.php?accion=eliminar" method="post"
            onsubmit="return confirm('¿Estás seguro de que querés eliminar este torneo? Esta acción no se puede deshacer.');">
        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>" />
        <button type="submit" class="btn btn-peligro">Eliminar</button>
      </form>
    </div>
  <?php else: ?>
    <a href="<?= $detalle ?>" class="btn btn-outline btn-block">Ver detalle</a>
  <?php endif; ?>
</article>
