<?php

namespace App\Http\Controllers;

use App\Models\JournalBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JournalBookController extends Controller
{
    public function index()
    {
        $books = Auth::user()->journalBooks()->orderBy('sort_order')->orderBy('name')->get();

        return view('journal.books.index', compact('books'));
    }

    public function create()
    {
        return view('journal.books.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['user_id'] = Auth::id();
        $data['is_default'] = (bool) ($data['is_default'] ?? false);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        JournalBook::create($data);

        return redirect()->route('journal.books.index')->with('status', __('Book created.'));
    }

    public function show(JournalBook $journalBook)
    {
        $this->authorizeBook($journalBook);

        return redirect()->route('journal.books.index');
    }

    public function edit(JournalBook $journalBook)
    {
        $this->authorizeBook($journalBook);

        return view('journal.books.edit', compact('journalBook'));
    }

    public function update(Request $request, JournalBook $journalBook)
    {
        $this->authorizeBook($journalBook);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['is_default'] = (bool) ($data['is_default'] ?? false);
        $data['sort_order'] = $data['sort_order'] ?? $journalBook->sort_order;

        $journalBook->update($data);

        return redirect()->route('journal.books.index')->with('status', __('Book updated.'));
    }

    public function destroy(JournalBook $journalBook)
    {
        $this->authorizeBook($journalBook);
        $journalBook->delete();

        return redirect()->route('journal.books.index')->with('status', __('Book deleted.'));
    }

    protected function authorizeBook(JournalBook $book): void
    {
        abort_if($book->user_id !== Auth::id(), 403);
    }
}
