<?php
// Crea el rol «Gestor de Área Temática».
//
// Es la pieza que desbloquea todo lo demás. Hoy no existe un permiso intermedio:
// o se es administrador de todo el sitio, o no se puede publicar nada. Por eso
// ninguna otra gerencia ha podido usar la plataforma, y por eso el administrador
// central es el cuello de botella de toda la institución.
//
// La restricción que hace posible el diseño es la última fila del Anexo D: el
// gestor de un área NO puede tocar los cursos de otra. Eso NO se consigue con
// capacidades — se consigue asignando el rol a nivel de CATEGORÍA, para que su
// alcance sea el subárbol de su área y nada más.
//
// Este script crea el rol y define sus permisos. NO designa a nadie: eso es una
// decisión institucional (Parte 4.1 de la Propuesta), y se hace después con
// Administración del sitio → Usuarios → Permisos → Asignar roles de sistema, o
// desde la propia categoría.
//
//   docker compose exec -u www-data app php /opt/academia/cli/50_rol_gestor.php --dry-run
//   docker compose exec -u www-data app php /opt/academia/cli/50_rol_gestor.php

require(__DIR__ . '/bootstrap.php');

const ACADEMIA_ROL_SHORTNAME = 'gestorarea';
const ACADEMIA_ROL_NOMBRE    = 'Gestor de Área Temática';

$opciones = academia_cli_inicio(
    'Crea el rol Gestor de Área Temática, asignable solo a nivel de categoría.');

$reporte = new academia_reporte('Rol Gestor de Área Temática', $opciones['dry-run']);

$sistema = context_system::instance();

$descripcion = <<<'HTML'
<p>Responde por su área temática ante el Comité de la Academia. Decide qué cursos existen
en ella y en qué orden se producen, asigna autores, aplica la lista de verificación antes de
publicar y declara el ciclo de vida de cada curso.</p>
<p><strong>No puede</strong> tocar cursos de otras áreas, ni cambiar la configuración del
sitio, ni crear áreas nuevas, ni modificar la plantilla maestra o el marco de competencias.</p>
<p>Este rol se asigna <strong>a nivel de categoría</strong>, nunca de sitio: el alcance del
permiso ES la frontera del área.</p>
HTML;

// ─── 1. El rol ──────────────────────────────────────────────────────────────
$rol = $DB->get_record('role', ['shortname' => ACADEMIA_ROL_SHORTNAME]);

if ($rol) {
    $reporte->existia('rol ' . ACADEMIA_ROL_SHORTNAME, "id {$rol->id}");
    $idrol = (int)$rol->id;
} else if ($reporte->es_simulacion()) {
    $reporte->creado('rol ' . ACADEMIA_ROL_SHORTNAME, 'arquetipo manager');
    $idrol = -1;
} else {
    // Arquetipo `manager`: es «duplicar el rol Gestor y recortar permisos», como
    // dice la pantalla 13. Partir de cero obligaría a enumerar cientos de
    // capacidades y cualquier plugin nuevo dejaría huecos.
    $idrol = create_role(ACADEMIA_ROL_NOMBRE, ACADEMIA_ROL_SHORTNAME, $descripcion, 'manager');
    // create_role deja el rol SIN capacidades: solo guarda de qué arquetipo
    // desciende. Esto es lo que copia las del arquetipo.
    reset_role_capabilities($idrol);
    $reporte->creado('rol ' . ACADEMIA_ROL_SHORTNAME, "id $idrol · arquetipo manager");
}

// ─── 2. Dónde se puede asignar ──────────────────────────────────────────────
// SOLO categoría. Es la línea más importante del script: con CONTEXT_SYSTEM en
// esta lista, alguien podría asignarlo globalmente y el gestor pasaría a ver las
// siete áreas — que es exactamente lo que el diseño impide.
if (!$reporte->es_simulacion()) {
    set_role_contextlevels($idrol, [CONTEXT_COURSECAT]);
}
$reporte->corregido('contextos asignables', 'solo CONTEXT_COURSECAT');

// ─── 3. Las capacidades que se recortan ─────────────────────────────────────
// Traducción de la columna «No puede» de la pantalla 13 y del Anexo D.
//
// Se ponen en CAP_PROHIBIT y no en CAP_PREVENT a propósito: prohibit no se puede
// revertir con una anulación local en una categoría o curso. Para un rol cuyo
// sentido es delimitar un área, que el límite sea negociable caso a caso vacía
// el diseño.
$prohibidas = [
    // Configuración del sitio, versiones, plugins.
    'moodle/site:config'                 => 'cambiar la configuración del sitio',
    'moodle/site:maintenanceaccess'      => 'poner el sitio en mantención',
    // Crear áreas temáticas es del administrador (Anexo D, fila 3).
    'moodle/category:manage'             => 'crear o borrar categorías',
    // Las cohortes son del administrador: son transversales a las siete áreas.
    'moodle/cohort:manage'               => 'crear ni editar cohortes',
    'moodle/cohort:assign'               => 'cambiar quién pertenece a una cohorte',
    // El marco de competencias y el estándar no se tocan desde un área.
    'moodle/competency:competencymanage' => 'modificar el marco de competencias',
    'moodle/competency:templatemanage'   => 'modificar las plantillas de aprendizaje',
    // Roles a nivel de sitio.
    'moodle/role:manage'                 => 'crear ni editar roles',
    // Usuarios: el alta y la baja son del administrador o del directorio.
    'moodle/user:create'                 => 'crear cuentas de usuario',
    'moodle/user:delete'                 => 'borrar cuentas de usuario',
    'moodle/user:update'                 => 'editar cuentas de usuario',
    // Superficie que no corresponde a un organismo público ni a este rol.
    'moodle/site:uploadusers'            => 'cargar usuarios masivamente',
];

