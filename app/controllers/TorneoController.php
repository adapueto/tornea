<?php

require_once __DIR__ . '/../models/torneo.php';

session_start();

$accion = $_GET['accion'] ?? '';

// Todas las acciones de torneo requieren sesión iniciada
if (!isset($_SESSION['usuario'])) {
    $_SESSION['error'] = 'Tenés que iniciar sesión para crear un torneo';
    header('Location: /tornea/app/views/login.php');
    exit;
}

$torneo = new Torneo();

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
        $_SESSION['exito'] = $resultado['mensaje'];
    } else {
        $_SESSION['error'] = $resultado['mensaje'];
        // Se guardan los datos para no tener que escribir todo de nuevo
        $_SESSION['form_torneo'] = $datos;
    }

    header('Location: /tornea/app/views/crear-torneo.php');
    exit;
}

header('Location: /tornea/app/views/torneos.php');
exit;
