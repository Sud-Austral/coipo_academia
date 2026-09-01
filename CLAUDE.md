# CLAUDE.md — coipo_academia

Migración de la plataforma Moodle de CONAF, hoy alojada por la empresa externa **Lazzos**, a
infraestructura propia. Lo pidió la **Dirección Ejecutiva** (nivel nacional). Encargado
técnico: Luis Monsalve.

Este archivo cubre dos sistemas y conviene no confundirlos, porque desde el 31-08-2026 dejaron
de ser el mismo con distinta versión.

- **El campus actual** (`coipo_moodle`, base `academia_prod`, puerto 8115): el Moodle en
  producción que había que sacar de Lazzos. **Esa migración ya está hecha y verificada** (fases
  F0–F5, 30-07-2026); lo que le queda es operación, limpieza y gobierno de datos. Tiene 2.869
  usuarios y 37 cursos, y le aplica la Ley 19.628.
- **La Academia** (`coipo_academia`, base `academia_v2`, puerto 8116): este repositorio. Es un
  Moodle 5.2.1 **instalado de cero**, sin heredar del campus ni un usuario, ni un curso, ni un
  archivo. Empieza vacío y el contenido se crea sobre la plantilla GC-000.

Todo lo que sigue hasta «La Academia — este repositorio» describe el campus actual y se
conserva porque ese sistema sigue vivo. De ahí en adelante, la Academia.

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
Dockerfile                      la imagen de la Academia: `php:8.4-apache` y `ARG MOODLE_VERSION=v5.2.1` (el compose y `.env.v2.example` dicen lo mismo; si alguna vez vuelven a divergir, gana el compose)
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
plugins/                        los 2 que la imagen copia: mod/customcert y theme/academia
docs/                           TRASLADO, MIGRACION, TLS-HTTPS, nginx-academia.conf, entrevista
manuales/                       8 manuales de usuario por rol + README (ver sección propia)
mejoras.md · mejoras.pdf        trabajo de rendimiento: fase 0 medida y cerrada
INSUMO/                         referencia de infraestructura CONAF (Docker, deploy, PostgreSQL)
INSUMO_MEJORA/                  insumos de la propuesta de rediseño (prototipo HTML + 2 .docx)
.github/workflows/deploy-prod.yml   push a main → workflow reutilizable de infra-docker-base
```

**No hay build ni suite de pruebas propia.** El código de Moodle no vive en el repositorio: lo
clona el `Dockerfile` en tiempo de build (`--branch ${MOODLE_VERSION}`, que hoy vale
`v5.2.1` en las tres partes que lo fijan: `Dockerfile:33`, `docker-compose.yml:20` y `50`,
y `.env.v2.example`). `mod_customcert` trae
sus propios tests de PHPUnit —`theme_academia` no, porque es un hijo de Boost escrito acá y no
tiene lógica que probar—, pero se ejecutan dentro de un Moodle completo y **este proyecto no
los corre**. La verificación de este sistema es funcional y está definida más abajo.

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
| Tema activo | `boost_magnific`. Se recuperó y se vendorizó **para poder migrar**; el 01-09-2026 salió de `plugins/` y se recupera del historial de git. El campus lo sigue usando; la Academia usa `theme_academia` |
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

### Plugins no-core — los cinco que trajo el campus, los dos que quedaron

Versiones leídas de los `version.php` vendorizados. La regla: la versión vendorizada debe ser
**igual o superior** a la registrada en la base. Si fuera menor, Moodle entiende que se está
degradando el plugin y se niega a arrancar.

| Componente | En la base | Vendorizado | Origen |
|---|---|---|---|
| `mod_customcert` | 2024042217 | **2026042005 · release 5.2.4 · requiere 5.2** (19 elementos, `expiry` incluido) | el árbol original, subido a 5.2 en el commit «nuevas versiones» |
| `tool_mergeusers` | 2025020504 | 2026052700 · `supported [405,502]` | `ndunand/moodle-tool_mergeusers`, rama `MOODLE_405_STABLE` |
| `block_configurable_reports` | 2024051300 | 2027050401 · release 5.2.0 · `supported [400,500]` | `jleyva/moodle-block_configurablereports` |
| `theme_boost_magnific` | 2024073000 | 2026062801 · release 9.6.2 · requiere 4.4 | `EduardoKrausME/moodle-theme_boost_magnific` |
| `theme_academi` | 2024060503 | 2026042900 · `v5.2` · requiere 5.2 — **borrado el 01-09-2026** | `lmsace/academi`, del directorio oficial. El MD5 `555e3d35…` era el de la v4.5.1, no el de esta |
| `theme_moove`, `almondb`, `degrade` | varias | No | Inactivos y sin datos propios: se ignoran |

**Corrección del 31-07-2026 sobre `theme_academi`.** Este archivo lo daba por "inactivo y sin
datos propios". Es falso: tiene **~135 ajustes en `mdl_config_plugins`** con datos reales de
CONAF (`address = Paseo Bulnes 285, Santiago, Chile`, `emailid = campus.geprif@conaf.cl`,
aviso de copyright). Sus archivos nunca se habían vendorizado, así que Moodle lo listaba como
ausente del disco y esos ajustes quedaban huérfanos. Se vendorizó en
`plugins/theme/academi/` y el `Dockerfile` lo copiaba. El tema activo del campus sigue
siendo `boost_magnific`.

**Y el 01-09-2026 se borraron cuatro de los cinco**: `theme_boost_magnific`,
`theme_academi`, `block_configurable_reports` y `tool_mergeusers`. La tabla de arriba se
queda porque sigue diciendo qué había registrado en `academia_prod`, que es el dato que
importa si alguna vez hay que volver sobre el campus del 8115. Lo que cambió es la
columna «Vendorizado»: en este repositorio ya no está ninguno de los cuatro. El motivo
de cada uno, y de dónde bajarlo si vuelve, quedó escrito en el `Dockerfile`, que es
donde lo va a buscar quien esté tocando la imagen. Los ~135 ajustes huérfanos de
`theme_academi` son un problema de `academia_prod` y siguen ahí; la Academia se instala
vacía y no los hereda.

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
| **`docs/GUIA-OPERATIVA.md`** | **CÓMO SE HACE UN CURSO.** El ciclo completo: duplicar GC-000, reclasificar, matricular por cohorte, publicar. Y lo que todavía no está resuelto. Es la guía que tres scripts citaban y que no existía |
| **`academia/README.md`** | Orden de ejecución de los scripts de provisión, los CSV de datos y las pruebas |
| `docs/TRASLADO.md` | **Historia del campus actual.** Fase F0: mover el volcado y los 10,9 GB al servidor. No describe a la Academia, que se instala vacía |
| `docs/MIGRACION.md` | **Historia del campus actual.** Fases F1–F5: imagen, carga, upgrade, paso a PostgreSQL, sitio final. La Academia no pasa por ninguna de ellas |
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

## La Academia — este repositorio

`coipo_moodle` resolvió la infraestructura. **`coipo_academia` resuelve la arquitectura de
información**, que es lo que `INSUMO_MEJORA` identifica como el problema real: 9 categorías
planas con cinco criterios mezclados, 1 campo de clasificación, 0 cohortes, 0 roles delegados y
las competencias apagadas.

**Y desde el 31-08-2026 lo hace en un sitio nuevo.** Hasta ese día la Academia iba a ser un
clon de `academia_prod`: `pg_dump` completo con sus 2.869 usuarios, sus 37 cursos y sus 179.147
archivos, más el `filedir` de 11 GB por hardlink. Ya no. Instalación limpia de 5.2.1 con
`admin/cli/install_database.php`, un puñado de cuentas nominadas de CONAF para construir y
probar, y contenido nuevo sobre la plantilla GC-000. Los 37 cursos se descartan del todo y el
campus del 8115 queda como archivo histórico mientras siga vivo.

**Consecuencia que hay que tener presente en todo lo que sigue: acá no hay datos personales,
así que la Ley 19.628 deja de condicionar este repositorio.** Sigue condicionando al campus del
8115, que es otro sistema y tiene sus propios pendientes más arriba.

**Corre en paralelo, no reemplaza — y va por delante en versión.** El deploy usa el nombre del
repositorio como `app_name`, así que son dos despliegues distintos en el mismo servidor:

| | Puerto | Base | Moodle | PHP |
|---|---|---|---|---|
| `coipo_moodle` | 8115 | `academia_prod` | 4.5.10 LTS | 8.3 |
| `coipo_academia` | 8116 | `academia_v2` | **5.2.1** | **8.4** |

**Por qué 5.2, y por qué ahora.** Decidido el 01-09-2026: se instala **5.2.1 ya**, no se
espera a 5.3 LTS. 5.2 es la rama estable y soportada de hoy —seguridad hasta el 4 de octubre
de 2027— y PHP 8.4 tiene soporte activo hasta 12-2026; 8.3 salió del suyo el 31-12-2025. La
razón que este archivo daba antes —«ensaya el salto 4.5 → 5.x en un clon desechable»— **ya no
existe**: no hay clon, no hay salto, y la Academia se instala limpia con
`install_database.php`, así que no actualiza nada. Esperar a octubre no compraba nada y costaba
dos meses de construcción detenida.

**En octubre de 2026 se sube a 5.3 LTS.** Eso ya no es una pregunta: es trabajo planificado, y
ahí sí será un upgrade de verdad, porque para entonces la Academia tendrá cursos, cohortes y
competencias propias — datos que perder. El paso 5.2 → 5.3 es corto. Está en
`docs/V2-ACADEMIA.md`, en «Al poner la Academia en servicio».

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
- **`MaxRequestWorkers` está en 6 en este repositorio, no en 50, y eso dejó de ser temporal.**
  El `CONNECTION LIMIT` es del ROL `academia`, no de la base, así que las dos instancias
  comparten un techo de 60 que el 31-08-2026 se decidió NO volver a ampliar: 50 (campus del
  8115) + 6 (esta) + 2 crones + 2 de margen = 60. Cuando la Academia abra a usuarios el reparto
  pasa a **36 / 16**, pero el 36 vive en OTRO repositorio y el orden no es negociable:
  **primero baja ese, después sube este.** Los tres estados del reparto, la regla que los
  recalcula y la consulta que detecta que se quedó corto están en `docker/apache-moodle.conf`.
- **El `moodledata` de la Academia nace vacío.** Ya no se clona `filedir` por hardlink: esos
  11 GB son el contenido de los 37 cursos descartados. Como no queda ningún inodo compartido,
  tampoco queda forma de que un error acá alcance los archivos del 8115.
- **`MOODLE_NOEMAILEVER=true` sigue siendo obligatorio**, no una preferencia, y por otra razón:
  ya no hay 2.869 buzones que proteger acá, pero las cuentas nominadas que construyen el sitio
  son direcciones institucionales reales y un Moodle a medio armar manda avisos a destajo. Se
  apaga cuando haya SMTP decidido y usuarios de verdad.

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
- **El techo de conexiones es del ROL, se reparte entre dos sitios, y el otro extremo vive en
  otro repositorio.** `rolconnlimit` del rol `academia` es **60** desde el 31-07-2026, y el
  31-08-2026 se decidió que ahí se queda: sin ampliación y sin rol nuevo. Lo que cuesta caro no
  es el número sino el ORDEN. El `MaxRequestWorkers` del campus está en `coipo_moodle` y el de
  la Academia acá, y entre los dos despliegues hay una ventana de minutos en que ambas
  configuraciones conviven: si se sube este antes de bajar aquel, esa ventana suma 68 sobre 60
  y el `FATAL: too many connections` le toca al sitio con 2.869 personas adentro, no a este.
- **El nombre de la fuente de íconos es `Font Awesome 6 Free`, no `FontAwesome`.** El segundo
  es de FontAwesome 4 y hace años que no existe en Moodle; con él, un `::before` no dibuja
  nada. Costó caro porque el ícono es lo que impide que el significado viaje solo en el color
  (WCAG 1.4.1): el CSS compilaba, la clase existía, y el incumplimiento volvía sin que se viera
  nada roto. Y hace falta `font-weight: 900`, que es lo que selecciona el juego «solid».
- **`lib/scssphp` cambió de forma en 5.2**: pasó de estar aplanado a tener subdirectorio
  `src/`. Cualquier cosa que cargue esa biblioteca a mano tiene que probar las dos rutas.
- **`admin/cli/install_database.php` es el paso 0 de la Academia**, y no es un upgrade: la base
  `academia_v2` nace vacía. El contenedor levanta igual pero el sitio no existe hasta
  ejecutarlo. Tres cosas leídas en el propio script: se niega a correr si encuentra una sola
  tabla (`clitablesexist`, y esa negativa es una red, no un estorbo); exige `--agree-license` y
  una `--adminpass` no vacía; y `--lang=es` descarga el paquete de idioma, de modo que sin
  salida a internet **no falla** — avisa con `remotedownloaderror` y deja el sitio en inglés.
  Después, `check_database_schema.php` tiene que decir `Database structure is ok.`

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

5. **El techo de conexiones se queda en 60, y desde ahora se reparte entre dos sitios.**
   `SELECT rolconnlimit FROM pg_roles WHERE rolname='academia'` → **60** desde el 31-07-2026,
   cuando el administrador de 172.31.2.40 lo subió de 20 (y de paso `max_connections` 100 → 300
   y `work_mem` 64 MB → 16 MB). **El 31-08-2026 se decidió que ahí se queda**: no se pide otra
   ampliación y **no se crea un rol de PostgreSQL nuevo para la Academia**. Esta entrada
   reemplaza a las dos anteriores —la que decía «es 20, no 60» y la que la daba por resuelta—,
   que se contradecían entre sí y hacían perder tiempo cada vez que alguien las leía.

   Lo que cambió no es el número, es que ya no sobra para nadie. El límite es del **ROL**, no de
   la base —`CREATE DATABASE` no lo lleva, por eso `db/setup_bd_v2.sql` no lo declara y hace
   bien—, así que el campus del 8115 y la Academia del 8116 se reparten los mismos 60:

   | Momento | 8115 | 8116 | crones | margen | suma |
   |---|---|---|---|---|---|
   | **Hoy** · la Academia se construye, sin usuarios de verdad | 50 | **6** | 2 | 2 | 60 |
   | La Academia abre a usuarios; la gente sigue en el campus | 36 | 16 | 2 | 6 | 60 |
   | El campus queda de solo consulta | 16 | 36 | 2 | 6 | 60 |

   La regla que genera cualquier fila: `workers(8115) + workers(8116) + 2 + 6 ≤ rolconnlimit`.
   El margen de 6 no es relleno: es lo que consumen los `docker compose exec` de `academia/cli`,
   un psql de diagnóstico y un `pg_dump`, todos con el sitio arriba. Y el **16** no es
   inventado: es el único número de este proyecto con una medición de carga detrás —con 16
   workers se midieron ~42 peticiones/s en páginas típicas y ~11/s en la de curso de 942 KB, con
   la CPU al 52 %—. El 36 del otro lado sí es una elección: le sobra a propósito, porque si
   alguna de las dos tiene que ir holgada es la que hoy tiene la gente adentro.

   **El `MaxRequestWorkers` del 8115 vive en el repositorio `coipo_moodle`, no acá**, y ahí está
   el único riesgo real: **primero baja el que baja** (alguien entra a `coipo_moodle`, lo deja en
   36, push, espera el deploy y comprueba un 200) y **después sube el que sube** (este
   repositorio, de 6 a 16). Al revés, la ventana entre los dos despliegues suma 50 + 16 + 2 = 68
   sobre 60 y el `FATAL: too many connections` le toca al sitio con 2.869 personas adentro.
   Mientras el 8115 siga en 50, este número **no se sube**: hoy no cabe.

   **Cómo se detecta que se quedó corto**, que son dos síntomas distintos y se confunden
   siempre: `FATAL: too many connections` + 500 es reparto **mal sumado** (error de
   configuración, se arregla bajando un número); páginas lentas sin nada en el log es reparto
   bien sumado y **corto** (Apache encolando, y por eso no hay error que buscar). Las consultas
   de `pg_stat_activity` que lo ven venir, con sus umbrales —`libres` bajo 6, o el `total` de una
   base tocando su `MaxRequestWorkers` en dos muestras de la misma hora—, están en el bloque
   "Concurrencia" de `.env.v2.example`.

5b. ~~`academia.conaf.cl` no existe en el DNS~~ **RESUELTO el 30-07-2026.** Resuelve a
   **172.31.2.100** (el Alteon).

5c. **11 tareas adhoc `assignfeedback_editpdf\task\convert_submission` fallando en bucle**,
   con `faildelay` acumulado de 337.920. Falta `ghostscript` en la imagen — confirmado: el
   `apt-get install` del `Dockerfile` no lo incluye. Se arregla agregándolo y desplegando.

6. Decidir sobre el correo saliente: `MOODLE_NOEMAILEVER=true` significa que nadie recibe
   notificaciones ni recuperación de contraseña, y los manuales ya lo advierten a los usuarios.
   Apagarlo requiere SMTP configurado y una decisión explícita.

7. Definir política de respaldos — **ahora para los dos sitios**. El campus del 8115 no tiene
   ninguna, ni para la base ni para los 12 GB, y de eso depende la retención del log
   (`loglifetime`): sin política de respaldos no se borra nada, y `loglifetime = 0` es la
   opción más difícil de defender bajo la Ley 19.628. Ver el apartado "Registro de eventos" de
   `mejoras.md`. Y desde el 31-08-2026 se suma la Academia, que es el caso nuevo: cuando dejó
   de ser un clon dejó de ser desechable, y su base y su `moodledata` van a ser **el único
   ejemplar** del contenido que se cree acá. Un clon perdido se rehace; esto no.

8. **No borrar todavía** el volumen `coipo_moodle_mariadb_tmp` ni los volcados: son el
   rollback. Apagar el contenedor `mariadb-tmp` es otra cosa y es seguro (1,26 GB usados con
   7,9 GB libres, así que tampoco urge).

9. Correo a Lazzos para el traspaso formal, DNS y fecha de corte.

10. Retomar el levantamiento de requisitos (áreas 3 a 8) y decidir qué se hace con la propuesta
    de rediseño de `INSUMO_MEJORA/`.

11. **Vigilancia continua, sin herramientas nuevas**: un `curl -w` a `/login/index.php` cada 5
    minutos desde el host, guardando `time_starttransfer` con fecha. La próxima vez que alguien
    diga "está lento", habrá una serie temporal para responder cuándo empezó.

12. **Lo que quedó abierto al descartar el clon (31-08-2026).** Cuatro cosas, ninguna resuelta
    y ninguna técnica salvo la última: (a) qué se hace con los 2.869 usuarios del campus —si
    alguna vez se migran, y con qué criterio—; (b) qué se hace con los 37 cursos y con el
    historial de certificaciones ya emitidas el día que el 8115 se apague; (c) por qué nombre
    se llega a la Academia, porque `academia.conaf.cl` apunta hoy al campus actual y de eso
    depende `MOODLE_WWWROOT`; (d) si la base sigue llamándose `academia_v2` cuando ya no es la
    versión 2 de nada, sino el sitio definitivo — renombrarla cuesta menos ahora que con
    contenido dentro.
