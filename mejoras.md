# Mejoras de rendimiento — academia.conaf.cl

**Estado: fase 0 ejecutada y cerrada el 30 de julio de 2026. Ver el veredicto abajo.**
Fecha del borrador: 29 de julio de 2026 · Autor técnico: Luis Monsalve

---

## VEREDICTO TRAS MEDIR — 30 de julio de 2026

> **Nota sobre los datos.** Todas las mediciones de abajo se tomaron **antes** de rehacer la
> migración con `moodle_db.sql` esa misma tarde. Es el mismo sitio con ~3 % más de contenido
> (37 cursos en vez de 36, 179.147 archivos en vez de 159.768), así que las conclusiones se
> mantienen, pero **los números absolutos habría que volver a tomarlos** con el protocolo de
> la sección Verificación si se quiere una referencia exacta del estado actual. Lo único
> re-medido después de la migración es el login: **21,6 ms de mediana**, contra los 20,1 ms
> de antes.

**El servidor no está lento.** La fase 0 se ejecutó completa contra producción. La página
más pesada del sitio —el curso 26, 72 actividades y 348 matrículas, 937 KB de HTML— carga
**entera en 0,30 s**, incluidos sus 18 subrecursos con paralelismo 6 como hace el navegador.
Un usuario que vuelve paga **0,22 s**.

| Página | Mediana | p90 | Consultas |
|---|---|---|---|
| `/login/index.php` | 20,1 ms | 21,4 | 13 |
| `/my/courses.php` | 63,2 ms | 64,6 | 36 |
| portada `/` | 96,4 ms | 105,4 | 46 |
| `/course/view.php?id=25` (112 actividades) | 216,1 ms | 229,1 | 287 |
| `/course/view.php?id=26` (72 act / 348 matr.) | 219,5 ms | 227,8 | 230 |
| `/grade/report/grader/index.php?id=26` (348 × 44) | 137,9 ms | 140,8 | 102 |
| `/grade/report/grader/index.php?id=3` (772 × 8) | 199,7 ms | 205,6 | 91 |
| `/admin/search.php` | 162,7 ms | 173,5 | 117 |

Protocolo: punto C (host → Nginx), sesión de administrador, `Accept-Encoding: gzip`,
10 repeticiones, mediana y p90 de `time_total`, una vuelta de calentamiento.

**El libro de calificaciones tarda 138 ms con 102 consultas**, no las 500–3.000 que este
documento preveía. La aritmética de "la base remota es un multiplicador" **no se sostiene**:
el RTT real medido desde el contenedor es de **0,112 ms por consulta** (mediana de 200
`SELECT 1`), así que 150 consultas son 17 ms.

### Los cuatro supuestos: tres confirmados, y el que faltaba

1. `perfdebug = 7` es apagado — **correcto**.
2. Ya hay compresión — **correcto y medido**: 23.279 → 5.222 bytes. `gzip on` en Nginx no
   aportaría nada. Además el tiempo **total** es idéntico con y sin gzip (224 vs 227 ms):
   comprimir solo mueve el tiempo al TTFB porque zlib bufferea.
3. El autovacuum ya hizo el `ANALYZE` — **correcto**: `last_autoanalyze` con fecha en las 20
   tablas, `cache_hit` **100,00 %**, base de 651 MB contra `shared_buffers` de 7 GB.
4. `themedesignermode = 0` — **correcto**.

### Lo que se aplicó y lo que se revirtió

- **A2 `langstringcache = 1`: PROBADO Y REVERTIDO.** No mejora: empeora. `/admin/search.php`
  pasó de 157,5 a 178,9 ms y volvió a 157,8 al revertir — reproducible, no ruido. La razón:
  bajo Apache, OPcache tiene los 1.569 archivos `.php` de idioma ya compilados en memoria
  compartida, así que el `include` es casi gratis; `langstringcache = 1` lo sustituye por un
  `file_get_contents` + `unserialize` de la MUC en disco, que **no** pasa por OPcache. El
  cálculo de 35,6 ms que motivaba este ítem se midió en CLI, donde OPcache está apagado: era
  engañoso.
- **B2 + B3: APLICADOS** en un solo deploy. No aceleran nada —±3,4 %, ruido— y no se esperaba
  que lo hicieran; corrigen dos condiciones reales. Ver la tabla de verificación.

### Correcciones a este documento

| Afirmación del borrador | Lo que dice la medición |
|---|---|
| `opcache.max_accelerated_files` hay que subirlo a 32531 | **Ya vale eso.** PHP redondea 20000 a `max_cached_keys` = 32.531. Ocupación real 13,77 %, `hash_restarts` 0, `oom_restarts` 0 |
| `realpath_cache_size` a 8M | **Innecesario**: usa 0,03 MB de los 4 MB actuales. Lo que sí sirve es el TTL |
| `CONNECTION LIMIT 60` del rol `academia` | **Es 20.** Verificado con `SELECT rolconnlimit FROM pg_roles`. El rol no puede subírselo: hay que pedirlo al administrador de 172.31.2.40 |
| A4: ~3.300 referencias fósiles de contenido, casi todas en `mdl_question` | **3.283 de ellas son `mdl_question.stamp` y `mdl_question_categories.stamp`**, que no son URLs sino el identificador de deduplicación de preguntas: `campus.conaf.cl+240922154501+0zOvBC`. **Pasarles `tool_replace` sería un error.** Contenido realmente renderizable afectado: ~50 filas, y **cero** en atributos `src` |
| B4 Redis: ganancia "moderada" | **4,9× con 20 peticiones simultáneas del mismo usuario** (2.165 → 443 ms con sesiones distintas). Pero **irrelevante en navegación normal**: los subrecursos que pide el navegador (`theme/image.php`, `lib/ajax/service.php`) no retienen el bloqueo — 16 ms con 6 en paralelo |
| A3: `enrol_*` / `auth_*` apuntando a directorios reales | **No hay ninguno.** `auth = email`; las tareas de `auth_ldap`, `auth_cas`, `auth_db`, `enrol_database` y `enrol_ldap` están **deshabilitadas**; `backup_auto_active = 0`; `faildelay = 0` en todas |
| A5 `debug = 0`: "el volumen puede ser alto" | **Cero avisos PHP y cero líneas `Debugging:`** en 2.723 líneas de log. No cuesta nada medible |
| 0.5: las URLs fósiles cuelgan el navegador | **No.** `campus.conaf.cl` responde 200 en 32 ms y Google Fonts en 120 ms. Y el HTML servido no referencia ninguno de los dos |

