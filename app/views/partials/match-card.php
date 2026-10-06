<?php
// Tarjeta de un enfrentamiento. Espera $en: una fila de Torneo::listarRondas(),
// o null para un cruce de una ronda que todavía no se generó ("Por definir").
// Opcional: $es_final para resaltar la final de una llave.

$tiene_resultado = $en && $en['score_local'] !== null;
$gana_local = $tiene_resultado && $en['score_local'] > $en['score_visitante'];
$gana_visitante = $tiene_resultado && $en['score_visitante'] > $en['score_local'];
$estado_en = $en ? $en['estado'] : 'pendiente';
?>
<article class="match-card<?= !empty($es_final) ? ' match-card-final' : '' ?>">
  <div class="match-teams">
    <div class="match-team<?= $gana_local ? ' match-team-ganador' : '' ?>">
      <span class="match-team-nombre"><?= $en ? e($en['local']) : 'Por definir' ?></span>
      <?php if ($tiene_resultado): ?>
        <span class="match-team-score"><?= (int) $en['score_local'] ?></span>
      <?php endif; ?>
    </div>
    <div class="match-team<?= $gana_visitante ? ' match-team-ganador' : '' ?>">
      <span class="match-team-nombre"><?= $en ? e($en['visitante']) : 'Por definir' ?></span>
      <?php if ($tiene_resultado): ?>
        <span class="match-team-score"><?= (int) $en['score_visitante'] ?></span>
      <?php endif; ?>
    </div>
  </div>
  <div class="match-meta">
    <span class="match-fecha"><?= $tiene_resultado && !$gana_local && !$gana_visitante ? 'Empate' : '' ?></span>
    <span class="match-estado match-estado-<?= str_replace('_', '-', $estado_en) ?>"><?= etiquetaEstadoPartido($estado_en) ?></span>
  </div>
</article>
