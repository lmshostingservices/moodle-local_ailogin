<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Hook: before_standard_head_html_generation
 *
 * Fires during <head> generation on every Moodle page (Moodle 4.3+ / 5.x).
 * On the login page only: injects the AI Login CSS into <head> so the branded
 * layout is applied from the first paint with no flash of unstyled content.
 *
 * @package   local_ailogin
 * @copyright 2026 AI Grader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ailogin\hook;

defined('MOODLE_INTERNAL') || die();

class before_standard_head_html_generation {

    /**
     * Injects AI Login CSS into the page <head> on the Moodle login page.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function callback(\core\hook\output\before_standard_head_html_generation $hook): void {
        global $CFG, $PAGE;

        // Self-load lib.php — hook callbacks fire before Moodle auto-loads plugin
        // lib.php, so we must load it explicitly here inside the method where $CFG
        // is in scope (autoloader runs in its own scope where $CFG is unavailable).
        require_once($CFG->dirroot . '/local/ailogin/lib.php');

        if (empty($PAGE->pagetype) && empty($PAGE->pagelayout)) {
            return;
        }

        // Run on login page — check both pagetype and pagelayout for robustness.
        $isLoginPage = (!empty($PAGE->pagetype) && strpos($PAGE->pagetype, 'login') !== false)
                    || (!empty($PAGE->pagelayout) && $PAGE->pagelayout === 'login');

        if (!$isLoginPage) {
            return;
        }

        if (!get_config('local_ailogin', 'enabled')) {
            return;
        }

        // Inject CSS into <head> via the hook's add_html method (Moodle 4.4+/5.x).
        // Moodle 4.3 had this hook but add_html() may not exist — the footer hook
        // handles it as a fallback in that case.
        if (method_exists($hook, 'add_html')) {
            $hook->add_html(local_ailogin_get_css_html());
        }
    }
}