foreach ($prohibidas as $capacidad => $porque) {
    if (!get_capability_info($capacidad)) {
        // Una capacidad que no existe en esta versión no es un error: es una
        // línea que sobra. Se informa para poder limpiarla.
        $reporte->omitido($capacidad, 'no existe en esta versión de Moodle');
        continue;
    }
    if (!$reporte->es_simulacion()) {
        assign_capability($capacidad, CAP_PROHIBIT, $idrol, $sistema->id, true);
    }
    $reporte->corregido($capacidad, "prohibida — $porque");
}

// ─── 4. Las capacidades que se aseguran ─────────────────────────────────────
// El arquetipo manager ya trae casi todas, pero dependerlo de eso es frágil: si
// alguien edita el rol Gestor del sitio, este hereda el recorte sin saberlo.
// Estas son las que definen lo que el gestor SÍ puede, y se afirman explícitas.
$permitidas = [
    'moodle/course:create'            => 'crear cursos en su área',
    'moodle/course:update'            => 'editar la configuración de sus cursos',
    'moodle/course:visibility'        => 'publicar y ocultar sus cursos',
    'moodle/course:viewhiddencourses' => 'ver los cursos en desarrollo de su área',
    'moodle/course:manageactivities'  => 'editar contenido y evaluaciones',
    'moodle/course:changefullname'    => 'nombrar sus cursos',
    'moodle/course:changeshortname'   => 'asignar el código del curso',
    'moodle/course:changeidnumber'    => 'asignar el número ID del curso',
    'moodle/course:changecategory'    => 'mover cursos dentro de su área',
    'moodle/backup:backupcourse'      => 'duplicar la plantilla maestra (respaldo)',
    'moodle/restore:restorecourse'    => 'duplicar la plantilla maestra (restauración)',
    'moodle/restore:restoretargetimport' => 'importar contenido entre sus cursos',
    'moodle/role:assign'              => 'asignar autores y tutores en sus cursos',
    'enrol/manual:enrol'              => 'matricular a mano cuando corresponde',
    'enrol/cohort:config'             => 'matricular una cohorte completa',
    'moodle/course:enrolreview'       => 'revisar los métodos de matriculación',
    'moodle/course:viewparticipants'  => 'ver a los participantes de sus cursos',
    'moodle/grade:viewall'            => 'ver las calificaciones de su área',
    'moodle/competency:coursecompetencymanage' => 'vincular competencias a sus cursos',
    'report/completion:view'          => 'ver el avance de sus cursos',
    'report/outline:view'             => 'ver los informes de sus cursos',
];

foreach ($permitidas as $capacidad => $porque) {
    if (!get_capability_info($capacidad)) {
        $reporte->omitido($capacidad, 'no existe en esta versión de Moodle');
        continue;
    }
    if (!$reporte->es_simulacion()) {
        assign_capability($capacidad, CAP_ALLOW, $idrol, $sistema->id, true);
    }
    $reporte->corregido($capacidad, "permitida — $porque");
}

// ─── 5. Qué roles puede asignar ─────────────────────────────────────────────
// «Asigna autores y les da permisos sobre cursos concretos, SIN pasar por el
// administrador» (pantalla 13). Sin esto, moodle/role:assign no sirve de nada:
// el gestor tendría el permiso y ningún rol que ofrecer.
//
// Y no incluye `manager` ni `gestorarea`: un gestor que puede nombrar a otro
// gestor puede darse a sí mismo otra área, y la frontera deja de existir.
$asignables = ['editingteacher', 'teacher', 'student'];

if (!$reporte->es_simulacion()) {
    $DB->delete_records('role_allow_assign', ['roleid' => $idrol]);
}
foreach ($asignables as $shortname) {
    $destino = $DB->get_record('role', ['shortname' => $shortname], 'id, name');
    if (!$destino) {
        $reporte->omitido("puede asignar: $shortname", 'ese rol no existe en el sitio');
        continue;
    }
    if (!$reporte->es_simulacion()) {
        core_role_set_assign_allowed($idrol, (int)$destino->id);
    }
    $reporte->corregido("puede asignar: $shortname", $destino->name);
}

// Las capacidades de un rol se cachean en MUC. Sin esto los permisos están en la
// base pero el sitio sigue aplicando los anteriores, que es el síntoma más
// confuso posible: «le di el permiso y no lo tiene».
if (!$reporte->es_simulacion()) {
    accesslib_clear_role_cache($idrol);
}

academia_purgar_caches($reporte);

$codigo = $reporte->resumen();

cli_writeln('');
cli_writeln('El rol está creado y VACÍO de personas: designar a los gestores es una decisión');
cli_writeln('institucional, no técnica (Propuesta, Parte 4.1). Se asigna entrando a cada');
cli_writeln('categoría de área → Permisos → Asignar roles.');
cli_writeln('');
cli_writeln('CÓMO SE COMPRUEBA QUE FUNCIONA — y es la verificación que de verdad importa:');
cli_writeln('  0. Poner primero un curso cualquiera, oculto, dentro de la categoría 04. Sin');
cli_writeln('     eso la prueba no prueba nada: en un sitio vacío «no ve los cursos de 04»');
cli_writeln('     se cumple solo, porque no hay ninguno que ver.');
cli_writeln('  1. Asignar el rol a una cuenta de prueba SOLO en la categoría 01.');
cli_writeln('  2. Entrar con esa cuenta: debe poder crear un curso en 01...');
cli_writeln('  3. ...y NO debe ver el curso de 04.');
cli_writeln('Si el punto 3 falla, la delegación no está implementada, por muy creado que');
cli_writeln('esté el rol. Esa restricción es lo único que permite que siete gerencias');
cli_writeln('convivan en una sola plataforma.');

exit($codigo);
