<?php

namespace Tests\Unit\Services;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalEntryService;
use App\Services\JournalService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\MongoTestCase;

class JournalServiceTest extends MongoTestCase
{
    public function test_create_and_update_dedupes_tags(): void
    {
        $user = User::factory()->create();
        $userId = (string)($user->_id ?? $user->id);
        $svc = app(JournalService::class);

        $j = $svc->createJournal($userId, ['title' => 'T', 'tags' => ['a', 'b', 'a']]);
        $rawTags = (function ($m) { $attrs = method_exists($m,'getAttributes') ? $m->getAttributes() : []; return $attrs['tags'] ?? []; })($j);
        $this->assertEquals(['a', 'b'], array_values($rawTags));

        $updated = $svc->updateJournal($j, ['tags' => ['c', 'c', 'a']]);
        $rawTags2 = (function ($m) { $attrs = method_exists($m,'getAttributes') ? $m->getAttributes() : []; return $attrs['tags'] ?? []; })($updated);
        $this->assertEquals(['c', 'a'], array_values($rawTags2));
        $this->assertEquals('T', $updated->title);
    }

    public function test_delete_soft_deletes_entries_and_journal_and_checks_owner(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $u1 = (string)($user1->_id ?? $user1->id);
        $u2 = (string)($user2->_id ?? $user2->id);

        $journalSvc = app(JournalService::class);
        $entrySvc = app(JournalEntryService::class);

        $j = $journalSvc->createJournal($u1, ['title' => 'T']);
        // add entries
        $entrySvc->createEntry($j, ['card_type' => 'text', 'content' => 'x']);
        $entrySvc->createEntry($j, ['card_type' => 'text', 'content' => 'y']);

        // unauthorized delete
        $this->expectException(HttpException::class);
        $journalSvc->deleteJournal($u2, $j);

        // authorized delete
        try {
            $journalSvc->deleteJournal($u1, $j);
        } catch (HttpException $e) {
            $this->fail('Should not throw for owner');
        }

        $this->assertNotNull($j->fresh()->deleted_at);
        $this->assertSame(2, JournalEntry::withTrashed()->where('journal_id', (string)($j->_id ?? $j->id))->whereNotNull('deleted_at')->count());
    }
}

