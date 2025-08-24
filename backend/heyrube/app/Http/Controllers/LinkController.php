<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MongoDB\BSON\ObjectId;

class LinkController extends Controller
{
    // Create a bi-directional (stored as undirected) link
    public function store(Request $request)
    {
        $validated = $request->validate([
            'source_type' => 'required|in:entry,journal',
            'source_id' => 'required|string',
            'target_type' => 'required|in:entry,journal',
            'target_id' => 'required|string',
            'label' => 'sometimes|string|max:64',
        ]);

        // Prevent self-link
        if ($validated['source_type'] === $validated['target_type'] && $validated['source_id'] === $validated['target_id']) {
            return response()->json(['message' => 'Cannot link an item to itself'], 422);
        }

        $userId = (string)(Auth::user()->_id ?? Auth::id());

        // Check if a link already exists between these EXACT two nodes (in either direction)
        // Using two separate queries for clarity and better MongoDB compatibility
        $existsForward = Link::where('user_id', $userId)
            ->where('source_type', $validated['source_type'])
            ->where('source_id', $validated['source_id'])
            ->where('target_type', $validated['target_type'])
            ->where('target_id', $validated['target_id'])
            ->first();
            
        $existsReverse = Link::where('user_id', $userId)
            ->where('source_type', $validated['target_type'])
            ->where('source_id', $validated['target_id'])
            ->where('target_type', $validated['source_type'])
            ->where('target_id', $validated['source_id'])
            ->first();
            
        $exists = $existsForward ?: $existsReverse;

        if ($exists) {
            return response()->json($exists, 200);
        }

        $link = Link::create([
            'user_id' => $userId,
            'source_type' => $validated['source_type'],
            'source_id' => $validated['source_id'],
            'target_type' => $validated['target_type'],
            'target_id' => $validated['target_id'],
            'label' => $validated['label'] ?? 'linked to',
        ]);

        return response()->json($link, 201);
    }

    // List links for a given node (entry or journal)
    public function index(Request $request)
    {
        $validated = $request->validate([
            'node_type' => 'required|in:entry,journal',
            'node_id' => 'required|string',
        ]);
        $userId = (string)(Auth::user()->_id ?? Auth::id());
        $links = Link::where('user_id', $userId)
            ->where(function ($q) use ($validated) {
                $q->where(function ($q2) use ($validated) {
                    $q2->where('source_type', $validated['node_type'])
                       ->where('source_id', $validated['node_id']);
                })->orWhere(function ($q2) use ($validated) {
                    $q2->where('target_type', $validated['node_type'])
                       ->where('target_id', $validated['node_id']);
                });
            })
            ->get();

        return response()->json($links);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'source_type' => 'required|in:entry,journal',
            'source_id' => 'required|string',
            'target_type' => 'required|in:entry,journal',
            'target_id' => 'required|string',
        ]);

        $userId = (string)(Auth::user()->_id ?? Auth::id());

        // Find the link, regardless of direction
        $link = Link::where('user_id', $userId)
            ->where(function ($q) use ($validated) {
                $q->where([
                    'source_type' => $validated['source_type'],
                    'source_id' => $validated['source_id'],
                    'target_type' => $validated['target_type'],
                    'target_id' => $validated['target_id'],
                ])->orWhere([
                    'source_type' => $validated['target_type'],
                    'source_id' => $validated['target_id'],
                    'target_type' => $validated['source_type'],
                    'target_id' => $validated['source_id'],
                ]);
            })
            ->first();

        if ($link) {
            $link->delete();
            return response()->json(null, 204);
        }

        return response()->json(['message' => 'Link not found.'], 404);
    }

    // Global graph for current user
    public function graph()
    {
        $userId = (string)(Auth::user()->_id ?? Auth::id());
        $links = Link::where('user_id', $userId)->get();

        // Collect node ids
        $entryIds = [];
        $journalIds = [];
        foreach ($links as $l) {
            if ($l->source_type === 'entry') $entryIds[] = $l->source_id;
            if ($l->target_type === 'entry') $entryIds[] = $l->target_id;
            if ($l->source_type === 'journal') $journalIds[] = $l->source_id;
            if ($l->target_type === 'journal') $journalIds[] = $l->target_id;
        }
        $entryIds = array_values(array_unique($entryIds));
        $journalIds = array_values(array_unique($journalIds));

        // Fetch nodes
        $entries = [];
        if (!empty($entryIds)) {
            // Accept both string and ObjectId
            $candidates = collect($entryIds)->flatMap(function ($id) {
                $arr = [(string)$id];
                try { $arr[] = new ObjectId((string)$id); } catch (\Throwable $e) {}
                return $arr;
            })->all();
            $entries = JournalEntry::whereIn('_id', $candidates)->get(['_id','content','journal_id','card_type','created_at'])
                ->map(function ($e) {
                    return [
                        'id' => (string)$e->_id,
                        'type' => 'entry',
                        'label' => $e->card_type === 'checkbox' ? 'Checklist' : (mb_substr($e->content ?? '', 0, 24) ?: 'Text Entry'),
                        'journal_id' => (string)$e->journal_id,
                    ];
                })->values()->all();
        }

        $journals = [];
        if (!empty($journalIds)) {
            $candidates = collect($journalIds)->flatMap(function ($id) {
                $arr = [(string)$id];
                try { $arr[] = new ObjectId((string)$id); } catch (\Throwable $e) {}
                return $arr;
            })->all();
            $journals = Journal::withTrashed()->whereIn('_id', $candidates)->get(['_id','title'])
                ->map(function ($j) {
                    return [
                        'id' => (string)$j->_id,
                        'type' => 'journal',
                        'label' => $j->title,
                    ];
                })->values()->all();
        }

        $nodes = array_values(array_unique(array_merge($entries, $journals), SORT_REGULAR));
        $edges = $links->map(function ($l) {
            return [
                'source' => [ 'id' => (string)$l->source_id, 'type' => $l->source_type ],
                'target' => [ 'id' => (string)$l->target_id, 'type' => $l->target_type ],
                'label' => $l->label ?? 'linked to',
            ];
        })->values()->all();

        return response()->json([
            'nodes' => $nodes,
            'edges' => $edges,
        ]);
    }

    // Global search over journals and entries
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);
        $q = $validated['q'];
        $limit = $validated['limit'] ?? 20;
        $userId = (string)(Auth::user()->_id ?? Auth::id());

        // Journals by title
        $journals = Journal::where('user_id', $userId)
            ->where('title', 'like', "%$q%")
            ->take($limit)
            ->get(['_id','title'])
            ->map(fn($j) => ['id' => (string)$j->_id, 'type' => 'journal', 'label' => $j->title]);

        // Entries by content (basic substring)
        $entries = JournalEntry::where('user_id', $userId)
            ->where('content', 'like', "%$q%")
            ->take($limit)
            ->get(['_id','content','journal_id','card_type'])
            ->map(fn($e) => [
                'id' => (string)$e->_id,
                'type' => 'entry',
                'label' => $e->card_type === 'checkbox' ? 'Checklist' : (mb_substr($e->content ?? '', 0, 60) ?: 'Text Entry'),
                'journal_id' => (string)$e->journal_id,
            ]);

        return response()->json([
            'journals' => $journals->values()->all(),
            'entries' => $entries->values()->all(),
        ]);
    }
}
