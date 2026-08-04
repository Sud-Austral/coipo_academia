# CLAUDE.md — coipo_academia

Migración de la plataforma Moodle de CONAF, hoy alojada por la empresa externa **Lazzos**, a
infraestructura propia. Lo pidió la **Dirección Ejecutiva** (nivel nacional). Encargado
técnico: Luis Monsalve.

No es una aplicación nueva: es un Moodle en producción que hay que mover. **La migración ya
está hecha y verificada** (fases F0–F5, 30-07-2026). Lo que queda es operación, limpieza y
gobierno de datos.

**El repositorio se llama `coipo_academia`** (remoto `Sud-Austral/coipo_academia`). Los
documentos y los `.env.example` todavía nombran rutas del servidor como
`/opt/apps/coipo_moodle/` y `/opt/moodledata/coipo_moodle`. **No es un error de copia**: son
dos cosas distintas y hay que tratarlas por separado.

- El **directorio de despliegue** lo fija el workflow con
  `app_name: ${{ github.event.repository.name }}` → `/opt/apps/<nombre del repo>`. Si el
  repositorio se renombró, ese directorio **cambió con él**: verificar en el servidor antes
  de dar por buena cualquier ruta de este archivo.
- El **bind mount de moodledata** lo fija `MOODLEDATA_HOST_PATH` del `.env`, no el nombre del
  repositorio. Sigue siendo `/opt/moodledata/coipo_moodle` mientras nadie mueva la carpeta —
  y **moverla no aporta nada y arriesga 12 GB**.

---

## Dominios: de dónde viene y a dónde va

| Dominio | Rol |
|---|---|
| `campus.conaf.cl` | **Origen.** Es el sitio del que salió el volcado (20.994 referencias dentro de la base). Queda descontinuado |
| `academia.conaf.cl` | **Destino definitivo.** Ya resuelve, ya tiene TLS y el sitio está ahí |
| `cursos.conaf.cl` | Aparece **cero** veces en el volcado. Fue el nombre con que se planteó el encargo, pero no es ninguno de los dos |

Consecuencia operativa: el contenido de los cursos trae 20.994 enlaces absolutos a
`campus.conaf.cl` incrustados. Medido el 30-07-2026, **casi ninguno hay que reescribirlo** —
ver el pendiente 1, que corrige lo que este archivo decía antes. Por eso el rol de base de
datos se llama `academia` / `academia_prod`.

---

## Mapa del repositorio

El repositorio guarda **cómo se construye y se despliega** el sistema, y la documentación del
proyecto. Nunca datos ni secretos.

```
Dockerfile                      imagen única para las dos fases (Apache + PHP 8.3 + Moodle 4.5.10)
docker-compose.yml              estado final: app + cron contra PostgreSQL 17 de 172.31.2.40
docker-compose.migracion.yml    superposición temporal: MariaDB desechable para leer el volcado
.env.example                    lista de referencia de variables (no se copia; el .env real va a mano)
.env.migracion.example          idem para la fase de conversión
docker/
  config.php                    config.php de Moodle: TODO por variables de entorno
  health.php                    /health — NO carga Moodle, a propósito
  apache-moodle.conf            MPM prefork, RemoteIP, alias /health, blindaje de moodledata
  php.ini                       OPcache, realpath cache, límites (ajustado y medido)
  moodle-crontab                una línea: cron.php cada minuto, por supercronic
db/setup_bd.sql                 rol y base de PostgreSQL — con MARCADOR, nunca la clave real
plugins/                        los 5 plugins no-core vendorizados (ver tabla más abajo)
docs/                           TRASLADO, MIGRACION, TLS-HTTPS, nginx-academia.conf, entrevista
manuales/                       8 manuales de usuario por rol + README (ver sección propia)
mejoras.md · mejoras.pdf        trabajo de rendimiento: fase 0 medida y cerrada
INSUMO/                         referencia de infraestructura CONAF (Docker, deploy, PostgreSQL)
INSUMO_MEJORA/                  insumos de la propuesta de rediseño (prototipo HTML + 2 .docx)
.github/workflows/deploy-prod.yml   push a main → workflow reutilizable de infra-docker-base
```

