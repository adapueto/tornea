#!/bin/bash
# ============================================
# Configuración común de los scripts del servidor - Tornea
# Terobyte - Administración de SO
#
# No se ejecuta solo: lo cargan backup, monitoreo y gestión de servicios
# con "source", así las rutas y los nombres quedan en un único lugar.
# ============================================

# Carpeta del proyecto (donde se clonó el repo). Se calcula a partir de
# dónde está este archivo (scripts/servidor/), así no hay que cambiarla
# si el repo se clona en otra ruta.
DIR_PROYECTO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# En el servidor Apache y MySQL no están instalados en AlmaLinux: corren en
# contenedores Docker definidos en este archivo (web = Apache + PHP, db = MySQL)
ARCHIVO_COMPOSE="$DIR_PROYECTO/docker-compose.prod.yml"

# Comando base para manejar los contenedores de Tornea.
# Ej: compose ps, compose restart web
compose() {
    docker compose -f "$ARCHIVO_COMPOSE" "$@"
}

# Contenedores del proyecto (nombres de servicio del docker-compose)
CONTENEDORES="web db"

# Dirección donde responde la app (prod publica el puerto 80)
URL_APP="http://localhost/tornea/index.php"
