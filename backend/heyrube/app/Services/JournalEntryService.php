<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;

class JournalEntryService
{
    public function getJournalEntries(Journal $journal): Collection
    {
        return $journal->entries()->orderBy('created_at', 'desc')->get();
    }

    public function createEntry(Journal $journal, array $data): JournalEntry
    {
        $entryData = [
            'card_type' => $data['card_type'] ?? 'text',
            'title' => $data['title'] ?? null,
            'mood' => $data['mood'] ?? null,
            'user_id' => $journal->user_id,
        ];
        if (in_array($data['card_type'] ?? 'text', ['text', 'spreadsheet', 'audio'], true)) {
            $entryData['content'] = $data['content'] ?? null;
        } elseif (($data['card_type'] ?? '') === 'checkbox') {
            $entryData['checkbox_items'] = $data['checkbox_items'] ?? [];
            $entryData['content'] = $this->generateCheckboxSummary($entryData['checkbox_items']);
        }
        $entry = $journal->entries()->create($entryData);
        $this->renumberJournalEntries($journal, (string) ($entry->_id ?? $entry->id));
        return $entry->refresh();
    }

    public function updateEntry(Journal $journal, JournalEntry $entry, array $data): JournalEntry
    {
        $updateData = [
            'journal_id' => $data['journal_id'] ?? $entry->journal_id,
            'user_id' => $entry->user_id,
        ];
        if (isset($data['journal_id']) && $data['journal_id'] !== $entry->journal_id) {
            $targetUserId = Journal::withTrashed()->where('_id', $data['journal_id'])->value('user_id');
            if ($targetUserId) {
                $updateData['user_id'] = $targetUserId;
            }
        }
        if (isset($data['card_type'])) {
            $updateData['card_type'] = $data['card_type'];
        }
        if (array_key_exists('mood', $data)) {
            $updateData['mood'] = $data['mood'];
        }
        if (array_key_exists('title', $data)) {
            $updateData['title'] = $data['title'];
        }
        $cardType = $data['card_type'] ?? $entry->card_type ?? 'text';
        if (in_array($cardType, ['text', 'spreadsheet', 'audio'], true)) {
            $updateData['content'] = $data['content'] ?? null;
            $updateData['checkbox_items'] = null;
        } elseif ($cardType === 'checkbox') {
            $updateData['checkbox_items'] = $data['checkbox_items'] ?? [];
            $updateData['content'] = $this->generateCheckboxSummary($updateData['checkbox_items']);
        }
        $entry->update($updateData);
        return $entry;
    }

    public function deleteEntry(Journal $journal, JournalEntry $entry): void
    {
        $entry->delete();
        $this->renumberJournalEntries($journal);
    }

    public function reorderEntries(Journal $journal, array $entries): void
    {
        foreach ($entries as $entryData) {
            $query = JournalEntry::where('_id', $entryData['id'])
                ->where('journal_id', $journal->_id);
            $update = [];
            if (array_key_exists('pinned', $entryData)) {
                $update['pinned'] = (bool) $entryData['pinned'];
            }
            if (array_key_exists('display_order', $entryData)) {
                if (($entryData['pinned'] ?? false) === true) {
                    $update['display_order'] = null;
                } else {
                    $update['display_order'] = $entryData['display_order'];
                }
            }
            if (!empty($update)) {
                $query->update($update);
            }
        }
        $this->renumberJournalEntries($journal);
    }

    public function pinEntry(Journal $journal, JournalEntry $entry, bool $pinned): void
    {
        $entry->update([
            'pinned' => $pinned,
            'display_order' => $pinned ? null : $entry->display_order,
        ]);
        $this->renumberJournalEntries($journal, $pinned ? (string) ($entry->_id ?? $entry->id) : null);
    }

    private function generateCheckboxSummary(array $items): string
    {
        if (empty($items)) return 'Checklist';
        $checked = array_filter($items, fn($i) => $i['checked'] ?? false);
        return count($checked) . '/' . count($items) . ' completed';
    }

    private function renumberJournalEntries(Journal $journal, ?string $pinFirstId = null): void
    {
        // Fetch active entries
        $all = JournalEntry::where('journal_id', $journal->_id)
            ->whereNull('deleted_at')
            ->get();
        if ($all->isEmpty()) {
            return;
        }
        $pinned = $all->filter(fn($e) => (bool) ($e->pinned ?? false))->values();
        $others = $all->reject(fn($e) => (bool) ($e->pinned ?? false))->values();
        $ordered = $others->filter(fn($e) => !is_null($e->display_order))->sortBy('display_order')->values();
        $unordered = $others->filter(fn($e) => is_null($e->display_order))
            ->sortByDesc(function ($e) {
                $c = $e->created_at ?? null;
                if ($c instanceof \Carbon\Carbon) return $c->getTimestamp();
                if (is_numeric($c)) return (int)$c;
                if (is_string($c)) return strtotime($c) ?: 0;
                return 0;
            })->values();
        $merged = $ordered->concat($unordered)->values();
        if ($pinFirstId) {
            $merged = $merged->reject(function ($e) use ($pinFirstId) {
                return (string) ($e->_id ?? $e->id) === (string) $pinFirstId;
            })->values();
            $candidate = $others->first(function ($e) use ($pinFirstId) {
                return (string) ($e->_id ?? $e->id) === (string) $pinFirstId;
            });
            if ($candidate) {
                $merged->prepend($candidate);
            }
        }
        foreach ($pinned as $e) {
            if (!is_null($e->display_order)) {
                JournalEntry::where('_id', $e->_id)->update(['display_order' => null]);
            }
        }
        $i = 0;
        foreach ($merged as $e) {
            $newOrder = $i++;
            if ($e->display_order !== $newOrder) {
                JournalEntry::where('_id', $e->_id)->update(['display_order' => $newOrder]);
            }
        }
    }
}
