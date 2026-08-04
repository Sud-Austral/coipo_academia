<?php
// Banco de pruebas de la propuesta automática de clasificación de cursos.
//
// POR QUÉ EXISTE. De todos los scripts de academia/cli/, este es el único que
// DEDUCE algo en vez de copiar un CSV. Y una deducción equivocada no falla: deja
// un curso mal clasificado que después aparece en el catálogo y en los informes
// como si alguien lo hubiera decidido.
//
// La primera versión de las reglas fallaba en 4 de los 35 cursos, y los cuatro
// por lo mismo: un número dentro del nombre que no era el nivel.
//   «Inducción formulario SCI-225»       el 225 es un formulario
//   «Prevención y combate C-111 N2»      el 111 es el código; el nivel es el N2
//   «Curso C-111 Nivel 2, 1.ª edición»   y su gemelo de MBZ
//
// CÓMO SE EJECUTA. No necesita Moodle ni base de datos: extrae la función del
// script y la corre contra el inventario del Anexo A.
//
//   php -d extension=mbstring academia/pruebas/propuesta-clasificacion.php
//
// Si se cambian las reglas de 60_clasificar_cursos.php, esto tiene que seguir
// dando 0 fallos.

$script = dirname(__DIR__) . '/cli/60_clasificar_cursos.php';
$fuente = file_get_contents($script);
$ini = strpos($fuente, 'function academia_proponer_clasificacion');
$fin = strpos($fuente, 'function academia_csv_celda');
if ($ini === false || $fin === false) {
    fwrite(STDERR, "No se encontró academia_proponer_clasificacion() en $script\n");
    exit(2);
}
eval(substr($fuente, $ini, $fin - $ini));

// ─── Inventario del Anexo A: los 35 cursos al 1 de agosto de 2026 ───────────
$inventario = [
    ['Malla Estándar', 'C-111 presencial'],
    ['Malla Estándar', 'Certificados C-111 para apoyo internacional'],
    ['Malla Estándar', 'Inducción formulario SCI-225'],
    ['Malla Estándar', 'Curso Internacional'],
    ['Malla Estándar', '114 Operación con motosierra'],
    ['Malla Estándar', '115 Primeros auxilios'],
    ['Malla Estándar', '116 Mantenimiento de herramientas'],
    ['Malla Estándar', '117 Operación de motobomba'],
    ['Malla Estándar', '118 Brigada de heliataque'],
    ['Malla Estándar', '119 Especialista de ignición'],
    ['Malla Estándar', 'Prevención y combate C-111 N1'],
    ['Malla Estándar', 'Taller de maquinaria pesada'],
    ['Malla Intermedia', '211 Analista'],
    ['Malla Intermedia', '212 Maquinaria pesada'],
    ['Malla Intermedia', '213 SCI intermedio'],
    ['Malla Intermedia', '214 Coordinación aérea'],
    ['Malla Intermedia', '215 Oficial de seguridad'],
    ['Malla Intermedia', '216 Jefe de operaciones'],
    ['Malla Intermedia', '217 Jefe de planificación'],
    ['Malla Intermedia', '218 Comandante del incidente'],
    ['Malla Intermedia', '219 Jefe de quema'],
    ['Malla Intermedia', 'Prevención y combate C-111 N2'],
    ['Malla Avanzada', 'Curso Avanzado'],
    ['Sistema Comando de Incidentes', 'Curso básico presencial'],
    ['Sistema Comando de Incidentes', 'CIL-SCI'],
    ['Sistema Comando de Incidentes', 'Curso básico SCI PI 2013-2022'],
    ['Sistema Comando de Incidentes', 'SCI prueba'],
    ['Material instructores', 'Certificación de cursos presenciales'],
    ['Material instructores', 'Material instructor C-111'],
    ['Cursos CONAF', 'Curso C-111 Nivel 2, 1.ª edición'],
    ['Cursos CONAF', 'Curso de iniciación 5.ª edición'],
    ['Cursos CONAF', 'Curso de pruebas SCORM'],
    ['Cursos MBZ', 'Curso C-111 Nivel 2, 2.ª edición'],
    ['Cursos MBZ', 'Prueba MBZ'],
    ['Manuales de usuario', 'Curso de práctica — manuales de usuario'],
];