### Un dato curioso, sin acción posible

El HTML del curso 25 pesa 1.030.199 bytes con solo 3.482 nodos DOM. **726.853 de esos bytes
—el 70,6 %— son espacios, tabuladores y saltos de línea** de las plantillas Mustache. Con
gzip queda en 50 KB y el tiempo total no cambia, así que es llamativo pero no accionable:
Moodle no ofrece minificar su propio HTML.

---

## Contexto

El sitio quedó funcionando sobre PostgreSQL 17 tras la migración, pero se percibe **lento en
todo el sitio**: portada, inicio de sesión, "Mis cursos" y al entrar a los cursos. En cambio
**no** se percibe lentitud al abrir o descargar archivos.

Ese patrón es información: si abrir un PDF de varios MB va bien pero cargar una página va mal,
el cuello de botella **no** es el bind mount de los 10,9 GB ni el disco.

**Nada de esto se ha medido todavía.** Es una percepción al navegar: un punto de partida
legítimo pero insuficiente, porque sin números no se puede saber si un cambio mejoró algo.

---

## Cuatro supuestos que hay que desarmar antes de empezar

Los verifiqué contra el código de Moodle 4.5.10 y contra los archivos del tema. Conviene
ponerlos sobre la mesa al principio para que la conversación no arranque torcida.

**1. `perfdebug = 7` significa APAGADO, no "nivel 7".** En `admin/settings/development.php:53`:

```php
new admin_setting_configcheckbox('perfdebug', ..., '7', '15', '7')
```

La firma es `($nombre, $visible, $descripción, $default, $activado, $desactivado)`. O sea: 15
es activado, 7 es desactivado. **No está costando nada y no hay que bajarlo.** Al contrario:
hay que subirlo a 15 durante la medición, porque es el instrumento.

**2. Probablemente ya haya compresión.** `mod_deflate` viene habilitado por defecto en Debian,
y además Moodle comprime su propio CSS y JS por su cuenta — `lib/csslib.php:103` y `:129`
llaman a `min_enable_zlib_compression()`. Antes de proponer `gzip on` en Nginx **hay que
medirlo**: puede que no cambie nada.

**3. El autovacuum probablemente ya ejecutó el `ANALYZE`.** `dbtransfer` usa `INSERT` normales,
y PostgreSQL los cuenta en `n_mod_since_analyze`. El umbral del autovacuum es
`50 + 0,1 × filas`, así que con 1,5 millones de filas se cruza con muchísimo margen. Sigue
siendo el candidato más fuerte **si falta**, pero es lo primero que hay que comprobar en vez de
darlo por hecho.

**4. `themedesignermode` está en 0.** Es *la* causa clásica de lentitud extrema en Moodle, y
aquí está bien configurada. Nadie tiene que proponerla.

---

## Evidencia verificada

### Configuración de Moodle heredada del sitio original

| Ajuste | Valor | Debería ser | Por qué importa |
|---|---|---|---|
| **`langstringcache`** | **0** | **1** | Verificado en `lib/classes/string_manager_standard.php`: con 0, `get_revision()` devuelve `-1` y Moodle **no usa la MUC** — arma una caché que muere al terminar la petición. Resultado: vuelve a leer y ejecutar los archivos de idioma de cada componente **en cada petición**. El paquete `es` tiene 1.570 archivos |
| `debug` | 15 (NORMAL) | 0 | Construye y escribe al log de Apache cada aviso y cada llamada obsoleta, aunque `debugdisplay=0` no los muestre. En un sitio recién actualizado 4.4→4.5 con cuatro plugins de terceros, el volumen puede ser alto |
| `perfdebug` | 7 = **apagado** | 15 para medir, luego 7 | Ver corrección 1 |
| `themedesignermode` | 0 | 0 ✔ | Correcto |
| `cachejs` · `cachetemplates` | 1 · 1 | ✔ | Correctos |
| `enablestats` | 0 | ✔ | Correcto |

**Importante**: el informe de rendimiento del sitio (`report/performance`) solo revisa seis
comprobaciones —`designermode`, `cachejs`, `debugging`, `backups`, `stats`, `dbschema`— y
**no revisa `langstringcache`**. Que salga verde no significa que la caché esté bien.

### Infraestructura

| Hecho | Estado |
|---|---|
| Caché de aplicación (MUC) y sesiones | **Archivos**, en `moodledata/muc`, `cache/` y `sessions/` |
| Redis / Memcached | **No hay ninguno.** `cache/stores/redis` viene en el core; la **extensión `redis` de PHP no está en la imagen** |
| PostgreSQL | En **otra máquina** (172.31.2.40), compartida con otras apps. Cada consulta cruza la red |
| Estadísticas del planificador | **Sin verificar.** Ver corrección 3 |
| `mariadb-tmp` | **Sigue encendido**, con `innodb_buffer_pool_size=2G` |
| Archivos `.php` en la imagen | 18.254 · `opcache.max_accelerated_files=20000`: alcanza, con poco margen |
| SCSS del tema | **1,7 MB en 138 archivos** en `theme_boost_magnific` |
| **Google Fonts en el tema** | `_editor/model/_assets/style.scss:1` importa **nueve familias** desde `fonts.googleapis.com` |
| Apache | `mod_php` con MPM prefork. El default de Debian es **`MaxRequestWorkers 150`** |
| Nginx del host | Sin `gzip` explícito, sin caché de estáticos, sin `keepalive` al upstream |

---

## Fase 0 — Medir. No cambia nada

### 0.1 El estado del planificador de PostgreSQL — la consulta más importante del documento

El rol `academia` es dueño de sus tablas, así que puede consultar esto **sin depender del
administrador de 172.31.2.40**. Todo es de lectura.

