# Paso a HTTPS — academia.conaf.cl

**Ejecutado y verificado el 30 de julio de 2026.** Encargado técnico: Luis Monsalve.

Este documento registra el paso del sitio de `http://` a `https://`: qué cambió en la
infraestructura, cuál fue el síntoma, cómo se diagnosticó, qué se tocó exactamente, cómo se
verifica y cómo se revierte.

---

## Resumen en una línea

El TLS lo terminó un balanceador nuevo, pero Moodle seguía creyendo que vivía en `http://` y
generaba las URLs de sus recursos con ese esquema. El navegador las bloqueaba por contenido
mixto y **el sitio aparecía sin CSS ni JS, sin ningún mensaje de error**. Se corrigió poniendo
`MOODLE_WWWROOT=https://…` y `MOODLE_SSLPROXY=true` **a la vez**.

---

## Qué cambió en la infraestructura ese día

Dos cosas ocurrieron el mismo día, ninguna avisada por nosotros:

1. **Apareció el registro DNS.** Esa mañana `academia.conaf.cl` todavía **no existía**:
   `pimiento.conaf.cl` (172.16.1.120) respondía `Non-existent domain` y solo se llegaba
   forzando la resolución en el navegador. Por la tarde ya resolvía.
2. **Apareció el certificado**, pero **no en nuestro servidor**.

```
academia.conaf.cl ── DNS ──▶ 172.31.2.100
                             Balanceador Radware Alteon
                             · TERMINA el TLS (certificado acá)
                             · redirige http:// → https:// con un 307
                             · se reconoce por la cookie AlteonP
        │ HTTP plano
        ▼
   172.31.2.41:80   Nginx de nuestro servidor
        │           (sin certificado, y así debe quedar)
        ▼
   127.0.0.1:8115   contenedor coipo_moodle-app-1
```

**Consecuencia importante**: nuestro vhost de Nginx **no necesita `listen 443 ssl` ni ningún
`.pem`**. Poner TLS acá sería duplicarlo sin ninguna ventaja. El certificado lo administra
Informática en el Alteon.

Verificado: el puerto **443 está cerrado en 172.31.2.41** y el 80 abierto. `curl https://` contra
nuestra IP directamente da conexión rechazada; contra el nombre da 200 desde `172.31.2.100`.

---

## El síntoma

`https://academia.conaf.cl/login/index.php` cargaba, pero **completamente sin estilos**. Ni un
error en pantalla, ni un 404, ni nada en los registros del servidor.

Lo que despistaba: **todas las mediciones del lado del servidor salían perfectas.** El documento
respondía 200 en 71 ms, el CSS respondía 200 con sus 253.690 bytes, `/health` decía `ok`. El
problema no estaba en servir el recurso, sino en que **el navegador se negaba a pedirlo**.

---

## El diagnóstico

La pista está en el esquema de las URLs que Moodle escribe en el HTML:

```bash
curl -s https://academia.conaf.cl/login/index.php \
  | grep -oE '(src|href)="https?://[^"]+"' \
  | sed -E 's/.*"(https?):\/\/([^\/"]+).*/\1:\/\/\2/' | sort | uniq -c
```

Antes de la corrección:

```
      9   http://academia.conaf.cl
```

Nueve recursos —el CSS del tema, los dos JS, el favicon, el logo, los dos `yui_combo`—
referenciados por **`http://` dentro de una página servida por `https://`**. Eso es *contenido
mixto activo*, y todos los navegadores modernos lo **bloquean en silencio**: no lo piden, no lo
registran como error visible y no lo dicen en la pantalla. Por eso el servidor se veía sano.

La causa raíz está en `docker/config.php`: `$CFG->wwwroot` sale de `MOODLE_WWWROOT`, y Moodle
construye **todas** las URLs del sitio a partir de ese valor. Mientras dijera `http://`, todo
recurso nacía en `http://`.

---

## La corrección

### 1. Las dos variables del `.env` del servidor

En `/opt/apps/coipo_moodle/.env`:

```diff
- MOODLE_WWWROOT=http://academia.conaf.cl
+ MOODLE_WWWROOT=https://academia.conaf.cl
  MOODLE_REVERSEPROXY=false
- MOODLE_SSLPROXY=false
+ MOODLE_SSLPROXY=true
```

**Van juntas, siempre. Cambiar solo una rompe el sitio de otra forma:**

| Combinación | Qué pasa |
|---|---|
| `https://` + `sslproxy=false` | Los recursos nacen en `http://` → contenido mixto → **sitio sin CSS ni JS**. Es el fallo que se acaba de corregir |
| `http://` + `sslproxy=true` | Las cookies se marcan `Secure`, el navegador no las envía sobre HTTP plano y **el login deja de funcionar** sin decir por qué |
| `https://` + `sslproxy=true` | Correcto |

