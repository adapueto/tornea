<?php
session_start();
require_once __DIR__ . '/../models/admin.php';
require_once __DIR__ . '/../models/torneo.php';
require_once __DIR__ . '/../helpers/formato.php';

// Panel de administración: reportes, todos los torneos, usuarios y roles, e historial de cambios.
// Solo para administradores (RF-07, RNF-18); el rol se consulta en la base.

if (!isset($_SESSION['usuario'])) {
    $_SESSION['error'] = 'Tenés que iniciar sesión para continuar';
    header('Location: /tornea/app/views/login.php');
    exit;
}
$admin_id = $_SESSION['usuario']['id'];
if (!(new Torneo())->esAdmin($admin_id)) {
    http_response_code(403);
    $_SESSION['error'] = 'Esa sección es solo para administradores';
    header('Location: /tornea/app/views/perfil.php');
    exit;
}

$secciones = [
    'resumen' => 'Resumen',
    'torneos' => 'Torneos',
    'usuarios' => 'Usuarios',
    'historial' => 'Historial de cambios',
];
$seccion = isset($secciones[$_GET['seccion'] ?? '']) ? $_GET['seccion'] : 'resumen';
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$modelo = new Admin();

if ($seccion === 'resumen') {
    $r = $modelo->reportes();
} elseif ($seccion === 'torneos') {
    $filtros = ['buscar' => $_GET['buscar'] ?? '', 'estado' => $_GET['estado'] ?? '', 'tipo' => $_GET['tipo'] ?? ''];
    $torneos = $modelo->listarTorneos($filtros);
} elseif ($seccion === 'usuarios') {
    $filtros = ['buscar' => $_GET['buscar'] ?? '', 'rol' => $_GET['rol'] ?? ''];
    $total = $modelo->contarUsuarios($filtros);
    $usuarios = $modelo->listarUsuarios($filtros, $pagina);
    $form = $_SESSION['form_usuario'] ?? [];
    unset($_SESSION['form_usuario']);
} else {
    $filtros = [
        'accion' => $_GET['accion'] ?? '', 'tabla' => $_GET['tabla'] ?? '', 'usuario' => $_GET['usuario'] ?? '',
        'desde' => $_GET['desde'] ?? '', 'hasta' => $_GET['hasta'] ?? '',
    ];
    $total = $modelo->contarAuditoria($filtros);
    $cambios = $modelo->listarAuditoria($filtros, $pagina);
    $tablas = $modelo->tablasAuditadas();
}

// Para volver a la misma vista (sección, filtros y página) después de una acción
$query_actual = http_build_query(array_filter(['seccion' => $seccion] + ($filtros ?? []) + ['pagina' => $pagina > 1 ? $pagina : null], function ($v) {
    return $v !== null && $v !== '';
}));

// Enlace a otra página del listado, manteniendo los filtros
function urlPagina($n) {
    return '?' . http_build_query(array_merge($_GET, ['pagina' => $n]));
}

// Barra proporcional para los reportes
function barra($valor, $maximo) {
    $ancho = $maximo > 0 ? round($valor * 100 / $maximo) : 0;
    return '<span class="reporte-barra"><span style="width: ' . (int) $ancho . '%"></span></span>';
}