```sql
SELECT relname, n_live_tup, n_mod_since_analyze,
       last_analyze, last_autoanalyze, seq_scan, seq_tup_read, idx_scan
FROM pg_stat_user_tables
ORDER BY seq_tup_read DESC NULLS LAST LIMIT 20;
```

- **`last_autoanalyze` con fecha** → el autovacuum ya hizo su trabajo. **Táchenlo de la lista y
  no lo vuelvan a proponer.**
- **`NULL` en las tablas grandes** → es el sospechoso número uno y explica solo toda la
  lentitud. Sin histogramas, el planificador usa constantes por defecto y cae en recorridos
  secuenciales de `mdl_files` (178.833 filas), `mdl_context` y `mdl_role_assignments`. Una
  consulta de 0,5 ms pasa a 200 ms, multiplicado por 100–200 consultas por página, cruzando la
  red.
- Señal independiente y muy útil: `seq_tup_read` altísimo en esas tablas es evidencia directa
  de planes malos, exista o no el `ANALYZE`.

### 0.2 Medir el mismo URL en cuatro puntos

Aislar cada tramo es lo que evita optimizar lo que no corresponde:

```bash
FMT='ttfb %{time_starttransfer}  total %{time_total}  bytes %{size_download}\n'

# A) dentro del contenedor: Apache + PHP + PostgreSQL
docker compose exec app curl -o /dev/null -s -w "$FMT" http://localhost/login/index.php
# B) desde el host al contenedor
curl -o /dev/null -s -w "$FMT" http://127.0.0.1:8115/login/index.php
# C) desde el host por Nginx
curl -o /dev/null -s -w "$FMT" -H 'Host: academia.conaf.cl' http://127.0.0.1/login/index.php
# D) desde una estación de la red
curl -o /dev/null -s -w "$FMT" http://academia.conaf.cl/login/index.php
```

`B` vs `C` = lo que agrega Nginx (debería ser < 2 ms). `C` vs `D` = la red del usuario. `A` es
el techo de lo que este plan puede arreglar.

**Diez repeticiones y quedarse con mediana y p90**, nunca el promedio: una medición de 8 s
arruina el promedio y esconde la mediana.

### 0.3 Ver dónde se va el tiempo dentro de Moodle

```bash
docker compose exec -u www-data app php admin/cli/cfg.php --name=perfdebug --set=15
docker compose exec -u www-data app php admin/cli/purge_caches.php
```

Al pie de cada página aparecen tiempo de generación, **número de consultas**, memoria y CPU:

- **> 250 consultas y mucho tiempo** → el cuello es la base o los planes.
- **< 80 consultas y mucho tiempo** → el cuello es PHP: cadenas de idioma, plantillas, tema.
- **Tiempo bajo en el pie pero la página se siente lenta** → el problema está en el cliente
  (ver 0.5).

### 0.4 Host, Apache y OPcache

```bash
free -h; vmstat 1 5; uptime; nproc      # ¿swap en uso? ¿carga > nproc?
df -h /opt /var/lib/docker
docker stats --no-stream               # ¿cuánto se lleva mariadb-tmp?
docker compose exec app curl -s http://localhost/server-status?auto | grep -E 'Workers|ReqPerSec'
```

`BusyWorkers` es el número que decide si vale la pena php-fpm más adelante. Si nunca pasa de
5–10, **la concurrencia no es el problema**.

Y OPcache **por web, no por CLI** (`opcache.enable_cli=0`, así que `php -r` no sirve):
Administración del sitio → Servidor → Información de PHP, sección `Zend OPcache`. Buscar
`oom_restarts` y `hash_restarts`: si son > 0, el sitio está recompilando PHP en caliente.

### 0.5 El lado del cliente — la causa que ningún número del servidor muestra

Esto puede ser, perfectamente, la explicación completa con un servidor sano.

**Las URLs fósiles.** Quedan ~3.300 referencias de contenido a `campus.conaf.cl` y a
`127.0.0.1:8080`. Si esos hosts **resuelven pero están filtrados**, el navegador espera el
timeout TCP —decenas de segundos— por cada imagen. Una página con tres imágenes fósiles se
siente muerta, y el pie de `perfdebug` dirá "0,4 s".

```bash
curl -m 5 -o /dev/null -s -w 'campus %{time_total} %{http_code}\n' http://campus.conaf.cl/
```

**Las fuentes externas del tema.** Verificado: `theme_boost_magnific` importa **nueve familias
de Google Fonts** en `_editor/model/_assets/style.scss`. Está bajo `_editor/`, lo que sugiere
que solo aplica al constructor de páginas del tema — pero si alguna portada o página se armó
con esa herramienta, ese CSS entra y el navegador se bloquea contra `fonts.googleapis.com`. En
una red corporativa con proxy o sin salida, son segundos de render bloqueado por página.

**Cómo se mide**: DevTools → Network, sin caché, ordenado por tiempo. Si el TTFB del documento
HTML es bajo y las barras largas son peticiones a dominios ajenos, el servidor no tiene nada
que ver.

### 0.6 El cron y las tareas — sospechoso subestimado

El contenedor `cron` corre `admin/cli/cron.php` **cada minuto en la misma máquina que Apache**.
Una tarea colgada 60 s en un timeout, o un respaldo automático de 35 cursos en horario
laboral, se ve exactamente como "el sitio está lento".

```sql
SELECT classname, count(*), round(avg(timeend-timestart)) AS seg_prom, max(timeend-timestart) AS seg_max
FROM mdl_task_log GROUP BY 1 ORDER BY seg_prom DESC LIMIT 20;

SELECT name, value FROM mdl_config
WHERE name IN ('auth','enrol_plugins_enabled','backup_auto_active','backup_auto_starttime','enableanalytics');
```

Tres cosas concretas a buscar, las tres ya señaladas como riesgo en `docs/MIGRACION.md`:

- `enrol_database` / `enrol_ldap` / `auth_ldap` activos apuntando a directorios reales de CONAF
  → **timeouts en cada login y en cada corrida de cron**.
- `backup_auto_active=1` con horario heredado → respaldos de 35 cursos y 10,9 GB en horario
  laboral.
