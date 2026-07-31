<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade steps for local_ailogin.
 *
 * @package   local_ailogin
 * @copyright 2026 AI Grader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_ailogin_upgrade($oldversion) {

    if ($oldversion < 2026070800001) {
        // INITIAL RELEASE (v1.0.0): No DB schema changes — all settings stored in
        // mdl_config_plugins (plugin config). See lib.php / css.php for details.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'css.php', 'admin.php', 'ajax.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070800001, 'local', 'ailogin');
    }

    // v1.0.1: UPLOAD-LOGO — replaced Logo URL text field with file-upload widget.
    // Added local_ailogin_pluginfile() in lib.php to serve uploaded logos.
    // No DB schema changes.
    if ($oldversion < 2026070800002) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'admin.php', 'ajax.php', 'styles.css', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070800002, 'local', 'ailogin');
    }

    // v1.0.2: Added Purge Caches button to admin page, incognito preview note,
    // and purge_caches AJAX action. No DB schema changes.
    if ($oldversion < 2026070800003) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'admin.php', 'ajax.php', 'styles.css', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070800003, 'local', 'ailogin');
    }

    // v1.0.3: Fixed credential key names — local_aiconfig uses 'siteid'/'apikey'
    // (no underscores), not 'site_id'/'api_key'. This caused "credentials not
    // configured" even when local_aiconfig was correctly set up.
    if ($oldversion < 2026070800004) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['ajax.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070800004, 'local', 'ailogin');
    }

    // v1.0.4: Added optional animated background icon (favicon particles).
    // Uploading a favicon creates 8 faint floating/fading particles on the brand panel.
    // New: ajax actions upload_favicon, remove_favicon; lib.php CSS keyframes + JS particle injection.
    if ($oldversion < 2026070800005) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'admin.php', 'ajax.php', 'styles.css', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070800005, 'local', 'ailogin');
    }

    // v1.0.5: FIX-HOOKS-AND-CSS
    // ROOT CAUSE FIX: require_once(lib.php) moved from file scope into each hook
    // callback() method body. PHP autoloader runs class files in its own function
    // scope where $CFG is unavailable, so the file-level require_once silently
    // failed — lib.php functions were never loaded — CSS/JS never injected on login page.
    // Also fixed: --ailogin-primary CSS var missing # prefix; added write_close()
    // before cURL; fixed format_string() AJAX context exception; dual pagetype/pagelayout check.
    if ($oldversion < 2026070800006) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach ([
                'lib.php',
                'ajax.php',
                'version.php',
                'db/upgrade.php',
                'classes/hook/before_footer_html_generation.php',
                'classes/hook/before_standard_head_html_generation.php',
            ] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070800006, 'local', 'ailogin');
    }

    // v1.0.6: FIX-REQUIRE-LOGIN
    // Replaced require_login() with direct isloggedin()/isguestuser() check.
    // require_login() on Moodle 4.4+/5.x throws "coursehidden" exception in AJAX
    // context, causing ALL AJAX actions (purge_caches, generate, save_settings etc.)
    // to fail with "Course or activity not accessible". Direct login check avoids this.
    if ($oldversion < 2026070800007) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['ajax.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070800007, 'local', 'ailogin');
    }

    // v1.0.7: FIX-CSS-MISSING-4.3
    // Fixed CSS never injected on Moodle 4.3.1–4.3.x.
    // Root cause: before_footer_html_generation used version cutoff 2023110100
    // (Nov 2023) to decide whether to include the CSS fallback, but add_html()
    // on before_standard_head_html_generation was only available from Moodle 4.4.0
    // (2024042200). On Moodle 4.3.1+ the head hook had no add_html() AND the footer
    // fallback check was false — CSS was never injected anywhere, so the two-column
    // layout never appeared. Fix: always prepend CSS in the footer hook regardless
    // of version (harmless duplicate on 4.4+ as both blocks are identical).
    if ($oldversion < 2026070900001) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach ([
                'classes/hook/before_footer_html_generation.php',
                'version.php',
                'db/upgrade.php',
            ] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900001, 'local', 'ailogin');
    }

    // v1.0.8: FIX-ACADEMI-THEME-CHROME
    // Academi theme (and other non-Boost themes) have extra chrome elements that
    // were not hidden by the base CSS: .header-main (top logo/nav bar inside
    // #page-wrapper), .drawer / [data-region="fixed-drawer"] (left sidebar drawer),
    // footer#page-footer, and .toast-wrapper. These remained visible on the login
    // page even after ailogin-active was applied, breaking the two-column layout.
    // Fix: added those selectors to the display:none rule in lib.php get_css_html().
    if ($oldversion < 2026070900002) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach ([
                'lib.php',
                'version.php',
                'db/upgrade.php',
            ] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900002, 'local', 'ailogin');
    }

    // v1.0.9: FIX-HOOK-INJECTION
    // $hook->add_html() can silently fail on some Moodle 4.4 installs when the
    // hook registry is stale, opcache serves old bytecode, or a theme-level hook
    // suppressor is active. Added $PAGE->requires->js_init_code() as a guaranteed
    // parallel injection path. This API is unconditional and has been stable in
    // Moodle for 10+ years — it outputs its payload via $OUTPUT->footer() →
    // $PAGE->requires->get_end_code() which always runs. The JS payload injects
    // CSS into <head> via insertAdjacentHTML and runs the brand-panel IIFE.
    // Idempotency guard in IIFE prevents double-injection if add_html() also works.
    if ($oldversion < 2026070900003) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach ([
                'lib.php',
                'version.php',
                'db/upgrade.php',
                'classes/hook/before_footer_html_generation.php',
                'classes/hook/before_standard_head_html_generation.php',
            ] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900003, 'local', 'ailogin');
    }

    // v1.1.0: CORRECTFIXES
    // (1) Legacy callback cutoff corrected: 2023100900 → 2024042200.
    //     before_footer_html_generation and before_standard_head_html_generation
    //     only dispatch from Moodle 4.4.0 — Moodle 4.3.x had both hooks missing
    //     AND the legacy bail-out firing too early, so no injection happened at all.
    // (2) Added local_ailogin_before_standard_html_head() for 4.0–4.3 CSS in <head>.
    // (3) Fixed echo→return in before_footer(): Moodle concatenates return value.
    // (4) Removed CSS from footer hook add_html() to eliminate triple injection.
    // (5) Sanitised generated_css at storage (ajax.php) and output (lib.php).
    // (6) HTML-escaped slogan/subtext before innerHTML injection.
    // (7) Fixed undefined array key on $result['error'] in ajax.php.
    if ($oldversion < 2026070900004) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach ([
                'lib.php',
                'ajax.php',
                'version.php',
                'db/upgrade.php',
                'classes/hook/before_footer_html_generation.php',
                'classes/hook/before_standard_head_html_generation.php',
            ] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900004, 'local', 'ailogin');
    }

    // v1.1.1: ARCH-ADDITIONALHTML
    // Replaced hook-based injection with core additionalhtmlhead/additionalhtmlfooter approach.
    if ($oldversion < 2026070900005) {
        require_once(__DIR__ . '/../lib.php');
        if (function_exists('local_ailogin_sync_additionalhtml')) {
            local_ailogin_sync_additionalhtml();
        }
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach ([
                'lib.php', 'ajax.php', 'admin.php', 'version.php',
                'db/upgrade.php', 'db/uninstall.php',
                'classes/hook/before_footer_html_generation.php',
                'classes/hook/before_standard_head_html_generation.php',
            ] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900005, 'local', 'ailogin');
    }

    // v1.1.2: PREMIUM-REDESIGN
    // Dark panel, dot-grid texture, glow orb, tripled particle opacity, accent-rule,
    // premium card/button shadows, --ailogin-primary-glow variable.
    if ($oldversion < 2026070900006) {
        require_once(__DIR__ . '/../lib.php');
        if (function_exists('local_ailogin_sync_additionalhtml')) {
            local_ailogin_sync_additionalhtml();
        }
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900006, 'local', 'ailogin');
    }

    // v1.1.3: FIX-CURL-PROXY
    // Replaced raw curl_init() with Moodle's \curl class — respects proxy settings.
    if ($oldversion < 2026070900007) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['ajax.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900007, 'local', 'ailogin');
    }

    // v1.1.4: PREMIUM-AI-PROMPT
    // Full AI prompt rewrite with per-style design briefs, --ailogin-primary-glow added.
    if ($oldversion < 2026070900008) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['admin.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900008, 'local', 'ailogin');
    }

    // v1.1.5: FIX-UPGRADE-ORDER
    // Savepoints were written in descending order (newest first) — Moodle requires
    // ascending order. Reordered all blocks; sync re-runs to heal partial upgrades.
    if ($oldversion < 2026070900009) {
        require_once(__DIR__ . '/../lib.php');
        if (function_exists('local_ailogin_sync_additionalhtml')) {
            local_ailogin_sync_additionalhtml();
        }
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'ajax.php', 'admin.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900009, 'local', 'ailogin');
    }

    // v1.1.6: FIX-PANEL-COLOUR (initial attempt — gradient brightness too dark at 8-10%)
    if ($oldversion < 2026070900010) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900010, 'local', 'ailogin');
    }

    // v1.1.7: FIX-PANEL-COLOUR-BRIGHTNESS
    // v1.1.6 derived the gradient at only 8-10% of primary brightness — at that level
    // any colour looks indistinguishable from dark navy. Increased to 20% / 40% / 60%
    // so the panel clearly shows the user's chosen hue while remaining premium-dark.
    // Example for #2795d2 (sky blue): #081e2a → #103c54 → #17597e (clearly blue).
    if ($oldversion < 2026070900011) {
        require_once(__DIR__ . '/../lib.php');
        if (function_exists('local_ailogin_sync_additionalhtml')) {
            local_ailogin_sync_additionalhtml();
        }
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900011, 'local', 'ailogin');
    }

    // v1.1.8: FIX-PANEL-COLOUR-HSL
    // RGB-multiplication (v1.1.6/v1.1.7) desaturates colour — any hue becomes dark navy.
    // Root cause: multiplying r=39 by 20% = 8, which kills the blue identity of #2795d2.
    // Fix: HSL darkening — preserve hue and saturation, only reduce lightness.
    // L=20%→35%→45% keeps full saturation: #2795d2 → #103d56 → #1c6b97 → #2489c2 (sky blue).
    if ($oldversion < 2026070900012) {
        require_once(__DIR__ . '/../lib.php');
        if (function_exists('local_ailogin_sync_additionalhtml')) {
            local_ailogin_sync_additionalhtml();
        }
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900012, 'local', 'ailogin');
    }

    // v1.1.9: FIX-THEME-BACKGROUND-BLEED
    // Root cause confirmed from live HTML source: Academi theme sets its login
    // background-image on #page (class="container-fluid mt-0"), NOT on #page-wrapper.
    // Our previous CSS only overrode #page-wrapper, so the theme's blue wave
    // background bled through on the right-hand panel.
    // Fix: explicitly clear background + background-image on #page, .login-wrapper,
    // #main, and common Moodle page containers. Also clear body::before/::after
    // pseudo-elements used by some themes for background overlays.
    if ($oldversion < 2026070900013) {
        require_once(__DIR__ . '/../lib.php');
        if (function_exists('local_ailogin_sync_additionalhtml')) {
            local_ailogin_sync_additionalhtml();
        }
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026070900013, 'local', 'ailogin');
    }

    if ($oldversion < 2026071000014) {
        // v1.2.0 — Five genuinely distinct preset login page layouts.
        // Each style (elegant/bold/warm/dark/vibrant) now produces a completely
        // different page design — not just a colour variation — via
        // local_ailogin_get_style_override_css(). Re-sync so the new CSS
        // is written into additionalhtmlhead immediately after upgrade.
        require_once(__DIR__ . '/../lib.php');
        if (function_exists('local_ailogin_sync_additionalhtml')) {
            local_ailogin_sync_additionalhtml();
        }
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026071000014, 'local', 'ailogin');
    }

    if ($oldversion < 2026071000015) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026071000015, 'local', 'ailogin');
    }

    return true;
}