$acciones = ['INSERT' => 'Creó', 'UPDATE' => 'Modificó', 'DELETE' => 'Eliminó'];
$nombres_tabla = [
    'torneos' => 'Torneo', 'torneo_organizadores' => 'Organizador de torneo', 'participantes' => 'Inscripción',
    'rondas' => 'Ronda', 'resultados' => 'Resultado', 'equipos' => 'Equipo', 'equipo_miembros' => 'Integrante de equipo',
    'invitaciones' => 'Invitación a equipo', 'usuarios' => 'Cuenta de usuario', 'usuario_roles' => 'Rol de usuario',
];
$nombres_tipo = ['liga' => 'Liga', 'eliminacion' => 'Eliminación', 'suizo' => 'Suizo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Administración — Tornea</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/tornea/css/style.css?v=2" />
  <link rel="stylesheet" href="/tornea/css/torneos.css?v=3" />
  <link rel="stylesheet" href="/tornea/css/torneo-detalle.css?v=8" />
  <link rel="stylesheet" href="/tornea/css/auth.css?v=3" />
  <link rel="stylesheet" href="/tornea/css/admin.css?v=2" />
</head>
<body>

  <?php $pagina_actual = 'admin'; include __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="torneos-hero">
      <div class="container">
        <h1 class="torneos-title">Panel de administración</h1>
        <p class="torneos-subtitle">Reportes, torneos, usuarios y el historial de todo lo que cambia en el sistema.</p>

        <nav class="admin-tabs" aria-label="Secciones del panel">
          <?php foreach ($secciones as $clave => $texto): ?>
            <a href="?seccion=<?= $clave ?>" class="admin-tab<?= $clave === $seccion ? ' admin-tab-activa' : '' ?>"<?= $clave === $seccion ? ' aria-current="page"' : '' ?>><?= $texto ?></a>
          <?php endforeach; ?>
        </nav>
      </div>
    </section>

    <section class="admin-section">
      <div class="container">

        <?php if (isset($_SESSION['error'])): ?>
          <p class="mensaje mensaje-error"><?= e($_SESSION['error']); unset($_SESSION['error']); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['exito'])): ?>
          <p class="mensaje mensaje-exito"><?= e($_SESSION['exito']); unset($_SESSION['exito']); ?></p>
        <?php endif; ?>

        <?php if ($seccion === 'resumen'): ?>
          <!-- ===== Reportes (RF-61) ===== -->
          <div class="admin-numeros">
            <div class="admin-numero"><span class="admin-numero-valor"><?= $r['usuarios'] ?></span><span class="admin-numero-texto">usuarios · <?= $r['usuarios_nuevos_30'] ?> nuevos en 30 días</span></div>
            <div class="admin-numero"><span class="admin-numero-valor"><?= $r['torneos'] ?></span><span class="admin-numero-texto">torneos</span></div>
            <div class="admin-numero"><span class="admin-numero-valor"><?= $r['equipos'] ?></span><span class="admin-numero-texto">equipos</span></div>
            <div class="admin-numero"><span class="admin-numero-valor"><?= $r['partidos_jugados'] ?></span><span class="admin-numero-texto">partidos jugados · <?= $r['partidos_pendientes'] ?> por jugar</span></div>
          </div>

          <div class="admin-reportes">
            <?php
              $bloques = [
                  'Torneos por estado' => array_combine(array_map('etiquetaEstado', array_keys($r['torneos_por_estado'])), $r['torneos_por_estado']),
                  'Torneos por tipo' => array_combine(array_map('etiquetaTipo', array_keys($r['torneos_por_tipo'])), $r['torneos_por_tipo']),
                  'Usuarios por rol' => array_combine(array_map('ucfirst', array_keys($r['usuarios_por_rol'])), $r['usuarios_por_rol']),
                  'Inscripciones' => array_combine(array_map('ucfirst', array_keys($r['inscripciones_por_estado'])), $r['inscripciones_por_estado']),
                  'Torneos por deporte' => $r['torneos_por_deporte'],
              ];
            ?>
            <?php foreach ($bloques as $titulo => $datos): ?>
              <div class="gestion-panel admin-reporte">
                <h2 class="gestion-subtitulo"><?= $titulo ?></h2>
                <ul class="reporte-lista">
                  <?php $max = $datos ? max($datos) : 0; ?>
                  <?php foreach ($datos as $etiqueta => $valor): ?>
                    <li><span class="reporte-etiqueta"><?= e($etiqueta) ?></span><?= barra($valor, $max) ?><span class="reporte-valor"><?= (int) $valor ?></span></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endforeach; ?>

            <div class="gestion-panel admin-reporte">
              <h2 class="gestion-subtitulo">Actividad de los últimos 14 días</h2>
              <?php if ($r['actividad']): ?>
                <ul class="reporte-lista">
                  <?php $max = max($r['actividad']); ?>
                  <?php foreach ($r['actividad'] as $dia => $valor): ?>
                    <li><span class="reporte-etiqueta"><?= date('d/m', strtotime($dia)) ?></span><?= barra($valor, $max) ?><span class="reporte-valor"><?= (int) $valor ?></span></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <p class="gestion-ayuda">No hubo cambios en las últimas dos semanas.</p>
              <?php endif; ?>
              <p class="gestion-ayuda"><a href="?seccion=historial" class="form-link form-link-strong">Ver el historial completo</a></p>
            </div>

            <div class="gestion-panel admin-reporte">
              <h2 class="gestion-subtitulo">Torneos con más inscriptos</h2>
              <ol class="reporte-ranking">
                <?php foreach ($r['torneos_mas_inscriptos'] as $t): ?>
                  <li>
                    <a href="/tornea/app/views/torneo-detalle.php?id=<?= (int) $t['id'] ?>" class="form-link"><?= e($t['nombre']) ?></a>
                    <span class="reporte-valor"><?= (int) $t['inscriptos'] ?></span>
                  </li>
                <?php endforeach; ?>
              </ol>
            </div>
          </div>

        <?php elseif ($seccion === 'torneos'): ?>
          <!-- ===== Todos los torneos, también los borradores ===== -->
          <form class="admin-filtros" method="get">
            <input type="hidden" name="seccion" value="torneos" />
            <div class="form-group">
              <label for="f-buscar">Nombre</label>
              <input type="search" id="f-buscar" name="buscar" value="<?= e($filtros['buscar']) ?>" placeholder="Buscar torneo" />
            </div>
            <div class="form-group">
              <label for="f-estado">Estado</label>
              <select id="f-estado" name="estado">
                <option value="">Todos</option>
                <?php foreach (['borrador', 'publicado', 'en_curso', 'finalizado'] as $est): ?>
                  <option value="<?= $est ?>"<?= $filtros['estado'] === $est ? ' selected' : '' ?>><?= etiquetaEstado($est) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="f-tipo">Tipo</label>
              <select id="f-tipo" name="tipo">
                <option value="">Todos</option>
                <?php foreach (Torneo::TIPOS as $tipo): ?>
                  <option value="<?= $tipo ?>"<?= $filtros['tipo'] === $tipo ? ' selected' : '' ?>><?= etiquetaTipo($tipo) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-outline">Filtrar</button>
          </form>

          <p class="gestion-ayuda admin-total"><?= count($torneos) ?> torneos. Como administrador podés entrar a cualquiera y gestionarlo, aunque no lo organices.</p>

          <div class="admin-tabla-wrap">
            <table class="admin-tabla">
              <thead>
                <tr><th>Torneo</th><th>Estado</th><th>Tipo</th><th>Organiza</th><th>Inscriptos</th><th>Fechas</th></tr>
              </thead>
              <tbody>
                <?php foreach ($torneos as $t): ?>
                  <tr>
                    <td>
                      <a href="/tornea/app/views/torneo-detalle.php?id=<?= (int) $t['id'] ?>" class="admin-enlace"><?= e($t['nombre']) ?></a>
                      <span class="admin-sub"><?= iconoDeporte($t['deporte']) ?> <?= e($t['deporte']) ?> · <?= $t['modalidad'] === 'equipo' ? 'Por equipos' : 'Individual' ?></span>
                    </td>
                    <td><span class="torneo-estado <?= claseEstado($t['estado']) ?>"><?= etiquetaEstado($t['estado']) ?></span></td>
                    <td><?= $nombres_tipo[$t['tipo']] ?></td>
                    <td><?= e($t['organizadores']) ?></td>
                    <td><?= (int) $t['inscriptos'] ?><?php if ($t['pendientes']): ?> <span class="admin-sub">+<?= (int) $t['pendientes'] ?> pendientes</span><?php endif; ?></td>
                    <td class="admin-nowrap"><?= formatearFechas($t['fecha_inicio'], $t['fecha_fin']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php if (!$torneos): ?>
              <p class="detalle-vacio">No hay torneos con esos filtros.</p>
            <?php endif; ?>
          </div>

        <?php elseif ($seccion === 'usuarios'): ?>
          <!-- ===== Usuarios y roles (RF-03 a RF-06, RF-10, RF-11) ===== -->
          <details class="gestion-panel admin-crear"<?= $form ? ' open' : '' ?>>
            <summary class="gestion-subtitulo">Crear una cuenta</summary>
            <p class="gestion-ayuda">Por ejemplo, para sumar otro administrador u organizador. La persona después puede cambiar sus datos desde su perfil.</p>
            <form class="auth-form admin-form" action="/tornea/app/controllers/AdminController.php?accion=crear_usuario" method="post">
              <input type="hidden" name="volver" value="<?= e($query_actual) ?>" />
              <div class="form-group">
                <label for="u-nombre">Nombre</label>
                <input type="text" id="u-nombre" name="nombre" maxlength="100" required value="<?= e($form['nombre'] ?? '') ?>" />
              </div>
              <div class="form-group">
                <label for="u-apellido">Apellido</label>
                <input type="text" id="u-apellido" name="apellido" maxlength="100" required value="<?= e($form['apellido'] ?? '') ?>" />
              </div>
              <div class="form-group">
                <label for="u-email">Email</label>
                <input type="email" id="u-email" name="email" maxlength="150" required value="<?= e($form['email'] ?? '') ?>" />
              </div>
              <div class="form-group">
                <label for="u-password">Contraseña inicial</label>
                <input type="password" id="u-password" name="password" minlength="8" required placeholder="Al menos 8 caracteres" autocomplete="new-password" />
              </div>
              <div class="form-group">
                <label for="u-rol">Rol</label>
                <select id="u-rol" name="rol" required>
                  <?php foreach (Admin::ROLES as $rol): ?>
                    <option value="<?= $rol ?>"<?= ($form['rol'] ?? 'organizador') === $rol ? ' selected' : '' ?>><?= ucfirst($rol) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-gradient">Crear cuenta</button>
            </form>
          </details>

          <form class="admin-filtros" method="get">
            <input type="hidden" name="seccion" value="usuarios" />
            <div class="form-group">
              <label for="f-buscar">Nombre o email</label>
              <input type="search" id="f-buscar" name="buscar" value="<?= e($filtros['buscar']) ?>" placeholder="Buscar usuario" />
            </div>
            <div class="form-group">
              <label for="f-rol">Rol</label>
              <select id="f-rol" name="rol">
                <option value="">Todos</option>
                <?php foreach (Admin::ROLES as $rol): ?>
                  <option value="<?= $rol ?>"<?= $filtros['rol'] === $rol ? ' selected' : '' ?>><?= ucfirst($rol) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-outline">Filtrar</button>
          </form>

          <p class="gestion-ayuda admin-total">
            <?= $total ?> usuarios. Los roles se asignan solos: al registrarse cada cuenta es participante, y pasa a organizador
            cuando crea su primer torneo. Desde acá se nombran administradores o se corrige un rol a mano.
            Una cuenta solo se puede eliminar si no tiene historial en torneos ni equipos.
          </p>

          <div class="admin-tabla-wrap">
            <table class="admin-tabla">
              <thead>
                <tr><th>Usuario</th><th>Rol</th><th>Actividad</th><th>Alta</th><th>Eliminar</th></tr>
              </thead>
              <tbody>
                <?php foreach ($usuarios as $u): ?>
                  <?php $motivo = $modelo->motivoNoPuedeEliminar($u, $admin_id); ?>
                  <tr>
                    <td>
                      <span class="admin-enlace"><?= e($u['nombre'] . ' ' . $u['apellido']) ?></span>
                      <span class="admin-sub"><?= e($u['email']) ?></span>
                    </td>
                    <td>
                      <form class="admin-rol" action="/tornea/app/controllers/AdminController.php?accion=cambiar_rol" method="post">
                        <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>" />
                        <input type="hidden" name="volver" value="<?= e($query_actual) ?>" />
                        <select name="rol" aria-label="Rol de <?= e($u['nombre']) ?>">
                          <?php foreach (Admin::ROLES as $rol): ?>
                            <option value="<?= $rol ?>"<?= $u['rol'] === $rol ? ' selected' : '' ?>><?= ucfirst($rol) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline">Cambiar</button>
                      </form>
                    </td>
                    <td class="admin-sub">
                      <?= (int) $u['organiza'] ?> organiza · <?= (int) $u['inscripciones'] ?> inscripciones · <?= (int) $u['equipos'] ?> equipos
                    </td>
                    <td class="admin-nowrap"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    <td>
                      <?php if ($motivo): ?>
                        <span class="admin-sub"><?= e($motivo) ?></span>
                      <?php else: ?>
                        <form action="/tornea/app/controllers/AdminController.php?accion=eliminar_usuario" method="post"
                              onsubmit="return confirm('¿Eliminar la cuenta de <?= e(addslashes($u['nombre'] . ' ' . $u['apellido'])) ?>? No se puede deshacer.');">
                          <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>" />
                          <input type="hidden" name="volver" value="<?= e($query_actual) ?>" />
                          <button type="submit" class="btn btn-peligro">Eliminar</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php if (!$usuarios): ?>
              <p class="detalle-vacio">No hay usuarios con esos filtros.</p>
            <?php endif; ?>
          </div>
          <?php include __DIR__ . '/partials/paginacion.php'; ?>

        <?php else: ?>
          <!-- ===== Historial de cambios (RF-63 a RF-66) ===== -->
          <form class="admin-filtros" method="get">
            <input type="hidden" name="seccion" value="historial" />
            <div class="form-group">
              <label for="f-usuario">Quién</label>
              <input type="search" id="f-usuario" name="usuario" value="<?= e($filtros['usuario']) ?>" placeholder="Nombre, email o &quot;sistema&quot;" />
            </div>
            <div class="form-group">
              <label for="f-accion">Acción</label>
              <select id="f-accion" name="accion">
                <option value="">Todas</option>
                <?php foreach ($acciones as $clave => $texto): ?>
                  <option value="<?= $clave ?>"<?= $filtros['accion'] === $clave ? ' selected' : '' ?>><?= $texto ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="f-tabla">Qué</label>
              <select id="f-tabla" name="tabla">
                <option value="">Todo</option>
                <?php foreach ($tablas as $tabla): ?>
                  <option value="<?= e($tabla) ?>"<?= $filtros['tabla'] === $tabla ? ' selected' : '' ?>><?= e($nombres_tabla[$tabla] ?? $tabla) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="f-desde">Desde</label>
              <input type="date" id="f-desde" name="desde" value="<?= e($filtros['desde']) ?>" />
            </div>
            <div class="form-group">
              <label for="f-hasta">Hasta</label>
              <input type="date" id="f-hasta" name="hasta" value="<?= e($filtros['hasta']) ?>" />
            </div>
            <button type="submit" class="btn btn-outline">Filtrar</button>
          </form>

          <p class="gestion-ayuda admin-total"><?= $total ?> cambios registrados. Cada uno guarda quién lo hizo, cuándo y sobre qué registro.</p>

          <div class="admin-tabla-wrap">
            <table class="admin-tabla">
              <thead>
                <tr><th>Fecha y hora</th><th>Quién</th><th>Acción</th><th>Sobre qué</th><th>Torneo</th></tr>
              </thead>
              <tbody>
                <?php foreach ($cambios as $c): ?>
                  <tr>
                    <td class="admin-nowrap"><?= date('d/m/Y H:i', strtotime($c['fecha'])) ?></td>
                    <td>
                      <?php if ($c['usuario_id'] === null): ?>
                        <span class="admin-sub">Sistema</span>
                      <?php else: ?>
                        <?= e($c['usuario'] ?? 'Cuenta eliminada') ?>
                      <?php endif; ?>
                    </td>
                    <td><span class="admin-accion admin-accion-<?= strtolower($c['accion']) ?>"><?= $acciones[$c['accion']] ?? e($c['accion']) ?></span></td>
                    <td><?= e($nombres_tabla[$c['tabla_afectada']] ?? $c['tabla_afectada']) ?> <span class="admin-sub">#<?= (int) $c['registro_id'] ?></span></td>
                    <td>
                      <?php if ($c['torneo_id']): ?>
                        <a href="/tornea/app/views/torneo-detalle.php?id=<?= (int) $c['torneo_id'] ?>" class="form-link"><?= e($c['torneo']) ?></a>
                      <?php else: ?>
                        <span class="admin-sub">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php if (!$cambios): ?>
              <p class="detalle-vacio">No hay cambios con esos filtros.</p>
            <?php endif; ?>
          </div>
          <?php include __DIR__ . '/partials/paginacion.php'; ?>
        <?php endif; ?>

      </div>
    </section>
  </main>

  <?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
