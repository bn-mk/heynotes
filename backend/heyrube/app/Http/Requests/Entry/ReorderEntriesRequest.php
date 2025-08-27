<?php

namespace App\Http\Requests\Entry;

use Illuminate\Foundation\Http\FormRequest;

class ReorderEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entries' => 'required|array',
            'entries.*.id' => 'required|string',
            'entries.*.display_order' => 'nullable|integer|min:0',
            'entries.*.pinned' => 'nullable|boolean',
        ];
    }
}
