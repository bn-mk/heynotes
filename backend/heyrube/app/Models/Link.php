<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Link extends Model
{
    protected $connection = 'mongodb';

    protected $fillable = [
        'user_id',
        'source_type', // 'entry' | 'journal'
        'source_id',
        'target_type', // 'entry' | 'journal'
        'target_id',
        'label',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