- Analítica de Moodle activa → tareas de predicción carísimas.

### Entregable de la fase 0

| URL | TTFB mediana (C) | p90 | Consultas | Tiempo servidor |
|---|---|---|---|---|
| `/login/index.php` | | | | |
| `/health` | | | | |
| `/` portada | | | | |
| `/my/` | | | | |
| `/course/view.php?id=<el más grande>` | | | | |
| `/grade/report/grader/index.php?id=<ese>` | | | | |
| `/admin/search.php` | | | | |
| `/pluginfile.php/...` un PDF | | | | |

Más: veredicto de estadísticas, `BusyWorkers` máximo, RAM libre, y si hay compresión.

---

## Bloque A — Victorias inmediatas

Ordenadas por impacto/riesgo. **Una a la vez, midiendo entre cada una.**

### A1 · `ANALYZE` sobre `academia_prod` — solo si la fase 0 dice que falta

```bash
psql -h 172.31.2.40 -U academia -d academia_prod -c "ANALYZE;"
# Después, en ventana de baja carga (VACUUM no bloquea lecturas ni escrituras):
psql -h 172.31.2.40 -U academia -d academia_prod -c "VACUUM (ANALYZE, VERBOSE);"
```

Lo ejecuta el rol `academia`, dueño de la base: **no toca las otras apps**. Consume E/S en una
máquina compartida, así que conviene avisar a quien la administra.

Hay un segundo efecto real aunque el autovacuum ya haya analizado: la carga fila por fila deja
el mapa de visibilidad vacío, y un `VACUUM` lo reconstruye habilitando *index-only scans*. Es
menor que el efecto de las estadísticas, pero va en el mismo comando.

### A2 · `langstringcache = 1`

```bash
docker compose exec -u www-data app php admin/cli/cfg.php --name=langstringcache --set=1
docker compose exec -u www-data app php admin/cli/purge_caches.php
```

Riesgo casi nulo: es el valor por defecto de Moodle. Es el ajuste con mejor relación
impacto/riesgo de toda la configuración heredada, y el informe de rendimiento **no lo detecta**.

Mirar especialmente `/admin/search.php` y la portada, que tocan decenas de componentes.

### A3 · Domar el cron, la autenticación y los respaldos automáticos

Según lo que muestre 0.6. **Es el ítem que hay que discutir con más cuidado**: desactivar
`enrol_*` o `auth_*` cambia quién puede entrar y quién queda matriculado. **No es una decisión
de rendimiento, es de servicio.** Documentar qué se apaga y por qué.

### A4 · Reescribir las URLs fósiles y revisar las fuentes del tema

El procedimiento de `tool_replace` ya está en `docs/MIGRACION.md`, con respaldo previo y en
mantención. Es la causa de percepción de lentitud más probable que **no aparece en ninguna
métrica del servidor**.

Si el `@import` de Google Fonts entra en páginas reales, la salida limpia es servir esas
fuentes localmente —el tema ya trae sus propias fuentes— o quitar el import. Eso último toca el
SCSS vendorizado, así que primero hay que confirmar con DevTools que se está usando.

### A5 · `debug = 0`

```bash
docker compose exec -u www-data app php admin/cli/cfg.php --name=debug --set=0
```

Hacerlo **después** de la fase 0: durante el diagnóstico los avisos sirven. Antes de cambiarlo,
mirar `docker compose logs app | tail -50` para ver de qué se queja Moodle — puede haber ahí un
problema real que valga la pena arreglar.

### A6 · Pre-compilar el CSS del tema después de cada purga y de cada deploy

```bash
docker compose exec -u www-data app php admin/cli/build_theme_css.php -t=boost_magnific -v
```

El tema trae **1,7 MB de SCSS en 138 archivos**. Con `themedesignermode=0` se compila una vez y
se cachea, pero **la primera petición después de una purga paga la compilación completa** con el
compilador SCSS de PHP: decenas de segundos. Y si varias peticiones llegan a la vez, todas
compilan en paralelo y el sitio se ve congelado.

Cualquiera que haya purgado cachés a mediodía "para probar" produjo exactamente el síntoma
reportado.

**Detalle que importa**: el CSS compilado vive en `$CFG->localcachedir`, y en este compose `app`
y `cron` tienen **volúmenes separados** (`localcache_app` / `localcache_cron`). Hay que
ejecutarlo **en el contenedor `app`**; en `cron` no sirve de nada para el sitio web.

### A7 · Apagar `mariadb-tmp`

```bash
docker stop coipo_moodle-mariadb-tmp-1
free -h
```

**El volumen NO se toca.** Es el rollback, y para volver atrás hay que arrancar el contenedor de
todos modos: encendido no aporta nada al rollback.

**Honestidad sobre el impacto**: si 0.4 muestra RAM de sobra y sin swap, esto **no va a mejorar
nada perceptible**. Es riesgo cero e higiene, no una victoria. Si el host está en swap, pasa a
ser de los primeros en importancia.

Advertencia: solo `docker stop`. **Nunca `docker compose down -v` ni `docker volume prune`**, o
se va el rollback.

---

## Bloque B — Estructurales

Reconstruir la imagen no es un obstáculo: el despliegue automático está probado. Por eso
conviene **agrupar en un solo deploy** todo lo que toca la imagen.

### B1 · Nginx: compresión, caché de estáticos, keepalive

**Primero medir** (corrección 2):

```bash
curl -s -D- -o /dev/null -H 'Accept-Encoding: gzip' -H 'Host: academia.conaf.cl' \
     http://127.0.0.1/login/index.php | grep -i -E 'content-encoding|content-length'
```

Si sale `Content-Encoding: gzip`, agregar `gzip on` **no cambia nada** y hay que decirlo para
que no se proponga como victoria. Si no sale, entonces sí — y con `gzip_proxied any`, que sin
él Nginx no comprime respuestas proxeadas.

**Caché de estáticos revisionados.** Moodle sirve CSS y JS a través de PHP con URLs versionadas
por `themerev` y `jsrev`, así que son seguras de cachear en el proxy. Un `proxy_cache` con
**`proxy_cache_lock on`** es además la segunda mitad de A6: si veinte usuarios piden el CSS tras
una purga, solo uno llega a Apache y los demás esperan ese resultado, en lugar de disparar
veinte compilaciones SCSS simultáneas.

