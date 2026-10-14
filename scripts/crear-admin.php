<?php
// Crea una cuenta de administrador, o le cambia la contraseña a una que ya existe
// y la deja como administradora. Se usa desde la consola del servidor:
//   docker compose -f docker-compose.prod.yml exec web php scripts/crear-admin.php

// Solo desde la consola, nunca desde el navegador
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/models/admin.php';

// Si se escribe a mano (y no pasado por un archivo), la contraseña se oculta
$es_terminal = stream_isatty(STDIN);

// Hace una pregunta y devuelve lo que se escribió.
// Con $oculto no se ve lo que se escribe (para la contraseña).
function preguntar($texto, $oculto = false) {
    global $es_terminal;
    echo $texto;
    $ocultar = $oculto && $es_terminal;
    if ($ocultar) {
        shell_exec('stty -echo');
    }
    $linea = (string) fgets(STDIN);
    // A la contraseña solo se le saca el Enter: los espacios cuentan, igual que en la web
    $respuesta = $oculto ? rtrim($linea, "\r\n") : trim($linea);
    if ($ocultar) {
        shell_exec('stty echo');
        echo PHP_EOL;
    }
    return $respuesta;
}

function terminar($mensaje) {
    fwrite(STDERR, $mensaje . PHP_EOL);
    exit(1);
}

$admin = new Admin();
$email = preguntar('Email: ');
$cuenta = $admin->buscarPorEmail($email);

if ($cuenta) {
    echo "Ya existe la cuenta de {$cuenta['nombre']} {$cuenta['apellido']}: se le cambia la contraseña" . PHP_EOL;
} else {
    $nombre = preguntar('Nombre: ');
    $apellido = preguntar('Apellido: ');
}

$password = preguntar('Contraseña (al menos 8 caracteres): ', true);
if ($password !== preguntar('Repetí la contraseña: ', true)) {
    terminar('Las contraseñas no coinciden. No se cambió nada.');
}

// El cambio lo hace "Sistema" (sin usuario) en el historial
if ($cuenta) {
    $resultado = $admin->cambiarPassword($cuenta['id'], $password, null);
    if ($resultado['exito'] && $cuenta['rol'] !== 'admin') {
        $resultado = $admin->cambiarRol($cuenta['id'], 'admin', null);
        if (!$resultado['exito']) {
            terminar('La contraseña se cambió, pero no se pudo dar el rol de administrador: ' . $resultado['mensaje']);
        }
    }
} else {
    $resultado = $admin->crearUsuario([
        'nombre' => $nombre, 'apellido' => $apellido, 'email' => $email,
        'password' => $password, 'rol' => 'admin',
    ], null);
}

if (!$resultado['exito']) {
    terminar($resultado['mensaje']);
}
echo "Listo: $email es administrador y ya puede iniciar sesión." . PHP_EOL;
