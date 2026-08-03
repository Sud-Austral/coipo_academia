# Migración del Moodle de CONAF a PostgreSQL 17

Procedimiento completo desde el volcado original hasta el sitio funcionando en el servidor
CONAF. Requiere que `docs/TRASLADO.md` (fase F0) ya esté terminado y verificado.

## Por qué hay un paso intermedio con MariaDB

`bitnami_moodle.sql` es un volcado de **MariaDB** (verificado en su cabecera:
`MariaDB dump 10.19 Distrib 10.11.15`). Un volcado de MariaDB **no se puede cargar en
PostgreSQL**: la sintaxis, los tipos de datos y el manejo de secuencias son incompatibles.

Convertirlo con `pgloader` tampoco sirve para Moodle, y el motivo concreto importa: pgloader
copia los `id` tal cual pero **deja las secuencias de PostgreSQL en 1**, así que el primer
`INSERT` que haga Moodle revienta con `duplicate key value violates unique constraint`.
Además tiene que adivinar los tipos (`tinyint(1)`, `longtext`, `bigint(10) unsigned` — que en
PostgreSQL no existe) y los nombres de índice. El resultado no es un fallo limpio: es una
base que parece correcta y se comporta de forma errática.

La herramienta de Moodle `admin/tool/dbtransfer` construye el esquema destino desde el
`install.xml` del propio código y **reinicia las secuencias** al terminar cada tabla. Por eso:

```
volcado MariaDB → MariaDB temporal → upgrade a 4.5.10 → dbtransfer → PostgreSQL 17
                  (desechable)                            (oficial)    (estado final)
```

**El orden no es negociable.** `dbtransfer` valida el esquema contra el código en disco antes
de empezar; con una base 4.4.2 bajo código 4.5.10 faltan tablas (`stored_progress`, `ai_*`,
`sms_*`) y aborta de inmediato. Primero el upgrade, después la migración.

---

## Plugins no-core: resueltos

El sitio original tenía plugins que no venían en el código descargado. Faltaban tres, y dos de
ellos **tienen tablas con datos**: `dbtransfer` recorre únicamente las tablas declaradas en el
`install.xml` del código en disco, así que sin el plugin instalado sus datos **no se copian a
PostgreSQL y se pierden sin ningún mensaje de error**.

Ya están los cuatro vendorizados en `plugins/`, y el `Dockerfile` los instala:

| Componente | En la base | Vendorizado | Origen |
|---|---|---|---|
| `mod_customcert` | 2024042217 | 2024042217 (19 elementos) | el árbol de código original |
| `tool_mergeusers` | 2025020504 | 2026052700 · `supported [405,502]` | `github.com/ndunand/moodle-tool_mergeusers` (rama `MOODLE_405_STABLE`) |
| `block_configurable_reports` | 2024051300 | 2027050401 · `supported [400,500]` | `github.com/jleyva/moodle-block_configurablereports` |
| `theme_boost_magnific` | 2024073000 | 2026062801 · requiere 4.4 | `github.com/EduardoKrausME/moodle-theme_boost_magnific` |

Regla que se respetó: la versión vendorizada debe ser **igual o superior** a la registrada en
la base. Si fuera menor, Moodle interpreta que se está degradando el plugin y se niega a
arrancar.

Los temas `moove`, `academi`, `almondb` y `degrade` también están registrados en la base, pero
no están activos y no tienen datos propios: se ignoran. Moodle los listará como "falta en el
disco", lo cual es inofensivo.

---

## Resumen de fases

| Fase | Qué hace | Duración estimada |
|---|---|---|
| F1 | Construir la imagen de Moodle 4.5.10 | 10–20 min |
| F2 | MariaDB temporal + cargar el volcado de 295 MB | 10–30 min |
| F3 | Actualizar la base de Moodle 4.4.2 a 4.5.10 | 5–20 min |
| F4 | Migrar a PostgreSQL 17 con `dbtransfer` | 15–40 min |
| F5 | Sitio final sobre PostgreSQL + cron + Nginx | 30 min |

