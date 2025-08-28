<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Link;
use MongoDB\BSON\ObjectId;

class LinkService
{
    public function createLink(array $data, string $userId): array
    {
        // Prevent self-link
        if ($data['source_type'] === $data['target_type'] && $data['source_id'] === $data['target_id']) {
            return ['status' => 422, 'payload' => ['message' => 'Cannot link an item to itself']];
        }

        $existsForward = Link::where('user_id', $userId)
            ->where('source_type', $data['source_type'])
            ->where('source_id', $data['source_id'])
            ->where('target_type', $data['target_type'])
            ->where('target_id', $data['target_id'])
            ->first();
        $existsReverse = Link::where('user_id', $userId)
            ->where('source_type', $data['target_type'])
            ->where('source_id', $data['target_id'])
            ->where('target_type', $data['source_type'])
            ->where('target_id', $data['source_id'])
            ->first();

        $exists = $existsForward ?: $existsReverse;
        if ($exists) {
            return ['status' => 200, 'payload' => $exists];
        }

        $link = Link::create([
            'user_id' => $userId,
            'source_type' => $data['source_type'],
            'source_id' => $data['source_id'],
            'target_type' => $data['target_type'],
            'target_id' => $data['target_id'],
            'label' => $data['label'] ?? 'linked to',
        ]);
        return ['status' => 201, 'payload' => $link];
    }

    public function listLinks(string $nodeType, string $nodeId, string $userId)
    {
        return Link::where('user_id', $userId)
            ->where(function ($q) use ($nodeType, $nodeId) {
                $q->where(function ($q2) use ($nodeType, $nodeId) {
                    $q2->where('source_type', $nodeType)
                       ->where('source_id', $nodeId);
                })->orWhere(function ($q2) use ($nodeType, $nodeId) {
                    $q2->where('target_type', $nodeType)
                       ->where('target_id', $nodeId);
                });
            })
            ->get();
    }

    public function deleteLink(array $data, string $userId): int
    {
        $link = Link::where('user_id', $userId)
            ->where(function ($q) use ($data) {
                $q->where([
                    'source_type' => $data['source_type'],
                    'source_id' => $data['source_id'],
                    'target_type' => $data['target_type'],
                    'target_id' => $data['target_id'],
                ])->orWhere([
                    'source_type' => $data['target_type'],
                    'source_id' => $data['target_id'],
                    'target_type' => $data['source_type'],
                    'target_id' => $data['source_id'],
                ]);
            })
            ->first();
        if ($link) {
            $link->delete();
            return 204;
        }
        return 404;
    }

    public function graph(string $userId): array
    {
        $links = Link::where('user_id', $userId)->get();
        $entryIds = [];
        $journalIds = [];
        foreach ($links as $l) {
            if ($l->source_type === 'entry') $entryIds[] = $l->source_id;
            if ($l->target_type === 'entry') $entryIds[] = $l->target_id;
            if ($l->source_type === 'journal') $journalIds[] = $l->source_id;
            if ($l->target_type === 'journal') $journalIds[] = $l->target_id;
        }
        $entryIds = array_values(array_unique($entryIds));
        $journalIds = array_values(array_unique($journalIds));

        $entries = [];
        if (!empty($entryIds)) {
            $candidates = collect($entryIds)->flatMap(function ($id) {
                $arr = [(string)$id];
                try { $arr[] = new ObjectId((string)$id); } catch (\Throwable $e) {}
                return $arr;
            })->all();
            $entries = JournalEntry::whereIn('_id', $candidates)->get(['_id','title','content','journal_id','card_type','created_at'])
                ->map(function ($e) {
                    if ($e->title && trim($e->title) !== '') {
                        $label = $e->title;
                    } else if ($e->card_type === 'checkbox') {
                        $label = 'Checklist';
                    } else {
                        // Extract first three words from content
                        $content = strip_tags($e->content ?? '');
                        $words = preg_split('/\s+/', trim($content), 4);
                        if (count($words) >= 3 && !empty(trim($words[0]))) {
                            $label = implode(' ', array_slice($words, 0, 3)) . '...';
                        } else {
                            $label = 'Text Entry';
                        }
                    }
                    return [
                        'id' => (string)$e->_id,
                        'type' => 'entry',
                        'label' => $label,
                        'journal_id' => (string)$e->journal_id,
                    ];
                })->values()->all();
        }

        $journals = [];
        if (!empty($journalIds)) {
            $candidates = collect($journalIds)->flatMap(function ($id) {
                $arr = [(string)$id];
                try { $arr[] = new ObjectId((string)$id); } catch (\Throwable $e) {}
                return $arr;
            })->all();
            $journals = Journal::withTrashed()->whereIn('_id', $candidates)->get(['_id','title'])
                ->map(function ($j) {
                    return [
                        'id' => (string)$j->_id,
                        'type' => 'journal',
                        'label' => $j->title,
                    ];
                })->values()->all();
        }

        $nodes = array_values(array_unique(array_merge($entries, $journals), SORT_REGULAR));
        $edges = $links->map(function ($l) {
            return [
                'source' => [ 'id' => (string)$l->source_id, 'type' => $l->source_type ],
                'target' => [ 'id' => (string)$l->target_id, 'type' => $l->target_type ],
                'label' => $l->label ?? 'linked to',
            ];
        })->values()->all();

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    public function search(string $q, int $limit, string $userId): array
    {
        $journals = Journal::where('user_id', $userId)
            ->where('title', 'like', "%$q%")
            ->take($limit)
            ->get(['_id','title'])
            ->map(fn($j) => ['id' => (string)$j->_id, 'type' => 'journal', 'label' => $j->title])
            ->values()->all();

        $entries = JournalEntry::where('user_id', $userId)
            ->where(function($q2) use ($q) {
                $q2->where('content', 'like', "%$q%")
                   ->orWhere('title', 'like', "%$q%");
            })
            ->take($limit)
            ->get(['_id','title','content','journal_id','card_type'])
            ->map(function ($e) {
                if ($e->title && trim($e->title) !== '') {
                    $label = $e->title;
                } else if ($e->card_type === 'checkbox') {
                    $label = 'Checklist';
                } else {
                    // Extract first three words from content
                    $content = strip_tags($e->content ?? '');
                    $words = preg_split('/\s+/', trim($content), 4);
                    if (count($words) >= 3 && !empty(trim($words[0]))) {
                        $label = implode(' ', array_slice($words, 0, 3)) . '...';
                    } else {
                        $label = 'Text Entry';
                    }
                }
                return [
                    'id' => (string)$e->_id,
                    'type' => 'entry',
                    'label' => $label,
                    'journal_id' => (string)$e->journal_id,
                ];
            })
            ->values()->all();

        return ['journals' => $journals, 'entries' => $entries];
    }
}
