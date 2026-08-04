# Moodle 5.2.1 — Academia CONAF v2
#
# Una sola imagen sirve para las dos fases de la migración: apunta a MariaDB
# mientras se convierte el volcado y a PostgreSQL en el estado final. Lo que
# cambia son variables de entorno, no la imagen.
#
# ─── DOS CAMBIOS GRANDES RESPECTO DE coipo_moodle (4.5.10 + PHP 8.3) ─────────
#
# 1. PHP 8.4. Moodle 4.5 lo BLOQUEA explícitamente (restrict_php_version_84 en
#    admin/environment.xml). Moodle 5.2 no solo lo permite: no tiene ningún
#    bloqueo superior de PHP. Verificado en el environment.xml de 5.2.1 —
#    PHP mínimo 8.3.0 y ni un <RESTRICT>.
#
#    Aun así NO se usa 8.5, aunque pasaría la comprobación de entorno: Moodle
#    solo documenta 8.4.x como soportada para 5.2. «No da error» no es «está
#    soportado», y detrás hay datos de 2.869 funcionarios.
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
# pgsql Y mysqli: la primera para el estado final, la segunda para poder leer
# la base MariaDB durante la conversión. Las dos van en la misma imagen.
# El driver de Moodle para MariaDB es mysqli, no pdo_mysql.
#
# Las bibliotecas -dev NO se purgan a propósito: apt no sabe que las extensiones
# compiladas dependen de sus .so en tiempo de ejecución y un --auto-remove deja
# la imagen rota. Pesa más, pero funciona.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates locales tzdata \
        mariadb-client postgresql-client \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libicu-dev libzip-dev libxml2-dev libpq-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
        gd intl zip soap exif opcache mysqli pgsql; \
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
# El `cd /` no es decorativo: la imagen php:8.3-apache trae WORKDIR /var/www/html,
# así que sin él el shell está parado dentro de la carpeta que borra el rm y git
# aborta con "Unable to read current working directory".
RUN set -eux; \
    cd /; \
    rm -rf /var/www/html; \
    git clone --depth 1 --branch "${MOODLE_VERSION}" \
        https://github.com/moodle/moodle.git /var/www/html; \
    rm -rf /var/www/html/.git

# ─── Plugins no-core ─────────────────────────────────────────────────────────
# mod_customcert va vendorizado en el repo (plugins/mod/customcert, 3 MB) en vez
# de clonarse desde GitHub, por dos razones verificadas:
#   1. La rama MOODLE_405_STABLE de ese repositorio NO existe (404) — clonarla
#      hacía fallar el build.
#   2. La copia vendorizada es la versión 2024042217 exacta que registra la base
#      de datos original, con sus 19 subplugins de elementos. Una versión menor
#      que la de la base haría que Moodle se niegue a arrancar.
COPY --chown=www-data:www-data plugins/mod/customcert/ /var/www/html/public/mod/customcert/

# Los otros tres estaban registrados en la base pero faltaban en el código. Sin
# ellos, admin/tool/dbtransfer no copiaría las tablas de configurable_reports ni
# de mergeusers a PostgreSQL: sus datos se perderían sin ningún mensaje de error.
#
# Versiones verificadas: todas son más nuevas que las de la base (nunca al revés,
# o Moodle se niega a arrancar) y todas declaran compatibilidad con Moodle 4.5.
#   tool_mergeusers        2026052700  requires 2024100700  supported [405,502]
#   block_configurable_reports 2027050401  requires 2022041900  supported [400,500]
#   theme_boost_magnific   2026062801  requires 2024042200   (era el tema activo)
COPY --chown=www-data:www-data plugins/admin/tool/mergeusers/       /var/www/html/public/admin/tool/mergeusers/
COPY --chown=www-data:www-data plugins/blocks/configurable_reports/ /var/www/html/public/blocks/configurable_reports/
COPY --chown=www-data:www-data plugins/theme/boost_magnific/        /var/www/html/public/theme/boost_magnific/

# theme_academi — recuperado el 31-07-2026. No es un tema nuevo: la base ya traía
# 134 ajustes suyos con datos reales de CONAF (dirección de Paseo Bulnes, correo
# campus.geprif@conaf.cl, aviso de copyright), pero sus archivos nunca se
# vendorizaron y por eso Moodle lo daba por "ausente del disco".
#
#   en la base    2024060503
#   vendorizado   2025050300  release v4.5.1  requires 2024042200  MATURITY_STABLE
#
# Descargado del directorio oficial de Moodle y verificado contra el MD5 que
# publica la API: 555e3d351fe1252c09983c1203e5c0b7. Origen github.com/lmsace/academi.
#
# NO instalar este tipo de plugin por la interfaz web: los archivos quedarían solo
# dentro del contenedor y el siguiente despliegue reconstruye la imagen desde este
# Dockerfile, así que desaparecerían y el sitio quedaría otra vez con ajustes
# huérfanos en la base. Todo plugin va vendorizado en plugins/ y copiado acá.
COPY --chown=www-data:www-data plugins/theme/academi/                /var/www/html/public/theme/academi/

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
