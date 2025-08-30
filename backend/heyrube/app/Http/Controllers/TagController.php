<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tag\StoreTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Services\TagService;

class TagController extends Controller
{

    public function __construct(private TagService $tagService) {}

    /**
     * Get all unique tags used by the authenticated user
     */
    public function index()
    {
        $tags = $this->tagService->listNames();
        return response()->json($tags);
    }

    /**
     * Create a new tag (this would be handled by journal creation/update)
     * This endpoint might not be needed if tags are managed through journals
     */
    public function store(StoreTagRequest $request)
    {
        $validated = $request->validated();
        $name = $validated['name'] ?? '';

        $tag = $this->tagService->create($name);

        // Return just the created tag name for simplicity (frontend expects a string)
        return response()->json($tag->name, 201);
    }
}