---

## Preparación

El `.env.migracion` va **en `/opt/migracion/coipo_moodle/`, no en el directorio de deploy**.
Verificado a la fuerza: el `rsync` del despliegue excluye `.env` pero **borra cualquier otro
archivo que no esté en el repositorio**, así que un `.env.migracion` dentro de
`/opt/apps/coipo_moodle/` desaparece en el siguiente deploy —y con él la clave del MariaDB
temporal— en mitad de la migración.

```bash
ssh usuario@172.31.2.41
cd /opt/apps/coipo_moodle

PW=$(openssl rand -hex 32)
sed -e "s|^MARIADB_ROOT_PASSWORD=.*|MARIADB_ROOT_PASSWORD=$PW|" \
    -e "s|^DATABASE_PASSWORD=.*|DATABASE_PASSWORD=$PW|" \
    .env.migracion.example > /opt/migracion/coipo_moodle/.env.migracion
chmod 600 /opt/migracion/coipo_moodle/.env.migracion
```

Si el archivo se perdiera con el contenedor de MariaDB aún en pie, la clave se recupera de su
propio entorno sin tener que recargar el volcado:

```bash
docker exec coipo_moodle-mariadb-tmp-1 printenv MARIADB_ROOT_PASSWORD
```

Para acortar los comandos del resto del documento se usa una **variable**, no un alias: los
alias no existen en una shell nueva, así que se pierden al entrar a `screen` o `tmux` y el
comando falla con `command not found` justo cuando creías haberlo lanzado.

```bash
DCM="docker compose -f docker-compose.yml -f docker-compose.migracion.yml --env-file /opt/migracion/coipo_moodle/.env.migracion"
```

> **Qué hace y qué no hace `--env-file`.** Solo alimenta la interpolación de `${...}` dentro
> de los archivos compose. **No reemplaza la directiva `env_file:` de un servicio.** El
> compose base define `env_file: .env` (PostgreSQL), así que sin más nada el contenedor
> seguiría hablando con PostgreSQL durante toda la conversión. Por eso
> `docker-compose.migracion.yml` fija las variables de MariaDB en `environment:`, que **sí**
> tiene prioridad sobre `env_file:`. El síntoma cuando esto falta es engañoso: `/health`
> responde `db: ok` (la conexión a PostgreSQL funciona) pero Moodle falla con *"Config table
> does not contain the version"*, porque esa base está vacía.

---

## F1 — Construir la imagen

```bash
$DCM build
```

El build descarga Moodle 4.5.10 desde GitHub (el tag `v4.5.10` está verificado) y el binario
de supercronic. `mod_customcert` **no** se descarga: va vendorizado en el repositorio. **Si el
servidor no tiene salida a internet**, el build falla en el `git clone`: ver "Sin salida a
internet" al final.

### Verificar (no basta con que el build termine)

```bash
$DCM run --rm app php -v                       # PHP 8.3.x
$DCM run --rm app php -m | grep -E '^(pgsql|mysqli|gd|intl|zip|soap|exif)$'
$DCM run --rm app head -3 /var/www/html/version.php

# Los cuatro plugins no-core deben estar en su sitio
$DCM run --rm app ls /var/www/html/mod/customcert/element | wc -l    # 19
$DCM run --rm app ls -d /var/www/html/admin/tool/mergeusers \
                       /var/www/html/blocks/configurable_reports \
                       /var/www/html/theme/boost_magnific
```

Deben aparecer **`pgsql` y `mysqli` a la vez**: la primera para el estado final, la segunda
para leer MariaDB. Si falta alguna, el resto del procedimiento no funciona.

---

## F2 — MariaDB temporal y carga del volcado

```bash
$DCM up -d mariadb-tmp
$DCM ps                              # esperar hasta que aparezca "healthy"
```

Confirmar que la base se creó en utf8mb4. Importa: la base original era **utf8mb3** (lo dice
el `db.opt` de la copia del datadir) aunque sus tablas sean utf8mb4. Si se hereda ese valor,
las tablas nuevas que cree el upgrade 4.4→4.5 nacen con la codificación equivocada:

```bash
$DCM exec mariadb-tmp mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e \
  "SELECT default_character_set_name, default_collation_name FROM information_schema.SCHEMATA
   WHERE schema_name='bitnami_moodle';"
# Debe decir utf8mb4 / utf8mb4_unicode_ci. Si no:
#   ALTER DATABASE bitnami_moodle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Cargar los 295 MB. El archivo ya está montado en `/dump`, así que la carga ocurre entera del
lado del servidor, sin pasar por la tubería del cliente Docker:

```bash
time $DCM exec -T mariadb-tmp sh -c \
  'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" --default-character-set=utf8mb4 bitnami_moodle < /dump/bitnami_moodle.sql'
```

No muestra progreso. Para seguirlo desde otra terminal:

```bash
$DCM exec mariadb-tmp mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='bitnami_moodle';"
```

### Verificar la carga

```bash
$DCM exec mariadb-tmp mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" bitnami_moodle -e "
  SELECT (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='bitnami_moodle') AS tablas,
         (SELECT COUNT(*) FROM mdl_user WHERE deleted=0) AS usuarios,
         (SELECT COUNT(*) FROM mdl_course) AS cursos,
         (SELECT value FROM mdl_config WHERE name='release') AS version;"
```

Valores **verificados** en la carga real del 29 de julio de 2026:

| Campo | Esperado |
|---|---|
| `tablas` | **492** |
| `usuarios` (`deleted=0`) | **2.798** |
| `cursos` (filas de `mdl_course`) | **36** — 35 cursos más el curso "sitio", que no es un curso real |
| `matriculas` | **3.474** |
| `archivos` (filas de `mdl_files`) | **178.833** |
| `version` | **4.4.2 (Build: 20240812)** |

Si `tablas` es menor que 492, la carga se cortó: vaciar la base y recargar.

> **Cuidado con el `AUTO_INCREMENT` como estimación.** Inspeccionando el volcado, esos
> contadores sugieren 465.494 filas en `mdl_files` y 11 millones en `mdl_task_log`. Son
> falsos: el contador registra cuántas filas se crearon **alguna vez**, no cuántas quedan.
> `mdl_task_log` llegó con **0 filas** porque Moodle purga ese historial a los 30 días.

### Ruido

`mdl_task_log` es historial de ejecuciones de tareas programadas y no aporta nada a la
migración. En este volcado ya venía vacío, pero si en un volcado futuro tuviera millones de
filas conviene truncarla antes de F4, que copia fila por fila a través de PHP:

```bash
docker exec coipo_moodle-mariadb-tmp-1 sh -c \
  'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" bitnami_moodle -e "TRUNCATE TABLE mdl_task_log;"'
```

**No hagas lo mismo con `mdl_logstore_standard_log`**: eso es traza de auditoría —quién vio
qué, quién calificó qué— y en un servicio público se conserva.

---

## F3 — Actualizar la base de 4.4.2 a 4.5.10

### Respaldo primero — el upgrade no se deshace

```bash
$DCM exec -T mariadb-tmp sh -c \
  'exec mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines --no-tablespaces bitnami_moodle' \
  | gzip > /opt/migracion/coipo_moodle/respaldo-antes-upgrade.sql.gz
ls -lh /opt/migracion/coipo_moodle/respaldo-antes-upgrade.sql.gz
```

### Apuntar el contenedor a MariaDB — y comprobarlo antes de tocar la base

```bash
$DCM up -d --force-recreate app
sleep 15

# Verificación imprescindible: el contenedor debe traer las variables de MariaDB.
$DCM exec app env | grep -E '^(MOODLE_DBTYPE|DATABASE_HOST|DATABASE_NAME|DATABASE_USER)='
# Esperado: mariadb / mariadb-tmp / bitnami_moodle / root
# Si dice pgsql y 172.31.2.40, NO CONTINUAR: ver la nota sobre --env-file arriba.