**No hay build ni suite de pruebas propia.** El código de Moodle no vive en el repositorio: lo
clona el `Dockerfile` en tiempo de build (`--branch v4.5.10`). Los plugins vendorizados traen
sus propios tests de PHPUnit, pero se ejecutan dentro de un Moodle completo y **este proyecto
no los corre**. La verificación de este sistema es funcional y está definida más abajo.

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
| Código fuente | Moodle **4.5.10** (Build 20260216), branch 405 — lo clona el `Dockerfile`, no está en el repositorio |
| moodledata | **12 GB** en `/opt/moodledata/coipo_moodle` · `filedir` 11 GB. **Compatible con el volcado nuevo**: se verificaron 3.076 `contenthash` distintos y los 3.076 existen |
| Escala (conteos reales tras la migración del 30-07-2026) | **2.869 usuarios** activos (2.873 filas) · **37 filas** en `mdl_course` (36 cursos + el curso "sitio") · **3.731 matrículas** · **179.147 filas** en `mdl_files` · **503 tablas** · 1.843.658 filas en total |
| `mdl_task_log` | Se **trunca antes de F4** (traía 11.099 filas). `dbtransfer` copia fila por fila a través de PHP y esa tabla no aporta nada a la migración |
| Tema activo | `boost_magnific` (recuperado y vendorizado) |
| Requisitos de Moodle 4.5 | PHP ≥ 8.1 y **bloquea 8.4** · PostgreSQL ≥ 13 · MariaDB ≥ 10.6.7 |
| Tamaño máximo de subida | 200 MB (`docker/php.ini`) |

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

**Son datos personales de ~2.870 funcionarios.** Aplica la Ley 19.628. Eso condiciona todo lo
demás.

### Plugins no-core — resueltos, los cinco en `plugins/`

Versiones leídas de los `version.php` vendorizados. La regla: la versión vendorizada debe ser
**igual o superior** a la registrada en la base. Si fuera menor, Moodle entiende que se está
degradando el plugin y se niega a arrancar.

| Componente | En la base | Vendorizado | Origen |
|---|---|---|---|
| `mod_customcert` | 2024042217 | 2024042217 · release 4.4.9 (19 subplugins de elementos) | el árbol de código original |
| `tool_mergeusers` | 2025020504 | 2026052700 · `supported [405,502]` | `ndunand/moodle-tool_mergeusers`, rama `MOODLE_405_STABLE` |
| `block_configurable_reports` | 2024051300 | 2027050401 · release 5.2.0 · `supported [400,500]` | `jleyva/moodle-block_configurablereports` |
| `theme_boost_magnific` | 2024073000 | 2026062801 · release 9.6.2 · requiere 4.4 | `EduardoKrausME/moodle-theme_boost_magnific` |
| `theme_academi` | 2024060503 | 2025050300 · `v4.5.1` · requiere 4.4 | `lmsace/academi`, del directorio oficial. MD5 verificado (`555e3d35…`) |
| `theme_moove`, `almondb`, `degrade` | varias | No | Inactivos y sin datos propios: se ignoran |

**Corrección del 31-07-2026 sobre `theme_academi`.** Este archivo lo daba por "inactivo y sin
datos propios". Es falso: tiene **~135 ajustes en `mdl_config_plugins`** con datos reales de
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

---

## Decisiones tomadas

1. **PostgreSQL 17 como motor final**, aunque el volcado sea MariaDB. Implica un paso
   intermedio inevitable: un MariaDB temporal que solo sirve para leer el volcado, y luego
   `admin/tool/dbtransfer` para pasar a PostgreSQL. No se usa `pgloader` ni conversión directa
   del `.sql`: deja las secuencias en 1 y el primer `INSERT` de Moodle revienta.
2. **Moodle 4.5.10.** El orden es obligatorio: `dbtransfer` valida el esquema contra el código
   en disco, así que **primero el upgrade, después la migración**.
3. **moodledata fuera del contenedor**, por bind mount con lectura y escritura.
4. **Corre en el servidor CONAF 172.31.2.41**, con el PostgreSQL 17 compartido de 172.31.2.40.
   Rol `academia`, base `academia_prod`, puerto `8115`. **Traer PostgreSQL a la misma máquina
   no está sobre la mesa**: contradice el estándar de infraestructura CONAF y crea otro motor
   que respaldar y parchar.

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
        └──── bind mount ─────────┴──► /opt/moodledata/coipo_moodle  (12 GB, UID 33)
        │
        ▼
   PostgreSQL 17 compartido — 172.31.2.40 (fuera de Docker)
