<?php
// Herramientas para construir cursos desde la línea de comandos.
//
// Las usan 80_plantilla_maestra.php y 90_cursos_esqueleto.php. Están aparte
// porque construir un curso en Moodle por API tiene tres detalles que se
// olvidan siempre y que no dan error cuando se olvidan:
//
//   1. La finalización de actividad. Sin ella, Moodle no sabe quién terminó
//      qué: no hay informes, no hay certificados y no hay competencias. El
//      estándar lo llama «el error más común».
//   2. Las restricciones de acceso. Necesitan el id del elemento de
//      calificación, que solo existe DESPUÉS de crear la actividad.
//   3. La idempotencia. Un curso se identifica por shortname; una actividad,
//      por su idnumber. Sin idnumber, volver a correr el script duplica todo.
//
// @package academia

defined('MOODLE_INTERNAL') || die();

// course/lib.php arrastra completionlib.php, de donde salen las constantes
// COMPLETION_*. resourcelib.php hay que pedirlo aparte: sin él,
// RESOURCELIB_DISPLAY_OPEN no existe y la Página se crea con display = 0.
require_once($GLOBALS['CFG']->dirroot . '/course/lib.php');
require_once($GLOBALS['CFG']->dirroot . '/course/modlib.php');
require_once($GLOBALS['CFG']->libdir . '/gradelib.php');
require_once($GLOBALS['CFG']->libdir . '/resourcelib.php');

/**
 * Comprueba que el sitio tenga encendido lo que estos scripts necesitan.
 *
 * Se llama al principio y aborta: crear medio curso y descubrir después que la
 * finalización estaba apagada obliga a borrarlo y empezar de nuevo, porque los
 * campos de finalización no se pueden rellenar hacia atrás.
 */
function academia_exigir_ajustes_de_curso(): void {
    global $CFG;

    $faltan = [];
    if (empty($CFG->enablecompletion)) {
        $faltan[] = 'enablecompletion (Finalización de actividad)';
    }
    if (empty($CFG->enableavailability)) {
        $faltan[] = 'enableavailability (Restricciones de acceso)';
    }

    if ($faltan) {
        cli_error("El sitio tiene apagado lo que este script necesita:\n  · " .
            implode("\n  · ", $faltan) . "\n\nEjecutar antes 95_ajustes_sitio.php.");
    }
}

/**
 * Crea un curso, o devuelve el que ya exista con ese shortname.
 *
 * @param array $spec shortname, fullname, idnumber, categoria (idnumber), summary,
 *                    visible, numsections, campos (shortname => etiqueta)
 * @return array{curso: stdClass, creado: bool}
 */
function academia_crear_curso(array $spec): array {
    global $DB;

    $existente = $DB->get_record('course', ['shortname' => $spec['shortname']]);
    if ($existente) {
        return ['curso' => $existente, 'creado' => false];
    }

    $categoria = $DB->get_record('course_categories', ['idnumber' => $spec['categoria']]);
    if (!$categoria) {
        throw new moodle_exception('errorgeneral', 'error', '',
            "no existe la categoría con idnumber '{$spec['categoria']}' — ejecutar 10_categorias.php");
    }

    $datos = (object)[
        'category'         => $categoria->id,
        'fullname'         => $spec['fullname'],
        'shortname'        => $spec['shortname'],
        'idnumber'         => $spec['idnumber'] ?? $spec['shortname'],
        'summary'          => $spec['summary'] ?? '',
        'summaryformat'    => FORMAT_HTML,
        'format'           => 'topics',
        'numsections'      => $spec['numsections'] ?? 1,
        // Oculto SIEMPRE al crearse. Un curso en construcción visible para
        // 2.869 personas es un curso que alguien va a empezar a hacer a medias.
        'visible'          => 0,
        'enablecompletion' => 1,
        'showgrades'       => 1,
        'startdate'        => usergetmidnight(time()),
    ];

    $curso = create_course($datos);
    course_create_sections_if_missing($curso, range(0, $datos->numsections));

    return ['curso' => $curso, 'creado' => true];
}

/**
 * Completa los campos personalizados de un curso a partir de sus etiquetas.
 *
 * Recibe etiquetas legibles («01 Incendios Forestales») y no índices, porque un
 * script que hable en índices es imposible de revisar. La conversión a lo que
 * Moodle guarda de verdad la hace academia_indice_de_opcion().
 *
 * @param array<string,string|int> $valores shortname del campo => etiqueta
 * @return string[] Los campos que no se pudieron completar, con su motivo.
 */
