#!/bin/bash
# ============================================
# Script de consultas y monitoreo de la base de datos - Tornea
# Terobyte - Administración de SO
#
# MySQL corre en el contenedor "db", así que cada consulta se ejecuta
# adentro del contenedor con "docker compose exec".
# - Las consultas usan el usuario tornea_readonly (solo SELECT): aunque se
#   escriba mal una consulta, la base no se puede modificar desde acá.
# - Solo la opción de usuarios y permisos usa root, porque tornea_readonly
#   no puede leer la tabla mysql.user.
# Las contraseñas ya están dentro del contenedor (variables del .env),
# por eso no se escriben en este archivo.
#
# Uso: sudo ./monitoreo_bd.sh             (menú)
#      sudo ./monitoreo_bd.sh --informe   (resumen al log, para el cron)
# ============================================

source "$(dirname "$0")/config.sh"

LOG="/var/log/tornea/bd.log"
UMBRAL_CONEXIONES=50
UMBRAL_TAMANO_MB=500

if [ "$EUID" -ne 0 ]; then
    echo "Error: este script debe ejecutarse como root o con sudo."
    echo "Uso: sudo ./monitoreo_bd.sh"
    exit 1
fi

mkdir -p /var/log/tornea

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG"
}

# Ejecuta la consulta que llega por la entrada estándar con tornea_readonly.
# -t muestra el resultado como tabla; con "plano" sale sin bordes (para el log)
consulta() {
    local FORMATO="-t"
    [ "$1" = "plano" ] && FORMATO="-N -B"
    compose exec -T db sh -c "MYSQL_PWD=\"\$DB_READONLY_PASSWORD\" mysql -utornea_readonly $FORMATO tornea"
}

consulta_root() {
    compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -t'
}

base_responde() {
    compose exec -T db mysqladmin ping -h 127.0.0.1 --silent < /dev/null > /dev/null 2>&1
}

# COUNT(*) da el número exacto de filas; information_schema solo da una
# estimación. La lista de tablas se arma sola, así sirve si se agregan tablas.
sql_filas_por_tabla() {
    local TABLAS
    TABLAS=$(echo "SELECT table_name FROM information_schema.tables WHERE table_schema = 'tornea' ORDER BY table_name;" | consulta plano | tr -d '\r')
    local SQL=""
    for T in $TABLAS; do
        [ -n "$SQL" ] && SQL="$SQL UNION ALL "
        SQL="${SQL}SELECT '$T' AS tabla, COUNT(*) AS registros FROM \`$T\`"
    done
    echo "$SQL;"
}

estado_servidor() {
    echo "== ESTADO DEL SERVIDOR MYSQL =="
    consulta <<'SQL'
SELECT VERSION() AS version,
       (SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME = 'Uptime') DIV 3600 AS horas_encendido;
SHOW GLOBAL STATUS WHERE Variable_name IN
  ('Threads_connected', 'Max_used_connections', 'Questions', 'Slow_queries', 'Aborted_connects');
SHOW VARIABLES WHERE Variable_name IN ('max_connections', 'character_set_server');
SQL
}

filas_por_tabla() {
    echo "== REGISTROS POR TABLA =="
    sql_filas_por_tabla | consulta
}

tamano_base() {
    echo "== TAMANO DE LA BASE (MB) =="
    consulta <<'SQL'
SELECT table_name AS tabla,
       ROUND((data_length + index_length) / 1024 / 1024, 2) AS mb
FROM information_schema.tables
WHERE table_schema = 'tornea'
ORDER BY (data_length + index_length) DESC;
SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS total_mb
FROM information_schema.tables
WHERE table_schema = 'tornea';
SQL
}

resumen_app() {
    echo "== RESUMEN DE TORNEA =="
    consulta <<'SQL'
SELECT r.nombre AS rol, COUNT(ur.usuario_id) AS usuarios
FROM roles r LEFT JOIN usuario_roles ur ON ur.rol_id = r.id
GROUP BY r.id, r.nombre;
SELECT tipo, estado, COUNT(*) AS torneos
FROM torneos GROUP BY tipo, estado ORDER BY tipo, estado;
SELECT estado AS inscripciones, COUNT(*) AS cantidad
FROM participantes GROUP BY estado;
SELECT COUNT(*) AS partidos_sin_resultado
FROM enfrentamientos e LEFT JOIN resultados r ON r.enfrentamiento_id = e.id
WHERE r.id IS NULL;
SQL
}

