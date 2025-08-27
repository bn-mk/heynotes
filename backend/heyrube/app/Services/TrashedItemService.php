<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalEntry;
use MongoDB\BSON\ObjectId;

class TrashedItemService
{
    public function getTrashedJournalsForUser(string $userId)
    {
        return Journal::onlyTrashed()
            ->where('user_id', $userId)
            ->with(['entries' => function ($query) {
                $query->withTrashed();
            }])
            ->get();
    }

    public function getTrashedEntriesForUser(string $userId)
    {
        $userIdVariants = [$userId];
        try { $userIdVariants[] = new ObjectId($userId); } catch (\Throwable $e) {}

        // Active and trashed journal IDs for this user (support both variants)
        $activeJournalIds = Journal::whereIn('user_id', $userIdVariants)->pluck('_id');
        $trashedJournalIds = Journal::onlyTrashed()->whereIn('user_id', $userIdVariants)->pluck('_id');

        $normalize = function ($ids) {
            return collect($ids)
                ->flatMap(function ($id) {
                    $asString = (string) $id;
                    $variants = [$id, $asString];
                    try { $variants[] = new ObjectId($asString); } catch (\Throwable $e) {}
                    return $variants;
                })
                ->unique(strict: false)
                ->values()
                ->all();
        };

        $activeIdsNormalized = $normalize($activeJournalIds);
        $trashedIdsNormalized = $normalize($trashedJournalIds);

        return JournalEntry::withTrashed()
            ->whereNotNull('deleted_at')
            ->where(function ($q) use ($activeIdsNormalized, $userIdVariants) {
                $q->whereIn('journal_id', $activeIdsNormalized)
                  ->orWhereIn('user_id', $userIdVariants);
            })
            ->whereNotIn('journal_id', $trashedIdsNormalized)
            ->with('journal:_id,title')
            ->orderBy('deleted_at', 'desc')
            ->get();
    }

    public function restoreJournal(string $userId, string $journalId)
    {
        $journal = Journal::onlyTrashed()
            ->where('_id', $journalId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $journal->restore();

        // Restore entries for this journal (handle ObjectId vs string)
        $jidVariants = [$journalId];
        try { $jidVariants[] = new ObjectId($journalId); } catch (\Throwable $e) {}
        JournalEntry::onlyTrashed()->whereIn('journal_id', $jidVariants)->restore();

        return $journal;
    }

    public function forceDeleteJournal(string $userId, string $journalId): void
    {
        $journal = Journal::onlyTrashed()
            ->where('_id', $journalId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Force delete entries for this journal (handle ObjectId vs string)
        $jidVariants = [$journalId];
        try { $jidVariants[] = new ObjectId($journalId); } catch (\Throwable $e) {}
        JournalEntry::withTrashed()->whereIn('journal_id', $jidVariants)->forceDelete();

        $journal->forceDelete();
    }

    public function emptyTrashForUser(string $userId): void
    {
        $trashedJournals = Journal::onlyTrashed()->where('user_id', $userId)->get();
        foreach ($trashedJournals as $journal) {
            $jid = (string) ($journal->_id ?? $journal->id);
            $jidVariants = [$jid];
            try { $jidVariants[] = new ObjectId($jid); } catch (\Throwable $e) {}
            JournalEntry::withTrashed()->whereIn('journal_id', $jidVariants)->forceDelete();
            $journal->forceDelete();
        }

        // Also delete individual trashed entries (not in trashed journals)
        $activeJournalIds = Journal::where('user_id', $userId)->pluck('_id');
        $idsNormalized = collect($activeJournalIds)
            ->flatMap(function ($id) {
                $asString = (string) $id;
                try {
                    $asObject = new ObjectId($asString);
                    return [$id, $asString, $asObject];
                } catch (\Throwable $e) {
                    return [$id, $asString];
                }
            })
            ->unique(strict: false)
            ->values()
            ->all();

        JournalEntry::onlyTrashed()
            ->whereIn('journal_id', $idsNormalized)
            ->forceDelete();
    }

    public function restoreEntry(string $userId, string $entryId)
    {
        $userIdVariants = [$userId];
        try { $userIdVariants[] = new ObjectId($userId); } catch (\Throwable $e) {}

        $activeJournalIds = Journal::whereIn('user_id', $userIdVariants)->pluck('_id');
        $trashedJournalIds = Journal::onlyTrashed()->whereIn('user_id', $userIdVariants)->pluck('_id');

        $normalize = function ($ids) {
            return collect($ids)
                ->flatMap(function ($id) {
                    $asString = (string) $id;
                    try {
                        $asObject = new ObjectId($asString);
                        return [$id, $asString, $asObject];
                    } catch (\Throwable $e) {
                        return [$id, $asString];
                    }
                })
                ->unique(strict: false)
                ->values()
                ->all();
        };

        $activeIdsNormalized = $normalize($activeJournalIds);
        $trashedIdsNormalized = $normalize($trashedJournalIds);

        $entryIdCandidates = [(string)$entryId];
        try { $entryIdCandidates[] = new ObjectId((string)$entryId); } catch (\Throwable $e) {}

        $entry = JournalEntry::withTrashed()
            ->whereNotNull('deleted_at')
            ->whereIn('_id', $entryIdCandidates)
            ->where(function ($q) use ($activeIdsNormalized, $userIdVariants) {
                $q->whereIn('journal_id', $activeIdsNormalized)
                  ->orWhereIn('user_id', $userIdVariants);
            })
            ->whereNotIn('journal_id', $trashedIdsNormalized)
            ->firstOrFail();

        $entry->restore();
        return $entry->load('journal');
    }

    public function forceDeleteEntry(string $userId, string $entryId): void
    {
        $userIdVariants = [$userId];
        try { $userIdVariants[] = new ObjectId($userId); } catch (\Throwable $e) {}

        $activeJournalIds = Journal::whereIn('user_id', $userIdVariants)->pluck('_id');
        $trashedJournalIds = Journal::onlyTrashed()->whereIn('user_id', $userIdVariants)->pluck('_id');

        $normalize = function ($ids) {
            return collect($ids)
                ->flatMap(function ($id) {
                    $asString = (string) $id;
                    try {
                        $asObject = new ObjectId($asString);
                        return [$id, $asString, $asObject];
                    } catch (\Throwable $e) {
                        return [$id, $asString];
                    }
                })
                ->unique(strict: false)
                ->values()
                ->all();
        };

        $activeIdsNormalized = $normalize($activeJournalIds);
        $trashedIdsNormalized = $normalize($trashedJournalIds);

        $entryIdCandidates = [(string)$entryId];
        try { $entryIdCandidates[] = new ObjectId((string)$entryId); } catch (\Throwable $e) {}

        $entry = JournalEntry::withTrashed()
            ->whereNotNull('deleted_at')
            ->whereIn('_id', $entryIdCandidates)
            ->where(function ($q) use ($activeIdsNormalized, $userIdVariants) {
                $q->whereIn('journal_id', $activeIdsNormalized)
                  ->orWhereIn('user_id', $userIdVariants);
            })
            ->whereNotIn('journal_id', $trashedIdsNormalized)
            ->firstOrFail();

        $entry->forceDelete();
    }
}
