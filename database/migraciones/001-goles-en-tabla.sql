-- =============================================
-- Agrega goles a favor (gf) y en contra (gc) a la tabla de posiciones
-- y los completa con los resultados que ya están cargados.
--
-- Solo hace falta en bases creadas antes de este cambio (las nuevas ya
-- traen las columnas desde tornea.sql). Se corre una sola vez, antes de
-- levantar el código nuevo (ver README, sección de Docker):
--   docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" tornea' < database/migraciones/001-goles-en-tabla.sql
--
-- Si da "Duplicate column name 'gf'", es que ya estaba aplicada: no hace falta nada más.
-- Si el ALTER anduvo pero el UPDATE falló, se puede correr solo el UPDATE de abajo.
-- =============================================

ALTER TABLE tabla_posiciones
  ADD COLUMN gf INT DEFAULT 0 AFTER puntos,
  ADD COLUMN gc INT DEFAULT 0 AFTER gf;

-- Suma los goles de cada participante con las mismas reglas que la app:
-- solo partidos con resultado, sin pases libres y contra rivales aprobados
UPDATE tabla_posiciones tp
JOIN (
  SELECT p.id,
         SUM(IF(en.participante_local_id = p.id, res.score_local, res.score_visitante)) AS gf,
         SUM(IF(en.participante_local_id = p.id, res.score_visitante, res.score_local)) AS gc
  FROM participantes p
  JOIN enfrentamientos en ON p.id IN (en.participante_local_id, en.participante_visitante_id)
  JOIN participantes rival
    ON rival.id = IF(en.participante_local_id = p.id, en.participante_visitante_id, en.participante_local_id)
   AND rival.estado = 'aprobado'
  JOIN resultados res ON res.enfrentamiento_id = en.id
  GROUP BY p.id
) goles ON goles.id = tp.participante_id
SET tp.gf = goles.gf, tp.gc = goles.gc;