```

**Una sola imagen para las dos fases.** `docker/config.php` lee todo de variables de entorno,
así el mismo artefacto apunta a MariaDB durante la conversión y a PostgreSQL en el estado
final. Lo que cambia es el `.env`, no la imagen.

### Rutas en el servidor

```
/opt/apps/<nombre del repo>/     repositorio desplegado (código, compose, .env)
/opt/moodledata/coipo_moodle/    los 12 GB — FUERA del directorio de deploy
/opt/migracion/coipo_moodle/     el volcado y .env.migracion, temporales
```

`moodledata` va fuera de `/opt/apps/` a propósito: el deploy sincroniza esa carpeta con
`rsync --delete` y un borrado accidental se llevaría el contenido de los cursos.

### Despliegue

`.github/workflows/deploy-prod.yml` no hace el trabajo: delega en el workflow reutilizable
`Sud-Austral/infra-docker-base/.github/workflows/deploy.yml@main`, pasándole el nombre del
repositorio como `app_name`. **Push a `main` reconstruye y redespliega solo** (medido: 140 s de
extremo a extremo). No hay despliegue manual documentado ni previsto.

---

## Reglas de higiene — no negociables

- **Nada de datos ni secretos en el repositorio.** El volcado supera el límite de 100 MB de
  GitHub y contiene datos personales. `.gitignore` bloquea `*.sql` (con excepción explícita
  para `db/setup_bd.sql` y los de los plugins), `/Moodle/`, `/moodledata/`, `.env`, `.env.*`
  salvo los dos `.example`, comprimidos y `.claude/`; `.dockerignore` evita que todo eso viaje
  al contexto de build.
- `db/setup_bd.sql` **se versiona a propósito y lleva un marcador**
  (`REEMPLAZAR_CON_openssl_rand_hex_32`), nunca la clave real. Quien lo ejecute reemplaza,
  ejecuta y borra su copia con `shred -u`.
- Las claves se generan con `openssl rand -hex 32`, viven solo en el `.env` del servidor con
  `chmod 600`, y los archivos que las contuvieron se borran con `shred -u`.
- Los comandos de Moodle dentro del contenedor se ejecutan **siempre con `-u www-data`**. Si
  se corren como root, los archivos que Moodle crea en `moodledata` quedan con dueño root y
  el sitio deja de poder escribir. Es el error más común y el más confuso.
- **`MOODLE_NOEMAILEVER=true` mientras esto sea un entorno de pruebas.** Son ~2.870 correos
  institucionales reales: sin ese freno, el cron envía resúmenes de foro y avisos de
  contraseña a funcionarios de verdad. Consecuencia visible hoy: **"¿Olvidó su contraseña?" no
  funciona** y los manuales lo advierten en cada sección 2.
- `.gitattributes` fuerza normalización de fin de línea. Con la normalización anterior, el
  crontab y los scripts entraban a la imagen con `\r` y fallaban con "bad interpreter".
- **Después de cada purga de caché y de cada deploy, pre-compilar el CSS del tema**
  (`admin/cli/build_theme_css.php`, medido: 4,1 s). Purgar sin recompilar obliga a rehacer
  1,7 MB de SCSS en la primera visita y es el origen de los episodios de "se congeló".

---

## Cómo verificar (regla del proyecto)

"Funciona" significa **haber visto el resultado**, no que el contenedor levantó ni que el
comando no dio error. El mínimo para este sistema, y **no es negociable aunque el cambio sea
"solo de rendimiento"**:

1. `curl -sI http://172.31.2.41:8115/login/index.php` → **200**.
2. `/health` responde `{"status":"ok"}` con `db: ok` y `dataroot: ok`.
3. Entrar por navegador como administrador.
4. Abrir un curso y **abrir un PDF o SCORM** → confirma que `moodledata` se lee.
5. Subir un archivo desde la web y encontrarlo en `/opt/moodledata/coipo_moodle/filedir/`
   **en el host** → confirma que se escribe fuera del contenedor.
6. Contar: **503 tablas, 2.869 usuarios, 37 filas en `mdl_course`**. Si no cuadran, no está
   listo.
7. `php admin/cli/cfg.php --name=dbtype` → `pgsql`.
8. Con HTTPS: `Set-Cookie: MoodleSession` debe salir marcada **`secure`**.

**Lo que parece un fallo y no lo es**: los enlaces fósiles a `campus.conaf.cl` que quedan son
enlaces de navegación del tema y de `user_info_field`, no recursos incrustados. **Cero
referencias fósiles en atributos `src`** — verificado sobre todas las columnas de texto de las
503 tablas. Ninguna imagen se carga desde el sitio antiguo.

---

## Rendimiento — medido y cerrado (`mejoras.md`)