**`MOODLE_REVERSEPROXY` se queda en `false`** aunque ahora haya *dos* proxys delante. No es un
descuido: `lib/setuplib.php:745` lanza `reverseproxyabused` cuando `reverseproxy` está activo y
el `Host` que llega coincide con el de `wwwroot`, que es exactamente el caso acá (nuestro Nginx
hace `proxy_set_header Host $host`). No se pierde nada: la IP real del visitante la resuelve
`mod_remoteip` de Apache, configurado en `docker/apache-moodle.conf`.

Respaldo del `.env` previo: `/opt/apps/coipo_moodle/.env.bak-20260730-191420` (`chmod 600`).

### 2. Recrear los contenedores

Las variables de entorno se fijan al crear el contenedor, no al arrancarlo:

```bash
cd /opt/apps/coipo_moodle
docker compose up -d          # recrea app y cron con el .env nuevo
```

Hay que confirmar que **el `cron` también** tomó el valor: comparte `moodledata` y genera URLs
en notificaciones y respaldos.

```bash
docker compose exec cron sh -c 'echo "$MOODLE_WWWROOT $MOODLE_SSLPROXY"'
# https://academia.conaf.cl true
```

### 3. Purgar cachés y recompilar el CSS del tema

**Este paso no es opcional.** El `wwwroot` queda incrustado dentro del CSS compilado (las
`url()` de imágenes y fuentes) y dentro de la caché de plantillas. Sin purgar, el sitio sigue
sirviendo CSS con URLs `http://` aunque el `.env` ya diga `https://`.

```bash
docker compose exec -u www-data app php admin/cli/purge_caches.php
docker compose exec -u www-data app php admin/cli/build_theme_css.php -t=boost_magnific
```

El `build_theme_css.php` va **siempre** después de un `purge_caches.php`: si no, la primera
petición que llegue paga la compilación completa del SCSS del tema, y si llegan varias a la vez
compilan todas en paralelo. Tarda ~4 s hacerlo a mano; el síntoma de no hacerlo es "el sitio se
congeló un momento". **Se ejecuta en el contenedor `app`**, no en `cron`: tienen volúmenes de
`localcache` separados y hacerlo en `cron` no sirve de nada para el sitio web.

---

## Cómo se verifica

No basta con que el `.env` diga `https://`. Estas cinco comprobaciones son las que prueban que
funciona de verdad.

### 1. Cero referencias `http://` en el HTML

```bash
curl -s https://academia.conaf.cl/login/index.php | grep -c 'http://academia.conaf.cl'
# debe dar 0
```

### 2. Todos los subrecursos responden 200 por HTTPS

```bash
curl -s https://academia.conaf.cl/login/index.php -o /tmp/p.html
grep -oE '<(img|script)[^>]*src="[^"]+"|<link[^>]*href="[^"]+"' /tmp/p.html \
  | grep -oE '(src|href)="[^"]+"' | sed 's/^[a-z]*="//; s/"$//; s/&amp;/\&/g' | sort -u \
  | while read -r u; do
      curl -s -o /dev/null -w "%{http_code}  $u\n" -H 'Accept-Encoding: gzip' "$u"
    done
```

**Lo que importa es que los siete devuelvan 200.** Los tamaños son de referencia y varían un
poco entre revisiones del tema:

```
200  .../lib/javascript.php/…/lib/javascript-static.js          ~6,5 kB
200  .../lib/javascript.php/…/lib/polyfills/polyfill.js          ~60 kB
200  .../pluginfile.php/1/core_admin/favicon/64x64/…            ~300 B
200  .../pluginfile.php/1/core_admin/logocompact/x200/…          ~12 kB
200  .../theme/styles.php/boost_magnific/…/all                  ~254 kB
200  .../theme/yui_combo.php?rollup/3.18.1/…-min.css            ~900 B
200  .../theme/yui_combo.php?rollup/3.18.1/…-min.js              ~84 kB
```

Si alguno diera 404, casi seguro falta el `build_theme_css.php` del paso 3: la revisión del
tema en el HTML no coincide con el CSS que hay compilado en disco.

### 3. La cookie de sesión sale marcada `secure`

Es la señal directa de que `sslproxy=true` tomó efecto:

```bash
curl -s -D- -o /dev/null https://academia.conaf.cl/login/index.php | grep -i set-cookie
# Set-Cookie: MoodleSession=…; path=/; secure; HttpOnly
```

Si falta `secure`, `sslproxy` no está activo.

### 4. Los marcadores antiguos siguen sirviendo

