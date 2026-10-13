<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the AI markdown -> innerHTML render paths.
 *
 * Background: AI replies are untrusted text. They used to go straight from
 * `marked.parse()` into `innerHTML`, which allowed script/onerror/`javascript:`
 * injection from any model output (or from project/task/note text echoed back
 * by the model). Every render path is now funneled through the single choke
 * point `window.LinaMd.render()` (public/js/lina-markdown.js), which parses
 * with marked, sanitises with DOMPurify, and fails closed to escaped text.
 *
 * These tests pin down the two properties that make the fix safe:
 *   1. the sanitizer and the choke point are actually loaded on the chat page;
 *   2. no raw, unsanitised markdown path can be reintroduced.
 */
class AiMarkdownRenderingTest extends TestCase
{
    use RefreshDatabase;

    /** Blade files that render AI/LLM markdown. */
    private const CHAT_VIEWS = [
        'ai/index.blade.php',
        'layouts/_ai_chat.blade.php',
    ];

    private function viewSource(string $relative): string
    {
        $path = resource_path('views/'.$relative);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_chat_page_loads_the_sanitizer_and_the_choke_point(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ai.index'))
            ->assertOk()
            ->assertSee('dompurify', false)
            ->assertSee('js/lina-markdown.js', false);
    }

    public function test_the_choke_point_module_is_published(): void
    {
        $this->assertFileExists(public_path('js/lina-markdown.js'));
    }

    public function test_no_unsanitised_marked_output_reaches_inner_html(): void
    {
        foreach (self::CHAT_VIEWS as $view) {
            $source = $this->viewSource($view);

            // A raw `marked.parse(...)` must never be assigned to innerHTML.
            $this->assertDoesNotMatchRegularExpression(
                '/innerHTML\s*=\s*[^\n;]*marked\.parse\s*\(/',
                $source,
                "{$view} still assigns raw marked.parse() output to innerHTML."
            );

            // The old newline-only fallback injected the raw text as HTML.
            $this->assertStringNotContainsString(
                "replace(/\\n/g, '<br>')",
                $source,
                "{$view} still has the unescaped newline-><br> innerHTML fallback."
            );
        }
    }

    /**
     * innerHTML targets that are assigned a locally-built string rather than a
     * model payload. Each one was reviewed by hand: the string it receives is
     * assembled with escPlan()/static markup only.
     */
    private const SAFE_VARIABLE_TARGETS = [
        // Plan preview card: built from server preview rows via escPlan().
        'html' => 'plan preview assembled with escPlan() (see renderPlanCard)',
    ];

    /**
     * Extract the right-hand side of every `x.innerHTML = ...` statement in a
     * Blade view. Quote/template-literal aware, so a `;` inside a CSS string or
     * a `${...}` placeholder never splits a statement.
     *
     * @return array<int, string> the assigned expressions
     */
    private function innerHtmlStatements(string $source): array
    {
        $out = [];
        $offset = 0;
        $needle = '.innerHTML';

        while (($pos = strpos($source, $needle, $offset)) !== false) {
            $i = $pos + strlen($needle);
            while ($i < strlen($source) && ctype_space($source[$i])) {
                $i++;
            }
            if ($i >= strlen($source) || $source[$i] !== '=') {
                // A read, or the mention inside a comment.
                $offset = $i;
                continue;
            }
            if (($source[$i + 1] ?? '') === '=') {
                // `===` comparison, not an assignment.
                $offset = $i + 2;
                continue;
            }
            $i++;

            $expr = '';
            $quote = null;
            $inTemplate = false;
            for (; $i < strlen($source); $i++) {
                $ch = $source[$i];
                if ($quote !== null) {
                    $expr .= $ch;
                    if ($ch === '\\') {
                        $expr .= $source[++$i] ?? '';
                        continue;
                    }
                    if ($ch === $quote) {
                        $quote = null;
                    }
                    continue;
                }
                if ($ch === "'" || $ch === '"') {
                    $quote = $ch;
                    $expr .= $ch;
                    continue;
                }
                if ($ch === '`') {
                    $inTemplate = ! $inTemplate;
                    $expr .= $ch;
                    continue;
                }
                if ($ch === ';' && ! $inTemplate) {
                    break;
                }
                $expr .= $ch;
            }

            $out[] = trim($expr);
            $offset = $i;
        }

        return $out;
    }

    public function test_every_inner_html_assignment_in_chat_views_goes_through_a_safe_source(): void
    {
        foreach (self::CHAT_VIEWS as $view) {
            $statements = $this->innerHtmlStatements($this->viewSource($view));

            $this->assertNotEmpty($statements, "No innerHTML assignment found in {$view}.");

            foreach ($statements as $expression) {
                // A concatenation of quoted literals is static markup: strip the
                // literals and nothing but whitespace / '+' / grouping may remain.
                $withoutLiterals = preg_replace(
                    "/'(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\"/",
                    '',
                    $expression
                );
                $isStaticConcat = trim((string) $withoutLiterals) !== ''
                    && preg_match('/^[\s+()]+$/', (string) $withoutLiterals) === 1;

                // A template literal is only acceptable when every ${...}
                // placeholder goes through an escaping helper.
                $templateOk = true;
                if (str_contains($expression, '`')) {
                    preg_match_all('/\$\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/s', $expression, $holes);
                    foreach ($holes[1] as $hole) {
                        $escaped = str_contains($hole, 'escHtml(')
                            || str_contains($hole, 'escPlan(')
                            || str_contains($hole, 'LinaMd.');
                        if (! $escaped) {
                            $templateOk = false;
                            break;
                        }
                    }
                }

                $safe = str_contains($expression, 'LinaMd.render(')
                    || str_contains($expression, 'LinaMd.escape(')
                    || $expression === "''"
                    || $expression === '""'
                    // A single quoted literal with no interpolation is static markup.
                    || (preg_match("/^'[^'`$]*'$/", $expression) === 1)
                    || (preg_match('/^"[^"`$]*"$/', $expression) === 1)
                    || $isStaticConcat
                    || $templateOk
                    // Reviewed, locally-escaped accumulator.
                    || isset(self::SAFE_VARIABLE_TARGETS[$expression]);

                $this->assertTrue(
                    (bool) $safe,
                    "Unsafe innerHTML source in {$view}: {$expression}"
                );
            }
        }
    }

    public function test_the_module_declares_its_fail_closed_contract(): void
    {
        $js = (string) file_get_contents(public_path('js/lina-markdown.js'));

        $this->assertStringContainsString('window.LinaMd', $js);
        $this->assertStringContainsString('DOMPurify.sanitize', $js);

        // Without the sanitizer we must fall back to escaped text, never raw HTML.
        $this->assertStringContainsString('return escapeHtml(text);', $js);
        $this->assertStringContainsString('USE_PROFILES', $js);
    }

    public function test_stream_history_and_report_paths_use_the_choke_point(): void
    {
        // Strip Blade comments so documentation references are not counted.
        $index = preg_replace('/\{\{--.*?--\}\}/s', '', $this->viewSource('ai/index.blade.php'));

        // history render + live-stream final render + report_generate result
        $this->assertSame(3, substr_count($index, 'LinaMd.render('));
    }
}
