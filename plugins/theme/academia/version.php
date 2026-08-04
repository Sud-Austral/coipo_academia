<?php
// Tema de la Academia CONAF — hijo de Boost.
//
// @package   theme_academia
// @copyright 2026 CONAF
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026080400;
$plugin->requires  = 2026042000;   // Moodle 5.2
$plugin->component = 'theme_academia';
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.2.0 — instancia v2 sobre Moodle 5.2';

// Declarado explícito: solo 5.2. Es la única rama contra la que se compiló y se
// comprobó que las reglas del estándar llegan al CSS.
//
// Y NO se declara compatibilidad hacia atrás con 4.5 aunque el SCSS
// probablemente compilaría igual: en 4.5 la raíz web no es public/, así que el
// tema quedaría en otra ruta y ni la imagen ni el Dockerfile de este repositorio
// sirven ahí. Un `supported` optimista solo consigue que alguien lo instale
// donde no corresponde.
$plugin->supported = [502, 502];

$plugin->dependencies = [
    'theme_boost' => 2026042000,
];
