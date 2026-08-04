<?php
// Configuración del tema de la Academia CONAF.
//
// Es un hijo de Boost, no una copia: hereda sus plantillas, sus disposiciones y
// su renderer, y solo aporta variables de SCSS y unas pocas reglas. Esa es la
// razón de que no haya un directorio layout/ ni templates/ acá — cuando Moodle
// suba de versión, las plantillas se actualizan solas.
//
// @package   theme_academia
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'academia';

// Hereda de Boost: disposiciones, plantillas Mustache y renderers.
$THEME->parents = ['boost'];

$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->editor_scss = ['editor'];
$THEME->usefallback = true;

// Sin $THEME->layouts: se heredan enteras de Boost. Copiarlas acá es el error
// clásico de los temas hijos — quedan congeladas en la versión del día que se
// copiaron y rompen en la siguiente actualización de Moodle.

$THEME->scss = function($theme) {
    return theme_academia_get_main_scss_content($theme);
};

$THEME->prescsscallback   = 'theme_academia_get_pre_scss';
$THEME->extrascsscallback = 'theme_academia_get_extra_scss';

$THEME->enable_dock = false;
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->yuicssmodules = [];
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->activityheaderconfig = [
    'notitle' => true,
];
