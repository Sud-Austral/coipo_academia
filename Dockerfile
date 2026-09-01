# Moodle 5.2.1 — Academia CONAF v2
#
# Instalación limpia: admin/cli/install_database.php contra PostgreSQL 17. Acá
# no se convierte ningún volcado ni se hereda ninguna base — la Academia parte
# vacía, y por eso la imagen ya no necesita saber leer MariaDB.
#
# ─── DOS CAMBIOS GRANDES RESPECTO DE coipo_moodle (4.5.10 + PHP 8.3) ─────────
#
# 1. PHP 8.4. Moodle 4.5 lo BLOQUEA explícitamente (restrict_php_version_84 en
#    admin/environment.xml). Moodle 5.2 no solo lo permite: no tiene ningún
#    bloqueo superior de PHP. Verificado en el environment.xml de 5.2.1 —
#    PHP mínimo 8.3.0 y ni un <RESTRICT>.
#
#    Aun así NO se usa 8.5, aunque pasaría la comprobación de entorno: Moodle
#    solo documenta 8.4.x como soportada para 5.2, y «no da error» no es «está
#    soportado». Que la Academia parta vacía no debilita el argumento: la rama
#    de PHP con la que se construye hoy es la que habrá que sostener el día que
#    tenga gente adentro, y cambiarla después es un redespliegue completo.
#
# 2. LA RAÍZ WEB SE MOVIÓ A public/. Es el cambio estructural de Moodle 5.1/5.2
#    y es el que más rompe si se pasa por alto:
#
#      /var/www/html/               <- raíz del repositorio. Acá va config.php
#      /var/www/html/public/        <- DocumentRoot de Apache. Acá va TODO lo web
#      /var/www/html/admin/cli/     <- los scripts CLI se quedan en la raíz
#
#    $CFG->dirroot pasa a valer /var/www/html/public (public/lib/setup.php:62),
#    y el index.php de la raíz LANZA UNA EXCEPCIÓN a propósito si alguien llega
#    ahí: es la trampa para el DocumentRoot mal apuntado.

FROM php:8.4-apache

ARG MOODLE_VERSION=v5.2.1
# Verificado contra la API de GitHub: v0.2.48 es la última publicada y trae el
# binario supercronic-linux-amd64.
ARG SUPERCRONIC_VERSION=v0.2.48

ENV TZ=America/Santiago

