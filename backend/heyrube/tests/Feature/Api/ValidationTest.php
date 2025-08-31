<?php

namespace Tests\Feature\Api;

use App\Models\Journal;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\MongoTestCase;

class ValidationTest extends MongoTestCase
{
    public function test_reorder_entries_requires_entry_ids(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $jid = (string)(Journal::create(['title' => 'J', 'user_id' => (string)($user->_id ?? $user->id)])->id);

        $this->postJson("/api/journals/{$jid}/entries/reorder", [
            'entries' => [
                ['display_order' => 0],
                ['display_order' => 1],
            ],
        ])->assertStatus(422);
    }
}

