<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VocabCard;
use App\Models\VocabDeck;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
