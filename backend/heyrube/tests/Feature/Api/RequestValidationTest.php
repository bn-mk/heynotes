<?php

namespace Tests\Feature\Api;

use App\Models\Journal;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\MongoTestCase;

class RequestValidationTest extends MongoTestCase
{
    private function actingUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        return $user;
    }

    // Journals: store/update
    public function test_journal_store_requires_title_and_valid_tags(): void
    {
        $this->actingUser();

        // Missing title
        $this->postJson('/api/journals', [ 'tags' => ['a'] ])->assertStatus(422);

        // Title too long
        $this->postJson('/api/journals', [ 'title' => Str::random(260) ])->assertStatus(422);

        // Tags must be array of strings
        $this->postJson('/api/journals', [ 'title' => 'T', 'tags' => 'not-an-array' ])->assertStatus(422);
        $this->postJson('/api/journals', [ 'title' => 'T', 'tags' => [ ['x' => 1] ] ])->assertStatus(422);
    }

    public function test_journal_update_validates_fields(): void
    {
        $user = $this->actingUser();
        $jid = (string)(Journal::create(['title' => 'J', 'user_id' => (string)($user->_id ?? $user->id)])->id);

        // Title too long
        $this->putJson("/api/journals/{$jid}", [ 'title' => Str::random(260) ])->assertStatus(422);

        // Tags invalid
        $this->putJson("/api/journals/{$jid}", [ 'tags' => 'bad' ])->assertStatus(422);
        $this->putJson("/api/journals/{$jid}", [ 'tags' => [ ['x' => 1] ] ])->assertStatus(422);
    }

    // Entries: store/update
    public function test_entry_store_validates_by_card_type_and_mood(): void
    {
        $user = $this->actingUser();
        $jid = (string)(Journal::create(['title' => 'J', 'user_id' => (string)($user->_id ?? $user->id)])->id);

        // card_type required
        $this->postJson("/api/journals/{$jid}/entries", [])->assertStatus(422);

        // text: content required
        $this->postJson("/api/journals/{$jid}/entries", [ 'card_type' => 'text' ])->assertStatus(422);

        // checkbox: items required & shape
        $this->postJson("/api/journals/{$jid}/entries", [ 'card_type' => 'checkbox' ])->assertStatus(422);
        $this->postJson("/api/journals/{$jid}/entries", [ 'card_type' => 'checkbox', 'checkbox_items' => [ ['checked' => true] ] ])->assertStatus(422);

        // invalid mood
        $this->postJson("/api/journals/{$jid}/entries", [ 'card_type' => 'text', 'content' => 'c', 'mood' => 'unknown' ])->assertStatus(422);

        // invalid card_type value
        $this->postJson("/api/journals/{$jid}/entries", [ 'card_type' => 'bad', 'content' => 'c' ])->assertStatus(422);
    }

    public function test_entry_update_validates_by_card_type_and_mood(): void
    {
        $user = $this->actingUser();
        $jid = (string)(Journal::create(['title' => 'J', 'user_id' => (string)($user->_id ?? $user->id)])->id);
        // Create a real entry to pass route binding
        $entryId = (string)(\App\Models\JournalEntry::create([
            'journal_id' => $jid,
            'user_id' => (string)($user->_id ?? $user->id),
            'card_type' => 'text',
            'content' => 'x',
        ])->id);

        // Missing required fields will be validated when changing card_type/content
        $this->putJson("/api/journals/{$jid}/entries/{$entryId}", [ 'card_type' => 'text' ])->assertStatus(422);
        $this->putJson("/api/journals/{$jid}/entries/{$entryId}", [ 'card_type' => 'checkbox', 'checkbox_items' => [ ['checked' => true] ] ])->assertStatus(422);
        $this->putJson("/api/journals/{$jid}/entries/{$entryId}", [ 'mood' => 'invalid' ])->assertStatus(422);
    }

    // Links: store validation
    public function test_links_store_validates_required_fields_and_types(): void
    {
        $this->actingUser();
        // Missing all
        $this->postJson('/api/links', [])->assertStatus(422);
        // Invalid types
        $this->postJson('/api/links', [
            'source_type' => 'bad','source_id' => '1', 'target_type' => 'entry', 'target_id' => '2'
        ])->assertStatus(422);
        $this->postJson('/api/links', [
            'source_type' => 'journal','source_id' => '1', 'target_type' => 'bad', 'target_id' => '2'
        ])->assertStatus(422);
    }

    // Tags: store validation
    public function test_tags_store_requires_name(): void
    {
        $this->actingUser();
        $this->postJson('/api/tags', [])->assertStatus(422);
        $this->postJson('/api/tags', ['name' => Str::random(300)])->assertStatus(422);
    }
}

