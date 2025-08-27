<?php

namespace App\Http\Controllers;

use App\Http\Requests\Journal\StoreJournalRequest;
use App\Http\Requests\Journal\UpdateJournalRequest;
use App\Http\Requests\Entry\StoreEntryRequest;
use App\Http\Requests\Entry\UpdateEntryRequest;
use App\Http\Requests\Entry\ReorderEntriesRequest;
use App\Http\Requests\Entry\PinEntryRequest;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use Inertia\Inertia;
use MongoDB\BSON\ObjectId;
use App\Services\TrashedItemService;
use App\Services\JournalService;
use App\Services\JournalEntryService;

class JournalController extends Controller
{
    public function __construct(
        private TrashedItemService $trashService,
        private JournalService $journalService,
        private JournalEntryService $entryService,
    ) {}
    // List all journals for the current user
    public function index()
    {
        $journals = Auth::user()
            ->journals()
            ->with(['entries' => function ($q) {
                $q->orderBy('created_at', 'desc');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Dashboard', [
            'journals' => $journals,
        ]);
    }
    

    // Show the form for creating a new journal.
    public function create()
    {
        return Inertia::render('pages/Journals/Create');
    }

    // Store a new journal (does not create entry)
    public function store(StoreJournalRequest $request)
    {
        $validated = $request->validated();

        $journal = $this->journalService->createJournal((string)(Auth::user()->_id ?? Auth::id()), $validated);

        if ($request->wantsJson()) {
            return response()->json($journal, 201);
        }

        return redirect()->route('dashboard');
    }

    // List available tags
    public function tags()
    {
        $names = Tag::orderBy('name')->pluck('name');
        return response()->json($names);
    }

    // Create a new tag
    public function createTag(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:64',
        ]);

        $name = trim($validated['name']);
        if ($name === '') {
            return response()->json(['message' => 'Tag name cannot be empty'], 422);
        }

        // Create if not exists (case-sensitive uniqueness)
        $tag = Tag::firstOrCreate(['name' => $name]);

