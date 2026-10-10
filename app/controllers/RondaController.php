<?php

require_once __DIR__ . '/../models/ronda.php';

session_start();

$accion = $_GET['accion'] ?? '';

// Todas las acciones de rondas requieren sesión iniciada
if (!isset($_SESSION['usuario'])) {
    $_SESSION['error'] = 'Tenés que iniciar sesión para continuar';
    header('Location: /tornea/app/views/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tornea/app/views/torneos.php');
    exit;
}

$ronda = new Ronda();
$usuario_id = $_SESSION['usuario']['id'];

function volverAlTorneo($torneo_id, $resultado, $ancla = '') {
    $_SESSION[$resultado['exito'] ? 'exito' : 'error'] = $resultado['mensaje'];
    if (!$torneo_id) {
        header('Location: /tornea/app/views/torneos.php');
        exit;
    }
    header('Location: /tornea/app/views/torneo-detalle.php?id=' . (int) $torneo_id . $ancla);
    exit;
}

// Genera el fixture de la liga, la llave de eliminación o la siguiente ronda del suizo
if ($accion === 'generar') {
    $torneo_id = (int) ($_POST['torneo_id'] ?? 0);
    volverAlTorneo($torneo_id, $ronda->generar($torneo_id, $usuario_id));
}

// Carga o corrige el resultado de un partido
if ($accion === 'resultado') {
    $enfrentamiento_id = (int) ($_POST['enfrentamiento_id'] ?? 0);
    $resultado = $ronda->cargarResultado(
        $enfrentamiento_id,
        trim($_POST['score_local'] ?? ''),
        trim($_POST['score_visitante'] ?? ''),
        $usuario_id
    );
    // Si salió bien, se vuelve al partido para seguir cargando los demás
    volverAlTorneo($ronda->torneoDeEnfrentamiento($enfrentamiento_id), $resultado,
        $resultado['exito'] ? '#partido-' . $enfrentamiento_id : '');
}

// Cierra la ronda que se está jugando (en eliminación, arma la siguiente)
if ($accion === 'cerrar') {
    $ronda_id = (int) ($_POST['ronda_id'] ?? 0);
    volverAlTorneo($ronda->torneoDeRonda($ronda_id), $ronda->cerrar($ronda_id, $usuario_id));
}

// Finaliza el torneo cuando ya no quedan rondas por jugar
if ($accion === 'finalizar') {
    $torneo_id = (int) ($_POST['torneo_id'] ?? 0);
    volverAlTorneo($torneo_id, $ronda->finalizarTorneo($torneo_id, $usuario_id));
}

header('Location: /tornea/app/views/torneos.php');
exit;
