<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tag;

class TagController extends Controller
{
    /**
     * Get all unique tags used by the authenticated user
     */
    public function index()
    {
       $tags = Tag::all()->pluck('name')->unique()->sort()->values();
        return response()->json($tags);
    }

    /**
     * Create a new tag (this would be handled by journal creation/update)
     * This endpoint might not be needed if tags are managed through journals
     */
    public function store(Request $request)
    {
        // Tags are typically created/managed through journal creation/updates
        // This could be used for standalone tag management if needed
        
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // For now, just return the tag name since tags are managed through journals
        return response()->json([
            'name' => $request->name,
            'message' => 'Tag noted. Tags are managed through journal creation and updates.'
        ], 201);
    }
}
