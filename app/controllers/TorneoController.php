<?php

require_once __DIR__ . '/../models/torneo.php';

session_start();

$accion = $_GET['accion'] ?? '';

// Todas las acciones de torneo requieren sesión iniciada
if (!isset($_SESSION['usuario'])) {
    $_SESSION['error'] = 'Tenés que iniciar sesión para continuar';
    header('Location: /tornea/app/views/login.php');
    exit;
}

$torneo = new Torneo();

// A dónde volver después de publicar o eliminar: al detalle si la acción
// se hizo desde el panel de gestión, si no al perfil ("Mis torneos")
function urlVolver($id) {
    if (($_POST['volver'] ?? '') === 'detalle') {
        return '/tornea/app/views/torneo-detalle.php?id=' . (int) $id;
    }
    return '/tornea/app/views/perfil.php';
}

if ($accion === 'crear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'nombre' => $_POST['nombre'] ?? '',
        'descripcion' => $_POST['descripcion'] ?? '',
        'deporte' => $_POST['deporte'] ?? '',
        'tipo' => $_POST['tipo'] ?? '',
        'fecha_inicio' => $_POST['fecha_inicio'] ?? '',
        'fecha_fin' => $_POST['fecha_fin'] ?? '',
    ];

    $resultado = $torneo->crear($datos, $_SESSION['usuario']['id']);

    if ($resultado['exito']) {
        // El torneo nuevo queda como borrador en "Mis torneos" del perfil
        $_SESSION['exito'] = $resultado['mensaje'] . '. Publicalo desde "Mis torneos" cuando esté listo.';
        header('Location: /tornea/app/views/perfil.php');
    } else {
        $_SESSION['error'] = $resultado['mensaje'];
        // Se guardan los datos para no tener que escribir todo de nuevo
        $_SESSION['form_torneo'] = $datos;
        header('Location: /tornea/app/views/crear-torneo.php');
    }
    exit;
}

if ($accion === 'publicar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);

    $resultado = $torneo->publicar($id, $_SESSION['usuario']['id']);

    $_SESSION[$resultado['exito'] ? 'exito' : 'error'] = $resultado['mensaje'];
    header('Location: ' . urlVolver($id));
    exit;
}

if ($accion === 'editar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $datos = [
        'nombre' => $_POST['nombre'] ?? '',
        'descripcion' => $_POST['descripcion'] ?? '',
        'deporte' => $_POST['deporte'] ?? '',
        'tipo' => $_POST['tipo'] ?? '',
        'fecha_inicio' => $_POST['fecha_inicio'] ?? '',
        'fecha_fin' => $_POST['fecha_fin'] ?? '',
    ];

    $resultado = $torneo->actualizar($id, $datos, $_SESSION['usuario']['id']);

    if ($resultado['exito']) {
        $_SESSION['exito'] = $resultado['mensaje'];
        header('Location: /tornea/app/views/torneo-detalle.php?id=' . $id);
    } else {
        $_SESSION['error'] = $resultado['mensaje'];
        $_SESSION['form_torneo'] = $datos;
        header('Location: /tornea/app/views/editar-torneo.php?id=' . $id);
    }
    exit;
}

if ($accion === 'eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);

    $resultado = $torneo->eliminar($id, $_SESSION['usuario']['id']);

    $_SESSION[$resultado['exito'] ? 'exito' : 'error'] = $resultado['mensaje'];
    // Si se eliminó, el detalle ya no existe: se vuelve al perfil
    header('Location: ' . ($resultado['exito'] ? '/tornea/app/views/perfil.php' : urlVolver($id)));
    exit;
}

header('Location: /tornea/app/views/torneos.php');
exit;
