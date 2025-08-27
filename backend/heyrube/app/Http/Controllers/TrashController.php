<?php

namespace App\Http\Controllers;

use App\Services\TrashedItemService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    public function __construct(private TrashedItemService $trashService) {}

    // Journals
    public function journals(Request $request)
    {
        $items = $this->trashService->getTrashedJournalsForUser((string)(Auth::user()->_id ?? Auth::id()));
        return response()->json($items);
    }

    public function restoreJournal(Request $request, $id)
    {
        $journal = $this->trashService->restoreJournal((string)(Auth::user()->_id ?? Auth::id()), (string)$id);
        return response()->json(['message' => 'Journal restored successfully', 'journal' => $journal]);
    }

    public function forceDestroyJournal(Request $request, $id)
    {
        $this->trashService->forceDeleteJournal((string)(Auth::user()->_id ?? Auth::id()), (string)$id);
        return response()->json(['message' => 'Journal permanently deleted']);
    }

    // Entries
    public function entries(Request $request)
    {
        $items = $this->trashService->getTrashedEntriesForUser((string)(Auth::user()->_id ?? Auth::id()));
        return response()->json($items);
    }

    public function restoreEntry(Request $request, $id)
    {
        $entry = $this->trashService->restoreEntry((string)(Auth::user()->_id ?? Auth::id()), (string)$id);
        return response()->json(['message' => 'Entry restored successfully', 'entry' => $entry]);
    }

    public function forceDestroyEntry(Request $request, $id)
    {
        $this->trashService->forceDeleteEntry((string)(Auth::user()->_id ?? Auth::id()), (string)$id);
        return response()->json(['message' => 'Entry permanently deleted']);
    }

    public function empty(Request $request)
    {
        $this->trashService->emptyTrashForUser((string)(Auth::user()->_id ?? Auth::id()));
        return response()->json(['message' => 'Trash emptied successfully']);
    }
}