**Este es el ítem de mayor riesgo del documento y hay que decirlo claro**: si el `location`
captura por error un endpoint que depende del usuario, Nginx serviría el contenido de una
persona a otra. Reglas no negociables:

- Lista blanca explícita de endpoints. **Nunca** un patrón por extensión ni `location /`.
- **Jamás** cachear `/pluginfile.php`, `/draftfile.php`, `/login/`, `/user/`, `/webservice/`.
- **No agregar `proxy_ignore_headers Set-Cookie`**: por defecto Nginx no cachea respuestas con
  cookie, y así debe quedar.
- Probar en el servidor con dos sesiones distintas antes de dar por bueno.

**Keepalive al upstream**: hoy cada petición abre una conexión TCP nueva al contenedor. Un
`upstream` con `keepalive 32` y `proxy_set_header Connection ""` lo evita. Ganancia pequeña,
gratis.

**Cuando llegue el certificado**: HTTP/2 es una mejora real de latencia percibida, mayor que
varios ítems de este documento, porque HTTP/1.1 serializa tras 6 conexiones por host. Recordar
que hay que cambiar `MOODLE_WWWROOT` a `https://` **y** `MOODLE_SSLPROXY=true` a la vez.

### B2 · Afinar `docker/php.ini` — un solo deploy

| Ajuste | Hoy | Propuesto | Por qué |
|---|---|---|---|
| `realpath_cache_size` | defecto 4M | **8M** | Moodle hace miles de `include`/`file_exists` por petición sobre 18.254 archivos. Es uno de los ajustes con mejor retorno en Moodle |
| `realpath_cache_ttl` | defecto 120 | **600** | El código solo cambia cuando se despliega una imagen nueva |
| `opcache.max_accelerated_files` | 20000 | **32531** (primo) | OPcache redondea al primo siguiente. Si se llena hay `hash_restarts` y el sitio recompila en caliente. Confirmar en Información de PHP antes y después |
| `opcache.interned_strings_buffer` | 16 | **32** | Al agotarse, las cadenas dejan de internarse y sube la memoria por petición |
| `opcache.memory_consumption` | 256 | 256 | Suficiente. Solo subir si `oom_restarts > 0` |
| `zend.assertions` | defecto | **-1** | La imagen oficial no instala ningún `php.ini`, así que rigen los defaults compilados y las aserciones se ejecutan |
| `expose_php` | On | **Off** | Higiene, coherente con `ServerTokens Prod` |

`opcache.validate_timestamps=0` **queda fuera a propósito**: ahorra un `stat()` por archivo,
pero si alguien edita un archivo dentro del contenedor para depurar, el cambio no tendrá efecto
y va a perder una tarde buscando por qué.

### B3 · Acotar `MaxRequestWorkers` de Apache — protege, no acelera

El default de Debian en prefork es **150**. Cada hijo con `mod_php` y Moodle cargado ronda
60–120 MB, y `memory_limit` permite hasta 512 MB por petición. En el peor caso, 150 hijos piden
mucho más RAM de la que tiene el servidor → **OOM o swap**, y en swap el servidor entero se
arrodilla.

```apache
<IfModule mpm_prefork_module>
    StartServers            4
    MinSpareServers         4
    MaxSpareServers        10
    MaxRequestWorkers      <N>
    MaxConnectionsPerChild 1000
</IfModule>
```

```
N = (RAM_total − RAM de Nginx y Docker − 1 GB de margen) / RSS medio por hijo
```

Si `BusyWorkers` nunca pasa de 10, `N=40` es holgadísimo y protege sin quitar nada. Y hay un
segundo techo: si algún día se activara `dbpersist`, `N` debe quedar **por debajo de 55** (el
`CONNECTION LIMIT 60` menos las conexiones del `cron`).

### B4 · Redis para las sesiones

**Qué gana exactamente.** Hoy las sesiones son archivos, y ahí no solo va `$SESSION` y `$USER`:
**las 12 definiciones de caché `MODE_SESSION` de Moodle viven dentro del archivo de sesión**
(`navigation_cache`, `coursecat`, `grade_categories`, `calendar_categories`…). Ese archivo se
serializa y deserializa completo en cada petición, con `flock`, y el bloqueo se mantiene
durante toda la petición.

**Honestidad sobre la magnitud**: `/opt/moodledata` es ext4 local sobre LVM, y un bind mount de
Docker no añade ninguna capa — mismo kernel, mismo sistema de archivos. Las sesiones de archivo
**no están sobre nada lento**, y el page cache absorbe la mayoría. Con la concurrencia real de
este sitio la ganancia es **moderada, no espectacular**. Es una mejora de arquitectura y de
concurrencia, no la cura de la lentitud.

**Por qué Redis y no Memcached**: no porque memcached esté obsoleto —su manejador sigue vigente
en 4.5—, sino porque **Memcached expulsa datos por LRU cuando se llena**, y una sesión expulsada
es un usuario deslogueado a media clase sin ningún error visible. Redis con
`maxmemory-policy noeviction` falla de forma detectable, que en un servicio público es
infinitamente preferible.

Cambios: extensión `redis` en el `Dockerfile` (con `yes '' | pecl install redis`, porque el
instalador hace preguntas interactivas y sin eso el build se cuelga esperando un stdin que no
existe), servicio `redis` en el compose **sin puerto publicado**, y en `docker/config.php`:

```php
if (conaf_env('MOODLE_REDIS_HOST') !== null) {
    $CFG->session_handler_class = '\core\session\redis';
    $CFG->session_redis_host    = conaf_env('MOODLE_REDIS_HOST');
    $CFG->session_redis_port    = (int) conaf_env('MOODLE_REDIS_PORT', '6379');
    $CFG->session_redis_prefix  = conaf_env('MOODLE_REDIS_PREFIX', 'academia_sess_');
    $CFG->session_redis_acquire_lock_timeout = 120;
    // Debe ser >= max_execution_time (300) o una petición larga pierde su propio bloqueo.
    $CFG->session_redis_lock_expire = 7200;
}
```

