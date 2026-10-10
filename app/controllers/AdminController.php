<?php

require_once __DIR__ . '/../models/admin.php';
require_once __DIR__ . '/../models/torneo.php';

session_start();

$accion = $_GET['accion'] ?? '';

if (!isset($_SESSION['usuario'])) {
    $_SESSION['error'] = 'Tenés que iniciar sesión para continuar';
    header('Location: /tornea/app/views/login.php');
    exit;
}

// Solo administradores (RNF-18). El rol se consulta en la base, no en la sesión.
$admin_id = $_SESSION['usuario']['id'];
if (!(new Torneo())->esAdmin($admin_id)) {
    $_SESSION['error'] = 'Esa sección es solo para administradores';
    header('Location: /tornea/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tornea/app/views/admin.php');
    exit;
}

$admin = new Admin();

// Se vuelve a la misma sección con los mismos filtros. Solo se aceptan parámetros
// del propio panel, para que no se pueda usar para redirigir a otro sitio.
function volverAlPanel($resultado) {
    $_SESSION[$resultado['exito'] ? 'exito' : 'error'] = $resultado['mensaje'];
    $volver = $_POST['volver'] ?? '';
    $query = preg_match('/^seccion=[a-z]+(&[a-z]+=[^&#\s]*)*$/', $volver) ? $volver : 'seccion=usuarios';
    header('Location: /tornea/app/views/admin.php?' . $query);
    exit;
}

if ($accion === 'crear_usuario') {
    $datos = [
        'nombre' => $_POST['nombre'] ?? '',
        'apellido' => $_POST['apellido'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'rol' => $_POST['rol'] ?? '',
    ];
    $resultado = $admin->crearUsuario($datos, $admin_id);
    if (!$resultado['exito']) {
        // Se guardan los datos (menos la contraseña) para no escribir todo de nuevo
        unset($datos['password']);
        $_SESSION['form_usuario'] = $datos;
    }
    volverAlPanel($resultado);
}

if ($accion === 'cambiar_rol') {
    volverAlPanel($admin->cambiarRol((int) ($_POST['usuario_id'] ?? 0), $_POST['rol'] ?? '', $admin_id));
}

if ($accion === 'eliminar_usuario') {
    volverAlPanel($admin->eliminarUsuario((int) ($_POST['usuario_id'] ?? 0), $admin_id));
}

// Habilita o deshabilita un tipo de torneo (RF-59, RF-60)
if ($accion === 'cambiar_modulo') {
    require_once __DIR__ . '/../models/modulo.php';
    $_POST['volver'] = 'seccion=modulos';
    volverAlPanel((new Modulo())->cambiar($_POST['codigo'] ?? '', ($_POST['habilitar'] ?? '') === '1', $admin_id));
}

// Configuración general (RF-62)
if ($accion === 'guardar_configuracion') {
    require_once __DIR__ . '/../models/configuracion.php';
    require_once __DIR__ . '/../models/ronda.php';
    $resultado = (new Configuracion())->guardar($_POST, $admin_id);
    if ($resultado['exito'] && $resultado['cambiaron_puntos']) {
        $cantidad = (new Ronda())->recalcularTorneosEnCurso();
        $resultado['mensaje'] .= ". Se recalcularon las tablas de $cantidad torneos en curso; los finalizados quedan como terminaron.";
    }
    $_POST['volver'] = 'seccion=configuracion';
    volverAlPanel($resultado);
}

header('Location: /tornea/app/views/admin.php');
exit;
