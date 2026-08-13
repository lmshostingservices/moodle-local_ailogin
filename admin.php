<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AI Login Designer — admin settings page.
 *
 * Lets administrators:
 *  1. Enable / disable the custom login page.
 *  2. Set brand colour, slogan, subtext, logo URL, and style preference.
 *  3. Generate a bespoke AI design (costs 25 credits via platform API).
 *  4. Preview the login page in a new tab.
 *
 * @package   local_ailogin
 * @copyright 2026 AI Grader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_ailogin_admin');

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->requires->css('/local/ailogin/styles.css');

// Current settings.
$enabled      = (bool)get_config('local_ailogin', 'enabled');
$primaryColor = get_config('local_ailogin', 'primary_color') ?: '#2563EB';
$slogan       = get_config('local_ailogin', 'slogan')         ?: '';
$subtext      = get_config('local_ailogin', 'subtext')        ?: '';
$logoUrl      = get_config('local_ailogin', 'logo_url')       ?: '';
$faviconUrl   = get_config('local_ailogin', 'favicon_url')    ?: '';
$style        = get_config('local_ailogin', 'design_style')   ?: 'elegant';
$lastGen      = get_config('local_ailogin', 'last_generated');
$hasCss       = !empty(get_config('local_ailogin', 'generated_css'));

$lastGenText  = $lastGen ? userdate((int)$lastGen, get_string('strftimedatetimeshort', 'langconfig')) : '';

$sesskey = sesskey();
$ajaxUrl = new moodle_url('/local/ailogin/ajax.php');
$loginUrl = new moodle_url('/login/index.php');

// ── Self-diagnostics (no SSH needed) ──────────────────────────────────────
$diag_code_version   = 2026071000014;
$diag_db_version     = (int)get_config('local_ailogin', 'version');
$diag_upgrade_ran    = ($diag_db_version >= $diag_code_version);
$diag_moodle_ver     = (int)$CFG->version;
$diag_hooks_support  = ($diag_moodle_ver >= 2024042200); // 4.4.0+
$diag_correct_path   = is_dir($CFG->dirroot . '/local/ailogin')
                       && !is_dir($CFG->dirroot . '/local/local_ailogin');
$diag_wrong_path     = is_dir($CFG->dirroot . '/local/local_ailogin');

// Hook registration is guaranteed when: (a) upgrade ran AND (b) Moodle >= 4.4.
// On 4.3 and below, legacy callbacks are used instead — hooks don't exist.
$diag_hooks_ok       = $diag_upgrade_ran && $diag_hooks_support;

// Check 5: Is our sentinel actually present in core additionalhtmlhead?
// This is the definitive "is injection working" test — no SSH, no page dump needed.
$diag_head_config   = (string)get_config(null, 'additionalhtmlhead');
$diag_sentinel_ok   = (strpos($diag_head_config, '<!-- ailogin:begin -->') !== false);

// Count problems so we can collapse the panel if everything is green.
$diag_problems = (!$diag_upgrade_ran ? 1 : 0)
               + (!$diag_correct_path ? 1 : 0)
               + ($diag_wrong_path    ? 1 : 0)
               + (!$enabled           ? 1 : 0)
               + ($enabled && $diag_upgrade_ran && !$diag_sentinel_ok ? 1 : 0);

$upgradeUrl = new moodle_url('/admin/index.php');
// ──────────────────────────────────────────────────────────────────────────

echo $OUTPUT->header();
?>

