<?php
// Ajustes del tema de la Academia CONAF.
//
// Deliberadamente mínimos: dos cajas de SCSS y nada más.
//
// La tentación en un tema institucional es exponer un ajuste por cada color y
// cada tamaño. Eso produce sitios donde nadie sabe de dónde salió un valor, y
// donde el estándar de diseño se puede desarmar desde la interfaz sin que quede
// rastro en ninguna parte. Los valores del estándar viven en scss/pre.scss, en
// el repositorio, con su justificación al lado.
//
// Las dos cajas existen para poder corregir algo en una urgencia sin esperar un
// despliegue. Lo que se escriba ahí hay que devolverlo al repositorio después:
// si no, el siguiente despliegue no lo pierde —los ajustes viven en la base—
// pero nadie sabrá por qué el sitio se ve distinto del código.
//
// @package   theme_academia
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettingacademia',
        get_string('configtitle', 'theme_academia'));

    $page = new admin_settingpage('theme_academia_general',
        get_string('generalsettings', 'theme_academia'));

    $page->add(new admin_setting_configtextarea(
        'theme_academia/scsspre',
        get_string('rawscsspre', 'theme_academia'),
        get_string('rawscsspre_desc', 'theme_academia'),
        '',
        PARAM_RAW
    ));

    $page->add(new admin_setting_configtextarea(
        'theme_academia/scss',
        get_string('rawscss', 'theme_academia'),
        get_string('rawscss_desc', 'theme_academia'),
        '',
        PARAM_RAW
    ));

    $settings->add($page);
}
