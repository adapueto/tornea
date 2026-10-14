<?php
// Lo que hace cada página y cada controller antes de empezar

session_start();
require_once __DIR__ . '/helpers/formato.php';
require_once __DIR__ . '/helpers/csrf.php';

// La página se arma entera antes de mandarla, así un error a mitad
// de camino puede reemplazarla por la página de error
ob_start();

// Muestra la página de error en lugar de lo que se venía armando.
// $detalle solo se ve en desarrollo (con los errores visibles).
function mostrarError($codigo, $mensaje, $detalle = '') {
    // La ruta se muestra desde la carpeta del proyecto (app/views/...)
    $detalle = str_replace(dirname(__DIR__) . '/', '', $detalle);
    // Se descarta lo que ya se había armado de la página
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    // Si se iba a redirigir a otra página, ya no
    header_remove('Location');
    http_response_code($codigo);
    require __DIR__ . '/views/partials/error.php';
    exit;
}

// Si un error se escapa sin que nadie lo ataje, cae acá
set_exception_handler(function (Throwable $error) {
    // El detalle queda en el log del servidor
    error_log('Error sin atajar: ' . $error);
    $detalle = $error->getMessage() . ' (' . $error->getFile() . ':' . $error->getLine() . ')';
    mostrarError(500, 'No pudimos completar lo que pediste. Probá de nuevo en unos minutos.', $detalle);
});

// Los errores graves que no son excepciones (por ejemplo, quedarse sin memoria)
// cortan la página; al terminar se cambia lo que quedó por la página de error
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $detalle = $error['message'] . ' (' . $error['file'] . ':' . $error['line'] . ')';
        mostrarError(500, 'No pudimos completar lo que pediste. Probá de nuevo en unos minutos.', $detalle);
    }
});
