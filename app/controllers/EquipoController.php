<?php

require_once __DIR__ . '/../models/equipo.php';

session_start();

$accion = $_GET['accion'] ?? '';

// Todas las acciones de equipos requieren sesión iniciada
if (!isset($_SESSION['usuario'])) {
    $_SESSION['error'] = 'Tenés que iniciar sesión para armar o sumarte a un equipo';
    header('Location: /tornea/app/views/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tornea/app/views/equipos.php');
    exit;
}

$equipo = new Equipo();
$usuario_id = $_SESSION['usuario']['id'];
$equipo_id = (int) ($_POST['equipo_id'] ?? 0);

$url_equipo = '/tornea/app/views/equipo.php?id=' . $equipo_id;
$url_mis_equipos = '/tornea/app/views/equipos.php';

switch ($accion) {
    // Crear el equipo desde el detalle del torneo (queda inscripto como pendiente)
    case 'crear':
        $torneo_id = (int) ($_POST['torneo_id'] ?? 0);
        $resultado = $equipo->crear($torneo_id, $_POST['nombre'] ?? '', $usuario_id);
        $destino = $resultado['exito']
            ? '/tornea/app/views/equipo.php?id=' . $resultado['equipo_id']
            : '/tornea/app/views/torneo-detalle.php?id=' . $torneo_id;
        break;

    case 'renombrar':
        $resultado = $equipo->renombrar($equipo_id, $_POST['nombre'] ?? '', $usuario_id);
        $destino = $url_equipo;
        break;

    case 'baja':
        $resultado = $equipo->darDeBaja($equipo_id, $usuario_id);
        $destino = $resultado['exito'] ? $url_mis_equipos : $url_equipo;
        break;

    case 'invitar':
        $resultado = $equipo->invitar($equipo_id, $_POST['email'] ?? '', $usuario_id);
        $destino = $url_equipo;
        break;

    // El invitado responde desde "Mis equipos"
    case 'aceptar':
    case 'rechazar':
        $resultado = $equipo->responderInvitacion((int) ($_POST['invitacion_id'] ?? 0), $accion === 'aceptar', $usuario_id);
        $destino = $resultado['exito'] && $accion === 'aceptar'
            ? '/tornea/app/views/equipo.php?id=' . $resultado['equipo_id']
            : $url_mis_equipos;
        break;

    case 'cancelar_invitacion':
        $resultado = $equipo->cancelarInvitacion((int) ($_POST['invitacion_id'] ?? 0), $usuario_id);
        $destino = $url_equipo;
        break;

    case 'quitar':
        $resultado = $equipo->quitarMiembro($equipo_id, (int) ($_POST['miembro_id'] ?? 0), $usuario_id);
        $destino = $url_equipo;
        break;

    case 'salir':
        $resultado = $equipo->salir($equipo_id, $usuario_id);
        $destino = $resultado['exito'] ? $url_mis_equipos : $url_equipo;
        break;

    default:
        header('Location: ' . $url_mis_equipos);
        exit;
}

$_SESSION[$resultado['exito'] ? 'exito' : 'error'] = $resultado['mensaje'];
header('Location: ' . $destino);
exit;
