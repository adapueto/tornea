<?php

// Protección contra pedidos falsos armados desde otra web (CSRF).
// La idea: cada formulario lleva un secreto que solo conoce la sesión,
// y el controller no hace nada si ese secreto no viene o no coincide.

// Devuelve el token de la sesión. Si todavía no hay, crea uno al azar.
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// Campo oculto para pegar dentro de cada formulario que cambia datos
function campoCsrf() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

// Corta el pedido con un 403 si no trae el token correcto.
// Si la sesión todavía no tiene token, también se rechaza (los dos vacíos coinciden).
function verificarCsrf() {
    $enSesion = $_SESSION['csrf'] ?? '';
    // Si no llega un texto (por ejemplo, una lista), cuenta como que no vino
    $enPost = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    if ($enSesion === '' || !hash_equals($enSesion, $enPost)) {
        http_response_code(403);
        exit('Petición no válida');
    }
}