# ─── Extensiones PHP que Moodle exige ────────────────────────────────────────
# Solo pgsql. mysqli y mariadb-client se fueron cuando la Academia dejó de
# clonarse de producción: existían para leer el volcado de MariaDB y pasarlo con
# dbtransfer, y ese paso ya no ocurre. Cargar el driver de un motor que nadie va
# a usar no es gratis — es superficie que hay que parchar cada vez.
#
# ghostscript SÍ hace falta, y en un sitio nuevo exactamente igual que en uno
# heredado: assignfeedback_editpdf lo invoca para rasterizar la entrega en PDF
# que el docente va a anotar. Sin él la tarea adhoc no falla a la vista, se
# reencola con faildelay creciente y el docente ve una entrega que «no termina
# de cargar». En coipo_moodle costó 11 tareas en bucle darse cuenta. Es el
# ejemplo de qué NO viaja en una migración: la base y los archivos sí, las
# dependencias del sistema operativo hay que reinstalarlas.
#
# Las bibliotecas -dev NO se purgan a propósito: apt no sabe que las extensiones
# compiladas dependen de sus .so en tiempo de ejecución y un --auto-remove deja
# la imagen rota. Pesa más, pero funciona.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates locales tzdata \
        postgresql-client ghostscript \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libicu-dev libzip-dev libxml2-dev libpq-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
        gd intl zip soap exif opcache pgsql; \
    a2enmod rewrite headers remoteip; \
    sed -i 's/^# *\(es_CL.UTF-8\)/\1/' /etc/locale.gen && locale-gen; \
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone; \
    rm -rf /var/lib/apt/lists/*

# ─── Cron empaquetado en la imagen (patrón CONAF: nunca cron del host) ──────
RUN set -eux; \
    curl -fsSL -o /usr/local/bin/supercronic \
        "https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-amd64"; \
    chmod +x /usr/local/bin/supercronic

# ─── Código de Moodle ────────────────────────────────────────────────────────
# Se descarga la versión oficial en el build: el repositorio queda liviano y la
# imagen es reproducible. El tag v5.2.1 fue verificado contra la API de GitHub.
# Si el servidor no tuviera salida a internet, ver docs/MIGRACION.md.
# El `cd /` no es decorativo: la imagen php:8.4-apache trae WORKDIR /var/www/html,
# así que sin él el shell está parado dentro de la carpeta que borra el rm y git
# aborta con "Unable to read current working directory".
RUN set -eux; \
    cd /; \
    rm -rf /var/www/html; \
    git clone --depth 1 --branch "${MOODLE_VERSION}" \
        https://github.com/moodle/moodle.git /var/www/html; \
    rm -rf /var/www/html/.git

# ─── Plugins no-core ─────────────────────────────────────────────────────────
# Eran cinco, y ninguno estaba acá porque alguien lo hubiera elegido: estaban
# REGISTRADOS en la base de producción, y un plugin registrado que falta en
# disco hace que dbtransfer no copie sus tablas y pierda esos datos sin decir
# nada. Esa razón se murió con el clon. En un sitio que se instala vacío no hay
# base heredada que respetar, así que cada plugin tiene que justificarse por lo
# que aporta hoy — y solo uno lo hace.
#
# Los otros cuatro salieron por su propio motivo, no por descarte en bloque, y
# el 01-09-2026 salieron también del árbol: 55 MB que ningún COPY tocaba pero
# que igual viajaban en el contexto de cada build, porque .dockerignore no tiene
# ninguna regla que nombre plugins/.
#
# Va anotado de dónde vino cada uno con su versión exacta, porque eso es lo
# único que se pierde al borrar el directorio. Bajar «la última» en vez de la
# misma es cómo se rompe un plugin sin que nadie se entere:
#
#   tool_mergeusers             fusiona usuarios duplicados; sin usuarios no
#                               tiene a quién fusionar. Vuelve el día que se
#                               carguen los 2.869, que es cuando sirve, y por eso
#                               esta línea importa más que las otras tres:
#                               ndunand/moodle-tool_mergeusers, 2026052700,
#                               supported [405,502] — sirve tal cual en la 502,
#                               no hay que salir a buscar otra rama.
#   block_configurable_reports  las vistas de la Academia las construye
#                               70_informes.php con el generador nativo
#                               (core_reportbuilder). Cero referencias al plugin
#                               en todo academia/. Además declara
#                               supported [400,500] y esta rama es la 502.
#                               jleyva/moodle-block_configurablereports, 2027050401.
#   theme_boost_magnific        38 MB heredados del sitio viejo, más cinco
#                               ajustes suyos con URLs a campus.conaf.cl. El
#                               tema de la Academia es theme_academia, que hereda
#                               de theme_boost —el de core— y no de éste: se
#                               comprobó en su config.php y en su version.php
#                               antes de borrar nada. El campus del 8115 lo sigue
#                               usando, pero es otro despliegue y no se sirve de
#                               este árbol.
#                               EduardoKrausME/moodle-theme_boost_magnific, 2026062801.
#   theme_academi               9,9 MB que estaban acá SOLO para que 134 ajustes
#                               huérfanos de la base de producción dejaran de
#                               serlo. Esos ajustes no existen en una base nueva:
#                               sin ellos el plugin no aporta absolutamente nada.
#                               lmsace/academi, 2026042900, release v5.2.
#
# Y una advertencia para el que venga a buscar espacio: borrarlos NO aligera el
# clon. Los cuatro estaban versionados y sus blobs se quedan en .git para
# siempre. Lo que baja es el árbol de trabajo y el contexto de build; el clon
# solo bajaría reescribiendo la historia, que cuesta mucho más de lo que vale.
#
# mod_customcert 2026042005 · release 5.2.4 · requires 2026042000 (Moodle 5.2).
# Se queda porque el diseño de la Academia depende de él, no por herencia:
# 80_plantilla_maestra.php y 90_cursos_esqueleto.php crean una instancia de
# customcert en GC-000 y en IF-151, y el campo `vigencia` de campos-curso.csv
# existe para alimentar el elemento Expiry, que vive dentro de este plugin
# (element/expiry). Sin customcert un certificado responde «esta persona hizo un
# curso alguna vez»; con Expiry responde «esta persona estaba habilitada el día
# del incidente», y en operaciones con riesgo vital esa diferencia es jurídica.
#
# Va vendorizado y no clonado desde GitHub porque la rama que correspondía a la
# versión anterior no existía (404) y el build fallaba. La regla vale para
# cualquier plugin que vuelva a entrar acá: se vendoriza en plugins/ y se copia
# en este archivo, NUNCA se instala por la interfaz web. Lo que sube la interfaz
# vive solo dentro del contenedor, y el siguiente despliegue reconstruye la
# imagen desde este Dockerfile: el plugin desaparece y deja ajustes huérfanos en
# la base, que es exactamente cómo llegó acá theme_academi.
COPY --chown=www-data:www-data plugins/mod/customcert/ /var/www/html/public/mod/customcert/

# theme_academia — el tema de la Academia v2. Es un HIJO de Boost: no trae
# plantillas ni disposiciones propias, solo variables de SCSS y unas pocas
# reglas. Por eso no hay que revisarlo en cada actualización de Moodle.
#
# Lleva la paleta institucional y las reglas del estándar que son
# responsabilidad del tema y no del autor: cuerpo de 16 px, interlineado 1,6,
# alineación siempre a la izquierda, largo de línea acotado y foco de teclado
# visible. Ver docs/V2-ACADEMIA.md.
#
# Comprobar que compila ANTES de desplegar —un error de SCSS no da error, da un
# sitio sin estilos—:
#   docker compose exec app php /opt/academia/pruebas/compilar-tema.php
COPY --chown=www-data:www-data plugins/theme/academia/               /var/www/html/public/theme/academia/

# ─── Scripts de provisión de la Academia ─────────────────────────────────────
# Van a /opt/academia, FUERA del DocumentRoot de Apache (/var/www/html), a
# propósito: así ningún script de provisión es alcanzable por HTTP ni por
# accidente. Se ejecutan siempre con -u www-data:
#
#   docker compose exec -u www-data app php /opt/academia/cli/10_categorias.php
#
# El orden y qué hace cada uno están en academia/README.md.
COPY --chown=www-data:www-data academia/ /opt/academia/

COPY docker/config.php         /var/www/html/config.php
COPY docker/health.php         /var/www/html/public/health.php
COPY docker/php.ini            /usr/local/etc/php/conf.d/zzz-moodle.ini
COPY docker/moodle-crontab     /etc/moodle-crontab
COPY docker/apache-moodle.conf /etc/apache2/conf-enabled/moodle.conf

# /var/moodledata es el punto de montaje del bind mount; en ejecución lo
# reemplaza la carpeta del host, que debe pertenecer al UID 33.
# /var/localcache es caché local del nodo (Moodle lo purga solo): va en volumen
# con nombre, no en el bind mount, para no escribir miles de archivos chicos
# sobre un sistema de archivos remoto o lento.
RUN set -eux; \
    mkdir -p /var/moodledata /var/localcache; \
    chown -R www-data:www-data /var/moodledata /var/localcache /var/www/html; \
    chmod 2775 /var/moodledata /var/localcache

EXPOSE 80
