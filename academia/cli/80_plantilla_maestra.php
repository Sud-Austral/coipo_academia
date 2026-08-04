<?php
// Crea GC-000 · Plantilla maestra de curso, en la categoría 99 Gestión del Campus.
//
// «La regla que ahorra el 80 % del trabajo: nunca crees un curso desde cero.
// Siempre duplica la plantilla maestra, que ya trae la estructura, la
// configuración de finalización, el certificado y los textos de ayuda. Tu
// trabajo es reemplazar contenido, no construir andamios.»
//   — prototipo pantalla 14, guía operativa
//
// Esa regla solo es cierta si la plantilla existe. Este script la construye.
//
// La plantilla queda OCULTA y su estado es Archivado: no es un curso que alguien
// deba cursar, es un molde. Se duplica desde el propio Moodle con
// Configuración → Duplicar curso, o desde la administración de la categoría.
//
//   docker compose exec -u www-data app php /opt/academia/cli/80_plantilla_maestra.php --dry-run
//   docker compose exec -u www-data app php /opt/academia/cli/80_plantilla_maestra.php

require(__DIR__ . '/bootstrap.php');
require_once(__DIR__ . '/lib_cursos.php');
require_once(__DIR__ . '/lib_quiz.php');

const ACADEMIA_PLANTILLA = 'GC-000';

$opciones = academia_cli_inicio('Crea la plantilla maestra de curso GC-000.');

academia_exigir_ajustes_de_curso();

$reporte = new academia_reporte('Plantilla maestra de curso', $opciones['dry-run']);

if ($reporte->es_simulacion()) {
    cli_writeln('  (la simulación de este script solo comprueba requisitos: crear un curso');
    cli_writeln('   con sus actividades no se puede simular sin escribir en la base)');
    $existe = $DB->record_exists('course', ['shortname' => ACADEMIA_PLANTILLA]);
    $existe ? $reporte->existia(ACADEMIA_PLANTILLA) : $reporte->creado(ACADEMIA_PLANTILLA);
    exit($reporte->resumen());
}

// ─── El curso ───────────────────────────────────────────────────────────────
try {
    ['curso' => $curso, 'creado' => $creado] = academia_crear_curso([
        'shortname'   => ACADEMIA_PLANTILLA,
        'fullname'    => 'GC-000 · Plantilla maestra de curso',
        'idnumber'    => ACADEMIA_PLANTILLA,
        'categoria'   => '99',
        'numsections' => 3,
        'summary'     => '<p><strong>No cursar. Este curso es un molde.</strong></p>' .
            '<p>Para crear un curso nuevo se duplica esta plantilla: trae la estructura, la ' .
            'finalización de actividad, las restricciones encadenadas y el certificado ya ' .
            'configurados. Cada sección incluye instrucciones que el autor borra al terminar.</p>' .
            '<p>La duplica el <strong>Gestor del Área Temática</strong> a partir de una solicitud ' .
            'del autor. Ver la guía operativa de la Academia.</p>',
    ]);
} catch (moodle_exception $e) {
    cli_error($e->getMessage());
}

if (!$creado) {
    $reporte->existia(ACADEMIA_PLANTILLA, "id {$curso->id}");
    cli_writeln('');
    cli_writeln('La plantilla ya existía y NO se toca: puede tener trabajo hecho encima.');
    cli_writeln('Para rehacerla desde cero, borrarla primero desde la interfaz.');
    exit($reporte->resumen());
}

$reporte->creado(ACADEMIA_PLANTILLA, "curso id {$curso->id} · oculto");

// Los ocho campos, completos también acá. Un curso sin clasificar es invisible
// para el catálogo y para los informes — incluida la plantilla, que si no,
// nadie encuentra.
$problemas = academia_completar_campos($curso, [
    'area'           => '99 Gestión del Campus',
    'nivel'          => 'Básico',
    'perfil'         => 'Profesional',
    'modalidad'      => 'e-learning autoinstruccional',
    'duracion'       => 1,
    'vigencia'       => 0,
    'financiamiento' => 'CONAF',
    'estado'         => 'Archivado',
]);
foreach ($problemas as $p) {
    $reporte->error('campos personalizados', $p);
}
if (!$problemas) {
    $reporte->corregido('campos personalizados', 'los 8 completos');
}

