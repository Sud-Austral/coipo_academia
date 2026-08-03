# CLAUDE.md — coipo_moodle

Migración de la plataforma Moodle de CONAF, hoy alojada por la empresa externa **Lazzos**, a
infraestructura propia. Lo pidió la **Dirección Ejecutiva** (nivel nacional). Encargado
técnico: Luis Monsalve.

No es una aplicación nueva: es un Moodle en producción que hay que mover.

---

## Dominios: de dónde viene y a dónde va

| Dominio | Rol |
|---|---|
| `campus.conaf.cl` | **Origen.** Es el sitio del que salió el volcado (20.994 referencias dentro de la base). Queda descontinuado |
| `academia.conaf.cl` | **Destino definitivo.** Es a donde hay que migrar |
| `cursos.conaf.cl` | Aparece **cero** veces en el volcado. Fue el nombre con que se planteó el encargo, pero no es ninguno de los dos |

Consecuencia operativa: el contenido de los cursos trae 20.994 enlaces absolutos a
`campus.conaf.cl` incrustados. **Reescribirlos a `academia.conaf.cl` es un paso obligatorio**
de la migración, no opcional (ver `docs/MIGRACION.md`). Por eso el rol de base de datos se
llama `academia` / `academia_prod`.

---

## Inventario verificado — esta es la fuente de verdad

Todo lo de abajo fue comprobado leyendo los archivos, no supuesto. **El supuesto inicial de
"1 curso y 1 usuario" era falso.**

**Volcado vigente: `moodle_db.sql`.** El 30 de julio de 2026 se rehízo la migración completa
porque el volcado original **no era el archivo correcto**. Lo de abajo refleja el estado actual.

| Qué | Valor real |
|---|---|
| Volcado **vigente** | `moodle_db.sql` — **338 MB**, dump de **phpMyAdmin 5.2.1** sobre **MariaDB 10.4.32 + PHP 8.2.12** en `127.0.0.1` (un XAMPP local), base origen `moodle_db`, **501 tablas**, InnoDB utf8mb4. **No trae `CREATE DATABASE` ni `USE`** |
| Volcado **anterior**, descartado | `bitnami_moodle.sql` — 295 MB, mysqldump de MariaDB 10.11.15, 492 tablas, Moodle 4.4.2. Se conserva solo como rollback |
| Versión de la base | Moodle **4.5.10** (Build 20260216, version 2024100710) — el volcado nuevo **ya viene actualizado**, no hay upgrade de core que hacer |
| Código fuente disponible | Moodle **4.5.10** (Build 20260216), branch 405 |
| moodledata | **12 GB** en `/opt/moodledata/coipo_moodle` · `filedir` 11 GB. **Compatible con el volcado nuevo**: se verificaron 3.076 `contenthash` distintos y los 3.076 existen |
| Escala (conteos reales tras la migración del 30-07-2026) | **2.869 usuarios** activos (2.873 filas) · **37 filas** en `mdl_course` (36 cursos + el curso "sitio") · **3.731 matrículas** · **179.147 filas** en `mdl_files` · **503 tablas** · 1.843.658 filas en total |
| `mdl_task_log` | Se **trunca antes de F4** (traía 11.099 filas). `dbtransfer` copia fila por fila a través de PHP y esa tabla no aporta nada a la migración |
| Tema activo | `boost_magnific` (recuperado y vendorizado) |
| Requisitos de Moodle 4.5 | PHP ≥ 8.1 y **bloquea 8.4** · PostgreSQL ≥ 13 · MariaDB ≥ 10.6.7 |

### Particularidades del volcado de phpMyAdmin

No se comporta como un `mysqldump` y eso cambia el procedimiento:

- Las **claves primarias, los índices y los `AUTO_INCREMENT` van en `ALTER TABLE` al final** del
  archivo, no dentro del `CREATE TABLE`. Si el archivo llegara truncado, las tablas cargarían
  con todos sus datos y **sin una sola clave** — parecería correcto hasta fallar después. Hay
  que verificarlo explícitamente: `information_schema.statistics` con `index_name='PRIMARY'`.