# Confirmación definitiva: esta versión se lee de la base, no de un archivo.
$DCM exec -u www-data app php admin/cli/cfg.php --name=release
# Esperado: 4.4.2 (Build: 20240812)
```

El `--force-recreate` es necesario: si el contenedor ya existe, Compose lo deja como está
(dice `Running` en vez de `Recreated`) y seguirías con la configuración anterior.

### Actualizar

```bash

# Todo con -u www-data. Si se corre como root, los directorios de caché que
# Moodle crea en moodledata quedan con dueño root y el sitio deja de poder
# escribir justo después de un upgrade exitoso. Es el error más común.
$DCM exec -u www-data app php admin/cli/maintenance.php --enable
$DCM exec -u www-data app php admin/cli/checks.php
$DCM exec -u www-data app php -d memory_limit=-1 -d max_execution_time=0 \
    admin/cli/upgrade.php --non-interactive
$DCM exec -u www-data app php admin/cli/purge_caches.php
```

Los 7 plugins ausentes del disco **no bloquean el upgrade**: Moodle los lista como "falta en
el disco" y sigue.

### Verificar — mirando el sitio, no los logs

```bash
$DCM exec -u www-data app php admin/cli/cfg.php --name=release        # 4.5.10 (Build: ...)
$DCM exec -u www-data app php admin/cli/check_database_schema.php     # sin diferencias
$DCM exec -u www-data app php admin/cli/maintenance.php --disable
curl -sI http://localhost:8115/login/index.php | head -1             # HTTP/1.1 200 OK
```

`check_database_schema.php` debe salir **limpio**: es exactamente la misma validación que
ejecuta `dbtransfer` antes de migrar. Si tiene diferencias, F4 no va a arrancar.

### Entrar como administrador

El administrador principal del sitio es el usuario **id = 2** (según `siteadmins` en la base).

```bash
$DCM exec mariadb-tmp mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" bitnami_moodle \
  -e "SELECT id, username, auth, email FROM mdl_user WHERE id=2;"

$DCM exec -u www-data app php admin/cli/reset_password.php
```

**Trampa conocida**: `reset_password.php` solo encuentra usuarios con `auth='manual'`. Si
responde `Can not find user`, esa cuenta usa otro método de autenticación. Anota el valor
original y cámbialo temporalmente:

```sql
UPDATE mdl_user SET auth='manual' WHERE id=2;
```

### Comprobaciones obligatorias antes de seguir

Abrir `http://172.31.2.41:8115` en el navegador y confirmar:

1. Entrar como administrador y llegar al Área personal.
2. **Administración del sitio → Cursos** → se ven los ~40 cursos con sus nombres reales.
3. **Usuarios → Examinar lista** → ~2.800 usuarios.
4. Abrir un curso y **abrir un PDF o un SCORM** → confirma que el bind mount de `moodledata`
   se lee.
5. Subir un archivo desde la web y confirmarlo **en el host**:
   ```bash
   find /opt/moodledata/coipo_moodle/filedir -newermt '-5 minutes' -type f | head
   ```
   Si aparece, Moodle está escribiendo fuera del contenedor: era el requisito del proyecto.

**Lo que es normal y no es un fallo**: el sitio se verá con el tema **Boost estándar**, no con
el diseño original, porque `theme_boost_magnific` no está en el código. Y las imágenes
incrustadas apuntan a `https://campus.conaf.cl`: si hay acceso a esa red se cargarán desde el
sitio en producción, y si no, saldrán rotas. Ambas cosas son esperadas.

**Si algo falla, se arregla aquí.** Arrastrar un problema a PostgreSQL solo mezcla dos causas
distintas.

---

## F4 — Migrar a PostgreSQL 17

### 1. Crear el rol y la base (en 172.31.2.40)

```bash
scp db/setup_bd.sql usuario@172.31.2.40:/tmp/
ssh usuario@172.31.2.40
openssl rand -hex 32                     # la clave real
nano /tmp/setup_bd.sql                   # reemplazar el marcador
sudo -u postgres psql -f /tmp/setup_bd.sql
shred -u /tmp/setup_bd.sql
```

