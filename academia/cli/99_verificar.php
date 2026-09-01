<?php
// Verificación de la Academia v2: cuenta lo que hay y lo compara con lo esperado.
//
// La regla del proyecto: «funciona» significa haber VISTO el resultado, no que el
// comando no dio error. Este script es la mitad que se puede automatizar —
// contar. La otra mitad no la reemplaza nada y está listada al final: entrar por
// navegador, abrir un curso, ver el catálogo filtrar, recorrerlo en un teléfono.
//
// Devuelve 0 solo si todo cuadra. Sirve para encadenarlo después de un despliegue.
//
//   docker compose exec -u www-data app php /opt/academia/cli/99_verificar.php

require(__DIR__ . '/bootstrap.php');

$opciones = academia_cli_inicio('Cuenta el estado de la Academia y lo compara con lo esperado.');

$fallos = 0;
$avisos = 0;

/**
 * Una línea de verificación: qué se midió, qué salió y qué se esperaba.
 */
$comprobar = function (string $que, $obtenido, $esperado, string $pista = '')
        use (&$fallos): void {
    $ok = is_callable($esperado) ? $esperado($obtenido) : ($obtenido == $esperado);
    $textoesperado = is_callable($esperado) ? '' : "  esperado: $esperado";
    printf("  %-5s %-46s %-10s%s\n",
        $ok ? ' ok ' : 'FALLA', $que, (string)$obtenido, $ok ? '' : $textoesperado);
    if (!$ok) {
        $fallos++;
        if ($pista !== '') {
            cli_writeln('        → ' . $pista);
        }
    }
};

$avisar = function (string $que, $valor, string $nota) use (&$avisos): void {
    printf("  %-5s %-46s %s\n", 'aviso', $que, (string)$valor);
    cli_writeln('        → ' . $nota);
    $avisos++;
};

// ═══════════════════════════════════════════════════════════════════════════
cli_separator();
cli_writeln('1 · Dónde estamos parados');
cli_separator();

$comprobar('base de datos', $CFG->dbname, fn($v) => $v !== 'academia_prod',
    'esto es la instancia v2: no debería apuntar a academia_prod');
cli_writeln('        sitio: ' . $CFG->wwwroot);
cli_writeln('        moodle: ' . $CFG->release);

// La Academia se instaló vacía a propósito: sin los 2.869 funcionarios y sin los
// 37 cursos del campus viejo. Esta cuenta es el detector de que alguien apuntó
// esta instancia a un clon por equivocación, y sale mucho más barata que
// descubrirlo después mirando el catálogo. Se cuenta desde el id 3 porque el 1 es
// el invitado y el 2 el administrador que crea la instalación.
$usuarios = $DB->count_records_select('user', 'deleted = 0 AND id > 2');
$cursos   = $DB->count_records_select('course', 'id > 1');
cli_writeln("        cuentas: $usuarios   ·   cursos: $cursos");
if ($usuarios > 50) {
    $avisar('cuentas de usuario', $usuarios,
        'la Academia parte con un puñado de cuentas nominadas. Este número dice que ' .
        'esta base salió de un clon de producción, no de una instalación limpia.');
}

// ═══════════════════════════════════════════════════════════════════════════
cli_writeln('');
cli_separator();
cli_writeln('2 · Modelo de datos');
cli_separator();

// ─── Categorías ─────────────────────────────────────────────────────────────
$areas = $DB->count_records_select('course_categories',
    "idnumber IN ('01','02','03','04','05','90','99')");
$comprobar('áreas temáticas de primer nivel', $areas, 7, 'ejecutar 10_categorias.php');

$subcategorias = $DB->count_records_select('course_categories',
    "idnumber IN ('01-FTC','90-AUT')");
$comprobar('subcategorías de itinerario', $subcategorias, 2, 'ejecutar 10_categorias.php');

// ─── Campos personalizados ──────────────────────────────────────────────────
$esperados = ['area', 'nivel', 'perfil', 'modalidad', 'duracion', 'vigencia',
              'financiamiento', 'estado'];
[$ensql, $enparams] = $DB->get_in_or_equal($esperados);
$campos = $DB->count_records_select('customfield_field', "shortname $ensql", $enparams);
$comprobar('campos personalizados de curso', $campos, 8, 'ejecutar 20_campos_curso.php');

