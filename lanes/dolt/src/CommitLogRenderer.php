<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class CommitLogRenderer
{
    public const OPERATION_ADDED = 'added';
    public const OPERATION_DELETED = 'deleted';
    public const OPERATION_MODIFIED = 'modified';

    /**
     * Render projected `dolt_log` rows in the CLI-oriented formats used by
     * upstream `dolt log`.
     *
     * @param list<array<string, scalar|null>> $rows
     * @param array{oneline?:bool, parents?:bool, stat?:bool, diffStats?:array<string, list<array<string, mixed>>>, diffStatsByCommit?:array<string, list<array<string, mixed>>>} $options
     */
    public function render(array $rows, array $options = []): string
    {
        $oneline = $this->boolOption($options, 'oneline');
        $parents = $this->boolOption($options, 'parents');
        $stat = $this->boolOption($options, 'stat');
        $statsByCommit = $this->normalizeStatsByCommit($options['diffStatsByCommit'] ?? $options['diffStats'] ?? []);

        $lines = [];
        foreach ($rows as $row) {
            $commitHash = $this->requiredString($row['commit_hash'] ?? null, 'Dolt log commit_hash');
            $parentHashes = $this->parentHashes($row['parents'] ?? null);
            if ($oneline) {
                $lines[] = $this->oneline($row, $commitHash, $parentHashes, $parents);
                if ($stat && count($parentHashes) === 1) {
                    array_push($lines, ...$this->diffStatLines($statsByCommit[$commitHash] ?? []));
                }
                continue;
            }

            array_push($lines, ...$this->defaultLines($row, $commitHash, $parentHashes, $parents));
            if ($stat && count($parentHashes) === 1) {
                $statLines = $this->diffStatLines($statsByCommit[$commitHash] ?? []);
                if ($statLines !== []) {
                    array_push($lines, ...$statLines);
                    $lines[] = '';
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, scalar|null> $row
     * @param list<non-empty-string> $parentHashes
     */
    private function oneline(array $row, string $commitHash, array $parentHashes, bool $parents): string
    {
        $head = $commitHash;
        if ($parents && $parentHashes !== []) {
            $head .= ' ' . implode(' ', $parentHashes);
        }

        $refs = $this->nullableString($row['refs'] ?? null, 'Dolt log refs');
        if ($refs !== null && $refs !== '') {
            $head .= ' (' . $refs . ')';
        }

        return $head . ' ' . str_replace("\n", ' ', $this->stringValue($row['message'] ?? null, 'Dolt log message'));
    }

    /**
     * @param array<string, scalar|null> $row
     * @param list<non-empty-string> $parentHashes
     * @return list<string>
     */
    private function defaultLines(array $row, string $commitHash, array $parentHashes, bool $parents): array
    {
        $head = 'commit ' . $commitHash;
        if ($parents && $parentHashes !== []) {
            $head .= ' ' . implode(' ', $parentHashes);
        }

        $refs = $this->nullableString($row['refs'] ?? null, 'Dolt log refs');
        if ($refs !== null && $refs !== '') {
            $head .= ' (' . $refs . ')';
        }

        $lines = [$head];
        if (count($parentHashes) > 1) {
            $lines[] = 'Merge: ' . implode(' ', $parentHashes);
        }

        $author = $this->requiredString($row['author'] ?? $row['committer'] ?? null, 'Dolt log author');
        $authorEmail = $this->requiredString($row['author_email'] ?? $row['email'] ?? null, 'Dolt log author_email');
        $date = $this->requiredString($row['author_date'] ?? $row['date'] ?? null, 'Dolt log date');
        $lines[] = 'Author: ' . $author . ' <' . $authorEmail . '>';
        $lines[] = 'Date:  ' . $date;
        $lines[] = '';

        foreach (explode("\n", $this->stringValue($row['message'] ?? null, 'Dolt log message')) as $messageLine) {
            $lines[] = "\t" . $messageLine;
        }
        $lines[] = '';

        return $lines;
    }

    /**
     * @param list<array{table:non-empty-string, operation:string, adds:int, modifications:int, deletes:int}> $stats
     * @return list<string>
     */
    private function diffStatLines(array $stats): array
    {
        $modified = [];
        $added = [];
        $deleted = [];
        foreach ($stats as $stat) {
            if ($stat['operation'] === self::OPERATION_MODIFIED) {
                $total = $stat['adds'] + $stat['modifications'] + $stat['deletes'];
                if ($total > 0) {
                    $modified[$stat['table']] = $stat;
                }
                continue;
            }
            if ($stat['operation'] === self::OPERATION_ADDED) {
                $added[] = $stat['table'];
                continue;
            }
            if ($stat['operation'] === self::OPERATION_DELETED) {
                $deleted[] = $stat['table'];
            }
        }

        ksort($modified, SORT_STRING);
        sort($added, SORT_STRING);
        sort($deleted, SORT_STRING);

        $lines = [];
        if ($modified !== []) {
            $maxNameLen = max(array_map('strlen', array_keys($modified)));
            $maxModCount = 0;
            $rowsAdded = 0;
            $rowsModified = 0;
            $rowsDeleted = 0;
            foreach ($modified as $stat) {
                $modCount = $stat['adds'] + $stat['modifications'] + $stat['deletes'];
                $maxModCount = max($maxModCount, $modCount);
                $rowsAdded += $stat['adds'];
                $rowsModified += $stat['modifications'];
                $rowsDeleted += $stat['deletes'];
            }

            $modCountStrLen = strlen((string) $maxModCount);
            foreach ($modified as $table => $stat) {
                $modCount = $stat['adds'] + $stat['modifications'] + $stat['deletes'];
                $lines[] = ' '
                    . str_pad($table, $maxNameLen)
                    . ' | '
                    . str_pad((string) $modCount, $modCountStrLen)
                    . ' '
                    . $this->visualizeChanges($stat, $maxModCount);
            }

            $lines[] = sprintf(
                ' %d tables changed, %d rows added(+), %d rows modified(*), %d rows deleted(-)',
                count($modified),
                $rowsAdded,
                $rowsModified,
                $rowsDeleted,
            );
        }

        foreach ($added as $table) {
            $lines[] = ' ' . $table . ' added';
        }
        foreach ($deleted as $table) {
            $lines[] = ' ' . $table . ' deleted';
        }

        return $lines;
    }

    /**
     * @param array{adds:int, modifications:int, deletes:int} $stat
     */
    private function visualizeChanges(array $stat, int $maxMods): string
    {
        if ($maxMods <= 0) {
            return '';
        }

        return str_repeat('+', $this->visualLength($stat['adds'], $maxMods))
            . str_repeat('*', $this->visualLength($stat['modifications'], $maxMods))
            . str_repeat('-', $this->visualLength($stat['deletes'], $maxMods));
    }

    private function visualLength(int $count, int $maxMods): int
    {
        if ($count <= 0) {
            return 0;
        }

        return min($count, (int) (30 * ($count / $maxMods)));
    }

    /**
     * @param array<string, list<array<string, mixed>>> $statsByCommit
     * @return array<string, list<array{table:non-empty-string, operation:string, adds:int, modifications:int, deletes:int}>>
     */
    private function normalizeStatsByCommit(array $statsByCommit): array
    {
        $normalized = [];
        foreach ($statsByCommit as $commitHash => $stats) {
            if (!is_string($commitHash) || $commitHash === '') {
                throw new \InvalidArgumentException('Dolt log diffStatsByCommit keys must be commit hashes.');
            }
            if (!is_array($stats)) {
                throw new \InvalidArgumentException("Dolt log diff stats for {$commitHash} must be a list.");
            }

            $normalized[$commitHash] = [];
            foreach ($stats as $stat) {
                if (!is_array($stat)) {
                    throw new \InvalidArgumentException("Dolt log diff stat for {$commitHash} must be an array.");
                }
                $normalized[$commitHash][] = $this->normalizeStat($stat, $commitHash);
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $stat
     * @return array{table:non-empty-string, operation:string, adds:int, modifications:int, deletes:int}
     */
    private function normalizeStat(array $stat, string $commitHash): array
    {
        $table = $this->requiredString(
            $stat['table'] ?? $stat['table_name'] ?? $stat['name'] ?? null,
            "Dolt log diff stat {$commitHash} table",
        );
        $operation = $this->normalizeOperation($stat['operation'] ?? $stat['diff_type'] ?? null, $commitHash, $table);

        return [
            'table' => $table,
            'operation' => $operation,
            'adds' => $this->nonNegativeInt($stat['adds'] ?? $stat['rows_added'] ?? $stat['added'] ?? 0, "{$commitHash} {$table} adds"),
            'modifications' => $this->nonNegativeInt($stat['modifications'] ?? $stat['rows_modified'] ?? $stat['modified'] ?? 0, "{$commitHash} {$table} modifications"),
            'deletes' => $this->nonNegativeInt($stat['deletes'] ?? $stat['rows_deleted'] ?? $stat['deleted'] ?? 0, "{$commitHash} {$table} deletes"),
        ];
    }

    private function normalizeOperation(mixed $operation, string $commitHash, string $table): string
    {
        if (!is_string($operation) || $operation === '') {
            throw new \InvalidArgumentException("Dolt log diff stat {$commitHash} {$table} operation must be a non-empty string.");
        }

        return match (strtolower($operation)) {
            'added', 'add', 'tableadded', 'table_added' => self::OPERATION_ADDED,
            'deleted', 'removed', 'dropped', 'drop', 'tabledeleted', 'tableremoved', 'table_deleted', 'table_removed' => self::OPERATION_DELETED,
            'modified', 'changed', 'tablemodified', 'table_modified' => self::OPERATION_MODIFIED,
            default => throw new \InvalidArgumentException("Unsupported Dolt log diff stat operation for {$commitHash} {$table}: {$operation}"),
        };
    }

    /**
     * @return list<non-empty-string>
     */
    private function parentHashes(mixed $parents): array
    {
        if ($parents === null || $parents === '') {
            return [];
        }
        if (!is_string($parents)) {
            throw new \InvalidArgumentException('Dolt log parents must be a string or null.');
        }

        $hashes = [];
        foreach (explode(',', $parents) as $parent) {
            $parent = trim($parent);
            if ($parent === '') {
                continue;
            }
            $hashes[] = $parent;
        }

        return $hashes;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function boolOption(array $options, string $name): bool
    {
        $value = $options[$name] ?? false;
        if (!is_bool($value)) {
            throw new \InvalidArgumentException("Dolt log {$name} option must be a boolean.");
        }

        return $value;
    }

    private function requiredString(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("{$label} must be a non-empty string.");
        }

        return $value;
    }

    private function nullableString(mixed $value, string $label): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException("{$label} must be a string or null.");
        }

        return $value;
    }

    private function stringValue(mixed $value, string $label): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("{$label} must be a string.");
        }

        return $value;
    }

    private function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("Dolt log diff stat {$label} must be a non-negative integer.");
        }

        return $value;
    }
}