ultimas_acciones() {
    echo "== ULTIMAS 15 ACCIONES (AUDITORIA) =="
    consulta <<'SQL'
SELECT a.fecha, COALESCE(u.email, '(borrado)') AS usuario, a.accion,
       a.tabla_afectada AS tabla, a.registro_id AS id
FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id
ORDER BY a.fecha DESC, a.id DESC
LIMIT 15;
SQL
}

usuarios_mysql() {
    echo "== USUARIOS DE MYSQL Y PERMISOS =="
    consulta_root <<'SQL'
SELECT user AS usuario, host FROM mysql.user
WHERE user NOT LIKE 'mysql.%' ORDER BY user;
SHOW GRANTS FOR 'tornea_app'@'%';
SHOW GRANTS FOR 'tornea_readonly'@'%';
SHOW GRANTS FOR 'tornea_backup'@'%';
SQL
}

consulta_libre() {
    echo "Consulta de solo lectura (tornea_readonly). Termina con ; y Enter."
    read -r -p "SQL> " SQL
    if [ -z "$SQL" ]; then
        echo "No se escribio ninguna consulta."
        return
    fi
    # Si es un INSERT, UPDATE, DELETE... MySQL la rechaza: el usuario no tiene permiso
    echo "$SQL" | consulta
}

# Modo para el cron: deja el estado en el log y marca las alertas
informe() {
    log "====== INFORME DE LA BASE ======"
    if ! base_responde; then
        log "ALERTA: la base no responde"
        log "====== FIN DEL INFORME ======"
        exit 1
    fi
    log "Base: responde"

    CONEXIONES=$(echo "SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME = 'Threads_connected';" | consulta plano | tr -d '\r')
    log "Conexiones abiertas: $CONEXIONES"
    if [ "$CONEXIONES" -gt "$UMBRAL_CONEXIONES" ]; then
        log "ALERTA: mas de $UMBRAL_CONEXIONES conexiones abiertas"
    fi

    LENTAS=$(echo "SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME = 'Slow_queries';" | consulta plano | tr -d '\r')
    log "Consultas lentas desde el arranque: $LENTAS"

    TAMANO=$(echo "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024) FROM information_schema.tables WHERE table_schema = 'tornea';" | consulta plano | tr -d '\r')
    log "Tamano de la base: ${TAMANO} MB"
    if [ "$TAMANO" -gt "$UMBRAL_TAMANO_MB" ]; then
        log "ALERTA: la base supera los ${UMBRAL_TAMANO_MB} MB"
    fi

    sql_filas_por_tabla | consulta plano | tr -d '\r' | while read -r TABLA FILAS; do
        log "Tabla $TABLA: $FILAS registros"
    done
    log "====== FIN DEL INFORME ======"
}

if [ "$1" = "--informe" ]; then
    informe
    exit 0
fi

if ! base_responde; then
    echo "La base no responde. Revisar el contenedor db con gestion_servicios.sh"
    exit 1
fi

while true; do
    echo ""
    echo "=== Consultas y Monitoreo de la Base de Datos ==="
    echo "1. Estado del servidor MySQL"
    echo "2. Registros por tabla"
    echo "3. Tamano de la base y de cada tabla"
    echo "4. Resumen de Tornea (usuarios, torneos, inscripciones)"
    echo "5. Ultimas acciones registradas (auditoria)"
    echo "6. Usuarios de MySQL y sus permisos"
    echo "7. Consulta libre (solo lectura)"
    echo "8. Guardar un informe en el log"
    echo "0. Salir"
    # Si se termina la entrada (Ctrl+D), se sale en vez de repetir el menú
    read -r -p "Opcion: " OPCION || exit 0
    echo ""
    case $OPCION in
        1) estado_servidor ;;
        2) filas_por_tabla ;;
        3) tamano_base ;;
        4) resumen_app ;;
        5) ultimas_acciones ;;
        6) usuarios_mysql ;;
        7) consulta_libre ;;
        8) informe && echo "Informe guardado en $LOG" && tail -n 25 "$LOG" ;;
        0) exit 0 ;;
        *) echo "Opcion Invalida" ;;
    esac
done