- Las 501 tablas traen `ROW_FORMAT=COMPRESSED`. **Hay que quitarlo al cargar**
  (`sed 's/ ROW_FORMAT=COMPRESSED//g'`), o MariaDB 10.6+ las deja de solo lectura por
  `innodb_read_only_compressed=ON` y el upgrade falla con `Table ... is read only`.
- `mdl_context_temp` no tiene `AUTO_INCREMENT`. **Es normal**, en el volcado anterior también.

**Son datos personales de ~2.800 funcionarios.** Aplica la Ley 19.628. Eso condiciona todo lo
demás.

### Plugins no-core — resueltos, los cuatro en `plugins/`

| Componente | En la base | Vendorizado | Origen |
|---|---|---|---|
| `mod_customcert` | 2024042217 | 2024042217 (19 elementos) | el árbol de código original |
| `tool_mergeusers` | 2025020504 | 2026052700 · `supported [405,502]` | `ndunand/moodle-tool_mergeusers`, rama `MOODLE_405_STABLE` |
| `block_configurable_reports` | 2024051300 | 2027050401 · `supported [400,500]` | `jleyva/moodle-block_configurablereports` |
| `theme_boost_magnific` | 2024073000 | 2026062801 · requiere 4.4 | `EduardoKrausME/moodle-theme_boost_magnific` |
| `theme_academi` | 2024060503 | 2025050300 · `v4.5.1` · requiere 4.4 | `lmsace/academi`, del directorio oficial. MD5 verificado |
| `theme_moove`, `almondb`, `degrade` | varias | No | Inactivos y sin datos propios: se ignoran |

**Corrección del 31-07-2026 sobre `theme_academi`.** Este archivo lo daba por "inactivo y sin
datos propios". Es falso: tiene **135 ajustes en `mdl_config_plugins`** con datos reales de
CONAF (`address = Paseo Bulnes 285, Santiago, Chile`, `emailid = campus.geprif@conaf.cl`,
aviso de copyright). Sus archivos nunca se habían vendorizado, así que Moodle lo listaba como
ausente del disco y esos ajustes quedaban huérfanos. Ya está vendorizado en
`plugins/theme/academi/` y copiado por el `Dockerfile`. El tema activo sigue siendo
`boost_magnific`.

**Cómo se instala un plugin en este proyecto — no por la interfaz web.** Subir el `.zip` desde
Administración del sitio deja los archivos **solo dentro del contenedor**, y el siguiente
despliegue reconstruye la imagen desde el `Dockerfile`: el plugin desaparece y vuelve a dejar
ajustes huérfanos en la base. El procedimiento correcto son cuatro pasos:

1. Descargar del directorio oficial y **verificar el MD5** que publica su API:
   `https://download.moodle.org/api/1.3/pluginfo.php?plugin=<componente>&branch=4.5`
2. Extraer en `plugins/<tipo>/<nombre>/`
3. Agregar un `COPY --chown=www-data:www-data` en el `Dockerfile`
4. Commit y push: el despliegue reconstruye y `admin/cli/upgrade.php` registra la versión nueva

`configurable_reports` y `mergeusers` **tienen tablas con datos**. Sin el plugin instalado,
`dbtransfer` no copia sus tablas a PostgreSQL y esos datos se pierden **sin ningún mensaje de
error** — por eso era imprescindible recuperarlos antes de la fase F4.

Regla al vendorizar: la versión debe ser **igual o superior** a la registrada en la base. Si
fuera menor, Moodle entiende que se está degradando el plugin y se niega a arrancar.

---

## Decisiones tomadas

1. **PostgreSQL 17 como motor final**, aunque el volcado sea MariaDB. Implica un paso
   intermedio inevitable: un MariaDB temporal que solo sirve para leer el volcado, y luego
   `admin/tool/dbtransfer` para pasar a PostgreSQL. No se usa `pgloader` ni conversión directa
   del `.sql`: deja las secuencias en 1 y el primer `INSERT` de Moodle revienta.
