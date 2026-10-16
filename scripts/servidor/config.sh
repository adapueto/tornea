#!/bin/bash
# ============================================
# Configuración común de los scripts del servidor - Tornea
# Terobyte - Administración de SO
#
# No se ejecuta solo: lo cargan backup, monitoreo y gestión de servicios
# con "source", así las rutas y los nombres quedan en un único lugar.
# ============================================

# Carpeta del proyecto (donde se clonó el repo y está el .env).
# Si los scripts están adentro del repo (scripts/servidor/), se usa ese repo.
# Si se copiaron sueltos a otra carpeta (por ejemplo /home/terobyte/scripts),
# se usa la ruta de abajo: cambiarla si el proyecto está en otro lado.
DIR_PROYECTO_SUELTO="/home/terobyte/tornea"

DIR_PROYECTO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
if [ ! -f "$DIR_PROYECTO/docker-compose.prod.yml" ]; then
    DIR_PROYECTO="$DIR_PROYECTO_SUELTO"
fi

# En el servidor Apache y MySQL no están instalados en AlmaLinux: corren en
# contenedores Docker definidos en este archivo (web = Apache + PHP, db = MySQL)
ARCHIVO_COMPOSE="$DIR_PROYECTO/docker-compose.prod.yml"

if [ ! -f "$ARCHIVO_COMPOSE" ]; then
    echo "Error: no se encontró $ARCHIVO_COMPOSE" >&2
    echo "Cambiá DIR_PROYECTO_SUELTO en config.sh por la carpeta del proyecto." >&2
    exit 1
fi

# Comando base para manejar los contenedores de Tornea.
# Ej: compose ps, compose restart web
compose() {
    docker compose -f "$ARCHIVO_COMPOSE" "$@"
}

# Contenedores del proyecto (nombres de servicio del docker-compose)
CONTENEDORES="web db"

# Dirección donde responde la app (prod publica el puerto 80)
URL_APP="http://localhost/tornea/index.php"
