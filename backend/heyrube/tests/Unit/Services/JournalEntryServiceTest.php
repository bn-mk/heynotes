<?php

namespace Tests\Unit\Services;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalEntryService;
use Tests\MongoTestCase;

class JournalEntryServiceTest extends MongoTestCase
{
    public function test_create_checkbox_entry_generates_summary_and_order(): void
    {
        $user = User::factory()->create();
        $journal = Journal::create([
            'title' => 'My Journal',
            'user_id' => (string)($user->_id ?? $user->id),
            'tags' => [],
        ]);

        $service = app(JournalEntryService::class);

        $entry = $service->createEntry($journal, [
            'card_type' => 'checkbox',
            'checkbox_items' => [
                ['text' => 'Task A', 'checked' => true],
                ['text' => 'Task B', 'checked' => false],
            ],
        ]);

        $this->assertEquals('checkbox', $entry->card_type);
        $this->assertSame('1/2 completed', $entry->content);
        $this->assertFalse((bool)($entry->pinned ?? false));
        $this->assertSame(0, $entry->display_order);
    }

    public function test_pin_and_unpin_renumbers_non_pinned_entries(): void
    {
        $user = User::factory()->create();
        $journal = Journal::create([
            'title' => 'J',
            'user_id' => (string)($user->_id ?? $user->id),
        ]);
        $svc = app(JournalEntryService::class);

        $e1 = $svc->createEntry($journal, ['card_type' => 'text', 'content' => 'A']);
        usleep(1000); // ensure distinct timestamps for ordering fallbacks
        $e2 = $svc->createEntry($journal, ['card_type' => 'text', 'content' => 'B']);
        usleep(1000);
        $e3 = $svc->createEntry($journal, ['card_type' => 'text', 'content' => 'C']);

        // Pin the second entry
        $svc->pinEntry($journal, $e2, true);
        $e2->refresh();
        $this->assertTrue((bool)$e2->pinned);
        $this->assertNull($e2->display_order);

        $others = JournalEntry::where('journal_id', $journal->_id)
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn($e) => !(bool)($e->pinned ?? false))
            ->values();

        $orders = $others->pluck('display_order')->all();
        sort($orders);
        $this->assertSame(range(0, count($others) - 1), $orders, 'Non-pinned entries should have consecutive display_order values');

        // Unpin the entry
        $svc->pinEntry($journal, $e2, false);

        $all = JournalEntry::where('journal_id', $journal->_id)->whereNull('deleted_at')->get();
        $pinnedCount = $all->filter(fn($e) => (bool)($e->pinned ?? false))->count();
        $this->assertSame(0, $pinnedCount);

        $orders = $all->pluck('display_order')->filter(fn($v) => $v !== null)->sort()->values()->all();
        $this->assertSame(range(0, $all->count() - 1), $orders, 'All entries should be renumbered after unpin');
    }
}