2. **Moodle 4.5.10**, asumiendo el upgrade 4.4.2 → 4.5.10 como paso controlado y con respaldo
   previo. El orden es obligatorio: `dbtransfer` valida el esquema contra el código en disco,
   así que **primero el upgrade, después la migración**.
3. **moodledata fuera del contenedor**, por bind mount con lectura y escritura.
4. **Corre en el servidor CONAF 172.31.2.41**, con el PostgreSQL 17 compartido de 172.31.2.40.
   Rol `academia`, base `academia_prod`, puerto `8115`.

---

## Arquitectura

```
academia.conaf.cl ─── DNS ──▶ 172.31.2.100
                              Balanceador Radware Alteon — TERMINA EL TLS
                              (se reconoce por la cookie AlteonP)
                              redirige http:// a https:// con un 307
        │ HTTP plano
        ▼
Nginx del servidor 172.31.2.41:80 — sin certificado, y así debe quedar
        │
        ▼
   app (único con puerto, 8115) ──┐      cron (sin puerto, user 33:33)
   Apache + PHP 8.3               │      supercronic → admin/cli/cron.php
   Moodle 4.5.10                  │
        │                         │
        └──── bind mount ─────────┴──► /opt/moodledata/coipo_moodle  (10,9 GB, UID 33)
        │
        ▼
   PostgreSQL 17 compartido — 172.31.2.40 (fuera de Docker)
```

**Una sola imagen para las dos fases.** `docker/config.php` lee todo de variables de entorno,
así el mismo artefacto apunta a MariaDB durante la conversión y a PostgreSQL en el estado
final. Lo que cambia es el `.env`, no la imagen.

### Rutas en el servidor

```
/opt/apps/coipo_moodle/          repositorio desplegado (código, compose, .env)
/opt/moodledata/coipo_moodle/    los 10,9 GB — FUERA del directorio de deploy
/opt/migracion/coipo_moodle/     el volcado, temporal
```

`moodledata` va fuera de `/opt/apps/` a propósito: el deploy sincroniza esa carpeta y un
borrado accidental se llevaría el contenido de los 40 cursos.

---

## Reglas de higiene — no negociables

- **Nada de datos ni secretos en el repositorio.** El volcado (295 MB) supera el límite de
  100 MB de GitHub y contiene datos personales. `.gitignore` bloquea `*.sql`, `Moodle/`,
  `moodledata/`, `.env` y comprimidos; `.dockerignore` evita que viajen al contexto de build.
- `INSUMO/setup_bd.sql` **contiene una contraseña real de producción** del rol `iam` (de otro
  proyecto). Está ignorado, pero **esa clave debe rotarse**. Se parece demasiado a la del rol
  `academia`: si una se filtra, la otra es adivinable.
- Las claves se generan con `openssl rand -hex 32`, viven solo en el `.env` del servidor con
  `chmod 600`, y los archivos que las contuvieron se borran con `shred -u`.
- Los comandos de Moodle dentro del contenedor se ejecutan **siempre con `-u www-data`**. Si
  se corren como root, los archivos que Moodle crea en `moodledata` quedan con dueño root y
  el sitio deja de poder escribir. Es el error más común y el más confuso.
- **`MOODLE_NOEMAILEVER=true` mientras esto sea un entorno de pruebas.** Son ~2.800 correos
  institucionales reales: sin ese freno, el cron envía resúmenes de foro y avisos de
  contraseña a funcionarios de verdad.
- `.gitattributes` fuerza `eol=lf`. Con la normalización automática anterior, el crontab y los
  scripts entraban a la imagen con `\r` y fallaban con "bad interpreter".

---

## Cómo verificar (regla del proyecto)

"Funciona" significa **haber visto el resultado**, no que el contenedor levantó ni que el
comando no dio error. El mínimo para este sistema:

