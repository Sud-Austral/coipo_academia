<?php
// Configuración de los cuestionarios de la Academia.
//
// Hay DOS instrumentos con funciones distintas, y tratarlos igual es lo que más
// se repite en los cursos institucionales:
//
//   La evaluación de lección ENSEÑA.
//   La evaluación final DECIDE si se emite un certificado con vigencia.
//
// No pueden configurarse igual, y la diferencia no es de matiz. Del documento de
// diseño de IF-151, Parte 2.5, sobre la configuración inicial que se corrigió:
//
//   · Aritmético. Con 3 ítems las notas posibles son 0, 33, 67 y 100. Un umbral
//     de «80 %» era en realidad 100 %: una sola respuesta mala reprobaba.
//   · De medición. Tres ítems no miden con fiabilidad, y acá se habilita un
//     certificado de competencia con 60 meses de vigencia.
//   · De diseño. Intentos ilimitados + calificación por intento más alto +
//     preguntas fijas + retroalimentación por opción significa que la evaluación
//     ENTREGA las respuestas correctas y después conserva el mejor intento. Para
//     lo formativo es exactamente lo que se busca. Para la certificación, no.
//
// @package academia

defined('MOODLE_INTERNAL') || die();

/**
 * Cuestionario FORMATIVO — el de cada lección. Su función es enseñar.
 *
 * Intentos ilimitados, calificación por el intento más alto y retroalimentación
 * inmediata por opción. Así el cuestionario se convierte en material de estudio,
 * que es justo lo que se busca: «un banco escrito así sirve dos veces, como
 * evaluación y como material de estudio».
 *
 * @param array $spec idnumber, seccion, name, intro, grade, aprobar
 */
function academia_quiz_formativo(array $spec): array {
    return array_merge(academia_quiz_base(), $spec, [
        'attempts'       => 0,     // sin límite
        'grademethod'    => 1,     // QUIZ_GRADEHIGHEST
        'timelimit'      => 0,
        'delay1'         => 0,
        'delay2'         => 0,
        'shuffleanswers' => 1,     // barajar también las respuestas dentro de cada pregunta

        // Inmediatamente después del intento: retroalimentación específica,
        // general y respuesta correcta. Es lo que lo vuelve material de estudio.
        'reviewattempt'          => academia_mascara(true,  true, true, true),
        'reviewcorrectness'      => academia_mascara(false, true, true, true),
        'reviewmarks'            => academia_mascara(false, true, true, true),
        'reviewmaxmarks'         => academia_mascara(false, true, true, true),
        'reviewspecificfeedback' => academia_mascara(false, true, true, true),
        'reviewgeneralfeedback'  => academia_mascara(false, true, true, true),
        'reviewrightanswer'      => academia_mascara(false, true, true, true),
        'reviewoverallfeedback'  => academia_mascara(false, true, true, true),

        // «Recibir una calificación» + «Aprobar».
        'completion'          => COMPLETION_TRACKING_AUTOMATIC,
        'completionusegrade'  => 1,
        'completionpassgrade' => 1,
        'gradepass'           => $spec['aprobar'],
    ]);
}

/**
 * Cuestionario de CERTIFICACIÓN — el final. Su función es decidir.
 *
 * Tres intentos con 24 horas de espera, límite de tiempo, y sobre todo:
 * durante el intento e inmediatamente después se muestra SOLO el puntaje. La
 * revisión completa se habilita al cerrar el cuestionario.
 *
 * Si eso último queda mal configurado, el primer intento le enseña al
 * participante las respuestas del segundo y el examen deja de medir. Es el error
 * que el documento de diseño marca como el más caro de los tres.
 *
 * @param array $spec idnumber, seccion, name, intro, grade, aprobar
 */
