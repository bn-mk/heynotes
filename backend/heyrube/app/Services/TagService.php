<?php

namespace App\Services;

use App\Models\Tag;
use Illuminate\Support\Collection;

class TagService
{
    public function listNames(): Collection
    {
        return Tag::orderBy('name')->pluck('name');
    }

    public function create(string $name): string
    {
        $name = trim($name);
        $tag = Tag::firstOrCreate(['name' => $name]);
        return $tag;
    }
}