<div class="ailogin-admin">

    <!-- ── Page heading ────────────────────────────────────────────────── -->
    <div class="ailogin-admin__header">
        <div class="ailogin-admin__title-row">
            <div>
                <h1 class="ailogin-admin__title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h5"/><path d="M16 3v6"/><path d="M8 21V9"/></svg>
                    AI Login Designer
                </h1>
                <p class="ailogin-admin__subtitle">Transform your Moodle login page into a world-class branded experience.</p>
            </div>
            <div class="ailogin-admin__header-actions">
                <button id="ailogin-purge-btn" class="btn btn-outline-secondary btn-sm" type="button" title="Purge Moodle caches — required after first install or upgrade so hooks take effect">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    <span id="ailogin-purge-text">Purge Caches</span>
                </button>
                <a href="<?php echo $loginUrl->out(false); ?>" target="_blank" class="btn btn-outline-secondary btn-sm" id="ailogin-preview-btn" title="Opens the login page in a new tab. You must be logged out (or use a private/incognito window) to see the custom design.">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    Preview Login Page
                </a>
                <button id="ailogin-toggle-btn" class="btn btn-sm <?php echo $enabled ? 'btn-success' : 'btn-outline-secondary'; ?>" data-enabled="<?php echo $enabled ? '1' : '0'; ?>" type="button">
                    <?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ── Diagnostics panel ────────────────────────────────────────────── -->
    <div class="ailogin-diag <?php echo $diag_problems > 0 ? 'ailogin-diag--warn' : 'ailogin-diag--ok'; ?>" id="ailogin-diag">
        <div class="ailogin-diag__heading">
            <?php if ($diag_problems > 0): ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Plugin Setup — <?php echo $diag_problems; ?> issue<?php echo $diag_problems > 1 ? 's' : ''; ?> detected
            <?php else: ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
            Plugin Setup — all checks passed
            <?php endif; ?>
        </div>
        <div class="ailogin-diag__rows">

            <?php
            // ── Check 1: Install path ──────────────────────────────────────
            if ($diag_wrong_path): ?>
            <div class="ailogin-diag__row ailogin-diag__row--fail">
                <span class="ailogin-diag__icon">✗</span>
                <div>
                    <strong>Wrong install path detected:</strong>
                    Plugin files are in <code>/local/local_ailogin/</code> but Moodle expects them at <code>/local/ailogin/</code>.
                    The folder inside the ZIP must be named <code>ailogin</code> (not <code>local_ailogin</code>).
                    Re-upload the ZIP via Site Admin → Plugins → Install plugins.
                </div>
            </div>
            <?php elseif (!$diag_correct_path): ?>
            <div class="ailogin-diag__row ailogin-diag__row--fail">
                <span class="ailogin-diag__icon">✗</span>
                <div>
                    <strong>Plugin folder not found at expected path.</strong>
                    Expected: <code><?php echo htmlspecialchars($CFG->dirroot); ?>/local/ailogin/</code>.
                    Upload the ZIP via Site Admin → Plugins → Install plugins, or copy the <code>ailogin/</code> folder to <code>/local/</code> manually.
                </div>
            </div>
            <?php else: ?>
            <div class="ailogin-diag__row ailogin-diag__row--pass">
                <span class="ailogin-diag__icon">✓</span>
                <div>Install path correct: <code>/local/ailogin/</code></div>
            </div>
            <?php endif; ?>

            <?php // ── Check 2: Upgrade ran ────────────────────────────────
            if (!$diag_upgrade_ran): ?>
            <div class="ailogin-diag__row ailogin-diag__row--fail">
                <span class="ailogin-diag__icon">✗</span>
                <div>
                    <strong>Upgrade has not run.</strong>
                    DB version: <code><?php echo $diag_db_version ?: '(none)'; ?></code> — requires <code><?php echo $diag_code_version; ?></code>.
                    Moodle only registers hook callbacks after the upgrade runs.
                    <a href="<?php echo $upgradeUrl->out(false); ?>" class="ailogin-diag__action-link">
                        Click here to run the upgrade now →
                    </a>
                    Then <strong>Purge Caches</strong> and reload this page.
                </div>
            </div>
            <?php else: ?>
            <div class="ailogin-diag__row ailogin-diag__row--pass">
                <span class="ailogin-diag__icon">✓</span>
                <div>Upgrade complete — DB version <code><?php echo $diag_db_version; ?></code></div>
            </div>
            <?php endif; ?>

            <?php // ── Check 3: Hook support (Moodle version) ───────────────
            if (!$diag_hooks_support): ?>
            <div class="ailogin-diag__row ailogin-diag__row--warn">
                <span class="ailogin-diag__icon">!</span>
                <div>
                    <strong>Moodle <?php echo sprintf('%.1f', $diag_moodle_ver / 100); ?> detected</strong> (version <code><?php echo $diag_moodle_ver; ?></code>).
                    Hook-based injection requires Moodle 4.4.0+. On this version, the plugin uses legacy callbacks instead — make sure the upgrade has run so they are registered.
                </div>
            </div>
            <?php else: ?>
            <div class="ailogin-diag__row ailogin-diag__row--pass">
                <span class="ailogin-diag__icon">✓</span>
                <div>Moodle <?php echo round($diag_moodle_ver / 10000000, 1); ?> — hook injection supported <?php echo $diag_hooks_ok ? '&amp; hooks registered' : '(hooks will register after upgrade)'; ?></div>
            </div>
            <?php endif; ?>

            <?php // ── Check 4: Enabled ────────────────────────────────────
            if (!$enabled): ?>
            <div class="ailogin-diag__row ailogin-diag__row--fail">
                <span class="ailogin-diag__icon">✗</span>
                <div>
                    <strong>Plugin is disabled.</strong>
                    All callbacks bail out immediately when disabled.
                    Click the <strong>Enabled/Disabled</strong> toggle in the header above, then save settings.
                </div>
            </div>
            <?php else: ?>
            <div class="ailogin-diag__row ailogin-diag__row--pass">
                <span class="ailogin-diag__icon">✓</span>
                <div>Plugin is enabled</div>
            </div>
            <?php endif; ?>

            <?php // ── Check 5: Sentinel in additionalhtmlhead ──────────────
            if ($enabled && $diag_upgrade_ran):
                if ($diag_sentinel_ok): ?>
            <div class="ailogin-diag__row ailogin-diag__row--pass">
                <span class="ailogin-diag__icon">✓</span>
                <div>
                    CSS block present in <strong>Additional HTML head</strong> — injection is active.
                    This is the definitive check: core outputs this unconditionally on every page.
                </div>
            </div>
                <?php else: ?>
            <div class="ailogin-diag__row ailogin-diag__row--fail">
                <span class="ailogin-diag__icon">✗</span>
                <div>
                    <strong>CSS block NOT found in Additional HTML head</strong> — the login page will not be styled.
                    The <code><!-- ailogin:begin --></code> sentinel is missing from
                    <em>Site Admin → Appearance → Additional HTML → Within HEAD</em>.
                    <br>Fix: <strong>open the settings form below, make any change (e.g. toggle Enabled off then on), and click Save</strong>.
                    That runs <code>local_ailogin_sync_additionalhtml()</code> and writes the block immediately.
                    After saving you should see this check turn green without a page reload.
                </div>
            </div>
                <?php endif;
            endif; ?>

        </div>
    </div>
    <!-- ── End diagnostics ─────────────────────────────────────────────── -->

    <div class="ailogin-admin__body">

        <!-- ── Left column: Settings form ──────────────────────────────── -->
        <div class="ailogin-admin__col-main">

            <!-- Status card -->
            <div class="ailogin-card <?php echo $enabled ? 'ailogin-card--active' : 'ailogin-card--inactive'; ?>" id="ailogin-status-card">
                <div class="ailogin-card__icon">
                    <?php if ($enabled): ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php else: ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="ailogin-card__status-text" id="ailogin-status-text">
                        <?php echo $enabled ? 'AI Login is <strong>active</strong> — your custom login page is live.' : 'AI Login is <strong>disabled</strong> — users see the default Moodle login.'; ?>
                    </div>
                    <?php if ($lastGenText): ?>
                    <div class="ailogin-card__meta">Design last generated: <?php echo $lastGenText; ?><?php if (!$hasCss): ?> (default colours active)<?php endif; ?></div>
                    <?php else: ?>
                    <div class="ailogin-card__meta">No AI design generated yet — default brand colours will be applied from your primary colour below.</div>
                    <?php endif; ?>
                    <?php if ($enabled): ?>
                    <div class="ailogin-card__meta" style="margin-top:6px;color:#b45309;">
                        <strong>To preview:</strong> open the login page in a private/incognito window — Moodle redirects logged-in admins to the dashboard.
                        If the custom layout is not showing, click <strong>Purge Caches</strong> above (required after first install or upgrade).
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Settings form -->
            <form id="ailogin-settings-form" class="ailogin-form" novalidate>
                <input type="hidden" name="sesskey" value="<?php echo $sesskey; ?>">
                <input type="hidden" name="action"  value="save_settings">

                <h2 class="ailogin-section-heading">Design Settings</h2>

                <!-- Primary colour + style on same row -->
                <div class="ailogin-form__row">
                    <div class="ailogin-form__group">
                        <label class="ailogin-form__label" for="ailogin-color">Primary Colour</label>
                        <div class="ailogin-color-wrap">
                            <input type="color" id="ailogin-color-picker" class="ailogin-color-picker" value="<?php echo htmlspecialchars($primaryColor); ?>">
                            <input type="text"  id="ailogin-color"        class="form-control ailogin-color-text" name="primary_color"
                                   value="<?php echo htmlspecialchars($primaryColor); ?>" maxlength="7" pattern="^#[0-9a-fA-F]{3,6}$" placeholder="#2563EB">
                        </div>
                        <p class="ailogin-form__hint">Hex code — used for the left brand panel and submit button.</p>
                    </div>

                    <div class="ailogin-form__group">
                        <label class="ailogin-form__label" for="ailogin-style">Design Style</label>
                        <select id="ailogin-style" name="style" class="form-control">
                            <?php
                            $styles = [
                                'elegant'  => 'Obsidian Minimal (Linear / Notion style)',
                                'bold'     => 'Corporate Dark (Stripe / Atlassian style)',
                                'warm'     => 'Executive Warm (Webflow / Framer style)',
                                'dark'     => 'Silicon Valley Dark (Vercel / Figma style)',
                                'vibrant'  => 'Modern Professional (Loom / HubSpot style)',
                            ];
                            foreach ($styles as $val => $label):
                            ?>
                            <option value="<?php echo $val; ?>" <?php echo $style === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="ailogin-form__hint">Tells the AI which premium design language to follow when generating your login page.</p>
                    </div>
                </div>

                <!-- Slogan -->
                <div class="ailogin-form__group">
                    <label class="ailogin-form__label" for="ailogin-slogan">Brand Slogan</label>
                    <input type="text" id="ailogin-slogan" name="slogan" class="form-control"
                           value="<?php echo htmlspecialchars($slogan); ?>"
                           placeholder="More teaching, less writing." maxlength="120">
                    <p class="ailogin-form__hint">The headline shown on the left panel. Keep it short and memorable.</p>
                </div>

                <!-- Subtext -->
                <div class="ailogin-form__group">
                    <label class="ailogin-form__label" for="ailogin-subtext">Supporting Text <span class="ailogin-form__optional">(optional)</span></label>
                    <input type="text" id="ailogin-subtext" name="subtext" class="form-control"
                           value="<?php echo htmlspecialchars($subtext); ?>"
                           placeholder="Sign in to access your courses and resources." maxlength="200">
                </div>

                <!-- Logo Upload -->
                <div class="ailogin-form__group">
                    <label class="ailogin-form__label">Logo <span class="ailogin-form__optional">(optional)</span></label>
                    <input type="hidden" id="ailogin-logo-url" name="logo_url" value="<?php echo htmlspecialchars($logoUrl); ?>">
                    <div class="ailogin-logo-upload" id="ailogin-logo-upload">
                        <div class="ailogin-logo-preview" id="ailogin-logo-preview" <?php echo $logoUrl ? '' : 'style="display:none"'; ?>>
                            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Current logo" class="ailogin-logo-preview__img" id="ailogin-logo-preview-img">
                            <button type="button" class="ailogin-logo-preview__remove" id="ailogin-logo-remove-btn" title="Remove logo">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <label class="ailogin-logo-upload__btn" for="ailogin-logo-file">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Upload Logo
                        </label>
                        <input type="file" id="ailogin-logo-file" accept="image/*" class="ailogin-logo-upload__input">
                        <span id="ailogin-logo-filename" class="ailogin-logo-upload__name"></span>
                    </div>
                    <p class="ailogin-form__hint">PNG, JPG, SVG, or WebP. Leave empty to use the Moodle site logo automatically.</p>
                    <div id="ailogin-logo-upload-msg" class="ailogin-msg" aria-live="polite"></div>
                </div>

                <!-- Favicon / Background Icon Upload -->
                <div class="ailogin-form__group">
                    <label class="ailogin-form__label">Animated Background Icon <span class="ailogin-form__optional">(optional)</span></label>
                    <div class="ailogin-favicon-row">
                        <div class="ailogin-favicon-upload-col">
                            <input type="hidden" id="ailogin-favicon-url" name="favicon_url" value="<?php echo htmlspecialchars($faviconUrl); ?>">
                            <div class="ailogin-logo-upload" id="ailogin-favicon-upload">
                                <div class="ailogin-logo-preview" id="ailogin-favicon-preview" <?php echo $faviconUrl ? '' : 'style="display:none"'; ?>>
                                    <img src="<?php echo htmlspecialchars($faviconUrl); ?>" alt="Current favicon" class="ailogin-logo-preview__img" id="ailogin-favicon-preview-img">
                                    <button type="button" class="ailogin-logo-preview__remove" id="ailogin-favicon-remove-btn" title="Remove icon">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>
                                <label class="ailogin-logo-upload__btn" for="ailogin-favicon-file">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    Upload Icon
                                </label>
                                <input type="file" id="ailogin-favicon-file" accept="image/*,.ico" class="ailogin-logo-upload__input">
                                <span id="ailogin-favicon-filename" class="ailogin-logo-upload__name"></span>
                            </div>
                            <p class="ailogin-form__hint">PNG, ICO, SVG, or WebP recommended.</p>
                            <div id="ailogin-favicon-upload-msg" class="ailogin-msg" aria-live="polite"></div>
                        </div>
                        <div class="ailogin-favicon-desc-col">
                            <div class="ailogin-favicon-desc">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                                <div>
                                    <strong>Animated particle background</strong>
                                    <p>8 faint copies of this icon will gently float, drift, and fade across the brand panel — each on its own path with smooth easing and randomised timing. Subtle enough to stay professional; striking enough to set your login page apart from every other Moodle site.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enable toggle (hidden in form — controlled by button) -->
                <input type="hidden" name="enabled" id="ailogin-enabled-input" value="<?php echo $enabled ? '1' : '0'; ?>">

                <div class="ailogin-form__actions">
                    <button type="submit" class="btn btn-primary" id="ailogin-save-btn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Settings
                    </button>
                    <span id="ailogin-save-msg" class="ailogin-msg" aria-live="polite"></span>
                </div>
            </form>

        </div>

        <!-- ── Right column: AI Generation ─────────────────────────────── -->
        <div class="ailogin-admin__col-side">

            <!-- AI Generate card -->
            <div class="ailogin-generate-card">
                <div class="ailogin-generate-card__header">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                    <h2>Generate with AI</h2>
                </div>

                <p class="ailogin-generate-card__desc">
                    AI analyses your brand colour and style preference, then creates a bespoke, world-class colour palette and visual style — applied instantly.
                </p>

                <div class="ailogin-credit-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    25 AI credits per generation
                </div>

                <button id="ailogin-generate-btn" class="btn btn-primary btn-block ailogin-generate-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    <span id="ailogin-generate-text">Generate Design</span>
                </button>

                <div id="ailogin-generate-result" class="ailogin-generate-result" style="display:none"></div>

                <?php if ($hasCss): ?>
                <div class="ailogin-generate-card__footer">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    AI design active<?php echo $lastGenText ? ' · Generated ' . $lastGenText : ''; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Preview card -->
            <div class="ailogin-preview-card">
                <div class="ailogin-preview-card__screen">
                    <!-- Mini mock-up of the two-column layout -->
                    <div class="ailogin-mock" id="ailogin-mock">
                        <div class="ailogin-mock__brand" id="ailogin-mock-brand" style="background: linear-gradient(145deg, <?php echo htmlspecialchars($primaryColor); ?>cc, <?php echo htmlspecialchars($primaryColor); ?>);">
                            <div class="ailogin-mock__logo"></div>
                            <div class="ailogin-mock__slogan"><?php echo htmlspecialchars($slogan ?: 'Your slogan here'); ?></div>
                        </div>
                        <div class="ailogin-mock__form">
                            <div class="ailogin-mock__card">
                                <div class="ailogin-mock__field"></div>
                                <div class="ailogin-mock__field"></div>
                                <div class="ailogin-mock__btn" id="ailogin-mock-btn" style="background: <?php echo htmlspecialchars($primaryColor); ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="ailogin-preview-card__hint">
                    <a href="<?php echo $loginUrl->out(false); ?>" target="_blank">Open live login page</a> to see the real result.
                </p>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var ajaxUrl = <?php echo json_encode($ajaxUrl->out(false)); ?>;
    var sesskey = <?php echo json_encode($sesskey); ?>;

    function post(action, extra, callback) {
        var params = Object.assign({ action: action, sesskey: sesskey }, extra || {});
        var form = new FormData();
        Object.keys(params).forEach(function (k) { form.append(k, params[k]); });
        fetch(ajaxUrl, { method: 'POST', body: form })
            .then(function (r) { return r.json(); })
            .then(callback)
            .catch(function (e) { callback({ ok: false, error: e.message }); });
    }

    /* ── Live colour preview ─────────────────────────────────────────── */
    var colorPicker = document.getElementById('ailogin-color-picker');
    var colorText   = document.getElementById('ailogin-color');
    var mockBrand   = document.getElementById('ailogin-mock-brand');
    var mockBtn     = document.getElementById('ailogin-mock-btn');
    var sloganInput = document.getElementById('ailogin-slogan');
    var mockSlogan  = document.querySelector('.ailogin-mock__slogan');

    function updatePreview(color) {
        if (mockBrand) {
            mockBrand.style.background = 'linear-gradient(145deg,' + color + 'aa,' + color + ')';
        }
        if (mockBtn) {
            mockBtn.style.background = color;
        }
    }

    if (colorPicker) {
        colorPicker.addEventListener('input', function () {
            colorText.value = this.value;
            updatePreview(this.value);
        });
    }
    if (colorText) {
        colorText.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{3,6}$/.test(this.value)) {
                colorPicker.value = this.value;
                updatePreview(this.value);
            }
        });
    }
    if (sloganInput && mockSlogan) {
        sloganInput.addEventListener('input', function () {
            mockSlogan.textContent = this.value || 'Your slogan here';
        });
    }

    /* ── Purge Moodle caches ─────────────────────────────────────────── */
    var purgeBtn  = document.getElementById('ailogin-purge-btn');
    var purgeText = document.getElementById('ailogin-purge-text');

    if (purgeBtn) {
        purgeBtn.addEventListener('click', function () {
            purgeBtn.disabled = true;
            purgeText.textContent = 'Purging…';
            post('purge_caches', {}, function (res) {
                purgeBtn.disabled = false;
                if (res.ok) {
                    purgeText.textContent = 'Caches Purged!';
                    setTimeout(function () { purgeText.textContent = 'Purge Caches'; }, 3000);
                } else {
                    purgeText.textContent = 'Purge Failed';
                    setTimeout(function () { purgeText.textContent = 'Purge Caches'; }, 3000);
                    alert('Cache purge failed: ' + (res.error || 'Unknown error'));
                }
            });
        });
    }

    /* ── Enable / Disable toggle ─────────────────────────────────────── */
    var toggleBtn   = document.getElementById('ailogin-toggle-btn');
    var enabledInput = document.getElementById('ailogin-enabled-input');
    var statusCard  = document.getElementById('ailogin-status-card');
    var statusText  = document.getElementById('ailogin-status-text');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            post('toggle', {}, function (res) {
                if (!res.ok) { alert('Error: ' + res.error); return; }
                var on = res.enabled;
                toggleBtn.textContent = on ? 'Enabled' : 'Disabled';
                toggleBtn.className = 'btn btn-sm ' + (on ? 'btn-success' : 'btn-outline-secondary');
                toggleBtn.dataset.enabled = on ? '1' : '0';
                enabledInput.value = on ? '1' : '0';
                statusCard.className = 'ailogin-card ' + (on ? 'ailogin-card--active' : 'ailogin-card--inactive');
                statusText.innerHTML = on
                    ? 'AI Login is <strong>active</strong> — your custom login page is live.'
                    : 'AI Login is <strong>disabled</strong> — users see the default Moodle login.';
            });
        });
    }

    /* ── Logo upload widget ──────────────────────────────────────────── */
    var logoFileInput  = document.getElementById('ailogin-logo-file');
    var logoUrlInput   = document.getElementById('ailogin-logo-url');
    var logoPreview    = document.getElementById('ailogin-logo-preview');
    var logoPreviewImg = document.getElementById('ailogin-logo-preview-img');
    var logoRemoveBtn  = document.getElementById('ailogin-logo-remove-btn');
    var logoFilename   = document.getElementById('ailogin-logo-filename');
    var logoUploadMsg  = document.getElementById('ailogin-logo-upload-msg');

    if (logoFileInput) {
        logoFileInput.addEventListener('change', function () {
            var f = this.files && this.files[0];
            if (!f) return;
            logoFilename.textContent = f.name;
            // Show local preview immediately.
            var reader = new FileReader();
            reader.onload = function (e) {
                logoPreviewImg.src = e.target.result;
                logoPreview.style.display = '';
            };
            reader.readAsDataURL(f);
        });
    }

    if (logoRemoveBtn) {
        logoRemoveBtn.addEventListener('click', function () {
            logoUploadMsg.textContent = 'Removing…';
            logoUploadMsg.className = 'ailogin-msg';
            post('remove_logo', {}, function (res) {
                if (res.ok) {
                    logoUrlInput.value = '';
                    logoPreviewImg.src = '';
                    logoPreview.style.display = 'none';
                    logoFilename.textContent = '';
                    if (logoFileInput) { logoFileInput.value = ''; }
                    logoUploadMsg.textContent = 'Logo removed.';
                    logoUploadMsg.className = 'ailogin-msg ailogin-msg--ok';
                    setTimeout(function () { logoUploadMsg.textContent = ''; }, 3000);
                } else {
                    logoUploadMsg.textContent = res.error || 'Remove failed.';
                    logoUploadMsg.className = 'ailogin-msg ailogin-msg--err';
                }
            });
        });
    }

    /* ── Favicon / background-icon upload widget ─────────────────────── */
    var faviconFileInput  = document.getElementById('ailogin-favicon-file');
    var faviconUrlInput   = document.getElementById('ailogin-favicon-url');
    var faviconPreview    = document.getElementById('ailogin-favicon-preview');
    var faviconPreviewImg = document.getElementById('ailogin-favicon-preview-img');
    var faviconRemoveBtn  = document.getElementById('ailogin-favicon-remove-btn');
    var faviconFilename   = document.getElementById('ailogin-favicon-filename');
    var faviconUploadMsg  = document.getElementById('ailogin-favicon-upload-msg');

    if (faviconFileInput) {
        faviconFileInput.addEventListener('change', function () {
            var f = this.files[0];
            if (!f) { return; }
            faviconFilename.textContent = f.name;
            var reader = new FileReader();
            reader.onload = function (e) {
                faviconPreviewImg.src = e.target.result;
                faviconPreview.style.display = '';
            };
            reader.readAsDataURL(f);
        });
    }

    if (faviconRemoveBtn) {
        faviconRemoveBtn.addEventListener('click', function () {
            faviconUploadMsg.textContent = 'Removing…';
            faviconUploadMsg.className = 'ailogin-msg';
            post('remove_favicon', {}, function (res) {
                if (res.ok) {
                    faviconUrlInput.value = '';
                    faviconPreviewImg.src = '';
                    faviconPreview.style.display = 'none';
                    faviconFilename.textContent = '';
                    if (faviconFileInput) { faviconFileInput.value = ''; }
                    faviconUploadMsg.textContent = 'Icon removed.';
                    faviconUploadMsg.className = 'ailogin-msg ailogin-msg--ok';
                    setTimeout(function () { faviconUploadMsg.textContent = ''; }, 3000);
                } else {
                    faviconUploadMsg.textContent = res.error || 'Remove failed.';
                    faviconUploadMsg.className = 'ailogin-msg ailogin-msg--err';
                }
            });
        });
    }

    /* ── Save settings form ──────────────────────────────────────────── */
    var settingsForm = document.getElementById('ailogin-settings-form');
    var saveBtn      = document.getElementById('ailogin-save-btn');
    var saveMsg      = document.getElementById('ailogin-save-msg');

    function doSaveSettings() {
        var data = {};
        new FormData(settingsForm).forEach(function (v, k) { data[k] = v; });
        post('save_settings', data, function (res) {
            saveBtn.disabled = false;
            if (res.ok) {
                saveMsg.textContent = 'Saved!';
                saveMsg.className = 'ailogin-msg ailogin-msg--ok';
                setTimeout(function () { saveMsg.textContent = ''; }, 3000);
            } else {
                saveMsg.textContent = res.error || 'Save failed.';
                saveMsg.className = 'ailogin-msg ailogin-msg--err';
            }
        });
    }

    // Returns a Promise that uploads a file and updates the URL hidden input.
    function uploadFileIfNeeded(action, fileKey, fileInput, urlInput, previewImg, previewEl, filenameEl, label) {
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            return Promise.resolve();
        }
        saveMsg.textContent = 'Uploading ' + label + '…';
        var fd = new FormData();
        fd.append('action',  action);
        fd.append('sesskey', sesskey);
        fd.append(fileKey,   fileInput.files[0]);
        return fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) { throw new Error(res.error || label + ' upload failed.'); }
                urlInput.value = res.url;
                if (previewImg) { previewImg.src = res.url; }
                if (previewEl)  { previewEl.style.display = ''; }
                fileInput.value = '';
                if (filenameEl) { filenameEl.textContent = ''; }
            });
    }

    if (settingsForm) {
        settingsForm.addEventListener('submit', function (e) {
            e.preventDefault();
            saveBtn.disabled = true;
            saveMsg.textContent = 'Saving…';

            // Upload logo and favicon (if new files chosen), then save.
            uploadFileIfNeeded('upload_logo',    'logo_file',    logoFileInput,    logoUrlInput,    logoPreviewImg,    logoPreview,    logoFilename,    'logo')
                .then(function () {
                    return uploadFileIfNeeded('upload_favicon', 'favicon_file', faviconFileInput, faviconUrlInput, faviconPreviewImg, faviconPreview, faviconFilename, 'icon');
                })
                .then(function () {
                    saveMsg.textContent = 'Saving…';
                    doSaveSettings();
                })
                .catch(function (err) {
                    saveBtn.disabled = false;
                    saveMsg.textContent = err.message || 'Upload failed.';
                    saveMsg.className = 'ailogin-msg ailogin-msg--err';
                });
        });
    }

    /* ── AI Generate button ──────────────────────────────────────────── */
    var generateBtn  = document.getElementById('ailogin-generate-btn');
    var generateText = document.getElementById('ailogin-generate-text');
    var generateResult = document.getElementById('ailogin-generate-result');

    if (generateBtn) {
        generateBtn.addEventListener('click', function () {
            if (!confirm('Generate a new AI design? This will cost 25 AI credits and replace the current design.')) {
                return;
            }
            generateBtn.disabled = true;
            generateText.textContent = 'Generating…';
            generateResult.style.display = 'none';

            post('generate', {}, function (res) {
                generateBtn.disabled = false;
                generateText.textContent = 'Generate Design';
                generateResult.style.display = 'block';
                if (res.ok) {
                    generateResult.className = 'ailogin-generate-result ailogin-generate-result--ok';
                    generateResult.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> ' +
                        (res.message || 'New design applied!') +
                        (res.creditsRemaining != null ? ' <span class="ailogin-credits-remaining">(' + res.creditsRemaining + ' credits remaining)</span>' : '') +
                        (res.rationale ? '<div class="ailogin-rationale">' + res.rationale + '</div>' : '');
                    // Reload page to show new design in mock
                    setTimeout(function () { window.location.reload(); }, 2000);
                } else {
                    generateResult.className = 'ailogin-generate-result ailogin-generate-result--err';
                    generateResult.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> ' +
                        (res.error || 'Generation failed. Please try again.');
                }
            });
        });
    }
})();
</script>

<?php
echo $OUTPUT->footer();
