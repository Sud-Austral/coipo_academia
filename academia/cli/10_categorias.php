<?php
// Crea el árbol de áreas temáticas de la Academia, vacío y oculto.
//
// Este árbol es TODO el árbol. La Academia se instala vacía y los 37 cursos del
// campus viejo se descartan, así que acá no hay nada que coexistir ni que
// trasladar después: lo que este script crea es la estructura definitiva, y todo
// curso que llegue a existir va a nacer dentro de ella.
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

        // La visibilidad NO se corrige. Un área se publica cuando tiene algo
        // adentro, y eso lo decide una persona mirando el área, no un CSV escrito
        // meses antes. Volver a ocultarla desde un script sería un retroceso
        // silencioso.
        if ((int)$actual->visible !== $visible) {
            cli_writeln("    (visible={$actual->visible} en el sitio, $visible en el CSV — " .
                'se respeta el sitio: publicar un área es una decisión de quien la gestiona)');
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