// ─── Las secciones ──────────────────────────────────────────────────────────
academia_nombrar_secciones($curso, [
    0 => [
        'nombre'  => 'Antes de empezar',
        'resumen' => '<p><em>Esta sección se borra al terminar el curso.</em></p>' .
            '<p>Los cinco momentos que debe tener cada lección: <strong>gancho</strong> ' .
            '(por qué esto te importa a ti), <strong>idea</strong> (la regla en menos de 25 ' .
            'palabras), <strong>desarrollo</strong> (el detalle con ejemplos de terreno), ' .
            '<strong>práctica</strong> (un escenario con decisión y consecuencia) y ' .
            '<strong>algo para llevar</strong> (una tarjeta descargable).</p>' .
            '<p>Una lección enseña una sola cosa y dura entre 5 y 12 minutos. Si tu lección ' .
            'tiene tres objetivos, son tres lecciones.</p>',
    ],
    1 => [
        'nombre'  => 'Presentación',
        'resumen' => '<p>Qué va a poder hacer la persona al terminar, y cómo funciona el curso. ' .
            'Nunca empieces con una definición.</p>',
    ],
    2 => [
        'nombre'  => 'Lección 1 · (reemplazar por el título)',
        'resumen' => '<p>Duplica esta sección tantas veces como lecciones tenga tu curso. ' .
            'Cada una: contenido interactivo + material de referencia + su evaluación.</p>',
    ],
    3 => [
        'nombre'  => 'Certificación',
        'resumen' => '<p>La evaluación final y el certificado. El certificado se condiciona a ' .
            'la evaluación final, no a la finalización del curso.</p>',
    ],
]);
$reporte->corregido('secciones', '4 con nombre y guía');

// ─── Sección 1 · Presentación ───────────────────────────────────────────────
['cm' => $cmpresentacion] = academia_agregar_modulo($curso, 'page', [
    'idnumber'         => 'GC000-PRESENTACION',
    'seccion'          => 1,
    'name'             => 'Para quién es este curso y qué vas a poder hacer al terminar',
    'intro'            => '<p>Página de bienvenida. Reemplazar el contenido.</p>',
    'content'          => academia_texto_presentacion(),
    'contentformat'    => FORMAT_HTML,
    'display'          => RESOURCELIB_DISPLAY_OPEN,
    'printheading'     => 1,
    'printintro'       => 0,
    'printlastmodified' => 1,
    'completion'       => COMPLETION_TRACKING_AUTOMATIC,
    'completionview'   => 1,
]);
$reporte->creado('Página de presentación');

['cm' => $cmforo] = academia_agregar_modulo($curso, 'forum', [
    'idnumber'     => 'GC000-FORO',
    'seccion'      => 1,
    'name'         => 'Foro de consultas al instructor',
    'intro'        => '<p>Para preguntas sobre el contenido. La participación es opcional.</p>',
    'type'         => 'general',
    // 0 = FORUM_CHOOSESUBSCRIBE. Va el literal porque mod/forum/lib.php todavía
    // no está cargado acá: lo carga add_moduleinfo() con include_modulelib().
    'forcesubscribe' => 0,
    'completion'   => COMPLETION_TRACKING_MANUAL,
]);
$reporte->creado('Foro de consultas');

['cm' => $cmmaterial] = academia_agregar_modulo($curso, 'folder', [
    'idnumber'       => 'GC000-MATERIAL',
    'seccion'        => 1,
    'name'           => 'Documentos de base',
    'intro'          => '<p>Manuales, protocolos y normativa. <strong>Esto es material de ' .
        'referencia, no una lección.</strong> Si estás por subir un PDF de 40 páginas y ' .
        'llamarlo lección, detente: la lección es lo que la persona hace.</p>',
    'display'        => 0,
    'showexpanded'   => 1,
    'completion'     => COMPLETION_TRACKING_AUTOMATIC,
    'completionview' => 1,
]);
$reporte->creado('Carpeta de material de referencia');

// ─── Sección 2 · Lección 1 ──────────────────────────────────────────────────
// El contenido va en H5P. Se deja una Página como marcador porque un H5P vacío
// no se puede crear por API sin un archivo .h5p, y una plantilla con una
// actividad rota es peor que una con una instrucción clara.
['cm' => $cmleccion] = academia_agregar_modulo($curso, 'page', [
    'idnumber'         => 'GC000-L1-CONTENIDO',
    'seccion'          => 2,
    'name'             => 'Contenido interactivo de la lección 1',
    'intro'            => '<p>Reemplazar por una actividad <strong>H5P</strong>.</p>',
    'content'          => academia_texto_leccion(),
    'contentformat'    => FORMAT_HTML,
    'display'          => RESOURCELIB_DISPLAY_OPEN,
    'printheading'     => 1,
    'printintro'       => 0,
    'printlastmodified' => 1,
    'completion'       => COMPLETION_TRACKING_AUTOMATIC,
    'completionview'   => 1,
]);
$reporte->creado('Marcador de contenido de la lección 1');