        return response()->json($tag->name, 201);
    }

    // Get all entries for a specific journal
    public function entries(Journal $journal)
    {
        return $this->entryService->getJournalEntries($journal);
    }

    // Create a new entry in a specific journal
    public function storeEntry(StoreEntryRequest $request, Journal $journal)
    {
        $validated = $request->validated();
        
        // New entries should appear at the very top and manual order should be updated.
        $entryData = [
            'card_type' => $validated['card_type'] ?? 'text',
            'mood' => $validated['mood'] ?? null,
            'user_id' => $journal->user_id,
        ];
        
        if ($validated['card_type'] === 'text' || $validated['card_type'] === 'spreadsheet') {
            $entryData['content'] = $validated['content'] ?? null;
        } else if ($validated['card_type'] === 'checkbox') {
            $entryData['checkbox_items'] = $validated['checkbox_items'] ?? [];
            // Set content as a summary of checkbox items for display
            $entryData['content'] = $this->generateCheckboxSummary($validated['checkbox_items'] ?? []);
        }
        
        $entry = $this->entryService->createEntry($journal, $validated);
        return response()->json($entry, 201);
    }
    
    private function generateCheckboxSummary($items)
    {
        if (empty($items)) return 'Checklist';
        $checked = array_filter($items, fn($item) => $item['checked'] ?? false);
        return count($checked) . '/' . count($items) . ' completed';
    }

    // Update a specific journal (title/tags)
    public function update(UpdateJournalRequest $request, Journal $journal)
    {
        $validated = $request->validated();
        $update = [];
        if (array_key_exists('title', $validated)) {
            $update['title'] = $validated['title'];
        }
        if (array_key_exists('tags', $validated)) {
            $update['tags'] = array_values(array_unique($validated['tags']));
        }
        $journal->update($update);
        return $this->journalService->updateJournal($journal, $validated);
    }

    // Soft delete the journal and its entries
    public function destroy(Journal $journal)
    {
        // Check if the user owns this journal
        if ($journal->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $this->journalService->deleteJournal((string)(Auth::user()->_id ?? Auth::id()), $journal);
        return response()->json(['message' => 'Journal moved to trash'], 200);
    }

    // Delete an individual journal entry
    public function destroyEntry(Journal $journal, JournalEntry $entry)
    {
        $this->entryService->deleteEntry($journal, $entry);
        return response()->json(null, 204);
    }

    // Update an individual journal entry
    public function updateEntry(UpdateEntryRequest $request, Journal $journal, JournalEntry $entry)
    {
        $validated = $request->validated();
        
        $updateData = [
            'journal_id' => $validated['journal_id'] ?? $entry->journal_id,
            'user_id' => $entry->user_id,
        ];
        
        // If journal_id changed, sync user_id from the target journal
        if (isset($validated['journal_id']) && $validated['journal_id'] !== $entry->journal_id) {
            $targetUserId = Journal::withTrashed()->where('_id', $validated['journal_id'])->value('user_id');
            if ($targetUserId) {
                $updateData['user_id'] = $targetUserId;
            }
        }

        // Handle card type update
        if (isset($validated['card_type'])) {
            $updateData['card_type'] = $validated['card_type'];
        }
        
        // Handle mood update
        if (array_key_exists('mood', $validated)) {
            $updateData['mood'] = $validated['mood'];
        }
        
        // Update based on card type
        $cardType = $validated['card_type'] ?? $entry->card_type ?? 'text';
        
        if ($cardType === 'text' || $cardType === 'spreadsheet') {
            $updateData['content'] = $validated['content'];
            $updateData['checkbox_items'] = null;
        } else if ($cardType === 'checkbox') {
            $updateData['checkbox_items'] = $validated['checkbox_items'] ?? [];
            $updateData['content'] = $this->generateCheckboxSummary($validated['checkbox_items'] ?? []);
        }
        
        $updated = $this->entryService->updateEntry($journal, $entry, $validated);
        return response()->json($updated, 200);
    }

    // Get all trashed journals for the current user
    public function trash()
    {
        $items = $this->trashService->getTrashedJournalsForUser((string)(Auth::user()->_id ?? Auth::id()));
        return response()->json($items);
    }
    
    // Get all trashed entries (not in trashed journals)
    public function trashedEntries()
    {
        $items = $this->trashService->getTrashedEntriesForUser((string)(Auth::user()->_id ?? Auth::id()));
        return response()->json($items);
    }

    // Restore a soft-deleted journal and its entries
    public function restore($id)
    {
        $journal = $this->trashService->restoreJournal((string)(Auth::user()->_id ?? Auth::id()), $id);
        return response()->json(['message' => 'Journal restored successfully', 'journal' => $journal]);
    }

    // Permanently delete a soft-deleted journal and its entries
    public function forceDestroy($id)
    {
        $this->trashService->forceDeleteJournal((string)(Auth::user()->_id ?? Auth::id()), $id);
        return response()->json(['message' => 'Journal permanently deleted']);
    }

    // Empty all items from trash
    public function emptyTrash()
    {
        $this->trashService->emptyTrashForUser((string)(Auth::user()->_id ?? Auth::id()));
        return response()->json(['message' => 'Trash emptied successfully']);
    }

    // Reorder journal entries (supports pinning)
    public function reorderEntries(ReorderEntriesRequest $request, Journal $journal)
    {
        if ($journal->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validated();

        $this->entryService->reorderEntries($journal, $validated['entries']);
        return response()->json(['message' => 'Entries reordered successfully']);
    }

    // Pin or unpin an entry
    public function pinEntry(PinEntryRequest $request, Journal $journal, JournalEntry $entry)
    {
        if ($journal->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $validated = $request->validated();
        $this->entryService->pinEntry($journal, $entry, (bool)$validated['pinned']);
        return response()->json(['message' => 'Entry pin status updated']);
    }
    
    // Restore a soft-deleted entry
    public function restoreEntry($entryId)
    {
        $entry = $this->trashService->restoreEntry((string)(Auth::user()->_id ?? Auth::id()), (string)$entryId);
        return response()->json(['message' => 'Entry restored successfully', 'entry' => $entry]);
    }

    // Permanently delete a soft-deleted entry
    public function forceDestroyEntry($entryId)
    {
        $this->trashService->forceDeleteEntry((string)(Auth::user()->_id ?? Auth::id()), (string)$entryId);
        return response()->json(['message' => 'Entry permanently deleted']);
    }
}
