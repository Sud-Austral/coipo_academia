<?php
// Completa los ocho campos personalizados en los cursos que ya existen.
//
// Es la tarea más tediosa y la que más rinde: al terminarla, el catálogo y los
// informes empiezan a funcionar sin construir nada más. Un curso con los campos
// vacíos queda invisible en el catálogo filtrable y ausente de todo informe
// institucional — es el olvido más frecuente del estándar.
//
// SE USA EN DOS PASOS, y tiene que ser así: la clasificación de los 36 cursos
// reales no se puede escribir a ciegas desde un documento. El Anexo A trae los
// nombres, no los nombres cortos con que están en la base.
//
//   1) Exportar lo que hay, con una propuesta ya rellenada:
//        ... 60_clasificar_cursos.php --exportar
//      Escribe academia/datos/cursos-clasificacion.csv con un curso por línea y
//      una sugerencia deducida del nombre y de la categoría actual. Lo que no se
//      puede deducir queda en blanco y marcado en la columna REVISAR.
//
//   2) Revisar ese CSV a mano —es una decisión institucional, no técnica— y
//      aplicarlo:
//        ... 60_clasificar_cursos.php --dry-run
//        ... 60_clasificar_cursos.php

require(__DIR__ . '/bootstrap.php');

use core_course\customfield\course_handler;

const ACADEMIA_CSV_CURSOS = 'cursos-clasificacion.csv';

// Los ocho campos, en el orden en que salen y entran del CSV.
const ACADEMIA_CAMPOS = ['area', 'nivel', 'perfil', 'modalidad', 'duracion',
                         'vigencia', 'financiamiento', 'estado'];

$opciones = academia_cli_inicio(
    'Completa los 8 campos personalizados de los cursos existentes.',
    ['exportar' => false, 'force' => false]);

$manejador = course_handler::create();

// Los campos, por shortname, para poder traducir etiquetas a índices.
$campos = [];
foreach ($manejador->get_categories_with_fields() as $cat) {
    foreach ($cat->get_fields() as $campo) {
        $campos[$campo->get('shortname')] = $campo;
    }
}
foreach (ACADEMIA_CAMPOS as $shortname) {
    if (!isset($campos[$shortname])) {
        cli_error("Falta el campo personalizado '$shortname'. Ejecutar antes 20_campos_curso.php.");
    }
}

if ($opciones['exportar']) {
    academia_exportar_clasificacion($manejador, $campos, (bool)$opciones['force']);
    exit(0);
}

// ─── Aplicar ────────────────────────────────────────────────────────────────
$reporte = new academia_reporte('Clasificación de cursos', $opciones['dry-run']);
$filas = academia_leer_csv(ACADEMIA_CSV_CURSOS);

foreach ($filas as $fila) {
    $shortname = $fila['shortname'];
    $curso = $DB->get_record('course', ['shortname' => $shortname], 'id, shortname, fullname');

    if (!$curso) {
        $reporte->error($shortname, 'no existe ningún curso con ese nombre corto');
        continue;
    }

    $etiqueta = $shortname . ' · ' . mb_substr($fila['fullname'], 0, 45);

    // Un curso que el CSV deja marcado para revisión no se toca. Aplicar una
    // clasificación a medias es peor que dejarla vacía: el curso aparece en el
    // catálogo con datos que nadie decidió.
    if (trim($fila['REVISAR']) !== '') {
        $reporte->omitido($etiqueta, $fila['REVISAR']);
        continue;
    }

    $datos = (object)['id' => $curso->id];
    $faltantes = [];
    $invalidos = [];

    foreach (ACADEMIA_CAMPOS as $shortnamecampo) {
        $valor = trim($fila[$shortnamecampo] ?? '');
        if ($valor === '') {
            $faltantes[] = $shortnamecampo;
            continue;
        }

        $campo = $campos[$shortnamecampo];
        if ($campo->get('type') === 'select') {
            $indice = academia_indice_de_opcion($campo, $valor);
            if ($indice === null) {
                $invalidos[] = "$shortnamecampo='$valor'";
                continue;
            }
            $datos->{'customfield_' . $shortnamecampo} = $indice;
        } else {
            if (!is_numeric($valor)) {
                $invalidos[] = "$shortnamecampo='$valor' (no es número)";
                continue;
            }
            $datos->{'customfield_' . $shortnamecampo} = (float)$valor;
        }
    }

    if ($invalidos) {
        $reporte->error($etiqueta, 'valores fuera de la lista: ' . implode(', ', $invalidos));
        continue;
    }

    // financiamiento es el único opcional. Los otros siete son obligatorios y
    // dejarlos vacíos deja el curso fuera del catálogo y de los informes.
    $faltantesobligatorios = array_diff($faltantes, ['financiamiento']);
    if ($faltantesobligatorios) {
        $reporte->omitido($etiqueta, 'faltan campos obligatorios: ' .
            implode(', ', $faltantesobligatorios));
        continue;
    }

    if (!$reporte->es_simulacion()) {
        $manejador->instance_form_save($datos);
    }
    $reporte->creado($etiqueta, $fila['area'] . ' · ' . $fila['nivel'] . ' · ' . $fila['estado']);
}

