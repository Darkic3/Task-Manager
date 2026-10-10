<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Support\MarkdownRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteEditorTest extends TestCase
{
    use RefreshDatabase;

    private function note(User $user, array $attrs = []): Note
    {
        return $user->notes()->create(array_merge([
            'title' => 'Editor note',
            'content' => "## Heading\n\nSome **bold** text.",
            'kind' => Note::KIND_GENERAL,
            'status' => Note::STATUS_ACTIVE,
            'visibility' => Note::VISIBILITY_PRIVATE,
        ], $attrs));
    }

    public function test_preview_endpoint_renders_gitHub_flavoured_markdown(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('notes.preview'), [
            'content' => "# Title\n\n- [ ] task\n\n| a | b |\n| --- | --- |\n| 1 | 2 |",
        ])
            ->assertOk()
            ->assertJsonPath('html', function (string $html) {
                return str_contains($html, '<h1>Title</h1>')
                    && str_contains($html, 'type="checkbox"')
                    && str_contains($html, '<table>');
            });
    }

    public function test_preview_escapes_raw_html_so_notes_cannot_inject_scripts(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->postJson(route('notes.preview'), [
            'content' => '<script>alert(1)</script>',
        ])->assertOk()->json('html');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_preview_requires_authentication(): void
    {
        $this->postJson(route('notes.preview'), ['content' => 'x'])->assertUnauthorized();
    }

    public function test_preview_requires_content(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('notes.preview'), ['content' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content');
    }

    public function test_note_show_renders_markdown_instead_of_printing_it_raw(): void
    {
        $user = User::factory()->create();
        $note = $this->note($user, ['content' => "## Roadmap\n\n- first\n- second"]);

        $html = $this->actingAs($user)->get(route('notes.show', $note))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<h2>Roadmap</h2>', $html);
        $this->assertStringNotContainsString('## Roadmap', $html);
    }

    public function test_note_content_html_accessor_is_the_single_render_path(): void
    {
        $note = new Note(['content' => '**hi**']);

        $this->assertSame('<p><strong>hi</strong></p>', trim($note->content_html));
    }

    public function test_excerpt_and_word_count_are_free_of_markdown_syntax(): void
    {
        $note = new Note(['content' => "## Title\n\nSome **bold** text with `code`."]);

        $this->assertStringNotContainsString('**', $note->excerpt);
        $this->assertStringNotContainsString('##', $note->excerpt);
        $this->assertStringNotContainsString('`', $note->excerpt);
        // Title (heading) + Some + bold + text + with + code
        $this->assertSame(6, $note->word_count);
    }

    public function test_create_and_edit_pages_ship_the_self_hosted_editor_without_cdn_assets(): void
    {
        $user = User::factory()->create();
        $note = $this->note($user);

        foreach ([route('notes.create'), route('notes.edit', $note)] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('id="ntEdShell"', $html, 'editor shell missing');
            $this->assertStringContainsString('id="ntEdPreview"', $html, 'live preview pane missing');
            $this->assertStringContainsString('nt-draft-bar', $html, 'draft restore banner missing');
            $this->assertStringContainsString('data-tpl=', $html, 'template gallery missing');
            $this->assertStringContainsString('id="ntEdMention"', $html, 'mention autocomplete missing');

            // The old CDN editor must be gone.
            $this->assertStringNotContainsString('easymde', strtolower($html));
            $this->assertStringNotContainsString('EasyMDE', $html);
            $this->assertStringNotContainsString('CodeMirror', $html);
        }
    }

    public function test_mood_block_is_always_rendered_so_switching_to_daily_reveals_it(): void
    {
        $user = User::factory()->create();
        $general = $this->note($user, ['kind' => Note::KIND_GENERAL]);

        $html = $this->actingAs($user)->get(route('notes.edit', $general))->assertOk()->getContent();

        // Present in the DOM but hidden for a non-daily note: the type switcher
        // can reveal it client-side instead of it being missing entirely.
        $this->assertMatchesRegularExpression(
            '/id="ntMoodBlock"[^>]*hidden/s',
            $html
        );
        $this->assertStringContainsString('id="ntMood"', $html);
        $this->assertStringContainsString('id="ntEnergy"', $html);
    }

    public function test_daily_note_shows_the_mood_block_unhidden(): void
    {
        $user = User::factory()->create();
        $daily = $this->note($user, ['kind' => Note::KIND_DAILY]);

        $html = $this->actingAs($user)->get(route('notes.edit', $daily))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/id="ntMoodBlock"[^>]*hidden/s', $html);
    }

    public function test_templates_can_be_applied_from_the_sidebar_gallery(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('notes.create'))->assertOk()->getContent();

        foreach (\App\Models\Note::KINDS as $kind) {
            $this->assertStringContainsString('data-tpl="' . $kind . '"', $html);
        }
    }

    public function test_notes_index_uses_the_prominent_new_note_button(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('notes.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="ntNewNoteBtn"', $html);
        $this->assertStringContainsString('nt-newbtn', $html);
    }

    public function test_note_show_links_resolved_label_tokens(): void
    {
        $user = User::factory()->create();
        $label = \App\Models\NoteLabel::create([
            'user_id' => $user->id,
            'name' => 'ideas',
            'slug' => 'ideas',
            'color' => '#7c3aed',
        ]);
        $note = $this->note($user, ['content' => 'Some thought about #ideas today.']);
        $note->labels()->attach($label->id);

        $html = $this->actingAs($user)->get(route('notes.show', $note))->assertOk()->getContent();

        $this->assertStringContainsString('nt-token-lbl', $html);
        $this->assertStringContainsString('#ideas', $html);
    }

    public function test_linked_and_backlink_panels_render_on_the_note_page(): void
    {
        $user = User::factory()->create();
        $note = $this->note($user);

        $html = $this->actingAs($user)->get(route('notes.show', $note))->assertOk()->getContent();

        $this->assertStringContainsString('id="ntLinked"', $html);
    }

    public function test_renderer_rejects_unsafe_link_schemes(): void
    {
        $html = MarkdownRenderer::toHtml('[click](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_renderer_linkifies_tokens_only_in_text_nodes(): void
    {
        $map = ['@ali' => '<a class="nt-token nt-token-psn" href="/notes?linked_type=subject">@ali</a>'];

        $html = MarkdownRenderer::toHtmlLinked('Hi @ali and `@ali` in code.', $map);

        $this->assertStringContainsString('<a class="nt-token nt-token-psn" href="/notes?linked_type=subject">@ali</a>', $html);
        // Inside code blocks the token stays plain text.
        $this->assertStringContainsString('<code>@ali</code>', $html);
    }
}