function academia_completar_campos(stdClass $curso, array $valores): array {
    $manejador = \core_course\customfield\course_handler::create();

    $campos = [];
    foreach ($manejador->get_categories_with_fields() as $cat) {
        foreach ($cat->get_fields() as $campo) {
            $campos[$campo->get('shortname')] = $campo;
        }
    }

    $datos = (object)['id' => $curso->id];
    $problemas = [];

    foreach ($valores as $shortname => $valor) {
        if (!isset($campos[$shortname])) {
            $problemas[] = "$shortname: el campo no existe (¿falta 20_campos_curso.php?)";
            continue;
        }
        $campo = $campos[$shortname];

        if ($campo->get('type') === 'select') {
            $indice = academia_indice_de_opcion($campo, (string)$valor);
            if ($indice === null) {
                $problemas[] = "$shortname: '$valor' no está entre las opciones del campo";
                continue;
            }
            $datos->{'customfield_' . $shortname} = $indice;
        } else {
            $datos->{'customfield_' . $shortname} = $valor;
        }
    }

    $manejador->instance_form_save($datos);

    return $problemas;
}

/**
 * Pone nombre y resumen a una sección del curso.
 *
 * @param array<int,array{nombre: string, resumen?: string}> $secciones indexado por número de sección
 */
function academia_nombrar_secciones(stdClass $curso, array $secciones): void {
    global $DB;

    foreach ($secciones as $numero => $datos) {
        $seccion = $DB->get_record('course_sections',
            ['course' => $curso->id, 'section' => $numero]);
        if (!$seccion) {
            continue;
        }
        course_update_section($curso, $seccion, (object)[
            'name'          => $datos['nombre'],
            'summary'       => $datos['resumen'] ?? '',
            'summaryformat' => FORMAT_HTML,
        ]);
    }
}

/**
 * Agrega una actividad al curso, o devuelve la que ya exista con ese idnumber.
 *
 * @param string $modulo nombre del módulo: page, quiz, folder, forum, customcert, h5pactivity...
 * @param array $datos campos propios del módulo, más:
 *                     idnumber      obligatorio — es la clave de idempotencia
 *                     name          obligatorio
 *                     intro         descripción en HTML
 *                     seccion       número de sección
 *                     completion    COMPLETION_TRACKING_*
 *                     completionview, completionusegrade, completionpassgrade
 *                     availability  JSON de restricciones
 * @return array{cm: stdClass, creado: bool}
 */
function academia_agregar_modulo(stdClass $curso, string $modulo, array $datos): array {
    global $DB;

    if (empty($datos['idnumber'])) {
        throw new coding_exception('Toda actividad necesita idnumber: es la clave de idempotencia.');
    }

    // Se busca por idnumber DENTRO del curso: el idnumber de course_modules no
    // es único en el sitio, solo dentro del curso.
    $existente = $DB->get_record_sql(
        'SELECT cm.* FROM {course_modules} cm WHERE cm.course = ? AND cm.idnumber = ?',
        [$curso->id, $datos['idnumber']]);
    if ($existente) {
        return ['cm' => $existente, 'creado' => false];
    }

    $registro = $DB->get_record('modules', ['name' => $modulo], '*', MUST_EXIST);

    $info = (object)[
        'modulename'          => $modulo,
        'module'              => $registro->id,
        'course'              => $curso->id,
        'section'             => $datos['seccion'] ?? 0,
        'visible'             => $datos['visible'] ?? 1,
        'visibleoncoursepage' => 1,
        'cmidnumber'          => $datos['idnumber'],
        'name'                => $datos['name'],
        'introeditor'         => [
            'text'   => $datos['intro'] ?? '',
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ],
        'showdescription'     => $datos['showdescription'] ?? 0,
        'completion'          => $datos['completion'] ?? COMPLETION_TRACKING_NONE,
        'completionview'      => $datos['completionview'] ?? 0,
        'completionusegrade'  => $datos['completionusegrade'] ?? 0,
        'completionpassgrade' => $datos['completionpassgrade'] ?? 0,
        'completionexpected'  => 0,
        'availability'        => $datos['availability'] ?? null,
    ];

    foreach ($datos as $clave => $valor) {
        if (in_array($clave, ['idnumber', 'name', 'intro', 'seccion', 'visible', 'completion',
                'completionview', 'completionusegrade', 'completionpassgrade', 'availability',
                'showdescription'], true)) {
            continue;
        }
        $info->{$clave} = $valor;
    }

    $resultado = add_moduleinfo($info, $curso);

    $cm = $DB->get_record('course_modules', ['id' => $resultado->coursemodule], '*', MUST_EXIST);
    return ['cm' => $cm, 'creado' => true];
}

/**
 * Devuelve el id del elemento de calificación de una actividad.
 *
 * Solo existe DESPUÉS de crear la actividad, y es lo que necesitan las
 * restricciones por calificación. De ahí que la cadena de restricciones de un
 * curso haya que armarla en una segunda pasada, no mientras se crean las
 * actividades.
 */
function academia_gradeitem(stdClass $curso, string $modulo, int $instancia): ?int {
    global $DB;

    $item = $DB->get_record('grade_items', [
        'courseid'     => $curso->id,
        'itemtype'     => 'mod',
        'itemmodule'   => $modulo,
        'iteminstance' => $instancia,
        'itemnumber'   => 0,
    ], 'id');

    return $item ? (int)$item->id : null;
}