**El servidor no está lento.** La fase 0 se ejecutó completa contra producción el 30-07-2026 y
el documento entero (`mejoras.md`, con copia en PDF) quedó cerrado con veredicto. Números de
referencia, mediana de 10 repeticiones:

| Página | Mediana | Consultas |
|---|---|---|
| `/login/index.php` | 20,1 ms (21,6 tras la migración nueva) | 13 |
| `/my/courses.php` | 63,2 ms | 36 |
| portada | 96,4 ms | 46 |
| curso más pesado (72 actividades, 348 matrículas, 937 KB) | 219,5 ms · **0,30 s la página entera con sus 18 subrecursos** | 230 |
| libro de calificaciones (348 × 44) | 137,9 ms | 102 |

Lo aplicado: **`php.ini` afinado** (`interned_strings_buffer` 16 → 32 MB porque estaba
agotado; `realpath_cache_ttl` 120 → 600 s) y **`MaxRequestWorkers` acotado**. Ninguno acelera —
corrigen condiciones reales. Lo probado y **revertido**: `langstringcache=1`, que empeora bajo
Apache porque OPcache ya tiene los archivos de idioma compilados.

**Antes de proponer cualquier optimización, leer la tabla "Qué NO hacer" de `mejoras.md`.**
Está entera respaldada con mediciones: `VACUUM FULL`, `REINDEX`, `dbpersist`, subir
`memory_limit`, JIT de OPcache, `yuicomboloading=0`, Varnish/CDN, purgar cachés "para que ande
mejor", mover `sessions` a tmpfs, y bajar `perfdebug` — todas descartadas con datos.

Tres cosas que conviene decir de entrada porque siempre vuelven a surgir:

- **`perfdebug = 7` significa APAGADO**, no "nivel 7" (`admin/settings/development.php:53`).
- **Ya hay compresión**: 23.279 → 5.222 bytes. `gzip on` en Nginx no aportaría nada.
- **El autovacuum ya hizo el `ANALYZE`**: `cache_hit` 100,00 %, base de 651 MB contra
  `shared_buffers` de 7 GB.

---

## Manuales de usuario (`manuales/`) — y la limpieza que dejan pendiente

Ocho manuales, uno por rol de Moodle, escritos para gente que no trabaja con computadores
todos los días (~5.000 líneas en total). Los ocho comparten la misma estructura de 8
secciones. `manuales/README.md` es el índice y explica cuál le toca a cada quien.

| # | Rol | Manual | Permisos |
|---|---|---|---|
| 1 | `manager` | `01-manager.md` | 593 |
| 2 | `coursecreator` | `02-course-creator.md` | 26 |
| 3 | `editingteacher` | `03-teacher.md` | 486 |
| 4 | `teacher` | `04-non-editing-teacher.md` | 224 |
| 5 | `student` | `05-student.md` | 84 |
| 6 | `guest` | `06-guest.md` | 30 |
| 7 | `user` | `07-authenticated-user.md` | 116 |
| 8 | `frontpage` | `08-authenticated-user-frontpage.md` | 10 |

Los tres últimos **no son roles asignables**: Moodle los aplica solo. Por eso `guest` no tiene
—ni puede tener— cuenta de práctica.

> ### ⚠️ Hay seis cuentas reales con contraseña escrita en el repositorio
>
> Para escribir y probar los manuales se creó, **en el sitio de producción**, una categoría
> "Manuales de usuario", el curso `MANUALES` (id 41) y **seis cuentas `manual.*` cuyas
> contraseñas están en `manuales/README.md`**. Las seis inician sesión. Cualquiera que lea el
> repositorio puede entrar.
>
> **Es el pendiente más urgente de este proyecto** (pendiente 0 más abajo), porque la
> plataforma tiene datos personales de 2.869 personas y aplica la Ley 19.628.

De paso, el trabajo de manuales confirmó lo que la migración había dejado sin comprobar: hay
**acceso administrativo con sesión** y el sitio permite crear categoría, curso, usuarios,
matrículas y roles.

---

## Documentos

