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
            'hobbies', 'self-care', 'goals', 'inspiration', 'nature', 'food', 'pets', 'events', 'memories',
            'learning', 'projects', 'challenges', 'successes', 'reflections', 'dreams', 'travel', 'adventure',
            'career', 'gratitude', 'personal', 'relationships', 'wellness', 'creativity', 'mindfulness',
            'self-improvement', 'community', 'volunteering', 'spirituality', 'lifestyle', 'culture',
        ];
        
        foreach ($tags as $tag) {
            Tag::create([
                'name' => $tag,
            ]);
        }
    }
}

