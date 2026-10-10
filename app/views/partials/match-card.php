<?php
// Tarjeta de un enfrentamiento. Espera $en: una fila de Torneo::listarRondas(),
// o null para un cruce de una ronda que todavía no se generó ("Por definir").
// Opcional: $es_final para resaltar la final de una llave, y $editable para que
// quien gestiona el torneo cargue o corrija el resultado ahí mismo (RF-43, RF-44).

// Sin visitante es un pase libre: el participante no juega esta ronda
$es_libre = $en && $en['participante_visitante_id'] === null;
$tiene_resultado = $en && $en['score_local'] !== null;
// En eliminación y suizo el libre cuenta como victoria; en liga solo descansa
$gana_local = ($tiene_resultado && $en['score_local'] > $en['score_visitante'])
    || ($es_libre && $torneo['tipo'] !== 'liga');
$gana_visitante = $tiene_resultado && $en['score_visitante'] > $en['score_local'];
$estado_en = $en ? $en['estado'] : 'pendiente';
$cargar_resultado = !empty($editable) && $en && !$es_libre;
$textos_libre = ['liga' => 'Descansa esta fecha', 'eliminacion' => 'Pasa directo', 'suizo' => 'Libre: suma una victoria'];
?>
<article class="match-card<?= !empty($es_final) ? ' match-card-final' : '' ?><?= $es_libre ? ' match-card-libre' : '' ?>"<?= $en ? ' id="partido-' . (int) $en['id'] . '"' : '' ?>>
  <?php if ($cargar_resultado): ?>
    <form action="/tornea/app/controllers/RondaController.php?accion=resultado" method="post">
      <input type="hidden" name="enfrentamiento_id" value="<?= (int) $en['id'] ?>" />
  <?php endif; ?>

  <div class="match-teams">
    <?php foreach (['local' => $gana_local, 'visitante' => $gana_visitante] as $lado => $gana): ?>
      <?php $nombre = !$en ? 'Por definir' : ($lado === 'visitante' && $es_libre ? 'Libre' : $en[$lado]); ?>
      <div class="match-team<?= $gana ? ' match-team-ganador' : '' ?><?= $lado === 'visitante' && $es_libre ? ' match-team-libre' : '' ?>">
        <span class="match-team-nombre"><?= e($nombre) ?></span>
        <?php if ($cargar_resultado): ?>
          <input type="number" class="match-score-input" name="score_<?= $lado ?>" min="0" max="999" required
                 value="<?= $tiene_resultado ? (int) $en['score_' . $lado] : '' ?>"
                 aria-label="Resultado de <?= e($nombre) ?>" />
        <?php elseif ($tiene_resultado): ?>
          <span class="match-team-score"><?= (int) $en['score_' . $lado] ?></span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="match-meta">
    <span class="match-fecha">
      <?php if ($es_libre): ?>
        <?= $textos_libre[$torneo['tipo']] ?>
      <?php elseif ($tiene_resultado && !$gana_local && !$gana_visitante): ?>
        Empate
      <?php endif; ?>
    </span>
    <?php if ($cargar_resultado): ?>
      <button type="submit" class="btn btn-outline match-guardar"><?= $tiene_resultado ? 'Corregir' : 'Guardar' ?></button>
    <?php elseif (!$es_libre): ?>
      <span class="match-estado match-estado-<?= str_replace('_', '-', $estado_en) ?>"><?= etiquetaEstadoPartido($estado_en) ?></span>
    <?php endif; ?>
  </div>

  <?php if ($cargar_resultado): ?>
    </form>
  <?php endif; ?>
</article>
