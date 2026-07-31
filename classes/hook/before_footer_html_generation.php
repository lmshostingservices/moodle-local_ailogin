<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Hook: before_footer_html_generation
 *
 * Fires just before the Moodle footer is output (Moodle 4.3+ / 5.x).
 * On the login page only: injects the brand-panel CSS and JavaScript.
 *
 * Two injection methods are used in parallel for maximum reliability:
 *
 *   1. $hook->add_html()  — preferred; places CSS+JS directly in the page stream
 *      at the footer injection point. Works on Moodle 4.4+. If this silently
 *      fails (Moodle 4.3, broken hook registry, opcache serving stale bytecode),
 *      nothing appears and there is no error.
 *
 *   2. $PAGE->requires->js_init_code()  — guaranteed fallback. This API has been
 *      stable in Moodle for 10+ years and always outputs its payload via
 *      $OUTPUT->footer() → $PAGE->requires->get_end_code(). The payload injects
 *      CSS into <head> via insertAdjacentHTML and runs the brand-panel IIFE.
 *      An idempotency guard in the IIFE prevents double-injection when both
 *      methods succeed.
 *
 * @package   local_ailogin
 * @copyright 2026 AI Grader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ailogin\hook;

defined('MOODLE_INTERNAL') || die();

class before_footer_html_generation {

    /**
     * Injects brand CSS and brand-panel JS at the end of <body> on the login page.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function callback(\core\hook\output\before_footer_html_generation $hook): void {
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

        $cssHtml = local_ailogin_get_css_html();
        $jsiife  = local_ailogin_get_js_iife();
        $jsBlock = '<script id="ailogin-js">' . "\n" . $jsiife . '</script>' . "\n";

        // ── Method 1: $hook->add_html() ───────────────────────────────────────
        // Injects JS ONLY — CSS is handled separately by before_standard_head_html_generation.
        // Keeping CSS out of the footer eliminates the triple-injection issue where
        // both hooks and the js_init_code path were each shipping a full copy.
        if (method_exists($hook, 'add_html')) {
            $hook->add_html($jsBlock);
        }

        // ── Method 2: $PAGE->requires->js_init_code() ────────────────────────
        // Safety net for 4.4+ installs where add_html() is silently broken
        // (stale hook registry, broken opcache, theme-level suppression, etc).
        // This path is reached from inside the hook callback so hooks ARE registered;
        // the issue is only that add_html() didn't produce output.
        //
        // CSS injection is guarded by document.getElementById('ailogin-base') — if
        // the head hook's add_html() already placed the <style> into <head>, the
        // insertAdjacentHTML is skipped and there is no duplication. The brand-panel
        // IIFE also has its own idempotency guard (document.getElementById('ailogin-brand')).
        $cssEscaped = json_encode($cssHtml);
        $initCode =
            'if (!document.getElementById("ailogin-base")) {' .
                'document.head.insertAdjacentHTML("beforeend",' . $cssEscaped . ');' .
            '}' . "\n" .
            $jsiife;

        $PAGE->requires->js_init_code($initCode, false);
    }
}