1. `curl -sI http://172.31.2.41:8115/login/index.php` → **200**.
2. Entrar por navegador como administrador (es el usuario id = 2).
3. Abrir un curso y **abrir un PDF o SCORM** → confirma que `moodledata` se lee.
4. Subir un archivo desde la web y encontrarlo en `/opt/moodledata/coipo_moodle/filedir/`
   **en el host** → confirma que se escribe fuera del contenedor.
5. Contar: 492 tablas, ~2.800 usuarios, ~40 cursos. Si no cuadran, no está listo.
6. `php admin/cli/cfg.php --name=dbtype` → `pgsql`.

**Lo que parece un fallo y no lo es**: mientras no se reescriban los enlaces, las imágenes
incrustadas apuntan a `campus.conaf.cl`. Si hay acceso a esa red se cargarán desde el sitio
antiguo; si no, saldrán rotas. Se resuelve en el paso de reescritura de URLs.

---

## Documentos

| Documento | Contenido |
|---|---|
| `docs/TRASLADO.md` | Fase F0: mover el volcado y los 10,9 GB al servidor. Lo ejecuta Luis |
| `docs/MIGRACION.md` | Fases F1–F5: imagen, carga, upgrade, paso a PostgreSQL, sitio final |
| **`docs/TLS-HTTPS.md`** | **Paso a HTTPS (30-07-2026).** El TLS lo termina el Alteon de 172.31.2.100, no nuestro Nginx. Por qué `MOODLE_WWWROOT` y `MOODLE_SSLPROXY` van siempre juntas, cómo se verifica y cómo se revierte |
| `docs/nginx-academia.conf` | Copia de referencia del vhost real. Escucha solo en el puerto 80 **a propósito** |
| `INSUMO/` | Referencia de la infraestructura CONAF: patrón Docker, deploy, PostgreSQL |

`docs/entrevista-avance.md` guarda el levantamiento de requisitos, pausado por el cambio de
prioridades.

---

## Estado y pendientes

### Hecho y verificado en el servidor (29 de julio de 2026)

Las fases F0 a F5 están ejecutadas. Lo verificado **mirando el resultado**, no el código:

| Fase | Evidencia |
|---|---|
| F0 · Traslado | 12 GB en `/opt/moodledata/coipo_moodle`, dueño UID 33; volcados en `/opt/migracion/coipo_moodle` |
| F1 · Imagen | `coipo_moodle-app` y `-cron` construidas; las 20 extensiones PHP que exige Moodle 4.5 presentes |
| Deploy | Push a `main` reconstruye y redespliega solo (verificado: 140 s de extremo a extremo) |

### Rehecha con `moodle_db.sql` — 30 de julio de 2026

El volcado anterior no era el archivo correcto. Se rehizo entera. Duración total: ~25 minutos.

| Fase | Evidencia |
|---|---|
| Respaldo previo | `respaldo-academia_prod-ANTES-de-moodle_db-*.sql.gz`, 34,7 MB, 503 tablas |
| Compatibilidad con `moodledata` | **3.076 de 3.076 `contenthash` presentes** en `filedir` antes de tocar nada |
| F2 · Carga en MariaDB | **67 s** · 501 tablas · **cero tablas sin clave primaria** · 1.971 índices · los conteos coinciden con lo declarado en el `.sql` |
| F3 · Upgrade | **16 s** · el core se queda en 2024100710 (ya venía al día); suben `block_configurable_reports`, `tool_mergeusers` y `theme_boost_magnific`, que **crea las 2 tablas que faltaban** → 503 |
| Validación | `check_database_schema.php` → `Database structure is ok.` |
| F4 · `dbtransfer` | **12 min** · `== Éxito ==` · **las 503 tablas comparadas una por una: 1.843.658 filas idénticas en MariaDB y PostgreSQL, cero diferencias** |
| Secuencias | **502 correctas, 0 mal**, todas en `max(id)+1`. Un `INSERT` real devolvió id nuevo |
| F5 · Sitio | `/health` ok · `dbtype` pgsql · login 200 en **21,6 ms** · logo servido desde `moodledata` como PNG real · tema `boost_magnific` con CSS en 26 ms · escritura en `moodledata`: 5.661 archivos, **0 con dueño root** |

