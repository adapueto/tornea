#!/bin/bash
# ============================================
# Script de gestión de servicios - Tornea
# Terobyte - Administración de SO
#
# Maneja dos tipos de servicios:
# - Servicios de AlmaLinux (systemd): docker, sshd, firewalld, crond
# - Contenedores de Tornea (Docker): web (Apache + PHP) y db (MySQL)
#
# Apache y MySQL no están instalados en el sistema (no existen httpd ni
# mysqld): corren dentro de los contenedores, por eso se manejan con
# "docker compose" en vez de systemctl.
#
# Uso: sudo ./gestion_servicios.sh
# ============================================

source "$(dirname "$0")/config.sh"

SERVICIOS_SISTEMA="docker sshd firewalld crond"

if [ "$EUID" -ne 0 ]; then
    echo "Error: este script debe ejecutarse como root o con sudo."
    echo "Uso: sudo ./gestion_servicios.sh"
    exit 1
fi

# Devuelve 0 si la palabra $1 está en la lista $2
esta_en_lista() {
    for ELEMENTO in $2; do
        [ "$ELEMENTO" = "$1" ] && return 0
    done
    return 1
}

# -- Lista de servicios disponibles --
echo "=== Gestion de Servicios ==="
echo ""
echo "Servicios del sistema (systemd):"
for S in $SERVICIOS_SISTEMA; do
    printf "  %-10s %s\n" "$S" "$(systemctl is-active "$S")"
done
echo ""
echo "Contenedores de Tornea (Docker):"
echo "  web        Apache + PHP (la pagina)"
echo "  db         MySQL (la base de datos)"
echo ""
read -p "Nombre del servicio (ej: web, docker): " SERVICIO

# Solo se aceptan nombres de la lista, así no se puede escribir cualquier cosa
if esta_en_lista "$SERVICIO" "$SERVICIOS_SISTEMA"; then
    TIPO="sistema"
elif esta_en_lista "$SERVICIO" "$CONTENEDORES"; then
    TIPO="contenedor"
else
    echo "El servicio '$SERVICIO' no esta en la lista."
    exit 1
fi

# --- Menu de operaciones --
echo ""
echo "Que queres hacer con $SERVICIO?"
echo "1. Iniciar"
echo "2. Detener"
echo "3. Reiniciar"
if [ "$TIPO" = "sistema" ]; then
    echo "4. Habilitar (arranque automatico)"
    echo "5. Deshabilitar (arranque automatico)"
else
    # Los contenedores ya arrancan solos (restart: unless-stopped en el
    # docker-compose), siempre que el servicio docker esté habilitado
    echo "4. Ver los ultimos registros (logs)"
fi
echo ""
read -p "Opcion: " OPCION

if [ "$TIPO" = "sistema" ]; then
    case $OPCION in
        1) systemctl start "$SERVICIO" ;;
        2) systemctl stop "$SERVICIO" ;;
        3) systemctl restart "$SERVICIO" ;;
        4) systemctl enable "$SERVICIO" ;;
        5) systemctl disable "$SERVICIO" ;;
        *) echo "Opcion Invalida"; exit 1 ;;
    esac

    # -- Status Final --
    echo ""
    echo "== STATUS ACTUAL DE $SERVICIO =="
    systemctl status "$SERVICIO" --no-pager
else
    case $OPCION in
        1) compose up -d "$SERVICIO" ;;
        2) compose stop "$SERVICIO" ;;
        3) compose restart "$SERVICIO" ;;
        4) compose logs --tail 30 "$SERVICIO" ;;
        *) echo "Opcion Invalida"; exit 1 ;;
    esac

    # -- Status Final --
    echo ""
    echo "== STATUS ACTUAL DE LOS CONTENEDORES DE TORNEA =="
    compose ps
fi
