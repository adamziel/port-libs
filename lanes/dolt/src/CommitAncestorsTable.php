<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class CommitAncestorsTable
{
    public const COLUMNS = [
        'commit_hash',
        'parent_hash',
        'parent_index',
    ];

    /**
     * Project upstream `dolt_commit_ancestors` rows.
     *
     * Root commits emit one row with a null `parent_hash` and parent index 0.
     * Merge commits emit one row per parent, preserving the parent list order
     * used by `dolt_log --parents` and upstream's parent index column.
     *
     * @param list<array<string, mixed>> $commits
     * @param non-empty-string|list<non-empty-string>|null $commitHashFilter
     * @return list<array{commit_hash:string, parent_hash:string|null, parent_index:int}>
     */
    public function rows(array $commits, string|array|null $commitHashFilter = null, ?int $limit = null): array
    {
        $commits = $this->normalizeCommits($commits);
        $selected = $this->selectedHashes($commits, $commitHashFilter);
        $limit = $this->normalizeLimit($limit);

        $rows = [];
        foreach ($selected as $hash) {
            $parents = $commits[$hash]['parents'];
            if ($parents === []) {
                $rows[] = [
                    'commit_hash' => $hash,
                    'parent_hash' => null,
                    'parent_index' => 0,
                ];
            } else {
                foreach ($parents as $i => $parent) {
                    if (!isset($commits[$parent])) {
                        throw new \RuntimeException("Dolt commit parent not found: {$parent}");
                    }
                    $rows[] = [
                        'commit_hash' => $hash,
                        'parent_hash' => $parent,
                        'parent_index' => $i,
                    ];
                }
            }

            if ($limit !== null && count($rows) >= $limit) {
                return array_slice($rows, 0, $limit);
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $commits
     * @return array<string, array{commit_hash:non-empty-string, date:string, parents:list<non-empty-string>, commit_order:int}>
     */
    private function normalizeCommits(array $commits): array
    {
        $normalized = [];
        foreach ($commits as $i => $commit) {
            if (!is_array($commit)) {
                throw new \InvalidArgumentException("Dolt commit {$i} must be an array.");
            }

            $hash = $this->requiredString($commit['commit_hash'] ?? null, "Dolt commit {$i} commit_hash");
            if (isset($normalized[$hash])) {
                throw new \InvalidArgumentException("Duplicate Dolt commit hash: {$hash}");
            }

            $date = $commit['date'] ?? '';
            if (!is_string($date)) {
                throw new \InvalidArgumentException("Dolt commit {$hash} date must be a string when supplied.");
            }

            $explicitOrder = $commit['commit_order'] ?? null;
            if ($explicitOrder !== null && (!is_int($explicitOrder) || $explicitOrder < 0)) {
                throw new \InvalidArgumentException("Dolt commit {$hash} commit_order must be a non-negative integer.");
            }

            $normalized[$hash] = [
                'commit_hash' => $hash,
                'date' => $date,
                'parents' => $this->normalizeStringList($commit['parents'] ?? [], "Dolt commit {$hash} parents"),
                'commit_order' => $explicitOrder ?? -1,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, array{commit_hash:non-empty-string, date:string, parents:list<non-empty-string>, commit_order:int}> $commits
     * @return list<non-empty-string>
     */
    private function selectedHashes(array &$commits, string|array|null $commitHashFilter): array
    {
        if ($commitHashFilter !== null) {
            $hashes = $this->normalizeHashFilter($commitHashFilter);
            $selected = [];
            foreach ($hashes as $hash) {
                if (!isset($commits[$hash])) {
                    continue;
                }
                $this->commitOrder($hash, $commits, []);
                $selected[] = $hash;
            }

            return $selected;
        }

        foreach (array_keys($commits) as $hash) {
            $this->commitOrder($hash, $commits, []);
        }

        $ordered = array_values($commits);
        usort($ordered, static function (array $a, array $b): int {
            return [$b['commit_order'], $b['date'], $b['commit_hash']]
                <=> [$a['commit_order'], $a['date'], $a['commit_hash']];
        });

        return array_column($ordered, 'commit_hash');
    }

    /**
     * @param non-empty-string|list<non-empty-string> $filter
     * @return list<non-empty-string>
     */
    private function normalizeHashFilter(string|array $filter): array
    {
        if (is_string($filter)) {
            return [$this->requiredString($filter, 'Dolt commit ancestor commit_hash filter')];
        }

        $hashes = [];
        foreach ($filter as $hash) {
            $hashes[] = $this->requiredString($hash, 'Dolt commit ancestor commit_hash filter');
        }

        return array_values(array_unique($hashes));
    }

    /**
     * @param array<string, array{parents:list<non-empty-string>, commit_order:int}> $commits
     * @param array<string, true> $visiting
     */
    private function commitOrder(string $hash, array &$commits, array $visiting): int
    {
        if (!isset($commits[$hash])) {
            throw new \RuntimeException("Dolt commit parent not found: {$hash}");
        }
        if ($commits[$hash]['commit_order'] >= 0) {
            return $commits[$hash]['commit_order'];
        }
        if (isset($visiting[$hash])) {
            throw new \RuntimeException("Dolt commit graph contains a cycle at {$hash}.");
        }

        $visiting[$hash] = true;
        $maxParentOrder = 0;
        foreach ($commits[$hash]['parents'] as $parent) {
            $maxParentOrder = max($maxParentOrder, $this->commitOrder($parent, $commits, $visiting));
        }

        $commits[$hash]['commit_order'] = $maxParentOrder + 1;

        return $commits[$hash]['commit_order'];
    }

    /**
     * @param mixed $value
     */
    private function requiredString($value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("{$label} must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return list<non-empty-string>
     */
    private function normalizeStringList($value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("{$label} must be a list of strings.");
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException("{$label} must contain only non-empty strings.");
            }
            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private function normalizeLimit($value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('Dolt commit ancestor limit must be a non-negative integer.');
        }

        return $value;
    }
}