| Documento | Contenido |
|---|---|
| **`docs/V2-ACADEMIA.md`** | **La v2.** Modelo de datos, vistas, el rol de Gestor de Área, los dos cursos y las decisiones abiertas. Empezar por acá para todo lo de `INSUMO_MEJORA` |
| **`academia/README.md`** | Orden de ejecución de los scripts de provisión, los CSV de datos y las pruebas |
| `docs/TRASLADO.md` | Fase F0: mover el volcado y los 10,9 GB al servidor. Lo ejecuta Luis |
| `docs/MIGRACION.md` | Fases F1–F5: imagen, carga, upgrade, paso a PostgreSQL, sitio final |
| `docs/TLS-HTTPS.md` | Paso a HTTPS (30-07-2026). El TLS lo termina el Alteon de 172.31.2.100, no nuestro Nginx. Por qué `MOODLE_WWWROOT` y `MOODLE_SSLPROXY` van siempre juntas, cómo se verifica y cómo se revierte |
| `docs/nginx-academia.conf` | Copia de referencia del vhost real. Escucha solo en el puerto 80 **a propósito** |
| `mejoras.md` · `mejoras.pdf` | Rendimiento: fase 0 medida, veredicto y tabla de "qué NO hacer" |
| `manuales/` | Los 8 manuales de usuario y su README |
| `INSUMO/` | Referencia de la infraestructura CONAF: patrón Docker, deploy, PostgreSQL |
| `INSUMO_MEJORA/` | Insumos de la propuesta de rediseño: `prototipo-academia-conaf.html` (prototipo funcional autocontenido), propuesta institucional y diseño de curso IF-151, en `.docx` |
| `docs/entrevista-avance.md` | Levantamiento de requisitos, pausado. Se borra cuando exista `docs/REQUISITOS.md` |

---

## Estado y pendientes

### Hecho y verificado en el servidor

| Fase | Evidencia |
|---|---|
| F0 · Traslado | 12 GB en `/opt/moodledata/coipo_moodle`, dueño UID 33; volcados en `/opt/migracion/coipo_moodle` |
| F1 · Imagen | `app` y `cron` construidas; las 20 extensiones PHP que exige Moodle 4.5 presentes |
| Deploy | Push a `main` reconstruye y redespliega solo (verificado: 140 s de extremo a extremo) |

### Migración rehecha con `moodle_db.sql` — 30 de julio de 2026

El volcado anterior no era el archivo correcto. Se rehizo entera. Duración total: ~25 minutos.

| Fase | Evidencia |
|---|---|
| Respaldo previo | `respaldo-academia_prod-ANTES-de-moodle_db-*.sql.gz`, 34,7 MB, 503 tablas |
| Compatibilidad con `moodledata` | **3.076 de 3.076 `contenthash` presentes** en `filedir` antes de tocar nada |
| F2 · Carga en MariaDB | **67 s** · 501 tablas · **cero tablas sin clave primaria** · 1.971 índices |
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
`file_trash_cleanup_task`, que había corrido tres veces. **Volver a contar `mdl_files` y
comparar contra 179.147.**

**La clave de administración cambió.** Las hashes vienen del volcado nuevo, así que la que
servía antes da "Acceso inválido". `siteadmins` sigue siendo `2,7,6,247,248` y los cinco
tienen `auth='manual'`.

### Lecciones que costaron tiempo — están documentadas en `docs/MIGRACION.md`

- `--env-file` **no** reemplaza `env_file:` de un servicio; solo alimenta la interpolación de
  `${...}`. Las variables de la fase de conversión van en `environment:`.
- `MOODLE_REVERSEPROXY` debe quedar en **`false`** aunque haya dos proxys delante:
  `lib/setuplib.php:745` aborta si el Host recibido coincide con el de `wwwroot`.
- `MOODLE_WWWROOT` y `MOODLE_SSLPROXY` van **siempre juntas**. Con `https://` y
  `sslproxy=false`, Moodle genera las URLs de sus recursos con `http://` dentro de una página
  servida por `https://`; el navegador las bloquea por contenido mixto y **el sitio aparece
  sin CSS ni JS, sin ningún error visible**. Al revés (`http://` + `sslproxy=true`) las
  cookies salen `Secure` y el login deja de funcionar. Al cambiar `wwwroot` hay que purgar
  cachés y recompilar el CSS del tema.
- El modo mantención por CLI es **solo** `climaintenance.html` en `moodledata`. Si un
  `--enable` falla después de escribirlo, queda huérfano y el sitio responde "under
  maintenance" a todo, incluido el healthcheck. `/health` lo reporta en el campo `mantencion`.
- `/health` **no carga Moodle a propósito**: un chequeo de salud tiene que poder responder
  cuando la configuración del sitio es justamente lo que falla.
- El `rsync` del deploy excluye `.env` pero **borra todo lo demás** que no esté en el
  repositorio: por eso `.env.migracion` vive en `/opt/migracion/coipo_moodle/`.
- `APP_PORT` es el puerto **interno** por el que Nginx alcanza el contenedor. Nunca 80: choca
  con Nginx y tumba también las otras apps del servidor.
