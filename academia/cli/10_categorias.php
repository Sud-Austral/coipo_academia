<?php
// Crea el árbol de áreas temáticas de la Academia, vacío y oculto.
//
// NO mueve ni un curso. Es el método de árbol paralelo de la Parte 3.4 de la
// Propuesta: la estructura nueva coexiste con las 9 categorías actuales y el
// traslado es la Etapa 4, en octubre.
//
//   docker compose exec -u www-data app php /opt/academia/cli/10_categorias.php --dry-run
//   docker compose exec -u www-data app php /opt/academia/cli/10_categorias.php

require(__DIR__ . '/bootstrap.php');

$opciones = academia_cli_inicio(
    'Crea las áreas temáticas desde academia/datos/categorias.csv, sin mover ningún curso.');

$reporte = new academia_reporte('Áreas temáticas', $opciones['dry-run']);
$filas = academia_leer_csv('categorias.csv');

// idnumber => id de categoría, para resolver la columna `padre` sin ir a la base
// en cada fila. Se precarga con lo que ya exista, así el script funciona igual
// tanto si es la primera corrida como si se agregó una subcategoría al CSV.
$porindnumber = [];
foreach ($DB->get_records_select('course_categories', "idnumber IS NOT NULL AND idnumber <> ''",
        null, '', 'id, idnumber, name, visible, parent') as $cat) {
    $porindnumber[$cat->idnumber] = $cat;
}

foreach ($filas as $fila) {
    $idnumber = $fila['idnumber'];
    $nombre   = $fila['nombre'];
    $visible  = (int)$fila['visible'];

    // Resolver la madre. El CSV va en orden, así que si tiene padre ya está creado.
    $idpadre = 0;
    if ($fila['padre'] !== '') {
        if (!isset($porindnumber[$fila['padre']])) {
            $reporte->error("$idnumber $nombre",
                "no existe la categoría madre con idnumber '{$fila['padre']}'");
            continue;
        }
        $idpadre = (int)$porindnumber[$fila['padre']]->id;
    }

    // ─── ¿Ya existe? ────────────────────────────────────────────────────────
    if (isset($porindnumber[$idnumber])) {
        $actual = $porindnumber[$idnumber];
        $difiere = [];
        if ($actual->name !== $nombre) {
            $difiere['name'] = $nombre;
        }
        if ((int)$actual->parent !== $idpadre) {
            $difiere['parent'] = $idpadre;
        }

        // La visibilidad NO se corrige. Si alguien ya publicó el área porque
        // empezó la Etapa 4, volver a ocultarla desde un script sería un
        // retroceso silencioso justo en el momento de mayor riesgo.
        if ((int)$actual->visible !== $visible) {
            cli_writeln("    (visible={$actual->visible} en el sitio, $visible en el CSV — " .
                'se respeta el sitio: la publicación del área es una decisión de la Etapa 4)');
        }

        if (!$difiere) {
            $reporte->existia("$idnumber $nombre");
            continue;
        }

        if (!$reporte->es_simulacion()) {
            $categoria = core_course_category::get($actual->id, MUST_EXIST, true);
            $categoria->update($difiere);
            $porindnumber[$idnumber] = $DB->get_record('course_categories', ['id' => $actual->id],
                'id, idnumber, name, visible, parent');
        }
        $reporte->corregido("$idnumber $nombre", implode(', ', array_keys($difiere)));
        continue;
    }

    // ─── Crear ──────────────────────────────────────────────────────────────
    if ($reporte->es_simulacion()) {
        $reporte->creado("$idnumber $nombre", $idpadre ? "bajo {$fila['padre']}" : 'primer nivel');
        // En simulación no hay id real; se anota uno falso para que las hijas
        // del CSV puedan resolver a su madre y el --dry-run llegue hasta el final.
        $porindnumber[$idnumber] = (object)[
            'id' => -1, 'idnumber' => $idnumber, 'name' => $nombre,
            'visible' => $visible, 'parent' => $idpadre,
        ];
        continue;
    }

    try {
        $nueva = core_course_category::create([
            'name'              => $nombre,
            'idnumber'          => $idnumber,
            'parent'            => $idpadre,
            'description'       => $fila['descripcion'],
            'descriptionformat' => FORMAT_HTML,
            'visible'           => $visible,
        ]);
    } catch (moodle_exception $e) {
        $reporte->error("$idnumber $nombre", $e->getMessage());
        continue;
    }

    $porindnumber[$idnumber] = $DB->get_record('course_categories', ['id' => $nueva->id],
        'id, idnumber, name, visible, parent');
    $reporte->creado("$idnumber $nombre",
        ($idpadre ? "bajo {$fila['padre']}" : 'primer nivel') . ($visible ? '' : ' · oculta'));
}

academia_purgar_caches($reporte);

exit($reporte->resumen());
