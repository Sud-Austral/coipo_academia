<?php
// Construye IF-151 y TR-104 como ESQUELETOS.
//
// Qué significa esqueleto, exactamente:
//
//   SÍ trae   la ficha con los 8 campos, las secciones, los cuestionarios con su
//             configuración exacta, el banco de 60 preguntas cargado, la cadena
//             de restricciones, el certificado con su vigencia, la competencia
//             vinculada y la matrícula por cohorte.
//
//   NO trae   el contenido H5P de las lecciones. Eso lo produce el autor, y en
//             IF-151 lo firma un especialista del Departamento de Protección
//             contra Incendios Forestales. En un dominio donde un error tiene
//             consecuencias en terreno, el contenido técnico no se genera.
//
// Cada lección deja una Página marcador con el guion de la lección —gancho, idea,
// desarrollo, práctica y para llevar, tal como está en el documento de diseño—
// para que el autor tenga delante lo que tiene que convertir en pantallas.
//
//   docker compose exec -u www-data app php /opt/academia/cli/90_cursos_esqueleto.php --dry-run
//   docker compose exec -u www-data app php /opt/academia/cli/90_cursos_esqueleto.php
//   docker compose exec -u www-data app php /opt/academia/cli/90_cursos_esqueleto.php --solo=IF-151

