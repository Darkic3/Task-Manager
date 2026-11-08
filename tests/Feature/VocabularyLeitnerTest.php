<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VocabCard;
use App\Models\VocabDeck;
use App\Models\VocabReview;
use App\Services\Vocabulary\VocabStatsService;
use App\Http\Controllers\VocabCardController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VocabularyLeitnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_leitner_loop(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create deck.
        $this->post(route('vocabulary.decks.store'), ['name' => 'English', 'color' => '#6366f1'])
            ->assertRedirect(route('vocabulary.index'));
        $deck = VocabDeck::where('user_id', $user->id)->firstOrFail();

        // Add card → starts in Box 1, due today.
        $this->post(route('vocabulary.cards.store'), [
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
        ])->assertRedirect();
        $card = VocabCard::where('user_id', $user->id)->firstOrFail();
        $this->assertEquals(1, $card->box);
        $this->assertTrue($card->is_due);

        // Pages render.
        $this->get(route('vocabulary.index'))->assertOk()->assertSee('apple');
        $this->get(route('vocabulary.cards.index'))->assertOk()->assertSee('apple');
        $this->get(route('vocabulary.review.index'))->assertOk()->assertSee('apple');

        // Remembered → Box 2, no longer due.
        $this->post(route('vocabulary.cards.grade', $card), ['remembered' => 1])
            ->assertRedirect();
        $card->refresh();
        $this->assertEquals(2, $card->box);
        $this->assertFalse($card->refresh()->is_due);

        // Forgot → back to Box 1, due tomorrow.
        $card->update(['next_review_at' => today()]);
        $this->post(route('vocabulary.cards.grade', $card), ['remembered' => 0])
            ->assertRedirect();
        $card->refresh();
        $this->assertEquals(1, $card->box);

        // Editing only the meaning keeps Leitner progress…
        $card->update(['box' => 3, 'review_count' => 5, 'success_count' => 4]);
        $this->put(route('vocabulary.cards.update', $card), [
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'میوه سیب',
        ])->assertRedirect();
        $this->assertEquals(3, $card->fresh()->box);
        $this->assertEquals(5, $card->fresh()->review_count);

        // …renaming the word restarts it in Box 1.
        $this->put(route('vocabulary.cards.update', $card), [
            'deck_id' => $deck->id, 'front' => 'apples', 'back' => 'میوه سیب',
        ])->assertRedirect();
        $this->assertEquals(1, $card->fresh()->box);
        $this->assertEquals(0, $card->fresh()->review_count);
    }

    public function test_users_cannot_touch_each_others_cards(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $deck = $owner->vocabDecks()->create(['name' => 'German']);
        $card = $owner->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'Haus', 'back' => 'خانه',
            'box' => 1, 'next_review_at' => today(),
        ]);

        $this->actingAs($intruder)
            ->post(route('vocabulary.cards.grade', $card), ['remembered' => 1])
            ->assertForbidden();
    }

    public function test_daily_new_card_limit_is_enforced(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);

        for ($i = 0; $i < VocabCardController::DAILY_NEW_CARD_LIMIT; $i++) {
            $this->post(route('vocabulary.cards.store'), [
                'deck_id' => $deck->id, 'front' => "w{$i}", 'back' => "m{$i}",
            ])->assertRedirect();
        }

        $this->assertEquals(
            VocabCardController::DAILY_NEW_CARD_LIMIT,
            VocabCard::where('user_id', $user->id)->count()
        );

        $this->post(route('vocabulary.cards.store'), [
            'deck_id' => $deck->id, 'front' => 'extra', 'back' => 'اضافی',
        ])->assertSessionHas('error');

        $this->assertEquals(
            VocabCardController::DAILY_NEW_CARD_LIMIT,
            VocabCard::where('user_id', $user->id)->count()
        );
    }

    public function test_dashboard_shows_vocabulary_strip(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 1, 'next_review_at' => today(),
        ]);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee(route('vocabulary.review.index'), false);
    }

    public function test_csv_import_preview_and_confirm(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);

        $csv = "front,back,example\napple,سیب,an apple\nbook,کتاب,\nlonely,\n";
        $file = UploadedFile::fake()->createWithContent('words.csv', $csv);

        $this->post(route('vocabulary.imports.preview'), ['deck_id' => $deck->id, 'file' => $file])
            ->assertRedirect(route('vocabulary.imports.create'))
            ->assertSessionHas('vocab_import_preview');

        $this->get(route('vocabulary.imports.create'))->assertOk()->assertSee('apple');

        $this->post(route('vocabulary.imports.confirm'))
            ->assertRedirect(route('vocabulary.cards.index', ['deck_id' => $deck->id]));

        $cards = VocabCard::where('user_id', $user->id)->get();
        $this->assertCount(2, $cards);
        $this->assertTrue($cards->every(fn (VocabCard $c) => $c->box === 1 && $c->is_due));
    }

    public function test_review_direction_controls_example_visibility(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'example' => 'an apple a day', 'box' => 1, 'next_review_at' => today(),
        ]);

        // Word is the prompt: example shown.
        $this->get(route('vocabulary.review.index'))->assertOk()->assertSee('an apple a day');

        // Meaning is the prompt: example hidden (it would leak the answer).
        $this->get(route('vocabulary.review.index', ['dir' => 'back']))->assertOk()->assertDontSee('an apple a day');

        $this->get(route('vocabulary.review.index', ['dir' => 'mixed']))->assertOk();

        // Unknown direction falls back to front.
        $this->get(route('vocabulary.review.index', ['dir' => 'bogus']))->assertOk()->assertSee('an apple a day');
    }

    public function test_grade_logs_review_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $card = $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 2, 'next_review_at' => today(),
        ]);

        // Build a reversed session queue, then grade.
        $this->get(route('vocabulary.review.index', ['dir' => 'back']))->assertOk();
        $this->post(route('vocabulary.cards.grade', $card), ['remembered' => 1, 'dir' => 'back'])
            ->assertRedirect();

        $review = VocabReview::where('user_id', $user->id)->sole();
        $this->assertTrue($review->remembered);
        $this->assertEquals(2, $review->box_before);
        $this->assertEquals(3, $review->box_after);
        $this->assertTrue($review->reversed);
        $this->assertEquals($deck->id, $review->deck_id);
        $this->assertEquals($card->id, $review->vocab_card_id);
    }

    public function test_stats_service_aggregates_retention(): void
    {
        $user = User::factory()->create();
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $card = $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 1, 'next_review_at' => today(),
        ]);

        $make = fn (bool $remembered, string $day) => VocabReview::create([
            'user_id' => $user->id, 'vocab_card_id' => $card->id, 'deck_id' => $deck->id,
            'remembered' => $remembered, 'box_before' => 1, 'box_after' => 2,
            'reversed' => false, 'reviewed_at' => $day,
        ]);

        $make(true, today()->toDateString());
        $make(true, today()->toDateString());
        $make(false, today()->toDateString());
        $make(false, today()->subDay()->toDateString());

        $daily = VocabStatsService::dailyRetention($user, 2);
        $this->assertCount(2, $daily);
        $this->assertSame(1, $daily[0]['reviewed']);
        $this->assertSame(0.0, $daily[0]['retention']);
        $this->assertSame(3, $daily[1]['reviewed']);
        $this->assertEqualsWithDelta(66.7, $daily[1]['retention'], 0.05);

        $summary = VocabStatsService::deckSummary($user);
        $this->assertCount(1, $summary);
        $this->assertSame(1, $summary[0]['total']);
        $this->assertSame(4, $summary[0]['reviewed_7d']);
        $this->assertEqualsWithDelta(50.0, $summary[0]['retention_7d'], 0.01);

        $this->assertEqualsWithDelta(50.0, VocabStatsService::overallRetention($user), 0.01);
    }

    public function test_reports_page_shows_vocabulary_section_and_insight(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $card = $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 1, 'next_review_at' => today(),
        ]);
        VocabReview::create([
            'user_id' => $user->id, 'vocab_card_id' => $card->id, 'deck_id' => $deck->id,
            'remembered' => true, 'box_before' => 1, 'box_after' => 2,
            'reversed' => false, 'reviewed_at' => now(),
        ]);

        $html = $this->get(route('reports.overview', ['range' => 'week']))->assertOk()->getContent();

        $this->assertStringContainsString('id="vocabulary"', $html);
        $this->assertStringContainsString('vocab reviews due', $html);
    }

    public function test_daily_reminder_command_creates_and_updates_one_reminder(): void
    {
        $user = User::factory()->create();
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 1, 'next_review_at' => today(),
        ]);

        $this->artisan('vocab:remind-due')->assertSuccessful();

        $reminders = \App\Models\Reminder::where('user_id', $user->id)
            ->where('category', \App\Services\Vocabulary\VocabReminderService::CATEGORY)
            ->get();
        $this->assertCount(1, $reminders);
        $this->assertSame(today()->toDateString(), $reminders->first()->date->toDateString());

        // Re-running refreshes instead of duplicating.
        $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'book', 'back' => 'کتاب',
            'box' => 1, 'next_review_at' => today(),
        ]);
        $this->artisan('vocab:remind-due')->assertSuccessful();
        $this->assertCount(1, \App\Models\Reminder::where('user_id', $user->id)
            ->where('category', \App\Services\Vocabulary\VocabReminderService::CATEGORY)->get());

        // Completed reminders are never re-created.
        $reminders->first()->markAsCompleted();
        $this->artisan('vocab:remind-due')->assertSuccessful();
        $this->assertCount(1, \App\Models\Reminder::where('user_id', $user->id)
            ->where('category', \App\Services\Vocabulary\VocabReminderService::CATEGORY)->get());
    }

    public function test_grading_last_due_card_completes_daily_reminder(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $card = $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 1, 'next_review_at' => today(),
        ]);
        $reminder = $user->reminders()->create([
            'title' => 'x', 'description' => '', 'date' => today()->toDateString(), 'time' => '',
            'priority' => 'medium',
            'category' => \App\Services\Vocabulary\VocabReminderService::CATEGORY,
            'recurrence_type' => 'none', 'recurrence_interval' => 1, 'is_completed' => false,
        ]);

        $this->post(route('vocabulary.cards.grade', $card), ['remembered' => 1])->assertRedirect();

        $this->assertTrue($reminder->fresh()->is_completed);
    }

    public function test_custom_daily_limit_is_respected(): void
    {
        $user = User::factory()->create(['vocab_daily_limit' => 2]);
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);

        for ($i = 0; $i < 2; $i++) {
            $this->post(route('vocabulary.cards.store'), [
                'deck_id' => $deck->id, 'front' => "w{$i}", 'back' => "m{$i}",
            ])->assertRedirect();
        }
        $this->post(route('vocabulary.cards.store'), [
            'deck_id' => $deck->id, 'front' => 'extra', 'back' => 'اضافی',
        ])->assertSessionHas('error');
        $this->assertCount(2, VocabCard::where('user_id', $user->id)->get());
    }

    public function test_custom_session_limit_caps_review_queue(): void
    {
        $user = User::factory()->create(['vocab_session_limit' => 3]);
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        for ($i = 0; $i < 5; $i++) {
            $user->vocabCards()->create([
                'deck_id' => $deck->id, 'front' => "w{$i}", 'back' => "m{$i}",
                'box' => 1, 'next_review_at' => today(),
            ]);
        }

        $this->get(route('vocabulary.review.index'))->assertOk();
        $queue = session('vocab_review_queue');
        $this->assertCount(3, $queue['items']);
        $this->assertSame(3, $queue['total']);
    }

    public function test_disabled_reminder_skips_user(): void
    {
        $user = User::factory()->create(['vocab_remind_enabled' => false]);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 1, 'next_review_at' => today(),
        ]);

        $this->artisan('vocab:remind-due')->assertSuccessful();
        $this->assertCount(0, \App\Models\Reminder::where('user_id', $user->id)->get());
    }

    public function test_profile_update_saves_vocab_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'vocab_daily_limit' => 10,
            'vocab_session_limit' => 15,
            'vocab_remind_enabled' => 0,
        ])->assertRedirect(route('profile.show'));

        $user->refresh();
        $this->assertSame(10, $user->vocabDailyLimit());
        $this->assertSame(15, $user->vocabSessionLimit());
        $this->assertFalse((bool) $user->vocab_remind_enabled);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'vocab_daily_limit' => 0,
        ])->assertSessionHasErrors('vocab_daily_limit');
    }

    public function test_review_page_renders_focus_timer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 1, 'next_review_at' => today(),
        ]);

        $this->get(route('vocabulary.review.index'))->assertOk()
            ->assertSee('id="vbTimer"', false)
            // Hooks the no-reload grading, undo bar and summary dialog need.
            ->assertSee('data-grade="1"', false)
            ->assertSee('id="vbGrade"', false)
            ->assertSee('id="vbUndo"', false)
            ->assertSee('id="vbSummary"', false)
            ->assertSee('id="vbProgressText"', false);
    }

    public function test_duplicate_words_are_rejected_within_a_deck(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $other = $user->vocabDecks()->create(['name' => 'TOEFL']);

        $this->post(route('vocabulary.cards.store'), [
            'deck_id' => $deck->id, 'front' => 'Apple', 'back' => 'سیب',
        ])->assertRedirect();

        // Case, spacing and punctuation normalise away.
        $this->post(route('vocabulary.cards.store'), [
            'deck_id' => $deck->id, 'front' => '  apple. ', 'back' => 'سیب',
        ])->assertSessionHas('error');

        $this->assertSame(1, VocabCard::where('user_id', $user->id)->count());

        // The same word may live in another deck.
        $this->post(route('vocabulary.cards.store'), [
            'deck_id' => $other->id, 'front' => 'apple', 'back' => 'سیب',
        ])->assertSessionHas('success');

        $this->assertSame(2, VocabCard::where('user_id', $user->id)->count());
    }

    public function test_import_skips_words_the_deck_already_has(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 1, 'next_review_at' => today(),
        ]);

        // One existing word, one new word and one repeat inside the file.
        $csv = "front,back\nApple,سیب\nbook,کتاب\nBOOK,کتاب\n";
        $file = UploadedFile::fake()->createWithContent('words.csv', $csv);

        $this->post(route('vocabulary.imports.preview'), ['deck_id' => $deck->id, 'file' => $file])
            ->assertRedirect(route('vocabulary.imports.create'));

        $preview = session('vocab_import_preview');
        $this->assertCount(1, $preview['rows']);
        $this->assertSame('book', $preview['rows'][0]['front']);
        $this->assertSame(2, $preview['duplicates']);

        $this->post(route('vocabulary.imports.confirm'))->assertRedirect();

        $this->assertSame(2, VocabCard::where('user_id', $user->id)->count());
    }

    public function test_a_grade_can_be_undone(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $card = $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 2, 'next_review_at' => today(),
            'review_count' => 3, 'success_count' => 2,
        ]);

        $this->get(route('vocabulary.review.index'))->assertOk();

        $this->post(route('vocabulary.cards.grade', $card), ['remembered' => 1])->assertRedirect();

        $this->assertSame(3, $card->fresh()->box);
        $undo = session('vocab_undo');
        $this->assertNotNull($undo);

        $this->post(route('vocabulary.reviews.undo'), [
            'review_id' => $undo['review_id'], 'deck_id' => 'all', 'dir' => 'front',
        ])->assertRedirect();

        $card->refresh();
        $this->assertSame(2, $card->box);
        $this->assertSame(3, $card->review_count);
        $this->assertSame(2, $card->success_count);
        $this->assertTrue($card->is_due);
        $this->assertSame(0, VocabReview::where('user_id', $user->id)->count());

        // Back at the front of the running queue.
        $queue = session('vocab_review_queue');
        $this->assertSame($card->id, $queue['items'][0]['id']);
    }

    public function test_undo_is_rejected_after_its_window(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $card = $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 2, 'next_review_at' => today(),
        ]);

        $review = VocabReview::create([
            'user_id' => $user->id, 'vocab_card_id' => $card->id, 'deck_id' => $deck->id,
            'remembered' => true, 'box_before' => 2, 'box_after' => 3,
            'reversed' => false, 'reviewed_at' => now()->subMinutes(10),
        ]);

        $this->post(route('vocabulary.reviews.undo'), ['review_id' => $review->id])
            ->assertSessionHas('error');

        $this->assertSame(1, VocabReview::where('user_id', $user->id)->count());
        $this->assertSame(2, $card->fresh()->box);
    }

    public function test_ajax_grading_returns_the_next_card(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);

        $first = $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 1, 'next_review_at' => today(),
        ]);
        $second = $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'book', 'back' => 'کتاب',
            'box' => 1, 'next_review_at' => today(),
        ]);

        $this->get(route('vocabulary.review.index'))->assertOk();

        $this->postJson(route('vocabulary.cards.grade', $first), [
            'remembered' => 1, 'deck_id' => 'all', 'dir' => 'front',
        ])->assertOk()
            ->assertJsonPath('finished', false)
            ->assertJsonPath('progress.done', 1)
            ->assertJsonPath('progress.total', 2)
            ->assertJsonPath('next.id', $second->id)
            ->assertJsonPath('next.prompt', 'book')
            ->assertJsonStructure([
                'next' => ['id', 'prompt', 'answer', 'deck', 'box', 'box_label', 'direction', 'grade_url'],
                'progress' => ['done', 'total', 'percent', 'due', 'text'],
                'undo' => ['review_id', 'front'],
            ]);
    }

    public function test_ajax_grading_the_last_card_returns_a_session_summary(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $card = $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 1, 'next_review_at' => today(),
        ]);

        $this->get(route('vocabulary.review.index'))->assertOk();

        $this->postJson(route('vocabulary.cards.grade', $card), ['remembered' => 1])
            ->assertOk()
            ->assertJsonPath('finished', true)
            ->assertJsonPath('next', null)
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.remembered', 1)
            ->assertJsonPath('summary.accuracy', 100)
            ->assertJsonPath('summary.due_left', 0);
    }

    public function test_ajax_undo_returns_the_restored_card(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $card = $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 2, 'next_review_at' => today(),
        ]);

        $this->get(route('vocabulary.review.index'))->assertOk();

        $grade = $this->postJson(route('vocabulary.cards.grade', $card), ['remembered' => 1])->assertOk();
        $reviewId = $grade->json('undo.review_id');

        $this->postJson(route('vocabulary.reviews.undo'), ['review_id' => $reviewId])
            ->assertOk()
            ->assertJsonPath('restored.id', $card->id)
            ->assertJsonPath('restored.prompt', 'apple')
            ->assertJsonPath('progress.done', 0)
            ->assertJsonPath('progress.total', 1);

        $this->assertSame(2, $card->fresh()->box);
    }

    public function test_finished_session_renders_a_server_side_summary(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $deck = $user->vocabDecks()->create(['name' => 'English']);
        $card = $user->vocabCards()->create([
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'سیب',
            'box' => 1, 'next_review_at' => today(),
        ]);

        $this->get(route('vocabulary.review.index'))->assertOk();
        $this->post(route('vocabulary.cards.grade', $card), ['remembered' => 0])->assertRedirect();

        $this->get(route('vocabulary.review.index'))->assertOk()
            ->assertSee('data-session-summary', false);
    }
}