- Un **duplicado de variable en el `.env`** es traicionero: gana la última aparición, así que
  una clave correcta arriba queda anulada por un marcador pegado más abajo. Ya pasó una vez.
  `grep -oE '^[A-Z_]+=' .env | sort | uniq -d` debe salir vacío.
- El `AUTO_INCREMENT` de una tabla dice cuántas filas existieron alguna vez, no cuántas
  quedan. Estimar volumen con él da números inflados.

---

## La v2 — este repositorio

`coipo_moodle` resolvió la infraestructura. **`coipo_academia` resuelve la arquitectura de
información**, que es lo que `INSUMO_MEJORA` identifica como el problema real: 9 categorías
planas con cinco criterios mezclados, 1 campo de clasificación, 0 cohortes, 0 roles delegados y
las competencias apagadas.

**Corre en paralelo, no reemplaza — y va por delante en versión.** El deploy usa el nombre del
repositorio como `app_name`, así que son dos despliegues distintos en el mismo servidor:

| | Puerto | Base | Moodle | PHP |
|---|---|---|---|---|
| `coipo_moodle` | 8115 | `academia_prod` | 4.5.10 LTS | 8.3 |
| `coipo_academia` | 8116 | `academia_v2` | **5.2.1** | **8.4** |

**Por qué v2 va en 5.2.** El destino es 5.3 LTS + PHP 8.4 en octubre de 2026, que es lo que la
Propuesta ya planifica. Construir v2 sobre 5.2 ahora ensaya el salto 4.5 → 5.x en un clon
desechable y convierte el de octubre en 5.2 → 5.3, un paso corto, en vez de cuatro versiones
de golpe. PHP 8.3 además salió de soporte activo el 31-12-2025; 8.4 lo tiene hasta 12-2026.

**No se usa PHP 8.5** aunque Moodle 5.2 no tenga ningún bloqueo superior —verificado: su
`environment.xml` pide mínimo 8.3.0 y no trae ni un `<RESTRICT>`—. Moodle solo documenta 8.4.x
como soportada, y «no da error» no es «está soportado».

### MOODLE 5.2 MOVIÓ LA RAÍZ WEB A `public/`

Es el cambio que más rompe y el que hay que tener presente en cada ruta:

```
/var/www/html/           raíz del repositorio. Acá va config.php
/var/www/html/public/    raíz web: DocumentRoot y valor de $CFG->dirroot
/var/www/html/admin/cli/ los scripts CLI, que NO se movieron
```

El `index.php` de la raíz lanza una excepción a propósito (`rootdirpublic`) si el DocumentRoot
quedó mal apuntado. Cambió el `Dockerfile` (DocumentRoot y los `COPY` de plugins a
`public/<tipo>/`) y `docker/apache-moodle.conf`. **No** cambiaron `docker/moodle-crontab` ni
`academia/cli/bootstrap.php`, porque los dos apuntan a cosas que se quedaron en la raíz.

Esa misma lista es el trabajo que le espera a `coipo_moodle` cuando actualice.

Lo construido y las decisiones abiertas están en **`docs/V2-ACADEMIA.md`**; el orden de
ejecución, en **`academia/README.md`**. Cuatro cosas que conviene saber antes de tocar nada:

- **Los scripts se niegan a correr contra `academia_prod`.** Hay que pasar
  `--permitir-produccion` a mano, script por script.
- **`MaxRequestWorkers` está en 6 en este repositorio, no en 50.** El `CONNECTION LIMIT` es del
  ROL `academia`, no de la base, así que las dos instancias comparten el techo de 60:
  50 (prod) + 6 (v2) + 2 crones = 58. Al promover v2 hay que devolverlo a 50.
- **`filedir` se clona por hardlink** (`cp -al`), no se copia: son 11 GB de contenido
  direccionado por hash e inmutable. El clon cuesta ~1 GB en vez de 12.
- **`MOODLE_NOEMAILEVER=true` es obligatorio en v2**, no una preferencia. Es un clon con los
  2.869 correos institucionales reales, y sin el freno los avisos llegarían dos veces.

### Lecciones de la v2 que costaron tiempo

- **`customfield_select` guarda un ÍNDICE entero, no el texto de la opción.** Pasarle la
  etiqueta la convierte en 0 y el campo queda vacío — sin error, sin aviso, y el curso
  desaparece del catálogo. Los scripts hablan en etiquetas y convierten al escribir.
