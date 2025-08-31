<?php

namespace Tests\Unit\Services;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalEntryService;
use Tests\MongoTestCase;

class JournalEntryServiceMoreTest extends MongoTestCase
{
    public function test_reorder_entries_with_pinned_and_display_order(): void
    {
        $user = User::factory()->create();
        $journal = Journal::create([
            'title' => 'J',
            'user_id' => (string)($user->_id ?? $user->id),
        ]);
        $svc = app(JournalEntryService::class);

        $e1 = $svc->createEntry($journal, ['card_type' => 'text', 'content' => 'A']);
        $e2 = $svc->createEntry($journal, ['card_type' => 'text', 'content' => 'B']);
        $e3 = $svc->createEntry($journal, ['card_type' => 'text', 'content' => 'C']);

        // Reorder: pin e3, set explicit order for others
        $svc->reorderEntries($journal, [
            ['id' => (string)($e1->_id ?? $e1->id), 'display_order' => 1],
            ['id' => (string)($e2->_id ?? $e2->id), 'display_order' => 0],
            ['id' => (string)($e3->_id ?? $e3->id), 'pinned' => true, 'display_order' => 2], // display_order should be nulled when pinned
        ]);

        $e1 = $e1->fresh();
        $e2 = $e2->fresh();
        $e3 = $e3->fresh();

        $this->assertTrue((bool)($e3->pinned ?? false));
        $this->assertNull($e3->display_order);

        // Non-pinned must be renumbered starting at 0 in the specified order [e2, e1]
        $this->assertSame(0, $e2->display_order);
        $this->assertSame(1, $e1->display_order);
    }

    public function test_update_entry_changes_card_type_and_moves_between_journals(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $j1 = Journal::create(['title' => 'J1', 'user_id' => (string)($user1->_id ?? $user1->id)]);
        $j2 = Journal::create(['title' => 'J2', 'user_id' => (string)($user2->_id ?? $user2->id)]);

        $svc = app(JournalEntryService::class);
        $e = $svc->createEntry($j1, [
            'card_type' => 'checkbox',
            'checkbox_items' => [ ['text' => 'X', 'checked' => false] ],
        ]);

        // Change to text and move to j2
        $svc->updateEntry($j1, $e, [
            'card_type' => 'text',
            'content' => 'now text',
            'journal_id' => (string)($j2->_id ?? $j2->id),
        ]);

        $e = $e->fresh();
        $this->assertEquals('text', $e->card_type);
        $this->assertEquals('now text', $e->content);
        $this->assertNull($e->checkbox_items);
        $this->assertSame((string)($j2->_id ?? $j2->id), (string)$e->journal_id);
        $this->assertSame((string)($j2->user_id), (string)$e->user_id);
    }
}

