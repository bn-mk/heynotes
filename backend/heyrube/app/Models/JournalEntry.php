<?php

namespace App\Models;

use App\Models\Journal;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';

    protected $fillable = [
        'journal_id',
        'user_id',
        'content',
        'mood',
        'pinned',
        'display_order',
        'card_type',
        'checkbox_items',
        'created_at',
        'updated_at',
    ];
    
    protected $casts = [
        'checkbox_items' => 'array',
        'pinned' => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    public function getRouteKeyName()
    {
        return '_id';
    }

    public function journal(): BelongsTo   
    {
        // For MongoDB: foreign key journal_id, owner key _id
        return $this->belongsTo(Journal::class, 'journal_id', '_id');
    }
}
