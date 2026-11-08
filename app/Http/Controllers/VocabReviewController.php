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

        // A fresh session is built when the filter changes, when yesterday's
        // session is stale, or when the last session hit the session limit and
        // cards are still due today.
        $queue = session('vocab_review_queue');
        if (! $this->isFreshQueue($queue, $deckId, $dir, $totalDue)) {
            $queue = $this->buildQueue($user, $deckId, $dir);
        }

        $head = $this->pullNext($user, $queue);
        session(['vocab_review_queue' => $queue]);

        $current = $head['card'] ?? null;
        $reverse = (bool) ($head['reverse'] ?? false);

        $summary = null;
        if ($current === null && ($queue['finished_at'] ?? null) === today()->toDateString()) {
            $summary = $this->summaryPayload($queue, $totalDue);
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
            'sessionCount' => (int) ($queue['total'] ?? 0),
            'reviewedCount' => $this->reviewedCount($queue),
            'current' => $current,
            'reverse' => $reverse,
            'prompt' => $current ? ($reverse ? $current->back : $current->front) : null,
            'answer' => $current ? ($reverse ? $current->front : $current->back) : null,
            'showExample' => (bool) ($current && ! $reverse && $current->example),
            'summary' => $summary,
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

        $user = Auth::user();
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

        // Session counters power the end-of-session summary.
        $queue['remembered'] = (int) ($queue['remembered'] ?? 0) + ($data['remembered'] ? 1 : 0);
        $queue['forgotten'] = (int) ($queue['forgotten'] ?? 0) + ($data['remembered'] ? 0 : 1);

        $queue['items'] = array_values(array_filter(
            $queue['items'] ?? [],
            fn ($item) => ($item['id'] ?? null) != $vocabCard->id
        ));

        if (($queue['items'] ?? []) === []) {
            $queue['finished_at'] = today()->toDateString();

            // Queue drained: if nothing is due anymore, the day's review
            // reminder has served its purpose — complete it automatically.
            if ($user->vocabCards()->due()->count() === 0) {
                VocabReminderService::completeForUser($user);
            }
        }

        session(['vocab_review_queue' => $queue]);

        $deckId = (string) ($data['deck_id'] ?? 'all');

        if ($request->wantsJson()) {
            $head = $this->pullNext($user, $queue);
            session(['vocab_review_queue' => $queue]);

            $dueLeft = $this->dueQuery($user, $deckId)->count();
            $finished = $head === null;

            return response()->json([
                'next' => $finished ? null : $this->cardPayload($head['card'], (bool) $head['reverse']),
                'finished' => $finished,
                'summary' => $finished ? $this->summaryPayload($queue, $dueLeft) : null,
                'progress' => $this->progressPayload($queue, $dueLeft),
                'undo' => $this->undoPayload($review, $vocabCard),
            ]);
        }

        return redirect()->route('vocabulary.review.index', $this->filterParams($data))
            ->with('success', $data['remembered'] ? __('Well done — moved forward.') : __('Back to Box 1 for tomorrow.'))
            // Lets the next page offer a five-second undo for this grade.
            ->with('vocab_undo', $this->undoPayload($review, $vocabCard));
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
            if ($request->wantsJson()) {
                return response()->json(['error' => __('That review can no longer be undone.')], 422);
            }

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
        $queue = is_array($queue) ? $queue : ['items' => []];
        $queue['items'] = array_values(array_filter(
            $queue['items'] ?? [],
            fn ($item) => ($item['id'] ?? null) != $card->id
        ));
        array_unshift($queue['items'], ['id' => $card->id, 'reverse' => (bool) $review->reversed]);
        $queue['finished_at'] = null;
        $queue['remembered'] = max(0, (int) ($queue['remembered'] ?? 0) - ($review->remembered ? 1 : 0));
        $queue['forgotten'] = max(0, (int) ($queue['forgotten'] ?? 0) - ($review->remembered ? 0 : 1));

        if (! isset($queue['total']) || (int) $queue['total'] < count($queue['items'])) {
            $queue['total'] = count($queue['items']);
        }

        session(['vocab_review_queue' => $queue]);

        // The card is due again, so today's reminder is no longer finished.
        VocabReminderService::reopenForUser(Auth::user());

        if ($request->wantsJson()) {
            return response()->json([
                'restored' => $this->cardPayload($card, (bool) $review->reversed),
                'progress' => $this->progressPayload($queue, $this->dueQuery(Auth::user(), (string) ($data['deck_id'] ?? 'all'))->count()),
            ]);
        }

        return redirect()->route('vocabulary.review.index', $params)
            ->with('success', __('Review undone — the card is back in the queue.'));
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
     * @return array{deck: string, dir: string, total: int, remembered: int, forgotten: int, items: list<array{id: int, reverse: bool}>}
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

        return [
            'deck' => $deckId,
            'dir' => $dir,
            'total' => count($items),
            'remembered' => 0,
            'forgotten' => 0,
            'items' => $items,
        ];
    }

    /**
     * The next queue entry that still exists, skipping cards deleted mid-session.
     *
     * @param  array<string, mixed>  $queue
     * @return array{card: VocabCard, reverse: bool}|null
     */
    private function pullNext(User $user, array &$queue): ?array
    {
        while (($queue['items'] ?? []) !== []) {
            $head = $queue['items'][0];
            $card = $user->vocabCards()->with('deck')->find($head['id']);

            if (! $card) {
                array_shift($queue['items']);
                continue;
            }

            return ['card' => $card, 'reverse' => (bool) ($head['reverse'] ?? false)];
        }

        return null;
    }

    private function isFreshQueue(mixed $queue, string $deckId, string $dir, int $totalDue): bool
    {
        if (! is_array($queue)
            || ($queue['deck'] ?? null) !== $deckId
            || ($queue['dir'] ?? null) !== $dir
            || ! isset($queue['items']) || ! is_array($queue['items'])) {
            return false;
        }

        $finishedAt = $queue['finished_at'] ?? null;

        if ($finishedAt !== null) {
            // Yesterday's finished session, or a finished session that stopped
            // at the session limit while cards are still due — start fresh.
            if ($finishedAt !== today()->toDateString() || $totalDue > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $queue
     */
    private function reviewedCount(array $queue): int
    {
        return max(0, (int) ($queue['total'] ?? 0) - count($queue['items'] ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    private function cardPayload(VocabCard $card, bool $reverse): array
    {
        return [
            'id' => $card->id,
            'prompt' => $reverse ? $card->back : $card->front,
            'answer' => $reverse ? $card->front : $card->back,
            'example' => (! $reverse && $card->example) ? $card->example : null,
            'deck' => $card->deck->name ?? '',
            'box' => (int) $card->box,
            'box_label' => __('Box :n', ['n' => $card->box]),
            'reverse' => $reverse,
            'direction' => $reverse ? __('What is the word for this meaning?') : __('What does this mean?'),
            'grade_url' => route('vocabulary.cards.grade', $card),
            'total_boxes' => LeitnerScheduler::BOX_MAX,
        ];
    }

    /**
     * @param  array<string, mixed>  $queue
     * @return array<string, mixed>
     */
    private function progressPayload(array $queue, int $dueLeft): array
    {
        $total = (int) ($queue['total'] ?? 0);
        $done = $this->reviewedCount($queue);

        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'due' => $dueLeft,
            'text' => __(':done of :total in this session · :due due total', [
                'done' => app_num($done),
                'total' => app_num($total),
                'due' => app_num($dueLeft),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $queue
     * @return array<string, mixed>|null
     */
    private function summaryPayload(array $queue, int $dueLeft): ?array
    {
        $reviewed = $this->reviewedCount($queue);

        if ($reviewed === 0) {
            return null;
        }

        $remembered = (int) ($queue['remembered'] ?? 0);
        $forgotten = (int) ($queue['forgotten'] ?? 0);
        $graded = $remembered + $forgotten;

        return [
            'total' => $reviewed,
            'remembered' => $remembered,
            'forgotten' => $forgotten,
            'accuracy' => $graded > 0 ? (int) round($remembered / $graded * 100) : 0,
            'due_left' => $dueLeft,
            'due_text' => $dueLeft > 0
                ? __(':count cards still due today.', ['count' => app_num($dueLeft)])
                : __('Nothing left due today — nicely done.'),
        ];
    }

    /**
     * @return array{review_id: int, front: string}
     */
    private function undoPayload(VocabReview $review, VocabCard $card): array
    {
        return [
            'review_id' => $review->id,
            'front' => $card->front,
        ];
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
}