- **`enablemobilewebservice` no es una casilla suelta.** En la interfaz dispara
  `admin_setting_enablemobileservice::write_setting()`, que además habilita el servicio externo
  `moodle_mobile_app`, agrega el protocolo REST y da `webservice/rest:use` al usuario
  autenticado. Un `set_config()` solo deja el ajuste en 1 y **la app sigue sin funcionar**.
- **El rol de Gestor de Área lo define su CONTEXTO, no sus capacidades.**
  `set_role_contextlevels($id, [CONTEXT_COURSECAT])` es la línea que impide que un gestor vea
  las siete áreas. Si ahí aparece `CONTEXT_SYSTEM`, el diseño entero se desarma.
- **Las restricciones por calificación usan PORCENTAJE (0-100), no la nota bruta.** Poner 4
  pensando en «4 de 5» produce una restricción que se cumple con el 4 %, y el curso deja pasar
  a todo el mundo sin que nadie lo note.
- **`docker/apache-moodle.conf` dice que el 31-07-2026 el límite del rol se amplió de 20 a 60**
  y que `MaxRequestWorkers` quedó en 50. El pendiente 5b de más abajo quedó desactualizado:
  comprobar con `SELECT rolconnlimit FROM pg_roles WHERE rolname='academia'` antes de creerle a
  cualquiera de los dos.
- **El nombre de la fuente de íconos es `Font Awesome 6 Free`, no `FontAwesome`.** El segundo
  es de FontAwesome 4 y hace años que no existe en Moodle; con él, un `::before` no dibuja
  nada. Costó caro porque el ícono es lo que impide que el significado viaje solo en el color
  (WCAG 1.4.1): el CSS compilaba, la clase existía, y el incumplimiento volvía sin que se viera
  nada roto. Y hace falta `font-weight: 900`, que es lo que selecciona el juego «solid».
- **`lib/scssphp` cambió de forma en 5.2**: pasó de estar aplanado a tener subdirectorio
  `src/`. Cualquier cosa que cargue esa biblioteca a mano tiene que probar las dos rutas.
- **`admin/cli/upgrade.php` es un paso obligatorio de v2**, no un detalle. La base se clona de
  `academia_prod` con esquema 4.5.10 y la imagen trae 5.2.1: el contenedor levanta igual pero
  el sitio no funciona hasta ejecutarlo. Después, `check_database_schema.php` tiene que decir
  `Database structure is ok.`

---

### Pendientes

0. **Borrar el material de práctica de los manuales — lo más urgente.** En producción quedaron
   seis cuentas `manual.*` con **contraseñas publicadas en `manuales/README.md`**, el curso
   `MANUALES` (id 41) y la categoría "Manuales de usuario". Orden: dar de baja del curso,
   borrar el curso, borrar la categoría vacía, borrar las seis cuentas, y quitar las
   contraseñas del README y de la sección 2 de cada manual. No toca los 37 cursos ni las 2.869
   personas reales.

1. **Reescribir las URLs fósiles — pero NO con `tool_replace` sobre `mdl_question`.**
   Son dos dominios: `campus.conaf.cl` (20.994) y **`127.0.0.1:8080` (26.816)**, este último
   porque el sitio corrió en un XAMPP local. Medido el 30-07-2026:
   - El 80 % está en `mdl_logstore_standard_log`, que `tool_replace` salta por diseño. **No
     hay que tocarlo**: es traza de auditoría.
   - De las ~3.378 restantes, **3.283 son `mdl_question.stamp` y
     `mdl_question_categories.stamp`**, que **no son URLs** sino el identificador de
     deduplicación de preguntas: `campus.conaf.cl+240922154501+0zOvBC`. Nunca se renderiza y
     nunca genera una petición. **Reescribirlo sería un error.**
   - Lo que sí hay que corregir a mano son **5 valores de `mdl_config_plugins` del tema
     activo** (`theme_boost_magnific`: `mycourses_url_1/2/3`, `slideshow_url_1/2`) y **3 de
     `mdl_user_info_field.param4`** (de `mergeusers`). Son enlaces de navegación: llevan al
     sitio viejo si alguien hace clic, pero **no afectan el rendimiento ni el render**.
   - **Cero referencias fósiles en atributos `src`.**

2. ~~Certificado `*.conaf.cl`~~ **RESUELTO el 30-07-2026.** Lo termina el Alteon de
   172.31.2.100; nuestro Nginx sigue en el puerto 80 y no necesita certificado. El `.env` del
   servidor quedó con `MOODLE_WWWROOT=https://academia.conaf.cl` y **`MOODLE_SSLPROXY=true`**.

