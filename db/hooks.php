<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Hook callbacks for local_ailogin.
 *
 * Registered hooks (Moodle 4.3+ / Moodle 5.x):
 *  - before_standard_head_html_generation: injects the dynamic CSS <link> into <head>
 *    so the login page renders with the brand colours from the first paint (no FOUC).
 *  - before_footer_html_generation: injects the brand-panel JS at the end of <body>
 *    so DOM manipulation happens after the login form exists in the DOM.
 *
 * Moodle 4.0–4.2 fallback: local_ailogin_before_footer() in lib.php injects both
 * CSS and JS at the footer (slight FOUC acceptable on older Moodle versions).
 *
 * @package   local_ailogin
 * @copyright 2026 AI Grader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook'     => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => \local_ailogin\hook\before_standard_head_html_generation::class . '::callback',
        'priority' => 500,
    ],
    [
        'hook'     => \core\hook\output\before_footer_html_generation::class,
        'callback' => \local_ailogin\hook\before_footer_html_generation::class . '::callback',
        'priority' => 500,
    ],
];
