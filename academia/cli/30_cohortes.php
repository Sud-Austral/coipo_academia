<?php
// Crea las cohortes de la Academia a nivel de sistema, vacías.
//
// Hoy la plataforma tiene cero. Son la pieza que convierte «región» y «perfil»
// en atributos consultables, y lo que reemplaza la práctica de crear un curso
// nuevo por cada edición.
//
// Este script crea las cohortes; NO las puebla. La pertenencia se carga a mano o
// por CSV mientras la identidad sea manual, y se sincroniza desde el directorio
// institucional en la etapa 2 del escalamiento.
//
//   docker compose exec -u www-data app php /opt/academia/cli/30_cohortes.php --dry-run
//   docker compose exec -u www-data app php /opt/academia/cli/30_cohortes.php

require(__DIR__ . '/bootstrap.php');

require_once($CFG->dirroot . '/cohort/lib.php');

$opciones = academia_cli_inicio(
    'Crea las cohortes desde academia/datos/cohortes.csv, vacías y a nivel de sistema.');

$reporte = new academia_reporte('Cohortes', $opciones['dry-run']);
$filas = academia_leer_csv('cohortes.csv');

$contexto = context_system::instance();

// Las cohortes se buscan por idnumber en TODO el sitio, no solo en el contexto
// del sistema: un idnumber repetido en un contexto de categoría produce dos
// cohortes que se ven iguales y matriculan a gente distinta.
$existentes = [];
foreach ($DB->get_records_select('cohort', "idnumber IS NOT NULL AND idnumber <> ''",
        null, '', 'id, idnumber, name, contextid, visible') as $c) {
    $existentes[$c->idnumber] = $c;
}

$porfamilia = [];

foreach ($filas as $fila) {
    $idnumber = $fila['idnumber'];
    $nombre   = $fila['nombre'];
    $familia  = $fila['familia'];
    $etiqueta = "$idnumber · $nombre";

    $porfamilia[$familia] = ($porfamilia[$familia] ?? 0) + 1;

    if (isset($existentes[$idnumber])) {
        $actual = $existentes[$idnumber];

        if ((int)$actual->contextid !== (int)$contexto->id) {
            $reporte->omitido($etiqueta,
                'existe pero en otro contexto (id ' . $actual->contextid . '). Una cohorte fuera ' .
                'del contexto del sistema solo la ven los cursos de esa categoría.');
            continue;
        }

        if ($actual->name === $nombre) {
            $reporte->existia($etiqueta);
            continue;
        }

        if (!$reporte->es_simulacion()) {
            $actual->name = $nombre;
            cohort_update_cohort($actual);
        }
        $reporte->corregido($etiqueta, 'nombre');
        continue;
    }

    if ($reporte->es_simulacion()) {
        $reporte->creado($etiqueta, $familia);
        continue;
    }

    try {
        $cohorte = (object)[
            'contextid'         => $contexto->id,
            'name'              => $nombre,
            'idnumber'          => $idnumber,
            'description'       => $fila['descripcion'],
            'descriptionformat' => FORMAT_HTML,
            'visible'           => 1,
        ];
        cohort_add_cohort($cohorte);
    } catch (moodle_exception $e) {
        $reporte->error($etiqueta, $e->getMessage());
        continue;
    }

    $reporte->creado($etiqueta, $familia);
}

$codigo = $reporte->resumen();

cli_writeln('');
cli_writeln('Por familia:');
foreach ($porfamilia as $familia => $n) {
    cli_writeln(sprintf('  %-14s %2d', $familia, $n));
}

if (isset($porfamilia['funcional'])) {
    cli_writeln('');
    cli_problem('Las cohortes de la familia «funcional» son una decisión pendiente.');
    cli_writeln('GER-IF-PROF y PERF-AUTOR no están en el modelo de datos del Anexo C.2, pero');
    cli_writeln('IF-151 y TR-104 las exigen. Hay que definir si son una cuarta familia o si');
    cli_writeln('faltaban en la ocupacional — de eso depende que el total sea 26 o 28.');
    cli_writeln('El detalle está comentado en academia/datos/cohortes.csv.');
}

cli_writeln('');
cli_writeln('Quedan VACÍAS. Poblarlas es Usuarios → Cohortes → Subir cohortes (CSV), o la');
cli_writeln('sincronización con el directorio institucional cuando la UIA la construya.');

exit($codigo);
