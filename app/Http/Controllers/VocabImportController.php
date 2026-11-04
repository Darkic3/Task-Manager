<?php

namespace App\Http\Controllers;

use App\Services\Vocabulary\LeitnerScheduler;
use App\Services\Vocabulary\VocabCsvParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        session(['vocab_import_preview' => [
            'deck_id' => (int) $data['deck_id'],
            'rows' => $parsed['rows'],
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

        $now = now();
        $chunk = [];
        foreach ($preview['rows'] as $row) {
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

        // Bulk insert in chunks; import is an explicit action so the daily
        // new-card cap does not apply here.
        foreach (array_chunk($chunk, 200) as $piece) {
            \App\Models\VocabCard::insert($piece);
        }

        $count = count($chunk);
        session()->forget('vocab_import_preview');

        return redirect()->route('vocabulary.cards.index', ['deck_id' => $deck->id])
            ->with('success', __(':count cards imported.', ['count' => $count]));
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