**Riesgo — el más alto del bloque junto con B1:**

- **Si Redis cae, el sitio cae.** Es un componente nuevo en la ruta crítica.
- **Al cambiar de manejador, todas las sesiones actuales se invalidan**: todos quedan
  desconectados. Hacerlo en ventana.
- **Sin persistencia, reiniciar Redis desloguea a todo el mundo.** Es una decisión consciente.
- `noeviction` con la memoria agotada = escrituras fallidas = login roto. Hay que **vigilar
  `used_memory`** y empezar solo con sesiones, que son pequeñas.

### B5 · Redis para la MUC — solo si hay evidencia, y después de B4

Verificado en `lib/db/caches.php`: de las definiciones de caché, las `MODE_REQUEST` **no se
pueden mover** (viven en memoria PHP), las `MODE_SESSION` se resuelven con B4, y las que llevan
`canuseloc­alstore => true` (`string`, `langmenu`, `htmlpurifier`, `h5p_libraries`…) **conviene
dejarlas en archivos**: esa bandera existe precisamente porque son datos grandes leídos muchas
veces por petición, y sacarlos a la red los puede hacer **más lentos**.

Candidatas reales: `config`, `databasemeta`, `coursemodinfo`, `capabilities`, `groupdata`.

**Honestidad**: esto es **un solo nodo**, con el store de archivos en ext4 local y el page cache
teniéndolo casi todo en RAM. La ganancia es **pequeña**, y cada `get` pasa a costar un ida y
vuelta. El valor real de la MUC en Redis aparece con varios nodos web, que no es el caso.
**Hacerlo solo si el perfil muestra tiempo apreciable en E/S de `moodledata/cache`.**

### B6 · php-fpm — criterio, no recomendación

| | mod_php + prefork (hoy) | php-fpm |
|---|---|---|
| Memoria | Cada hijo lleva PHP embebido, incluso para servir un PNG | Solo los workers de FPM cargan PHP |
| Concurrencia | Un solo techo para web y PHP | Dos techos independientes |
| Diagnóstico | — | `slowlog`: **registro de peticiones lentas** |

**Criterio**: si `BusyWorkers` máximo < 20 y sobra RAM, **no vale la pena hoy**. Si el problema
es la base de datos, php-fpm **no arregla nada**: solo cambia qué recurso se satura.

**Y hay una trampa concreta en este repositorio**: `docker/config.php` y `docker/health.php`
leen toda su configuración con `getenv()`. Con php-fpm, **las variables de entorno del
contenedor no llegan a los workers** salvo que el pool declare `clear_env = no`. El respaldo con
`$_SERVER` que ya tiene el código tampoco alcanzaría. Sin eso, el sitio arranca sin base de
datos y el síntoma será "Moodle no encuentra la configuración", que no apunta a la causa.

**Recomendación: no hacerlo ahora.** Hacer B3, que captura la parte de seguridad de memoria con
cinco líneas y sin cambiar arquitectura. Condición de disparo explícita para revisarlo:
*cuando `BusyWorkers` llegue sostenidamente al 80 % de `MaxRequestWorkers`.*

### B7 · `dbpersist` — el criterio real, para desarmar el mito

Lo que dice el propio `config-dist.php` de Moodle: *"Set to 'false' for the most stable
setting, 'true' can improve performance sometimes"*. Y en
`lib/dml/pgsql_native_moodle_database.php`, cuando `dbpersist` está activo Moodle ejecuta un
`CLOSE ALL` al conectar, con este comentario en el código: *"When using persistent connections,
the cursors remain open and 'get in the way' of future connections"*. O sea que el propio
Moodle reconoce que hereda estado sucio entre peticiones y lo limpia a mano.

Tres razones para no activarlo:

1. **Lo que ahorra** es el connect + autenticación SCRAM, una vez por página: en una LAN, 3–10
   ms. Si la página tarda 1.200 ms, es ruido. **No ahorra ni una sola consulta.**
2. **El techo duro**: `CONNECTION LIMIT 60` contra `MaxRequestWorkers 150`. Con conexiones
   persistentes cada hijo se queda una para sí → `FATAL: too many connections` → **sitio
   caído**, no lento.
3. **Estado heredado**: cursores, tablas temporales y advisory locks que sobreviven a la
   petición.

**Veredicto: no activarlo.** Y **PgBouncer tampoco es la salida**: en modo *transaction* rompe
las tablas temporales y los advisory locks que Moodle usa como fábrica de bloqueos por defecto
con PostgreSQL; en modo *session* no ahorra nada respecto de `dbpersist`.

### B8 · Configuración del PostgreSQL compartido — evidencia, no propuesta

Con los `SHOW shared_buffers / work_mem / effective_cache_size / random_page_cost` de la fase 0,
si aparecen valores por defecto de instalación (`shared_buffers` en 128 MB,
`random_page_cost` en 4.0 sobre SSD) hay margen grande de mejora.

Pero es una máquina **compartida y administrada por otra persona**, y `shared_buffers` exige
reinicio. Esto **no es un cambio a proponer, es evidencia a entregar**: llevar la salida de las
consultas y pedir una revisión conjunta. Y una petición que rinde muchísimo si la aceptan:
activar `pg_stat_statements` o `log_min_duration_statement = 500ms`, para ver **qué** consultas
son lentas en lugar de deducirlo.

---

## El coste de tener la base en otra máquina

| Página | Consultas típicas |
|---|---|
| `/login/index.php` | 15–30 |
| Portada | 60–120 |
| `/my/` | 100–250 |
| Curso con 40 actividades | 80–200 |
| Libro de calificaciones | 500–3.000+ |

La aritmética que importa: con RTT de 0,3 ms, 150 consultas son 45 ms de latencia pura —
molesto, no dramático. Con RTT de 2 ms son 300 ms, y un libro de calificaciones con 2.000
consultas son **4 segundos solo de ida y vuelta**.

**La base remota es un multiplicador, no un sumando.** Por eso el orden del plan es este:
primero se arregla el **coste por consulta** (A1), y solo después se piensa en reducir el
**número** de consultas. `dbpersist` no toca ninguno de los dos.

