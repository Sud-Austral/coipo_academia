<?php
// Crea las vistas de la Academia como informes personalizados nativos.
//
// El catálogo filtrable de la pantalla 02 y el tablero de la pantalla 08 no
// necesitan ningún plugin: el generador de informes de Moodle 4.5 expone los
// campos personalizados de curso como columnas Y como filtros, automáticamente.
// Está verificado en el código:
//   reportbuilder/classes/local/entities/course.php:84 usa el helper custom_fields
//
// PERO ESTO NO SE PUEDE EJECUTAR ANTES QUE 20 Y 60, y no es un orden caprichoso:
// un informe no puede filtrar por una columna que no existe, y una columna vacía
// filtra a cero. Primero el modelo de datos, después el tablero.
//
// LÍMITE HONESTO. El generador de informes da tabla, filtros, ordenamiento y
// descarga. NO da las tarjetas ni los gráficos de barras del prototipo: esas
// pantallas son ilustrativas. Para un tablero con esa factura, el camino que el
// propio prototipo señala es una réplica de solo lectura de PostgreSQL conectada
// a Power BI, construida por la UIA. Eso queda fuera de este repositorio.
//
//   docker compose exec -u www-data app php /opt/academia/cli/70_informes.php --dry-run
//   docker compose exec -u www-data app php /opt/academia/cli/70_informes.php

require(__DIR__ . '/bootstrap.php');

use core_reportbuilder\local\helpers\report as report_helper;
use core_reportbuilder\manager;
use core_reportbuilder\reportbuilder\audience\allusers;
use core_reportbuilder\reportbuilder\audience\systemrole;

$opciones = academia_cli_inicio(
    'Crea el catálogo filtrable y el tablero de gestión como informes personalizados.');

$reporte = new academia_reporte('Informes', $opciones['dry-run']);

// ─── Definición de las vistas ───────────────────────────────────────────────
$vistas = [

    // Pantalla 02. Es la vista que justifica todo el trabajo de arquitectura de
    // información: cada filtro de acá es un campo que alguien tuvo que definir y
    // completar. Sin ese trabajo, ninguna herramienta —ni Moodle, ni Power BI, ni
    // la más cara del mercado— responde «cuántos jefes de cuadrilla del Biobío
    // están certificados y vigentes».
    'catalogo' => [
        'nombre'    => 'Academia · Catálogo de cursos',
        'source'    => \core_course\reportbuilder\datasource\courses::class,
        'audiencia' => 'todos',
        'columnas'  => [
            'course:coursefullnamewithlink',
            'course:shortname',
            'course_category:name',
            'course:customfield_area',
            'course:customfield_nivel',
            'course:customfield_perfil',
            'course:customfield_modalidad',
            'course:customfield_duracion',
            'course:customfield_vigencia',
            'course:customfield_estado',
        ],
        // Los cuatro filtros de la pantalla 02, más el estado del ciclo de vida:
        // sin ese último, el catálogo mezcla lo vigente con lo archivado.
        'filtros'   => [
            'course:customfield_area',
            'course:customfield_nivel',
            'course:customfield_perfil',
            'course:customfield_modalidad',
            'course:customfield_estado',
            'course:fullname',
        ],
    ],

    // Pantalla 08, la parte que el generador nativo sí puede dar: el inventario
    // de cursos por área, nivel y estado, con su carga formativa. Es lo que
    // responde «cuánto contenido está vencido o en revisión».
    'tablero-cursos' => [
        'nombre'    => 'Academia · Gestión — cursos por área y estado',
        'source'    => \core_course\reportbuilder\datasource\courses::class,
        'audiencia' => 'gestores',
        'columnas'  => [
            'course:customfield_area',
            'course:customfield_estado',
            'course:customfield_nivel',
            'course:coursefullnamewithlink',
            'course:customfield_perfil',
            'course:customfield_duracion',
            'course:customfield_vigencia',
            'course:customfield_financiamiento',
            'course:visible',
            'course:timemodified',
        ],
        'filtros'   => [
            'course:customfield_area',
            'course:customfield_estado',
            'course:customfield_financiamiento',
            'course:visible',
        ],
    ],

    // La otra mitad del tablero: quién está en qué cohorte. Es lo que convierte
    // «región» y «perfil» en algo consultable, y la base de cualquier informe de
    // cobertura por región. Hoy esta pregunta no tiene respuesta porque hay cero
    // cohortes.
    'tablero-cohortes' => [
        'nombre'    => 'Academia · Gestión — dotación por cohorte',
        'source'    => \core_cohort\reportbuilder\datasource\cohorts::class,
        'audiencia' => 'gestores',
        'columnas'  => [
            'cohort:name',
            'cohort:idnumber',
            'cohort:context',
            'user:fullname',
            'user:email',
        ],
        'filtros'   => [
            'cohort:name',
            'cohort:idnumber',
        ],
    ],
];