['cm' => $cmevaluacion] = academia_agregar_modulo($curso, 'quiz',
    academia_quiz_formativo([
        'idnumber' => 'GC000-L1-EVALUACION',
        'seccion'  => 2,
        'name'     => 'Evaluación de la lección 1',
        'intro'    => '<p>Entre 5 y 8 preguntas. <strong>Cada una plantea una situación de ' .
            'trabajo; ninguna pide una definición.</strong> Toda respuesta incorrecta explica ' .
            'por qué lo es — una evaluación sin retroalimentación es un trámite, no una ' .
            'instancia de aprendizaje.</p>',
        'grade'    => 5.0,
        'aprobar'  => 4.0,
    ]));
$reporte->creado('Cuestionario formativo de la lección 1', 'aprueba con 4 de 5 · intentos ilimitados');

// La evaluación de la lección exige haber completado su contenido.
academia_aplicar_restricciones($cmevaluacion, academia_restricciones([
    academia_restriccion_completado($cmleccion->id, COMPLETION_COMPLETE),
]));
$reporte->corregido('restricción', 'la evaluación 1 requiere completar su contenido');

// ─── Sección 3 · Certificación ──────────────────────────────────────────────
['cm' => $cmfinal] = academia_agregar_modulo($curso, 'quiz',
    academia_quiz_certificacion([
        'idnumber' => 'GC000-FINAL',
        'seccion'  => 3,
        'name'     => 'Evaluación final de certificación',
        'intro'    => '<p>Se arma con <strong>preguntas aleatorias</strong> de un banco ' .
            'reservado que el participante no vio antes. Un examen con las mismas preguntas ' .
            'que las evaluaciones de lección, intentos ilimitados y calificación por el mejor ' .
            'intento no certifica nada: entrega las respuestas y después conserva el mejor ' .
            'resultado.</p>',
        'grade'    => 16.0,
        'aprobar'  => 13.0,
    ]));
$reporte->creado('Cuestionario de certificación',
    'aprueba con 13 de 16 · 3 intentos · 24 h de espera · revisión solo al cerrar');

$notaevaluacion = academia_gradeitem($curso, 'quiz',
    $DB->get_field('course_modules', 'instance', ['id' => $cmevaluacion->id]));
if ($notaevaluacion) {
    academia_aplicar_restricciones($cmfinal, academia_restricciones([
        academia_restriccion_nota($notaevaluacion, 80.0),
    ]));
    $reporte->corregido('restricción', 'la final requiere 80 % en la evaluación de la lección 1');
} else {
    $reporte->error('restricción de la final', 'no se encontró el elemento de calificación');
}

// El certificado.
['cm' => $cmcertificado] = academia_agregar_modulo($curso, 'customcert', [
    'idnumber'          => 'GC000-CERTIFICADO',
    'seccion'           => 3,
    'name'              => 'Certificado del curso',
    'intro'             => '<p>Elegir la plantilla institucional y <strong>fijar la vigencia</strong> ' .
        'con el elemento <em>Expiry</em>, en meses. Un certificado sin vigencia responde «esta ' .
        'persona hizo un curso alguna vez»; uno con vigencia y código verificable responde ' .
        '«esta persona estaba habilitada el día del incidente». En operaciones con riesgo ' .
        'vital, esa diferencia es jurídica.</p>',
    'requiredtime'      => 0,
    'protection_print'  => 0,
    'protection_modify' => 1,
    'protection_copy'   => 0,
    'emailstudents'     => 0,
    'emailteachers'     => 0,
    'verifyany'         => 0,
    'deliveryoption'    => 'I',
    'completion'        => COMPLETION_TRACKING_MANUAL,
]);
$reporte->creado('Certificado');

$notafinal = academia_gradeitem($curso, 'quiz',
    $DB->get_field('course_modules', 'instance', ['id' => $cmfinal->id]));
if ($notafinal) {
    academia_aplicar_restricciones($cmcertificado, academia_restricciones([
        academia_restriccion_nota($notafinal, 80.0),
    ]));
    $reporte->corregido('restricción', 'el certificado requiere aprobar la evaluación final');
}

rebuild_course_cache($curso->id, true);
academia_purgar_caches($reporte);

$codigo = $reporte->resumen();

cli_writeln('');
cli_writeln("La plantilla está en {$CFG->wwwroot}/course/view.php?id={$curso->id}");
cli_writeln('Está OCULTA y su estado es Archivado. Así debe quedar: es un molde, no un curso.');
cli_writeln('');
cli_writeln('Lo que la plantilla NO puede traer resuelto:');
cli_writeln('  · el contenido H5P, que se crea dentro de Moodle y no se puede dejar vacío');
cli_writeln('  · la plantilla gráfica del certificado, que se arma arrastrando elementos');
cli_writeln('  · las preguntas, que son de cada curso');

