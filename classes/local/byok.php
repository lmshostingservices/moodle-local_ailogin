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

namespace local_ailogin\local;

/**
 * Bring your own AI: design the login page on this site's own AI instead of LMS Labs credits.
 *
 * A port of the LMS Labs /api/ai/login-design service: the same style briefs, prompts, token budget,
 * temperature and clean-up of the answer, run through the shared engine in AI Grader Central Config
 * (local_aiconfig 1.1.0+). The site name, slogan and colour go from this site straight to the site's AI
 * provider; nothing is sent to LMS Labs and no credits are spent. The prompts are generated from the
 * service source and checked word for word against it.
 *
 * @package    local_ailogin
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class byok {
    /** This plugin, as named in the licence's products list. */
    const COMPONENT = 'local_ailogin';

    /** @var callable|null Test hook: replaces the AI call with fn(system, user, maxtokens, temperature): string. */
    public static $aicall = null;

    /**
     * Should this site design its login page on its own AI?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return class_exists('\local_aiconfig\byok\ai')
            && \local_aiconfig\byok\ai::uses_own_ai(self::COMPONENT, 'text');
    }

    /**
     * Port of POST /api/ai/login-design.
     *
     * @param array $p primaryColor, style, slogan, siteName.
     * @return array ok, css, rationale | ok false, error.
     */
    public static function design(array $p): array {
        \core_php_time_limit::raise(300);
        $primaryColor = array_key_exists('primaryColor', $p) && $p['primaryColor'] !== null ? $p['primaryColor'] : '#2563EB';
        $style = array_key_exists('style', $p) && $p['style'] !== null ? $p['style'] : 'elegant';
        $slogan = array_key_exists('slogan', $p) && $p['slogan'] !== null ? $p['slogan'] : '';
        $siteName = array_key_exists('siteName', $p) && $p['siteName'] !== null ? $p['siteName'] : '';
        if (!is_string($primaryColor) || !preg_match('/^#[0-9a-fA-F]{3,6}$/', $primaryColor)
                || !in_array($style, ['elegant', 'bold', 'warm', 'dark', 'vibrant'], true)
                || !is_string($slogan) || self::js_len($slogan) > 150 || !is_string($siteName) || self::js_len($siteName) > 100) {
            return ['ok' => false, 'error' => get_string('error_generate', 'local_ailogin')];
        }
        [$systemMessage, $userMessage] = self::prompts($primaryColor, $style, $slogan, $siteName);
        try {
            $cssRaw = self::ai($systemMessage, $userMessage, 1200, 0.65);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => self::message($e)];
        }
        $css = self::js_trim(preg_replace('/\n?```$/i', '', preg_replace('/^```[a-z]*\n?/i', '', $cssRaw, 1), 1));
        $rationale = strtoupper(substr($style, 0, 1)) . substr($style, 1) . ' palette based on ' . $primaryColor
            . ' — gradient and accent tones derived for maximum contrast and visual harmony.';
        return ['ok' => true, 'css' => $css, 'rationale' => $rationale, 'byok' => true];
    }

    /**
     * The service's prompts.
     *
     * @param string $primaryColor Colour.
     * @param string $style Style.
     * @param string $slogan Slogan.
     * @param string $siteName Site name.
     * @return string[] [system, user]
     */
    public static function prompts(string $primaryColor, string $style, string $slogan, string $siteName): array {
        $styleConfigs = [
            'elegant' => ['brief' => 'Obsidian Minimal — editorial restraint in the tradition of Linear, Notion, and Apple. The left panel is deep charcoal-to-midnight, almost pure black with a faint cool-blue undertone. A single razor-thin glowing accent rule is the only colour flourish. Typography is everything: the slogan is crisp white on near-black. Maximum breathing room. Nothing extraneous.', 'panelSpec' => ('--ailogin-gradient must be a near-black cool-dark gradient: e.g. linear-gradient(160deg, #07090F 0%, #0C1020 55%, #101828 100%). The primary colour appears ONLY as the accent rule glow and button fill — never as a panel background tone.')],
            'bold' => ['brief' => 'Corporate Dark — authoritative and decisive, like Stripe\'s enterprise dashboard or Atlassian\'s admin interface. Very dark navy panel, bold 900-weight slogan, primary colour as a single high-contrast piercing accent. Trusted by global organisations. Confident. No ambiguity.', 'panelSpec' => ('--ailogin-gradient must be deep navy: e.g. linear-gradient(160deg, #050C18 0%, #0A1628 55%, #0D1C38 100%). Primary used only for accent rule, button fill, and focus rings. Panel must read as authoritative dark navy — not teal, not purple.')],
            'warm' => ['brief' => 'Executive Warmth — premium human warmth in the tradition of Webflow and Framer. Rich dark panel with a warm brown-black undertone (NOT blue-black — think aged espresso, not midnight navy). Amber and terracotta accent tones derived from the primary. Feels aspirational, trusted, and human. Used by leading professional-services brands.', 'panelSpec' => ('--ailogin-gradient must be warm near-black: e.g. linear-gradient(160deg, #0D0A07 0%, #181008 55%, #1E1510 100%). The gradient must have a warm brown/amber undertone — no cool blue. Primary-derived glow should feel warm and golden, not cold.')],
            'dark' => ['brief' => 'Silicon Valley Obsidian — the apex of SaaS design. Vercel, Linear, Figma at their most refined. Pure black-to-near-black panel with a faint mesh quality, primary colour blazing through the darkness like a neon accent. Maximum contrast slogan. Form panel crisp white. Nothing muddy, nothing soft — every edge is intentional.', 'panelSpec' => ('--ailogin-gradient must be near-pure-black: e.g. linear-gradient(160deg, #030305 0%, #06060A 55%, #09090F 100%). Primary accent must be full-intensity and high contrast against this dark. --ailogin-form-bg: #FFFFFF (pure white — maximum contrast with the dark panel). --ailogin-primary-glow must be strong: rgba(R,G,B,0.30).')],
            'vibrant' => ['brief' => 'Modern Professional — contemporary SaaS confidence like Loom, Intercom, or HubSpot. Deep cool-dark panel with vivid saturated accent energy. A gradient button (primary to primary-dark) adds dynamism. Professional but unmistakably current. Used by fast-growing scale-ups who want to signal momentum and credibility.', 'panelSpec' => ('--ailogin-gradient must be deep cool-dark: e.g. linear-gradient(160deg, #080B14 0%, #0E1422 55%, #121B30 100%). Primary accent is vivid and saturated. Use a gradient on the .ailogin-accent-rule (primary to a 15° shifted analogous hue) via an override rule after :root.')],
        ];
        $styleCfg = $styleConfigs[$style] ?? $styleConfigs['elegant'];
        $hexLong = str_pad(preg_replace('/^#/', '', $primaryColor), 6, '0');
        $r = hexdec(substr($hexLong, 0, 2));
        $g = hexdec(substr($hexLong, 2, 2));
        $b = hexdec(substr($hexLong, 4, 2));
        $systemMessage = ('You are an elite product designer and CSS engineer at a world-class SaaS company (Vercel / Linear / Stripe calibre). You specialise in authentication UX and login screen design for professional enterprise software.

INVIOLABLE RULES — breaking any rule means automatic rejection:
1. The left panel gradient (--ailogin-gradient) MUST be a near-black or very dark colour. NEVER derive it from the primary colour. Primary colour is used ONLY for micro-accents: button fill, focus rings, accent rule glow. A primary-coloured panel background is the single most common amateur mistake — it will always be rejected.
2. All panel gradients must have at least two meaningful tonal stops (e.g. #07090F → #101828) — not a single flat colour.
3. --ailogin-primary-alpha must be rgba(' . self::js_str($r) . ',' . self::js_str($g) . ',' . self::js_str($b) . ',0.15) — use the EXACT decimal RGB values provided.
4. --ailogin-primary-glow must be rgba(' . self::js_str($r) . ',' . self::js_str($g) . ',' . self::js_str($b) . ',0.25) — used for the ambient glow orb and accent rule. Increase to 0.30 for dark/vibrant styles.
5. After the :root block you MAY add CSS overrides for #ailogin-brand, .ailogin-accent-rule, or #ailogin-brand::after — but ONLY subtle, tasteful additions (no external images, no clip-path, no filter: blur on the panel itself).
6. Output ONLY raw CSS. No markdown code fences. CSS comments /* */ are fine. No prose explanations.');
        $userMessage = ('Generate a world-class Moodle login page colour scheme.

── SITE ─────────────────────────────────────────────
Name:     ' . self::js_str(self::js_or($siteName, 'RTO Learning Platform')) . '
Slogan:   "' . self::js_str(self::js_or($slogan, 'Welcome back.')) . '"
Primary:  ' . self::js_str($primaryColor) . '  (R:' . self::js_str($r) . ' G:' . self::js_str($g) . ' B:' . self::js_str($b) . ')

── STYLE DIRECTION ──────────────────────────────────
' . self::js_str(mb_strtoupper(self::js_str($style))) . ': ' . self::js_str(self::js_get($styleCfg, 'brief')) . '

Panel rule: ' . self::js_str(self::js_get($styleCfg, 'panelSpec')) . '

── REQUIRED CSS CUSTOM PROPERTIES ───────────────────
Output :root { } containing ALL of the following:

--ailogin-primary        ' . self::js_str($primaryColor) . ' (buttons, focus rings, accent rule)
--ailogin-primary-dark   primary darkened 25–35% (hover states, gradient terminus)
--ailogin-primary-alpha  rgba(' . self::js_str($r) . ',' . self::js_str($g) . ',' . self::js_str($b) . ',0.15) (focus ring soft glow — exact values)
--ailogin-primary-glow   rgba(' . self::js_str($r) . ',' . self::js_str($g) . ',' . self::js_str($b) . ',0.25) (ambient orb + accent rule shadow)
--ailogin-gradient       dark panel gradient — MUST follow panel rule above, NOT primary-colour-based
--ailogin-form-bg        right panel background (light: #F4F6FA, dark style: #FFFFFF)
--ailogin-card-bg        login card background (#FFFFFF unless design demands otherwise)

── QUALITY BAR ──────────────────────────────────────
Ask yourself: would a designer at Vercel or Linear ship this?
• Left panel = depth and atmosphere — multiple subtle tonal layers, not a flat wash
• Primary colour = a scalpel not a paintbrush — tiny doses, maximum impact
• The slogan text will be white — ensure the panel provides maximum contrast for it
• The form panel and card should feel clean, spacious, and trustworthy

Start output with: :root {');
        return [$systemMessage, $userMessage];
    }

    /**
     * Call the site's own AI.
     *
     * @param string $system System prompt.
     * @param string $user User prompt.
     * @param int $maxtokens Output budget.
     * @param float $temperature Temperature.
     * @return string
     */
    private static function ai(string $system, string $user, int $maxtokens, float $temperature): string {
        if (self::$aicall) {
            return (string) call_user_func(self::$aicall, $system, $user, $maxtokens, $temperature);
        }
        return \local_aiconfig\byok\ai::text(self::COMPONENT, $system, $user, [
            'maxtokens' => $maxtokens, 'temperature' => $temperature,
        ]);
    }

    /**
     * A readable message for a failed AI call.
     *
     * @param \Throwable $e The failure.
     * @return string
     */
    private static function message(\Throwable $e): string {
        if ($e instanceof \moodle_exception && $e->errorcode === 'byok_notavailable') {
            return get_string('byok_notavailable', 'local_ailogin');
        }
        return get_string('byok_failed', 'local_ailogin', s($e->getMessage()));
    }

    /**
     * JavaScript String.trim(): also trims Unicode spaces.
     *
     * @param mixed $s Text.
     * @return string
     */
    private static function js_trim($s): string {
        return preg_replace('/^[\s\x{00A0}\x{FEFF}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+'
            . '|[\s\x{00A0}\x{FEFF}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+$/u', '', self::js_str($s));
    }

    // JavaScript semantics used by the ported prompt templates (generated with the port).

    /** @param mixed $v @return bool JavaScript truthiness. */
    private static function js_truthy($v): bool {
        if (is_array($v) || is_object($v)) {
            return true;
        }
        if (is_float($v) && is_nan($v)) {
            return false;
        }
        return $v !== null && $v !== false && $v !== '' && $v !== 0 && $v !== 0.0;
    }

    /** @param mixed $a @param mixed $b @return mixed a || b */
    private static function js_or($a, $b) {
        return self::js_truthy($a) ? $a : $b;
    }

    /** @param mixed $a @param mixed $b @return mixed a && b */
    private static function js_and($a, $b) {
        return self::js_truthy($a) ? $b : $a;
    }

    /** @param mixed $a @param mixed $b @return mixed a + b */
    private static function js_add($a, $b) {
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return $a + $b;
        }
        return self::js_str($a) . self::js_str($b);
    }

    /** @param mixed $v @return string String(v) */
    private static function js_str($v): string {
        if ($v === null) {
            return 'null';
        }
        if ($v === true) {
            return 'true';
        }
        if ($v === false) {
            return 'false';
        }
        if (is_float($v)) {
            if (floor($v) == $v && abs($v) < 1e15) {
                return (string) (int) $v;
            }
            return rtrim(rtrim(sprintf('%.15F', $v), '0'), '.');
        }
        if (is_array($v)) {
            if ($v === [] || array_keys($v) === range(0, count($v) - 1)) {
                return implode(',', array_map(function($x) {
                    return $x === null ? '' : self::js_str($x);
                }, $v));
            }
            return '[object Object]';
        }
        return (string) $v;
    }

    /** @param mixed $v @return float Number(v) */
    private static function js_num($v) {
        if (is_int($v) || is_float($v)) {
            return $v;
        }
        if ($v === null || $v === false || (is_string($v) && trim($v) === '')) {
            return 0;
        }
        if ($v === true) {
            return 1;
        }
        return is_numeric(trim((string) $v)) ? trim((string) $v) + 0 : NAN;
    }

    /** @param mixed $o @param mixed $k @return mixed o[k] or null */
    private static function js_get($o, $k) {
        if (is_array($o)) {
            return $o[$k] ?? null;
        }
        if (is_string($o) && is_int($k)) {
            return mb_substr($o, $k, 1);
        }
        return null;
    }

    /** @param mixed $v @return int .length */
    private static function js_len($v): int {
        if (is_array($v)) {
            return count($v);
        }
        return strlen(mb_convert_encoding(self::js_str($v), 'UTF-16LE', 'UTF-8')) / 2;
    }

    /** @param mixed $h @param mixed $n @return bool .includes() */
    private static function js_includes($h, $n): bool {
        if (is_array($h)) {
            return in_array($n, $h, true);
        }
        return strpos(self::js_str($h), self::js_str($n)) !== false;
    }

    /** @param mixed $v @param int $a @param int|null $b @return mixed .slice() on strings (UTF-16) and arrays */
    private static function js_slice($v, $a = 0, $b = null) {
        if (is_array($v)) {
            $n = count($v);
            $a = $a < 0 ? max(0, $n + $a) : min($a, $n);
            $b = $b === null ? $n : ($b < 0 ? max(0, $n + $b) : min($b, $n));
            return array_slice($v, $a, max(0, $b - $a));
        }
        $u = mb_convert_encoding(self::js_str($v), 'UTF-16LE', 'UTF-8');
        $n = strlen($u) / 2;
        $a = $a < 0 ? max(0, $n + $a) : min($a, $n);
        $b = $b === null ? $n : ($b < 0 ? max(0, $n + $b) : min($b, $n));
        return mb_convert_encoding(substr($u, $a * 2, max(0, $b - $a) * 2), 'UTF-8', 'UTF-16LE');
    }

    /** @param mixed $v @param callable $f @return array .map() */
    private static function js_map($v, callable $f): array {
        $out = [];
        foreach (array_values((array) $v) as $i => $x) {
            $out[] = $f($x, $i);
        }
        return $out;
    }

    /** @param mixed $v @param callable $f @return array .filter() */
    private static function js_filter($v, callable $f): array {
        $out = [];
        foreach (array_values((array) $v) as $i => $x) {
            if (self::js_truthy($f($x, $i))) {
                $out[] = $x;
            }
        }
        return $out;
    }

    /** @param mixed $v @return string typeof */
    private static function js_typeof($v): string {
        if (is_string($v)) {
            return 'string';
        }
        if (is_int($v) || is_float($v)) {
            return 'number';
        }
        if (is_bool($v)) {
            return 'boolean';
        }
        return $v === null ? 'undefined' : 'object';
    }

    /** @param mixed $s @param string $a @param string $b @return string .replace(string, string) */
    private static function js_replace_first($s, $a, $b): string {
        $s = self::js_str($s);
        $p = strpos($s, $a);
        return $p === false ? $s : substr_replace($s, $b, $p, strlen($a));
    }
}
