<?php
// Enciende el subsistema de competencias y carga los marcos de la Academia.
//
// tool_lpimportcsv, que es la herramienta oficial para esto, NO tiene interfaz de
// línea de comandos: solo index.php web. Por eso acá se usa directamente la API
// \core_competency\api, que es lo mismo que usa esa herramienta por debajo.
//
//   docker compose exec -u www-data app php /opt/academia/cli/40_competencias.php --dry-run
//   docker compose exec -u www-data app php /opt/academia/cli/40_competencias.php

require(__DIR__ . '/bootstrap.php');

require_once($CFG->libdir . '/gradelib.php');

use core_competency\api as competency_api;
use core_competency\competency_framework;

// La escala de logro del Anexo C.3: tres valores, el último es «competente».
const ACADEMIA_ESCALA_VALORES = 'No demostrada,En desarrollo,Demostrada';
const ACADEMIA_ESCALA_NOMBRE  = 'Logro de competencia — Academia CONAF';

$opciones = academia_cli_inicio(
    'Enciende las competencias y carga los marcos desde academia/datos/competencias.csv.');

$reporte = new academia_reporte('Marcos de competencias', $opciones['dry-run']);
$filas = academia_leer_csv('competencias.csv');

// ─── 1. Encender el subsistema ──────────────────────────────────────────────
// Está instalado y apagado. Sin esto, todo lo demás se crea pero no se ve en
// ninguna parte del sitio: ni en el curso, ni en el perfil, ni en los informes.
if (empty($CFG->enablecompetencies)) {
    if (!$reporte->es_simulacion()) {
        set_config('enablecompetencies', 1);
        $CFG->enablecompetencies = 1;
    }
    $reporte->creado('subsistema de competencias', 'encendido');
} else {
    $reporte->existia('subsistema de competencias', 'ya estaba encendido');
}

// ─── 2. La escala de logro ──────────────────────────────────────────────────
// Escala global (courseid = 0). Se busca por sus valores exactos y no por el
// nombre: es lo mismo que hace tool_lpimportcsv, y evita crear una escala nueva
// cada vez que alguien le cambia el título.
$idescala = null;
foreach (grade_scale::fetch_all_global() as $escala) {
    $escala->load_items();
    if ($escala->compact_items() === ACADEMIA_ESCALA_VALORES) {
        $idescala = (int)$escala->id;
        $reporte->existia('escala «' . ACADEMIA_ESCALA_VALORES . '»');
        break;
    }
}

if ($idescala === null) {
    if ($reporte->es_simulacion()) {
        $reporte->creado('escala «' . ACADEMIA_ESCALA_VALORES . '»');
        $idescala = -1;
    } else {
        $nueva = new grade_scale();
        $nueva->name        = ACADEMIA_ESCALA_NOMBRE;
        $nueva->courseid    = 0;
        $nueva->userid      = $USER->id;
        $nueva->scale       = ACADEMIA_ESCALA_VALORES;
        $nueva->description = 'Escala de logro de los marcos de competencias de la Academia CONAF. ' .
            'El tercer valor, «Demostrada», es el que declara a la persona competente.';
        $nueva->insert();
        $idescala = (int)$nueva->id;
        $reporte->creado('escala «' . ACADEMIA_ESCALA_VALORES . '»', "id $idescala");
    }
}

// La configuración de la escala: el primer elemento lleva el id de la escala, y
// después uno por valor que se quiera marcar. Moodle EXIGE que haya exactamente
// un `scaledefault` y al menos un `proficient`; si falta cualquiera de los dos,
// la creación del marco falla con «errorscaleconfiguration», que no dice cuál.
$configescala = json_encode([
    ['scaleid' => $idescala],
    ['id' => 1, 'scaledefault' => 1, 'proficient' => 0],  // No demostrada — el valor por defecto
    ['id' => 3, 'scaledefault' => 0, 'proficient' => 1],  // Demostrada  — declara competente
]);

$contexto = context_system::instance();

// ─── 3. Marcos, unidades y competencias ─────────────────────────────────────
$marcos = [];         // idnumber del marco  => competency_framework
$competencias = [];   // idnumber            => competency

foreach ($DB->get_records('competency_framework', null, '', 'id, idnumber, shortname') as $f) {
    $marcos[$f->idnumber] = $f;
}
foreach ($DB->get_records('competency', null, '', 'id, idnumber, shortname, competencyframeworkid, parentid') as $c) {
    $competencias[$c->idnumber] = $c;
}