Traer PostgreSQL a la misma máquina **no está sobre la mesa**: contradice la decisión 4 del
`CLAUDE.md` y el estándar de infraestructura CONAF, y crea otro motor que respaldar y parchar.

---

## Registro de eventos

**La parte honesta: esto casi seguro NO es la causa.** Con `buffersize=50`, Moodle acumula los
eventos y los escribe al final de la petición en un `INSERT` múltiple: **una escritura por
página**, de pocos milisegundos. Comparado con 150 consultas de lectura, es ruido. Que la tabla
sea la más grande del sitio no la hace lenta de escribir.

| Opción | Veredicto |
|---|---|
| Desactivar `logstore_standard` | **No.** Ahorra milisegundos y destruye la auditoría, los informes, la participación y la finalización de actividad |
| `logguests = 0` | **Sí**, si hay tráfico anónimo. No pierde nada de la traza de usuarios autenticados |
| Subir `buffersize` | **Dejar en 50.** Subirlo cambia riesgo de auditoría por milisegundos: lo que está en buffer se pierde si el proceso muere |
| Retención (`loglifetime`) | **Sí, pero como decisión de gobierno de datos** — ver abajo |

**La retención, planteada como corresponde.** El argumento fuerte no es el rendimiento: es la
**Ley 19.628**. Son datos personales de 2.798 funcionarios, y el principio de finalidad exige no
conservarlos indefinidamente. `loglifetime = 0` (nunca borrar) es, en rigor, la opción **más
difícil de defender** desde protección de datos, no la más segura.

Propuesta para la reunión, a decidir por quien tenga la competencia:

1. Definir el plazo con la contraparte que responde por la auditoría del servicio.
2. **Antes de la primera purga**, exportar lo que se va a borrar a un archivo comprimido fuera
   de la base, con la misma política que el resto de los respaldos.
3. Recién entonces fijar `loglifetime`, y ejecutar la primera purga **en ventana de mantención**.
4. Esto depende de la política de respaldos, que todavía no existe: **sin ella no se borra nada.**

---

## Qué NO hacer

| Propuesta popular | Por qué acá es inútil o peligrosa |
|---|---|
| **`VACUUM FULL`** | Toma `ACCESS EXCLUSIVE`: **el sitio se detiene** mientras dura, reescribe la tabla completa y exige el doble de espacio en un disco ajeno. Y no aporta: estas tablas vienen de inserciones frescas, **no tienen bloat**. Lo que falta es `ANALYZE`, que es otra cosa |
| **`REINDEX` de toda la base** | Los índices se acaban de crear. Sin evidencia de fragmentación es tiempo de bloqueo regalado |
| **Activar `dbpersist`** | 150 hijos contra un límite de 60 conexiones = sitio caído. Y no ahorra ni una consulta. Ver B7 |
| **Subir `memory_limit` a 1 GB** | Es **por petición**: no acelera nada, solo eleva el techo de daño y acerca el OOM. 512M es correcto para Moodle |
| **Activar el JIT de OPcache** | En cargas dominadas por E/S y base de datos la ganancia medida es del 0–5 %, y el JIT ha tenido errores propios |
| **`yuicomboloading = 0`** | Está en 1 y así debe quedar: desactivarlo multiplica el número de peticiones HTTP |
| **`cachejs = 0` o `themedesignermode = 1`** | Ya están correctos. Cambiarlos hace el sitio **dramáticamente** más lento |
| **Purgar cachés "para que ande mejor"** | Hace lo contrario: obliga a recompilar 1,7 MB de SCSS y a repoblar la MUC. Si hay que purgar, encadenar `build_theme_css.php` (A6) |
| **Mover `sessions` o `cachedir` a tmpfs** | `sessions` en tmpfs = todos deslogueados en cada reinicio. Y `cachedir` **tiene que ser compartido entre `app` y `cron`**: si cada uno tuviera el suyo, la invalidación de la MUC se rompe y aparecen datos viejos de forma intermitente e imposible de depurar |
| **"Docker hace lento el disco"** | Falso acá: un bind mount en Linux no añade ninguna capa, es el mismo kernel sobre el mismo ext4 |
| **Varnish, CDN o caché de página completa** | Todo el contenido de un LMS es autenticado y personalizado. Serviría el escritorio de un funcionario a otro |
| **Agregar gzip sin verificar** | Probablemente ya esté comprimiendo. Ver corrección 2 |
| **Bajar `perfdebug` "porque 7 es alto"** | **7 es el valor de apagado.** Ver corrección 1 |
| **Subir la frecuencia del cron o correr varios en paralelo** | `cron.php` toma un bloqueo: las copias extra no hacen trabajo, solo consumen conexiones del límite de 60 |
| **Borrar el volumen `mariadb_tmp` o el volcado** | Es el rollback. Apagar el contenedor es una cosa; borrar el volumen es otra, y todavía no |
| **Aplicar varias fases a la vez** | Sin medición intermedia no se sabe qué funcionó ni qué revertir |

---

## Verificación

**"Mejoró" significa dos números comparables y la fecha en que se tomaron.**

Protocolo, idéntico antes y después de cada cambio:

1. **Un cambio a la vez.** La única excepción razonable es el lote de imagen (B2 + B3), porque
   comparten el coste del deploy; ahí se acepta que la atribución interna queda difusa.
2. Mismo momento del día, mismo estado de caché.
3. **10 repeticiones, mediana y p90.** Nunca el promedio.
4. **Umbral declarado antes de medir**: se conserva el cambio si la mediana mejora ≥ 10 % o el
   p90 mejora ≥ 20 %. Si no, se revierte. Sin umbral fijado de antemano, cualquier ruido se
   interpreta como éxito.

### Resultados reales — 30 de julio de 2026

Referencia base R0 tomada a las 13:01. Todas las medianas en ms, protocolo idéntico.

