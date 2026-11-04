<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VocabCard;
use App\Models\VocabDeck;
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

        // Editing the meaning restarts progress.
        $card->update(['box' => 3, 'review_count' => 5, 'success_count' => 4]);
        $this->put(route('vocabulary.cards.update', $card), [
            'deck_id' => $deck->id, 'front' => 'apple', 'back' => 'میوه سیب',
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
}
