<?php
/**
 * Endpoint de salud para el healthcheck del contenedor y el smoke test del
 * despliegue CONAF. Moodle no trae uno propio.
 *
 * NO carga config.php ni el arranque de Moodle, y eso es deliberado.
 *
 * La primera versión sí lo hacía, y se rompió en producción de una forma
 * engañosa: el healthcheck pide http://localhost/health, o sea con
 * `Host: localhost`, mientras el sitio tenía wwwroot=http://academia.conaf.cl
 * y reverseproxy activo. Moodle detecta esa discrepancia durante su arranque y
 * responde una página HTML de error — antes de que este archivo ejecute una sola
 * línea. Resultado: el contenedor quedaba "unhealthy" y el despliegue culpaba a
 * la base de datos, que estaba perfectamente bien.
 *
 * Un chequeo de salud tiene que poder responder cuando la configuración del
 * sitio es justamente lo que falla. Por eso lee las mismas variables de entorno
 * que docker/config.php, pero por su cuenta.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Coincide con $CFG->dataroot de docker/config.php y con el punto de montaje que
// crea el Dockerfile. Es el bind mount a /opt/moodledata/coipo_moodle del host.
const DATAROOT = '/var/moodledata';

function env_val(string $clave, string $defecto = ''): string {
    $v = getenv($clave);
    if ($v === false || $v === '') {
        $v = isset($_SERVER[$clave]) ? $_SERVER[$clave] : '';
    }
    return $v === '' ? $defecto : (string)$v;
}

$estado    = array('status' => 'ok');
$problemas = array();

// ─── 1. moodledata montado y escribible por el usuario del contenedor ───────
if (!is_dir(DATAROOT)) {
    $problemas[] = 'dataroot no existe: el bind mount no está montado';
} else if (!is_writable(DATAROOT)) {
    $problemas[] = 'dataroot no escribible: revisar chown 33:33 en el host';
}
$estado['dataroot'] = $problemas ? 'error' : 'ok';

// ─── 2. Base de datos: conectar y una consulta trivial, con timeout corto ───
$tipo    = env_val('MOODLE_DBTYPE', 'pgsql');
$host    = env_val('DATABASE_HOST');
$nombre  = env_val('DATABASE_NAME');
$usuario = env_val('DATABASE_USER');
$clave   = env_val('DATABASE_PASSWORD');
$puerto  = (int) env_val('DATABASE_PORT', $tipo === 'pgsql' ? '5432' : '3306');

$estado['db']   = 'error';
$estado['motor'] = $tipo;
$detalle = '';

try {
    if ($tipo === 'pgsql') {
        // PGGSSENCMODE y PGSSLMODE los toma libpq del entorno: el PostgreSQL de
        // CONAF rechaza la negociación GSSAPI.
        $cadena = sprintf(
            'host=%s port=%d dbname=%s user=%s password=%s connect_timeout=3',
            $host, $puerto, $nombre, $usuario, $clave
        );
        $con = @pg_connect($cadena);
        if ($con) {
            if (@pg_query($con, 'SELECT 1')) {
                $estado['db'] = 'ok';
            }
            @pg_close($con);
        }
    } else {
        $my = @mysqli_init();
        if ($my) {
            @mysqli_options($my, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
            if (@mysqli_real_connect($my, $host, $usuario, $clave, $nombre, $puerto)) {
                if (@mysqli_query($my, 'SELECT 1')) {
                    $estado['db'] = 'ok';
                }
                @mysqli_close($my);
            }
        }
    }
} catch (Throwable $e) {
    $detalle = $e->getMessage();
}

if ($estado['db'] !== 'ok') {
    // El detalle va al log del contenedor, nunca a la respuesta: llevaría la
    // topología interna y podría arrastrar credenciales.
    error_log(sprintf(
        'health: sin conexión a la base %s://%s:%d/%s%s',
        $tipo, $host, $puerto, $nombre, $detalle !== '' ? ' — ' . $detalle : ''
    ));
    $problemas[] = 'sin conexion a la base de datos';
}

// ─── Informativos: no afectan el veredicto, pero explican casi todos los
//     "el sitio responde raro" sin tener que entrar a buscar ───────────────────

// Comparar con la URL del navegador: si no coinciden, ahí está el problema.
$estado['wwwroot'] = env_val('MOODLE_WWWROOT', '(sin definir)');

// El modo mantención por CLI es solo este archivo, sin estado en la base. Si
// quedó de un upgrade interrumpido, Moodle responde una página de mantención a
// TODAS las peticiones y parece que la aplicación está caída. Ya pasó una vez:
// maintenance.php --enable escribió el archivo y falló después al leer la base,
// así que el --disable nunca lo borró. Se desactiva con:
//   rm -f /opt/moodledata/coipo_moodle/climaintenance.html
$estado['mantencion'] = file_exists(DATAROOT . '/climaintenance.html');

if ($problemas) {
    $estado['status']  = 'error';
    $estado['detalle'] = $problemas;
    http_response_code(503);
}

echo json_encode($estado), "\n";