| # | Cambio | Estado | Evidencia | Cómo se revierte |
|---|---|---|---|---|
| A1 | `ANALYZE` | **NO HACER** | `last_autoanalyze` con fecha en las 20 tablas, `cache_hit` 100,00 %, 1.859 lecturas de disco en toda la vida de la base | no aplica |
| A2 | `langstringcache=1` | **PROBADO Y REVERTIDO** | `/admin/search.php` 157,5 → **178,9** → 157,8 al revertir. Curso 26: 216,7 → 217,7. Ninguna página alcanzó el umbral | ya revertido (`--set=0` + purge) |
| A3 | cron / auth / respaldos | **NADA QUE HACER** | `auth=email`; `auth_ldap`/`auth_cas`/`auth_db`/`enrol_database`/`enrol_ldap` deshabilitadas; `backup_auto_active=0`; `faildelay=0` | no aplica |
| A4 | URLs fósiles | **NO CON `tool_replace`** | 3.283 de 3.378 son `stamp`, que no son URLs. Quedan 5 enlaces del tema activo y 3 de `user_info_field`, a mano | no aplica |
| A5 | `debug=0` | **irrelevante** | 0 avisos PHP en 2.723 líneas de log. Correcto por higiene, no acelera | `--set=15` |
| A6 | pre-compilar CSS | **procedimiento adoptado** | `build_theme_css.php` tarda **4,1 s**, no decenas. Se ejecuta tras cada purga y cada deploy | no aplica |
| A7 | apagar `mariadb-tmp` | **innecesario** | 1,26 GB usados con 7,9 GB libres y swap en 1,3 MB. Higiene, no victoria | `docker start` |
| B1 | Nginx | **NADA QUE HACER** | gzip ya existe (23.279 → 5.222 B); estáticos ya llevan `max-age=7776000, immutable`; Nginx no agrega latencia (B 20,1 vs C 18,4 ms) | — |
| B2 | `php.ini` | **APLICADO** · no acelera | `interned_strings_buffer` 16 → 32: estaba AGOTADO (16,0/16,0 MB, libre 0,0). Ahora **12,9 MB libres**. `realpath_cache_ttl` 120 → 600: entradas vivas 231 → **549**. Latencia sin cambio (±3,4 %) | revertir commit + deploy |
| B3 | `MaxRequestWorkers` | **APLICADO** · protege | 150 → 16. **Verificado con 40 peticiones simultáneas: las 40 devolvieron 200.** Antes, las que pasaran de 20 habrían recibido `FATAL: too many connections` | revertir commit + deploy |
| B4 | Redis sesiones | **no ahora** | 4,9× solo con 20 peticiones paralelas del mismo usuario. En navegación real los subrecursos no retienen el bloqueo | — |
| B5 | Redis MUC | **no** | Un solo nodo, `cache_hit` 100 %, base de 651 MB | — |
| B6 | php-fpm | **diferir** | `BusyWorkers` máximo observado: **1** | — |
| B7 | `dbpersist` | **NO HACER** | Ahorraría 7,24 ms de conexión por página sobre 220. Y con `CONNECTION LIMIT 20` tumbaría el sitio | — |

**Efecto medido del bloqueo de sesión de archivo** (20 peticiones simultáneas al curso 26):

| Escenario | Mediana | p90 |
|---|---|---|
| Misma sesión (bloqueo `flock`) | 2.165 ms | 3.845 |
| 20 sesiones distintas | 443 ms | 615 |
| Sin sesión (`/login`) | 39 ms | 47 |
| Una sola, secuencial | 226 ms | — |

Y después de **cada** deploy, las verificaciones funcionales del `CLAUDE.md`, que no son
negociables aunque el cambio sea "solo de rendimiento": 200 en el login, `/health` en `ok`,
`dbtype` en `pgsql`, entrar al sitio, **abrir un PDF**, y subir un archivo y encontrarlo en el
host.

**Vigilancia continua, sin herramientas nuevas.** Un `curl -w` a `/login/index.php` cada 5
minutos desde el host, guardando `time_starttransfer` con fecha. Así, la próxima vez que
alguien diga "está lento", habrá una serie temporal para responder **cuándo** empezó y qué se
había cambiado ese día. Eso vale más que cualquier optimización de este documento.

---

## Resumen para la reunión

| Prioridad | Ítem | Probabilidad de que mueva la aguja |
|---|---|---|
| 0 | **Fase de medición completa** | **Obligatoria.** Sin esto lo demás son creencias |
| 1 | `ANALYZE` si falta | **Alta si falta; nula si el autovacuum ya lo hizo.** Se decide con una consulta de 30 segundos |
| 2 | `langstringcache = 1` | **Media-alta y segura.** Verificado en el código: hoy no hay caché de cadenas |
| 3 | Cron, `auth_*`/`enrol_*`, respaldos automáticos | **Alta si algo apunta a un host inalcanzable** |
| 4 | URLs fósiles + fuentes externas del tema | **Alta sobre la percepción**, nula sobre el servidor |
| 5 | `debug = 0` | Media |
| 6 | Pre-compilar CSS tras cada purga | Alta sobre los episodios de "se congeló" |
| 7 | Apagar `mariadb-tmp` | **Solo si hay presión de RAM.** Riesgo cero igual |
| 8 | Nginx: verificar compresión, cachear estáticos, keepalive | Media sobre la red; la compresión probablemente ya exista |
| 9 | `php.ini` afinado | Baja-media, barata dentro de un deploy que ya se hace |
| 10 | Acotar `MaxRequestWorkers` | No acelera: **evita el desplome** en un pico |
| 11 | Redis para sesiones | Media. Mejora de arquitectura y concurrencia |
| 12 | Redis para la MUC | **Baja en un solo nodo.** Solo con evidencia |
| 13 | TLS + HTTP/2 | Media-alta, pero depende del certificado |
| 14 | php-fpm | **Diferir** hasta que `BusyWorkers` lo justifique |
| 15 | `dbpersist` | **No hacer** |

**Lo que hay que decir al empezar la reunión**: `perfdebug=7` significa apagado; `mod_deflate`
viene habilitado por defecto y Moodle ya comprime su CSS y JS; el autovacuum probablemente ya
hizo el `ANALYZE`; y `themedesignermode` ya está correcto. Cuatro propuestas que van a surgir y
que conviene descartar con datos antes de gastar tiempo en ellas.
