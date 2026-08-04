<?php
// Librería común de los scripts de provisión de la Academia CONAF v2.
//
// Todos los scripts de esta carpeta comparten tres cosas, y las tres están acá:
//
//   1. Idempotencia. Se busca por idnumber o shortname ANTES de crear. Correr un
//      script dos veces no duplica nada, y la segunda corrida no imprime ni un
//      solo "creado". Eso es lo que permite ejecutarlos sin miedo después de
//      corregir un CSV.
//
//   2. Contabilidad visible. Cada objeto imprime qué le pasó, y al final va un
//      resumen. "El script terminó sin error" no es verificación; ver la cuenta
//      de lo que creó, sí.
//
//   3. Un freno contra producción. Estos scripts modifican el modelo de datos.
//      Si el config.php apunta a academia_prod, se niegan a correr salvo que se
//      pase --permitir-produccion de forma explícita.
//
// Se ejecutan desde el contenedor, SIEMPRE como www-data:
//
//   docker compose exec -u www-data app php /opt/academia/cli/10_categorias.php
//
// Correrlos como root deja los archivos que Moodle cree en moodledata con dueño
// root, y el sitio deja de poder escribir. Es el error más común del proyecto.

defined('MOODLE_INTERNAL') || die();

/**
 * Nombre de la base que este proyecto considera producción intocable.
 */
const ACADEMIA_BD_PRODUCCION = 'academia_prod';

/**
 * Contabilidad de lo que hace un script, para poder verificar mirando la salida.
 */
class academia_reporte {

    /** @var string */
    protected $titulo;
    /** @var bool Si true, no se escribe nada en la base. */
    protected $simulacion;
    /** @var array<string,int> */
    protected $cuenta = [
        'creado'    => 0,
        'existia'   => 0,
        'corregido' => 0,
        'omitido'   => 0,
        'error'     => 0,
    ];
    /** @var string[] */
    protected $errores = [];

    public function __construct(string $titulo, bool $simulacion) {
        $this->titulo = $titulo;
        $this->simulacion = $simulacion;

        cli_separator();
        cli_writeln($titulo . ($simulacion ? '   [SIMULACIÓN — no se escribe nada]' : ''));
        cli_separator();
    }

    public function es_simulacion(): bool {
        return $this->simulacion;
    }

    /** Objeto que no existía y se creó. */
    public function creado(string $que, string $detalle = ''): void {
        $this->anotar('creado', '  + creado    ', $que, $detalle);
    }

    /** Objeto que ya estaba y quedó igual. En la segunda corrida deben ser todos. */
    public function existia(string $que, string $detalle = ''): void {
        $this->anotar('existia', '  = ya estaba ', $que, $detalle);
    }

    /** Objeto que ya estaba pero con un valor distinto del esperado, y se ajustó. */
    public function corregido(string $que, string $detalle = ''): void {
        $this->anotar('corregido', '  ~ corregido ', $que, $detalle);
    }

    /** Objeto que se salta a propósito: falta una decisión, o el CSV lo marca. */
    public function omitido(string $que, string $motivo): void {
        $this->anotar('omitido', '  · omitido   ', $que, $motivo);
    }

    /** Algo falló. No aborta el script: se acumula y se informa al final. */
    public function error(string $que, string $motivo): void {
        $this->anotar('error', '  ! ERROR     ', $que, $motivo);
        $this->errores[] = "$que — $motivo";
    }

    protected function anotar(string $clave, string $marca, string $que, string $detalle): void {
        $this->cuenta[$clave]++;
        cli_writeln($marca . $que . ($detalle !== '' ? '   ' . $detalle : ''));
    }

    /**
     * Resumen final. Devuelve el código de salida que debe usar el script:
     * 0 si todo bien, 1 si hubo algún error.
     */
    public function resumen(): int {
        cli_separator();
        $partes = [];
        foreach ($this->cuenta as $clave => $n) {
            if ($n > 0) {
                $partes[] = "$n $clave";
            }
        }
        cli_writeln($this->titulo . ' — ' . ($partes ? implode(' · ', $partes) : 'nada que hacer'));

        if ($this->errores) {
            cli_writeln('');
            cli_problem('Con errores:');
            foreach ($this->errores as $e) {
                cli_problem('  · ' . $e);
            }
            cli_separator();
            return 1;
        }

        if ($this->simulacion) {
            cli_writeln('SIMULACIÓN: no se escribió nada. Repetir sin --dry-run para aplicar.');
        }
        cli_separator();
        return 0;
    }
}

/**
 * Arranque común: parsea los parámetros, aplica el freno contra producción y
 * devuelve las opciones.
 *
 * @param string $descripcion Qué hace el script, para el --help.
 * @param array $extras Opciones adicionales propias del script, con su valor por defecto.
 * @return array Opciones ya resueltas.
 */
