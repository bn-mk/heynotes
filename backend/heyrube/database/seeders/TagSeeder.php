<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tag;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = Tag::all();
        $tags = [
            'music', 'work', 'money', 'gifts', 'hunter', 'ellie', 'books', 'travel', 'health', 'family', 'friends',
            'hobbies', 'fitness', 'shopping', 'education', 'career', 'goals', 'projects', 'ideas', 'inspiration',
        ];
        
        foreach ($tags as $tag) {
            Tag::create([
                'name' => $tag,
            ]);
        }
    }
}

