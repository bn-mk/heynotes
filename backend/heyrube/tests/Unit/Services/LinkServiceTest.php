<?php

namespace Tests\Unit\Services;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Link;
use App\Models\User;
use App\Services\LinkService;
use Tests\MongoTestCase;

class LinkServiceTest extends MongoTestCase
{
    public function test_prevent_self_link(): void
    {
        $svc = app(LinkService::class);
        $res = $svc->createLink([
            'source_type' => 'entry',
            'source_id' => 'e1',
            'target_type' => 'entry',
            'target_id' => 'e1',
        ], 'u1');
        $this->assertSame(422, $res['status']);
    }

    public function test_deduplicate_forward_and_reverse_links(): void
    {
        $user = User::factory()->create();
        $svc = app(LinkService::class);
        $userId = (string)($user->_id ?? $user->id);

        $res1 = $svc->createLink([
            'source_type' => 'journal',
            'source_id' => 'j1',
            'target_type' => 'entry',
            'target_id' => 'e1',
            'label' => 'linked to',
        ], $userId);
        $this->assertSame(201, $res1['status']);

        // Forward duplicate
        $res2 = $svc->createLink([
            'source_type' => 'journal',
            'source_id' => 'j1',
            'target_type' => 'entry',
            'target_id' => 'e1',
        ], $userId);
        $this->assertSame(200, $res2['status']);

        // Reverse duplicate
        $res3 = $svc->createLink([
            'source_type' => 'entry',
            'source_id' => 'e1',
            'target_type' => 'journal',
            'target_id' => 'j1',
        ], $userId);
        $this->assertSame(200, $res3['status']);

        $this->assertSame(1, Link::where('user_id', $userId)->count());
    }

    public function test_list_delete_graph_and_search(): void
    {
        $user = User::factory()->create();
        $userId = (string)($user->_id ?? $user->id);

        $j = Journal::create([ 'title' => 'Graph Journal', 'user_id' => $userId ]);
        $e = JournalEntry::create([
            'journal_id' => (string)($j->_id ?? $j->id),
            'user_id' => $userId,
            'content' => 'hello world entry content',
            'card_type' => 'text',
        ]);

        $svc = app(LinkService::class);
        $create = $svc->createLink([
            'source_type' => 'journal',
            'source_id' => (string)($j->_id ?? $j->id),
            'target_type' => 'entry',
            'target_id' => (string)($e->_id ?? $e->id),
            'label' => 'relates to',
        ], $userId);
        $this->assertSame(201, $create['status']);

        // listLinks should include this link regardless of direction
        $list1 = $svc->listLinks('journal', (string)($j->_id ?? $j->id), $userId);
        $this->assertCount(1, $list1);
        $list2 = $svc->listLinks('entry', (string)($e->_id ?? $e->id), $userId);
        $this->assertCount(1, $list2);

        // graph should produce nodes and edges
        $graph = $svc->graph($userId);
        $this->assertIsArray($graph['nodes']);
        $this->assertIsArray($graph['edges']);
        $this->assertNotEmpty($graph['nodes']);
        $this->assertNotEmpty($graph['edges']);

        // search should find both journal and entry by label/content
        $search = $svc->search('hello world', 5, $userId);
        $this->assertNotEmpty($search['entries']);

        // delete link
        $code = $svc->deleteLink([
            'source_type' => 'journal',
            'source_id' => (string)($j->_id ?? $j->id),
            'target_type' => 'entry',
            'target_id' => (string)($e->_id ?? $e->id),
        ], $userId);
        $this->assertSame(204, $code);

        $this->assertSame(0, Link::where('user_id', $userId)->count());
    }
}

