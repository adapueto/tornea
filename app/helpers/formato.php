<?php

// Funciones para mostrar datos en las vistas

// Escapa texto para mostrarlo en HTML de forma segura
function e($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function etiquetaTipo($tipo) {
    $tipos = ['liga' => 'Liga', 'eliminacion' => 'Eliminación directa', 'suizo' => 'Sistema suizo'];
    return $tipos[$tipo] ?? $tipo;
}

function etiquetaEstado($estado) {
    $estados = ['borrador' => 'Borrador', 'publicado' => 'Publicado', 'en_curso' => 'En curso', 'finalizado' => 'Finalizado'];
    return $estados[$estado] ?? $estado;
}

function etiquetaEstadoPartido($estado) {
    $estados = ['pendiente' => 'Pendiente', 'en_curso' => 'En curso', 'finalizado' => 'Finalizado'];
    return $estados[$estado] ?? $estado;
}

// "Fecha 3 · jugada", "Ronda 2 · en juego", "Fecha 5 · próxima"
function etiquetaRonda($prefijo, $numero, $estado) {
    $estados = ['finalizada' => 'jugada', 'en_curso' => 'en juego', 'pendiente' => 'próxima'];
    return $prefijo . ' ' . $numero . ' · ' . ($estados[$estado] ?? $estado);
}

// Nombre de una ronda de eliminación directa según cuántos cruces tiene
function nombreRondaEliminacion($cruces) {
    $nombres = [1 => 'Final', 2 => 'Semifinal', 4 => 'Cuartos de final', 8 => 'Octavos de final'];
    return $nombres[$cruces] ?? 'Ronda de ' . ($cruces * 2);
}

// Clase CSS del estado: torneo-estado-publicado, torneo-estado-en-curso, etc.
function claseEstado($estado) {
    return 'torneo-estado-' . str_replace('_', '-', $estado);
}

function iconoDeporte($deporte) {
    $iconos = [
        'Fútbol' => '⚽', 'Básquet' => '🏀', 'Vóley' => '🏐', 'Handball' => '🤾',
        'Ajedrez' => '♟️', 'Tenis' => '🎾', 'Pádel' => '🎾', 'Ping Pong' => '🏓', 'eSports' => '🎮',
    ];
    // Compara por el comienzo para que "Fútbol 5" o "eSports - FIFA" también tengan ícono
    foreach ($iconos as $nombre => $icono) {
        if (strpos($deporte, $nombre) === 0) {
            return $icono;
        }
    }
    return '🏆';
}

// "10 ago — 20 sep 2026", "1 — 15 jul 2026" o "5 ago 2026"
function formatearFechas($inicio, $fin) {
    if (!$inicio) {
        return 'Fecha a definir';
    }
    $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $i = new DateTime($inicio);
    $textoFin = function ($f) use ($meses) {
        return $f->format('j') . ' ' . $meses[(int) $f->format('n')] . ' ' . $f->format('Y');
    };

    if (!$fin || $fin === $inicio) {
        return $textoFin($i);
    }
    $f = new DateTime($fin);
    if ($i->format('Y-m') === $f->format('Y-m')) {
        return $i->format('j') . ' — ' . $textoFin($f);
    }
    if ($i->format('Y') === $f->format('Y')) {
        return $i->format('j') . ' ' . $meses[(int) $i->format('n')] . ' — ' . $textoFin($f);
    }
    return $textoFin($i) . ' — ' . $textoFin($f);
}
