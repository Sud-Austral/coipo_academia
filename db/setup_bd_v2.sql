-- Base de la instancia v2 de la Academia — PostgreSQL 17, servidor 172.31.2.40.
-- Ejecutar en vm1 (172.31.2.40):  sudo -u postgres psql -f setup_bd_v2.sql
--
-- NO crea rol. Reutiliza el rol `academia` que ya existe (lo creó db/setup_bd.sql
-- para academia_prod). Es deliberado: una tercera contraseña sería una tercera
-- contraseña que rotar, y la lista de pendientes de CLAUDE.md ya trae dos.
--
-- Consecuencia que hay que tener presente, y que muerde en caliente:
--
--   el CONNECTION LIMIT es del ROL, no de la base. Está en 20 (verificado con
--   SELECT rolconnlimit FROM pg_roles) y ahora lo comparten DOS instancias:
--   coipo_moodle (prod, puerto 8115) y coipo_academia (v2, puerto 8116).
--
--   Por eso el .env.v2.example deja MaxRequestWorkers en 6, no en 16. Con dos
--   instancias a 16 cada una, la conexión 21 recibe "FATAL: too many
--   connections" y el usuario ve un error 500 sin ninguna pista de por qué.
--
--   Cuando el administrador de 172.31.2.40 ejecute la línea de abajo, se puede
--   subir prod a 16 y v2 a 16, y todavía queda margen para el cron de ambas:
--
--       ALTER ROLE academia CONNECTION LIMIT 60;
--
--   El rol NO puede hacerlo solo: no tiene CREATEROLE y responde
--   "permission denied to alter role".

CREATE DATABASE academia_v2
  OWNER academia
  ENCODING 'UTF8'
  LC_COLLATE 'es_CL.UTF-8'
  LC_CTYPE 'es_CL.UTF-8'
  TEMPLATE template0;

COMMENT ON DATABASE academia_v2 IS
  'Academia CONAF v2 — clon de academia_prod para construir el modelo de datos nuevo. NO es producción.';

REVOKE CONNECT ON DATABASE academia_v2 FROM PUBLIC;

\l

-- ─── Cómo se puebla ─────────────────────────────────────────────────────────
--
-- Desde el servidor de aplicaciones (172.31.2.41), con la clave del rol en
-- PGPASSWORD para que no quede en el historial ni en `ps`:
--
--   export PGPASSWORD='...'          # la misma de DATABASE_PASSWORD del .env
--   pg_dump  -h 172.31.2.40 -U academia -d academia_prod --no-owner --no-acl \
--     | psql -h 172.31.2.40 -U academia -d academia_v2
--   unset PGPASSWORD
--
-- Y verificar ANTES de seguir — contar, no suponer. Las cifras tienen que
-- coincidir con las de academia_prod al 30-07-2026.
--
-- Esto se hace ANTES de levantar el contenedor: el clon sale con el esquema de
-- Moodle 4.5.10, y es la primera arrancada de la imagen 5.2.1 la que dispara el
-- upgrade 4.5 -> 5.0 -> 5.1 -> 5.2. Contar después del upgrade da otros números
-- (la 5.x agrega tablas), así que la foto de referencia se toma acá:
--
--   psql -h 172.31.2.40 -U academia -d academia_v2 -c \
--     "SELECT (SELECT count(*) FROM information_schema.tables
--                WHERE table_schema='public')          AS tablas,      -- 503
--             (SELECT count(*) FROM mdl_user)          AS usuarios,    -- 2873
--             (SELECT count(*) FROM mdl_course)        AS cursos,      -- 37
--             (SELECT count(*) FROM mdl_files)         AS archivos;"   -- 179147
--
-- Si `tablas` sale bien pero las secuencias quedaron en 1, el primer INSERT de
-- Moodle revienta. pg_dump las trae con su valor real; un pgloader o una
-- conversión a mano, no. Comprobación rápida:
--
--   SELECT count(*) FROM pg_sequences WHERE last_value IS NULL;   -- debe ser 0
--
-- ─── Rollback ───────────────────────────────────────────────────────────────
--
-- Esta base es desechable por diseño. Si el modelo de datos queda mal, se borra
-- entera y se vuelve a clonar; academia_prod no se toca en ningún momento:
--
--   DROP DATABASE academia_v2;
