<?php
// La línea base de configuración del sitio de la Academia.
//
// Nació como «los hallazgos del Anexo B, aplicados» —la auditoría del campus
// viejo— y esa lista sigue siendo la correcta, pero ya no por el traslado: son
// los subsistemas sin los cuales la Academia no funciona. Sobre una instalación
// limpia el script es rápido y aburrido, y la mitad va a decir «ya está». Así
// debe ser.
//
// LO QUE ESTE SCRIPT NO TOCA, Y POR QUÉ:
//
//   · Correo saliente (SMTP). Es justamente lo que MOODLE_NOEMAILEVER impide, y
//     soltar ese freno es una decisión, no un ajuste.
//   · Copias de seguridad automáticas. OJO: acá decía antes que los respaldos de
//     un clon desechable no protegen nada. Ya no hay clon. Lo que esta instancia
//     va a tener adentro —los cursos que se escriban sobre GC-000— no existe en
//     ninguna otra parte. No se enciende acá porque falta decidir dónde y cuánto
//     se retiene, no porque no haga falta.
//
//   docker compose exec -u www-data app php /opt/academia/cli/95_ajustes_sitio.php --dry-run
//   docker compose exec -u www-data app php /opt/academia/cli/95_ajustes_sitio.php

require(__DIR__ . '/bootstrap.php');

$opciones = academia_cli_inicio(
    'Aplica los hallazgos del Anexo B que son configuración del sitio.');

$reporte = new academia_reporte('Ajustes del sitio', $opciones['dry-run']);

/**
 * Fija un ajuste si no está ya en el valor esperado.
 */
$fijar = function (string $nombre, $valor, string $porque, ?string $plugin = null)
        use ($reporte, $CFG): void {
    $actual = $plugin === null
        ? ($CFG->{$nombre} ?? null)
        : get_config($plugin, $nombre);

    $etiqueta = ($plugin === null ? '' : "$plugin/") . $nombre;

    if ((string)$actual === (string)$valor) {
        $reporte->existia($etiqueta, "ya está en $valor");
        return;
    }

    if (!$reporte->es_simulacion()) {
        set_config($nombre, $valor, $plugin);
        if ($plugin === null) {
            $CFG->{$nombre} = $valor;
        }
    }
    $reporte->corregido($etiqueta,
        ($actual === null || $actual === false ? '(sin definir)' : $actual) . " → $valor · $porque");
};

// ─── Terreno: la aplicación móvil ───────────────────────────────────────────
// «Probablemente el clic de mayor impacto pendiente en el sitio.» El dispositivo
// real de un brigadista o un guardaparque no es un computador de escritorio.
cli_writeln('');
cli_writeln('── Terreno ────────────────────────────────────────────────────────');
// ATENCIÓN: `enablemobilewebservice` NO es una casilla suelta.
//
// En la interfaz, marcarla dispara admin_setting_enablemobileservice::write_setting()
// (lib/adminlib.php:9803), que además enciende los servicios web, habilita el
// servicio externo «moodle_mobile_app», agrega el protocolo REST y le da la
// capacidad webservice/rest:use al rol de usuario autenticado.
//
// Un set_config() suelto deja el ajuste en 1 y la app SIGUE sin funcionar: el
// administrador ve la casilla marcada, el usuario ve «no se puede conectar», y
// no hay ningún error que relacione las dos cosas. Por eso acá se replican los
// cuatro pasos.
$fijar('enablewebservices', 1,
    'sin servicios web, el ajuste móvil no hace nada');
$fijar('enablemobilewebservice', 1,
    'la app oficial de Moodle, con acceso sin conexión');

