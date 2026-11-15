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

        $query = $user->vocabCards()->with('deck')->withCount(['outgoingRelations', 'incomingRelations'])->orderByDesc('id');

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

        if ($request->filled('tags')) {
            $tags = is_array($request->tags) ? $request->tags : explode(',', (string) $request->tags);
            foreach ($tags as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '') {
                    $query->byTag($tag);
                }
            }
        }

        if ($request->filled('difficulty') && $request->difficulty !== 'all') {
            $query->where('difficulty', (int) $request->difficulty);
        }

        if ($request->filled('lapse_min')) {
            $query->where('lapse_count', '>=', (int) $request->lapse_min);
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

        $existingTags = $user->vocabCards()->pluck('tags')
            ->flatten()->filter()->unique()->values()->all();
        $existingSources = $user->vocabCards()->whereNotNull('source_section')
            ->distinct()->pluck('source_section')->values()->all();

        return view('vocabulary.cards', [
            'cards' => $cards,
            'decks' => $decks,
            'filters' => $request->only(['deck_id', 'box', 'filter', 'search', 'card_type', 'priority', 'source_section', 'tag', 'tags', 'difficulty', 'lapse_min']),
            'newToday' => static::newTodayCount($user),
            'newLimit' => $user->vocabDailyLimit(),
            'existingTags' => $existingTags,
            'existingSources' => $existingSources,
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

    public function conceptMap(Request $request, VocabCard $vocabCard)
    {
        $this->owned($vocabCard);
        $user = Auth::user();
        $typeFilter = (string) $request->get('type', 'all');

        $outgoing = $vocabCard->outgoingRelations()->with('relatedCard.deck')->get();
        if ($typeFilter !== 'all') {
            $outgoing = $outgoing->where('relation_type', $typeFilter)->values();
        }

        // JSON related_cards as pseudo "related" relations.
        $pseudo = collect();
        if (($typeFilter === 'all' || $typeFilter === 'related') && is_array($vocabCard->related_cards)) {
            $existingIds = $outgoing->pluck('related_card_id')->all();
            $cards = VocabCard::where('user_id', $vocabCard->user_id)
                ->whereIn('id', $vocabCard->related_cards)
                ->whereNotIn('id', $existingIds)
                ->with('deck')
                ->get();
            foreach ($cards as $rc) {
                $pseudo->push((object) [
                    'id' => null,
                    'related_card_id' => $rc->id,
                    'relation_type' => 'related',
                    'relatedCard' => $rc,
                ]);
            }
        }
        $outgoing = $outgoing->concat($pseudo);

        $incoming = $vocabCard->incomingRelations()->with('card.deck')->get();
        if ($typeFilter !== 'all') {
            $incoming = $incoming->where('relation_type', $typeFilter)->values();
        }

        $types = $vocabCard->outgoingRelations()->pluck('relation_type')
            ->merge($vocabCard->incomingRelations()->pluck('relation_type'))
            ->push('related')
            ->unique()->values()->all();

        $allCards = $user->vocabCards()
            ->where('id', '!=', $vocabCard->id)
            ->orderBy('front')
            ->get(['id', 'front']);

        return view('vocabulary.concept-map', [
            'card' => $vocabCard,
            'outgoing' => $outgoing,
            'incoming' => $incoming,
            'types' => $types,
            'typeFilter' => $typeFilter,
            'allCards' => $allCards,
        ]);
    }

    public function storeRelation(Request $request, VocabCard $vocabCard)
    {
        $this->owned($vocabCard);

        $data = $request->validate([
            'related_card_id' => ['required', 'integer', Rule::exists('vocab_cards', 'id')->where(fn ($q) => $q->where('user_id', Auth::id()))],
            'relation_type' => 'nullable|string|max:32',
        ]);

        if ((int) $data['related_card_id'] === (int) $vocabCard->id) {
            return back()->with('error', __('Cannot relate a card to itself.'));
        }

        VocabRelation::firstOrCreate([
            'card_id' => $vocabCard->id,
            'related_card_id' => (int) $data['related_card_id'],
            'relation_type' => $data['relation_type'] ?: 'related',
        ]);

        return back()->with('success', __('Relation added.'));
    }

    public function destroyRelation(VocabCard $vocabCard, VocabRelation $vocabRelation)
    {
        $this->owned($vocabCard);
        abort_if($vocabRelation->card_id !== $vocabCard->id, 403);

        $vocabRelation->delete();

        return back()->with('success', __('Relation removed.'));
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'deck_id' => ['required', 'integer', Rule::exists('vocab_decks', 'id')->where(fn ($q) => $q->where('user_id', Auth::id()))],
            'source_section' => 'nullable|string|max:64',
            'card_type' => ['nullable', 'string', Rule::in(\App\Enums\CardType::values())],
            'priority' => 'nullable|integer|min:1|max:3',
            'lines' => 'required|string',
        ]);

        $user = $request->user();
        $deck = $user->vocabDecks()->findOrFail($data['deck_id']);
        $initial = \App\Services\Vocabulary\LeitnerScheduler::initialState();
        $count = 0;

        foreach (preg_split('/\r\n|\r|\n/', $data['lines']) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('::', $line, 2));
            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }

            $front = mb_substr($parts[0], 0, 255);
            $back = mb_substr($parts[1], 0, 2000);

            if (\App\Models\VocabCard::findDuplicateInDeck((int) $user->id, (int) $deck->id, $front)) {
                continue;
            }

            $user->vocabCards()->create([
                'deck_id' => $deck->id,
                'front' => $front,
                'back' => $back,
                'card_type' => $data['card_type'] ?? 'definition',
                'priority' => $data['priority'] ?? 2,
                'source_section' => $data['source_section'] ?? null,
            ] + $initial);

            $count++;
        }

        return back()->with('success', __(':count cards generated.', ['count' => $count]));
    }
    public function reset(VocabCard $vocabCard)
    {

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
            'extra.*' => 'nullable',
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

        if ($request->has('extra') && is_array($request->input('extra'))) {
            $extra = $request->input('extra');
            foreach (['steps', 'detection_methods', 'triage_steps'] as $key) {
                if (isset($extra[$key]) && is_string($extra[$key])) {
                    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $extra[$key]))));
                    $extra[$key] = $lines;
                }
            }
            $request->merge(['extra' => $extra]);
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