require(__DIR__ . '/bootstrap.php');
require_once(__DIR__ . '/lib_cursos.php');
require_once(__DIR__ . '/lib_quiz.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

$opciones = academia_cli_inicio(
    'Construye IF-151 y TR-104 con su estructura, evaluaciones, banco y certificado.',
    ['solo' => '']);

academia_exigir_ajustes_de_curso();

$reporte = new academia_reporte('Cursos esqueleto', $opciones['dry-run']);

$cuales = $opciones['solo'] !== '' ? [$opciones['solo']] : ['IF-151', 'TR-104'];

foreach ($cuales as $cual) {
    if ($cual === 'IF-151') {
        academia_construir_if151($reporte);
    } else if ($cual === 'TR-104') {
        academia_construir_tr104($reporte);
    } else {
        $reporte->error($cual, 'no conozco ese curso (IF-151 o TR-104)');
    }
}

academia_purgar_caches($reporte);
exit($reporte->resumen());


// ═══════════════════════════════════════════════════════════════════════════
// IF-151 · Física y comportamiento del fuego forestal
// ═══════════════════════════════════════════════════════════════════════════

function academia_construir_if151(academia_reporte $reporte): void {
    global $DB, $CFG;

    // Las siete lecciones, tal como las define la Parte 2.2 del diseño.
    // «Cada lección enseña una sola cosa y dura menos de 12 minutos. Ninguna se
    // queda en nivel 0 o 1 de interactividad.»
    $lecciones = [
        1 => ['El problema no son los focos',        'Reformular', 2, 8,
              'Reformular el problema de gestión a partir de la concentración del daño'],
        2 => ['El fuego no avanza, se vuelve a encender', 'Explicar', 2, 7,
              'Explicar la propagación como una sucesión de igniciones'],
        3 => ['El triángulo, con números chilenos', 'Atribuir', 3, 10,
              'Atribuir el comportamiento a las variables del triángulo e identificar cuál dominó'],
        4 => ['Cómo se mueve el calor',              'Anticipar', 2, 8,
              'Anticipar dónde puede aparecer fuego nuevo antes de que llegue el frente'],
        5 => ['Leer los números de un parte',        'Interpretar', 2, 9,
              'Interpretar intensidad y longitud de llama y deducir qué era posible ese día'],
        6 => ['Dónde está realmente el riesgo',      'Distinguir', 3, 10,
              'Distinguir zonas de alta ocurrencia de zonas de alta severidad, y priorizar'],
        7 => ['Cuando todo se alinea',               'Explicar', 3, 12,
              'Explicar por qué un incendio superó la capacidad de control, sin atribuciones falsas'],
    ];

    if ($reporte->es_simulacion()) {
        $existe = $DB->record_exists('course', ['shortname' => 'IF-151']);
        $existe ? $reporte->existia('IF-151') : $reporte->creado('IF-151',
            count($lecciones) . ' lecciones · 8 cuestionarios · 60 preguntas');
        return;
    }

    try {
        ['curso' => $curso, 'creado' => $creado] = academia_crear_curso([
            'shortname'   => 'IF-151',
            'fullname'    => 'IF-151 · Física y comportamiento del fuego forestal',
            'idnumber'    => 'IF-151',
            'categoria'   => '01-FTC',
            'numsections' => 2 + count($lecciones),   // presentación + 7 + certificación
            'summary'     => '<p>Base técnica común para profesionales de la Gerencia de ' .
                'Incendios Forestales, cualquiera sea su función.</p>' .
                '<p><strong>Al terminar</strong> explicas el comportamiento de un incendio ' .
                'forestal usando el marco del ambiente del fuego calibrado con datos chilenos, ' .
                'y reconoces cuándo tu propia experiencia no aplica al caso que tienes delante.</p>' .
                '<p><em>El contenido técnico de este curso es propuesta y requiere validación ' .
                'del Departamento de Protección contra Incendios Forestales antes de su ' .
                'producción.</em></p>',
        ]);
    } catch (moodle_exception $e) {
        $reporte->error('IF-151', $e->getMessage());
        return;
    }

    if (!$creado) {
        $reporte->existia('IF-151', "id {$curso->id} — no se toca");
        return;
    }
    $reporte->creado('IF-151', "curso id {$curso->id} · oculto");

    // ─── Ficha: los ocho campos ─────────────────────────────────────────────
    // «Si quedan vacíos, el curso no aparece en el catálogo filtrable ni en
    // ningún informe institucional. Es el olvido más frecuente.»
    $problemas = academia_completar_campos($curso, [
        'area'           => '01 Incendios Forestales',
        'nivel'          => 'Básico',            // fundacional, no elemental
        'perfil'         => 'Profesional',
        'modalidad'      => 'e-learning autoinstruccional',
        'duracion'       => 6,                   // 64 min de contenido + evaluaciones + material
        'vigencia'       => 60,                  // la física no cambia
        'financiamiento' => 'CONAF',
        'estado'         => 'Desarrollo',        // pasa a Piloto al terminar la etapa 6
    ]);
    foreach ($problemas as $p) {
        $reporte->error('IF-151 · campos', $p);
    }
    if (!$problemas) {
        $reporte->corregido('IF-151 · campos', 'los 8 completos');
    }

    // ─── Secciones ──────────────────────────────────────────────────────────
    $secciones = [
        0 => ['nombre' => 'General', 'resumen' => ''],
        1 => ['nombre' => 'Presentación', 'resumen' =>
            '<p>Para quién es este curso, qué vas a poder hacer al terminar y los documentos ' .
            'de base.</p>'],
    ];
    foreach ($lecciones as $n => [$titulo, $verbo, $interactividad, $minutos, $objetivo]) {
        $secciones[$n + 1] = [
            'nombre'  => "Lección $n · $titulo",
            'resumen' => "<p><strong>Objetivo:</strong> $objetivo</p>" .
                "<p>Verbo observable: <em>$verbo</em> · Nivel $interactividad de interactividad · " .
                "$minutos minutos</p>",
        ];
    }
    $secciones[count($lecciones) + 2] = ['nombre' => 'Certificación', 'resumen' =>
        '<p>La evaluación final decide si se emite un certificado con 60 meses de vigencia. ' .
        'Sus preguntas salen de un banco reservado que no aparece en ninguna evaluación de ' .
        'lección.</p>'];
    academia_nombrar_secciones($curso, $secciones);
    $reporte->corregido('IF-151 · secciones', count($secciones) . ' con nombre y objetivo');

    // ─── Presentación ───────────────────────────────────────────────────────
    academia_agregar_modulo($curso, 'page', [
        'idnumber'          => 'IF151-PRESENTACION',
        'seccion'           => 1,
        'name'              => 'Para quién es este curso y qué vas a poder hacer',
        'intro'             => '',
        'content'           => academia_if151_presentacion(),
        'contentformat'     => FORMAT_HTML,
        'display'           => RESOURCELIB_DISPLAY_OPEN,
        'printheading'      => 1,
        'printintro'        => 0,
        'printlastmodified' => 1,
        'completion'        => COMPLETION_TRACKING_AUTOMATIC,
        'completionview'    => 1,
    ]);

    academia_agregar_modulo($curso, 'folder', [
        'idnumber'       => 'IF151-DOCUMENTOS',
        'seccion'        => 1,
        'name'           => 'Documentos de base',
        'intro'          => '<p>Los tres documentos institucionales de los que sale el curso. ' .
            'Son <strong>material de referencia</strong>: no hay que leerlos para aprobar.</p>' .
            '<ul><li><em>Física de los Incendios Forestales</em> (jul. 2026) — el porqué</li>' .
            '<li><em>Análisis estadístico de incendios CONAF 2014–2024</em> (jun. 2026) — ' .
            'la calibración chilena de cada afirmación teórica</li>' .
            '<li><em>Anexo metodológico y glosario estadístico</em> — base del curso IF-152</li></ul>' .
            '<p><strong>Falta subir los archivos.</strong></p>',
        'display'        => 0,
        'showexpanded'   => 1,
        'completion'     => COMPLETION_TRACKING_AUTOMATIC,
        'completionview' => 1,
    ]);

    academia_agregar_modulo($curso, 'forum', [
        'idnumber'       => 'IF151-FORO',
        'seccion'        => 1,
        'name'           => 'Foro de consultas',
        'intro'          => '<p>Participación opcional.</p>',
        'type'           => 'general',
        'forcesubscribe' => 0,
        'completion'     => COMPLETION_TRACKING_NONE,
    ]);
    $reporte->creado('IF-151 · presentación', '3 actividades');

    // ─── Las siete lecciones ────────────────────────────────────────────────
    $cmcontenidos = [];
    $cmevaluaciones = [];
    $notaevaluaciones = [];

    foreach ($lecciones as $n => [$titulo, $verbo, $interactividad, $minutos, $objetivo]) {
        $seccion = $n + 1;
        $tipoh5p = $interactividad === 3 ? 'Branching Scenario' : 'Course Presentation';

        ['cm' => $cm] = academia_agregar_modulo($curso, 'page', [
            'idnumber'          => "IF151-L$n-CONTENIDO",
            'seccion'           => $seccion,
            'name'              => "Lección $n · $titulo",
            'intro'             => "<p>Reemplazar por una actividad <strong>H5P</strong> del " .
                "tipo <em>$tipoh5p</em>. Nivel $interactividad de interactividad, $minutos min.</p>",
            'content'           => academia_if151_guion($n, $titulo, $objetivo, $verbo,
                                                         $interactividad, $minutos),
            'contentformat'     => FORMAT_HTML,
            'display'           => RESOURCELIB_DISPLAY_OPEN,
            'printheading'      => 1,
            'printintro'        => 1,
            'printlastmodified' => 1,
            'completion'        => COMPLETION_TRACKING_AUTOMATIC,
            'completionview'    => 1,
        ]);
        $cmcontenidos[$n] = $cm;

        ['cm' => $cmquiz] = academia_agregar_modulo($curso, 'quiz',
            academia_quiz_formativo([
                'idnumber' => "IF151-L$n-EVALUACION",
                'seccion'  => $seccion,
                'name'     => "Evaluación de la lección $n",
                'intro'    => '<p>Cinco preguntas de la categoría <em>IF-151 / Lección ' . $n .
                    ' / Formativa</em> del banco. Intentos ilimitados: esta evaluación está ' .
                    'para enseñar, y cada error explica por qué lo es.</p>' .
                    '<p><strong>Falta agregar las preguntas</strong> desde el banco. No usar ' .
                    'nunca los ítems C de esa lección: son la reserva de la evaluación final.</p>',
                'grade'    => 5.0,
                'aprobar'  => 4.0,
            ]));
        $cmevaluaciones[$n] = $cmquiz;

        $instancia = $DB->get_field('course_modules', 'instance', ['id' => $cmquiz->id]);
        $notaevaluaciones[$n] = academia_gradeitem($curso, 'quiz', (int)$instancia);
    }
    $reporte->creado('IF-151 · lecciones', count($lecciones) . ' lecciones y sus evaluaciones');

    // ─── La cadena de restricciones ─────────────────────────────────────────
    // «Cada evaluación de lección requiere haber completado su contenido H5P.
    //  Cada lección N requiere haber aprobado la evaluación de la lección N−1.
    //  La evaluación final requiere haber aprobado las siete evaluaciones.
    //  El certificado requiere haber aprobado la evaluación final.»
    //
    // El 80 % es un PORCENTAJE, no «4». Poner 4 acá deja pasar a cualquiera que
    // saque un 4 %, y el curso deja de encadenar sin que nadie lo note.
    foreach ($lecciones as $n => $_) {
        academia_aplicar_restricciones($cmevaluaciones[$n], academia_restricciones([
            academia_restriccion_completado($cmcontenidos[$n]->id, COMPLETION_COMPLETE),
        ]));

        if ($n > 1 && $notaevaluaciones[$n - 1]) {
            academia_aplicar_restricciones($cmcontenidos[$n], academia_restricciones([
                academia_restriccion_nota($notaevaluaciones[$n - 1], 80.0),
            ]));
        }
    }
    $reporte->corregido('IF-151 · restricciones', 'las 7 lecciones encadenadas al 80 %');

    // ─── Certificación ──────────────────────────────────────────────────────
    ['cm' => $cmfinal] = academia_agregar_modulo($curso, 'quiz',
        academia_quiz_certificacion([
            'idnumber' => 'IF151-FINAL',
            'seccion'  => count($lecciones) + 2,
            'name'     => 'Evaluación final de certificación',
            'intro'    => '<p>16 preguntas extraídas al azar de un banco reservado de 25 que ' .
                'no viste antes. Se aprueba con 13.</p>' .
                '<p><strong>Falta componerla</strong>: Añadir → una pregunta aleatoria, ' .
                '2 de cada una de las 7 categorías <em>Lección N / Certificación</em> (14) más ' .
                '2 de <em>Integradoras</em>.</p>' .
                '<p>Tres intentos, con 24 horas de espera entre uno y otro, y 40 minutos de ' .
                'límite: son preguntas situacionales, el tiempo tiene que alcanzar para leer ' .
                'y pensar.</p>',
            'grade'    => 16.0,
            'aprobar'  => 13.0,
        ]));

    $condicionesfinal = [];
    foreach ($lecciones as $n => $_) {
        if ($notaevaluaciones[$n]) {
            $condicionesfinal[] = academia_restriccion_nota($notaevaluaciones[$n], 80.0);
        }
    }
    academia_aplicar_restricciones($cmfinal, academia_restricciones($condicionesfinal));
    $reporte->creado('IF-151 · evaluación final',
        '13 de 16 · 3 intentos · 24 h · revisión completa solo al cerrar');

    ['cm' => $cmcert] = academia_agregar_modulo($curso, 'customcert', [
        'idnumber'          => 'IF151-CERTIFICADO',
        'seccion'           => count($lecciones) + 2,
        'name'              => 'Certificado del curso',
        'intro'             => '<p>Certificado <strong>de competencia</strong>, con vigencia de ' .
            '60 meses. No confundir con un certificado de participación: este declara ' .
            'habilitación y vence.</p>' .
            '<p><strong>Falta armar la plantilla</strong> con los elementos: nombre del ' .
            'participante, RUT, nombre y código del curso, fecha de emisión, ' .
            '<em>Expiry</em> a 60 meses, horas, código QR de verificación y firma digital.</p>',
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

    $notafinal = academia_gradeitem($curso, 'quiz',
        (int)$DB->get_field('course_modules', 'instance', ['id' => $cmfinal->id]));
    if ($notafinal) {
        // El certificado se condiciona a la evaluación final, NO a la
        // finalización del curso. Es explícito en la Parte 5.2 del diseño.
        academia_aplicar_restricciones($cmcert, academia_restricciones([
            academia_restriccion_nota($notafinal, 80.0),
        ]));
    }
    $reporte->creado('IF-151 · certificado', 'vigencia 60 meses · condicionado a la final');

    // ─── El banco de 60 preguntas ───────────────────────────────────────────
    $mensaje = academia_importar_banco($curso, 'banco-IF-151.xml');
    if ($mensaje === '') {
        $total = academia_contar_preguntas($curso);
        $reporte->creado('IF-151 · banco de preguntas', "$total preguntas cargadas");
    } else {
        $reporte->error('IF-151 · banco de preguntas', $mensaje);
    }

    // ─── Competencia y matrícula ────────────────────────────────────────────
    if (academia_vincular_competencia($curso, 'IF-C151')) {
        $reporte->corregido('IF-151 · competencia', 'IF-C151, al completar el curso');
    } else {
        $reporte->omitido('IF-151 · competencia', 'no existe IF-C151 — correr 40_competencias.php');
    }

    $problema = academia_matricular_cohorte($curso, 'GER-IF-PROF');
    if ($problema === '') {
        $reporte->corregido('IF-151 · matriculación', 'sincronización de cohorte GER-IF-PROF');
    } else {
        $reporte->omitido('IF-151 · matriculación', $problema);
    }

    rebuild_course_cache($curso->id, true);
}


// ═══════════════════════════════════════════════════════════════════════════
// TR-104 · Crear cursos para la Academia CONAF
// ═══════════════════════════════════════════════════════════════════════════

function academia_construir_tr104(academia_reporte $reporte): void {
    global $DB;

    // Ocho lecciones en dos módulos, según la ficha de diseño de la pantalla 19.
    $modulos = [
        1 => ['Diseñar', [
            1 => ['Qué es un curso, y qué no lo es', 'Distinguir', 2, 7],
            2 => ['Empieza por quien aprende',       'Completar',  2, 8],
            3 => ['Objetivos que se pueden verificar', 'Reescribir', 2, 9],
            4 => ['La estructura de una lección',    'Estructurar', 2, 8],
            5 => ['Escribir para que se entienda',   'Reescribir', 3, 8],
        ], 8],
        2 => ['Construir y publicar', [
            6 => ['Que se vea bien y lo pueda usar todo el mundo', 'Detectar', 3, 10],
            7 => ['Construirlo en Moodle',           'Ejecutar',   2, 12],
            8 => ['Publicar: la lista de verificación', 'Decidir', 3, 6],
        ], 7],
    ];

    if ($reporte->es_simulacion()) {
        $existe = $DB->record_exists('course', ['shortname' => 'TR-104']);
        $existe ? $reporte->existia('TR-104') : $reporte->creado('TR-104', '8 lecciones · proyecto');
        return;
    }

    try {
        ['curso' => $curso, 'creado' => $creado] = academia_crear_curso([
            'shortname'   => 'TR-104',
            'fullname'    => 'TR-104 · Crear cursos para la Academia CONAF',
            'idnumber'    => 'TR-104',
            'categoria'   => '90-AUT',
            'numsections' => 5,     // presentación + 2 módulos + proyecto + certificación
            'summary'     => '<p>Diseñar, construir y publicar un curso que cumpla el estándar ' .
                'institucional de calidad y accesibilidad.</p>' .
                '<p>Quien lo completa queda habilitado para crear cursos en su área.</p>' .
                '<p><em>Este curso cumple sus propias reglas: ocho lecciones de menos de 12 ' .
                'minutos, objetivos con verbos observables, tres lecciones en nivel 3 de ' .
                'interactividad y cierre con una entrega que produce evidencia. Si el estándar ' .
                'no se pudiera aplicar a sí mismo, no serviría para nada.</em></p>',
        ]);
    } catch (moodle_exception $e) {
        $reporte->error('TR-104', $e->getMessage());
        return;
    }

    if (!$creado) {
        $reporte->existia('TR-104', "id {$curso->id} — no se toca");
        return;
    }
    $reporte->creado('TR-104', "curso id {$curso->id} · oculto");

    $problemas = academia_completar_campos($curso, [
        'area'           => '90 Transversal',
        'nivel'          => 'Básico',
        'perfil'         => 'Profesional',
        'modalidad'      => 'e-learning autoinstruccional',
        'duracion'       => 12,
        'vigencia'       => 36,
        'financiamiento' => 'CONAF',
        'estado'         => 'Desarrollo',
    ]);
    foreach ($problemas as $p) {
        $reporte->error('TR-104 · campos', $p);
    }
    if (!$problemas) {
        $reporte->corregido('TR-104 · campos', 'los 8 completos');
    }

    academia_nombrar_secciones($curso, [
        0 => ['nombre' => 'General', 'resumen' => ''],
        1 => ['nombre' => 'Presentación', 'resumen' =>
            '<p>Para quién es este curso, y la plantilla de ficha de diseño que vas a usar en ' .
            'todas las lecciones.</p>'],
        2 => ['nombre' => 'Módulo 1 · Diseñar', 'resumen' =>
            '<p>Cinco lecciones, 40 minutos. Empezar por quien aprende, no por el temario.</p>'],
        3 => ['nombre' => 'Módulo 2 · Construir y publicar', 'resumen' =>
            '<p>Tres lecciones, 28 minutos. Accesibilidad, construcción en Moodle y la lista ' .
            'de verificación.</p>'],
        4 => ['nombre' => 'Proyecto final', 'resumen' =>
            '<p>La entrega es la ficha de diseño de un curso real de tu área. ' .
            '<strong>Nadie aprende a diseñar cursos respondiendo preguntas sobre diseño de ' .
            'cursos:</strong> la evidencia de que alguien puede hacerlo es que lo hizo.</p>'],
        5 => ['nombre' => 'Certificación', 'resumen' =>
            '<p>El certificado habilita como autor. Requiere la evaluación final aprobada y el ' .
            'proyecto calificado como Logrado.</p>'],
    ]);

    academia_agregar_modulo($curso, 'page', [
        'idnumber'          => 'TR104-PRESENTACION',
        'seccion'           => 1,
        'name'              => 'Para quién es este curso y qué vas a poder hacer al terminar',
        'intro'             => '',
        'content'           => academia_tr104_presentacion(),
        'contentformat'     => FORMAT_HTML,
        'display'           => RESOURCELIB_DISPLAY_OPEN,
        'printheading'      => 1,
        'printintro'        => 0,
        'printlastmodified' => 1,
        'completion'        => COMPLETION_TRACKING_AUTOMATIC,
        'completionview'    => 1,
    ]);

    academia_agregar_modulo($curso, 'forum', [
        'idnumber'       => 'TR104-FORO',
        'seccion'        => 1,
        'name'           => 'Foro: presenta el curso que quieres crear',
        'intro'          => '<p>Se espera participación: es donde empieza tu proyecto final.</p>',
        'type'           => 'general',
        'forcesubscribe' => 0,
        'completion'     => COMPLETION_TRACKING_MANUAL,
    ]);

    $cmevalmodulo = [];
    foreach ($modulos as $nummodulo => [$nombremodulo, $lecciones, $preguntas]) {
        $seccion = $nummodulo + 1;
        $cmlecciones = [];

        foreach ($lecciones as $n => [$titulo, $verbo, $interactividad, $minutos]) {
            $tipoh5p = $interactividad === 3 ? 'Branching Scenario' : 'Course Presentation';
            ['cm' => $cm] = academia_agregar_modulo($curso, 'page', [
                'idnumber'          => "TR104-L$n",
                'seccion'           => $seccion,
                'name'              => "L$n · $titulo",
                'intro'             => "<p>Reemplazar por H5P del tipo <em>$tipoh5p</em>. " .
                    "Verbo observable: <em>$verbo</em> · Nivel $interactividad · $minutos min.</p>",
                'content'           => '<div class="alert alert-warning">Contenido pendiente. ' .
                    'Esta lección tiene que cumplir el mismo estándar que enseña: cinco ' .
                    'momentos, menos de 12 minutos y un solo resultado de aprendizaje.</div>',
                'contentformat'     => FORMAT_HTML,
                'display'           => RESOURCELIB_DISPLAY_OPEN,
                'printheading'      => 1,
                'printintro'        => 1,
                'printlastmodified' => 1,
                'completion'        => COMPLETION_TRACKING_AUTOMATIC,
                'completionview'    => 1,
            ]);
            $cmlecciones[] = $cm;
        }

        ['cm' => $cmquiz] = academia_agregar_modulo($curso, 'quiz',
            academia_quiz_formativo([
                'idnumber' => "TR104-M$nummodulo-EVALUACION",
                'seccion'  => $seccion,
                'name'     => "Evaluación del módulo $nummodulo",
                'intro'    => "<p>$preguntas preguntas situacionales. Se aprueba con 80 %.</p>" .
                    '<p><strong>Faltan las preguntas.</strong></p>',
                'grade'    => (float)$preguntas,
                'aprobar'  => round($preguntas * 0.8, 2),
            ]));

        // La evaluación del módulo requiere haber completado sus lecciones.
        academia_aplicar_restricciones($cmquiz, academia_restricciones(
            array_map(fn($cm) => academia_restriccion_completado($cm->id, COMPLETION_COMPLETE),
                $cmlecciones)));

        $cmevalmodulo[$nummodulo] = $cmquiz;
    }
    $reporte->creado('TR-104 · módulos', '8 lecciones y 2 evaluaciones');

    // ─── Proyecto final ─────────────────────────────────────────────────────
    ['cm' => $cmfinal] = academia_agregar_modulo($curso, 'quiz',
        academia_quiz_certificacion([
            'idnumber' => 'TR104-FINAL',
            'seccion'  => 4,
            'name'     => 'Evaluación final integradora',
            'intro'    => '<p>Cinco situaciones reales de quien está creando un curso. Ninguna ' .
                'pregunta por definiciones.</p><p><strong>Faltan las preguntas.</strong></p>',
            'grade'    => 5.0,
            'aprobar'  => 4.0,
        ]));

    $condiciones = [];
    foreach ($cmevalmodulo as $cm) {
        $instancia = (int)$DB->get_field('course_modules', 'instance', ['id' => $cm->id]);
        if ($nota = academia_gradeitem($curso, 'quiz', $instancia)) {
            $condiciones[] = academia_restriccion_nota($nota, 80.0);
        }
    }
    academia_aplicar_restricciones($cmfinal, academia_restricciones($condiciones));

    ['cm' => $cmproyecto] = academia_agregar_modulo($curso, 'assign', [
        'idnumber'        => 'TR104-PROYECTO',
        'seccion'         => 4,
        'name'            => 'Proyecto: la ficha de diseño de tu curso',
        'intro'           => academia_tr104_proyecto(),
        'alwaysshowdescription' => 1,
        'submissiondrafts'      => 0,
        'requiresubmissionstatement' => 0,
        'sendnotifications'     => 0,
        'sendlatenotifications' => 0,
        'sendstudentnotifications' => 1,
        'duedate'               => 0,
        'allowsubmissionsfromdate' => 0,
        'cutoffdate'            => 0,
        'gradingduedate'        => 0,
        'teamsubmission'        => 0,
        'requireallteammemberssubmit' => 0,
        'blindmarking'          => 0,
        'hidegrader'            => 0,
        'markingworkflow'       => 0,
        'markingallocation'     => 0,
        'attemptreopenmethod'   => 'manual',
        'maxattempts'           => -1,
        'grade'                 => 100,
        'completion'            => COMPLETION_TRACKING_AUTOMATIC,
        'completionusegrade'    => 1,
        // Rúbrica: gradingform_rubric ya viene con Moodle. La rúbrica de cuatro
        // criterios y tres niveles se arma en la interfaz — se construye una vez
        // y queda visible para el participante ANTES de entregar, lo que por sí
        // solo mejora las entregas.
        'submissionplugins'     => 1,
    ]);

    $notafinal = academia_gradeitem($curso, 'quiz',
        (int)$DB->get_field('course_modules', 'instance', ['id' => $cmfinal->id]));
    if ($notafinal) {
        academia_aplicar_restricciones($cmproyecto, academia_restricciones([
            academia_restriccion_nota($notafinal, 80.0),
        ]));
    }
    $reporte->creado('TR-104 · proyecto final', 'tarea con rúbrica, tras la evaluación final');

    // ─── Certificado ────────────────────────────────────────────────────────
    ['cm' => $cmcert] = academia_agregar_modulo($curso, 'customcert', [
        'idnumber'          => 'TR104-CERTIFICADO',
        'seccion'           => 5,
        'name'              => 'Certificado y habilitación como autor',
        'intro'             => '<p>Vigencia 36 meses. Requiere la evaluación final aprobada y ' .
            'el proyecto calificado como <em>Logrado</em>.</p>' .
            '<p>La habilitación como autor se otorga agregando a la persona a la cohorte ' .
            '<strong>PERF-AUTOR</strong>, que a su vez le habilita el rol de edición en la ' .
            'categoría de su área. <em>Ese paso es manual y lo hace el Gestor de Área.</em></p>',
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
    academia_aplicar_restricciones($cmcert, academia_restricciones([
        academia_restriccion_completado($cmproyecto->id, COMPLETION_COMPLETE_PASS),
    ]));
    $reporte->creado('TR-104 · certificado', 'vigencia 36 meses');

    if (academia_vincular_competencia($curso, 'TR-C104')) {
        $reporte->corregido('TR-104 · competencia', 'TR-C104, al completar el curso');
    } else {
        $reporte->omitido('TR-104 · competencia', 'no existe TR-C104 — correr 40_competencias.php');
    }

    // TR-104 NO se matricula por cohorte: es catálogo abierto interno, con
    // auto-matriculación restringida. Se deja anotado y no configurado, porque
    // «restringida a miembros de cohorte» exige decidir a qué cohorte, y eso
    // depende de a quién se quiera habilitar como autor.
    $reporte->omitido('TR-104 · matriculación',
        'es catálogo abierto: va auto-matriculación restringida, no sincronización de cohorte. ' .
        'Falta decidir qué cohorte la habilita.');

    rebuild_course_cache($curso->id, true);
}


// ═══════════════════════════════════════════════════════════════════════════
// Banco de preguntas
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Importa un archivo Moodle Question XML al banco del curso.
 *
 * @return string cadena vacía si fue bien, o el motivo del fallo
 */
function academia_importar_banco(stdClass $curso, string $archivo): string {
    global $DB;

    $ruta = dirname(__DIR__) . '/datos/' . $archivo;
    if (!is_readable($ruta)) {
        return "no se puede leer $ruta";
    }

    $contexto = context_course::instance($curso->id);

    // La categoría raíz del banco del curso. En Moodle 4.x toda categoría
    // cuelga de una especial llamada «top», que se crea con el curso.
    $categoria = $DB->get_record('question_categories',
        ['contextid' => $contexto->id, 'parent' => 0], '*', IGNORE_MULTIPLE);
    if (!$categoria) {
        return 'el curso no tiene categoría raíz de banco de preguntas';
    }

    $formato = new qformat_xml();
    $formato->setCategory($categoria);
    $formato->setContexts([$contexto]);
    $formato->setCourse($curso);
    $formato->setFilename($ruta);
    $formato->setRealfilename($archivo);
    $formato->setMatchgrades('error');
    // El XML trae su propio árbol de categorías: Lección N / Formativa y
    // Lección N / Certificación. Esa separación es lo que impide que un ítem de
    // certificación aparezca en una evaluación de lección.
    $formato->setCatfromfile(true);
    // false: las categorías se crean en el contexto de ESTE curso, no en el que
    // diga el archivo. Con true, un XML exportado de otro sitio dejaría las
    // preguntas en un contexto que acá no existe.
    $formato->setContextfromfile(false);
    $formato->setStoponerror(true);

    ob_start();
    $ok = $formato->importpreprocess() && $formato->importprocess() && $formato->importpostprocess();
    $salida = ob_get_clean();

    if (!$ok) {
        $salida = trim(strip_tags($salida));
        return 'falló la importación' . ($salida !== '' ? ': ' . mb_substr($salida, 0, 300) : '');
    }

    return '';
}

/**
 * Cuenta las preguntas del banco de un curso.
 */
function academia_contar_preguntas(stdClass $curso): int {
    global $DB;

    $contexto = context_course::instance($curso->id);
    return (int)$DB->count_records_sql("
        SELECT COUNT(DISTINCT qbe.id)
          FROM {question_bank_entries} qbe
          JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
         WHERE qc.contextid = ?", [$contexto->id]);
}


// ═══════════════════════════════════════════════════════════════════════════
// Textos
// ═══════════════════════════════════════════════════════════════════════════

function academia_if151_presentacion(): string {
    return <<<'HTML'
<h3>Para quién es este curso</h3>
<p>Profesional de la Gerencia de Incendios Forestales, cualquiera sea tu función: prevención,
análisis, operaciones, logística, planificación territorial, comunicaciones o coordinación
regional.</p>

<h3>El problema que resuelve</h3>
<p>El diagnóstico no es que la gente se equivoque. Es que cada persona entiende el fuego en
función de los incendios que le tocó vivir, y esas experiencias son muy disímiles.</p>
<p>Quien enfrentó un evento extremo en terreno tiene una intuición sobre el comportamiento del
fuego que quien ingresó hace poco todavía no tiene. Quien trabaja en un extremo del país y
quien trabaja en el otro construyen modelos mentales distintos, y ambos tienden a suponer que
el suyo es general.</p>
<p><strong>Este curso no viene a corregir errores: viene a reemplazar la lotería de la
experiencia por una base común y explícita.</strong> El marco explica lo que ya viste; no lo
contradice.</p>

<h3>Qué vas a poder hacer al terminar</h3>
<p>Explicar el comportamiento de un incendio forestal usando el marco del ambiente del fuego
calibrado con datos chilenos, y <strong>reconocer cuándo tu propia experiencia no aplica al
caso que tienes delante</strong>.</p>

<h3>Cómo se aprueba</h3>
<ul>
  <li>Siete lecciones encadenadas, de menos de 12 minutos cada una.</li>
  <li>Una evaluación por lección: 5 preguntas, se aprueba con 4, intentos ilimitados.
      Están para enseñar — cada error explica por qué lo es.</li>
  <li>Una evaluación final de 16 preguntas que no viste antes. Se aprueba con 13,
      hay 3 intentos y entre uno y otro hay que esperar 24 horas.</li>
  <li>El certificado se emite al aprobar la final, y <strong>vence a los 60 meses</strong>.</li>
</ul>

<div class="alert alert-warning">
  <strong>Tres cosas que este curso declara en voz alta.</strong>
  La «regla del 30» es doctrina importada y los incendios extremos chilenos ocurrieron en
  promedio a 26,4 °C, 37,3 % de humedad y 12,6 km/h — ninguno de los tres umbrales.
  Los umbrales de longitud de llama vienen de NWCG y no están validados para Chile.
  Y el análisis estadístico describe asociaciones históricas, no un pronóstico operativo.
</div>
HTML;
}

function academia_if151_guion(int $n, string $titulo, string $objetivo, string $verbo,
                              int $interactividad, int $minutos): string {
    $tipo = $interactividad === 3 ? 'Branching Scenario' : 'Course Presentation';

    return <<<HTML
<div class="alert alert-warning">
  <strong>Esta página es un marcador. Reemplazarla por una actividad H5P del tipo
  <em>$tipo</em>.</strong>
  El texto de abajo es el guion de la lección tal como está en el documento de diseño:
  no es un resumen, es lo que hay que convertir en pantallas.
</div>

<p><strong>Objetivo:</strong> $objetivo<br>
<strong>Verbo observable:</strong> $verbo · <strong>Nivel de interactividad:</strong>
$interactividad · <strong>Duración:</strong> $minutos min</p>

<h3>Los cinco momentos que debe tener</h3>
<ol>
  <li><strong>Gancho</strong> — por qué esto te importa a ti. Nunca una definición.</li>
  <li><strong>La idea</strong> — la regla, en menos de 25 palabras.</li>
  <li><strong>Desarrollo</strong> — el detalle, con los números chilenos.</li>
  <li><strong>Práctica</strong> — un escenario con decisión y consecuencia.</li>
  <li><strong>Para llevar</strong> — una ficha descargable de una página.</li>
</ol>

<p>El guion completo de la lección $n está en <em>IF-151_Diseno-de-curso_Academia-CONAF</em>,
Parte 2.4. El contenido técnico debe ser validado por el Departamento de Protección contra
Incendios Forestales antes de producirse.</p>
HTML;
}

function academia_tr104_presentacion(): string {
    return <<<'HTML'
<h3>Para quién es este curso</h3>
<p>Profesional de cualquier área de CONAF, con dominio técnico de su materia y sin formación
en diseño instruccional. Probablemente ya tienes un encargo de curso asignado.</p>

<h3>Qué vas a hacer distinto al terminar</h3>
<p><strong>Diseñar un curso partiendo de la conducta a cambiar, y no del material que tienes
disponible.</strong></p>

<h3>Por qué este curso termina en un proyecto y no en un cuestionario</h3>
<p>Nadie aprende a diseñar cursos respondiendo preguntas sobre diseño de cursos. La evidencia
de que alguien puede hacerlo es que lo hizo: la entrega es la ficha de diseño de un curso real
de tu área, revisada por el Gestor de tu Área Temática.</p>
<p>Ese proyecto es, literalmente, el primer paso del siguiente curso de la Academia.</p>

<h3>Qué NO vas a encontrar acá, y por qué</h3>
<ul>
  <li><strong>La historia y las teorías del diseño instruccional.</strong> No cambia ninguna
      decisión de quien está redactando un objetivo un martes por la tarde.</li>
  <li><strong>Los 87 criterios de WCAG 2.2.</strong> Al autor le sirven siete reglas
      accionables; el resto lo verifica el Kit de Accesibilidad.</li>
  <li><strong>Los veinte tipos de actividad de Moodle.</strong> El estándar usa cuatro.
      Enseñar las veinte produce parálisis, no capacidad.</li>
  <li><strong>Un módulo de «Moodle desde cero».</strong> Se reemplaza por la plantilla
      maestra: si nunca se parte de cero, no hace falta enseñar a partir de cero.</li>
</ul>
HTML;
}

function academia_tr104_proyecto(): string {
    return <<<'HTML'
<p>Entrega individual. <strong>No se pide construir el curso todavía: se pide diseñarlo.</strong>
Construirlo viene después, con la guía operativa al lado.</p>

<h4>Qué se entrega</h4>
<ol>
  <li><strong>Ficha de audiencia</strong> — las seis preguntas, con la última —qué debe hacer
      distinto— respondida en una frase de acción.</li>
  <li><strong>Objetivos por lección</strong> — entre 3 y 8 lecciones. Cada objetivo con verbo
      observable y su forma de verificación.</li>
  <li><strong>Estructura de una lección</strong> — una lección desarrollada con los cinco
      momentos completos.</li>
  <li><strong>Nivel de interactividad</strong> — declarado por lección, y al menos una en
      nivel 3 si el contenido es de seguridad.</li>
  <li><strong>Tres preguntas de evaluación</strong> — situacionales, con retroalimentación
      redactada para cada opción.</li>
  <li><strong>Descartes</strong> — al menos tres contenidos que dejaste fuera, con la razón
      de cada uno.</li>
  <li><strong>Clasificación</strong> — los ocho campos personalizados completos, con el código
      de curso propuesto.</li>
</ol>

<h4>Cómo se corrige</h4>
<p>Con una rúbrica de cuatro criterios y tres niveles, que aplica el Gestor de tu Área
Temática: foco en la conducta, objetivos, interactividad y disciplina editorial.</p>
<p>Se aprueba con <em>Logrado</em> en al menos tres criterios y ningún <em>Insuficiente</em>.
<strong>Un proyecto devuelto no es un fracaso: es el mecanismo por el cual el estándar se
sostiene en el tiempo.</strong></p>

<div class="alert alert-info">
  <strong>Falta construir la rúbrica</strong> en Calificación avanzada → Rúbrica.
  Se construye una vez y queda visible para el participante antes de entregar, lo que por sí
  solo mejora las entregas.
</div>
HTML;
}
