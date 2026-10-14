<?php

require_once __DIR__ . '/../models/usuario.php';
require_once __DIR__ . '/../arranque.php';

$accion = $_GET['accion'] ?? '';

$usuario = new Usuario();

// Todo lo que cambia datos (registro, login, editar perfil, salir) llega por
// POST y tiene que traer el token de la sesión; si no, se corta acá
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
}

if ($accion === 'registrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = textoPost('nombre');
    $apellido = textoPost('apellido');
    $email = textoPost('email');
    $password = textoPost('password');
    $password_repeat = textoPost('password-repeat');

    if ($password !== $password_repeat) {
        $resultado = ['exito' => false, 'mensaje' => 'Las contraseñas no coinciden'];
    } else {
        $resultado = $usuario->registrar($nombre, $apellido, $email, $password);
    }

    if ($resultado['exito']) {
        $_SESSION['exito'] = $resultado['mensaje'];
        header('Location: /tornea/app/views/login.php');
    } else {
        $_SESSION['error'] = $resultado['mensaje'];
        // Guarda lo que escribió (menos la contraseña) para no tener que escribirlo de nuevo
        $_SESSION['form_registro'] = ['nombre' => $nombre, 'apellido' => $apellido, 'email' => $email];
        header('Location: /tornea/app/views/register.php');
    }
    exit;
}

if ($accion === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = textoPost('email');
    $password = textoPost('password');

    $resultado = $usuario->login($email, $password);

    if ($resultado['exito']) {
        // Sesión nueva al entrar: así nadie puede "prestarte" un id de sesión suyo
        session_regenerate_id(true);
        // El token de los formularios también se renueva (se crea otro en la próxima página)
        unset($_SESSION['csrf']);
        $_SESSION['usuario'] = $resultado['usuario'];
        header('Location: /tornea/app/views/perfil.php');
    } else {
        $_SESSION['error'] = $resultado['mensaje'];
        header('Location: /tornea/app/views/login.php');
    }
    exit;
}

// Registrar y login son públicos; lo que sigue requiere sesión
if (!isset($_SESSION['usuario'])) {
    $_SESSION['error'] = 'Tenés que iniciar sesión para continuar';
    header('Location: /tornea/app/views/login.php');
    exit;
}

if ($accion === 'actualizar' &&$_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_SESSION['usuario']['id'];
    $nombre = textoPost('nombre');
    $apellido = textoPost('apellido');
    $email = textoPost('email');
    $fecha_nac = textoPost('fecha_nac') !== '' ? textoPost('fecha_nac') : null;
    $perfil_publico = isset($_POST['perfil_publico']) ? 1 : 0;

    $resultado = $usuario->actualizar($id, $nombre, $apellido, $email, $fecha_nac, $perfil_publico,
        textoPost('actual'), textoPost('nueva'), textoPost('nueva-repeat'));

    if ($resultado['exito']) {
        // Con contraseña nueva, la sesión también cambia de id (como al iniciar sesión)
        if ($resultado['clave_cambiada']) {
            session_regenerate_id(true);
            unset($_SESSION['csrf']);
        }
        $datos = $usuario->buscarPorId($id);
        $_SESSION['usuario'] = $datos;
        $_SESSION['exito'] = $resultado['mensaje'];
        header('Location: /tornea/app/views/perfil.php');
    } else {
        // Vuelve al formulario y muestra qué está mal
        $_SESSION['error'] = $resultado['mensaje'];
        $_SESSION['form_perfil'] = [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'fecha_nac' => $fecha_nac,
            'perfil_publico' => $perfil_publico,
        ];
        header('Location: /tornea/app/views/perfil-editar.php');
    }
    exit;
}

// Cerrar sesión solo por POST: así una página de afuera no puede desloguearte
// con solo cargar una imagen (que sería un GET)
if ($accion === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vacía los datos y cierra la sesión del todo
    $_SESSION = [];
    session_destroy();
    header('Location: /tornea/app/views/login.php');
    exit;
}