/**
 * Restricción: haber obtenido al menos $porcentaje en un elemento de calificación.
 *
 * El valor es un PORCENTAJE (0-100), no la nota bruta. Poner 4 pensando en «4 de
 * 5» produce una restricción que se cumple con el 4 % — y nadie lo nota, porque
 * el curso simplemente deja pasar a todo el mundo.
 */
function academia_restriccion_nota(int $gradeitemid, float $porcentaje): array {
    return ['type' => 'grade', 'id' => $gradeitemid, 'min' => $porcentaje];
}

/**
 * Restricción: haber completado otra actividad.
 *
 * @param int $tipo COMPLETION_COMPLETE (basta con marcarla) o
 *                  COMPLETION_COMPLETE_PASS (además hay que aprobarla)
 */
function academia_restriccion_completado(int $cmid, int $tipo = COMPLETION_COMPLETE): array {
    return ['type' => 'completion', 'cm' => $cmid, 'e' => $tipo];
}

/**
 * Junta condiciones con «Y» y devuelve el JSON que espera course_modules.availability.
 *
 * `showc` en false por cada condición: la actividad bloqueada se muestra en gris
 * con el motivo, en vez de desaparecer. Es lo que hace que el alumno entienda el
 * itinerario —«no disponible hasta aprobar la evaluación de la lección 3»— en
 * lugar de ver un curso que cambia de forma solo.
 */
function academia_restricciones(array $condiciones): ?string {
    if (!$condiciones) {
        return null;
    }
    return json_encode([
        'op'    => '&',
        'c'     => array_values($condiciones),
        'showc' => array_fill(0, count($condiciones), true),
    ]);
}

/**
 * Aplica un JSON de restricciones a una actividad ya creada.
 */
function academia_aplicar_restricciones(stdClass $cm, ?string $json): void {
    global $DB;

    if ($json === null || $cm->availability === $json) {
        return;
    }
    $DB->set_field('course_modules', 'availability', $json, ['id' => $cm->id]);
    rebuild_course_cache($cm->course, true);
}

/**
 * Vincula una competencia al curso, por su idnumber.
 *
 * Sin esto, la competencia existe en el marco y no la acredita nadie nunca: es
 * el paso que convierte «aprobó el curso» en «está habilitado».
 */
function academia_vincular_competencia(stdClass $curso, string $idnumbercompetencia): bool {
    global $DB;

    $competencia = $DB->get_record('competency', ['idnumber' => $idnumbercompetencia], 'id');
    if (!$competencia) {
        return false;
    }

    if ($DB->record_exists('competency_coursecomp',
            ['courseid' => $curso->id, 'competencyid' => $competencia->id])) {
        return true;
    }

    \core_competency\api::add_competency_to_course($curso->id, $competencia->id);

    // Regla de logro: al completar el curso, la competencia se da por
    // demostrada. Sin regla, la competencia queda vinculada pero nunca se
    // alcanza sola y alguien tiene que calificarla a mano, persona por persona.
    \core_competency\api::set_course_competency_ruleoutcome(
        $DB->get_field('competency_coursecomp', 'id',
            ['courseid' => $curso->id, 'competencyid' => $competencia->id]),
        \core_competency\course_competency::OUTCOME_COMPLETE
    );

    return true;
}

/**
 * Agrega el método de matriculación por sincronización de cohorte.
 *
 * «No usar auto-matriculación. Si la base técnica es un deber institucional, no
 * puede depender de que cada uno decida inscribirse. Y solo la matrícula por
 * cohorte permite medir cuántos de los que debían, lo hicieron.»
 * — IF-151_Diseno-de-curso, Parte 5.5
 */
function academia_matricular_cohorte(stdClass $curso, string $idnumbercohorte): string {
    global $DB, $CFG;

    $cohorte = $DB->get_record('cohort', ['idnumber' => $idnumbercohorte], 'id, name');
    if (!$cohorte) {
        return "no existe la cohorte $idnumbercohorte";
    }

    $plugin = enrol_get_plugin('cohort');
    if (!$plugin) {
        return 'el método de matriculación por cohorte no está habilitado en el sitio';
    }

    $yaesta = $DB->record_exists('enrol',
        ['courseid' => $curso->id, 'enrol' => 'cohort', 'customint1' => $cohorte->id]);
    if ($yaesta) {
        return '';
    }

    $rolestudiante = $DB->get_record('role', ['shortname' => 'student'], 'id', MUST_EXIST);

    $plugin->add_instance($curso, [
        'customint1' => $cohorte->id,       // la cohorte
        'roleid'     => $rolestudiante->id,
        'name'       => 'Cohorte ' . $cohorte->name,
    ]);

    return '';
}
