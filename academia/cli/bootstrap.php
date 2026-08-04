<?php
// Arranque compartido por todos los scripts de academia/cli/.
//
// Esta carpeta vive en /opt/academia dentro del contenedor, fuera de todo lo que
// Apache sirve, a propósito: así ningún script de provisión es alcanzable por
// HTTP ni por accidente.
//
// ─── OJO CON LAS RUTAS EN MOODLE 5.2 ────────────────────────────────────────
// El árbol se partió en dos y no es intuitivo cuál es cuál:
//
//   /var/www/html/           raíz del repositorio. Acá vive config.php
//   /var/www/html/public/    raíz web. Es el valor de $CFG->dirroot
//   /var/www/html/admin/cli/ los scripts CLI, que NO se movieron a public/
//
// Por eso MOODLE_DIRROOT apunta a la raíz del REPOSITORIO y no a $CFG->dirroot:
// lo que necesitamos cargar es config.php, y ese se quedó arriba. Una vez
// cargado, $CFG->dirroot ya vale /var/www/html/public y todo lo demás
// ($CFG->dirroot . '/course/lib.php') resuelve solo.

define('CLI_SCRIPT', true);

$academiadirroot = getenv('MOODLE_DIRROOT');
if ($academiadirroot === false || $academiadirroot === '') {
    $academiadirroot = '/var/www/html';
}

if (!is_readable($academiadirroot . '/config.php')) {
    fwrite(STDERR, "No se encuentra {$academiadirroot}/config.php\n" .
        "Definir MOODLE_DIRROOT si Moodle está en otra ruta.\n");
    exit(2);
}

require($academiadirroot . '/config.php');
require_once($CFG->libdir . '/clilib.php');

// Sin esto $USER es «nadie», y varias APIs de Moodle se comportan distinto:
// las comprobaciones de capacidad fallan, y los campos usermodified de todo lo
// que se cree quedan en 0. Se hace en el arranque y no en cada script porque
// olvidarlo produce errores que no se parecen en nada a su causa.
\core\session\manager::set_user(get_admin());

require_once(__DIR__ . '/lib.php');
