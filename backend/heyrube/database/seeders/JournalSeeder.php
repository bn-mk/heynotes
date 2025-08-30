<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Journal;
use App\Models\Tag;

class JournalSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (!$user) return;
        $tags = Tag::all()->pluck('name')->toArray();

        $journals = [
            ['title' => 'Misc.', 'tags' => array_splice($tags, 0, 3)],
            ['title' => 'Lyrics & Poems', 'tags' => array_splice($tags, 0, 3)],
            ['title' => 'Music Ideas', 'tags' => array_splice($tags,0, 2)],
        ];

        foreach ($journals as $j) {
            Journal::create([
                'title' => $j['title'],
                'tags' => $j['tags'],
                'user_id' => $user->id,
            ]);
        }
    }
}

