#!/bin/bash
# DCL para Docker: misma lógica que database/dcl.example.sql, pero con usuarios
# '@%' (el contenedor web se conecta desde otro host de la red de Docker)
# y contraseñas tomadas de variables de entorno (ver .env.example).
set -e

# "root" es el usuario interno del contenedor de MySQL (lo crea la imagen con
# MYSQL_ROOT_PASSWORD); no tiene relación con los usuarios de otros servidores.
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE USER IF NOT EXISTS 'tornea_app'@'%' IDENTIFIED BY '${DB_APP_PASSWORD}';
GRANT SELECT, INSERT, UPDATE, DELETE ON tornea.* TO 'tornea_app'@'%';

CREATE USER IF NOT EXISTS 'tornea_readonly'@'%' IDENTIFIED BY '${DB_READONLY_PASSWORD}';
GRANT SELECT ON tornea.* TO 'tornea_readonly'@'%';

CREATE USER IF NOT EXISTS 'tornea_backup'@'%' IDENTIFIED BY '${DB_BACKUP_PASSWORD}';
GRANT SELECT, LOCK TABLES ON tornea.* TO 'tornea_backup'@'%';

FLUSH PRIVILEGES;
SQL
