<?php

namespace App\Http\Controllers;

use App\Models\VocabCard;
use App\Services\Vocabulary\LeitnerScheduler;
use App\Services\Vocabulary\VocabCsvParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VocabImportController extends Controller
{
    public function create()
    {
        $decks = Auth::user()->vocabDecks()->orderBy('sort_order')->orderByDesc('id')->get();

        return view('vocabulary.import', [
            'decks' => $decks,
            'preview' => session('vocab_import_preview'),
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'deck_id' => [
                'required', 'integer',
                Rule::exists('vocab_decks', 'id')->where(fn ($q) => $q->where('user_id', Auth::id())),
            ],
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $contents = file_get_contents($data['file']->getRealPath());
        $parsed = VocabCsvParser::parse($contents);

        if ($parsed['rows'] === []) {
            return back()->withInput()->with('error', __('No valid rows found in the file.'));
        }

        $deck = Auth::user()->vocabDecks()->findOrFail($data['deck_id']);

        // Skip words the deck already has (and repeats inside the file), so
        // uploading the same list twice cannot create duplicate cards.
        $seen = $this->knownWords($deck->id);
        $rows = [];
        $duplicates = 0;

        foreach ($parsed['rows'] as $row) {
            $key = VocabCard::normalizeFront((string) $row['front']);

            if ($key === '' || $seen->has($key)) {
                $duplicates++;
                continue;
            }

            $seen->put($key, true);
            $rows[] = $row;
        }

        if ($rows === []) {
            return back()->withInput()->with('error', __('Nothing new to import — :count rows are already in this deck.', ['count' => app_num($duplicates)]));
        }

        session(['vocab_import_preview' => [
            'deck_id' => (int) $data['deck_id'],
            'rows' => $rows,
            'duplicates' => $duplicates,
            'errors' => $parsed['errors'],
            'truncated' => $parsed['truncated'],
        ]]);

        return redirect()->route('vocabulary.imports.create');
    }

    public function confirm(Request $request)
    {
        $preview = session('vocab_import_preview');

        if (! $preview || empty($preview['rows'])) {
            return redirect()->route('vocabulary.imports.create')
                ->with('error', __('Nothing to import — upload a CSV file first.'));
        }

        $deck = Auth::user()->vocabDecks()->findOrFail($preview['deck_id']);
        $initial = LeitnerScheduler::initialState();

        // The deck may have gained cards since the preview — re-check so the
        // same file cannot be imported twice.
        $seen = $this->knownWords($deck->id);

        $now = now();
        $chunk = [];
        $skipped = 0;

        foreach ($preview['rows'] as $row) {
            $key = VocabCard::normalizeFront((string) $row['front']);

            if ($key === '' || $seen->has($key)) {
                $skipped++;
                continue;
            }

            $seen->put($key, true);

            $chunk[] = $row + [
                'user_id' => Auth::id(),
                'deck_id' => $deck->id,
                'box' => $initial['box'],
                'next_review_at' => $initial['next_review_at']->toDateString(),
                'is_mastered' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($chunk === []) {
            session()->forget('vocab_import_preview');

            return redirect()->route('vocabulary.cards.index', ['deck_id' => $deck->id])
                ->with('error', __('Nothing new to import — those words are already in this deck.'));
        }

        // Bulk insert in one transaction; import is an explicit action so the
        // daily new-card cap does not apply here.
        DB::transaction(function () use ($chunk): void {
            foreach (array_chunk($chunk, 200) as $piece) {
                VocabCard::insert($piece);
            }
        });

        $count = count($chunk);
        session()->forget('vocab_import_preview');

        $message = $skipped > 0
            ? __(':count cards imported · :skipped duplicates skipped.', ['count' => $count, 'skipped' => $skipped])
            : __(':count cards imported.', ['count' => $count]);

        return redirect()->route('vocabulary.cards.index', ['deck_id' => $deck->id])
            ->with('success', $message);
    }

    /**
     * Normalised words already present in a deck, keyed for O(1) lookups.
     *
     * @return \Illuminate\Support\Collection<string, bool>
     */
    private function knownWords(int $deckId): \Illuminate\Support\Collection
    {
        return Auth::user()->vocabCards()
            ->where('deck_id', $deckId)
            ->pluck('front')
            ->mapWithKeys(fn ($front) => [VocabCard::normalizeFront((string) $front) => true]);
    }

    public function template()
    {
        $csv = "front,back,example,note\napple,سیب,I eat an apple every day.,\nbook,کتاب,,\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="vocabulary-template.csv"',
        ]);
    }
}
