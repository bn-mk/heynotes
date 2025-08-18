<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use Inertia\Inertia;

class JournalController extends Controller
{
    // List all journals for the current user
    public function index()
    {
        $journals = Auth::user()->journals()->with('entries')->get();
        return Inertia::render('Dashboard',
     ['journals' => $journals] ?: [],  
        );
    }

    // Show the form for creating a new journal.
    public function create()
    {
        return Inertia::render('pages/Journals/Create');
    }

    // Store a new journal (does not create entry)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'tags' => 'nullable|string',
        ]);

        $journal = Journal::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'tags' => $validated['tags'] ?? '',
        ]);

        if ($request->wantsJson()) {
            return response()->json($journal, 201);
        }

        return redirect()->route('dashboard');
    }

    // Get all entries for a specific journal
    public function entries(Journal $journal)
    {
        return $journal->entries()->orderBy('created_at', 'desc')->get();
    }

    // Create a new entry in a specific journal
    public function storeEntry(Request $request, Journal $journal)
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);
        $entry = $journal->entries()->create([
            'content' => $validated['content'],
            // Optionally add more fields like 'user_id' if your entries table needs it
        ]);
        return response()->json($entry, 201);
    }

    // Update a specific journal (title/tags)
    public function update(Request $request, Journal $journal)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'tags' => 'sometimes|string',
        ]);
        $journal->update($validated);
        return $journal;
    }

    // Delete the journal entry
    public function destroy(Journal $journal)
    {
        $journal->delete();
        return response()->json(null, 204);
    }

    // Delete an individual journal entry
    public function destroyEntry(Journal $journal, JournalEntry $entry)
    {
        $entry->delete();
        return response()->json(null, 204);
    }

    // Update an individual journal entry
    public function updateEntry(Request $request, Journal $journal, JournalEntry $entry)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'journal_id' => 'sometimes|exists:journals,id'
        ]);
        $entry->update([
            'content' => $validated['content'],
            'journal_id' => $validated['journal_id'] ?? $entry->journal_id
        ]);
        return response()->json($entry, 200);
    }
}
