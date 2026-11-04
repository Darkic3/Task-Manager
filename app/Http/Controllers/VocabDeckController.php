<?php

namespace App\Http\Controllers;

use App\Models\VocabDeck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VocabDeckController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $decks = $user->vocabDecks()
            ->withCount('cards')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $dueByDeck = $user->vocabCards()
            ->due()
            ->selectRaw('deck_id, COUNT(*) as total')
            ->groupBy('deck_id')
            ->pluck('total', 'deck_id');

        $boxCounts = $user->vocabCards()
            ->selectRaw('box, COUNT(*) as total')
            ->groupBy('box')
            ->pluck('total', 'box');

        $stats = [
            'decks' => $decks->count(),
            'total' => (int) $user->vocabCards()->count(),
            'due' => (int) $user->vocabCards()->due()->count(),
            'mastered' => (int) $user->vocabCards()->mastered()->count(),
        ];

        return view('vocabulary.index', [
            'decks' => $decks,
            'dueByDeck' => $dueByDeck,
            'boxCounts' => $boxCounts,
            'stats' => $stats,
            'deck' => new VocabDeck(['color' => '#6366f1']),
            'newToday' => VocabCardController::newTodayCount($user),
            'newLimit' => $user->vocabDailyLimit(),
            'daily' => \App\Services\Vocabulary\VocabStatsService::dailyRetention($user),
            'deckStats' => \App\Services\Vocabulary\VocabStatsService::deckSummary($user),
            'overallRetention' => \App\Services\Vocabulary\VocabStatsService::overallRetention($user),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $maxOrder = (int) (Auth::user()->vocabDecks()->max('sort_order') ?? 0);

        $deck = Auth::user()->vocabDecks()->create($data + ['sort_order' => $maxOrder + 1]);

        return redirect()->route('vocabulary.index')
            ->with('success', __('Deck created.'));
    }

    public function update(Request $request, VocabDeck $vocabDeck)
    {
        $this->owned($vocabDeck);

        $data = $request->validate($this->rules());

        $vocabDeck->update($data);

        return redirect()->route('vocabulary.index')
            ->with('success', __('Deck updated.'));
    }

    public function destroy(VocabDeck $vocabDeck)
    {
        $this->owned($vocabDeck);

        $vocabDeck->delete();

        return redirect()->route('vocabulary.index')
            ->with('success', __('Deck deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    private function owned(VocabDeck $deck): void
    {
        abort_if($deck->user_id !== Auth::id(), 403);
    }
}