// ─── Cohortes ───────────────────────────────────────────────────────────────
$reg  = $DB->count_records_select('cohort', $DB->sql_like('idnumber', ':p'), ['p' => 'REG-%']);
$perf = $DB->count_records_select('cohort', $DB->sql_like('idnumber', ':p'), ['p' => 'PERF-%']);
$temp = $DB->count_records_select('cohort', $DB->sql_like('idnumber', ':p'), ['p' => 'TEMP-%']);
$comprobar('cohortes territoriales (REG-)', $reg, 16, 'ejecutar 30_cohortes.php');
$comprobar('cohortes ocupacionales (PERF-)', $perf, 8, 'son 7 del modelo + PERF-AUTOR');
$comprobar('cohortes de temporada (TEMP-)', $temp, 3, 'ejecutar 30_cohortes.php');

// ─── Competencias ───────────────────────────────────────────────────────────
$comprobar('subsistema de competencias', empty($CFG->enablecompetencies) ? 'apagado' : 'encendido',
    'encendido', 'ejecutar 40_competencias.php o 95_ajustes_sitio.php');
$marcos = $DB->count_records_select('competency_framework', "idnumber IN ('MARCO-IF','MARCO-TR')");
$comprobar('marcos de competencias', $marcos, 2, 'ejecutar 40_competencias.php');
$competencias = $DB->count_records('competency');
$comprobar('competencias y unidades', $competencias, fn($v) => $v >= 16,
    'esperadas al menos 16: 5 unidades + 11 competencias');

// ─── Rol de Gestor de Área ──────────────────────────────────────────────────
cli_writeln('');
$rol = $DB->get_record('role', ['shortname' => 'gestorarea'], 'id, name, archetype');
$comprobar('rol gestorarea', $rol ? 'existe' : 'no existe', 'existe', 'ejecutar 50_rol_gestor.php');

if ($rol) {
    // Esta es LA comprobación del diseño de roles. Si el rol se puede asignar a
    // nivel de sitio, un gestor puede terminar viendo las siete áreas y la
    // frontera entre gerencias deja de existir.
    $niveles = $DB->get_fieldset_select('role_context_levels', 'contextlevel',
        'roleid = ?', [$rol->id]);
    sort($niveles);
    $comprobar('gestorarea asignable SOLO en categoría',
        implode(',', $niveles), (string)CONTEXT_COURSECAT,
        'si aparece ' . CONTEXT_SYSTEM . ' (sitio), la frontera entre áreas no existe');

    $prohibidas = $DB->count_records('role_capabilities',
        ['roleid' => $rol->id, 'permission' => CAP_PROHIBIT]);
    $comprobar('capacidades prohibidas al gestor', $prohibidas, fn($v) => $v >= 10,
        'ejecutar 50_rol_gestor.php');

    $asignables = $DB->count_records('role_allow_assign', ['roleid' => $rol->id]);
    $comprobar('roles que el gestor puede asignar', $asignables, 3,
        'sin esto, moodle/role:assign no le sirve de nada');
}

// ═══════════════════════════════════════════════════════════════════════════
cli_writeln('');
cli_separator();
cli_writeln('3 · Clasificación de los cursos');
cli_separator();

$campoarea = $DB->get_record('customfield_field', ['shortname' => 'area'], 'id');
$totalcursos = $DB->count_records_select('course', 'id > 1');
cli_writeln("  cursos en el sitio (sin la portada): $totalcursos");

