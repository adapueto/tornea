<?php

require_once __DIR__ . '/../models/usuario.php';

session_start();

$accion = $_GET['accion'] ?? '';

$usuario = new Usuario();

if ($accion === 'registrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $apellido = $_POST['apellido'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_repeat = $_POST['password-repeat'] ?? '';

    if ($password !== $password_repeat) {
        $_SESSION['error'] = 'Las contraseñas no coinciden';
        header('Location: /tornea/app/views/register.php');
        exit;
    }

    $resultado = $usuario->registrar($nombre, $apellido, $email, $password);

    if ($resultado['exito']) {
        $_SESSION['exito'] = $resultado['mensaje'];
        header('Location: /tornea/app/views/login.php');
    } else {
        $_SESSION['error'] = $resultado['mensaje'];
        header('Location: /tornea/app/views/register.php');
    }
    exit;
}

if ($accion === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $resultado = $usuario->login($email, $password);

    if ($resultado['exito']) {
        $_SESSION['usuario'] = $resultado['usuario'];
        header('Location: /tornea/perfil.php');
    } else {
        $_SESSION['error'] = $resultado['mensaje'];
        header('Location: /tornea/app/views/login.php');
    }
    exit;
}

if ($accion === 'logout') {
    session_destroy();
    header('Location: /tornea/index.html');
    exit;
}