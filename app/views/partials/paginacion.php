<?php
// Paginación de los listados del panel. Espera $total, $pagina y la función urlPagina().
$paginas = (int) ceil($total / Admin::POR_PAGINA);
?>
<?php if ($paginas > 1): ?>
  <nav class="paginacion" aria-label="Páginas">
    <?php if ($pagina > 1): ?>
      <a href="<?= e(urlPagina($pagina - 1)) ?>" class="btn btn-outline">← Anterior</a>
    <?php endif; ?>
    <span class="paginacion-texto">Página <?= min($pagina, $paginas) ?> de <?= $paginas ?></span>
    <?php if ($pagina < $paginas): ?>
      <a href="<?= e(urlPagina($pagina + 1)) ?>" class="btn btn-outline">Siguiente →</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>
