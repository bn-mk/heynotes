<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use Inertia\Inertia;
use MongoDB\BSON\ObjectId;
use Illuminate\Support\Facades\Log;

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
            'content' => 'required_if:card_type,text|nullable|string',
            'card_type' => 'required|in:text,checkbox',
            'checkbox_items' => 'required_if:card_type,checkbox|nullable|array',
            'checkbox_items.*.text' => 'required_with:checkbox_items|string',
            'checkbox_items.*.checked' => 'required_with:checkbox_items|boolean',
            'mood' => 'nullable|string|in:happy,sad,tired,angry,anxious,grateful,calm,thoughtful,confident,stressed,loved,neutral',
        ]);
        
        // Get the highest display_order for this journal's entries
        $maxOrder = $journal->entries()->max('display_order') ?? -1;
        
        $entryData = [
            'display_order' => $maxOrder + 1,
            'card_type' => $validated['card_type'] ?? 'text',
            'mood' => $validated['mood'] ?? null,
            'user_id' => $journal->user_id,
        ];
        
        if ($validated['card_type'] === 'text') {
            $entryData['content'] = $validated['content'];
        } else if ($validated['card_type'] === 'checkbox') {
            $entryData['checkbox_items'] = $validated['checkbox_items'] ?? [];
            // Set content as a summary of checkbox items for display
            $entryData['content'] = $this->generateCheckboxSummary($validated['checkbox_items'] ?? []);
        }
        
        $entry = $journal->entries()->create($entryData);
        return response()->json($entry, 201);
    }
    
    private function generateCheckboxSummary($items)
    {
        if (empty($items)) return 'Checklist';
        $checked = array_filter($items, fn($item) => $item['checked'] ?? false);
        return count($checked) . '/' . count($items) . ' completed';
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

    // Soft delete the journal and its entries
    public function destroy(Journal $journal)
    {
        // Check if the user owns this journal
        if ($journal->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Soft delete all entries belonging to this journal
        $journal->entries()->delete();
        
        // Soft delete the journal itself
        $journal->delete();
        
        return response()->json(['message' => 'Journal moved to trash'], 200);
    }

    // Delete an individual journal entry
    public function destroyEntry(Journal $journal, JournalEntry $entry)
    {
        $entry->delete();
        // Refresh and log soft-delete state for debugging
        try {
            $entry->refresh();
        } catch (\Throwable $e) {}
        Log::info('entry_deleted', [
            'entry_id' => (string)($entry->_id ?? $entry->id ?? ''),
            'deleted_at' => $entry->deleted_at ?? null,
            'journal_id' => isset($entry->journal_id) ? (string)$entry->journal_id : null,
        ]);
        return response()->json(null, 204);
    }

    // Update an individual journal entry
    public function updateEntry(Request $request, Journal $journal, JournalEntry $entry)
    {
        $validated = $request->validate([
            'content' => 'required_if:card_type,text|nullable|string',
            'journal_id' => 'sometimes|exists:journals,id',
            'card_type' => 'sometimes|in:text,checkbox',
            'checkbox_items' => 'required_if:card_type,checkbox|nullable|array',
            'checkbox_items.*.text' => 'required_with:checkbox_items|string',
            'checkbox_items.*.checked' => 'required_with:checkbox_items|boolean',
            'mood' => 'nullable|string|in:happy,sad,tired,angry,anxious,grateful,calm,thoughtful,confident,stressed,loved,neutral',
        ]);
        
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
        
        if ($cardType === 'text') {
            $updateData['content'] = $validated['content'];
            $updateData['checkbox_items'] = null;
        } else if ($cardType === 'checkbox') {
            $updateData['checkbox_items'] = $validated['checkbox_items'] ?? [];
            $updateData['content'] = $this->generateCheckboxSummary($validated['checkbox_items'] ?? []);
        }
        
        $entry->update($updateData);
        return response()->json($entry, 200);
    }

    // Get all trashed journals for the current user
    public function trash()
    {
        $trashedJournals = Journal::onlyTrashed()
            ->where('user_id', Auth::id())
            ->with(['entries' => function ($query) {
                $query->withTrashed();
            }])
            ->get();
        
        return response()->json($trashedJournals);
    }
    
    // Get all trashed entries (not in trashed journals)
    public function trashedEntries()
    {
        // Prepare current user id as string
        $currentUserId = (string) (Auth::user()->_id ?? Auth::id());

        // Active and trashed journal ids for current user
        $activeJournalIds = Journal::where('user_id', $currentUserId)->pluck('_id');
        $trashedJournalIds = Journal::onlyTrashed()->where('user_id', $currentUserId)->pluck('_id');

        // Normalize ids to support ObjectId/string comparisons
        $normalize = function ($ids) {
            return collect($ids)
                ->flatMap(function ($id) {
                    $asString = (string) $id;
                    try {
                        $asObject = new ObjectId($asString);
                        return [$id, $asString, $asObject];
                    } catch (\Throwable $e) {
                        return [$id, $asString];
                    }
                })
                ->unique(strict: false)
                ->values()
                ->all();
        };

        $activeIdsNormalized = $normalize($activeJournalIds);
        $trashedIdsNormalized = $normalize($trashedJournalIds);

        $allTrashedCount = JournalEntry::withTrashed()->whereNotNull('deleted_at')->count();
        $filteredQuery = JournalEntry::withTrashed()
            ->whereNotNull('deleted_at')
            ->where(function ($q) use ($activeIdsNormalized, $currentUserId) {
                $q->whereIn('journal_id', $activeIdsNormalized)
                  ->orWhere('user_id', $currentUserId);
            })
            ->whereNotIn('journal_id', $trashedIdsNormalized);

        $filteredCount = $filteredQuery->count();

        Log::info('trashed_entries_query', [
            'active_journal_ids' => array_map(fn($v) => (string)$v, $activeIdsNormalized),
            'trashed_journal_ids' => array_map(fn($v) => (string)$v, $trashedIdsNormalized),
            'all_trashed_count' => $allTrashedCount,
            'filtered_count' => $filteredCount,
        ]);

        $trashedEntries = $filteredQuery
            ->with('journal:_id,title')
            ->orderBy('deleted_at', 'desc')
            ->get();
        
        return response()->json($trashedEntries);
    }

    // Restore a soft-deleted journal and its entries
    public function restore($id)
    {
        $journal = Journal::onlyTrashed()->where('_id', $id)->where('user_id', Auth::id())->firstOrFail();
        
        // Restore the journal
        $journal->restore();
        
        // Restore all its entries
        JournalEntry::onlyTrashed()->where('journal_id', $id)->restore();
        
        return response()->json(['message' => 'Journal restored successfully', 'journal' => $journal]);
    }

    // Permanently delete a soft-deleted journal and its entries
    public function forceDestroy($id)
    {
        $journal = Journal::onlyTrashed()->where('_id', $id)->where('user_id', Auth::id())->firstOrFail();
        
        // Permanently delete all entries
        JournalEntry::withTrashed()->where('journal_id', $id)->forceDelete();
        
        // Permanently delete the journal
        $journal->forceDelete();
        
        return response()->json(['message' => 'Journal permanently deleted']);
    }

    // Empty all items from trash
    public function emptyTrash()
    {
        // Get all trashed journals for the current user
        $trashedJournals = Journal::onlyTrashed()->where('user_id', Auth::id())->get();
        
        foreach ($trashedJournals as $journal) {
            // Permanently delete all entries
            JournalEntry::withTrashed()->where('journal_id', $journal->_id)->forceDelete();
            // Permanently delete the journal
            $journal->forceDelete();
        }
        
        // Also delete individual trashed entries (not in trashed journals)
        // Derive active journal ids using direct query on journals collection
        $activeJournalIds = Journal::where('user_id', Auth::id())->pluck('_id');
        $idsNormalized = collect($activeJournalIds)
            ->flatMap(function ($id) {
                $asString = (string) $id;
                try {
                    $asObject = new ObjectId($asString);
                    return [$id, $asString, $asObject];
                } catch (\Throwable $e) {
                    return [$id, $asString];
                }
            })
            ->unique(strict: false)
            ->values()
            ->all();
        JournalEntry::onlyTrashed()
            ->whereIn('journal_id', $idsNormalized)
            ->forceDelete();
        
        return response()->json(['message' => 'Trash emptied successfully']);
    }

    // Reorder journal entries
    public function reorderEntries(Request $request, Journal $journal)
    {
        // Check if the user owns this journal
        if ($journal->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'entries' => 'required|array',
            'entries.*.id' => 'required|string',
            'entries.*.display_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['entries'] as $entryData) {
            JournalEntry::where('_id', $entryData['id'])
                ->where('journal_id', $journal->_id)
                ->update(['display_order' => $entryData['display_order']]);
        }

        return response()->json(['message' => 'Entries reordered successfully']);
    }
    
    // Restore a soft-deleted entry
    public function restoreEntry($entryId)
    {
        // Restrict to entries belonging to the current user's active journals (handle string/ObjectId)
        // Derive active journal ids using direct query on journals collection
        $activeJournalIds = Journal::where('user_id', Auth::id())->pluck('_id');
        $idsNormalized = collect($activeJournalIds)
            ->flatMap(function ($id) {
                $asString = (string) $id;
                try {
                    $asObject = new ObjectId($asString);
                    return [$id, $asString, $asObject];
                } catch (\Throwable $e) {
                    return [$id, $asString];
                }
            })
            ->unique(strict: false)
            ->values()
            ->all();

        $entry = JournalEntry::withTrashed()
            ->where('_id', $entryId)
            ->whereNotNull('deleted_at')
            ->whereIn('journal_id', $idsNormalized)
            ->firstOrFail();
        
        $entry->restore();
        
        return response()->json(['message' => 'Entry restored successfully', 'entry' => $entry->load('journal')]);
    }
    
    // Permanently delete a soft-deleted entry
    public function forceDestroyEntry($entryId)
    {
        // Restrict to entries belonging to the current user's active journals (handle string/ObjectId)
        // Derive active journal ids using direct query on journals collection
        $activeJournalIds = Journal::where('user_id', Auth::id())->pluck('_id');
        $idsNormalized = collect($activeJournalIds)
            ->flatMap(function ($id) {
                $asString = (string) $id;
                try {
                    $asObject = new ObjectId($asString);
                    return [$id, $asString, $asObject];
                } catch (\Throwable $e) {
                    return [$id, $asString];
                }
            })
            ->unique(strict: false)
            ->values()
            ->all();

        $entry = JournalEntry::onlyTrashed()
            ->where('_id', $entryId)
            ->whereIn('journal_id', $idsNormalized)
            ->firstOrFail();
        
        $entry->forceDelete();
        
        return response()->json(['message' => 'Entry permanently deleted']);
    }
}