if (!$reporte->es_simulacion()) {
    require_once($CFG->dirroot . '/webservice/lib.php');
    $gestor = new webservice();

    $servicio = $gestor->get_external_service_by_shortname(MOODLE_OFFICIAL_MOBILE_SERVICE);
    if ($servicio && !$servicio->enabled) {
        $servicio->enabled = 1;
        $gestor->update_external_service($servicio);
        $reporte->corregido('servicio moodle_mobile_app', 'habilitado');
    } else {
        $reporte->existia('servicio moodle_mobile_app', 'ya estaba habilitado');
    }

    $protocolos = empty($CFG->webserviceprotocols) ? [] : explode(',', $CFG->webserviceprotocols);
    if (!in_array('rest', $protocolos, true)) {
        $protocolos[] = 'rest';
        set_config('webserviceprotocols', implode(',', $protocolos));
        $reporte->corregido('webserviceprotocols', 'REST habilitado');
    } else {
        $reporte->existia('webserviceprotocols', 'REST ya estaba');
    }

    if (!empty($CFG->defaultuserroleid)) {
        $sistema = context_system::instance();
        $tiene = $DB->record_exists('role_capabilities', [
            'permission' => CAP_ALLOW,
            'roleid'     => $CFG->defaultuserroleid,
            'capability' => 'webservice/rest:use',
        ]);
        if (!$tiene) {
            assign_capability('webservice/rest:use', CAP_ALLOW,
                $CFG->defaultuserroleid, $sistema->id, true);
            $reporte->corregido('webservice/rest:use', 'permitida al usuario autenticado');
        } else {
            $reporte->existia('webservice/rest:use', 'ya estaba permitida');
        }
    } else {
        $reporte->error('webservice/rest:use', 'no hay defaultuserroleid en el sitio');
    }
} else {
    $reporte->corregido('servicio moodle_mobile_app + REST + rest:use',
        'los tres pasos que la casilla dispara por debajo');
}

// El SCORM en ventana emergente es lo que vuelve un contenido inutilizable en
// teléfono: los navegadores móviles bloquean esas ventanas. El sitio parte sin
// ningún SCORM, así que la corrección de los existentes no va a encontrar nada;
// el que importa es el valor por defecto, que es el que heredarán los que suban.
$fijar('popup', 0, 'los SCORM nuevos se abren en la misma ventana', 'scorm');

$encolarpopup = $DB->count_records('scorm', ['popup' => 1]);
if ($encolarpopup > 0) {
    if (!$reporte->es_simulacion()) {
        $DB->set_field('scorm', 'popup', 0, ['popup' => 1]);
    }
    $reporte->corregido('SCORM existentes',
        "$encolarpopup paquete(s) pasan de ventana emergente a la misma ventana");
} else {
    $reporte->existia('SCORM existentes', 'ninguno abre en ventana emergente');
}

// ─── Modelo de datos: lo que hay que encender ───────────────────────────────
cli_writeln('');
cli_writeln('── Subsistemas que la Academia necesita ────────────────────────────');
$fijar('enablecompletion', 1,
    'sin finalización, Moodle no sabe quién terminó qué: no hay informes ni certificados');
$fijar('enableavailability', 1,
    'las restricciones que encadenan las lecciones');
$fijar('enablecompetencies', 1,
    'convierte «aprobó siete cursos» en «está habilitado como jefe de cuadrilla»');
$fijar('enablebadges', 1,
    'Open Badges: ya habilitado en la plataforma, sin usar');

// ─── Accesibilidad ──────────────────────────────────────────────────────────
// WCAG 2.2 nivel AA es exigible por el Decreto N.º 1, la Ley 21.180 de
// Transformación Digital del Estado y la Ley 20.422. La herramienta de auditoría
// ya viene con Moodle y está sin usar.
cli_writeln('');
cli_writeln('── Accesibilidad ──────────────────────────────────────────────────');
$fijar('enableaccessibilitytools', 1,
    'Kit de Accesibilidad (tool_brickfield): audita los cursos', 'tool_brickfield');

// ─── Idioma ─────────────────────────────────────────────────────────────────
cli_writeln('');
cli_writeln('── Idioma ─────────────────────────────────────────────────────────');
$fijar('lang', 'es', 'que el sitio no responda en inglés al visitante no autenticado');

