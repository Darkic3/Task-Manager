<?php

namespace App\Http\Controllers;

use App\Models\VocabCard;
use App\Models\VocabRelation;
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

        if ($request->filled('card_type') && $request->card_type !== 'all') {
            $query->byCardType((string) $request->card_type);
        }

        if ($request->filled('priority') && $request->priority !== 'all') {
            $query->byPriority((int) $request->priority);
        }

        if ($request->filled('source_section')) {
            $query->bySourceSection((string) $request->source_section);
        }

        if ($request->filled('tag')) {
            $query->byTag((string) $request->tag);
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
            'filters' => $request->only(['deck_id', 'box', 'filter', 'search', 'card_type', 'priority', 'source_section', 'tag']),
            'newToday' => static::newTodayCount($user),
            'newLimit' => $user->vocabDailyLimit(),
        ]);
    }

    public function store(Request $request)
    {
        $this->normalizeTagInput($request);
        $this->normalizeRelatedCardsInput($request);
        $this->normalizeExtraInput($request);

        $data = $request->validate($this->rules());

        $user = $request->user();

        if (VocabCard::findDuplicateInDeck((int) $user->id, (int) $data['deck_id'], (string) $data['front'])) {
            return back()->withInput()->with('error', __('This word is already in this deck.'));
        }

        $limit = $user->vocabDailyLimit();
        if (static::newTodayCount($user) >= $limit) {
            return back()->withInput()->with('error', __('Daily new-card limit reached (:limit/day).', ['limit' => $limit]));
        }

        $initial = LeitnerScheduler::initialState();

        $card = $user->vocabCards()->create($data + $initial);

        $this->syncRelations($card, $data['related_cards'] ?? null);

        return back()->with('success', __('Card added.'));
    }

    public function update(Request $request, VocabCard $vocabCard)
    {
        $this->owned($vocabCard);

        $this->normalizeTagInput($request);
        $this->normalizeRelatedCardsInput($request);
        $this->normalizeExtraInput($request);

        $data = $request->validate($this->rules());

        $duplicate = VocabCard::findDuplicateInDeck(
            (int) $vocabCard->user_id,
            (int) $data['deck_id'],
            (string) $data['front'],
            (int) $vocabCard->id
        );

        if ($duplicate) {
            return back()->withInput()->with('error', __('This word is already in this deck.'));
        }

        // Only renaming the word restarts its progress — fixing a typo in the
        // meaning must never throw a mastered card back to Box 1.
        $reset = VocabCard::normalizeFront((string) $vocabCard->front)
            !== VocabCard::normalizeFront((string) $data['front']);

        $vocabCard->update($data);

        if ($reset) {
            $vocabCard->resetProgress();
        }

        $this->syncRelations($vocabCard, $data['related_cards'] ?? $vocabCard->related_cards);

        return back()->with('success', $reset
            ? __('Card updated — progress restarted in Box 1.')
            : __('Card updated.'));
    }

    public function destroy(VocabCard $vocabCard)
    {
        $this->owned($vocabCard);

        $vocabCard->delete();

        return back()->with('success', __('Card deleted.'));
    }

    public function conceptMap(VocabCard $vocabCard)
    {
        $this->owned($vocabCard);

        $relatedIds = is_array($vocabCard->related_cards) ? $vocabCard->related_cards : [];
        $tableIds = $vocabCard->outgoingRelations()->pluck('related_card_id')->all();
        $relatedIds = array_values(array_unique(array_merge($relatedIds, $tableIds)));

        $relatedCards = VocabCard::where('user_id', $vocabCard->user_id)
            ->whereIn('id', $relatedIds)
            ->with('deck')
            ->get();

        $incomingIds = $vocabCard->incomingRelations()->pluck('card_id')->all();
        $incomingCards = VocabCard::where('user_id', $vocabCard->user_id)
            ->whereIn('id', $incomingIds)
            ->with('deck')
            ->get();

        return view('vocabulary.concept-map', [
            'card' => $vocabCard,
            'relatedCards' => $relatedCards,
            'incomingCards' => $incomingCards,
        ]);
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
            'card_type' => ['nullable', 'string', Rule::in(\App\Enums\CardType::values())],
            'priority' => 'nullable|integer|min:1|max:3',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:64',
            'related_cards' => 'nullable|array',
            'related_cards.*' => 'integer',
            'extra' => 'nullable|array',
            'source_section' => 'nullable|string|max:64',
            'difficulty' => 'nullable|integer|min:1|max:5',
        ];
    }

    private function owned(VocabCard $card): void
    {
        abort_if($card->user_id !== Auth::id(), 403);
    }

    private function syncRelations(VocabCard $card, mixed $relatedIds): void
    {
        VocabRelation::where('card_id', $card->id)->delete();

        if (! is_array($relatedIds)) {
            return;
        }

        foreach ($relatedIds as $relatedId) {
            $relatedId = (int) $relatedId;
            if ($relatedId <= 0 || $relatedId === (int) $card->id) {
                continue;
            }

            $related = VocabCard::where('user_id', $card->user_id)->find($relatedId);
            if (! $related) {
                continue;
            }

            VocabRelation::firstOrCreate([
                'card_id' => $card->id,
                'related_card_id' => $relatedId,
                'relation_type' => 'related',
            ]);
        }
    }

    private function normalizeExtraInput(Request $request): void
    {
        if ($request->has('extra') && is_string($request->input('extra'))) {
            $raw = trim((string) $request->input('extra'));
            if ($raw === '') {
                $request->merge(['extra' => null]);
                return;
            }
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['extra' => $decoded]);
            }
        }
    }
    private function normalizeTagInput(Request $request): void
    {
        if ($request->has('tags') && is_string($request->input('tags'))) {
            $tags = array_values(array_filter(array_map(
                static fn ($t) => trim($t),
                explode(',', (string) $request->input('tags'))
            )));
            $request->merge(['tags' => $tags]);
        }
    }

    private function normalizeRelatedCardsInput(Request $request): void
    {
        if ($request->has('related_cards') && is_string($request->input('related_cards'))) {
            $ids = array_values(array_filter(array_map(
                static fn ($t) => (int) trim($t),
                explode(',', (string) $request->input('related_cards'))
            )));
            $request->merge(['related_cards' => $ids]);
        }
    }
}