function academia_quiz_certificacion(array $spec): array {
    return array_merge(academia_quiz_base(), $spec, [
        'attempts'       => 3,
        // El más alto, igual que en el formativo. Acá no es un problema porque
        // la extracción aleatoria del banco lo neutraliza: cada intento trae
        // preguntas distintas.
        'grademethod'    => 1,
        'timelimit'      => 40 * 60,          // 40 min: son situacionales, hay que leer y pensar
        'delay1'         => 24 * 60 * 60,     // espera entre el 1.º y el 2.º intento
        'delay2'         => 24 * 60 * 60,     // y entre los siguientes
        'shuffleanswers' => 1,

        // Solo los puntos, hasta que el cuestionario cierre.
        'reviewattempt'          => academia_mascara(true,  true,  true,  true),
        'reviewmarks'            => academia_mascara(true,  true,  true,  true),
        'reviewmaxmarks'         => academia_mascara(true,  true,  true,  true),
        'reviewcorrectness'      => academia_mascara(false, false, false, true),
        'reviewspecificfeedback' => academia_mascara(false, false, false, true),
        'reviewgeneralfeedback'  => academia_mascara(false, false, false, true),
        'reviewrightanswer'      => academia_mascara(false, false, false, true),
        'reviewoverallfeedback'  => academia_mascara(false, false, false, true),

        'completion'          => COMPLETION_TRACKING_AUTOMATIC,
        'completionusegrade'  => 1,
        'completionpassgrade' => 1,
        'gradepass'           => $spec['aprobar'],
    ]);
}

/**
 * Campos comunes a todo cuestionario.
 *
 * Se dan TODOS explícitos aunque la tabla tenga valores por defecto: un
 * cuestionario que habilita un certificado con vigencia no puede depender de lo
 * que un administrador haya dejado configurado en Ajustes del sitio → Módulos.
 */
function academia_quiz_base(): array {
    return [
        'timeopen'              => 0,
        'timeclose'             => 0,
        'overduehandling'       => 'autosubmit',
        'graceperiod'           => 0,
        'preferredbehaviour'    => 'deferredfeedback',
        'canredoquestions'      => 0,
        'attemptonlast'         => 0,
        'decimalpoints'         => 0,
        'questiondecimalpoints' => -1,
        'questionsperpage'      => 1,
        'navmethod'             => 'free',
        'sumgrades'             => 0,
        'password'              => '',
        'subnet'                => '',
        'browsersecurity'       => '-',
        'showuserpicture'       => 0,
        'showblocks'            => 0,
        'completionattemptsexhausted' => 0,
        'completionminattempts' => 0,
        // La app móvil, que es donde efectivamente está el personal operativo.
        'allowofflineattempts'  => 1,
        // Campo nuevo de Moodle 5.2. Optimiza el rendimiento creando los intentos
        // por adelantado, y solo hace algo si además se configura `precreateperiod`
        // a nivel de sitio y el cuestionario tiene hora de apertura. Ninguno de los
        // dos es el caso acá, así que va en 0 explícito en vez de quedar NULL.
        'precreateattempts'     => 0,
    ];
}

/**
 * Arma la máscara de una opción de revisión.
 *
 * Moodle guarda las cuatro ventanas de revisión en un solo entero, con un bit
 * por ventana. Escribir ese número a mano es la forma más rápida de configurar
 * un examen que enseña las respuestas antes del segundo intento sin que nadie
 * lo note — el examen sigue funcionando, solo deja de medir.
 */
function academia_mascara(bool $durante, bool $despues, bool $mientrasabierto, bool $alcerrar): int {
    $m = 0;
    if ($durante) {
        $m |= \mod_quiz\question\display_options::DURING;
    }
    if ($despues) {
        $m |= \mod_quiz\question\display_options::IMMEDIATELY_AFTER;
    }
    if ($mientrasabierto) {
        $m |= \mod_quiz\question\display_options::LATER_WHILE_OPEN;
    }
    if ($alcerrar) {
        $m |= \mod_quiz\question\display_options::AFTER_CLOSE;
    }
    return $m;
}
