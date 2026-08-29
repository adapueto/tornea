CREATE DATABASE IF NOT EXISTS tornea;

CREATE USER 'tornea_app'@'localhost' IDENTIFIED BY 'CAMBIAR_ESTO';
GRANT SELECT, INSERT, UPDATE, DELETE ON tornea.* TO 'tornea_app'@'localhost';

CREATE USER 'tornea_readonly'@'localhost' IDENTIFIED BY 'CAMBIAR_ESTO';
GRANT SELECT ON tornea.* TO 'tornea_readonly'@'localhost';

CREATE USER 'tornea_backup'@'localhost' IDENTIFIED BY 'CAMBIAR_ESTO';
GRANT SELECT, LOCK TABLES ON tornea.* TO 'tornea_backup'@'localhost';

FLUSH PRIVILEGES;
