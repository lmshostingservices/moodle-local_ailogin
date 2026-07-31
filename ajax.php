<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX handler for AI Login Designer.
 *
 * Actions:
 *   save_settings — persist admin form settings (colour, slogan, subtext, style, logo_url, enabled)
 *   generate      — call AI Grader platform to generate bespoke CSS, consume 25 credits, store result
 *   toggle        — toggle the enabled flag on/off
 *
 * @package   local_ailogin
 * @copyright 2026 AI Grader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Avoid require_login() — on Moodle 4.4+/5.x it performs additional course-context
// checks that throw 'coursehidden' / "Course or activity not accessible" in AJAX context.
// A direct isloggedin() check + require_sesskey() + require_capability() is safer and
// equivalent for a site-admin-only endpoint.
if (!isloggedin() || isguestuser()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated. Please reload the page and log in again.']);
    exit;
}

require_sesskey();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Release the session lock before any long-running operations.
// Without this, PHP-FPM holds the session file exclusively for the entire
// request — blocking all other Moodle requests from the same user.
\core\session\manager::write_close();

$action = required_param('action', PARAM_ALPHANUMEXT);

header('Content-Type: application/json; charset=utf-8');

// ─── Helper: load AI Grader platform credentials ────────────────────────────
function local_ailogin_get_platform_credentials(): array {
    global $CFG;

    $siteId  = '';
    $apiKey  = '';

    // Try local_aiconfig (centralised credential store) first.
    // local_aiconfig uses 'siteid' and 'apikey' (no underscores).
    if (function_exists('get_config')) {
        $siteId = get_config('local_aiconfig', 'siteid') ?: '';
        $apiKey = get_config('local_aiconfig', 'apikey') ?: '';
    }

    // Fall back to direct plugin config (also uses siteid/apikey for consistency).
    if (empty($siteId)) {
        $siteId = get_config('local_ailogin', 'siteid') ?: '';
        $apiKey = get_config('local_ailogin', 'apikey') ?: '';
    }

    return [$siteId, $apiKey];
}

// ─── Helper: platform base URL ───────────────────────────────────────────────
function local_ailogin_platform_url(): string {
    $url = get_config('local_aiconfig', 'platform_url') ?: 'https://aigrader.com.au';
    return rtrim($url, '/');
}

// ─── Helper: POST to platform API using Moodle's \curl class ─────────────────
// Moodle's \curl (lib/filelib.php) respects $CFG->proxyhost / proxyport /
// proxyuser / proxypassword — raw curl_init() does not, which is why
// "Could not resolve host" errors occur on servers that need a proxy or have
// restricted outbound DNS. Always use \curl for outbound requests in Moodle.
function local_ailogin_api_post(string $endpoint, array $payload): array {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $url  = local_ailogin_platform_url() . $endpoint;
    $json = json_encode($payload);

    $curl = new \curl(['proxy' => true]);
    $curl->setHeader([
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($json),
    ]);
    $curl->setopt([
        'CURLOPT_TIMEOUT'        => 60,
        'CURLOPT_SSL_VERIFYPEER' => true,
    ]);

    $raw  = $curl->post($url, $json);
    $info = $curl->get_info();
    $code = (int)($info['http_code'] ?? 0);
    $err  = $curl->error;

    if ($raw === false || ($err && $code === 0)) {
        $hint = '';
        if (strpos((string)$err, 'Could not resolve host') !== false) {
            $hint = ' — Moodle server cannot reach aigrader.com.au. Check outbound internet access or configure a proxy under Site Admin → Server → HTTP.';
        }
        return ['ok' => false, 'error' => 'Network error: ' . $err . $hint];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Invalid JSON response from platform (HTTP ' . $code . ')'];
    }

    return $data;
}

