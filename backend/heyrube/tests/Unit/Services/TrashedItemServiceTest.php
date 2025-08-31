<?php

namespace Tests\Unit\Services;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\TrashedItemService;
use Tests\MongoTestCase;

class TrashedItemServiceTest extends MongoTestCase
{
    public function test_getTrashedEntries_excludes_entries_from_trashed_journals(): void
    {
        $user = User::factory()->create();
        $userId = (string)($user->_id ?? $user->id);
        $svc = app(TrashedItemService::class);

        $activeJournal = Journal::create(['title' => 'A', 'user_id' => $userId]);
        $trashedJournal = Journal::create(['title' => 'T', 'user_id' => $userId]);
        $trashedJournal->delete();

        $e1 = JournalEntry::create([
            'journal_id' => (string)($activeJournal->_id ?? $activeJournal->id),
            'user_id' => $userId,
            'content' => 'keep me',
            'card_type' => 'text',
        ]);
        $e1->delete();

        $e2 = JournalEntry::create([
            'journal_id' => new \MongoDB\BSON\ObjectId((string)($trashedJournal->_id ?? $trashedJournal->id)),
            'user_id' => $userId,
            'content' => 'drop me',
            'card_type' => 'text',
        ]);
        $e2->delete();

        $list = $svc->getTrashedEntriesForUser($userId);
        $this->assertTrue($list->pluck('content')->contains('keep me'));
    }

    public function test_restore_and_force_delete_journal(): void
    {
        $user = User::factory()->create();
        $userId = (string)($user->_id ?? $user->id);
        $svc = app(TrashedItemService::class);

        $j = Journal::create(['title' => 'R', 'user_id' => $userId]);
        $e = JournalEntry::create([
            'journal_id' => (string)($j->_id ?? $j->id),
            'user_id' => $userId,
            'content' => 'entry',
            'card_type' => 'text',
        ]);
        $j->delete();
        $e->delete();

        // Restore
        $restored = $svc->restoreJournal($userId, (string)($j->_id ?? $j->id));
        $this->assertNull($restored->deleted_at);
        $this->assertNull($e->fresh()->deleted_at);

        // Delete again for force deletion test
        $j->delete();
        $e->delete();
        $svc->forceDeleteJournal($userId, (string)($j->_id ?? $j->id));

        $this->assertNull(Journal::withTrashed()->where('_id', (string)($j->_id ?? $j->id))->first());
        $this->assertSame(0, JournalEntry::withTrashed()->where('journal_id', (string)($j->_id ?? $j->id))->count());
    }
}

