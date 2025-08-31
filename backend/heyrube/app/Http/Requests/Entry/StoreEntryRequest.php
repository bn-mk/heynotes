<?php

namespace App\Http\Requests\Entry;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'content' => 'required_if:card_type,text,audio|nullable|string',
            'card_type' => 'required|in:text,checkbox,spreadsheet,audio',
            'checkbox_items' => 'required_if:card_type,checkbox|nullable|array',
            'checkbox_items.*.text' => 'required_with:checkbox_items|string',
            'checkbox_items.*.checked' => 'required_with:checkbox_items|boolean',
            'mood' => 'nullable|string|in:happy,sad,tired,angry,anxious,grateful,calm,thoughtful,confident,stressed,loved,neutral',
        ];
    }
}
