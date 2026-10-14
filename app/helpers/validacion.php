<?php

// Revisa los datos de una cuenta (registro, editar perfil y panel de admin)

// Devuelve los errores encontrados. Si no hay ninguno, está todo bien.
// En editar perfil no se manda contraseña, por eso puede venir vacía.
function validarDatosUsuario($nombre, $apellido, $email, $password = null) {
    $errores = [];
    if ($nombre === '' || $apellido === '') {
        $errores[] = 'Completá el nombre y el apellido';
    }
    if (mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) {
        $errores[] = 'El nombre y el apellido pueden tener hasta 100 caracteres';
    }
    if (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ingresá un email válido, por ejemplo nombre@dominio.com';
    }
    if ($password !== null && !passwordValida($password)) {
        $errores[] = 'La contraseña tiene que tener al menos 8 caracteres';
    }
    return $errores;
}

// La regla de la contraseña, para usarla en todos lados igual
function passwordValida($password) {
    return mb_strlen($password) >= 8;
}

// Lee un campo del formulario. Si no llega como texto, lo toma como vacío
function textoPost($campo) {
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? $valor : '';
}

// Dice si el error de la base es por un email repetido
function esDuplicado(PDOException $e) {
    return ($e->errorInfo[1] ?? null) == 1062;
}