La base `academia_prod` debe quedar **vacía**: `dbtransfer` aborta si encuentra tablas con el
prefijo `mdl_`.

### 2. Pre-vuelo y transferencia

```bash
$DCM exec -u www-data app php admin/cli/check_database_schema.php    # DEBE salir limpio
$DCM exec -u www-data app php admin/cli/maintenance.php --enable

PGPW=$(grep '^DATABASE_PASSWORD=' /opt/apps/coipo_moodle/.env | cut -d= -f2-)

$DCM exec -u www-data app php -d memory_limit=-1 -d max_execution_time=0 \
  admin/tool/dbtransfer/cli/migrate.php \
    --dbtype=pgsql --dblibrary=native \
    --dbhost=172.31.2.40 --dbport=5432 \
    --dbname=academia_prod --dbuser=academia --dbpass="$PGPW" \
    --prefix=mdl_ --dbsocket=
```

El `--dbsocket=` vacío al final **no es opcional**: `migrate.php` pide de forma interactiva
cualquier parámetro que no reciba, y se queda esperando en `== Socket Unix ==`. Vacío es el
valor correcto —conexión TCP a otra máquina, no por socket local—, pero hay que pasarlo
explícitamente o el comando se cuelga a mitad del procedimiento.

Copia fila por fila a través de PHP. Con `mdl_task_log` ya truncado son ~15–40 minutos; sin
truncar, horas. **No se puede reanudar**: si falla a mitad hay que vaciar la base destino
(`DROP SCHEMA public CASCADE; CREATE SCHEMA public AUTHORIZATION academia;`) y repetir entero.

### 3. Verificar la transferencia

```bash
psql -h 172.31.2.40 -U academia -d academia_prod -c \
  "SELECT COUNT(*) AS tablas FROM information_schema.tables WHERE table_schema='public';"
psql -h 172.31.2.40 -U academia -d academia_prod -c \
  "SELECT (SELECT COUNT(*) FROM mdl_user WHERE deleted=0) AS usuarios,
          (SELECT COUNT(*) FROM mdl_course) AS cursos;"
```

Los números deben coincidir con los de F2. Si no, vacía la base y repite.

---

## F5 — Sitio final sobre PostgreSQL

```bash
cp .env.example .env
chmod 600 .env
nano .env            # DATABASE_PASSWORD real; MOODLE_WWWROOT = la URL definitiva

$DCM down                                   # apagar la fase de conversión
docker compose --env-file .env up -d --build
docker compose --env-file .env exec -u www-data app php admin/cli/purge_caches.php
```

**Conserva el volumen de MariaDB** (`mariadb_tmp`) hasta haber validado el sitio completo
sobre PostgreSQL: es el rollback de un comando.

### Verificación final

```bash
docker compose --env-file .env ps                    # app healthy, cron up
docker compose --env-file .env exec -u www-data app php admin/cli/cfg.php --name=dbtype
# → pgsql
curl -sI http://localhost:8115/login/index.php | head -1
docker compose --env-file .env logs cron | tail -20  # una ejecución por minuto
docker compose --env-file .env exec -u www-data app php admin/cli/checks.php
docker compose --env-file .env exec -u www-data app php admin/cli/check_database_schema.php
```

Y de nuevo, en el navegador: entrar, abrir un curso, abrir un archivo, subir un archivo.

### Antes de encender el cron: correo saliente

`MOODLE_NOEMAILEVER=true` viene activado por defecto y **debe seguir así**. Son ~2.800
correos institucionales reales: en cuanto el cron arranque sin ese freno, Moodle envía
resúmenes de foro, notificaciones de tareas y avisos de contraseña a funcionarios de verdad
desde un sitio que todavía es de pruebas.

Revisar también **Administración del sitio → Extensiones → Inscripciones** antes del primer
cron: en la base están instalados `enrol_database` y `enrol_ldap`. Si alguno quedó activo,
el cron intentará sincronizar contra los directorios reales de CONAF.

