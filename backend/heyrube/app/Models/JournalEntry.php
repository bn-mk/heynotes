<?php

namespace App\Models;

use App\Models\Journal;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = [
        'journal_id',
        'content',
        'created_at',
        'updated_at',
    ];
    public function journal(): BelongsTo   
    {
        // For MongoDB: foreign key journal_id, owner key _id
        return $this->belongsTo(Journal::class, 'journal_id', '_id');
    }
}
