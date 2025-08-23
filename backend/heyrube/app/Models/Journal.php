<?php

namespace App\Models;

use App\Models\User;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;
use MongoDB\Laravel\Relations\EmbedsMany;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class Journal extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';

    protected $fillable = [
        'title',
        'user_id',
        'tags',
    ];

    protected $dates = ['deleted_at'];

    public function getRouteKeyName()
    {
        return '_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): EmbedsMany
    {
        return $this->embedsMany(Tag::class);
    }

    public function entries(): HasMany
    {
        // Use foreign key = journal_id, local key = _id (for MongoDB)
        return $this->hasMany(JournalEntry::class, 'journal_id', '_id');
    }

}
