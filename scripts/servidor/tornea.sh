#!/bin/bash
# ============================================
# Script general de gestión del servidor - Tornea
# Terobyte - Administración de SO
#
# Menú principal: desde acá se llega a todos los demás scripts
# (usuarios, servicios, base de datos, seguridad, respaldos y monitoreo)
# y se maneja la aplicación en Docker sin tener que recordar los comandos.
#
# Uso: sudo ./tornea.sh
# ============================================

source "$(dirname "$0")/config.sh"

DIR_SCRIPTS="$(cd "$(dirname "$0")" && pwd)"
DIR_LOGS="/var/log/tornea"
DIR_BACKUPS="/var/backups/tornea"

if [ "$EUID" -ne 0 ]; then
    echo "Error: este script debe ejecutarse como root o con sudo."
    echo "Uso: sudo ./tornea.sh"
    exit 1
fi

pausa() {
    echo ""
    read -r -p "Enter para volver al menu..." _
}

estado_general() {
    echo "== ESTADO GENERAL DEL SERVIDOR =="
    echo "Equipo:        $(hostname)"
    echo "Sistema:       $(. /etc/os-release && echo "$PRETTY_NAME")"
    echo "Encendido:     $(uptime -p)"
    echo "Carga (1 min): $(cut -d' ' -f1 /proc/loadavg)"
    free -m | awk '/Mem:/ {printf "RAM:           %d MB usados de %d MB\n", $2 - $7, $2}'
    df -h / | awk 'NR == 2 {printf "Disco (/):     %s usado de %s (%s)\n", $3, $2, $5}'
    echo ""
    echo "-- Aplicacion (Docker: $(systemctl is-active docker)) --"
    compose ps --format 'table {{.Service}}\t{{.State}}\t{{.Status}}'
    CODIGO_HTTP=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$URL_APP")
    echo "Pagina de Tornea: HTTP $CODIGO_HTTP"
    echo ""
    echo "-- Ultimo respaldo --"
    ls -t "$DIR_BACKUPS"/completos/*.gz "$DIR_BACKUPS"/incrementales/*.gz 2>/dev/null | head -2 || true
    [ -d "$DIR_BACKUPS" ] || echo "Todavia no hay respaldos"
    echo ""
    echo "-- Ultimas alertas --"
    grep -h ALERTA "$DIR_LOGS"/*.log 2>/dev/null | tail -5
    grep -qh ALERTA "$DIR_LOGS"/*.log 2>/dev/null || echo "Ninguna"
}

menu_aplicacion() {
    echo "== APLICACION (DOCKER) =="
    echo "1. Levantar la aplicacion"
    echo "2. Detener la aplicacion"
    echo "3. Actualizar (traer cambios de GitHub y reconstruir)"
    echo "4. Ver los ultimos registros de los contenedores"
    echo "5. Uso de recursos de cada contenedor"
    # Si se termina la entrada (Ctrl+D), se sale en vez de repetir el menú
    read -r -p "Opcion: " OPCION || exit 0
    case $OPCION in
        1) compose up -d ;;
        2) compose stop ;;
        3)
            # La base queda en el volumen db_data: reconstruir no borra datos
            git -C "$DIR_PROYECTO" pull && compose up -d --build
            ;;
        4) compose logs --tail 20 ;;
        5) docker stats --no-stream $(compose ps -q) ;;
        *) echo "Opcion Invalida" ;;
    esac
    echo ""
    compose ps
}

ver_logs() {
    echo "== REGISTROS =="
    echo "1. Respaldos       ($DIR_BACKUPS/backup.log)"
    echo "2. Monitoreo       ($DIR_LOGS/monitoreo.log)"
    echo "3. Base de datos   ($DIR_LOGS/bd.log)"
    echo "4. Antivirus       ($DIR_LOGS/antivirus.log)"
    # Si se termina la entrada (Ctrl+D), se sale en vez de repetir el menú
    read -r -p "Opcion: " OPCION || exit 0
    case $OPCION in
        1) ARCHIVO="$DIR_BACKUPS/backup.log" ;;
        2) ARCHIVO="$DIR_LOGS/monitoreo.log" ;;
        3) ARCHIVO="$DIR_LOGS/bd.log" ;;
        4) ARCHIVO="$DIR_LOGS/antivirus.log" ;;
        *) echo "Opcion Invalida"; return ;;
    esac
    if [ -f "$ARCHIVO" ]; then
        tail -n 25 "$ARCHIVO"
    else
        echo "Todavia no existe $ARCHIVO"
    fi
}

while true; do
    clear
    echo "=========================================="
    echo "   TORNEA - Gestion del Servidor"
    echo "   Terobyte - $(hostname) - $(date '+%d/%m/%Y %H:%M')"
    echo "=========================================="
    echo "1. Estado general"
    echo "2. Usuarios del sistema"
    echo "3. Servicios y contenedores"
    echo "4. Base de datos (consultas y monitoreo)"
    echo "5. Seguridad (firewall, antivirus)"
    echo "6. Hacer un respaldo ahora"
    echo "7. Correr el monitoreo ahora"
    echo "8. Aplicacion (Docker)"
    echo "9. Ver registros"
    echo "0. Salir"
    # Si se termina la entrada (Ctrl+D), se sale en vez de repetir el menú
    read -r -p "Opcion: " OPCION || exit 0
    echo ""
    case $OPCION in
        1) estado_general; pausa ;;
        2) "$DIR_SCRIPTS/gestion_usuarios.sh" ;;
        3) "$DIR_SCRIPTS/gestion_servicios.sh"; pausa ;;
        4) "$DIR_SCRIPTS/monitoreo_bd.sh" ;;
        5) "$DIR_SCRIPTS/seguridad_tornea.sh" ;;
        # < /dev/null: docker compose exec (adentro del script) se comería lo que
        # se escriba en el menú
        6) "$DIR_SCRIPTS/backup_tornea.sh" < /dev/null && echo "Respaldo terminado."; tail -n 8 "$DIR_BACKUPS/backup.log"; pausa ;;
        7) "$DIR_SCRIPTS/monitoreo_tornea.sh" < /dev/null; tail -n 14 "$DIR_LOGS/monitoreo.log"; pausa ;;
        8) menu_aplicacion; pausa ;;
        9) ver_logs; pausa ;;
        0) exit 0 ;;
        *) echo "Opcion Invalida"; pausa ;;
    esac
done
