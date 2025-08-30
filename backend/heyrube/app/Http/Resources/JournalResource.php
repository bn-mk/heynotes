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
            // IMPORTANT: access raw attributes to avoid relation shadowing
            'tags' => (function () {
                try {
                    $attrs = method_exists($this->resource, 'getAttributes') ? $this->resource->getAttributes() : [];
                    $raw = $attrs['tags'] ?? (method_exists($this->resource, 'getRawOriginal') ? $this->resource->getRawOriginal('tags') : []);
                    return is_array($raw) ? array_values($raw) : [];
                } catch (\Throwable $e) {
                    return [];
                }
            })(),
            // You can add more fields here as necessary
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
