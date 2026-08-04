<?php
// Devoluciones de llamada de SCSS del tema de la Academia CONAF.
//
// El orden en que Moodle junta el SCSS importa y no es obvio:
//
//   1. prescsscallback   -> variables, ANTES de Bootstrap. Acá van $primary,
//                           $font-size-base y compañía: cambiarlas después no
//                           sirve de nada porque Bootstrap ya las usó.
//   2. $THEME->scss      -> el preset de Boost, que trae todo Bootstrap.
//   3. extrascsscallback -> reglas propias, DESPUÉS. Acá van las cosas que
//                           tienen que ganarle a Bootstrap.
//
// @package   theme_academia
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * El SCSS base: el preset de Boost sin tocar.
 *
 * No se copia el preset a este tema a propósito. Un preset copiado se queda en
 * la versión de Bootstrap del día que se copió, y en la siguiente actualización
 * de Moodle el sitio sale con la mitad de los estilos rotos y sin ningún error.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_academia_get_main_scss_content($theme) {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
}

/**
 * Variables, antes de Bootstrap.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_academia_get_pre_scss($theme) {
    $scss = file_get_contents(__DIR__ . '/scss/pre.scss');

    // Lo que un administrador escriba en los ajustes del tema va al final, para
    // que pueda corregir sin tocar el repositorio en una urgencia.
    if (!empty($theme->settings->scsspre)) {
        $scss .= "\n" . $theme->settings->scsspre;
    }

    return $scss;
}

/**
 * Reglas propias, después de Bootstrap.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_academia_get_extra_scss($theme) {
    $scss = file_get_contents(__DIR__ . '/scss/post.scss');

    if (!empty($theme->settings->scss)) {
        $scss .= "\n" . $theme->settings->scss;
    }

    return $scss;
}
