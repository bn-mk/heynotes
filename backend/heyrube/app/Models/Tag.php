<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;


class Tag extends Model
{
    protected $connection = 'mongodb';

    protected $fillable = ['name'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

}
