<?php
// Crea los ocho campos personalizados de curso, dentro de la categoría de campos
// «Clasificación institucional».
//
// Es el trabajo que condiciona todo lo demás: cada filtro del catálogo, cada
// barra del tablero y cada línea de la trayectoria formativa se apoya en uno de
// estos campos. Ninguna herramienta de reportería puede filtrar por una columna
// que no existe.
//
// OJO CON EL ORDEN. Siete de los ocho campos quedan como obligatorios, así que
// desde que este script corre, el formulario de curso los exige. En un sitio
// vacío eso no deja nada a medias —no hay cursos heredados—, pero sí cambia el
// trabajo de quien cree un curso de ahí en adelante: son siete decisiones antes
// de poder guardar. Correrlo ANTES que 80 y 90, que crean cursos y los completan
// solos.
//
//   docker compose exec -u www-data app php /opt/academia/cli/20_campos_curso.php --dry-run
//   docker compose exec -u www-data app php /opt/academia/cli/20_campos_curso.php

require(__DIR__ . '/bootstrap.php');

use core_course\customfield\course_handler;
use core_customfield\api as customfield_api;
use core_customfield\category_controller;
use core_customfield\field_controller;

const ACADEMIA_CATEGORIA_CAMPOS = 'Clasificación institucional';

$opciones = academia_cli_inicio(
    'Crea los 8 campos personalizados de curso desde academia/datos/campos-curso.csv.');

$reporte = new academia_reporte('Campos personalizados de curso', $opciones['dry-run']);
$filas = academia_leer_csv('campos-curso.csv');

$manejador = course_handler::create();

// ─── La categoría de campos ─────────────────────────────────────────────────
// Agrupa los ocho en el formulario de curso. Sin ella quedarían sueltos bajo
// «Otros campos», que es donde nadie los completa.
$categoria = null;
foreach ($manejador->get_categories_with_fields() as $cat) {
    if ($cat->get('name') === ACADEMIA_CATEGORIA_CAMPOS) {
        $categoria = $cat;
        break;
    }
}

if ($categoria) {
    $reporte->existia('categoría de campos «' . ACADEMIA_CATEGORIA_CAMPOS . '»');
} else if ($reporte->es_simulacion()) {
    $reporte->creado('categoría de campos «' . ACADEMIA_CATEGORIA_CAMPOS . '»');
} else {
    $idcategoria = $manejador->create_category(ACADEMIA_CATEGORIA_CAMPOS);
    $categoria = category_controller::create($idcategoria);
    $reporte->creado('categoría de campos «' . ACADEMIA_CATEGORIA_CAMPOS . '»');
}

// Campos que ya existen en el sitio, por shortname. Se buscan en TODA la
// instalación y no solo en nuestra categoría: el shortname es único en el área,
// así que un campo homónimo en otra categoría haría fallar la creación con un
// error que no dice cuál es el problema.
$existentes = [];
foreach ($manejador->get_categories_with_fields() as $cat) {
    foreach ($cat->get_fields() as $campo) {
        $existentes[$campo->get('shortname')] = ['campo' => $campo, 'categoria' => $cat];
    }
}

