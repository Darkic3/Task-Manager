<?php

namespace App\Http\Controllers;

use App\Models\JournalTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JournalTagController extends Controller
{
    public function index()
    {
        $tags = Auth::user()->journalTags()->orderBy('name')->get();

        return view('journal.tags.index', compact('tags'));
    }

    public function create()
    {
        return view('journal.tags.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
        $data['user_id'] = Auth::id();

        JournalTag::create($data);

        return redirect()->route('journal.tags.index')->with('status', __('Tag created.'));
    }

    public function show(JournalTag $journalTag)
    {
        $this->authorizeTag($journalTag);

        return redirect()->route('journal.tags.index');
    }

    public function edit(JournalTag $journalTag)
    {
        $this->authorizeTag($journalTag);

        return view('journal.tags.edit', compact('journalTag'));
    }

    public function update(Request $request, JournalTag $journalTag)
    {
        $this->authorizeTag($journalTag);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $journalTag->update($data);

        return redirect()->route('journal.tags.index')->with('status', __('Tag updated.'));
    }

    public function destroy(JournalTag $journalTag)
    {
        $this->authorizeTag($journalTag);
        $journalTag->delete();

        return redirect()->route('journal.tags.index')->with('status', __('Tag deleted.'));
    }

    protected function authorizeTag(JournalTag $tag): void
    {
        abort_if($tag->user_id !== Auth::id(), 403);
    }
}
