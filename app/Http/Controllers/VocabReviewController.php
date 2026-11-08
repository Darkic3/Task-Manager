<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VocabCard;
use App\Models\VocabReview;
use App\Services\Vocabulary\LeitnerScheduler;
use App\Services\Vocabulary\VocabReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VocabReviewController extends Controller
{
    public const SESSION_LIMIT = 20;

    public const DIR_FRONT = 'front';

    public const DIR_BACK = 'back';

    public const DIR_MIXED = 'mixed';

    public function index(Request $request)
    {
        $user = Auth::user();

        $decks = $user->vocabDecks()->orderBy('sort_order')->orderByDesc('id')->get();

        $deckId = (string) $request->get('deck_id', 'all');
        $dir = $request->get('dir', self::DIR_FRONT);
        if (! in_array($dir, [self::DIR_FRONT, self::DIR_BACK, self::DIR_MIXED], true)) {
            $dir = self::DIR_FRONT;
        }

        $totalDue = $this->dueQuery($user, $deckId)->count();

        $queue = session('vocab_review_queue');
        if (! $this->isFreshQueue($queue, $deckId, $dir)) {
            $queue = $this->buildQueue($user, $deckId, $dir);
            session(['vocab_review_queue' => $queue]);
        }

        // Drop cards that were deleted since the queue was built.
        $existingIds = $user->vocabCards()->whereIn('id', collect($queue['items'])->pluck('id')->all() ?: [0])->pluck('id')->all();
        $queue['items'] = array_values(array_filter($queue['items'], fn ($item) => in_array($item['id'], $existingIds, true)));
        session(['vocab_review_queue' => $queue]);

        $current = null;
        $prompt = null;
        $answer = null;
        $showExample = false;
        if ($queue['items'] !== []) {
            $current = $user->vocabCards()->with('deck')->find($queue['items'][0]['id']);
            if (! $current) {
                array_shift($queue['items']);
                session(['vocab_review_queue' => $queue]);
            } else {
                $reverse = (bool) $queue['items'][0]['reverse'];
                $prompt = $reverse ? $current->back : $current->front;
                $answer = $reverse ? $current->front : $current->back;
                // The example usually contains the word itself — only show
                // it when the word is the prompt.
                $showExample = ! $reverse && $current->example;
            }
        }

        return view('vocabulary.review', [
            'decks' => $decks,
            'deckId' => $deckId,
            'dir' => $dir,
            'dirs' => [
                self::DIR_FRONT => __('Front → Back'),
                self::DIR_BACK => __('Back → Front'),
                self::DIR_MIXED => __('Mixed'),
            ],
            'totalDue' => $totalDue,
            'sessionCount' => $queue['total'],
            'reviewedCount' => max(0, $queue['total'] - count($queue['items'])),
            'current' => $current,
            'prompt' => $prompt,
            'answer' => $answer,
            'showExample' => $showExample,
        ]);
    }

    public function grade(Request $request, VocabCard $vocabCard)
    {
        abort_if($vocabCard->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'remembered' => 'required|boolean',
            'deck_id' => 'nullable|string',
            'dir' => 'nullable|string',
        ]);

        $boxBefore = (int) $vocabCard->box;

        $vocabCard->markResult((bool) $data['remembered']);

        $queue = session('vocab_review_queue', ['items' => []]);
        $queued = collect($queue['items'] ?? [])->firstWhere('id', $vocabCard->id);
        $reversed = (bool) ($queued['reverse'] ?? false);

        // History log: powers retention stats and is the raw material for
        // any future SM-2/FSRS scheduler. Never backfilled — starts now.
        $review = $vocabCard->reviews()->create([
            'user_id' => Auth::id(),
            'deck_id' => $vocabCard->deck_id,
            'remembered' => (bool) $data['remembered'],
            'box_before' => $boxBefore,
            'box_after' => (int) $vocabCard->box,
            'reversed' => $reversed,
            'reviewed_at' => now(),
        ]);

        $queue['items'] = array_values(array_filter(
            $queue['items'] ?? [],
            fn ($item) => ($item['id'] ?? null) != $vocabCard->id
        ));
        session(['vocab_review_queue' => $queue]);

        // Queue drained: if nothing is due anymore, the day's review reminder
        // has served its purpose — complete it automatically.
        if (($queue['items'] ?? []) === [] && Auth::user()->vocabCards()->due()->count() === 0) {
            VocabReminderService::completeForUser(Auth::user());
        }

        return redirect()->route('vocabulary.review.index', $this->filterParams($data))
            ->with('success', $data['remembered'] ? __('Well done — moved forward.') : __('Back to Box 1 for tomorrow.'))
            // Lets the next page offer a five-second undo for this grade.
            ->with('vocab_undo', [
                'review_id' => $review->id,
                'front' => $vocabCard->front,
                'box_after' => (int) $vocabCard->box,
            ]);
    }

    /**
     * Revert the last grade: the card returns to its previous box, becomes due
     * again and goes back to the front of the session queue.
     */
    public function undo(Request $request)
    {
        $data = $request->validate([
            'review_id' => 'required|integer',
            'deck_id' => 'nullable|string',
            'dir' => 'nullable|string',
        ]);

        $params = $this->filterParams($data);
        $review = VocabReview::where('user_id', Auth::id())->find($data['review_id']);
        $card = $review?->card;

        if (! $review || ! $card || $card->user_id !== Auth::id()
            || $review->reviewed_at->lt(now()->subMinutes(2))) {
            return redirect()->route('vocabulary.review.index', $params)
                ->with('error', __('That review can no longer be undone.'));
        }

        $review->delete();

        $previous = $card->reviews()->latest('id')->first();

        $card->update([
            'box' => max(LeitnerScheduler::BOX_MIN, min(LeitnerScheduler::BOX_MAX, (int) $review->box_before)),
            'next_review_at' => today(),
            'last_reviewed_at' => $previous?->reviewed_at,
            'review_count' => max(0, (int) $card->review_count - 1),
            'success_count' => max(0, (int) $card->success_count - ($review->remembered ? 1 : 0)),
            'is_mastered' => (int) $review->box_before === LeitnerScheduler::MASTERED_BOX,
        ]);

        // Put the card back at the front of the running queue, same direction.
        $queue = session('vocab_review_queue');
        if (is_array($queue) && isset($queue['items']) && is_array($queue['items'])) {
            $queue['items'] = array_values(array_filter(
                $queue['items'],
                fn ($item) => ($item['id'] ?? null) != $card->id
            ));
            array_unshift($queue['items'], ['id' => $card->id, 'reverse' => (bool) $review->reversed]);
            session(['vocab_review_queue' => $queue]);
        }

        // The card is due again, so today's reminder is no longer finished.
        VocabReminderService::reopenForUser(Auth::user());

        return redirect()->route('vocabulary.review.index', $params)
            ->with('success', __('Review undone — the card is back in the queue.'));
    }

    /**
     * Review-page query params that keep the current deck/direction filter.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function filterParams(array $data): array
    {
        $params = [];

        if (! empty($data['deck_id']) && $data['deck_id'] !== 'all') {
            $params['deck_id'] = (string) $data['deck_id'];
        }

        if (! empty($data['dir']) && $data['dir'] !== self::DIR_FRONT) {
            $params['dir'] = (string) $data['dir'];
        }

        return $params;
    }

    private function dueQuery(User $user, string $deckId)
    {
        $query = $user->vocabCards()->due();

        if ($deckId !== 'all' && is_numeric($deckId)) {
            $query->byDeck((int) $deckId);
        }

        return $query;
    }

    /**
     * @return array{deck: string, dir: string, total: int, items: list<array{id: int, reverse: bool}>}
     */
    private function buildQueue(User $user, string $deckId, string $dir): array
    {
        $cards = $this->dueQuery($user, $deckId)
            ->orderBy('box')->orderBy('id')
            ->limit($user->vocabSessionLimit())
            ->get(['id']);

        $items = $cards->map(fn (VocabCard $c) => [
            'id' => $c->id,
            'reverse' => $dir === self::DIR_BACK || ($dir === self::DIR_MIXED && random_int(0, 1) === 1),
        ])->all();

        return ['deck' => $deckId, 'dir' => $dir, 'total' => count($items), 'items' => $items];
    }

    private function isFreshQueue(mixed $queue, string $deckId, string $dir): bool
    {
        return is_array($queue)
            && ($queue['deck'] ?? null) === $deckId
            && ($queue['dir'] ?? null) === $dir
            && isset($queue['items']) && is_array($queue['items']);
    }
}