**Rollback en tres capas**, ninguna borrada: el respaldo de `academia_prod` previo, la base
`bitnami_moodle` completa dentro del volumen `mariadb_tmp`, y los dos volcados originales.

**Dato a vigilar.** La migración anterior terminó con **159.768 filas en `mdl_files`** contra
las 178.833 del volcado. Esta vez salió **179.147 contra 179.147**, así que las 19.065 filas
**no se perdían en el `dbtransfer` sino después** — el sospechoso es
`file_trash_cleanup_task`, que había corrido tres veces. Conviene volver a contar
`mdl_files` en unos días y comparar contra 179.147.

**La clave de administración cambió.** Las hashes vienen del volcado nuevo, así que la que
servía antes da "Acceso inválido". `siteadmins` sigue siendo `2,7,6,247,248` y los cinco
tienen `auth='manual'`.

Salida a internet del servidor: **confirmada** (`curl -I https://github.com` → 200), que era el
bloqueante del build.

### Lecciones que costaron tiempo — están documentadas en `docs/MIGRACION.md`

- `--env-file` **no** reemplaza `env_file:` de un servicio; solo alimenta la interpolación de
  `${...}`. Las variables de la fase de conversión van en `environment:`.
- `MOODLE_REVERSEPROXY` debe quedar en **`false`** aunque haya Nginx delante:
  `lib/setuplib.php:745` aborta si el Host recibido coincide con el de `wwwroot`.
- El modo mantención por CLI es **solo** `climaintenance.html` en `moodledata`. Si un
  `--enable` falla después de escribirlo, queda huérfano y el sitio responde "under
  maintenance" a todo, incluido el healthcheck.
- El `rsync` del deploy excluye `.env` pero **borra todo lo demás** que no esté en el
  repositorio: por eso `.env.migracion` vive en `/opt/migracion/coipo_moodle/`.
- `APP_PORT` es el puerto **interno** por el que Nginx alcanza el contenedor. Nunca 80: choca
  con Nginx y tumba también las otras apps del servidor.
- El `AUTO_INCREMENT` de una tabla dice cuántas filas existieron alguna vez, no cuántas
  quedan. Estimar volumen con él da números inflados.

### Pendientes

0. **Entrar como administrador y verificar con sesión** — es lo único de la migración del
   30-07-2026 que quedó sin comprobar: abrir un curso, abrir un PDF y subir un archivo.
   La clave del XAMPP de origen debería servir.

1. **Reescribir las URLs fósiles — pero NO con `tool_replace` sobre `mdl_question`.**
   Son dos dominios: `campus.conaf.cl` (20.994) y **`127.0.0.1:8080` (26.816)**, este último
   porque el sitio corrió en un XAMPP local en `localhost:8080`. Medido el 30-07-2026:
   - El 80 % está en `mdl_logstore_standard_log`, que `tool_replace` salta por diseño. **No
     hay que tocarlo**: es traza de auditoría.
   - De las ~3.378 restantes, **3.283 son `mdl_question.stamp` y
     `mdl_question_categories.stamp`**, que **no son URLs** sino el identificador de
     deduplicación de preguntas de Moodle: `campus.conaf.cl+240922154501+0zOvBC`. Nunca se
     renderiza y nunca genera una petición. **Reescribirlo sería un error.**
   - Lo que sí hay que corregir a mano son **5 valores de `mdl_config_plugins` del tema
     activo** (`theme_boost_magnific`: `mycourses_url_1/2/3`, `slideshow_url_1/2`) y **3 de
     `mdl_user_info_field.param4`** (de `mergeusers`). Son enlaces de navegación: llevan al
     sitio viejo si alguien hace clic, pero **no afectan el rendimiento ni el render**.
   - **Cero referencias fósiles en atributos `src`**: ninguna imagen ni recurso incrustado
     apunta fuera. Verificado sobre todas las columnas de texto de las 503 tablas.
