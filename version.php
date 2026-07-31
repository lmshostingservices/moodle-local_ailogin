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

defined('MOODLE_INTERNAL') || die();

$plugin->component  = 'local_ailogin';
$plugin->version    = 2026071000015;
$plugin->release    = '1.2.1'; // v1.1.5 FIX-UPGRADE-ORDER: Savepoints were in descending order — Moodle requires ascending. Any upgrade from a pre-1.1.4 version caused "Cannot downgrade from 2026070900008 to 2026070900007" because block 008 ran first (writing 008 to DB) then block 007 tried to write a lower number. Reordered all blocks to ascending (oldest first, newest last). Bumped to 2026070900009 so affected sites get a clean re-run. Savepoint 2026070900009. Full audit and rewrite of the AI design prompt. Each style now has a concrete design brief (Vercel/Linear/Stripe reference) with an explicit panel specification forbidding primary-colour panel fills. Added --ailogin-primary-glow to required variables. Token budget 800→1200, temperature 0.7→0.65. Style labels renamed: Obsidian Minimal, Corporate Dark, Executive Warm, Silicon Valley Dark, Modern Professional. Savepoint 2026070900008. Replaced raw curl_init() in local_ailogin_api_post() with Moodle\'s \curl class (lib/filelib.php). Raw cURL ignores $CFG->proxyhost/proxyport settings — on servers that require a proxy or have restricted outbound DNS this caused "Network error: Could not resolve host: aigrader.com.au". Moodle\'s \curl respects proxy config automatically. Improved error hint when DNS resolution fails. Savepoint 2026070900007. Complete visual overhaul. Dark panel (deep navy #060B18→#0F1B35) replaces the flat primary-colour fill. Dot-grid texture (::before) + primary glow orb (::after) replace cheap circles. Particle opacity tripled (0.05-0.09 → 0.12-0.24) so favicon animations are visible. Glowing accent-rule injected above slogan. Slogan font-weight 800 letter-spacing -1.5px. Premium multi-layer card shadow. Clean button shadow. --ailogin-primary-glow CSS variable added. Savepoint 2026070900006. Replaced hook-based injection with core additionalhtmlhead/additionalhtmlfooter approach. Hooks were silently not firing on some Moodle 4.4 installs (Academi/Boost child theme, stale hook registry). New approach: local_ailogin_sync_additionalhtml() writes CSS+JS into core config keys using <!-- ailogin:begin/end --> sentinels; core_renderer outputs these unconditionally on every page regardless of hook registration. Called after every design-changing action (save_settings, generate, toggle, logo/favicon upload/remove). db/uninstall.php added to strip sentinel blocks on removal. Hooks kept as belt-and-suspenders fallback. Savepoint 2026070900005.
                                // FIX-CSS-MISSING-4.3 (v1.0.7): Fixed CSS never injected on Moodle 4.3.1-4.3.x. Root cause: before_footer_html_generation used version cutoff 2023110100 to decide whether to include the CSS fallback, but add_html() on before_standard_head_html_generation was only available from Moodle 4.4.0 (2024042200). On Moodle 4.3.1+ the head hook had no add_html() AND the footer fallback check was false so CSS was never injected anywhere. Fix: always prepend CSS in the footer hook (harmless duplicate on 4.4+). Savepoint 2026070900001.
                                // FIX-HOOKS-AND-CSS (v1.0.5): Moved require_once(lib.php) from file scope into each hook callback() method body. PHP autoloader runs hook class files in its own scope where $CFG is unavailable, silently preventing lib.php from loading. Fixed --ailogin-primary CSS variable missing # prefix. Savepoint 2026070800006.
                                // UPLOAD-LOGO (v1.0.1): Replaced Logo URL text field with file-upload widget. Savepoint 2026070800002.
                                // INITIAL RELEASE (v1.0.0): AI-powered custom login page designer. Savepoint 2026070800001.
$plugin->requires   = 2022041900; // Moodle 4.0
$plugin->maturity   = MATURITY_STABLE;
