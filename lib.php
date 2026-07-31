<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * lib.php for local_ailogin.
 *
 * Contains:
 *  (A) local_ailogin_before_footer() — Moodle 4.0–4.2 legacy callback.
 *      Moodle 4.3+ and Moodle 5.x use the hook system in db/hooks.php instead.
 *      This function checks the Moodle version and exits immediately on 4.3+ so it
 *      never double-injects when both this callback AND the hook fire.
 *
 *  (B) local_ailogin_get_inject_html($headonly) — shared helper used by both the
 *      lib.php legacy callback and the hook listeners.
 *
 * @package   local_ailogin
 * @copyright 2026 AI Grader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve uploaded logo files from the local_ailogin file area.
 *
 * Called by Moodle's pluginfile.php dispatcher when a request arrives for
 * a file stored under component=local_ailogin, filearea=logo.
 *
 * @param stdClass $course    The course object (unused — system-level file).
 * @param stdClass $cm        The course-module object (unused).
 * @param context  $context   The context the file belongs to.
 * @param string   $filearea  The filearea name.
 * @param array    $args      Extra path arguments: [itemid, filename].
 * @param bool     $forcedownload  Whether to force a download response.
 * @param array    $options   Additional options.
 * @return bool    false if the file is not found; otherwise sends the file and exits.
 */
function local_ailogin_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    // Serve logo and favicon filezones only.
    if ($filearea !== 'logo' && $filearea !== 'favicon') {
        return false;
    }

    // Require login so anonymous users can't enumerate files, but allow
    // the login page itself (which is loaded before the session exists) to
    // fetch the logo by checking for a valid file regardless of session.
    // We intentionally do NOT call require_login() here so that the logo
    // is visible on the Moodle login page itself (pre-authentication).

    $itemid  = (int)array_shift($args);   // 0
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, 'local_ailogin', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    // Cache for 1 day; logo rarely changes.
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

/**
 * Moodle 4.0–4.3 legacy "before_standard_html_head" callback.
 *
 * Injects brand CSS into <head> on the login page for Moodle versions that
 * predate the output hooks. before_standard_head_html_generation was only
 * dispatched from Moodle 4.4.0 (version 2024042200) onwards; on 4.0–4.3 this
 * legacy function is the correct CSS injection path with no FOUC.
 *
 * On Moodle 4.4+ the hook system handles CSS injection; this function bails out
 * immediately to prevent double-injection.
 *
 * @return string HTML to inject into <head>.
 */
function local_ailogin_before_standard_html_head(): string {
    global $CFG, $PAGE;

    // 4.4.0+ uses the hook system; bail out to prevent double-injection.
    if ($CFG->version >= 2024042200) {
        return '';
    }

    if (empty($PAGE->pagetype) && empty($PAGE->pagelayout)) {
        return '';
    }

    $isLoginPage = (!empty($PAGE->pagetype) && strpos($PAGE->pagetype, 'login') !== false)
                || (!empty($PAGE->pagelayout) && $PAGE->pagelayout === 'login');

    if (!$isLoginPage) {
        return '';
    }

    if (!get_config('local_ailogin', 'enabled')) {
        return '';
    }

    return local_ailogin_get_css_html();
}

/**
 * Moodle 4.0–4.3 legacy "before_footer" callback.
 *
 * Injects brand panel JS at the end of <body> on the login page for Moodle
 * versions that predate the output hooks. before_footer_html_generation was
 * only dispatched from Moodle 4.4.0 (version 2024042200) onwards; on 4.0–4.3
 * this legacy function is the correct JS injection path.
 *
 * On Moodle 4.4+ the hook system handles JS injection; this function bails out
 * immediately to prevent double-injection.
 *
 * Moodle invokes this as $output .= local_ailogin_before_footer(), so this
 * function RETURNS the HTML string rather than echoing it.
 *
 * @return string HTML to inject before the Moodle footer.
 */
function local_ailogin_before_footer(): string {
    global $CFG, $PAGE;

    // 4.4.0+ uses the hook system; bail out to prevent double-injection.
    if ($CFG->version >= 2024042200) {
        return '';
    }

    // Only run on the login page.
    if (empty($PAGE->pagetype) || strpos($PAGE->pagetype, 'login') === false) {
        return '';
    }

    if (!get_config('local_ailogin', 'enabled')) {
        return '';
    }

    // CSS was already injected by local_ailogin_before_standard_html_head().
    // Inject only JS here; returning it for Moodle to concatenate.
    return local_ailogin_get_js_html();
}

/**
 * Returns the <style> block with both the base layout CSS and any AI-generated CSS.
 * Used by both the lib.php legacy callback and the hook CSS listener.
 *
 * @return string HTML <style> block.
 */
function local_ailogin_get_css_html(): string {
    $primaryColor = get_config('local_ailogin', 'primary_color') ?: '#2563EB';
    $generatedCss = get_config('local_ailogin', 'generated_css') ?: '';

    // Sanitise primary colour — must be a valid hex colour.
    if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $primaryColor)) {
        $primaryColor = '#2563EB';
    }

    // Build default CSS variables from the primary colour (used when no AI CSS exists).
    $defaultVars = local_ailogin_build_default_vars($primaryColor);

    $cssVersion = (int)get_config('local_ailogin', 'css_version');

    ob_start();
    ?>
<style id="ailogin-base" data-v="<?php echo (int)$cssVersion; ?>">
/* =====================================================================
   AI Login Designer — Base Layout CSS
   Works with any Moodle theme on Moodle 4.0–5.x
   ===================================================================== */

/* Default CSS custom properties — overridden by AI-generated CSS below */
:root {
<?php echo $defaultVars; ?>
}