// ─── Actions ─────────────────────────────────────────────────────────────────
try {
    switch ($action) {

        // ── Save settings ──────────────────────────────────────────────────
        case 'save_settings': {
            $primaryColor = optional_param('primary_color', '#2563EB', PARAM_RAW_TRIMMED);
            $slogan       = optional_param('slogan',        '',         PARAM_TEXT);
            $subtext      = optional_param('subtext',       '',         PARAM_TEXT);
            $style        = optional_param('style',         'elegant',  PARAM_ALPHANUMEXT);
            $logoUrl      = optional_param('logo_url',      '',         PARAM_URL);
            $enabled      = optional_param('enabled',       0,          PARAM_INT);

            // Sanitise hex colour.
            if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $primaryColor)) {
                $primaryColor = '#2563EB';
            }

            $faviconUrl   = optional_param('favicon_url', '', PARAM_URL);

            set_config('primary_color', $primaryColor, 'local_ailogin');
            set_config('slogan',        $slogan,        'local_ailogin');
            set_config('subtext',       $subtext,       'local_ailogin');
            set_config('design_style',  $style,         'local_ailogin');
            set_config('logo_url',      $logoUrl,       'local_ailogin');
            set_config('favicon_url',   $faviconUrl,    'local_ailogin');
            set_config('enabled',       $enabled ? 1 : 0, 'local_ailogin');

            // Bump CSS version so browsers request fresh CSS.
            set_config('css_version', time(), 'local_ailogin');

            // Write CSS+JS into core additionalhtmlhead/additionalhtmlfooter.
            // This is the guaranteed injection path — core_renderer outputs these
            // unconditionally regardless of whether plugin hooks are registered.
            local_ailogin_sync_additionalhtml();

            echo json_encode(['ok' => true, 'message' => 'Settings saved.']);
            break;
        }

        // ── Generate AI design ─────────────────────────────────────────────
        case 'generate': {
            [$siteId, $apiKey] = local_ailogin_get_platform_credentials();
            if (empty($siteId) || empty($apiKey)) {
                echo json_encode(['ok' => false, 'error' => get_string('error_noconfig', 'local_ailogin')]);
                break;
            }

            global $SITE;
            $primaryColor = get_config('local_ailogin', 'primary_color') ?: '#2563EB';
            $style        = get_config('local_ailogin', 'design_style')  ?: 'elegant';
            $slogan       = get_config('local_ailogin', 'slogan')         ?: '';
            // Use $SITE->fullname directly (no format_string) — format_string in
            // AJAX context requires a valid page context which may not be set,
            // causing "Course or activity not accessible" exceptions on some versions.
            $siteName = isset($SITE->fullname) ? strip_tags($SITE->fullname) : '';

            $result = local_ailogin_api_post('/api/ai/login-design', [
                'siteId'       => $siteId,
                'apiKey'       => $apiKey,
                'primaryColor' => $primaryColor,
                'style'        => $style,
                'slogan'       => $slogan,
                'siteName'     => $siteName,
            ]);

            if (!empty($result['ok']) && !empty($result['css'])) {
                // Sanitise AI-generated CSS before storing — strip any tag sequences
                // that could escape the <style> block and execute as stored XSS on
                // the login page when get_css_html() injects the stored value later.
                $safeCss = preg_replace('/<\s*\/?\s*(?:style|script)\b/i', '', $result['css']);
                set_config('generated_css',    $safeCss,        'local_ailogin');
                set_config('last_generated',   time(),          'local_ailogin');
                set_config('css_version',      time(),          'local_ailogin');

                // Sync the new CSS into additionalhtmlhead immediately.
                local_ailogin_sync_additionalhtml();

                echo json_encode([
                    'ok'               => true,
                    'message'          => get_string('success_generate', 'local_ailogin'),
                    'creditsRemaining' => $result['creditsRemaining'] ?? null,
                    'rationale'        => $result['rationale']        ?? '',
                ]);
            } else {
                $error = $result['error'] ?? get_string('error_generate', 'local_ailogin');
                if (($result['error'] ?? '') === 'INSUFFICIENT_CREDITS') {
                    $error = get_string('error_nocredits', 'local_ailogin');
                }
                echo json_encode(['ok' => false, 'error' => $error]);
            }
            break;
        }

        // ── Upload logo ────────────────────────────────────────────────────
        case 'upload_logo': {
            if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
                $uploadError = $_FILES['logo_file']['error'] ?? -1;
                echo json_encode(['ok' => false, 'error' => 'No file received (error code ' . $uploadError . ')']);
                break;
            }

            $file = $_FILES['logo_file'];

            // Validate MIME type using fileinfo.
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mime     = $finfo->file($file['tmp_name']);
            $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
            if (!in_array($mime, $allowed)) {
                echo json_encode(['ok' => false, 'error' => 'Invalid file type. Please upload a PNG, JPG, GIF, SVG, or WebP image.']);
                break;
            }

            // Size cap: 2 MB.
            if ($file['size'] > 2 * 1024 * 1024) {
                echo json_encode(['ok' => false, 'error' => 'File too large. Maximum size is 2 MB.']);
                break;
            }

            $fs      = get_file_storage();
            $context = context_system::instance();

            // Remove any previously uploaded logo.
            $fs->delete_area_files($context->id, 'local_ailogin', 'logo', 0);

            $safeFilename = clean_filename($file['name']);
            $fileinfo = [
                'contextid' => $context->id,
                'component' => 'local_ailogin',
                'filearea'  => 'logo',
                'itemid'    => 0,
                'filepath'  => '/',
                'filename'  => $safeFilename,
            ];

            $storedFile = $fs->create_file_from_pathname($fileinfo, $file['tmp_name']);
            $logoUrl    = moodle_url::make_pluginfile_url(
                $context->id, 'local_ailogin', 'logo', 0, '/', $storedFile->get_filename()
            )->out(false);

            set_config('logo_url', $logoUrl, 'local_ailogin');
            set_config('css_version', time(), 'local_ailogin');
            local_ailogin_sync_additionalhtml();

            echo json_encode(['ok' => true, 'url' => $logoUrl]);
            break;
        }

        // ── Upload favicon (animated background icon) ─────────────────────
        case 'upload_favicon': {
            if (empty($_FILES['favicon_file']['tmp_name'])) {
                echo json_encode(['ok' => false, 'error' => 'No file received.']);
                break;
            }
            $maxBytes = 2 * 1024 * 1024; // 2 MB
            if ($_FILES['favicon_file']['size'] > $maxBytes) {
                echo json_encode(['ok' => false, 'error' => 'File too large (max 2 MB).']);
                break;
            }
            $fs      = get_file_storage();
            $context = context_system::instance();
            $fs->delete_area_files($context->id, 'local_ailogin', 'favicon', 0);
            $origName = clean_filename($_FILES['favicon_file']['name']);
            $fileinfo = [
                'contextid' => $context->id,
                'component' => 'local_ailogin',
                'filearea'  => 'favicon',
                'itemid'    => 0,
                'filepath'  => '/',
                'filename'  => $origName,
            ];
            $file    = $fs->create_file_from_pathname($fileinfo, $_FILES['favicon_file']['tmp_name']);
            $faviconUrl = moodle_url::make_pluginfile_url(
                $context->id, 'local_ailogin', 'favicon', 0, '/', $origName
            )->out(false);
            set_config('favicon_url', $faviconUrl, 'local_ailogin');
            local_ailogin_sync_additionalhtml();
            echo json_encode(['ok' => true, 'url' => $faviconUrl]);
            break;
        }

        // ── Remove favicon ─────────────────────────────────────────────────
        case 'remove_favicon': {
            $fs      = get_file_storage();
            $context = context_system::instance();
            $fs->delete_area_files($context->id, 'local_ailogin', 'favicon', 0);
            set_config('favicon_url', '', 'local_ailogin');
            local_ailogin_sync_additionalhtml();
            echo json_encode(['ok' => true]);
            break;
        }

        // ── Purge Moodle caches ────────────────────────────────────────────
        case 'purge_caches': {
            // Purge all Moodle caches so hook registrations, CSS, and JS
            // caches reload the new plugin code immediately.
            if (function_exists('purge_all_caches')) {
                purge_all_caches();
            } else {
                \cache_helper::purge_all(false);
            }
            echo json_encode(['ok' => true, 'message' => 'Moodle caches purged successfully.']);
            break;
        }

        // ── Remove logo ────────────────────────────────────────────────────
        case 'remove_logo': {
            $fs      = get_file_storage();
            $context = context_system::instance();
            $fs->delete_area_files($context->id, 'local_ailogin', 'logo', 0);
            set_config('logo_url', '', 'local_ailogin');
            set_config('css_version', time(), 'local_ailogin');
            local_ailogin_sync_additionalhtml();
            echo json_encode(['ok' => true]);
            break;
        }

        // ── Toggle enabled ─────────────────────────────────────────────────
        case 'toggle': {
            $current = (int)get_config('local_ailogin', 'enabled');
            $new     = $current ? 0 : 1;
            set_config('enabled', $new, 'local_ailogin');
            // Sync immediately — disable must strip blocks, enable must inject them.
            local_ailogin_sync_additionalhtml();
            echo json_encode(['ok' => true, 'enabled' => (bool)$new]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action: ' . $action]);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