### Nginx del servidor

El vhost completo y comentado está en [`nginx-academia.conf`](nginx-academia.conf). Vive en el
servidor, no lo despliega este repositorio:

```bash
# Convención de la casa: <dominio>.conf (como iam.conaf.cl.conf)
cp docs/nginx-academia.conf /etc/nginx/sites-available/academia.conaf.cl.conf
ln -sf /etc/nginx/sites-available/academia.conaf.cl.conf /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

No lleva `default_server`: ya existe `00-default.conf` y solo puede haber uno por puerto —
Nginx se negaría a arrancar.

Verificar sin depender del DNS:

```bash
curl -s -H "Host: academia.conaf.cl" http://127.0.0.1/health; echo
```

#### El puerto 8115 no es accesible desde la red, y está bien así

Verificado el 29-07-2026 desde una estación de trabajo: `172.31.2.41:8115` acepta la conexión
TCP y luego la reinicia (`Connection was reset`). Hay un firewall de red que solo deja pasar
los puertos estándar.

Es el comportamiento buscado: `APP_PORT` es el puerto **interno** por el que Nginx habla con
el contenedor, y la única puerta de entrada es Nginx en el 80. **Nunca poner `APP_PORT=80`**:
el contenedor chocaría con Nginx y tumbaría también las otras apps del servidor.

Consecuencia práctica: `MOODLE_WWWROOT` **no debe llevar puerto**.

#### `MOODLE_REVERSEPROXY` debe quedar en `false`, aunque haya Nginx delante

Parece contradictorio, pero está en el código. `lib/setuplib.php:745`:

```php
if (!empty($CFG->reverseproxy) && $rurl['host'] === $wwwroot['host'] && (empty($wwwroot['port']) || $rurl['port'] === $wwwroot['port'])) {
    throw new \moodle_exception('reverseproxyabused', 'error');
}
```

El modo `reverseproxy` de Moodle asume que el proxy **deja el Host apuntando al nombre interno
del servidor**, así que espera que el Host recibido sea *distinto* al de `wwwroot`. Nuestro
Nginx hace `proxy_set_header Host $host` —lo estándar, y lo que Moodle necesita para construir
bien sus URLs—, o sea reenvía `academia.conaf.cl`, idéntico a `wwwroot`. Con `true`, el sitio
no carga y muestra *"Proxy inverso habilitado para que no se pueda acceder directamente al
servidor"*.

No se pierde nada poniéndolo en `false`: Moodle solo comprueba que host, puerto y ruta de la
petición coincidan con `wwwroot`, y coinciden. La IP real del visitante en los registros la
resuelve `mod_remoteip` de Apache (`docker/apache-moodle.conf`), no esta variable.

`MOODLE_SSLPROXY` es independiente y **sí** hay que activarlo cuando el certificado
`*.conaf.cl` esté instalado y Nginx sirva HTTPS —junto con cambiar `wwwroot` a `https://`,
porque `setuplib.php:727` exige que coincidan—. Antes no: las cookies seguras no viajan por
HTTP y el login deja de funcionar sin decir por qué.

Para navegar la URL definitiva antes de que el DNS resuelva, sin tocar el archivo `hosts`:

```powershell
& "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" `
  --user-data-dir="$env:TEMP\edge-test" `
  --host-resolver-rules="MAP academia.conaf.cl 172.31.2.41" http://academia.conaf.cl
```

### URLs antiguas dentro del contenido — paso obligatorio

El sitio nuevo es **`academia.conaf.cl`** y el anterior (`campus.conaf.cl`) queda
descontinuado. Pero el contenido de los cursos trae **20.994 enlaces absolutos** al dominio
viejo, incrustados en páginas, etiquetas y mensajes de foro. Si no se reescriben, esos
enlaces e imágenes apuntarán a un sitio que dejará de existir.