foreach ($filas as $fila) {
    $shortname = $fila['shortname'];
    $nombre    = $fila['nombre'];
    $tipo      = $fila['tipo'];
    $etiqueta  = "$shortname · $nombre";

    if (!in_array($tipo, ['select', 'number'], true)) {
        $reporte->error($etiqueta, "tipo '$tipo' no soportado por este script (select o number)");
        continue;
    }

    // ─── configdata ─────────────────────────────────────────────────────────
    $configdata = [
        'required'     => (int)$fila['obligatorio'],
        'uniquevalues' => 0,
        'locked'       => 0,
        // VISIBLETOALL: el campo se muestra en el listado de cursos. Es lo que
        // hace que el catálogo filtrable tenga algo que mostrar; con NOTVISIBLE
        // el dato existe pero no se ve en ninguna parte.
        'visibility'   => course_handler::VISIBLETOALL,
    ];

    if ($tipo === 'select') {
        if ($fila['opciones'] === '') {
            $reporte->error($etiqueta, 'es select y no trae opciones');
            continue;
        }
        // Moodle guarda las opciones como texto con un valor por línea.
        $configdata['options'] = implode("\n", explode('|', $fila['opciones']));
        // Sin valor por defecto a propósito: un select con defecto se completa
        // solo y el curso queda mal clasificado sin que nadie lo note.
        $configdata['defaultvalue'] = '';
    } else {
        $configdata['defaultvalue']    = '';
        $configdata['minimumvalue']    = $fila['minimo'] !== '' ? (float)$fila['minimo'] : null;
        $configdata['maximumvalue']    = $fila['maximo'] !== '' ? (float)$fila['maximo'] : null;
        $configdata['decimalplaces']   = 0;
        $configdata['display']         = '{value}';
        $configdata['displaywhenzero'] = '';
    }

    $datos = (object)[
        'name'               => $nombre,
        'shortname'          => $shortname,
        'type'               => $tipo,
        'description_editor' => ['text' => $fila['descripcion'], 'format' => FORMAT_HTML, 'itemid' => 0],
        'configdata'         => $configdata,
    ];

    // ─── ¿Ya existe? ────────────────────────────────────────────────────────
    if (isset($existentes[$shortname])) {
        $campo = $existentes[$shortname]['campo'];
        $catactual = $existentes[$shortname]['categoria'];

        if ($catactual->get('name') !== ACADEMIA_CATEGORIA_CAMPOS) {
            $reporte->omitido($etiqueta,
                'ya existe en la categoría «' . $catactual->get('name') . '» — revisar a mano ' .
                'antes de tocarlo: en una instalación limpia solo puede venir de un plugin, ' .
                'y un campo de plugin no se toca desde acá');
            continue;
        }

        // Comparar lo que de verdad importa: el tipo y la lista de valores. Si
        // alguien editó la descripción por la web, no es motivo para reescribir.
        $configactual = (array)json_decode((string)$campo->get('configdata'), true);
        $difiere = $campo->get('type') !== $tipo
            || ($configactual['options'] ?? '') !== ($configdata['options'] ?? '')
            || (int)($configactual['required'] ?? 0) !== (int)$configdata['required']
            || (int)($configactual['visibility'] ?? 0) !== (int)$configdata['visibility'];

        if (!$difiere) {
            $reporte->existia($etiqueta);
            continue;
        }

        if ($campo->get('type') !== $tipo) {
            $reporte->omitido($etiqueta,
                "está creado como '{$campo->get('type')}' y el CSV pide '$tipo'. Cambiar el tipo " .
                'de un campo con datos los borra: hacerlo a mano y con respaldo.');
            continue;
        }

        if (!$reporte->es_simulacion()) {
            $datos->id = $campo->get('id');
            customfield_api::save_field_configuration($campo, $datos);
        }
        $reporte->corregido($etiqueta, 'opciones/obligatoriedad/visibilidad');
        continue;
    }

    // ─── Crear ──────────────────────────────────────────────────────────────
    if ($reporte->es_simulacion()) {
        $reporte->creado($etiqueta, $tipo . ($configdata['required'] ? ' · obligatorio' : ''));
        continue;
    }

    try {
        $campo = field_controller::create(0, (object)['type' => $tipo], $categoria);
        customfield_api::save_field_configuration($campo, $datos);
    } catch (moodle_exception $e) {
        $reporte->error($etiqueta, $e->getMessage());
        continue;
    }

    $reporte->creado($etiqueta, $tipo . ($configdata['required'] ? ' · obligatorio' : ''));
}

academia_purgar_caches($reporte);

$codigo = $reporte->resumen();

cli_writeln('');
cli_writeln('Desde acá, los campos obligatorios bloquean el formulario de curso hasta que');
cli_writeln('estén completos. Un curso sin clasificar queda invisible en el catálogo y');
cli_writeln('ausente de todo informe, que es el olvido más frecuente del estándar.');
cli_writeln('');
cli_writeln('No hay cursos heredados que clasificar: el sitio está vacío. 60_clasificar_');
cli_writeln('cursos.php pasa a ser la revisión en bloque de los cursos que la Academia vaya');
cli_writeln('creando, y sobre todo de los que nazcan duplicando GC-000: esos heredan la');
cli_writeln('clasificación de la plantilla, no la suya.');

exit($codigo);