exit($codigo);


// ═══════════════════════════════════════════════════════════════════════════
// Textos de ayuda que el autor borra
// ═══════════════════════════════════════════════════════════════════════════

function academia_texto_presentacion(): string {
    return <<<'HTML'
<div class="alert alert-info">
  <strong>Instrucciones para el autor — borrar esta caja antes de publicar.</strong>
</div>

<h3>Antes de escribir una sola pantalla, completa la ficha de audiencia</h3>
<ul>
  <li>¿Qué escolaridad tiene quien va a cursar esto?</li>
  <li>¿En qué dispositivo va a estudiar? ¿Con qué señal?</li>
  <li>¿Dónde y cuándo? ¿En la oficina, o en la base entre turnos y cansado?</li>
  <li>¿Qué sabe ya, y qué palabras usa para hablar de esto?</li>
  <li><strong>¿Qué tiene que hacer distinto después?</strong></li>
</ul>
<p>Esa última pregunta es la más importante del estándar: <strong>si no cambia nada en el
terreno, el curso no sirvió</strong>.</p>

<h3>Objetivos: verbos de acción, no de memoria</h3>
<p>Un objetivo describe algo <strong>observable</strong>. Si no puedes imaginar cómo
verificarlo mirando a la persona trabajar, está mal redactado.</p>
<table class="table table-sm">
  <thead><tr><th>No uses</th><th>Usa</th></tr></thead>
  <tbody>
    <tr><td>Conocer, comprender, saber, definir, nombrar, familiarizarse con</td>
        <td>Ejecutar, aplicar el protocolo de…, decidir cuándo…, reconocer en terreno,
            verificar antes de…, comunicar al mando, detener la operación si…</td></tr>
  </tbody>
</table>
<p><em>Antes:</em> «Conocer el listado de situaciones de cuidado.»<br>
<em>Después:</em> «Reconocer en terreno al menos tres situaciones de cuidado presentes y
comunicarlas al jefe de cuadrilla antes de iniciar el trabajo.»</p>
HTML;
}

function academia_texto_leccion(): string {
    return <<<'HTML'
<div class="alert alert-warning">
  <strong>Reemplazar esta página por una actividad H5P.</strong>
  Añadir una actividad o un recurso → <em>Contenido interactivo (H5P)</em>.
  Los tipos que usa el estándar son <em>Course Presentation</em> para las lecciones
  expositivas y <em>Branching Scenario</em> para las de decisión.
</div>

<h3>La escala de interactividad</h3>
<p><strong>Ninguna lección puede quedarse en nivel 0 o 1.</strong> Toda lección de seguridad
operacional debe alcanzar nivel 3 al menos una vez.</p>
<ol start="0">
  <li><strong>Pasar página.</strong> Solo el botón «siguiente». No cuenta como interacción.</li>
  <li><strong>Clic para revelar.</strong> Acordeones y pestañas. Obliga a hacer clic, no a
      pensar. Sirve para organizar contenido, nunca para enseñarlo.</li>
  <li><strong>Responder y recibir retroalimentación.</strong> El mínimo aceptable.</li>
  <li><strong>Decidir con consecuencia.</strong> La decisión cambia lo que pasa después.</li>
</ol>

<h3>Los cinco momentos de la lección</h3>
<ol>
  <li><strong>Gancho</strong> — por qué esto te importa a ti. Un caso real, una pregunta
      incómoda o una consecuencia concreta. Nunca empieces con una definición.</li>
  <li><strong>Idea</strong> — la regla, en menos de 25 palabras. Si la persona solo lee eso,
      ya se lleva lo importante.</li>
  <li><strong>Desarrollo</strong> — el detalle, con ejemplos de terreno. Frases de menos de
      20 palabras, voz activa, tuteo. Nada de teoría que no se use.</li>
  <li><strong>Práctica</strong> — un escenario con opciones y consecuencias. Aquí es donde
      realmente se aprende.</li>
  <li><strong>Para llevar</strong> — una tarjeta, una lista de chequeo, una regla
      mnemotécnica. Toda lección termina con algo descargable.</li>
</ol>

<h3>Cómo se ve</h3>
<ul>
  <li>Texto de cuerpo mínimo 16 px, interlineado 1,5 o más.</li>
  <li>Alineación siempre a la izquierda. Nunca justificado, nunca centrado.</li>
  <li>Toda imagen lleva texto alternativo. Si la imagen no enseña, no va.</li>
  <li>Todo video, subtítulos, y máximo 3 minutos.</li>
  <li>Revisar en un <strong>teléfono real</strong>, no solo en el computador.</li>
</ul>
HTML;
}
