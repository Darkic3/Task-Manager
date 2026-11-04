<?php

namespace App\Http\Controllers;

use App\Models\VocabCard;
use App\Services\Vocabulary\LeitnerScheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VocabCardController extends Controller
{
    /**
     * Max new cards a user may add per day. Keeps the review queue sustainable.
     */
    public const DAILY_NEW_CARD_LIMIT = 20;

    public static function newTodayCount(\App\Models\User $user): int
    {
        return $user->vocabCards()->whereDate('created_at', today())->count();
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $decks = $user->vocabDecks()->orderBy('sort_order')->orderByDesc('id')->get();

        $query = $user->vocabCards()->with('deck')->orderByDesc('id');

        if ($request->filled('deck_id') && $request->deck_id !== 'all') {
            $query->byDeck((int) $request->deck_id);
        }

        if ($request->filled('box') && $request->box !== 'all') {
            $query->byBox((int) $request->box);
        }

        if ($request->filled('filter')) {
            match ($request->filter) {
                'due' => $query->due(),
                'mastered' => $query->mastered(),
                'learning' => $query->learning(),
                default => null,
            };
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $cards = $query->paginate(30)->withQueryString();

        return view('vocabulary.cards', [
            'cards' => $cards,
            'decks' => $decks,
            'filters' => $request->only(['deck_id', 'box', 'filter', 'search']),
            'newToday' => static::newTodayCount($user),
            'newLimit' => $user->vocabDailyLimit(),
        ]);
    }

    public function store(Request $request)
    {
        $limit = $request->user()->vocabDailyLimit();
        if (static::newTodayCount($request->user()) >= $limit) {
            return back()->with('error', __('Daily new-card limit reached (:limit/day).', ['limit' => $limit]));
        }

        $data = $request->validate($this->rules());

        $initial = LeitnerScheduler::initialState();

        Auth::user()->vocabCards()->create($data + $initial);

        return back()->with('success', __('Card added.'));
    }

    public function update(Request $request, VocabCard $vocabCard)
    {
        $this->owned($vocabCard);

        $data = $request->validate($this->rules());

        $reset = $vocabCard->front !== $data['front'] || $vocabCard->back !== $data['back'];

        $vocabCard->update($data);

        // Edited word/meaning restarts Leitner progress for that card.
        if ($reset) {
            $vocabCard->resetProgress();
        }

        return back()->with('success', __('Card updated.'));
    }

    public function destroy(VocabCard $vocabCard)
    {
        $this->owned($vocabCard);

        $vocabCard->delete();

        return back()->with('success', __('Card deleted.'));
    }

    public function reset(VocabCard $vocabCard)
    {
        $this->owned($vocabCard);

        $vocabCard->resetProgress();

        return back()->with('success', __('Card reset to Box 1.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $userId = Auth::id();

        return [
            'deck_id' => [
                'required',
                'integer',
                Rule::exists('vocab_decks', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'front' => 'required|string|max:255',
            'back' => 'required|string|max:2000',
            'example' => 'nullable|string|max:2000',
            'note' => 'nullable|string|max:2000',
        ];
    }

    private function owned(VocabCard $card): void
    {
        abort_if($card->user_id !== Auth::id(), 403);
    }
}
