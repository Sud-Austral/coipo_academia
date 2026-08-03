<?php
// Configuración de Moodle para contenedor — CONAF.
//
// Este archivo NO contiene credenciales ni rutas fijas: todo llega por variables
// de entorno desde el .env del servidor. Gracias a eso, la misma imagen sirve
// para la fase de conversión (MariaDB) y para el estado final (PostgreSQL).
//
// Reemplaza al config.php del sitio original, que traía credenciales de root sin
// clave, rutas Windows de otra máquina y $CFG->ignoreunmetenvironmentrequirements
// (que enmascaraba fallos reales de entorno en vez de resolverlos).

unset($CFG);
global $CFG;
$CFG = new stdClass();

// getenv() funciona con mod_php —php:8.3-apache hereda el entorno del
// contenedor— y también en CLI. El respaldo con $_SERVER es por si algún día
// esto pasa a php-fpm.
function conaf_env(string $clave, ?string $defecto = null): ?string {
    $v = getenv($clave);
    if ($v === false || $v === '') {
        $v = $_SERVER[$clave] ?? null;
    }
    return ($v === null || $v === '') ? $defecto : $v;
}

function conaf_env_bool(string $clave, bool $defecto = false): bool {
    $v = conaf_env($clave);
    return $v === null ? $defecto : filter_var($v, FILTER_VALIDATE_BOOLEAN);
}

// ─── Base de datos ──────────────────────────────────────────────────────────
// MOODLE_DBTYPE: 'pgsql' en el estado final, 'mariadb' durante la conversión.
// Los nombres DATABASE_* siguen la convención de las 6 variables de
// infraestructura que usan todas las apps CONAF.
$CFG->dbtype    = conaf_env('MOODLE_DBTYPE',   'pgsql');
$CFG->dblibrary = 'native';
$CFG->dbhost    = conaf_env('DATABASE_HOST',   '');
$CFG->dbname    = conaf_env('DATABASE_NAME',   '');
$CFG->dbuser    = conaf_env('DATABASE_USER',   '');
$CFG->dbpass    = conaf_env('DATABASE_PASSWORD', '');
$CFG->prefix    = conaf_env('MOODLE_DBPREFIX', 'mdl_');

$CFG->dboptions = array(
    'dbpersist' => false,
    'dbport'    => conaf_env('DATABASE_PORT', ''),
    'dbsocket'  => '',
);

// El volcado original es utf8mb4_unicode_ci. Solo aplica a MariaDB/MySQL; el
// driver de PostgreSQL lo ignora.
if ($CFG->dbtype === 'mariadb' || $CFG->dbtype === 'mysqli') {
    $CFG->dboptions['dbcollation'] = 'utf8mb4_unicode_ci';
}

// ─── Sitio ──────────────────────────────────────────────────────────────────
// wwwroot debe coincidir EXACTAMENTE con lo que muestra la barra de direcciones:
// esquema, host y puerto, sin barra final. Si no coincide, aparece un bucle de
// redirección o el CSS y el JS no cargan, sin ningún mensaje que lo explique.
$CFG->wwwroot = rtrim(conaf_env('MOODLE_WWWROOT', 'http://localhost'), '/');

// moodledata vive FUERA del contenedor: bind mount a /opt/moodledata/coipo_moodle
// en el host, propiedad del UID 33 (www-data).
$CFG->dataroot = '/var/moodledata';

// localcachedir es caché local del nodo y Moodle lo purga solo. Va en un volumen
// de Docker, no en el bind mount: son miles de archivos pequeños y escribirlos
// sobre el sistema de archivos del host es mucho más lento sin ninguna ventaja.
$CFG->localcachedir = '/var/localcache';

$CFG->admin = 'admin';

// 02775 con setgid: los subdirectorios heredan el grupo. Sin el setgid, lo que
// se copie a moodledata desde otro usuario deja directorios con grupo
// equivocado y Apache pierde la escritura a mitad del árbol.
$CFG->directorypermissions = 02775;
$CFG->filepermissions      = 0664;
$CFG->slasharguments       = true;

// ─── TLS y proxys ───────────────────────────────────────────────────────────
// Desde el 30-07-2026 hay certificado. El TLS NO lo termina el Nginx de este
// servidor sino un balanceador Radware Alteon en 172.31.2.100, que es a donde
// apunta el DNS de academia.conaf.cl (se reconoce por la cookie AlteonP). El
// Alteon reenvía en HTTP plano al Nginx de 172.31.2.41:80, y además redirige
// http:// a https:// con un 307, así que los marcadores antiguos siguen sirviendo.
//
// sslproxy = true es OBLIGATORIO en ese esquema: Moodle recibe HTTP y, sin esta
// variable, generaría todas las URLs de recursos con http:// dentro de una
// página servida por https://. El navegador las bloquea por contenido mixto y el
// sitio aparece SIN CSS ni JS, sin ningún error visible. Va siempre junto con
// MOODLE_WWWROOT=https://... : las dos o ninguna.
//
// reverseproxy se queda en false aunque haya dos proxys delante. No es un
// descuido: lib/setuplib.php:745 lanza "reverseproxyabused" cuando el Host que
// llega coincide con el de wwwroot, que es exactamente lo que pasa acá.
$CFG->reverseproxy = conaf_env_bool('MOODLE_REVERSEPROXY', false);
$CFG->sslproxy     = conaf_env_bool('MOODLE_SSLPROXY', false);

// ─── SEGURIDAD: correo saliente ─────────────────────────────────────────────
// Esta es una copia de producción con ~2.800 correos institucionales REALES. En
// cuanto el cron arranque, Moodle enviaría resúmenes de foro, notificaciones de
// tareas y avisos de cambio de contraseña a funcionarios de verdad, desde un
// sitio de pruebas. noemailever corta el envío en el núcleo: email_to_user() no
// manda nada y lo deja registrado en el log.
// Por defecto true: para desactivarlo hace falta una decisión explícita.
if (conaf_env_bool('MOODLE_NOEMAILEVER', true)) {
    $CFG->noemailever = true;
}

// El cron solo por línea de comandos: /admin/cron.php deja de ser invocable
// desde la web por cualquiera que adivine la URL.
$CFG->cronclionly = true;

// Solo para diagnosticar. Jamás en producción: expone rutas y consultas.
if (conaf_env_bool('MOODLE_DEBUG', false)) {
    $CFG->debug = E_ALL;
    $CFG->debugdisplay = 1;
}

require_once(__DIR__ . '/lib/setup.php');
