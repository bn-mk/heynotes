<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\MongoTestCase;

class LinksValidationTest extends MongoTestCase
{
    public function test_links_index_validates_node_type(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/links?node_type=bad&node_id=1')->assertStatus(422);
    }
}

