<?php

namespace Tests\Feature\Api;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Link;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\MongoTestCase;

class LinksTest extends MongoTestCase
{
    public function test_create_list_graph_search_and_delete_links(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $userId = (string)($user->_id ?? $user->id);

        $j = Journal::create(['title' => 'Searchable Journal', 'user_id' => $userId]);
        $jid = (string)($j->_id ?? $j->id);
        $e = JournalEntry::create([
            'journal_id' => $jid,
            'user_id' => $userId,
            'content' => 'hello links world',
            'card_type' => 'text',
        ]);
        $eid = (string)($e->_id ?? $e->id);

        // Prevent self-link
        $this->postJson('/api/links', [
            'source_type' => 'entry',
            'source_id' => $eid,
            'target_type' => 'entry',
            'target_id' => $eid,
        ])->assertStatus(422);

        // Create link journal -> entry
        $this->postJson('/api/links', [
            'source_type' => 'journal',
            'source_id' => $jid,
            'target_type' => 'entry',
            'target_id' => $eid,
            'label' => 'relates to',
        ])->assertCreated();

        $this->assertSame(1, Link::where('user_id', $userId)->count());

        // List links for journal
        $this->getJson('/api/links?node_type=journal&node_id='.$jid)
            ->assertOk()
            ->assertJsonCount(1);

        // Graph endpoint
        $this->getJson('/api/graph')->assertOk()->assertJsonStructure([
            'nodes', 'edges'
        ]);

        // Search endpoint should find the entry by its content
        $this->getJson('/api/search?q=hello%20links&limit=10')
            ->assertOk()
            ->assertJsonStructure(['journals', 'entries']);

        // Delete link
        $this->deleteJson('/api/links', [
            'source_type' => 'journal',
            'source_id' => $jid,
            'target_type' => 'entry',
            'target_id' => $eid,
        ])->assertNoContent();

        $this->assertSame(0, Link::where('user_id', $userId)->count());
    }
}