// ─── Identidad visual ──────────────────────────────────────────────────
// EL TEMA NO SE ACTIVA SOLO. Moodle instala con `boost` y ahí se queda: mientras
// nadie fije este valor, theme_academia está copiado en disco por el Dockerfile
// pero no lo usa nadie, y 99_verificar.php falla en «tema activo» sin que se vea
// nada roto —el sitio funciona igual, solo que sin la identidad ni las reglas del
// estándar—.
//
// Antes esta línea no hacía falta y por eso no estaba: la base se clonaba de
// producción y el tema activo venía puesto dentro del volcado. Con la instalación
// limpia el paso aparece, y es de los que se pierden con más facilidad
// justamente porque el sitio «se ve bien» sin él.
//
// theme_academia es HIJO DE BOOST (su config.php: $THEME->parents = ['boost']),
// así que no arrastra nada del campus viejo: no depende de boost_magnific ni de
// academi, que ya no se copian a la imagen.
cli_writeln('');
cli_writeln('── Identidad visual ───────────────────────────────────────────');
$fijar('theme', 'academia',
    'el tema propio de la Academia; sin esto el sitio se queda en el Boost por defecto');

// ─── Avisos de versión ──────────────────────────────────────────────────────
// «Un sitio público que no se entera de las liberaciones de seguridad es un
// riesgo.»
cli_writeln('');
cli_writeln('── Actualizaciones ────────────────────────────────────────────────');
$fijar('disableupdatenotifications', 0, 'volver a recibir avisos de versiones nuevas');
$fijar('updateautocheck', 1, 'comprobación automática diaria');
$fijar('updateminmaturity', MATURITY_STABLE, 'avisar solo de versiones estables');
$fijar('updatenotifybuilds', 0, 'no avisar de compilaciones intermedias');

// ─── Superficie que sobra ───────────────────────────────────────────────────
cli_writeln('');
cli_writeln('── Superficie innecesaria en un organismo público ──────────────────');
$habilitados = explode(',', (string)get_config('core', 'enrol_plugins_enabled'));
if (in_array('fee', $habilitados, true)) {
    if (!$reporte->es_simulacion()) {
        set_config('enrol_plugins_enabled',
            implode(',', array_diff($habilitados, ['fee'])));
    }
    $reporte->corregido('enrol_fee', 'matriculación de pago deshabilitada');
} else {
    $reporte->existia('enrol_fee', 'ya estaba deshabilitada');
}

academia_purgar_caches($reporte);

$codigo = $reporte->resumen();


// ═══════════════════════════════════════════════════════════════════════════
// Lo que hay que MIRAR, no cambiar
// ═══════════════════════════════════════════════════════════════════════════

cli_writeln('');
cli_separator();
cli_writeln('Lo que este script NO cambia, y hay que revisar a mano');
cli_separator();

// ─── Antivirus ──────────────────────────────────────────────────────────────
// Deliberadamente NO se enciende. Habilitar ClamAV sin el demonio instalado hace
// que TODA subida de archivo falle con «el análisis antivirus falló» — se pasa
// de «archivos sin revisar» a «nadie puede subir nada», que es peor.
$rutaclam = trim((string)shell_exec('command -v clamdscan 2>/dev/null'));
$antivirus = (string)get_config('core', 'antiviruses');

cli_writeln('');
cli_writeln('ANTIVIRUS  — Anexo B lo marca como «instalado y deshabilitado».');
cli_writeln('  antiviruses actual: ' . ($antivirus === '' ? '(ninguno)' : $antivirus));
cli_writeln('  clamdscan en la imagen: ' . ($rutaclam !== '' ? $rutaclam : 'NO ESTÁ'));
if ($rutaclam === '') {
    cli_writeln('  El Dockerfile no instala ClamAV. Encender el antivirus sin el demonio');
    cli_writeln('  hace que toda subida de archivo falle: se pasa de «los archivos entran sin');
    cli_writeln('  revisión» a «nadie puede subir nada». Primero agregar clamav-daemon al');
    cli_writeln('  Dockerfile, verificar que el socket responde, y recién ahí encenderlo.');
} else {
    cli_writeln('  Está disponible: se puede encender con antiviruses=clamav.');
}

