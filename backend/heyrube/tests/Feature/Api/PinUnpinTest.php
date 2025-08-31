<?php

namespace Tests\Feature\Api;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\MongoTestCase;

class PinUnpinTest extends MongoTestCase
{
    public function test_pin_and_unpin_entry_updates_flags_and_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $uid = (string)($user->_id ?? $user->id);

        $j = Journal::create(['title' => 'J', 'user_id' => $uid]);
        $jid = (string)($j->_id ?? $j->id);

        // Create three unpinned entries with increasing created_at
        $e1 = JournalEntry::create(['journal_id' => $jid, 'user_id' => $uid, 'card_type' => 'text', 'content' => 'A', 'created_at' => now()->subMinutes(3)]);
        $e2 = JournalEntry::create(['journal_id' => $jid, 'user_id' => $uid, 'card_type' => 'text', 'content' => 'B', 'created_at' => now()->subMinutes(2)]);
        $e3 = JournalEntry::create(['journal_id' => $jid, 'user_id' => $uid, 'card_type' => 'text', 'content' => 'C', 'created_at' => now()->subMinutes(1)]);

        // Pin the middle one
        $this->postJson("/api/journals/{$jid}/entries/".(string)$e2->_id."/pin", ['pinned' => true])->assertOk();
        $e2->refresh();
        $this->assertTrue((bool)($e2->pinned ?? false));
        $this->assertNull($e2->display_order);

        // Unpinned should be renumbered 0..n-1
        $others = JournalEntry::where('journal_id', $jid)->whereNull('deleted_at')->where(function($q){ $q->whereNull('pinned')->orWhere('pinned', false); })->get();
        $orders = $others->pluck('display_order')->sort()->values()->all();
        $this->assertEquals(range(0, count($others)-1), $orders);

        // Unpin again
        $this->postJson("/api/journals/{$jid}/entries/".(string)$e2->_id."/pin", ['pinned' => false])->assertOk();
        $e2->refresh();
        $this->assertFalse((bool)($e2->pinned ?? false));
        $this->assertIsInt($e2->display_order);
    }

    public function test_pin_requires_boolean(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $uid = (string)($user->_id ?? $user->id);

        $j = Journal::create(['title' => 'J', 'user_id' => $uid]);
        $e = JournalEntry::create(['journal_id' => (string)$j->_id, 'user_id' => $uid, 'card_type' => 'text', 'content' => 'A']);

        $this->postJson("/api/journals/".(string)$j->_id."/entries/".(string)$e->_id."/pin", [])->assertStatus(422);
        $this->postJson("/api/journals/".(string)$j->_id."/entries/".(string)$e->_id."/pin", ['pinned' => 'yes'])->assertStatus(422);
    }

    public function test_pin_and_reorder_forbidden_for_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $uid = (string)($owner->_id ?? $owner->id);

        $j = Journal::create(['title' => 'J', 'user_id' => $uid]);
        $e = JournalEntry::create(['journal_id' => (string)$j->_id, 'user_id' => $uid, 'card_type' => 'text', 'content' => 'A']);

        Sanctum::actingAs($other);

        // Pin forbidden
        $this->postJson("/api/journals/".(string)$j->_id."/entries/".(string)$e->_id."/pin", ['pinned' => true])->assertStatus(403);

        // Reorder forbidden
        $this->postJson("/api/journals/".(string)$j->_id."/entries/reorder", ['entries' => [['id' => (string)$e->_id, 'display_order' => 0]]])->assertStatus(403);
    }
}

