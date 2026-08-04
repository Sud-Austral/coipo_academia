<?php
// Compila el SCSS de theme_academia y comprueba que las reglas del estándar
// llegaron de verdad al CSS.
//
// POR QUÉ EXISTE. Un error de SCSS no da un error: da un sitio sin estilos. La
// página carga, el contenido está, y todo se ve como HTML de 1995 — sin ningún
// mensaje que explique por qué. Es exactamente el modo de fallo que este
// proyecto ya sufrió al cambiar wwwroot.
//
// Y comprobar que compila no basta. Una regla con un selector mal anidado
// compila perfecto y no produce nada. Por eso, además de compilar, se busca cada
// regla del estándar dentro del CSV de salida.
//
// CÓMO SE EJECUTA. Necesita el árbol de Moodle, no la base de datos:
//
//   php -d extension=mbstring -d memory_limit=512M \
//       academia/pruebas/compilar-tema.php [ruta/al/moodle]
//
// Dentro del contenedor la ruta por defecto ya es la correcta:
//
//   docker compose exec app php /opt/academia/pruebas/compilar-tema.php
//
// La ruta es /var/www/html/public y no /var/www/html: en Moodle 5.2 la raíz web
// se movió a public/, y ahí es donde están lib/scssphp y theme/boost. Es el
// mismo valor que $CFG->dirroot, pero esta prueba no carga Moodle —a propósito,
// para poder correrla sin base de datos— así que lo lleva escrito.

$dirroot = $argv[1] ?? '/var/www/html/public';
$tema = dirname(__DIR__, 2) . '/plugins/theme/academia';

if (!is_dir($dirroot . '/lib/scssphp')) {
    fwrite(STDERR, "No se encuentra $dirroot/lib/scssphp\n" .
        "Pasar la ruta del árbol de Moodle como primer argumento.\n");
    exit(2);
}
if (!is_dir($tema)) {
    fwrite(STDERR, "No se encuentra el tema en $tema\n");
    exit(2);
}

// Moodle carga scssphp con su propio autoloader; acá basta un PSR-4 mínimo.
//
// Las dos disposiciones son a propósito: en Moodle 4.5 la biblioteca está
// aplanada en lib/scssphp/, y en 5.2 pasó a lib/scssphp/src/ siguiendo el
// empaquetado de upstream. Probar las dos evita que esta prueba se caiga por un
// detalle de empaquetado en vez de por un error del tema, que es lo que mide.
spl_autoload_register(function ($clase) use ($dirroot) {
    $prefijo = 'ScssPhp\\ScssPhp\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $relativa = str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    foreach (["$dirroot/lib/scssphp/src/$relativa", "$dirroot/lib/scssphp/$relativa"] as $ruta) {
        if (is_readable($ruta)) {
            require_once($ruta);
            return;
        }
    }
});

// El mismo orden que arma outputlib: variables, luego Bootstrap, luego reglas.
// Compilar en otro orden pasa igual y produce un CSS distinto del real.
$pre  = file_get_contents($tema . '/scss/pre.scss');
$main = file_get_contents($dirroot . '/theme/boost/scss/preset/default.scss');
$post = file_get_contents($tema . '/scss/post.scss');

$compilador = new ScssPhp\ScssPhp\Compiler();
$compilador->setImportPaths([
    $dirroot . '/theme/boost/scss/',
    $dirroot . '/theme/',
    $tema . '/scss/',
]);

$inicio = microtime(true);
try {
    $css = $compilador->compileString($pre . "\n" . $main . "\n" . $post)->getCss();
} catch (Throwable $e) {
    fwrite(STDERR, "FALLA AL COMPILAR — el sitio saldría sin estilos:\n\n" .
        $e->getMessage() . "\n");
    exit(1);
}
$ms = (int)round((microtime(true) - $inicio) * 1000);

printf("Compiló en %d ms · %s KB de CSS\n\n", $ms, number_format(strlen($css) / 1024, 1));

// ─── Cada regla del estándar, buscada en el CSS de salida ───────────────────
// La columna de la derecha dice de dónde sale la exigencia.
$comprobaciones = [
    'azul institucional #143A73 como color primario' => '/#143a73/i',
    'interlineado 1,6 (el estándar pide mínimo 1,5)' => '/line-height:\s*1\.6/',
    'texto de cuerpo a 16 px (1rem)'                 => '/font-size:\s*1rem/',
    'enlaces subrayados, no solo de color'           => '/text-decoration:\s*underline/',
    'foco de teclado con contorno visible'           => '/outline:\s*3px solid #1e5fa8/i',
    'alineación a la izquierda forzada'              => '/text-align:\s*left/',
    'largo de línea acotado a 72ch'                  => '/max-width:\s*72ch/',
    'estados ok / atención / riesgo con ícono'       => '/\.academia-estado--(ok|atencion|riesgo)/',
    // Que la clase exista no basta. El ícono es lo que impide que el significado
    // viaje SOLO en el color (WCAG 1.4.1), y se dibuja con una familia de fuente
    // concreta: si el nombre está mal, el ::before no pinta nada y no se nota
    // hasta que alguien que no distingue el color no entiende el estado.
    //
    // Esta comprobación existe porque el nombre estuvo mal: decía "FontAwesome",
    // que es de FontAwesome 4 y no existe en Moodle desde hace años.
    'la familia de íconos es "Font Awesome 6 Free"'  => '/font-family:\s*"Font Awesome 6 Free"/',
    'y con el peso 900, que selecciona el juego solid' => '/font-family:\s*"Font Awesome 6 Free";\s*font-weight:\s*900/',
    'tablas de informe con su propio scroll'         => '/overflow-x:\s*auto/',
    'reglas de impresión para las fichas'            => '/@media print/',
    'imágenes que no desbordan la pantalla'          => '/max-width:\s*100%/',
];

$fallos = 0;
foreach ($comprobaciones as $que => $patron) {
    $ok = (bool)preg_match($patron, $css);
    printf("  %s  %s\n", $ok ? '  ok' : 'FALTA', $que);
    if (!$ok) {
        $fallos++;
    }
}

echo "\n";
if ($fallos === 0) {
    echo "OK — " . count($comprobaciones) . " reglas del estándar presentes en el CSS.\n";
    echo "\nEsto comprueba que el CSS se genera. Que el sitio SE VEA bien es otra cosa:\n";
    echo "hay que abrirlo en un navegador y en un teléfono real.\n";
    exit(0);
}
echo "FALLA — $fallos regla(s) no llegaron al CSS compilado.\n";
exit(1);
