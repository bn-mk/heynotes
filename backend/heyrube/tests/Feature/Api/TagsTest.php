<?php

namespace Tests\Feature\Api;

use App\Models\Tag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\MongoTestCase;

class TagsTest extends MongoTestCase
{
    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/tags', ['name' => 'x'])->assertStatus(401);
    }

    public function test_store_returns_string_and_creates_tag(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/tags', ['name' => 'newtag']);
        $res->assertCreated();
        $res->assertSee('newtag');

        $this->assertTrue(Tag::where('name', 'newtag')->exists());
    }

    public function test_index_returns_list_of_names(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Tag::insert([
            ['name' => 'alpha'],
            ['name' => 'beta'],
        ]);

        $res = $this->getJson('/api/tags')->assertOk();
        $res->assertJson(fn ($json) => $json->has(2));
    }
}

