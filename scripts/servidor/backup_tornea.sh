#!/bin/bash

# ============================================
# Script de respaldos - Tornea
# Terobyte - Administración de SO
#
# - Domingos: respaldo COMPLETO de los archivos del proyecto
# - Lunes a sábado: respaldo INCREMENTAL (solo lo que cambió desde el
#   respaldo anterior, usando el archivo de control de GNU tar)
# - Base de datos: se respalda entera todos los días (comprimida). Un
#   incremental de MySQL necesitaría los binlogs; con el tamaño de esta base
#   un volcado diario es más simple y se restaura con un solo comando.
#
# La base corre en el contenedor "db", así que mysqldump se ejecuta adentro
# del contenedor con el usuario tornea_backup (solo SELECT y LOCK TABLES).
# La contraseña ya está en el contenedor (variable DB_BACKUP_PASSWORD del
# .env), por eso no se escribe en este archivo.
#
# Uso: sudo ./backup_tornea.sh   (o desde el cron de root)
# ============================================

source "$(dirname "$0")/config.sh"

# Si falla cualquier comando de una tubería (mysqldump | gzip), que cuente como error
set -o pipefail

# Los respaldos tienen datos de los usuarios y el .env con contraseñas:
# solo root puede leerlos
umask 077

FECHA=$(date +%Y-%m-%d)
DIA_SEMANA=$(date +%u)
DIR_BACKUPS="/var/backups/tornea"
DIR_COMPLETOS="$DIR_BACKUPS/completos"
DIR_INCREMENTALES="$DIR_BACKUPS/incrementales"
# Archivo de control de tar: guarda qué archivos había en el último respaldo
SNAPSHOT="$DIR_BACKUPS/proyecto.snar"
DB_NOMBRE="tornea"
DB_USUARIO="tornea_backup"
LOG="$DIR_BACKUPS/backup.log"
RETENCION=28
ERRORES=0

if [ "$EUID" -ne 0 ]; then
    echo "Error: este script debe ejecutarse como root o con sudo."
    exit 1
fi

mkdir -p "$DIR_COMPLETOS" "$DIR_INCREMENTALES"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG"
}

# ARCHIVOS DEL PROYECTO
# Domingo, o si todavía no hay ningún completo (primera vez): completo
if [ "$DIA_SEMANA" -eq 7 ] || [ ! -f "$SNAPSHOT" ]; then
    TIPO="COMPLETO"
    DESTINO="$DIR_COMPLETOS"
    # Sin archivo de control, tar guarda todo y crea uno nuevo
    rm -f "$SNAPSHOT"
    ARCHIVO_TAR="$DIR_COMPLETOS/proyecto_completo_$FECHA.tar.gz"
else
    TIPO="INCREMENTAL"
    DESTINO="$DIR_INCREMENTALES"
    ARCHIVO_TAR="$DIR_INCREMENTALES/proyecto_inc_$FECHA.tar.gz"
fi

log "Iniciando respaldo $TIPO"

# -C deja rutas relativas dentro del .tar.gz (tornea/...), así se puede
# restaurar en cualquier carpeta
if tar -czf "$ARCHIVO_TAR" --listed-incremental="$SNAPSHOT" \
        -C "$(dirname "$DIR_PROYECTO")" "$(basename "$DIR_PROYECTO")" 2>> "$LOG"; then
    log "Archivos del proyecto respaldados correctamente ($ARCHIVO_TAR)"
else
    log "ERROR al respaldar archivos del proyecto"
    ERRORES=$((ERRORES + 1))
fi

# BASE DE DATOS
ARCHIVO_DB="$DESTINO/db_${FECHA}.sql.gz"

# Comprobar que el contenedor de la base esté corriendo
if [ -z "$(compose ps --status running -q db 2>/dev/null)" ]; then
    log "ERROR: el contenedor de la base (db) no está corriendo, no se respaldó la base"
    ERRORES=$((ERRORES + 1))
else
    # -T: sin terminal, para poder redirigir la salida a un archivo
    # --single-transaction: copia consistente sin bloquear a la app
    # --no-tablespaces: MySQL 8 lo pide cuando el usuario no tiene permiso PROCESS
    # MYSQL_PWD: la contraseña no queda a la vista en la lista de procesos
    if compose exec -T db sh -c \
            "MYSQL_PWD=\"\$DB_BACKUP_PASSWORD\" mysqldump -u$DB_USUARIO --single-transaction --no-tablespaces $DB_NOMBRE" \
            2>> "$LOG" | gzip > "$ARCHIVO_DB"; then
        log "Base de datos respaldada correctamente ($ARCHIVO_DB)"
    else
        log "ERROR al respaldar la base de datos"
        rm -f "$ARCHIVO_DB"
        ERRORES=$((ERRORES + 1))
    fi
fi

log "Respaldo $TIPO finalizado con $ERRORES error(es)"

# LIMPIEZA
find "$DIR_COMPLETOS" -type f -mtime +$RETENCION -delete
find "$DIR_INCREMENTALES" -type f -mtime +$RETENCION -delete
log "Limpieza de archivos antiguos completada"

# Código de salida distinto de 0 si algo falló (lo ve cron y quien lo corra a mano)
[ "$ERRORES" -eq 0 ]