function academia_cli_inicio(string $descripcion, array $extras = []): array {
    global $CFG, $DB;

    $largas = array_merge([
        'help'                => false,
        'dry-run'             => false,
        'permitir-produccion' => false,
    ], $extras);

    [$opciones, $sinopcion] = cli_get_params($largas, ['h' => 'help', 'n' => 'dry-run']);

    if ($sinopcion) {
        cli_error('Parámetro no reconocido: ' . implode(', ', $sinopcion) . '. Usar --help.');
    }

    if ($opciones['help']) {
        $lineas = [$descripcion, '', 'Opciones:'];
        foreach ($largas as $nombre => $defecto) {
            $lineas[] = sprintf('  --%-22s %s', $nombre,
                is_bool($defecto) ? '' : "(por defecto: $defecto)");
        }
        $lineas[] = '';
        $lineas[] = 'Es idempotente: correrlo dos veces no duplica nada.';
        cli_writeln(implode("\n", $lineas));
        exit(0);
    }

    // ─── El freno ───────────────────────────────────────────────────────────
    // La instancia v2 existe justamente para no tocar producción. Si alguien
    // ejecuta esto con el .env equivocado, el error debe salir ANTES de escribir,
    // no después.
    $bd = $CFG->dbname ?? '';
    if ($bd === ACADEMIA_BD_PRODUCCION && !$opciones['permitir-produccion']) {
        cli_error(
            "Este script apunta a la base de PRODUCCIÓN ($bd) y se detuvo sin escribir nada.\n\n" .
            "  wwwroot: {$CFG->wwwroot}\n" .
            "  dbname:  $bd\n\n" .
            "La instancia v2 debe apuntar a academia_v2 (ver .env.v2.example). Si de verdad\n" .
            "quieres aplicar esto sobre producción, repite con --permitir-produccion.", 2);
    }

    cli_writeln("base: $bd   ·   sitio: {$CFG->wwwroot}");

    return $opciones;
}

/**
 * Ruta a un archivo de academia/datos/.
 */
function academia_datos(string $archivo): string {
    $ruta = dirname(__DIR__) . '/datos/' . $archivo;
    if (!is_readable($ruta)) {
        cli_error("No se puede leer el archivo de datos: $ruta");
    }
    return $ruta;
}

/**
 * Lee un CSV de academia/datos/ con cabecera, saltando líneas vacías y las que
 * empiezan con '#'.
 *
 * Los comentarios importan: los CSV de este proyecto llevan escritas las
 * decisiones pendientes al lado de la fila que afectan, y ese es el lugar donde
 * se leen de verdad.
 *
 * @return array<int,array<string,string>>
 */
function academia_leer_csv(string $archivo): array {
    $ruta = academia_datos($archivo);
    $manejador = fopen($ruta, 'r');
    if ($manejador === false) {
        cli_error("No se pudo abrir $ruta");
    }

    $cabecera = null;
    $filas = [];
    $numero = 0;

    while (($campos = fgetcsv($manejador, 0, ',', '"', '')) !== false) {
        $numero++;

        // fgetcsv devuelve [null] en las líneas totalmente vacías.
        if ($campos === [null] || $campos === []) {
            continue;
        }
        $primero = trim((string)($campos[0] ?? ''));
        if ($primero === '' || str_starts_with($primero, '#')) {
            continue;
        }

        if ($cabecera === null) {
            $cabecera = array_map(fn($c) => trim((string)$c), $campos);
            continue;
        }

        if (count($campos) !== count($cabecera)) {
            fclose($manejador);
            cli_error("$archivo línea $numero: tiene " . count($campos) .
                ' columnas y la cabecera tiene ' . count($cabecera) . '.');
        }

        $filas[] = array_combine($cabecera, array_map(fn($c) => trim((string)$c), $campos));
    }

    fclose($manejador);

    if ($cabecera === null) {
        cli_error("$archivo no tiene cabecera (¿está todo comentado?).");
    }

    return $filas;
}

/**
 * Convierte la ETIQUETA de una opción de un campo select en el ÍNDICE que Moodle
 * guarda de verdad.
 *
 * Esto no es un detalle: customfield_select almacena un entero en `intvalue`, no
 * el texto. Si se le pasa la etiqueta, PHP la convierte a 0 y el campo queda
 * vacío — sin error, sin aviso, y el curso desaparece del catálogo.
 *
 * El índice 0 es siempre la opción vacía; las reales empiezan en 1.
 *
 * @param \core_customfield\field_controller $campo
 * @param string $etiqueta Texto exacto de la opción, como está en el CSV.
 * @return int|null Índice, o null si la etiqueta no está entre las opciones.
 */
function academia_indice_de_opcion($campo, string $etiqueta): ?int {
    if ($etiqueta === '') {
        return 0;
    }
    foreach ($campo->get_options() as $indice => $opcion) {
        if ((string)$opcion === $etiqueta) {
            return (int)$indice;
        }
    }
    return null;
}

/**
 * El camino inverso: del valor guardado a la etiqueta legible. Se usa al exportar
 * la clasificación de los cursos existentes.
 *
 * @param \core_customfield\data_controller|null $dato
 * @return string Cadena vacía si el campo está sin completar.
 */
function academia_etiqueta_de_valor($dato): string {
    if ($dato === null || !$dato->get('id')) {
        return '';
    }
    $valor = $dato->export_value();
    return $valor === null ? '' : (string)$valor;
}

/**
 * Purga las cachés. Varios de estos scripts tocan definiciones que Moodle cachea
 * (campos personalizados, roles, competencias): sin esto los cambios están en la
 * base pero no se ven en pantalla, que es el síntoma más confuso de todos.
 */
function academia_purgar_caches(academia_reporte $reporte): void {
    if ($reporte->es_simulacion()) {
        return;
    }
    purge_all_caches();
    cli_writeln('  cachés purgadas');
}