$codigo = $reporte->resumen();

// ─── Cuántos cursos quedan sin clasificar ───────────────────────────────────
// Es la cifra que importa: mientras no sea 0, el catálogo está incompleto y los
// informes mienten por omisión.
$sinclasificar = academia_cursos_sin_clasificar($manejador);
cli_writeln('');
cli_writeln('Cursos sin el campo `area` completo: ' . count($sinclasificar));
foreach (array_slice($sinclasificar, 0, 15) as $c) {
    cli_writeln('  · ' . $c->shortname . ' — ' . mb_substr($c->fullname, 0, 60));
}
if (count($sinclasificar) > 15) {
    cli_writeln('  … y ' . (count($sinclasificar) - 15) . ' más');
}

exit($codigo);


// ═══════════════════════════════════════════════════════════════════════════
// Exportación
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Escribe academia/datos/cursos-clasificacion.csv con los cursos del sitio y una
 * propuesta de clasificación.
 */
function academia_exportar_clasificacion(course_handler $manejador, array $campos, bool $forzar): void {
    global $DB;

    $ruta = dirname(__DIR__) . '/datos/' . ACADEMIA_CSV_CURSOS;
    if (file_exists($ruta) && !$forzar) {
        cli_error("$ruta ya existe.\nSe detuvo para no perder el trabajo de revisión que pueda " .
            'tener dentro. Repetir con --force si de verdad quieres regenerarlo.');
    }

    // El curso del sitio (id 1) no es un curso: es la portada.
    $cursos = $DB->get_records_select('course', 'id > 1', null, 'category, shortname',
        'id, shortname, idnumber, fullname, category');

    $categorias = $DB->get_records_menu('course_categories', null, '', 'id, name');

    $lineas = [];
    $lineas[] = '# Clasificación de los cursos existentes — GENERADO por';
    $lineas[] = '#   60_clasificar_cursos.php --exportar';
    $lineas[] = '#';
    $lineas[] = '# Las columnas de los 8 campos vienen con una PROPUESTA deducida del nombre del';
    $lineas[] = '# curso y de su categoría actual. Es una sugerencia mecánica, no una decisión:';
    $lineas[] = '# hay que revisarla curso por curso antes de aplicarla.';
    $lineas[] = '#';
    $lineas[] = '# La columna REVISAR es un freno: mientras tenga texto, ese curso NO se toca.';
    $lineas[] = '# Vaciarla es la forma de decir «esta clasificación está decidida».';
    $lineas[] = '#';
    $lineas[] = '# Lo que NO se puede deducir de ninguna manera y hay que averiguar:';
    $lineas[] = '#   duracion   horas cronológicas de dedicación';
    $lineas[] = '#   vigencia   meses de validez del certificado (0 = no vence)';
    $lineas[] = '#   perfil     a quién va dirigido de verdad';
    $lineas[] = '#';
    $lineas[] = '# Los valores tienen que coincidir EXACTAMENTE con las opciones de';
    $lineas[] = '# campos-curso.csv. Un valor fuera de la lista se rechaza al aplicar.';
    $lineas[] = '';
    $lineas[] = implode(',', array_merge(
        ['shortname', 'fullname', 'categoria_actual'], ACADEMIA_CAMPOS, ['REVISAR']));

    $conpropuesta = 0;
    foreach ($cursos as $curso) {
        $categoria = $categorias[$curso->category] ?? '';
        $propuesta = academia_proponer_clasificacion($curso, $categoria);

        // Si el curso YA tiene campos completos en el sitio, se exportan tal
        // cual: el CSV debe reflejar la realidad, no volver a proponer.
        $actuales = $manejador->get_instance_data($curso->id, true);
        $poridcampo = [];
        foreach ($actuales as $dato) {
            $poridcampo[$dato->get_field()->get('shortname')] = $dato;
        }
        foreach (ACADEMIA_CAMPOS as $shortname) {
            $yaesta = academia_etiqueta_de_valor($poridcampo[$shortname] ?? null);
            if ($yaesta !== '') {
                $propuesta['valores'][$shortname] = $yaesta;
            }
        }

        $celdas = array_merge(
            [$curso->shortname, $curso->fullname, $categoria],
            array_map(fn($c) => $propuesta['valores'][$c] ?? '', ACADEMIA_CAMPOS),
            [$propuesta['revisar']]
        );
        $lineas[] = implode(',', array_map('academia_csv_celda', $celdas));

        if ($propuesta['revisar'] === '') {
            $conpropuesta++;
        }
    }

    file_put_contents($ruta, implode("\n", $lineas) . "\n");

    cli_writeln('Escrito: ' . $ruta);
    cli_writeln('  cursos exportados:        ' . count($cursos));
    cli_writeln('  con propuesta completa:   ' . $conpropuesta);
    cli_writeln('  marcados para revisar:    ' . (count($cursos) - $conpropuesta));
    cli_writeln('');
    cli_writeln('Siguiente: abrir ese CSV, revisar curso por curso, vaciar la columna REVISAR');
    cli_writeln('de los que queden decididos, y aplicar con --dry-run primero.');
}

