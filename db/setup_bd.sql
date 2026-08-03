-- Rol y base de PostgreSQL 17 para el Moodle de CONAF.
-- Ejecutar en vm1 (172.31.2.40):  sudo -u postgres psql -f setup_bd.sql
--
-- ANTES DE EJECUTAR:
--   1. Generar la contraseña real:  openssl rand -hex 32
--   2. Reemplazar el marcador de abajo.
--   3. Copiar la misma clave a DATABASE_PASSWORD en
--      /opt/apps/coipo_moodle/.env del servidor 172.31.2.41, con chmod 600.
--   4. Borrar este archivo con `shred -u` una vez ejecutado.
--
-- Este archivo, tal como está en el repositorio, NO contiene secretos. Nunca se
-- commitea con la clave real dentro.

CREATE ROLE academia LOGIN
  PASSWORD 'REEMPLAZAR_CON_openssl_rand_hex_32'
  VALID UNTIL 'infinity'
  -- Apache atiende cada petición con un proceso propio y Moodle abre una
  -- conexión por petición, así que el número crece con el tráfico. 60 da
  -- holgura para ~2.800 usuarios sin acaparar el PostgreSQL compartido. Subirlo
  -- solo ante errores de "too many connections", revisando antes el
  -- max_connections global del servidor.
  CONNECTION LIMIT 60;

COMMENT ON ROLE academia IS 'Rol de aplicación Moodle — ver /opt/apps/coipo_moodle/.env en 172.31.2.41';

CREATE DATABASE academia_prod
  OWNER academia
  ENCODING 'UTF8'
  LC_COLLATE 'es_CL.UTF-8'
  LC_CTYPE 'es_CL.UTF-8'
  TEMPLATE template0;

COMMENT ON DATABASE academia_prod IS 'Moodle CONAF — la puebla admin/tool/dbtransfer; debe estar VACIA antes de migrar';

REVOKE CONNECT ON DATABASE academia_prod FROM PUBLIC;

\du
\l

-- DESPUÉS DE EJECUTAR: verificar que pg_hba.conf autoriza al servidor de apps
-- (172.31.2.41) a conectarse con este rol. Si no, la conexión falla con
-- "no pg_hba.conf entry for host".
--
-- NOTA: el nombre del rol/base (academia / academia_prod) NO coincide con el
-- nombre del repositorio (coipo_moodle). Es normal y esperado: la carpeta del
-- servidor siempre es el nombre del repo, el rol de BD viene de otra
-- asignación. Anotado para que nadie los confunda.
