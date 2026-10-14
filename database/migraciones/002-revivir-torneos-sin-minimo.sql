-- =============================================
-- Devuelve a "publicado" los torneos que quedaron "en curso" sin rondas y
-- con menos de 2 inscriptos aprobados. Antes, un torneo arrancaba solo en
-- su fecha aunque no tuviera gente, y quedaba trabado: no se podían armar
-- rondas, ni finalizarlo, ni editarlo. Con 2 o más ya se pueden armar las
-- rondas, así que esos no se tocan.
--
-- No cambia la estructura: el código nuevo anda con o sin esta migración.
-- Se puede correr más de una vez (la segunda no encuentra nada):
--   docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" tornea' < database/migraciones/002-revivir-torneos-sin-minimo.sql
-- =============================================

START TRANSACTION;

-- Los torneos trabados
CREATE TEMPORARY TABLE torneos_trabados AS
SELECT t.id
FROM torneos t
WHERE t.estado = 'en_curso'
  AND NOT EXISTS (SELECT 1 FROM rondas r WHERE r.torneo_id = t.id)
  AND (SELECT COUNT(*) FROM participantes p
       WHERE p.torneo_id = t.id AND p.estado = 'aprobado') < 2;

UPDATE torneos SET estado = 'publicado'
WHERE id IN (SELECT id FROM torneos_trabados);

-- Queda en el historial como un cambio del sistema (sin usuario)
INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id)
SELECT NULL, 'UPDATE', 'torneos', id FROM torneos_trabados;

COMMIT;

-- Cuántos quedaron cambiados
SELECT COUNT(*) AS torneos_revividos FROM torneos_trabados;
DROP TEMPORARY TABLE torneos_trabados;
