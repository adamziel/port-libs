<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class SchemaHistoryTable
{
    public const EMPTY_COMMIT = 'EMPTY';
    public const WORKING_COMMIT = 'WORKING';

    public const HISTORY_COLUMNS = [
        'type',
        'name',
        'fragment',
        'extra',
        'sql_mode',
        'commit_hash',
        'committer',
        'commit_date',
    ];

    public const DIFF_COLUMNS = [
        'to_type',
        'to_name',
        'to_fragment',
        'to_extra',
        'to_sql_mode',
        'to_commit',
        'to_commit_date',
        'from_type',
        'from_name',
        'from_fragment',
        'from_extra',
        'from_sql_mode',
        'from_commit',
        'from_commit_date',
        'diff_type',
    ];

    private const BASE_COLUMNS = ['type', 'name', 'fragment', 'extra', 'sql_mode'];

    /**
     * Project the row shape returned by upstream `dolt_history_dolt_schemas`.
     *
     * @param list<array{commit_hash:string, committer:string, commit_date:string|null, schemas:list<array{type:string, name:string, fragment?:string|null, extra?:mixed, sql_mode?:string|null}>}> $commits
     * @return list<array{type:string, name:string, fragment:string|null, extra:mixed, sql_mode:string|null, commit_hash:string, committer:string, commit_date:string|null}>
     */
    public function historyRows(array $commits): array
    {
        $rows = [];
        foreach ($this->normalizeCommits($commits) as $commit) {
            foreach ($commit['schemas'] as $schemaRow) {
                $rows[] = [
                    'type' => $schemaRow['type'],
                    'name' => $schemaRow['name'],
                    'fragment' => $schemaRow['fragment'],
                    'extra' => $schemaRow['extra'],
                    'sql_mode' => $schemaRow['sql_mode'],
                    'commit_hash' => $commit['commit_hash'],
                    'committer' => $commit['committer'],
                    'commit_date' => $commit['commit_date'],
                ];
            }
        }

        return $rows;
    }

    /**
     * Project the row shape returned by upstream `dolt_diff_dolt_schemas`.
     *
     * Commits are supplied oldest-to-newest. Each commit is compared to its
     * immediate parent, the first commit is compared to Dolt's `EMPTY` ref, and
     * optional working schemas are compared to the last commit with `to_commit`
     * set to `WORKING`.
     *
     * @param list<array{commit_hash:string, committer:string, commit_date:string|null, schemas:list<array{type:string, name:string, fragment?:string|null, extra?:mixed, sql_mode?:string|null}>}> $commits
     * @param list<array{type:string, name:string, fragment?:string|null, extra?:mixed, sql_mode?:string|null}>|null $workingSchemas
     * @return list<array{to_type:string|null, to_name:string|null, to_fragment:string|null, to_extra:mixed, to_sql_mode:string|null, to_commit:string, to_commit_date:string|null, from_type:string|null, from_name:string|null, from_fragment:string|null, from_extra:mixed, from_sql_mode:string|null, from_commit:string, from_commit_date:string|null, diff_type:string}>
     */
    public function diffRows(array $commits, ?array $workingSchemas = null): array
    {
        $commits = $this->normalizeCommits($commits);
        $rows = [];
        $fromRows = [];
        $fromCommit = self::EMPTY_COMMIT;
        $fromDate = null;

        foreach ($commits as $commit) {
            array_push(
                $rows,
                ...$this->diffPair(
                    $fromRows,
                    $commit['schemas'],
                    $fromCommit,
                    $fromDate,
                    $commit['commit_hash'],
                    $commit['commit_date']
                )
            );

            $fromRows = $commit['schemas'];
            $fromCommit = $commit['commit_hash'];
            $fromDate = $commit['commit_date'];
        }

        if ($workingSchemas !== null) {
            array_push(
                $rows,
                ...$this->diffPair(
                    $fromRows,
                    $this->normalizeSchemaRows($workingSchemas, 'working schemas'),
                    $fromCommit,
                    $fromDate,
                    self::WORKING_COMMIT,
                    null
                )
            );
        }

        return $rows;
    }

    /**
     * @param list<array{commit_hash:string, committer:string, commit_date:string|null, schemas:list<array{type:string, name:string, fragment?:string|null, extra?:mixed, sql_mode?:string|null}>}> $commits
     * @return list<array{commit_hash:non-empty-string, committer:non-empty-string, commit_date:string|null, schemas:array<string, array{type:non-empty-string, name:non-empty-string, fragment:string|null, extra:mixed, sql_mode:string|null}>}>
     */
    private function normalizeCommits(array $commits): array
    {
        $normalized = [];
        foreach ($commits as $i => $commit) {
            $hash = $commit['commit_hash'] ?? null;
            if (!is_string($hash) || $hash === '') {
                throw new \InvalidArgumentException("Dolt schema history commit {$i} requires a non-empty commit_hash.");
            }
            $committer = $commit['committer'] ?? null;
            if (!is_string($committer) || $committer === '') {
                throw new \InvalidArgumentException("Dolt schema history commit {$hash} requires a non-empty committer.");
            }
            $commitDate = $commit['commit_date'] ?? null;
            if ($commitDate !== null && (!is_string($commitDate) || $commitDate === '')) {
                throw new \InvalidArgumentException("Dolt schema history commit {$hash} commit_date must be a non-empty string or null.");
            }
            $schemas = $commit['schemas'] ?? null;
            if (!is_array($schemas)) {
                throw new \InvalidArgumentException("Dolt schema history commit {$hash} requires a schemas array.");
            }

            $normalized[] = [
                'commit_hash' => $hash,
                'committer' => $committer,
                'commit_date' => $commitDate,
                'schemas' => $this->normalizeSchemaRows($schemas, "commit {$hash} schemas"),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{type:string, name:string, fragment?:string|null, extra?:mixed, sql_mode?:string|null}> $rows
     * @return array<string, array{type:non-empty-string, name:non-empty-string, fragment:string|null, extra:mixed, sql_mode:string|null}>
     */
    private function normalizeSchemaRows(array $rows, string $label): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $type = $row['type'] ?? null;
            $name = $row['name'] ?? null;
            if (!is_string($type) || $type === '') {
                throw new \InvalidArgumentException("Dolt {$label} rows require a non-empty type.");
            }
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException("Dolt {$label} rows require a non-empty name.");
            }

            $fragment = $row['fragment'] ?? null;
            if ($fragment !== null && !is_string($fragment)) {
                throw new \InvalidArgumentException("Dolt schema row {$type}:{$name} fragment must be a string or null.");
            }
            $sqlMode = $row['sql_mode'] ?? null;
            if ($sqlMode !== null && !is_string($sqlMode)) {
                throw new \InvalidArgumentException("Dolt schema row {$type}:{$name} sql_mode must be a string or null.");
            }

            $schemaRow = [
                'type' => $type,
                'name' => $name,
                'fragment' => $fragment,
                'extra' => $row['extra'] ?? null,
                'sql_mode' => $sqlMode,
            ];
            $key = $this->schemaKey($schemaRow);
            if (isset($normalized[$key])) {
                throw new \InvalidArgumentException("Duplicate Dolt schema row key in {$label}: {$type}:{$name}");
            }
            $normalized[$key] = $schemaRow;
        }

        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<string, array{type:non-empty-string, name:non-empty-string, fragment:string|null, extra:mixed, sql_mode:string|null}> $fromRows
     * @param array<string, array{type:non-empty-string, name:non-empty-string, fragment:string|null, extra:mixed, sql_mode:string|null}> $toRows
     * @return list<array{to_type:string|null, to_name:string|null, to_fragment:string|null, to_extra:mixed, to_sql_mode:string|null, to_commit:string, to_commit_date:string|null, from_type:string|null, from_name:string|null, from_fragment:string|null, from_extra:mixed, from_sql_mode:string|null, from_commit:string, from_commit_date:string|null, diff_type:string}>
     */
    private function diffPair(
        array $fromRows,
        array $toRows,
        string $fromCommit,
        ?string $fromDate,
        string $toCommit,
        ?string $toDate,
    ): array {
        $rows = [];
        foreach ($toRows as $key => $toRow) {
            if (isset($fromRows[$key])) {
                if (!$this->rowsEqual($fromRows[$key], $toRow)) {
                    $rows[] = $this->diffRow($toRow, $fromRows[$key], $toCommit, $toDate, $fromCommit, $fromDate, TableDiff::DIFF_MODIFIED);
                }
                continue;
            }

            $rows[] = $this->diffRow($toRow, null, $toCommit, $toDate, $fromCommit, $fromDate, TableDiff::DIFF_ADDED);
        }

        foreach ($fromRows as $key => $fromRow) {
            if (!isset($toRows[$key])) {
                $rows[] = $this->diffRow(null, $fromRow, $toCommit, $toDate, $fromCommit, $fromDate, TableDiff::DIFF_REMOVED);
            }
        }

        return $rows;
    }

    /**
     * @param array{type:non-empty-string, name:non-empty-string, fragment:string|null, extra:mixed, sql_mode:string|null}|null $toRow
     * @param array{type:non-empty-string, name:non-empty-string, fragment:string|null, extra:mixed, sql_mode:string|null}|null $fromRow
     * @return array{to_type:string|null, to_name:string|null, to_fragment:string|null, to_extra:mixed, to_sql_mode:string|null, to_commit:string, to_commit_date:string|null, from_type:string|null, from_name:string|null, from_fragment:string|null, from_extra:mixed, from_sql_mode:string|null, from_commit:string, from_commit_date:string|null, diff_type:string}
     */
    private function diffRow(
        ?array $toRow,
        ?array $fromRow,
        string $toCommit,
        ?string $toDate,
        string $fromCommit,
        ?string $fromDate,
        string $diffType,
    ): array {
        $row = [];
        foreach (self::BASE_COLUMNS as $column) {
            $row['to_' . $column] = $toRow[$column] ?? null;
        }
        $row['to_commit'] = $toCommit;
        $row['to_commit_date'] = $toDate;
        foreach (self::BASE_COLUMNS as $column) {
            $row['from_' . $column] = $fromRow[$column] ?? null;
        }
        $row['from_commit'] = $fromCommit;
        $row['from_commit_date'] = $fromDate;
        $row['diff_type'] = $diffType;

        return $row;
    }

    /**
     * @param array{type:non-empty-string, name:non-empty-string, fragment:string|null, extra:mixed, sql_mode:string|null} $row
     */
    private function schemaKey(array $row): string
    {
        return strtolower($row['type']) . ':' . strtolower($row['name']);
    }

    /**
     * @param array{type:non-empty-string, name:non-empty-string, fragment:string|null, extra:mixed, sql_mode:string|null} $left
     * @param array{type:non-empty-string, name:non-empty-string, fragment:string|null, extra:mixed, sql_mode:string|null} $right
     */
    private function rowsEqual(array $left, array $right): bool
    {
        foreach (self::BASE_COLUMNS as $column) {
            $leftValue = $left[$column] ?? null;
            $rightValue = $right[$column] ?? null;
            if ($leftValue === null && $rightValue === null) {
                continue;
            }
            if ($leftValue === null || $rightValue === null) {
                return false;
            }
            if ($this->renderValue($leftValue) !== $this->renderValue($rightValue)) {
                return false;
            }
        }

        return true;
    }

    private function renderValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($this->sortArrayValue($value), JSON_THROW_ON_ERROR);
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private function sortArrayValue(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortArrayValue($item);
            }
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
