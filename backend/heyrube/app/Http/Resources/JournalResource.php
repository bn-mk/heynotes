<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JournalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'user_id' => (string) $this->user_id,
            'tags' => collect($this->tags)->pluck('name')->all(),
            // You can add more fields here as necessary
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