if ($campoarea) {
    $clasificados = $DB->count_records_sql("
        SELECT COUNT(DISTINCT d.instanceid)
          FROM {customfield_data} d
         WHERE d.fieldid = ? AND d.intvalue IS NOT NULL AND d.intvalue > 0", [$campoarea->id]);
    $comprobar('cursos con el campo `area` completo', $clasificados, $totalcursos,
        'los que faltan quedan invisibles en el catálogo y ausentes de todo informe. ' .
        'Con el sitio recién instalado esto es 0 de 0 y está bien. El caso que hay que ' .
        'mirar es otro y esta línea NO lo ve: un curso duplicado de GC-000 trae `area` ' .
        'completo, pero con el valor de la plantilla. Eso se detecta mirando el catálogo.');
} else {
    $fallos++;
    cli_writeln('  FALLA  no existe el campo `area`');
}

// ═══════════════════════════════════════════════════════════════════════════
cli_writeln('');
cli_separator();
cli_writeln('4 · Vistas');
cli_separator();

$informes = $DB->get_records_select('reportbuilder_report',
    $DB->sql_like('name', ':p'), ['p' => 'Academia · %'], 'name', 'id, name');
$comprobar('informes de la Academia', count($informes), 3, 'ejecutar 70_informes.php');
foreach ($informes as $r) {
    $columnas = $DB->count_records('reportbuilder_column', ['reportid' => $r->id]);
    $filtros  = $DB->count_records('reportbuilder_filter', ['reportid' => $r->id]);
    $audiencias = $DB->count_records('reportbuilder_audience', ['reportid' => $r->id]);
    cli_writeln(sprintf('        %-46s %2d col · %2d filtros · %d audiencia(s)',
        mb_substr($r->name, 0, 45), $columnas, $filtros, $audiencias));
    cli_writeln("          {$CFG->wwwroot}/reportbuilder/view.php?id={$r->id}");
    if ($columnas === 0 || $filtros === 0) {
        $fallos++;
        cli_writeln('        FALLA: un informe sin columnas o sin filtros no sirve');
    }
}

$temaactivo = get_config('core', 'theme');
$comprobar('tema activo', $temaactivo, 'academia',
    'con otro tema el sitio funciona igual, pero sin la identidad ni las reglas del estándar');

// ═══════════════════════════════════════════════════════════════════════════
cli_writeln('');
cli_separator();
cli_writeln('5 · Cursos construidos');
cli_separator();

foreach (['GC-000' => 'plantilla maestra', 'IF-151' => 'esqueleto', 'TR-104' => 'esqueleto'] as $sn => $que) {
    $curso = $DB->get_record('course', ['shortname' => $sn], 'id, fullname, visible');
    if (!$curso) {
        $fallos++;
        printf("  %-5s %-46s %s\n", 'FALLA', "$sn ($que)", 'no existe');
        continue;
    }
    $modulos = $DB->count_records('course_modules', ['course' => $curso->id]);
    $conrestriccion = $DB->count_records_select('course_modules',
        'course = ? AND availability IS NOT NULL', [$curso->id]);
    printf("  %-5s %-46s %2d actividades · %2d con restricción%s\n",
        ' ok ', "$sn ($que)", $modulos, $conrestriccion,
        $curso->visible ? '   <<< VISIBLE' : ' · oculto');
    if ($curso->visible) {
        $avisos++;
        cli_writeln('        → está visible y todavía es un esqueleto sin contenido');
    }
}

// El banco de preguntas de IF-151.
$if151 = $DB->get_record('course', ['shortname' => 'IF-151'], 'id');
if ($if151) {
    $contexto = context_course::instance($if151->id);
    $preguntas = (int)$DB->count_records_sql("
        SELECT COUNT(DISTINCT qbe.id)
          FROM {question_bank_entries} qbe
          JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
         WHERE qc.contextid = ?", [$contexto->id]);
    $comprobar('preguntas en el banco de IF-151', $preguntas, 60,
        '35 formativas + 21 de certificación + 4 integradoras');

    $categorias = $DB->count_records_select('question_categories',
        'contextid = ? AND parent > 0', [$contexto->id]);
    $comprobar('categorías del banco de IF-151', $categorias, fn($v) => $v >= 15,
        'la separación Formativa / Certificación es lo que impide que un ítem ' .
        'reservado aparezca en una evaluación de lección');
}

// ═══════════════════════════════════════════════════════════════════════════
cli_writeln('');
cli_separator();
cli_writeln('6 · Ajustes del Anexo B');
cli_separator();

$comprobar('finalización de actividad', empty($CFG->enablecompletion) ? 'apagada' : 'encendida',
    'encendida', 'sin esto no hay informes, certificados ni competencias');
$comprobar('restricciones de acceso', empty($CFG->enableavailability) ? 'apagadas' : 'encendidas',
    'encendidas');
$comprobar('servicios web', empty($CFG->enablewebservices) ? 'apagados' : 'encendidos', 'encendidos');
$comprobar('servicio móvil', empty($CFG->enablemobilewebservice) ? 'apagado' : 'encendido',
    'encendido', 'la app oficial no funciona sin esto');

// La casilla móvil marcada NO basta: hacen falta el servicio, REST y la capacidad.
if (!empty($CFG->enablemobilewebservice)) {
    $servicioactivo = $DB->get_field('external_services', 'enabled',
        ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE]);
    $comprobar('  · servicio moodle_mobile_app', $servicioactivo ? 'habilitado' : 'DESHABILITADO',
        'habilitado', 'la casilla está marcada pero la app no se conecta');

    $protocolos = (string)($CFG->webserviceprotocols ?? '');
    $comprobar('  · protocolo REST', str_contains($protocolos, 'rest') ? 'sí' : 'no', 'sí');

    $tienecap = $DB->record_exists('role_capabilities', [
        'permission' => CAP_ALLOW,
        'roleid'     => $CFG->defaultuserroleid ?? 0,
        'capability' => 'webservice/rest:use',
    ]);
    $comprobar('  · webservice/rest:use al autenticado', $tienecap ? 'sí' : 'no', 'sí');
}

$brickfield = get_config('tool_brickfield', 'enableaccessibilitytools');
$comprobar('Kit de Accesibilidad', $brickfield ? 'encendido' : 'apagado', 'encendido',
    'WCAG 2.2 AA es exigible por el Decreto N.º 1 y la Ley 21.180');

$scormpopup = $DB->count_records('scorm', ['popup' => 1]);
$comprobar('SCORM en ventana emergente', $scormpopup, 0,
    'los navegadores de teléfono las bloquean: el contenido queda inutilizable en terreno');

// ─── Correo: en v2 tiene que estar frenado ──────────────────────────────────
$comprobar('MOODLE_NOEMAILEVER', empty($CFG->noemailever) ? 'APAGADO' : 'encendido',
    'encendido',
    'la Academia se está construyendo: sin este freno, el cron manda avisos de foro y ' .
    'de contraseña desde un sitio a medio armar. Apagarlo es una decisión con fecha, ' .
    'no un ajuste que se corrija acá.');

// ─── Tareas adhoc en bucle ──────────────────────────────────────────────────
$fallando = (int)$DB->count_records_select('task_adhoc', 'faildelay > 0');
if ($fallando > 0) {
    $avisar('tareas adhoc con fallos acumulados', $fallando,
        'ver el detalle con 95_ajustes_sitio.php; el sospechoso conocido es que falte ' .
        'ghostscript para assignfeedback_editpdf');
}

// ═══════════════════════════════════════════════════════════════════════════
cli_writeln('');
cli_separator();
if ($fallos === 0) {
    cli_writeln("VERIFICACIÓN AUTOMÁTICA: todo cuadra." .
        ($avisos ? "  ($avisos aviso" . ($avisos > 1 ? 's' : '') . ')' : ''));
} else {
    cli_problem("VERIFICACIÓN AUTOMÁTICA: $fallos comprobación(es) fallaron.");
}
cli_separator();

// ═══════════════════════════════════════════════════════════════════════════
cli_writeln('');
cli_writeln('LO QUE ESTO NO VERIFICA, Y HAY QUE MIRAR CON LOS PROPIOS OJOS');
cli_writeln('');
cli_writeln('  1. Entrar por navegador como administrador. Subir un PDF a la carpeta de');
cli_writeln('     material de GC-000 y abrirlo: confirma que moodledata se LEE. El sitio');
cli_writeln('     parte con moodledata vacío, así que primero hay que poner algo adentro.');
cli_writeln('  2. Subir un archivo desde la web y encontrarlo en el filedir del host:');
cli_writeln('     confirma que se ESCRIBE fuera del contenedor, y con el dueño correcto.');
cli_writeln('  3. Abrir el catálogo, aplicar un filtro de área y ver que la lista cambia.');
cli_writeln('     Si sale vacío, el problema son cursos sin clasificar, no el informe.');
cli_writeln('  4. Poner primero un curso cualquiera, oculto, en la categoría 04 —sin él la');
cli_writeln('     prueba no prueba nada, porque en un sitio vacío «no ve los de 04» se cumple');
cli_writeln('     solo—. Después crear una cuenta de prueba con rol Gestor de Área SOLO en la');
cli_writeln('     categoría 01, entrar con ella y comprobar que puede crear un curso en 01 y');
cli_writeln('     que NO ve el de 04. Si lo ve, la delegación no está implementada por muy');
cli_writeln('     creado que esté el rol.');
cli_writeln('  5. Entrar a IF-151 con una cuenta de estudiante: la lección 2 tiene que');
cli_writeln('     aparecer BLOQUEADA hasta aprobar la evaluación 1.');
cli_writeln('  6. Recorrer el catálogo y una lección en un TELÉFONO REAL, no en el emulador.');
cli_writeln('  7. Comprobar que coipo_moodle en el puerto 8115 sigue respondiendo 200. Es el');
cli_writeln('     archivo histórico mientras siga vivo, y las dos instancias comparten el rol');
cli_writeln('     `academia` de PostgreSQL: si esta se lleva las conexiones, la que atiende a');
cli_writeln('     la gente empieza a dar 500. El techo del rol es 60 y se reparte entre las');
cli_writeln('     dos; el reparto vigente, el orden en que se cambia y las consultas de');
cli_writeln('     pg_stat_activity que lo miden están en docker/apache-moodle.conf y en el');
cli_writeln('     bloque "Concurrencia" de .env.v2.example.');
cli_writeln('');
cli_writeln('  Y borrar la cuenta de prueba del punto 4 al terminar.');
cli_writeln('');

exit($fallos === 0 ? 0 : 1);