3. **Revisar quiénes son administradores del sitio**: `siteadmins` trae `2,7,6,247,248`. El
   id 2 es `user`/`user@example.com`, la cuenta por defecto de Bitnami. En una plataforma con
   datos de 2.869 funcionarios deberían quedar solo cuentas nominadas de CONAF.

5b. ~~**`CONNECTION LIMIT` del rol `academia` es 20, no 60.**~~ **DESACTUALIZADO.**
   `docker/apache-moodle.conf` documenta que el **31-07-2026 el administrador de 172.31.2.40 lo
   amplió a 60** (y de paso `max_connections` 100 → 300), y dejó `MaxRequestWorkers` en 50.
   Los dos textos se contradicen: **comprobar antes de creerle a cualquiera** con
   `SELECT rolconnlimit FROM pg_roles WHERE rolname='academia';`. Importa porque ahora hay dos
   instancias repartiéndose ese techo. Lo que sigue es el texto original:

   **`CONNECTION LIMIT` del rol `academia` es 20, no 60.** Verificado con
   `SELECT rolconnlimit FROM pg_roles`. El rol **no puede subírselo** (`permission denied to
   alter role`: no tiene `CREATEROLE`): hay que pedírselo al administrador de 172.31.2.40 con
   `ALTER ROLE academia CONNECTION LIMIT 60;`. Mientras tanto, `MaxRequestWorkers` está en
   **16** en `docker/apache-moodle.conf` justamente para respetar ese techo — con 150, la
   petición 21 recibía `FATAL: too many connections` y el usuario veía un error 500. Cuando
   suban el límite, subir `MaxRequestWorkers` a 50 y no más, para dejar margen al cron.

5. ~~`CONNECTION LIMIT` del rol `academia` es 20~~ **RESUELTO el 31-07-2026.** El
   administrador de 172.31.2.40 lo subió de **20 a 60** (y de paso `max_connections` 100 → 300
   y `work_mem` 64 MB → 16 MB). Verificado con
   `SELECT rolconnlimit FROM pg_roles WHERE rolname='academia'` → 60. Con eso,
   `MaxRequestWorkers` de `docker/apache-moodle.conf` subió de 16 a **50, y no más**: 50 web +
   ~2 del cron + margen para CLI = 60. Pasar de ahí vuelve a producir `FATAL: too many
   connections` y errores 500. Si algún día amplían otra vez el límite del rol, este número
   sube con él — **nunca al revés**. Nótese que `db/setup_bd.sql` ya pide 60 en el
   `CREATE ROLE`, que es lo correcto para una instalación nueva.

5b. ~~`academia.conaf.cl` no existe en el DNS~~ **RESUELTO el 30-07-2026.** Resuelve a
   **172.31.2.100** (el Alteon).

5c. **11 tareas adhoc `assignfeedback_editpdf\task\convert_submission` fallando en bucle**,
   con `faildelay` acumulado de 337.920. Falta `ghostscript` en la imagen — confirmado: el
   `apt-get install` del `Dockerfile` no lo incluye. Se arregla agregándolo y desplegando.

6. Decidir sobre el correo saliente: `MOODLE_NOEMAILEVER=true` significa que nadie recibe
   notificaciones ni recuperación de contraseña, y los manuales ya lo advierten a los usuarios.
   Apagarlo requiere SMTP configurado y una decisión explícita.

7. Definir política de respaldos: no hay ninguna para la base ni para los 12 GB. **De esto
   depende la retención del log** (`loglifetime`): sin política de respaldos no se borra nada,
   y `loglifetime = 0` es la opción más difícil de defender bajo la Ley 19.628. Ver el
   apartado "Registro de eventos" de `mejoras.md`.

8. **No borrar todavía** el volumen `coipo_moodle_mariadb_tmp` ni los volcados: son el
   rollback. Apagar el contenedor `mariadb-tmp` es otra cosa y es seguro (1,26 GB usados con
   7,9 GB libres, así que tampoco urge).

9. Correo a Lazzos para el traspaso formal, DNS y fecha de corte.

10. Retomar el levantamiento de requisitos (áreas 3 a 8) y decidir qué se hace con la propuesta
    de rediseño de `INSUMO_MEJORA/`.

11. **Vigilancia continua, sin herramientas nuevas**: un `curl -w` a `/login/index.php` cada 5
    minutos desde el host, guardando `time_starttransfer` con fecha. La próxima vez que alguien
    diga "está lento", habrá una serie temporal para responder cuándo empezó.