/**
 * Propone una clasificación a partir del nombre del curso y de su categoría.
 *
 * Las reglas son deliberadamente conservadoras: proponen solo lo que se deduce
 * sin margen de duda, y todo lo demás lo dejan en blanco con un motivo escrito
 * en REVISAR. Una propuesta agresiva ahorra diez minutos y cuesta un catálogo
 * con datos que nadie decidió.
 *
 * @return array{valores: array<string,string>, revisar: string}
 */
function academia_proponer_clasificacion(stdClass $curso, string $categoria): array {
    $valores = [];
    $motivos = [];

    $nombre = mb_strtolower($curso->fullname . ' ' . $curso->shortname);
    $cat = mb_strtolower($categoria);

    // ─── Área ───────────────────────────────────────────────────────────────
    // Hoy los 35 cursos publicados son de incendios: es el hecho central del
    // diagnóstico. Las dos excepciones son material de gestión del campus.
    if (str_contains($cat, 'manuales de usuario') || str_contains($nombre, 'manuales de usuario')) {
        $valores['area'] = '99 Gestión del Campus';
    } else {
        $valores['area'] = '01 Incendios Forestales';
    }

    // ─── Nivel ──────────────────────────────────────────────────────────────
    // El orden de estas reglas se corrigió después de probarlas contra los 35
    // cursos del Anexo A. Buscar la serie numerada primero producía cuatro
    // errores, y los cuatro del mismo tipo: un número dentro del nombre que no
    // era el nivel.
    //
    //   «Inducción formulario SCI-225»  ->  el 225 es un formulario, no la serie 200
    //   «Prevención y combate C-111 N2» ->  el 111 es el código C-111; el nivel es el N2
    //   «Curso C-111 Nivel 2, 1.ª edición» y su gemelo de MBZ, igual
    //
    // De ahí el orden: primero lo que el nombre DICE explícitamente, después
    // dónde lo puso una persona, y solo al final el número suelto.
    if (preg_match('/\b(nivel\s*3|n3)\b/u', $nombre)) {
        $valores['nivel'] = 'Avanzado';
    } else if (preg_match('/\b(nivel\s*2|n2)\b/u', $nombre)) {
        $valores['nivel'] = 'Intermedio';
    } else if (preg_match('/\b(nivel\s*1|n1)\b/u', $nombre)) {
        $valores['nivel'] = 'Básico';

    // La categoría es una colocación curada: alguien puso ese curso ahí.
    } else if (str_contains($cat, 'estándar') || str_contains($cat, 'estandar')) {
        $valores['nivel'] = 'Básico';
    } else if (str_contains($cat, 'intermedia')) {
        $valores['nivel'] = 'Intermedio';
    } else if (str_contains($cat, 'avanzada')) {
        $valores['nivel'] = 'Avanzado';

    // La palabra en el propio nombre.
    } else if (preg_match('/\bavanzad[oa]\b/u', $nombre)) {
        $valores['nivel'] = 'Avanzado';
    } else if (preg_match('/\bintermedi[oa]\b/u', $nombre)) {
        $valores['nivel'] = 'Intermedio';
    } else if (preg_match('/\bbásic[oa]|basic[oa]\b/u', $nombre)) {
        $valores['nivel'] = 'Básico';

    // Y por último la serie numerada, solo si el número ABRE el nombre. Es el
    // formato de las series 114-119 y 211-219, que son los 22 cursos ya limpios.
    } else if (preg_match('/^\s*1\d{2}\b/u', $curso->fullname)) {
        $valores['nivel'] = 'Básico';
    } else if (preg_match('/^\s*2\d{2}\b/u', $curso->fullname)) {
        $valores['nivel'] = 'Intermedio';
    } else if (preg_match('/^\s*3\d{2}\b/u', $curso->fullname)) {
        $valores['nivel'] = 'Avanzado';
    } else {
        $motivos[] = 'nivel no deducible';
    }

    // ─── Modalidad ──────────────────────────────────────────────────────────
    if (str_contains($nombre, 'presencial')) {
        $valores['modalidad'] = 'Presencial con soporte';
    } else {
        $motivos[] = 'modalidad no deducible';
    }

    // ─── Financiamiento ─────────────────────────────────────────────────────
    // La categoría actual lo dice, y sacarlo del árbol es justamente el punto:
    // hoy el mismo curso está duplicado porque cambió de financiador.
    if (str_contains($cat, 'mbz') || str_contains($nombre, 'mbz')) {
        $valores['financiamiento'] = 'MBZ';
    } else if (str_contains($cat, 'kizuna') || str_contains($nombre, 'kizuna')) {
        $valores['financiamiento'] = 'KIZUNA-JICA';
    } else if (str_contains($cat, 'internacional')) {
        $valores['financiamiento'] = 'Otra cooperación internacional';
    } else if (str_contains($cat, 'cursos conaf')) {
        $valores['financiamiento'] = 'CONAF';
    }

    // ─── Estado del ciclo de vida ───────────────────────────────────────────
    // Cuatro cursos de prueba están publicados en producción, visibles para los
    // 2.875 usuarios. No se archivan solos: se marcan para que alguien decida.
    if (preg_match('/\b(prueba|pruebas|test|demo)\b/u', $nombre)) {
        $motivos[] = 'PARECE UN CURSO DE PRUEBA en producción — decidir si se archiva o se borra';
    } else {
        $valores['estado'] = 'Vigente';
    }

    // ─── Lo que ningún dato del sistema puede responder ─────────────────────
    $motivos[] = 'faltan perfil, duracion y vigencia: hay que preguntarlos';

    return ['valores' => $valores, 'revisar' => implode(' · ', $motivos)];
}

/**
 * Escapa una celda para el CSV. Se cita siempre que tenga coma, comilla o salto.
 */
function academia_csv_celda(string $valor): string {
    if (preg_match('/[",\n\r]/', $valor)) {
        return '"' . str_replace('"', '""', $valor) . '"';
    }
    return $valor;
}

/**
 * Cursos que todavía no tienen `area`. Es el indicador de avance real.
 *
 * @return stdClass[]
 */
function academia_cursos_sin_clasificar(course_handler $manejador): array {
    global $DB;

    $sinclasificar = [];
    $cursos = $DB->get_records_select('course', 'id > 1', null, 'shortname',
        'id, shortname, fullname');

    foreach ($cursos as $curso) {
        $completo = false;
        foreach ($manejador->get_instance_data($curso->id, true) as $dato) {
            if ($dato->get_field()->get('shortname') === 'area'
                    && academia_etiqueta_de_valor($dato) !== '') {
                $completo = true;
                break;
            }
        }
        if (!$completo) {
            $sinclasificar[] = $curso;
        }
    }

    return $sinclasificar;
}
