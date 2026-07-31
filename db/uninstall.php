<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Uninstall hook for local_ailogin.
 *
 * Strips the plugin's sentinel blocks from Moodle's core Additional HTML config
 * keys (additionalhtmlhead and additionalhtmlfooter) so they don't linger after
 * the plugin is removed.
 *
 * @package   local_ailogin
 * @copyright 2026 AI Grader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_ailogin_uninstall() {
    $strip = '/\n?<!-- ailogin:begin -->.*?<!-- ailogin:end -->\n?/s';

    $head = (string)get_config(null, 'additionalhtmlhead');
    $head = preg_replace($strip, '', $head);
    set_config('additionalhtmlhead', $head);

    $foot = (string)get_config(null, 'additionalhtmlfooter');
    $foot = preg_replace($strip, '', $foot);
    set_config('additionalhtmlfooter', $foot);

    if (function_exists('purge_all_caches')) {
        purge_all_caches();
    }

    return true;
}
