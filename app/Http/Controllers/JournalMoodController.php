<?php

namespace App\Http\Controllers;

use App\Models\JournalMood;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JournalMoodController extends Controller
{
    public function index()
    {
        $moods = Auth::user()->journalMoods()->orderBy('sort_order')->orderBy('name')->get();

        return view('journal.moods.index', compact('moods'));
    }

    public function create()
    {
        return view('journal.moods.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:60'],
            'category' => ['required', 'string', 'in:positive,negative,neutral'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['user_id'] = Auth::id();
        $data['sort_order'] = $data['sort_order'] ?? 0;

        JournalMood::create($data);

        return redirect()->route('journal.moods.index')->with('status', __('Mood created.'));
    }

    public function show(JournalMood $journalMood)
    {
        $this->authorizeMood($journalMood);

        return redirect()->route('journal.moods.index');
    }

    public function edit(JournalMood $journalMood)
    {
        $this->authorizeMood($journalMood);

        return view('journal.moods.edit', compact('journalMood'));
    }

    public function update(Request $request, JournalMood $journalMood)
    {
        $this->authorizeMood($journalMood);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:60'],
            'category' => ['required', 'string', 'in:positive,negative,neutral'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? $journalMood->sort_order;

        $journalMood->update($data);

        return redirect()->route('journal.moods.index')->with('status', __('Mood updated.'));
    }

    public function destroy(JournalMood $journalMood)
    {
        $this->authorizeMood($journalMood);
        $journalMood->delete();

        return redirect()->route('journal.moods.index')->with('status', __('Mood deleted.'));
    }

    protected function authorizeMood(JournalMood $mood): void
    {
        abort_if($mood->user_id !== Auth::id(), 403);
    }
}