foreach ($filas as $fila) {
    $nivel    = $fila['nivel'];
    $idnumber = $fila['idnumber'];
    $nombre   = $fila['nombre'];
    $etiqueta = "$idnumber · " . mb_substr($nombre, 0, 60);

    // La correspondencia internacional se guarda dentro de la descripción:
    // Moodle no tiene campos personalizados en competencias. Queda visible y
    // exportable, pero NO se puede filtrar ni agrupar por ella.
    $descripcion = $fila['descripcion'];
    if ($fila['correspondencia'] !== '') {
        $descripcion = trim($descripcion . ' <br><strong>Correspondencia internacional (propuesta, ' .
            'pendiente de validación):</strong> ' . $fila['correspondencia']);
    }

    if ($nivel === 'marco') {
        if (isset($marcos[$idnumber])) {
            $reporte->existia($etiqueta, 'marco');
            continue;
        }
        if ($reporte->es_simulacion()) {
            $reporte->creado($etiqueta, 'marco');
            $marcos[$idnumber] = (object)['id' => -1, 'idnumber' => $idnumber];
            continue;
        }
        try {
            $marco = competency_api::create_framework((object)[
                'shortname'          => $nombre,
                'idnumber'           => $idnumber,
                'description'        => $descripcion,
                'descriptionformat'  => FORMAT_HTML,
                'visible'            => 1,
                'scaleid'            => $idescala,
                'scaleconfiguration' => $configescala,
                'contextid'          => $contexto->id,
                // Tres niveles: unidad, competencia y un tercero por si alguna
                // competencia necesita desglosarse en indicadores.
                'taxonomies'         => implode(',', [
                    competency_framework::TAXONOMY_DOMAIN,
                    competency_framework::TAXONOMY_COMPETENCY,
                    competency_framework::TAXONOMY_INDICATOR,
                ]),
            ]);
        } catch (Exception $e) {
            $reporte->error($etiqueta, $e->getMessage());
            continue;
        }
        $marcos[$idnumber] = (object)['id' => $marco->get('id'), 'idnumber' => $idnumber];
        $reporte->creado($etiqueta, 'marco');
        continue;
    }

    // Unidades y competencias son las dos filas de mdl_competency; lo único que
    // las distingue es que la unidad cuelga de la raíz y la competencia de ella.
    if (!isset($marcos[$fila['marco']])) {
        $reporte->error($etiqueta, "no existe el marco {$fila['marco']} (¿va antes en el CSV?)");
        continue;
    }
    $idmarco = (int)$marcos[$fila['marco']]->id;

    $idpadre = 0;
    if ($nivel === 'competencia') {
        if ($fila['padre'] === '') {
            $reporte->error($etiqueta, 'una competencia necesita su unidad madre en la columna padre');
            continue;
        }
        if (!isset($competencias[$fila['padre']])) {
            $reporte->error($etiqueta, "no existe la unidad {$fila['padre']}");
            continue;
        }
        $idpadre = (int)$competencias[$fila['padre']]->id;
    }

    if (isset($competencias[$idnumber])) {
        $reporte->existia($etiqueta, $nivel);
        continue;
    }

    if ($reporte->es_simulacion()) {
        $reporte->creado($etiqueta, $nivel);
        $competencias[$idnumber] = (object)['id' => -1, 'idnumber' => $idnumber];
        continue;
    }

    try {
        $competencia = competency_api::create_competency((object)[
            'shortname'             => $nombre,
            'idnumber'              => $idnumber,
            'description'           => $descripcion,
            'descriptionformat'     => FORMAT_HTML,
            'competencyframeworkid' => $idmarco,
            'parentid'              => $idpadre,
        ]);
    } catch (Exception $e) {
        $reporte->error($etiqueta, $e->getMessage());
        continue;
    }

    $competencias[$idnumber] = (object)['id' => $competencia->get('id'), 'idnumber' => $idnumber];
    $reporte->creado($etiqueta, $nivel);
}

academia_purgar_caches($reporte);

$codigo = $reporte->resumen();

cli_writeln('');
cli_writeln('Falta vincular cada competencia a su curso: eso lo hace 90_cursos_esqueleto.php');
cli_writeln('para IF-151 y TR-104. Las demás quedan sin ningún curso que las acredite, y así');
cli_writeln('van a seguir hasta que alguien construya ese curso: el marco se carga completo a');
cli_writeln('propósito, para que se vea cuánto falta. Una competencia sin curso que la');
cli_writeln('acredite no la alcanza nadie nunca.');
cli_writeln('');
cli_writeln('Y OJO CON EL CRON: al encender competencias, las tareas programadas se cargan');
cli_writeln('más. Si siguen corriendo cada 4 minutos en vez de cada 1, la emisión de');
cli_writeln('certificados y el logro de competencias se retrasan. Lo corrige 95_ajustes_sitio.php.');

exit($codigo);