2. ~~Certificado `*.conaf.cl`~~ **RESUELTO el 30-07-2026.** El TLS lo termina el balanceador
   Alteon de 172.31.2.100, no nuestro Nginx, que sigue escuchando solo en el puerto 80 y **no
   necesita certificado**. El `.env` del servidor quedó en `MOODLE_WWWROOT=https://academia.conaf.cl`
   y **`MOODLE_SSLPROXY=true`**.

   **Las dos variables van juntas, siempre.** Con `https://` y `sslproxy=false`, Moodle genera
   las URLs de sus recursos con `http://` dentro de una página servida por `https://`; el
   navegador las bloquea por contenido mixto y **el sitio aparece sin CSS ni JS, sin ningún
   error visible**. Se verifica mirando `Set-Cookie`: `MoodleSession` debe salir marcada
   `secure`. Al cambiar `wwwroot` hay que purgar cachés y recompilar el CSS del tema, porque
   las URLs quedan incrustadas en el CSS cacheado.
3. **Revisar quiénes son administradores del sitio**: `siteadmins` trae `2,7,6,247,248`. El
   id 2 es `user`/`user@example.com`, la cuenta por defecto de Bitnami. En una plataforma con
   datos de 2.798 funcionarios deberían quedar solo cuentas nominadas de CONAF.
4. Borrar la cuenta de prueba que se cree para validar la vista de alumno.
5. **Rotar DOS contraseñas, no una.** La del rol `iam` expuesta en `INSUMO/setup_bd.sql`, y
   **la del rol `academia`**: `admin/tool/dbtransfer/cli/migrate.php` la recibe como argumento
   de línea de comandos, así que queda visible con un `ps` para cualquiera con acceso al
   contenedor mientras corre la migración. Es una limitación de la herramienta de Moodle.
   Conviene rotarlas juntas, porque este mismo archivo ya advertía que se parecen entre sí.
   `ALTER ROLE ... PASSWORD` en 172.31.2.40, actualizar el `.env` con `chmod 600` y recrear
   los contenedores.

5b. **`CONNECTION LIMIT` del rol `academia` es 20, no 60.** Verificado con
   `SELECT rolconnlimit FROM pg_roles`. El rol **no puede subírselo** (`permission denied to
   alter role`: no tiene `CREATEROLE`): hay que pedírselo al administrador de 172.31.2.40 con
   `ALTER ROLE academia CONNECTION LIMIT 60;`. Mientras tanto, `MaxRequestWorkers` está en
   **16** en `docker/apache-moodle.conf` justamente para respetar ese techo — con 150, la
   petición 21 recibía `FATAL: too many connections` y el usuario veía un error 500. Cuando
   suban el límite, subir `MaxRequestWorkers` a 50 y no más, para dejar margen al cron.

5c. ~~`academia.conaf.cl` no existe en el DNS~~ **RESUELTO el 30-07-2026.** Ya resuelve a
   **172.31.2.100** (el Alteon), no a 172.31.2.41. Ya no hace falta la entrada en `hosts` ni
   el `--host-resolver-rules` del navegador.

5d. **11 tareas adhoc `assignfeedback_editpdf\task\convert_submission` fallando en bucle**,
   con `faildelay` acumulado de 337.920. Probablemente falte `ghostscript` en la imagen.
6. Decidir sobre el correo saliente: `MOODLE_NOEMAILEVER=true` significa que nadie recibe
   notificaciones ni recuperación de contraseña. Apagarlo requiere SMTP configurado y una
   decisión explícita.
7. Definir política de respaldos: no hay ninguna para la base ni para los 10,9 GB.
8. **No borrar todavía** el volumen `coipo_moodle_mariadb_tmp` ni el volcado: son el rollback.
9. Correo a Lazzos para el traspaso formal, DNS y fecha de corte.
10. Retomar el levantamiento de requisitos (áreas 3 a 8).
