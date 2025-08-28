<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string)($this->_id ?? $this->id),
            'journal_id' => (string)($this->journal_id),
            'title' => $this->title,
            'content' => $this->content,
            'card_type' => $this->card_type ?? 'text',
            'checkbox_items' => $this->checkbox_items,
            'mood' => $this->mood,
            'pinned' => (bool)($this->pinned ?? false),
            'display_order' => $this->display_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