/* ── Two-column body layout ─────────────────────────────────────────── */
body.pagelayout-login.ailogin-active {
    margin: 0 !important;
    padding: 0 !important;
    min-height: 100vh;
    display: flex !important;
    flex-direction: row;
    background: var(--ailogin-form-bg, #F8FAFC) !important;
    overflow-x: hidden;
}

/* ── Brand panel (left column) ──────────────────────────────────────── */
#ailogin-brand {
    width: 45%;
    min-width: 300px;
    min-height: 100vh;
    background: var(--ailogin-gradient, linear-gradient(145deg, #1E3A8A 0%, #2563EB 100%));
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: space-between;
    padding: 3rem;
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
    box-sizing: border-box;
}

/* Dot-grid texture overlay */
#ailogin-brand::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,0.045) 1px, transparent 1px);
    background-size: 30px 30px;
    pointer-events: none;
    z-index: 0;
}

/* Primary-colour glow orb — bottom right, subtle */
#ailogin-brand::after {
    content: '';
    position: absolute;
    width: 640px;
    height: 640px;
    background: radial-gradient(circle, var(--ailogin-primary-glow, rgba(37,99,235,0.18)) 0%, transparent 68%);
    border-radius: 50%;
    bottom: -180px;
    right: -180px;
    pointer-events: none;
    z-index: 0;
}

/* Logo */
.ailogin-logo {
    max-height: 52px;
    max-width: 200px;
    width: auto;
    object-fit: contain;
    display: block;
    position: relative;
    z-index: 1;
    filter: brightness(0) invert(1);
}

.ailogin-logo-fallback {
    font-size: 1.25rem;
    font-weight: 700;
    color: rgba(255,255,255,0.9);
    letter-spacing: -0.3px;
    position: relative;
    z-index: 1;
}

/* Centre content */
.ailogin-brand-middle {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    transform: translateY(-50%);
    padding: 0 3rem;
    text-align: left;
    z-index: 1;
}

/* Accent rule — glowing primary-colour bar above slogan */
.ailogin-accent-rule {
    width: 44px;
    height: 3px;
    background: var(--ailogin-primary, #2563EB);
    border-radius: 2px;
    margin-bottom: 1.5rem;
    box-shadow: 0 0 18px var(--ailogin-primary-glow, rgba(37,99,235,0.7));
    position: relative;
    z-index: 1;
}

.ailogin-slogan {
    font-size: clamp(2rem, 3.2vw, 3rem);
    font-weight: 800;
    color: #ffffff;
    line-height: 1.08;
    margin: 0 0 1.1rem 0;
    letter-spacing: -1.5px;
}

.ailogin-subtext {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.58);
    line-height: 1.7;
    max-width: 30ch;
    font-weight: 400;
}

/* Brand panel footer */
.ailogin-brand-footer {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.35);
    position: relative;
    z-index: 1;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    font-weight: 500;
}

/* ── Form panel (right column) ─────────────────────────────────────── */
body.pagelayout-login.ailogin-active #page-wrapper {
    flex: 1;
    display: flex !important;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    background: var(--ailogin-form-bg, #F5F7FA) !important;
    background-image: none !important;
    padding: 2.5rem 2rem;
    box-sizing: border-box;
}

/* Override Academi/any theme backgrounds on inner page containers.
   The Academi theme sets its login background-image on #page (not #page-wrapper),
   so we must explicitly clear it here too. */
