<?php
// Formulario de torneo, compartido por crear-torneo.php y editar-torneo.php.
// Variables que espera:
//   $form            datos para llenar los campos (vacío al crear)
//   $hoy             fecha mínima para las fechas
//   $accion_form     URL a la que se envía
//   $texto_boton     texto del botón
//   $torneo_id       (editar) id del torneo, va en un campo oculto
//   $bloquear_formato (editar) true si el deporte y el tipo no se pueden cambiar
?>
<form class="auth-form" action="<?= $accion_form ?>" method="post">
  <?php if (!empty($torneo_id)): ?>
    <input type="hidden" name="id" value="<?= (int) $torneo_id ?>" />
  <?php endif; ?>
  <div class="form-group">
    <label for="nombre">Nombre del torneo</label>
    <input type="text" id="nombre" name="nombre" maxlength="150" placeholder="Ej: Liga Amateur de Fútbol 5" value="<?= e($form['nombre'] ?? '') ?>" required />
  </div>

  <div class="form-group">
    <label for="descripcion">Descripción</label>
    <textarea id="descripcion" name="descripcion" placeholder="Contá de qué se trata el torneo..."><?= e($form['descripcion'] ?? '') ?></textarea>
  </div>

  <div class="form-group">
    <label for="deporte">Deporte</label>
    <select id="deporte" name="deporte" required <?= !empty($bloquear_formato) ? 'disabled' : '' ?>>
      <option value="" disabled <?= empty($form['deporte']) ? 'selected' : '' ?>>Seleccioná un deporte</option>
      <?php foreach (Torneo::DEPORTES as $deporte): ?>
        <option value="<?= e($deporte) ?>" <?= ($form['deporte'] ?? '') === $deporte ? 'selected' : '' ?>><?= e($deporte) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group">
    <label for="tipo">Tipo de torneo</label>
    <select id="tipo" name="tipo" required <?= !empty($bloquear_formato) ? 'disabled' : '' ?>>
      <option value="" disabled <?= empty($form['tipo']) ? 'selected' : '' ?>>Seleccioná un tipo</option>
      <?php foreach (['liga' => 'Liga', 'eliminacion' => 'Eliminación directa', 'suizo' => 'Sistema suizo'] as $valor => $texto): ?>
        <option value="<?= $valor ?>" <?= ($form['tipo'] ?? '') === $valor ? 'selected' : '' ?>><?= $texto ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if (!empty($bloquear_formato)): ?>
    <p class="form-ayuda">El deporte y el tipo no se pueden cambiar porque el torneo ya está publicado y la gente se anotó con ese formato.</p>
  <?php endif; ?>

  <div class="form-grid-2">
    <div class="form-group">
      <label for="fecha_inicio">Fecha de inicio</label>
      <input type="date" id="fecha_inicio" name="fecha_inicio" min="<?= $hoy ?>" value="<?= e($form['fecha_inicio'] ?? '') ?>" required />
    </div>

    <div class="form-group">
      <label for="fecha_fin">Fecha de finalización</label>
      <input type="date" id="fecha_fin" name="fecha_fin" min="<?= $hoy ?>" value="<?= e($form['fecha_fin'] ?? '') ?>" required />
    </div>
  </div>

  <button type="submit" class="btn btn-gradient btn-lg btn-block"><?= $texto_boton ?></button>
</form>
