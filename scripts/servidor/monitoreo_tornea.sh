#!/bin/bash

# ============================================
# Script de monitoreo - Tornea
# Terobyte - Administración de SO
#
# Revisa los recursos del servidor y que Tornea esté funcionando.
# Apache y MySQL no son servicios de AlmaLinux: corren en los contenedores
# "web" y "db" de Docker, así que se controla el servicio docker, el
# estado de cada contenedor y que la página realmente responda.
#
# Uso: sudo ./monitoreo_tornea.sh   (o desde el cron de root)
# ============================================

source "$(dirname "$0")/config.sh"

LOG="/var/log/tornea/monitoreo.log"
UMBRAL_CPU=80
UMBRAL_RAM=80
UMBRAL_DISCO=90

if [ "$EUID" -ne 0 ]; then
    echo "Error: este script debe ejecutarse como root o con sudo."
    exit 1
fi

mkdir -p /var/log/tornea

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG"
}

log "====== INICIO DE MONITOREO ======"

# CPU
# vmstat mide durante 1 segundo; la columna 15 es el % de CPU libre (idle)
CPU_USO=$(vmstat 1 2 | tail -1 | awk '{print 100 - $15}')
log "CPU: ${CPU_USO}% en uso"
if [ "$CPU_USO" -gt "$UMBRAL_CPU" ]; then
    log "ALERTA: uso de CPU supera el ${UMBRAL_CPU}%"
fi

# RAM
# Se usa la columna "available" (lo que realmente pueden usar los programas);
# "used" no cuenta la memoria que Linux usa como caché y libera cuando hace falta
RAM_TOTAL=$(free -m | awk '/Mem:/ {print $2}')
RAM_DISPONIBLE=$(free -m | awk '/Mem:/ {print $7}')
RAM_USADA=$(( RAM_TOTAL - RAM_DISPONIBLE ))
RAM_PORCENTAJE=$(( RAM_USADA * 100 / RAM_TOTAL ))
log "RAM: ${RAM_USADA}MB / ${RAM_TOTAL}MB (${RAM_PORCENTAJE}%)"
if [ "$RAM_PORCENTAJE" -gt "$UMBRAL_RAM" ]; then
    log "ALERTA: uso de RAM supera el ${UMBRAL_RAM}%"
fi

# DISCO
# Las imágenes y la base de Docker se guardan en /var/lib/docker (en la raíz)
DISCO_USO=$(df / | awk 'NR==2 {print $5}' | cut -d'%' -f1)
log "Disco: ${DISCO_USO}% utilizado"
if [ "$DISCO_USO" -gt "$UMBRAL_DISCO" ]; then
    log "ALERTA: uso de disco supera el ${UMBRAL_DISCO}%"
fi

# SERVICIO DOCKER (sin él no corre ningún contenedor)
if systemctl is-active --quiet docker; then
    log "Docker: ACTIVO"
else
    log "ALERTA: Docker no está corriendo"
    systemctl start docker
    log "Se intentó iniciar Docker"
fi

# CONTENEDORES DE TORNEA (web = Apache + PHP, db = MySQL)
for CONTENEDOR in $CONTENEDORES; do
    if [ -n "$(compose ps --status running -q "$CONTENEDOR" 2>/dev/null)" ]; then
        log "Contenedor $CONTENEDOR: ACTIVO"
    else
        log "ALERTA: el contenedor $CONTENEDOR no está corriendo"
        # up -d lo crea si no existe y lo arranca si estaba detenido
        compose up -d "$CONTENEDOR" >> "$LOG" 2>&1
        log "Se intentó iniciar el contenedor $CONTENEDOR"
    fi
done

# SALUD DE LA BASE (healthcheck definido en docker-compose: mysqladmin ping)
ID_DB=$(compose ps -q db 2>/dev/null)
if [ -n "$ID_DB" ]; then
    SALUD_DB=$(docker inspect --format '{{.State.Health.Status}}' "$ID_DB" 2>/dev/null)
    log "Salud de la base: $SALUD_DB"
    if [ "$SALUD_DB" = "unhealthy" ]; then
        log "ALERTA: la base no responde"
    fi
fi

# LA PÁGINA RESPONDE (Apache + PHP + conexión a la base)
CODIGO_HTTP=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$URL_APP")
if [ "$CODIGO_HTTP" = "200" ]; then
    log "Página de Tornea: responde (HTTP $CODIGO_HTTP)"
else
    log "ALERTA: la página de Tornea no responde bien (HTTP $CODIGO_HTTP)"
fi

# CONEXIONES
# La base no se publica fuera de Docker, así que solo se cuentan HTTP/HTTPS
CONEXIONES=$(ss -Htn state established '( sport = :80 or sport = :443 )' | wc -l)
log "Conexiones activas (HTTP/HTTPS): ${CONEXIONES}"

log "====== FIN DE MONITOREO ======"