// ─── Nivel esperado, donde un humano puede afirmarlo sin dudar ──────────────
// Los que no están acá son precisamente los que la regla debe dejar en blanco.
$nivelesperado = [
    'C-111 presencial'                            => 'Básico',
    'Certificados C-111 para apoyo internacional' => 'Básico',
    'Inducción formulario SCI-225'                => 'Básico',
    'Curso Internacional'                         => 'Básico',
    '114 Operación con motosierra'                => 'Básico',
    '115 Primeros auxilios'                       => 'Básico',
    '116 Mantenimiento de herramientas'           => 'Básico',
    '117 Operación de motobomba'                  => 'Básico',
    '118 Brigada de heliataque'                   => 'Básico',
    '119 Especialista de ignición'                => 'Básico',
    'Prevención y combate C-111 N1'               => 'Básico',
    'Taller de maquinaria pesada'                 => 'Básico',
    '211 Analista'                                => 'Intermedio',
    '212 Maquinaria pesada'                       => 'Intermedio',
    '213 SCI intermedio'                          => 'Intermedio',
    '214 Coordinación aérea'                      => 'Intermedio',
    '215 Oficial de seguridad'                    => 'Intermedio',
    '216 Jefe de operaciones'                     => 'Intermedio',
    '217 Jefe de planificación'                   => 'Intermedio',
    '218 Comandante del incidente'                => 'Intermedio',
    '219 Jefe de quema'                           => 'Intermedio',
    'Prevención y combate C-111 N2'               => 'Intermedio',
    'Curso Avanzado'                              => 'Avanzado',
    'Curso C-111 Nivel 2, 1.ª edición'            => 'Intermedio',
    'Curso C-111 Nivel 2, 2.ª edición'            => 'Intermedio',
];

// ─── Los cuatro cursos de prueba que el Anexo A denuncia como publicados ────
// «Cuatro cursos de prueba están publicados en producción, visibles para los
// 2.875 usuarios». La regla tiene que marcarlos, no clasificarlos.
$debenmarcarse = ['SCI prueba', 'Curso de pruebas SCORM', 'Prueba MBZ'];

$fallos = 0;

printf("%-45s %-31s %-11s %-9s %s\n", 'CURSO', 'CATEGORÍA ACTUAL', 'nivel', 'financ.', 'REVISAR');
echo str_repeat('-', 160), "\n";

foreach ($inventario as [$categoria, $nombre]) {
    $curso = (object)['fullname' => $nombre, 'shortname' => $nombre];
    $p = academia_proponer_clasificacion($curso, $categoria);

    $nivel = $p['valores']['nivel'] ?? '—';
    $marcas = [];

    if (isset($nivelesperado[$nombre]) && $nivel !== $nivelesperado[$nombre]) {
        $marcas[] = 'ESPERABA nivel=' . $nivelesperado[$nombre];
        $fallos++;
    }
    if (in_array($nombre, $debenmarcarse, true) && !str_contains($p['revisar'], 'PRUEBA')) {
        $marcas[] = 'ESPERABA que lo marcara como curso de prueba';
        $fallos++;
    }

    printf("%-45s %-31s %-11s %-9s %s%s\n",
        mb_substr($nombre, 0, 44),
        mb_substr($categoria, 0, 30),
        $nivel,
        $p['valores']['financiamiento'] ?? '—',
        mb_substr($p['revisar'], 0, 62),
        $marcas ? '   <<< ' . implode(' · ', $marcas) : '');
}

echo str_repeat('-', 160), "\n";
$comprobables = count($nivelesperado) + count($debenmarcarse);
if ($fallos === 0) {
    echo "OK — $comprobables comprobaciones, 0 fallos.\n";
    exit(0);
}
echo "FALLA — $fallos de $comprobables comprobaciones.\n";
exit(1);
