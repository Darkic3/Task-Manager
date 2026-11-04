<?php

namespace App\Http\Controllers;

use App\Models\VocabCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VocabReviewController extends Controller
{
    public const SESSION_LIMIT = 20;

    public function index(Request $request)
    {
        $user = Auth::user();

        $decks = $user->vocabDecks()->orderBy('sort_order')->orderByDesc('id')->get();

        $query = $user->vocabCards()->with('deck')->due()->orderBy('box')->orderBy('id');

        if ($request->filled('deck_id') && $request->deck_id !== 'all') {
            $query->byDeck((int) $request->deck_id);
        }

        $totalDue = (clone $query)->count();

        $cards = $query->limit(self::SESSION_LIMIT)->get();

        $current = null;
        if ($request->filled('card_id')) {
            $current = $user->vocabCards()->due()->find($request->card_id);
        }
        $current ??= $cards->first();

        $remaining = $cards->reject(fn (VocabCard $c) => $current && $c->id === $current->id)->values();

        return view('vocabulary.review', [
            'decks' => $decks,
            'deckId' => $request->get('deck_id', 'all'),
            'totalDue' => $totalDue,
            'sessionCount' => $cards->count(),
            'current' => $current,
            'remaining' => $remaining->pluck('id')->all(),
            'reviewedCount' => max(0, min($totalDue, self::SESSION_LIMIT) - $cards->count()),
        ]);
    }

    public function grade(Request $request, VocabCard $vocabCard)
    {
        abort_if($vocabCard->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'remembered' => 'required|boolean',
            'deck_id' => 'nullable|string',
            'remaining' => 'nullable|string',
        ]);

        $vocabCard->markResult((bool) $data['remembered']);

        $params = [];
        if (! empty($data['deck_id']) && $data['deck_id'] !== 'all') {
            $params['deck_id'] = $data['deck_id'];
        }

        // Pass the rest of the queue so the review page continues seamlessly.
        if (! empty($data['remaining'])) {
            $ids = array_values(array_filter(explode(',', $data['remaining']), fn ($v) => $v !== '' && $v != $vocabCard->id));
            if ($ids !== []) {
                $params['card_id'] = $ids[0];
                $params['remaining'] = implode(',', array_slice($ids, 1));
                // Keep remaining in sync client-side via query string fallback.
                return redirect()->route('vocabulary.review.index', $params)
                    ->with('success', $data['remembered'] ? __('Well done — moved forward.') : __('Back to Box 1 for tomorrow.'));
            }
        }

        return redirect()->route('vocabulary.review.index', $params)
            ->with('success', $data['remembered'] ? __('Well done — moved forward.') : __('Back to Box 1 for tomorrow.'));
    }
}
