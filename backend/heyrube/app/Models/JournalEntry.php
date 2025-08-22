<?php

namespace App\Models;

use App\Models\Journal;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'journal_id',
        'content',
        'mood',
        'display_order',
        'card_type',
        'checkbox_items',
        'created_at',
        'updated_at',
    ];
    
    protected $casts = [
        'checkbox_items' => 'array',
    ];

    protected $dates = ['deleted_at'];
    public function journal(): BelongsTo   
    {
        // For MongoDB: foreign key journal_id, owner key _id
        return $this->belongsTo(Journal::class, 'journal_id', '_id');
    }
}