// ─── Cron ───────────────────────────────────────────────────────────────────
// El Anexo B dice «se ejecutan cada 4 minutos; deberían hacerlo cada 1».
// En ESTE repositorio el crontab ya pide cada minuto, así que si se observan 4,
// la causa no es la programación.
$ultimo = (int)get_config('tool_task', 'lastcronstart');
if (!$ultimo) {
    $ultimo = (int)($CFG->lastcron ?? 0);
}
cli_writeln('');
cli_writeln('CRON  — docker/moodle-crontab de este repositorio ya pide «* * * * *».');
if ($ultimo) {
    $hace = time() - $ultimo;
    cli_writeln('  última ejecución hace ' . $hace . ' s' .
        ($hace > 180 ? '   <<< más de 3 minutos: revisar el contenedor cron' : ''));
} else {
    cli_writeln('  todavía no hay registro de ejecución.');
}
cli_writeln('  Si se observan 4 minutos con el crontab en 1, la causa está en el contenedor,');
cli_writeln('  no en la programación: revisar `docker compose logs cron`.');

// ─── Tareas adhoc fallando ──────────────────────────────────────────────────
// Las 11 tareas assignfeedback_editpdf en bucle que anota CLAUDE.md son del sitio
// viejo y no viajan a una instalación limpia. Lo que sí viaja es la causa: el
// Dockerfile sigue sin instalar ghostscript, así que la primera vez que alguien
// anote un PDF en la tarea de TR-104 el problema reaparece acá igual.
$fallando = $DB->get_records_sql("
    SELECT classname, COUNT(*) AS n, MAX(faildelay) AS peor
      FROM {task_adhoc}
     WHERE faildelay > 0
  GROUP BY classname
  ORDER BY peor DESC");
cli_writeln('');
cli_writeln('TAREAS ADHOC CON FALLOS');
if (!$fallando) {
    cli_writeln('  ninguna.');
} else {
    foreach ($fallando as $t) {
        cli_writeln(sprintf('  %-58s %2d tareas · faildelay %d', $t->classname, $t->n, $t->peor));
    }
    $rutags = trim((string)shell_exec('command -v gs 2>/dev/null'));
    cli_writeln('  ghostscript en la imagen: ' . ($rutags !== '' ? $rutags : 'NO ESTÁ'));
    cli_writeln('  Es el síntoma de qué NO viaja en una migración: la base y los archivos sí,');
    cli_writeln('  las dependencias del sistema operativo hay que reinstalarlas.');
}

// ─── Respaldos y correo: de producción, no de acá ───────────────────────────
cli_writeln('');
cli_writeln('RESPALDOS Y CORREO SALIENTE  — los dos hallazgos CRÍTICOS del Anexo B.');
cli_writeln('  No se tocan acá, y por motivos distintos: ninguno de los dos es «no hace falta».');
cli_writeln('    · RESPALDOS: esto ya no es un clon. Los cursos que se escriban acá no');
cli_writeln('      existen en ninguna otra parte y hoy no hay ninguna política. Es el');
cli_writeln('      pendiente más grande que este script deja sin resolver.');
cli_writeln('    · CORREO: sigue frenado por MOODLE_NOEMAILEVER. Ya no protege 2.869 buzones');
cli_writeln('      reales —acá hay un puñado de cuentas de construcción—: protege de que un');
cli_writeln('      sitio a medio armar empiece a mandar avisos.');

// ─── Lo que hay que hacer DESPUÉS de este script ───────────────────────
cli_writeln('');
cli_writeln('AHORA, DOS COMANDOS — y en este orden:');
cli_writeln('  docker compose exec -u www-data app php admin/cli/purge_caches.php');
cli_writeln('  docker compose exec -u www-data app php admin/cli/build_theme_css.php');
cli_writeln('');
cli_writeln('  Cambiar el tema sin purgar deja el sitio con el CSS anterior y parece que');
cli_writeln('  la línea de arriba no hizo nada. Y purgar SIN recompilar obliga a rehacer');
cli_writeln('  1,7 MB de SCSS en la primera visita: es el origen de los episodios de');
cli_writeln('  «se congeló». Los dos, siempre, y siempre con -u www-data.');

cli_separator();

exit($codigo);