// ─── Crear ──────────────────────────────────────────────────────────────────
foreach ($vistas as $clave => $vista) {
    $nombre = $vista['nombre'];

    $existente = $DB->get_record('reportbuilder_report', ['name' => $nombre], 'id, name, source');
    if ($existente) {
        $reporte->existia($nombre, "id {$existente->id} — /reportbuilder/view.php?id={$existente->id}");
        continue;
    }

    if (!class_exists($vista['source'])) {
        $reporte->error($nombre, "no existe el datasource {$vista['source']}");
        continue;
    }

    if ($reporte->es_simulacion()) {
        $reporte->creado($nombre, count($vista['columnas']) . ' columnas · ' .
            count($vista['filtros']) . ' filtros');
        continue;
    }

    // default:false — se arma la lista de columnas a mano. Con las de por
    // defecto, el catálogo sale con columnas que no le sirven a nadie y sin los
    // campos personalizados, que son justamente el punto.
    try {
        $informe = report_helper::create_report((object)[
            'name'       => $nombre,
            'source'     => $vista['source'],
            'uniquerows' => 0,
            'contextid'  => context_system::instance()->id,
        ], false);
    } catch (Exception $e) {
        $reporte->error($nombre, $e->getMessage());
        continue;
    }

    $idinforme = (int)$informe->get('id');
    $reporte->creado($nombre, "id $idinforme");

    // Los identificadores de columna dependen de la versión de Moodle. En vez de
    // fallar con «Invalid column», que no dice cuál ni cuáles había, se compara
    // contra la lista que el propio informe declara y se muestra lo parecido.
    $instancia = manager::get_report_from_id($idinforme);
    $columnasdisponibles = array_keys($instancia->get_columns());
    $filtrosdisponibles  = array_keys($instancia->get_filters());

    foreach ($vista['columnas'] as $identificador) {
        if (!in_array($identificador, $columnasdisponibles, true)) {
            $reporte->error("$nombre → columna $identificador",
                'no existe. Parecidas: ' . academia_parecidos($identificador, $columnasdisponibles));
            continue;
        }
        report_helper::add_report_column($idinforme, $identificador);
    }

    foreach ($vista['filtros'] as $identificador) {
        if (!in_array($identificador, $filtrosdisponibles, true)) {
            $reporte->error("$nombre → filtro $identificador",
                'no existe. Parecidos: ' . academia_parecidos($identificador, $filtrosdisponibles));
            continue;
        }
        report_helper::add_report_filter($idinforme, $identificador);
    }

    // ─── Quién lo ve ────────────────────────────────────────────────────────
    if ($vista['audiencia'] === 'todos') {
        // El catálogo es para todo el personal: es la puerta de entrada.
        allusers::create($idinforme, []);
        $reporte->corregido("$nombre → audiencia", 'todos los usuarios del sitio');
    } else {
        // El tablero, no. Muestra la brecha de formación de personas concretas:
        // es información de gestión y no se publica a todo el mundo «por
        // comodidad». Hoy detrás hay un puñado de cuentas de construcción, así que
        // la restricción todavía no protege a nadie — se pone ahora porque
        // ponerla el día que entren las personas reales es el día en que a nadie
        // se le ocurre.
        $rolgestor = $DB->get_record('role', ['shortname' => 'gestorarea'], 'id');
        $roles = $rolgestor ? [(int)$rolgestor->id] : [];
        if (!$roles) {
            $reporte->omitido("$nombre → audiencia",
                'no existe el rol gestorarea; el informe queda solo para administradores. ' .
                'Ejecutar 50_rol_gestor.php y volver a crear este informe.');
        } else {
            systemrole::create($idinforme, ['roles' => $roles]);
            $reporte->corregido("$nombre → audiencia", 'rol Gestor de Área Temática');
        }
    }
}

academia_purgar_caches($reporte);

$codigo = $reporte->resumen();

// ─── Dónde quedaron ─────────────────────────────────────────────────────────
cli_writeln('');
cli_writeln('Los informes quedan en:');
foreach ($DB->get_records_select('reportbuilder_report', $DB->sql_like('name', ':patron'),
        ['patron' => 'Academia · %'], 'name', 'id, name') as $r) {
    cli_writeln(sprintf('  %s/reportbuilder/view.php?id=%d   %s', $CFG->wwwroot, $r->id, $r->name));
}

cli_writeln('');
cli_writeln('VERIFICACIÓN QUE VALE: abrir el catálogo en el navegador, aplicar un filtro de');
cli_writeln('área y ver que la lista cambia. Con el sitio recién instalado sale vacío y eso');
cli_writeln('es correcto: no hay cursos. La prueba de verdad va después de 80 y 90, con');
cli_writeln('GC-000, IF-151 y TR-104 dentro. Si con ellos sigue vacío, el problema no está');
cli_writeln('en el informe: son cursos sin clasificar. Correr 60_clasificar_cursos.php.');

exit($codigo);


/**
 * Devuelve los identificadores más parecidos, para que un error de nombre se
 * pueda arreglar leyendo el mensaje en vez de leyendo el código de Moodle.
 */
function academia_parecidos(string $buscado, array $disponibles): string {
    $sufijo = str_contains($buscado, ':') ? explode(':', $buscado, 2)[1] : $buscado;
    $parecidos = array_filter($disponibles,
        fn($d) => str_contains($d, $sufijo) || levenshtein($d, $buscado) <= 6);
    $parecidos = array_slice(array_values($parecidos), 0, 6);
    return $parecidos ? implode(', ', $parecidos) : '(ninguno; hay ' . count($disponibles) . ' en total)';
}
