<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\LinkService;

class LinkController extends Controller
{
    public function __construct(private LinkService $links) {}
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
        $result = $this->links->createLink($validated, $userId);
        return response()->json($result['payload'], $result['status']);
    }

    // List links for a given node (entry or journal)
    public function index(Request $request)
    {
        $validated = $request->validate([
            'node_type' => 'required|in:entry,journal',
            'node_id' => 'required|string',
        ]);
        $userId = (string)(Auth::user()->_id ?? Auth::id());
        $links = $this->links->listLinks($validated['node_type'], $validated['node_id'], $userId);
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
        $status = $this->links->deleteLink($validated, $userId);
        if ($status === 204) return response()->json(null, 204);
        return response()->json(['message' => 'Link not found.'], 404);
    }

    // Global graph for current user
    public function graph()
    {
        $userId = (string)(Auth::user()->_id ?? Auth::id());
        $graph = $this->links->graph($userId);
        return response()->json($graph);
    }

    // Global search over journals and entries
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);
        $q = $validated['q'];
        $limit = (int)($validated['limit'] ?? 20);
        $userId = (string)(Auth::user()->_id ?? Auth::id());
        $result = $this->links->search($q, $limit, $userId);
        return response()->json($result);
    }
}
