<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class CommitDiffTable
{
    public const ERR_EXACTLY_ONE_TO_COMMIT = "dolt_commit_diff_* tables must be filtered to a single 'to_commit'";
    public const ERR_EXACTLY_ONE_FROM_COMMIT = "dolt_commit_diff_* tables must be filtered to a single 'from_commit'";
    public const ERR_INVALID_ARGS = "commit_diff_<table> requires one 'to_commit' and one 'from_commit'";

    private TableDiff $differ;

    public function __construct(?TableDiff $differ = null)
    {
        $this->differ = $differ ?? new TableDiff();
    }

    /**
     * Project the row shape returned by upstream `DOLT_COMMIT_DIFF_<table>`.
     *
     * The upstream table requires point filters on both `to_commit` and
     * `from_commit`; additional `to_*` / `from_*` key filters are then applied
     * to the projected diff rows.
     *
     * @param list<array{commit_hash:string, commit_date?:string|null, rows:list<array<string, scalar|null>>}> $snapshots
     * @param non-empty-string|list<non-empty-string> $primaryKey
     * @param array{to_commit?:string|list<string>, from_commit?:string|list<string>} $filters
     * @param list<non-empty-string>|null $columns
     * @return list<array<string, scalar|null>>
     */
    public function rows(
        array $snapshots,
        string|array $primaryKey,
        array $filters,
        ?array $columns = null,
        ?string $where = null,
        ?int $limit = null,
    ): array {
        $toCommit = $this->requiredCommitFilter($filters, 'to_commit', self::ERR_EXACTLY_ONE_TO_COMMIT);
        $fromCommit = $this->requiredCommitFilter($filters, 'from_commit', self::ERR_EXACTLY_ONE_FROM_COMMIT);

        return $this->rowsBetween($snapshots, $primaryKey, $fromCommit, $toCommit, $columns, $where, $limit);
    }

    /**
     * @param list<array{commit_hash:string, commit_date?:string|null, rows:list<array<string, scalar|null>>}> $snapshots
     * @param non-empty-string|list<non-empty-string> $primaryKey
     * @param list<non-empty-string>|null $columns
     * @return list<array<string, scalar|null>>
     */
    public function rowsBetween(
        array $snapshots,
        string|array $primaryKey,
        string $fromCommit,
        string $toCommit,
        ?array $columns = null,
        ?string $where = null,
        ?int $limit = null,
    ): array {
        if ($fromCommit === '' || $toCommit === '') {
            throw new \InvalidArgumentException(self::ERR_INVALID_ARGS);
        }

        $indexed = $this->indexSnapshots($snapshots);
        if (!isset($indexed[$fromCommit])) {
            throw new \RuntimeException("Dolt commit snapshot not found: {$fromCommit}");
        }
        if (!isset($indexed[$toCommit])) {
            throw new \RuntimeException("Dolt commit snapshot not found: {$toCommit}");
        }

        $rows = $this->differ->diffTableRows(
            $indexed[$fromCommit]['rows'],
            $indexed[$toCommit]['rows'],
            $primaryKey,
            $columns,
            $fromCommit,
            $indexed[$fromCommit]['commit_date'],
            $toCommit,
            $indexed[$toCommit]['commit_date'],
        );

        return $this->differ->filterDiffTableRows($rows, $where, $limit);
    }

    /**
     * @param array{to_commit?:string|list<string>, from_commit?:string|list<string>} $filters
     */
    private function requiredCommitFilter(array $filters, string $field, string $error): string
    {
        if (!array_key_exists($field, $filters)) {
            throw new \InvalidArgumentException($error);
        }

        $value = $filters[$field];
        if (is_array($value)) {
            if (count($value) !== 1) {
                throw new \InvalidArgumentException($error);
            }
            $value = array_values($value)[0];
        }

        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException(self::ERR_INVALID_ARGS);
        }

        return $value;
    }

    /**
     * @param list<array{commit_hash:string, commit_date?:string|null, rows:list<array<string, scalar|null>>}> $snapshots
     * @return array<string, array{commit_date:string|null, rows:list<array<string, scalar|null>>}>
     */
    private function indexSnapshots(array $snapshots): array
    {
        $indexed = [];
        foreach ($snapshots as $i => $snapshot) {
            $commitHash = $snapshot['commit_hash'] ?? null;
            if (!is_string($commitHash) || $commitHash === '') {
                throw new \InvalidArgumentException("Dolt commit diff snapshot {$i} requires a non-empty commit_hash.");
            }
            if (isset($indexed[$commitHash])) {
                throw new \InvalidArgumentException("Duplicate Dolt commit diff snapshot: {$commitHash}");
            }

            $commitDate = $snapshot['commit_date'] ?? null;
            if ($commitDate !== null && (!is_string($commitDate) || $commitDate === '')) {
                throw new \InvalidArgumentException("Dolt commit diff snapshot {$commitHash} commit_date must be a non-empty string or null.");
            }

            $rows = $snapshot['rows'] ?? null;
            if (!is_array($rows)) {
                throw new \InvalidArgumentException("Dolt commit diff snapshot {$commitHash} requires rows.");
            }

            $indexed[$commitHash] = [
                'commit_date' => $commitDate,
                'rows' => $this->normalizeRows($rows, $commitHash),
            ];
        }

        return $indexed;
    }

    /**
     * @param array<mixed> $rows
     * @return list<array<string, scalar|null>>
     */
    private function normalizeRows(array $rows, string $commitHash): array
    {
        $normalized = [];
        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("Dolt commit diff snapshot {$commitHash} row {$i} must be an array.");
            }

            $normalizedRow = [];
            foreach ($row as $column => $value) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException("Dolt commit diff snapshot {$commitHash} row {$i} has an invalid column name.");
                }
                if ($value !== null && !is_scalar($value)) {
                    throw new \InvalidArgumentException("Dolt commit diff snapshot {$commitHash} row {$i} column {$column} must be scalar or null.");
                }
                $normalizedRow[$column] = $value;
            }

            $normalized[] = $normalizedRow;
        }

        return $normalized;
    }
}
