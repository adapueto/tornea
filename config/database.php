<?php

// En Docker los valores vienen de variables de entorno (docker-compose.yml).
// Sin Docker (XAMPP) se usan los valores por defecto.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'tornea');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// Devuelve la conexión a la base. Se abre una sola vez por página:
// la primera llamada la crea y las siguientes reutilizan la misma.
function conectar() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        // El detalle queda en el log del servidor; a la persona solo le mostramos qué hacer
        error_log('Error de conexión a la base: ' . $e->getMessage());
        http_response_code(503);
        exit('No pudimos conectarnos con la base de datos. Probá de nuevo en unos minutos.');
    }
}