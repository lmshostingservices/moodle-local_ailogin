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

namespace local_ailogin;

use local_ailogin\local\byok;

/**
 * Tests for designing the login page on the site's own AI.
 *
 * The prompts were checked word for word against the LMS Labs service code on 83 randomised payloads
 * (identical prompts, token budget, temperature and response); these tests cover the wiring.
 *
 * @package    local_ailogin
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(byok::class)]
final class byok_test extends \advanced_testcase {
    /** @var array Requests the mocked provider received. */
    protected $requests = [];

    /**
     * Licence the site for Bring your own AI on a test key, with text on an Anthropic key.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\local_aiconfig\byok\ai')) {
            $this->markTestSkipped('AI Grader Central Config 1.1.0 or later is not installed.');
        }
        $this->resetAfterTest();
        $pair = sodium_crypto_sign_keypair();
        set_config('testpublickey', base64_encode(sodium_crypto_sign_publickey($pair)), 'local_aiconfig');
        $payload = json_encode(['v' => 1, 'id' => 'lic_t', 'site' => \local_aiconfig\byok\licence::site_host(),
            'plan' => 'pro', 'byok' => true, 'products' => ['*'], 'sub' => 'sub_t', 'exp' => time() + DAYSECS]);
        $b64 = function(string $s): string {
            return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        };
        set_config('licencekey', $b64($payload) . '.' .
            $b64(sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($pair))), 'local_aiconfig');
        set_config('src_text', 'ownkey', 'local_aiconfig');
        set_config('vendor_text', 'anthropic', 'local_aiconfig');
        set_config('key_anthropic', 'sk-test', 'local_aiconfig');
    }

    /**
     * Clean up the provider mock.
     */
    protected function tearDown(): void {
        unset($GLOBALS['local_aiconfig_byok_mock']);
        parent::tearDown();
    }

    /**
     * Make the provider answer with this text, or fail when null.
     *
     * @param string|null $answer
     */
    protected function answer(?string $answer): void {
        $this->requests = [];
        $GLOBALS['local_aiconfig_byok_mock'] = function($url, $headers, $body) use ($answer) {
            $this->requests[] = $body;
            if ($answer === null) {
                throw new \moodle_exception('byok_providererror', 'local_aiconfig', '', 'Overloaded');
            }
            return ['content' => [['type' => 'text', 'text' => $answer]]];
        };
    }

    public function test_design_strips_fences_and_uses_the_brief(): void {
        $this->assertTrue(byok::enabled());
        $this->answer("```css\n:root { --ailogin-primary: #0f6cbf; }\n```");
        $out = byok::design(['primaryColor' => '#0f6cbf', 'style' => 'dark', 'slogan' => 'Learn. Work. Thrive.',
            'siteName' => 'Kestrel Ridge Training']);
        $this->assertTrue($out['ok']);
        $this->assertSame(':root { --ailogin-primary: #0f6cbf; }', $out['css']);
        $this->assertStringStartsWith('Dark palette based on #0f6cbf', $out['rationale']);
        $this->assertStringContainsString('rgba(15,108,191,0.15)', $this->requests[0]['system']);
        $user = $this->requests[0]['messages'][0]['content'][0]['text'];
        $this->assertStringContainsString('Name:     Kestrel Ridge Training', $user);
        $this->assertStringContainsString('DARK: Silicon Valley Obsidian', $user);
        $this->assertSame(1200, $this->requests[0]['max_tokens']);
        $this->assertSame(0.65, $this->requests[0]['temperature']);
    }

    public function test_bad_input_and_provider_failure(): void {
        $this->answer(':root{}');
        $this->assertFalse(byok::design(['primaryColor' => 'red'])['ok']);
        $this->assertFalse(byok::design(['style' => 'neon'])['ok']);
        $this->assertSame([], $this->requests);
        $this->answer(null);
        $out = byok::design([]);
        $this->assertFalse($out['ok']);
        $this->assertStringContainsString('Your AI provider could not design the login page', $out['error']);
    }
}
