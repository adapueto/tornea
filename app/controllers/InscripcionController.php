<?php

require_once __DIR__ . '/../models/participante.php';

session_start();

$accion = $_GET['accion'] ?? '';

// Todas las acciones de inscripción requieren sesión iniciada
if (!isset($_SESSION['usuario'])) {
    $_SESSION['error'] = 'Tenés que iniciar sesión para inscribirte en un torneo';
    header('Location: /tornea/app/views/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tornea/app/views/torneos.php');
    exit;
}

$participante = new Participante();
$usuario_id = $_SESSION['usuario']['id'];

// El participante se anota o se baja de un torneo
if ($accion === 'inscribirse' || $accion === 'cancelar') {
    $torneo_id = (int) ($_POST['torneo_id'] ?? 0);

    $resultado = $accion === 'inscribirse'
        ? $participante->inscribir($torneo_id, $usuario_id)
        : $participante->cancelar($torneo_id, $usuario_id);

    $_SESSION[$resultado['exito'] ? 'exito' : 'error'] = $resultado['mensaje'];
    header('Location: /tornea/app/views/torneo-detalle.php?id=' . $torneo_id);
    exit;
}

// El organizador (o un admin) aprueba o rechaza una inscripción pendiente
if ($accion === 'aprobar' || $accion === 'rechazar') {
    $participante_id = (int) ($_POST['participante_id'] ?? 0);
    $torneo_id = $participante->torneoDeInscripcion($participante_id);

    $resultado = $participante->resolver($participante_id, $accion === 'aprobar' ? 'aprobado' : 'rechazado', $usuario_id);

    $_SESSION[$resultado['exito'] ? 'exito' : 'error'] = $resultado['mensaje'];
    header('Location: ' . ($torneo_id ? '/tornea/app/views/torneo-detalle.php?id=' . $torneo_id : '/tornea/app/views/perfil.php'));
    exit;
}

header('Location: /tornea/app/views/torneos.php');
exit;
