<?php

namespace Tests\Feature\Api;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\MongoTestCase;

class TrashTest extends MongoTestCase
{
    public function test_trash_endpoints_for_journals_and_entries(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $uid = (string)($user->_id ?? $user->id);

        // Create active journal + trashed journal
        $ja = Journal::create(['title' => 'Active', 'user_id' => $uid]);
        $jt = Journal::create(['title' => 'Trashed', 'user_id' => $uid]);

        // Entries: one in active journal to be trashed, one in journal that will be trashed
        $ea = JournalEntry::create(['journal_id' => (string)($ja->_id ?? $ja->id), 'user_id' => $uid, 'content' => 'keep entry', 'card_type' => 'text']);
        $et = JournalEntry::create(['journal_id' => (string)($jt->_id ?? $jt->id), 'user_id' => $uid, 'content' => 'drop entry', 'card_type' => 'text']);

        // Trash the entry in the active journal
        $ea->delete();
        // Trash the journal (and its entry)
        $jt->delete();
        $et->delete();

        // List trashed journals
        $this->getJson('/api/trash/journals')->assertOk()->assertJsonFragment(['title' => 'Trashed']);

        // List trashed entries (should include 'keep entry', exclude 'drop entry' as its journal is trashed)
        $entries = $this->getJson('/api/trash/entries')->assertOk()->json();
        $contents = array_map(fn($e) => $e['content'] ?? null, $entries);
        $this->assertContains('keep entry', $contents);

        // Restore journal
        $this->postJson('/api/trash/journals/'.(string)($jt->_id ?? $jt->id).'/restore')->assertOk();
        $this->getJson('/api/trash/journals')->assertOk()->assertJsonMissing(['title' => 'Trashed']);

        // Force delete entry (from earlier)
        $this->deleteJson('/api/trash/entries/'.(string)($ea->_id ?? $ea->id))->assertOk();

        // Empty trash (create one more trashed item first)
        $et2 = JournalEntry::create(['journal_id' => (string)($ja->_id ?? $ja->id), 'user_id' => $uid, 'content' => 'another', 'card_type' => 'text']);
        $et2->delete();
        $this->deleteJson('/api/trash/empty')->assertOk();
        $this->assertSame(0, Journal::onlyTrashed()->where('user_id', $uid)->count());
    }
}