```bash
curl -s -o /dev/null -w '%{http_code} -> %{redirect_url}\n' http://academia.conaf.cl/login/index.php
# 307 -> https://academia.conaf.cl/login/index.php
```

El 307 lo hace el Alteon, no nosotros.

### 5. Mirarlo renderizado — la única que prueba el síntoma original

Las cuatro anteriores pueden pasar y la página verse mal igual. Hay que **verla**:

```powershell
& "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" `
  --headless=new --disable-gpu --no-first-run `
  --user-data-dir="$env:TEMP\edge-shot" --virtual-time-budget=20000 `
  --window-size=1400,1000 --screenshot="$env:TEMP\login.png" `
  "https://academia.conaf.cl/login/index.php"
```

Debe verse el tema `boost_magnific` completo: fondo de lago y montañas, la barra de logos
(Ministerio de Agricultura · CONAF · CAMPUS CONAF), los campos con bordes redondeados y el botón
"Acceder" con degradado turquesa a azul. Si sale texto plano sobre fondo blanco, el CSS sigue
bloqueado.

**Ojo con la caché del navegador.** El perfil que ya visitó el sitio roto tiene cacheado el HTML
con las URLs `http://`. Hay que forzar `Ctrl+F5` o usar un perfil limpio, o parecerá que el
arreglo no sirvió.

---

## Coste medido

| Ruta | Mediana | p90 |
|---|---|---|
| HTTP directo a nuestro Nginx (antes) | 30,7 ms | 31,7 ms |
| **HTTPS por el Alteon (ahora)** | **60,4 ms** | 65,1 ms |

El TLS más el salto por el balanceador añaden ~30 ms. Es un costo normal y no requiere ninguna
acción.

---

## Cómo se revierte

Si hubiera que volver a HTTP plano —por ejemplo, si el Alteon dejara de terminar TLS:

```bash
cd /opt/apps/coipo_moodle
cp .env.bak-20260730-191420 .env        # o editar las dos variables a mano
docker compose up -d
docker compose exec -u www-data app php admin/cli/purge_caches.php
docker compose exec -u www-data app php admin/cli/build_theme_css.php -t=boost_magnific
```

Las dos variables vuelven a `http://` y `false` **juntas**, por la misma razón por la que se
cambiaron juntas.

---

## Qué se cambió en el repositorio

Ninguno de estos archivos altera el comportamiento: son comentarios y valores de ejemplo que
decían lo contrario de la realidad y habrían inducido al error otra vez.

| Archivo | Cambio |
|---|---|
| `docker/config.php` | El comentario decía "el servidor CONAF sirve HTTP plano por ahora: sslproxy DEBE quedar en false". Ahora describe el Alteon y por qué `sslproxy=true` es obligatorio |
| `.env.example` | `MOODLE_WWWROOT` pasa a `https://`, `MOODLE_SSLPROXY` a `true`, con el diagrama de la arquitectura y la tabla de combinaciones |
| `docs/nginx-academia.conf` | Se quitó la plantilla `listen 443 ssl` comentada: **no hace falta**, el TLS está en el Alteon. Se documenta que este vhost queda en el puerto 80 a propósito |
| `CLAUDE.md` | Arquitectura con el Alteon; los pendientes "certificado" y "DNS" quedan cerrados |

---

## Trampas, para que no se repitan

1. **Cambiar una sola de las dos variables.** Rompe el sitio de una forma distinta cada vez, y
   ninguna de las dos da un mensaje de error útil.
2. **No purgar y recompilar el CSS.** El `wwwroot` viejo sobrevive dentro del CSS compilado.
3. **Creer que el servidor está mal porque la página se ve mal.** Acá todas las métricas del
   servidor estaban perfectas: el bloqueo ocurría en el navegador. Cuando el TTFB es bajo y la
   página se ve rota, hay que mirar el HTML servido y la consola del navegador, no el servidor.
4. **Poner `MOODLE_REVERSEPROXY=true` "porque ahora hay dos proxys".** Tumba el sitio con
   `reverseproxyabused`. Ver `lib/setuplib.php:745`.
5. **Pedir un certificado para nuestro Nginx.** No hace falta: el TLS termina en el Alteon.
6. **Probar con el navegador que ya vio el sitio roto.** La caché miente.
7. **Olvidar el contenedor `cron`.** También necesita el `wwwroot` correcto.

---

## Pendiente relacionado

Las URLs fósiles del contenido (`campus.conaf.cl`, `127.0.0.1:8080`) **no se ven afectadas por
este cambio** y siguen pendientes por separado. Antes de tocarlas, leer la nota de `CLAUDE.md`:
la inmensa mayoría son `mdl_question.stamp`, que **no son URLs** sino identificadores de
deduplicación de preguntas, y reescribirlas sería un error.
