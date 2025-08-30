<?php

namespace App\Services;

use App\Models\Journal;

class JournalService
{
    public function createJournal(string $userId, array $data): Journal
    {
        return Journal::create([
            'user_id' => $userId,
            'title' => $data['title'],
            'tags' => array_values(array_unique($data['tags'] ?? [])),
        ]);
    }

    public function updateJournal(Journal $journal, array $data): Journal
    {
        $update = [];
        if (array_key_exists('title', $data)) {
            $update['title'] = $data['title'];
        }
        if (array_key_exists('tags', $data)) {
            $update['tags'] = array_values(array_unique($data['tags']));
        }
        $journal->update($update);
        return $journal->fresh();
    }

    public function deleteJournal(string $userId, Journal $journal): void
    {
        if ($journal->user_id !== $userId) {
            abort(403);
        }
        $journal->entries()->delete();
        $journal->delete();
    }
}
