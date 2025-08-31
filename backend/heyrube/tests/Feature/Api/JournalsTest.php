<?php

namespace Tests\Feature\Api;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\MongoTestCase;

class JournalsTest extends MongoTestCase
{
    public function test_store_and_update_journal_with_tags_dedupe(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $userId = (string)($user->_id ?? $user->id);

        $res = $this->postJson('/api/journals', [
            'title' => 'My J',
            'tags' => ['x', 'y', 'x'],
        ])->assertCreated();

        $id = (string)($res->json('data.id') ?? $res->json('id'));
        $this->assertNotEmpty($id);

        $tags1 = (function ($m) { $attrs = method_exists($m,'getAttributes') ? $m->getAttributes() : []; return $attrs['tags'] ?? []; })(Journal::find($id));
        $this->assertEquals(['x','y'], array_values($tags1));

        $res2 = $this->putJson("/api/journals/{$id}", [
            'tags' => ['a', 'a', 'b'],
        ])->assertOk();

        $tags2 = (function ($m) { $attrs = method_exists($m,'getAttributes') ? $m->getAttributes() : []; return $attrs['tags'] ?? []; })(Journal::find($id));
        $this->assertEquals(['a','b'], array_values($tags2));
    }

    public function test_entries_crud_reorder_and_pin(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $j = Journal::create(['title' => 'J', 'user_id' => (string)($user->_id ?? $user->id)]);
        $jid = (string)($j->_id ?? $j->id);

        // Create entries
        $e1 = $this->postJson("/api/journals/{$jid}/entries", [
            'card_type' => 'text',
            'content' => 'A',
        ])->assertCreated()->json('id');

        $e2 = $this->postJson("/api/journals/{$jid}/entries", [
            'card_type' => 'text',
            'content' => 'B',
        ])->assertCreated()->json('id');

        // Reorder and pin second
        $this->postJson("/api/journals/{$jid}/entries/reorder", [
            'entries' => [
                ['id' => $e1, 'display_order' => 1],
                ['id' => $e2, 'display_order' => 0, 'pinned' => true],
            ],
        ])->assertOk();

        $e1m = JournalEntry::find($e1);
        $e2m = JournalEntry::find($e2);
        $this->assertSame(0, $e1m->display_order);
        $this->assertTrue((bool)($e2m->pinned ?? false));
        $this->assertNull($e2m->display_order);

        // Unpin via explicit pin endpoint
        $this->postJson("/api/journals/{$jid}/entries/{$e2}/pin", ['pinned' => false])->assertOk();
        $this->assertFalse((bool)($e2m->fresh()->pinned ?? false));
    }
}

