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
    public function store(StoreTagRequest $request): TagResource
    {
        $validated = $request->validated();
        $update = [];

        if (array_key_exists('name', $validated)) {
            $update['name'] = array_values(array_unique($validated['name']));
        }

        $tag = $this->tagService->create($update['name']);

        
        if ($request->wantsJson()) {
            return response()->json(new TagResource($tag), 201);
        }

        return new TagResource($tag);
    }
}