body.pagelayout-login.ailogin-active #page,
body.pagelayout-login.ailogin-active .login-wrapper,
body.pagelayout-login.ailogin-active #main,
body.pagelayout-login.ailogin-active #maincontent,
body.pagelayout-login.ailogin-active [data-region="mainpage"],
body.pagelayout-login.ailogin-active .pagelayout-login {
    background: var(--ailogin-form-bg, #F5F7FA) !important;
    background-image: none !important;
    background-color: var(--ailogin-form-bg, #F5F7FA) !important;
    min-height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}

/* Also clear any theme pseudo-element backgrounds on body */
body.pagelayout-login.ailogin-active::before,
body.pagelayout-login.ailogin-active::after {
    background: none !important;
    background-image: none !important;
}

/* Strip default Moodle chrome on login page */
body.pagelayout-login.ailogin-active #page-header,
body.pagelayout-login.ailogin-active .navbar,
body.pagelayout-login.ailogin-active nav.navbar,
body.pagelayout-login.ailogin-active #page footer,
body.pagelayout-login.ailogin-active footer#page-footer,
body.pagelayout-login.ailogin-active .header-main,
body.pagelayout-login.ailogin-active [data-region="fixed-drawer"],
body.pagelayout-login.ailogin-active .drawer,
body.pagelayout-login.ailogin-active .toast-wrapper {
    display: none !important;
}

/* ── Login card ─────────────────────────────────────────────────────── */
body.pagelayout-login.ailogin-active .loginpanel,
body.pagelayout-login.ailogin-active #login,
body.pagelayout-login.ailogin-active div.login-container {
    background: var(--ailogin-card-bg, #ffffff) !important;
    border-radius: 16px !important;
    box-shadow:
        0 0 0 1px rgba(0,0,0,0.04),
        0 2px 4px rgba(0,0,0,0.03),
        0 8px 20px rgba(0,0,0,0.05),
        0 28px 56px rgba(0,0,0,0.09) !important;
    padding: 3rem !important;
    width: 100% !important;
    max-width: 440px !important;
    box-sizing: border-box;
    border: none !important;
}

/* ── Form inputs ────────────────────────────────────────────────────── */
body.pagelayout-login.ailogin-active .form-control,
body.pagelayout-login.ailogin-active input[type="text"],
body.pagelayout-login.ailogin-active input[type="password"] {
    border: 1px solid #E8EDF2 !important;
    border-radius: 8px !important;
    padding: 0.75rem 1rem !important;
    font-size: 0.9375rem !important;
    background: #FAFCFE !important;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    color: #111827 !important;
}

body.pagelayout-login.ailogin-active .form-control:focus,
body.pagelayout-login.ailogin-active input[type="text"]:focus,
body.pagelayout-login.ailogin-active input[type="password"]:focus {
    border-color: var(--ailogin-primary, #2563EB) !important;
    box-shadow: 0 0 0 3px var(--ailogin-primary-alpha, rgba(37,99,235,0.12)) !important;
    background: #fff !important;
    outline: none !important;
}

/* ── Submit button ──────────────────────────────────────────────────── */
body.pagelayout-login.ailogin-active #loginbtn,
body.pagelayout-login.ailogin-active button[type="submit"],
body.pagelayout-login.ailogin-active .login-btn {
    background: var(--ailogin-primary, #2563EB) !important;
    border: none !important;
    color: #fff !important;
    border-radius: 8px !important;
    padding: 0.875rem 1.5rem !important;
    font-size: 0.9375rem !important;
    font-weight: 600 !important;
    letter-spacing: 0.01em;
    width: 100% !important;
    cursor: pointer;
    transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.12s ease;
    box-shadow:
        0 1px 2px rgba(0,0,0,0.12),
        0 4px 12px var(--ailogin-primary-alpha, rgba(37,99,235,0.4)) !important;
}

body.pagelayout-login.ailogin-active #loginbtn:hover,
body.pagelayout-login.ailogin-active button[type="submit"]:hover {
    transform: translateY(-1px);
    box-shadow:
        0 2px 4px rgba(0,0,0,0.1),
        0 8px 20px var(--ailogin-primary-alpha, rgba(37,99,235,0.5)) !important;
    opacity: 0.93;
}

body.pagelayout-login.ailogin-active #loginbtn:active,
body.pagelayout-login.ailogin-active button[type="submit"]:active {
    transform: translateY(0);
    opacity: 1;
}

/* ── Form labels ────────────────────────────────────────────────────── */
body.pagelayout-login.ailogin-active .form-label,
body.pagelayout-login.ailogin-active label {
    font-weight: 600 !important;
    color: #374151 !important;
    font-size: 0.875rem !important;
    margin-bottom: 0.4rem !important;
}

/* ── "Forgot password" link ─────────────────────────────────────────── */
body.pagelayout-login.ailogin-active .login-form-forgotpassword a,
body.pagelayout-login.ailogin-active a.login-forgot-password {
    color: var(--ailogin-primary, #2563EB) !important;
    font-size: 0.875rem;
    text-decoration: none;
}

body.pagelayout-login.ailogin-active .login-form-forgotpassword a:hover {
    text-decoration: underline;
}

/* ── Page heading inside card ───────────────────────────────────────── */
body.pagelayout-login.ailogin-active h2,
body.pagelayout-login.ailogin-active .login-heading {
    font-size: 1.5rem !important;
    font-weight: 700 !important;
    color: #111827 !important;
    margin-bottom: 1.5rem !important;
}

/* ── Accessibility ──────────────────────────────────────────────────── */
body.pagelayout-login.ailogin-active :focus-visible {
    outline: 3px solid var(--ailogin-primary, #2563EB) !important;
    outline-offset: 2px;
}

/* ── Mobile responsive ──────────────────────────────────────────────── */
@media (max-width: 768px) {
    body.pagelayout-login.ailogin-active {
        flex-direction: column !important;
    }
    #ailogin-brand {
        width: 100% !important;
        min-height: 200px !important;
        max-height: 260px;
        padding: 2rem !important;
        justify-content: center !important;
        align-items: flex-start !important;
    }
    .ailogin-brand-middle {
        position: relative !important;
        top: auto !important;
        left: auto !important;
        right: auto !important;
        transform: none !important;
        padding: 1rem 0 0 0 !important;
    }
    .ailogin-slogan {
        font-size: 1.5rem !important;
    }
    .ailogin-subtext {
        display: none;
    }
    .ailogin-brand-footer {
        display: none;
    }
    body.pagelayout-login.ailogin-active #page-wrapper {
        padding: 1.5rem 1rem !important;
        min-height: auto !important;
    }
}

/* ── Reduced motion ─────────────────────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
    body.pagelayout-login.ailogin-active #loginbtn,
    body.pagelayout-login.ailogin-active button[type="submit"],
    body.pagelayout-login.ailogin-active .form-control {
        transition: none !important;
    }
    .ailogin-particle {
        animation: none !important;
    }
}

/* ── Animated favicon particles ─────────────────────────────────────── */
@keyframes ailogin-pfloat {
    0%   { transform: translate(0,     0)     rotate(  0deg) scale(1.00); }
    20%  { transform: translate(10px, -20px)  rotate(  5deg) scale(1.04); }
    40%  { transform: translate(-7px, -10px)  rotate( -4deg) scale(0.97); }
    60%  { transform: translate(13px, -26px)  rotate(  7deg) scale(1.05); }
    80%  { transform: translate(-4px, -14px)  rotate( -2deg) scale(1.02); }
    100% { transform: translate(0,     0)     rotate(  0deg) scale(1.00); }
}
@keyframes ailogin-pfade {
    0%,  100% { opacity: 0; }
    15%        { opacity: var(--p-op, 0.07); }
    45%        { opacity: calc(var(--p-op, 0.07) * 0.3); }
    70%        { opacity: var(--p-op, 0.07); }
    90%        { opacity: calc(var(--p-op, 0.07) * 0.15); }
}
.ailogin-particle {
    position: absolute;
    pointer-events: none;
    user-select: none;
    object-fit: contain;
    will-change: transform, opacity;
    filter: blur(0.4px);
    animation:
        ailogin-pfloat var(--p-dur,  14s) cubic-bezier(0.45, 0.05, 0.55, 0.95) infinite,
        ailogin-pfade  var(--p-fdur, 14s) ease-in-out                           infinite;
}
</style>
<?php
// ── Style-specific design override ───────────────────────────────────────
$_aiStyle    = get_config('local_ailogin', 'design_style') ?: 'elegant';
$_aiHex      = ltrim($primaryColor, '#');
if (strlen($_aiHex) === 3) {
    $_aiHex = $_aiHex[0].$_aiHex[0].$_aiHex[1].$_aiHex[1].$_aiHex[2].$_aiHex[2];
}
$_aiR        = (int)hexdec(substr($_aiHex, 0, 2));
$_aiG        = (int)hexdec(substr($_aiHex, 2, 2));
$_aiB        = (int)hexdec(substr($_aiHex, 4, 2));
$_aiStyleCss = local_ailogin_get_style_override_css($_aiStyle, $_aiR, $_aiG, $_aiB);
if ($_aiStyleCss !== ''):
?>
<style id="ailogin-theme">
<?php echo $_aiStyleCss; ?>
</style>
<?php endif; ?>
<?php if ($generatedCss):
    // Sanitise AI-generated CSS before injection to prevent stored XSS.
    // Strip any closing </style> or opening <script> tags that could break
    // out of the <style> block — even if the platform TLS is intact, defence
    // in depth matters for content that is echoed verbatim on the login page.
    $safeCss = preg_replace('/<\s*\/?\s*(?:style|script)\b/i', '', $generatedCss);
?>
<style id="ailogin-ai">
<?php echo $safeCss; ?>
</style>
<?php endif; ?>
<?php
    return ob_get_clean();
}

/**
 * Returns design-theme-specific CSS overrides for the 5 preset login page styles.
 *
 * Each style produces a genuinely different page layout — not just a colour variation.
 * The CSS is injected AFTER the base layout CSS and BEFORE any AI-generated CSS,
 * so AI generation can still refine/extend these presets.
 *
 * CSS cascade note: all selectors use `body.pagelayout-login.ailogin-active` prefix
 * with `!important` so they win over the base CSS which uses the same specificity.
 * Moodle's PHP CSS minifier does NOT process this output (it is injected raw via
 * additionalhtmlhead), so modern CSS features (clip-path, backdrop-filter, keyframes)
 * are safe to use here without the restrictions that apply to styles.css.
 *
 * @param string $style  One of: elegant, bold, warm, dark, vibrant
 * @param int    $r      Primary colour red channel 0–255
 * @param int    $g      Primary colour green channel 0–255
 * @param int    $b      Primary colour blue channel 0–255
 * @return string  CSS string (no <style> wrapper)
 */
function local_ailogin_get_style_override_css(string $style, int $r, int $g, int $b): string {
    // Pre-compute rgba() strings for the primary colour at common opacities.
    $c06  = "rgba({$r},{$g},{$b},0.06)";
    $c09  = "rgba({$r},{$g},{$b},0.09)";
    $c10  = "rgba({$r},{$g},{$b},0.10)";
    $c14  = "rgba({$r},{$g},{$b},0.14)";
    $c30  = "rgba({$r},{$g},{$b},0.30)";
    $cHex = sprintf('#%02x%02x%02x', $r, $g, $b);

    // Shorthand aliases for the CSS selectors used in every design.
    $S        = 'body.pagelayout-login.ailogin-active';

    // Login-card selector group (all three Moodle variants).
    $card     = "{$S} .loginpanel,{$S} #login,{$S} div.login-container";
    $cardHov  = "{$S} .loginpanel:hover,{$S} #login:hover,{$S} div.login-container:hover";

    // Inner background elements that must inherit the page background.
    $bgInner  = implode(',', [
        "{$S} #page-wrapper",
        "{$S} #page",
        "{$S} .login-wrapper",
        "{$S} #main",
        "{$S} #maincontent",
        "{$S} [data-region='mainpage']",
        "{$S} .pagelayout-login",
    ]);

    switch ($style) {

        // ── 1. ELEGANT — Obsidian Minimal ────────────────────────────────────
        // No left panel. Animated gradient-orb background (near-white with subtle
        // lavender/blue tint). Frosted-glass login card that lifts on hover.
        // Inspired by Linear.app and Notion.
        case 'elegant':
            return <<<CSS
{$S} #ailogin-brand{display:none!important}
{$S}{background:linear-gradient(160deg,#f8f9ff 0%,#eff4ff 45%,#f4f0ff 100%)!important}
{$S}::before{
    content:''!important;position:fixed!important;
    width:860px!important;height:860px!important;
    top:-280px!important;right:-200px!important;left:auto!important;bottom:auto!important;
    border-radius:50%!important;
    background:radial-gradient(circle,{$c09} 0%,transparent 68%)!important;
    pointer-events:none!important;z-index:0!important;
    animation:ailogin-orb-a 22s ease-in-out infinite alternate!important}
{$S}::after{
    content:''!important;position:fixed!important;
    width:700px!important;height:700px!important;
    bottom:-180px!important;left:-80px!important;top:auto!important;right:auto!important;
    border-radius:50%!important;
    background:radial-gradient(circle,{$c06} 0%,transparent 68%)!important;
    pointer-events:none!important;z-index:0!important;
    animation:ailogin-orb-b 28s ease-in-out infinite alternate!important}
@keyframes ailogin-orb-a{
    0%{transform:translate(0,0) scale(1)}
    100%{transform:translate(-60px,50px) scale(1.1)}}
@keyframes ailogin-orb-b{
    0%{transform:translate(0,0) scale(1)}
    100%{transform:translate(60px,-50px) scale(1.1)}}
{$bgInner}{background:transparent!important;background-image:none!important;background-color:transparent!important}
{$card}{
    background:rgba(255,255,255,0.88)!important;
    backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
    border-radius:20px!important;
    box-shadow:
        0 0 0 1px rgba(0,0,0,0.04),
        0 4px 24px {$c06},
        0 20px 60px rgba(0,0,0,0.09),
        0 60px 120px rgba(0,0,0,0.05)!important;
    transition:transform 0.35s cubic-bezier(0.34,1.56,0.64,1),box-shadow 0.35s ease}
{$cardHov}{
    transform:translateY(-4px);
    box-shadow:
        0 0 0 1px rgba(0,0,0,0.04),
        0 8px 32px {$c10},
        0 30px 80px rgba(0,0,0,0.12),
        0 80px 140px rgba(0,0,0,0.07)!important}
CSS;

        // ── 2. BOLD — Corporate Dark ─────────────────────────────────────────
        // Left panel retained but clipped with a diagonal polygon edge.
        // Very dark panel (near-black → primary) with a fine grid texture.
        // Pure white right side. Card lifts on hover.
        // Inspired by Stripe and Atlassian.
        case 'bold':
            return <<<CSS
{$S}{background:#ffffff!important}
{$S} #page-wrapper{background:#ffffff!important}
#ailogin-brand{
    width:48%!important;
    clip-path:polygon(0 0,87% 0,100% 100%,0 100%)!important;
    padding-right:6rem!important;
    background:linear-gradient(150deg,#080c16 0%,{$cHex} 160%)!important;
    box-shadow:14px 0 56px rgba(0,0,0,0.4)!important;
    z-index:1!important;position:relative!important}
#ailogin-brand::before{
    background-image:
        linear-gradient(rgba(255,255,255,0.035) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,0.035) 1px,transparent 1px)!important;
    background-size:44px 44px!important;background-color:transparent!important}
#ailogin-brand::after{
    width:560px!important;height:560px!important;
    background:radial-gradient(circle,{$c30} 0%,transparent 65%)!important;
    bottom:-100px!important;right:-60px!important}
{$card}{
    box-shadow:
        0 0 0 1px rgba(0,0,0,0.06),
        0 4px 16px rgba(0,0,0,0.05),
        0 20px 48px rgba(0,0,0,0.10),
        0 48px 80px rgba(0,0,0,0.07)!important;
    border-radius:16px!important;
    transition:transform 0.3s ease,box-shadow 0.3s ease}
{$cardHov}{
    transform:translateY(-3px);
    box-shadow:
        0 0 0 1px rgba(0,0,0,0.06),
        0 8px 28px rgba(0,0,0,0.09),
        0 28px 64px rgba(0,0,0,0.13),
        0 64px 100px rgba(0,0,0,0.08)!important}
CSS;

        // ── 3. WARM — Executive Warm ─────────────────────────────────────────
        // No left panel. Full-page warm gradient (cream → amber → blush).
        // Two soft ambient orbs in opposing corners. White card with warm shadow.
        // Inspired by Webflow and Framer.
        case 'warm':
            return <<<CSS
{$S} #ailogin-brand{display:none!important}
{$S}{background:linear-gradient(135deg,#fff6ee 0%,#ffe8d6 35%,#fff0f8 70%,#fff6ee 100%)!important}
{$S}::before{
    content:''!important;position:fixed!important;
    width:700px!important;height:700px!important;
    top:-180px!important;left:-120px!important;bottom:auto!important;right:auto!important;
    border-radius:50%!important;
    background:radial-gradient(circle,rgba(255,160,80,0.14) 0%,transparent 65%)!important;
    pointer-events:none!important;z-index:0!important}
{$S}::after{
    content:''!important;position:fixed!important;
    width:800px!important;height:800px!important;
    bottom:-220px!important;right:-160px!important;top:auto!important;left:auto!important;
    border-radius:50%!important;
    background:radial-gradient(circle,{$c09} 0%,transparent 65%)!important;
    pointer-events:none!important;z-index:0!important}
{$bgInner}{background:transparent!important;background-image:none!important;background-color:transparent!important}
{$card}{
    background:#ffffff!important;border-radius:20px!important;
    box-shadow:
        0 0 0 1px rgba(200,100,50,0.07),
        0 4px 20px rgba(200,80,40,0.08),
        0 20px 60px rgba(0,0,0,0.08),
        0 60px 100px rgba(0,0,0,0.05)!important;
    transition:transform 0.35s cubic-bezier(0.34,1.56,0.64,1),box-shadow 0.35s ease}
{$cardHov}{
    transform:translateY(-4px);
    box-shadow:
        0 0 0 1px rgba(200,100,50,0.07),
        0 8px 32px rgba(200,80,40,0.13),
        0 30px 80px rgba(0,0,0,0.11),
        0 80px 120px rgba(0,0,0,0.06)!important}
CSS;

        // ── 4. DARK — Silicon Valley Dark ────────────────────────────────────
        // No left panel. Near-black (#07090f) full-page background with a subtle
        // 60px dot-grid texture. Elliptical primary-colour glow at the top-centre.
        // White card floats with a deep shadow and primary-colour outer glow.
        // Inspired by Vercel and Figma.
        case 'dark':
            return <<<CSS
{$S} #ailogin-brand{display:none!important}
{$S}{background:#07090f!important}
{$S}::before{
    content:''!important;position:fixed!important;inset:0!important;
    background-image:
        linear-gradient(rgba(255,255,255,0.028) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,0.028) 1px,transparent 1px)!important;
    background-size:60px 60px!important;background-color:transparent!important;
    pointer-events:none!important;z-index:0!important}
{$S}::after{
    content:''!important;position:fixed!important;
    width:1000px!important;height:500px!important;
    top:-100px!important;left:50%!important;right:auto!important;bottom:auto!important;
    transform:translateX(-50%)!important;border-radius:50%!important;
    background:radial-gradient(ellipse,{$c10} 0%,transparent 65%)!important;
    pointer-events:none!important;z-index:0!important}
{$bgInner}{background:transparent!important;background-image:none!important;background-color:transparent!important}
{$card}{
    background:#ffffff!important;border-radius:18px!important;
    box-shadow:
        0 0 0 1px rgba(255,255,255,0.07),
        0 0 60px {$c14},
        0 20px 60px rgba(0,0,0,0.7),
        0 60px 120px rgba(0,0,0,0.55)!important;
    transition:transform 0.4s cubic-bezier(0.34,1.56,0.64,1),box-shadow 0.4s ease}
{$cardHov}{
    transform:translateY(-5px);
    box-shadow:
        0 0 0 1px rgba(255,255,255,0.10),
        0 0 100px {$c30},
        0 30px 80px rgba(0,0,0,0.80),
        0 80px 160px rgba(0,0,0,0.65)!important}
CSS;

        // ── 5. VIBRANT — Modern Professional ─────────────────────────────────
        // Brand panel becomes a full-width HORIZONTAL BANNER at the top of the
        // page instead of a left column. Logo + slogan sit inline in the banner.
        // Clean light-grey content area below. Card lifts on hover.
        // Inspired by HubSpot and Loom.
        case 'vibrant':
            return <<<CSS
{$S}{flex-direction:column!important;background:#f2f5fb!important}
#ailogin-brand{
    width:100%!important;min-width:0!important;
    min-height:0!important;max-height:none!important;height:auto!important;
    flex-direction:row!important;align-items:center!important;
    justify-content:flex-start!important;gap:2rem;
    padding:1.75rem 3.5rem!important;
    background:var(--ailogin-gradient)!important;overflow:hidden!important}
#ailogin-brand::before{
    background-image:radial-gradient(rgba(255,255,255,0.07) 1px,transparent 1px)!important;
    background-size:26px 26px!important;background-color:transparent!important}
#ailogin-brand::after{
    width:380px!important;height:380px!important;
    right:60px!important;top:-140px!important;bottom:auto!important;
    background:radial-gradient(circle,rgba(255,255,255,0.18) 0%,transparent 65%)!important}
.ailogin-brand-middle{
    position:relative!important;top:auto!important;left:auto!important;right:auto!important;
    transform:none!important;padding:0!important;flex:1}
.ailogin-accent-rule{display:none!important}
.ailogin-slogan{
    font-size:1.3rem!important;margin:0!important;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ailogin-subtext{
    font-size:0.82rem!important;margin:0.25rem 0 0!important;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:70ch}
.ailogin-brand-footer{display:none!important}
{$S} #page-wrapper{flex:1!important;background:#f2f5fb!important}
{$S} #page,{$S} .login-wrapper,{$S} #main,{$S} #maincontent,
{$S} [data-region='mainpage'],{$S} .pagelayout-login{
    background:#f2f5fb!important;background-color:#f2f5fb!important}
{$card}{
    border-radius:16px!important;
    box-shadow:
        0 0 0 1px rgba(0,0,0,0.04),
        0 4px 16px rgba(0,0,0,0.05),
        0 16px 40px rgba(0,0,0,0.08),
        0 40px 80px rgba(0,0,0,0.05)!important;
    transition:transform 0.3s cubic-bezier(0.34,1.56,0.64,1),box-shadow 0.3s ease}
{$cardHov}{
    transform:translateY(-3px);
    box-shadow:
        0 0 0 1px rgba(0,0,0,0.04),
        0 6px 24px rgba(0,0,0,0.07),
        0 24px 56px rgba(0,0,0,0.11),
        0 60px 100px rgba(0,0,0,0.07)!important}
@media(max-width:768px){
    #ailogin-brand{padding:1.25rem 1.5rem!important;gap:1rem}
    .ailogin-slogan{font-size:1.05rem!important}
    .ailogin-subtext{display:none!important}}
CSS;

        default:
            return '';
    }
}

/**
 * Returns the raw JavaScript IIFE (no <script> tags) that injects the brand panel.
 *
 * Includes an idempotency guard so it is safe to call multiple times on the same page
 * (e.g. when both $hook->add_html() and $PAGE->requires->js_init_code() are used).
 *
 * @return string Raw JavaScript IIFE string.
 */
function local_ailogin_get_js_iife(): string {
    global $PAGE, $SITE, $OUTPUT;

    $slogan     = get_config('local_ailogin', 'slogan')      ?: 'Welcome back.';
    $subtext    = get_config('local_ailogin', 'subtext')     ?: '';
    $logoUrl    = get_config('local_ailogin', 'logo_url')    ?: '';
    $faviconUrl = get_config('local_ailogin', 'favicon_url') ?: '';

    // Auto-detect Moodle site logo if no override provided.
    if (empty($logoUrl)) {
        try {
            $logoMoodleUrl = $OUTPUT->get_logo_url(null, 80);
            if ($logoMoodleUrl) {
                $logoUrl = $logoMoodleUrl->out(false);
            }
        } catch (\Throwable $e) {
            $logoUrl = '';
        }
    }

    // HTML-escape slogan/subtext before embedding in JSON — they are inserted via
    // innerHTML so escaping here ensures tags cannot be injected even if a future
    // code path writes these config values without PARAM_TEXT sanitisation.
    $config = json_encode([
        'slogan'     => htmlspecialchars($slogan,  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'subtext'    => htmlspecialchars($subtext, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'logoUrl'    => $logoUrl,
        'faviconUrl' => $faviconUrl,
        'siteName'   => htmlspecialchars(format_string($SITE->fullname), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

    ob_start();
    ?>
(function () {
    'use strict';
    var cfg = <?php echo $config; ?>;

    function init() {
        if (!document.body.classList.contains('pagelayout-login')) {
            return;
        }
        /* Idempotency guard — safe when both add_html() and js_init_code() fire */
        if (document.getElementById('ailogin-brand')) {
            return;
        }

        /* Build brand panel HTML ---------------------------------------- */
        var logoHtml = '';
        if (cfg.logoUrl) {
            logoHtml = '<img src="' + cfg.logoUrl + '" class="ailogin-logo" alt="' + cfg.siteName + '" ' +
                'onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'block\'">' +
                '<span class="ailogin-logo-fallback" style="display:none">' + cfg.siteName + '</span>';
        } else {
            logoHtml = '<span class="ailogin-logo-fallback">' + cfg.siteName + '</span>';
        }

        var middleHtml = '<div class="ailogin-brand-middle">' +
            '<div class="ailogin-accent-rule"></div>' +
            '<div class="ailogin-slogan">' + cfg.slogan + '</div>' +
            (cfg.subtext ? '<div class="ailogin-subtext">' + cfg.subtext + '</div>' : '') +
            '</div>';

        var footerHtml = '<div class="ailogin-brand-footer">' + cfg.siteName + '</div>';

        /* Animated favicon particles ------------------------------------ */
        var particlesHtml = '';
        if (cfg.faviconUrl) {
            var particles = [
                { x:  8, y: 12, s: 80,  d:  0.0, dur: 12, fdur: 10, op: 0.20 },
                { x: 70, y:  6, s: 110, d:  2.5, dur: 16, fdur: 14, op: 0.14 },
                { x: 20, y: 50, s: 64,  d:  5.0, dur: 14, fdur: 12, op: 0.22 },
                { x: 56, y: 40, s: 96,  d:  1.2, dur: 18, fdur: 16, op: 0.13 },
                { x: 80, y: 66, s: 72,  d:  7.0, dur: 11, fdur:  9, op: 0.18 },
                { x: 36, y: 76, s: 88,  d:  3.8, dur: 15, fdur: 13, op: 0.16 },
                { x: 48, y: 20, s: 56,  d:  9.0, dur: 13, fdur: 11, op: 0.24 },
                { x: 10, y: 68, s: 104, d:  6.2, dur: 17, fdur: 15, op: 0.12 }
            ];
            particles.forEach(function (p) {
                particlesHtml += '<img class="ailogin-particle" src="' + cfg.faviconUrl + '" alt="" ' +
                    'aria-hidden="true" ' +
                    'style="' +
                        'left:' + p.x + '%;' +
                        'top:'  + p.y + '%;' +
                        'width:'  + p.s + 'px;' +
                        'height:' + p.s + 'px;' +
                        '--p-op:'   + p.op   + ';' +
                        '--p-dur:'  + p.dur  + 's;' +
                        '--p-fdur:' + p.fdur + 's;' +
                        'animation-delay:-' + p.d + 's,-' + p.d + 's;' +
                    '">';
            });
        }

        var brand = document.createElement('div');
        brand.id = 'ailogin-brand';
        brand.innerHTML = particlesHtml + logoHtml + middleHtml + footerHtml;

        /* Inject before page-wrapper ------------------------------------ */
        document.body.insertBefore(brand, document.body.firstChild);
        document.body.classList.add('ailogin-active');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
<?php
    return ob_get_clean();
}

/**
 * Returns the <script> block that injects the brand panel DOM element on the login page.
 * Wraps local_ailogin_get_js_iife() in a <script id="ailogin-js"> tag.
 *
 * @return string HTML <script> block.
 */
function local_ailogin_get_js_html(): string {
    return '<script id="ailogin-js">' . "\n" . local_ailogin_get_js_iife() . '</script>' . "\n";
}

/**
 * Writes the plugin's CSS and JS into Moodle's core Additional HTML config keys
 * (additionalhtmlhead and additionalhtmlfooter), using sentinel markers so the
 * block can be updated or removed cleanly without clobbering admin-placed HTML.
 *
 * Called after any action that changes the design: save_settings, generate, toggle,
 * upload_logo, remove_logo, upload_favicon, remove_favicon.
 *
 * Because core_renderer outputs additionalhtmlhead/additionalhtmlfooter unconditionally
 * on every page regardless of hook registration, this approach is guaranteed to work
 * even when the hook machinery is broken or the upgrade hasn't fired yet.
 *
 * The CSS is scoped to body.pagelayout-login.ailogin-active and the JS bails when
 * pagelayout-login is absent, so printing on every page is completely harmless.
 */
function local_ailogin_sync_additionalhtml(): void {
    $enabled = (int)get_config('local_ailogin', 'enabled');

    $css = $enabled ? local_ailogin_get_css_html() : '';
    $js  = $enabled ? local_ailogin_get_js_html()  : '';

    $begin = "\n<!-- ailogin:begin -->\n";
    $end   = "\n<!-- ailogin:end -->\n";
    $strip = '/\n?<!-- ailogin:begin -->.*?<!-- ailogin:end -->\n?/s';

    // HEAD: strip any previous ailogin block, then append fresh CSS block.
    $head = (string)get_config(null, 'additionalhtmlhead');
    $head = preg_replace($strip, '', $head);
    if ($css !== '') {
        $head .= $begin . $css . $end;
    }
    set_config('additionalhtmlhead', $head);   // plugin=null → core (site-level) config

    // FOOTER: same treatment for the JS block.
    $foot = (string)get_config(null, 'additionalhtmlfooter');
    $foot = preg_replace($strip, '', $foot);
    if ($js !== '') {
        $foot .= $begin . $js . $end;
    }
    set_config('additionalhtmlfooter', $foot);

    // Purge all caches so Moodle's theme/config cache serves the fresh values.
    // Additional HTML is cached; without this the old (or empty) block may linger.
    if (function_exists('purge_all_caches')) {
        purge_all_caches();
    } else {
        \cache_helper::purge_all(false);
    }
}

/**
 * HSL helper: converts a hue fraction + p/q to an RGB channel (0.0–1.0).
 */
function local_ailogin_hue2rgb(float $p, float $q, float $t): float {
    if ($t < 0) { $t += 1; }
    if ($t > 1) { $t -= 1; }
    if ($t < 1/6) { return $p + ($q - $p) * 6 * $t; }
    if ($t < 1/2) { return $q; }
    if ($t < 2/3) { return $p + ($q - $p) * (2/3 - $t) * 6; }
    return $p;
}

/**
 * Returns an RGB hex colour (#rrggbb) with the same hue and saturation as
 * the input RGB but with lightness clamped to $targetL (0.0–1.0).
 * Preserving hue/saturation means the output is unmistakably the user's
 * chosen colour — just darker — unlike plain RGB multiplication which
 * desaturates the colour and makes any hue look like dark navy.
 *
 * @param  int   $r        Red   0–255
 * @param  int   $g        Green 0–255
 * @param  int   $b        Blue  0–255
 * @param  float $targetL  Target HSL lightness 0.0–1.0
 * @return string  Hex colour e.g. "#1c6b97"
 */
function local_ailogin_darken_hsl(int $r, int $g, int $b, float $targetL): string {
    // Normalise to 0–1.
    $rn = $r / 255;
    $gn = $g / 255;
    $bn = $b / 255;

    $max = max($rn, $gn, $bn);
    $min = min($rn, $gn, $bn);
    $d   = $max - $min;

    // Compute hue.
    if ($d < 0.0001) {
        $h = 0.0;
        $s = 0.0;
    } else {
        $l = ($max + $min) / 2;
        $s = ($l <= 0.5) ? ($d / ($max + $min)) : ($d / (2 - $max - $min));

        if ($max === $rn) {
            $h = ($gn - $bn) / $d + ($gn < $bn ? 6 : 0);
        } elseif ($max === $gn) {
            $h = ($bn - $rn) / $d + 2;
        } else {
            $h = ($rn - $gn) / $d + 4;
        }
        $h /= 6;
    }

    // Convert HSL (with $targetL) back to RGB.
    $l = (float)$targetL;
    if ($s < 0.0001) {
        // Achromatic — greyscale at target lightness.
        $rv = $gv = $bv = $l;
    } else {
        $q  = ($l < 0.5) ? ($l * (1 + $s)) : ($l + $s - $l * $s);
        $p  = 2 * $l - $q;
        $rv = local_ailogin_hue2rgb($p, $q, $h + 1/3);
        $gv = local_ailogin_hue2rgb($p, $q, $h);
        $bv = local_ailogin_hue2rgb($p, $q, $h - 1/3);
    }

    return sprintf('#%02x%02x%02x',
        (int)round($rv * 255),
        (int)round($gv * 255),
        (int)round($bv * 255)
    );
}

/**
 * Builds default CSS :root variables from a hex primary colour.
 * Used when no AI-generated CSS is stored yet.
 *
 * @param  string $hex  Hex colour e.g. "#2563EB"
 * @return string       CSS variable declarations (without the :root wrapper)
 */
function local_ailogin_build_default_vars(string $hex): string {
    // Parse hex to RGB.
    $hex  = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Darken by ~30% for button/link hover shade.
    $dr = max(0, (int)round($r * 0.7));
    $dg = max(0, (int)round($g * 0.7));
    $db = max(0, (int)round($b * 0.7));
    $darkHex = sprintf('#%02x%02x%02x', $dr, $dg, $db);

    // Brand panel gradient using HSL darkening.
    // HSL preserves hue + saturation — only lightness is reduced.
    // This means the panel is unmistakably the user's chosen colour (just darker),
    // unlike RGB multiplication which desaturates everything into dark navy.
    //
    // Target lightness levels:
    //   L=0.20 (start) — dark, but clearly tinted with the brand hue
    //   L=0.35 (mid)   — medium-dark, clearly the brand colour
    //   L=0.45 (end)   — vivid, essentially the primary colour at readable contrast
    //
    // Example for #2795d2 (sky blue):
    //   RGB 20% approach (old): #081e2a → #17597e  (looks like dark navy — wrong)
    //   HSL L=20%→45% (new):   #103d56 → #2489c2  (clearly sky blue — correct)
    $panelStart = local_ailogin_darken_hsl($r, $g, $b, 0.20);
    $panelMid   = local_ailogin_darken_hsl($r, $g, $b, 0.35);
    $panelEnd   = local_ailogin_darken_hsl($r, $g, $b, 0.45);

    return <<<CSS
    --ailogin-primary: #{$hex};
    --ailogin-primary-dark: {$darkHex};
    --ailogin-r: {$r};
    --ailogin-g: {$g};
    --ailogin-b: {$b};
    --ailogin-primary-alpha: rgba({$r},{$g},{$b},0.18);
    --ailogin-primary-glow: rgba({$r},{$g},{$b},0.28);
    --ailogin-gradient: linear-gradient(160deg, {$panelStart} 0%, {$panelMid} 55%, {$panelEnd} 100%);
    --ailogin-form-bg: #F5F7FA;
    --ailogin-card-bg: #FFFFFF;
CSS;
}