```bash
# Respaldo ANTES: esta operación es destructiva e irreversible.
pg_dump -h 172.31.2.40 -U academia academia_prod | gzip > /opt/migracion/coipo_moodle/antes-replace.sql.gz

docker compose --env-file .env exec -u www-data app php admin/cli/maintenance.php --enable
docker compose --env-file .env exec -u www-data app php admin/tool/replace/cli/replace.php \
  --search='campus.conaf.cl' --replace='academia.conaf.cl'
docker compose --env-file .env exec -u www-data app php admin/cli/purge_caches.php
docker compose --env-file .env exec -u www-data app php admin/cli/maintenance.php --disable
```

Se busca `campus.conaf.cl` sin el esquema a propósito: así cubre tanto `http://` como
`https://`. Hazlo **solo después** del upgrade y con el sitio ya funcionando sobre
PostgreSQL — no antes.

Verificar que no quedó ninguno:

```bash
psql -h 172.31.2.40 -U academia -d academia_prod -c \
  "SELECT COUNT(*) FROM mdl_page WHERE content LIKE '%campus.conaf.cl%';"
```

---

## Limpieza (solo cuando el sitio lleve días funcionando)

```bash
docker compose -f docker-compose.yml -f docker-compose.migracion.yml --env-file /opt/migracion/coipo_moodle/.env.migracion down -v
rm -f .env.migracion
shred -u /opt/migracion/coipo_moodle/bitnami_moodle.sql
shred -u /opt/migracion/coipo_moodle/respaldo-antes-upgrade.sql.gz
```

No borres el volcado antes de tiempo: es el único punto de retorno si algo aparece semanas
después. Y cuando lo borres, hazlo con `shred`: contiene datos personales de ~2.800
funcionarios.

---

## Problemas conocidos

| Síntoma | Causa y solución |
|---|---|
| `MySQL server has gone away` durante la carga | `max_allowed_packet` insuficiente. Ya está en 512M; si persiste, subirlo y recargar sobre base vacía |
| `$CFG->dataroot is not writable` | La carpeta del host no pertenece al UID 33: `sudo chown -R 33:33 /opt/moodledata/coipo_moodle` |
| Andaba y se cayó tras correr un comando | Se ejecutó un CLI sin `-u www-data` y dejó archivos con dueño root en `moodledata`. `sudo chown -R 33:33 ...` |
| Login que no persiste, CSS que no carga, bucle de redirección | `MOODLE_WWWROOT` no coincide con la URL del navegador (incluido el puerto) |
| `no pg_hba.conf entry for host` | Falta autorizar 172.31.2.41 en el `pg_hba.conf` de 172.31.2.40 |
| El login falla con HTTPS | `MOODLE_SSLPROXY` mal puesto: `true` solo cuando Nginx sirva TLS |
| `Can not find user` al resetear la contraseña | El usuario no tiene `auth='manual'`. Ver F3 |
| `dbtransfer` aborta al empezar | `check_database_schema.php` no está limpio, o la base destino no está vacía |
| Todas las páginas responden "The site is undergoing maintenance", y el contenedor queda `unhealthy` | Quedó un `climaintenance.html` de un `maintenance.php --enable` que falló después de escribirlo (por ejemplo, porque el contenedor apuntaba a una base vacía) y cuyo `--disable` tampoco pudo correr. El modo mantención por CLI es **solo ese archivo**, sin estado en la base: `rm -f /opt/moodledata/coipo_moodle/climaintenance.html`. El campo `mantencion` de `/health` lo delata |
| `Table ... is read only` en MariaDB | Añadir `--innodb_read_only_compressed=OFF` al `command:` del compose |

### Sin salida a internet en el servidor

Si el `git clone` del Dockerfile falla, hay dos caminos:

1. **Construir la imagen donde sí haya internet** y transferirla:
   `docker save coipo_moodle-app | gzip > moodle.tar.gz`, copiarla y `docker load < moodle.tar.gz`.
2. **Copiar el árbol local** `Moodle/Moodle/moodle` al contexto de build y reemplazar el
   `git clone` por un `COPY`. Ese árbol está en `.gitignore` y no viaja con el despliegue
   automático, así que habría que subirlo aparte.
