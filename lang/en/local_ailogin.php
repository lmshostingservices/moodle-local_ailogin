<?php
/**
 * local_ailogin file.
 *
 * @package    local_ailogin
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$string['pluginname']            = 'AI Login Designer';
$string['plugindesc']            = 'Transform your Moodle login page into a world-class branded experience using AI.';

// Settings
$string['ailogin_settings']     = 'AI Login Designer Settings';
$string['manage']               = 'Manage AI Login';

// Admin page
$string['heading_status']       = 'Status';
$string['heading_design']       = 'Design Settings';
$string['heading_generate']     = 'Generate with AI';
$string['heading_preview']      = 'Preview';

$string['enabled']              = 'Enable AI Login';
$string['enabled_desc']         = 'When enabled, replaces the standard Moodle login page with a world-class branded layout.';
$string['primary_color']        = 'Primary Colour';
$string['primary_color_desc']   = 'The main brand colour used for the left panel and button. Enter a hex code (e.g. #2563EB).';
$string['slogan']               = 'Brand Slogan';
$string['slogan_desc']          = 'The headline text shown on the left brand panel (e.g. "More teaching, less writing.").';
$string['subtext']              = 'Supporting Text';
$string['subtext_desc']         = 'Optional smaller text below the slogan.';
$string['logo_url']             = 'Logo URL Override';
$string['logo_url_desc']        = 'Leave blank to use the Moodle site logo automatically. Enter a full URL to override.';
$string['design_style']         = 'Design Style';
$string['design_style_desc']    = 'The AI will tailor the colour palette and decorative elements to match this style.';
$string['style_elegant']        = 'Elegant & Minimal';
$string['style_bold']           = 'Bold & Modern';
$string['style_warm']           = 'Warm & Friendly';
$string['style_dark']           = 'Dark & Professional';
$string['style_vibrant']        = 'Vibrant & Energetic';

$string['generate_btn']         = 'Generate with AI (25 credits)';
$string['generate_info']        = 'AI will analyse your colour and style preference then generate a bespoke design. This costs 25 AI credits.';
$string['last_generated']       = 'Last generated:';
$string['never_generated']      = 'Not yet generated — default colours are active.';
$string['generating']           = 'Generating…';

$string['save_settings']        = 'Save Settings';
$string['settings_saved']       = 'Settings saved.';
$string['preview_btn']          = 'Preview Login Page';

$string['error_nocredits']      = 'Insufficient AI credits. Please top up your balance.';
$string['error_generate']       = 'Generation failed. Please try again.';
$string['error_noconfig']       = 'AI Grader platform credentials are not configured. Please install and configure the AI Config plugin first.';
$string['success_generate']     = 'New design generated and applied!';

// Privacy
$string['privacy:metadata']     = 'The AI Login Designer plugin stores design settings in Moodle plugin config. No personal data is stored.';

// Bring your own AI.
$string['byok_badge'] = 'Your own AI · no credits';
$string['byok_confirm'] = 'Generate a new AI design? It will be written by your own AI (no LMS Labs credits are used) and replace the current design.';
$string['byok_failed'] = 'Your AI provider could not design the login page: {$a}';
$string['byok_notavailable'] = 'Bring your own AI is on, but this site\'s AI is not ready. Check Site administration > Plugins > AI Grader Central Config > Bring your own AI.';
