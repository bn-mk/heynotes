<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Journal;
use App\Models\JournalEntry;

class JournalEntrySeeder extends Seeder
{
    public function run(): void
    {
        $journals = Journal::all();

        $dummySets = [
            [
                'Saw beautiful mountains and rivers today. It was an unforgettable experience.',
                'Met friendly locals and tried new foods!',
            ],
            [
                'Finished a big project at work. Relief and pride!',
                'Brainstormed ideas for next quarter.',
                'Tough meeting in the morning but productive.',
            ],
            [
                'Grateful for family and a sunny morning.',
                'Had coffee with a friend and good conversation.',
                'Received a surprise act of kindness.',
                'Took a long walk and reflected on recent events.',
                'Made a new recipe for dinner tonight.',
            ],
        ];

        foreach ($journals as $i => $journal) {
            $texts = $dummySets[$i % count($dummySets)];
            foreach ($texts as $text) {
                JournalEntry::create([
                    'journal_id' => $journal->_id,
                    'content' => $text,
                ]);
            }
        }
    }
}

