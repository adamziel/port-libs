<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class ProcedureHistoryTable
{
    public const EMPTY_COMMIT = 'EMPTY';
    public const WORKING_COMMIT = 'WORKING';

    public const HISTORY_COLUMNS = [
        'name',
        'create_stmt',
        'created_at',
        'modified_at',
        'sql_mode',
        'commit_hash',
        'committer',
        'commit_date',
    ];

    public const DIFF_COLUMNS = [
        'to_name',
        'to_create_stmt',
        'to_created_at',
        'to_modified_at',
        'to_sql_mode',
        'to_commit',
        'to_commit_date',
        'from_name',
        'from_create_stmt',
        'from_created_at',
        'from_modified_at',
        'from_sql_mode',
        'from_commit',
        'from_commit_date',
        'diff_type',
    ];

    private const BASE_COLUMNS = ['name', 'create_stmt', 'created_at', 'modified_at', 'sql_mode'];

    /**
     * Project the row shape returned by upstream `dolt_history_dolt_procedures`.
     *
     * @param list<array{commit_hash:string, committer:string, commit_date:string|null, procedures:list<array{name:string, create_stmt:string, created_at:mixed, modified_at:mixed, sql_mode?:string|null}>}> $commits
     * @return list<array{name:string, create_stmt:string, created_at:mixed, modified_at:mixed, sql_mode:string|null, commit_hash:string, committer:string, commit_date:string|null}>
     */
    public function historyRows(array $commits): array
    {
        $rows = [];
        foreach ($this->normalizeCommits($commits) as $commit) {
            foreach ($commit['procedures'] as $procedureRow) {
                $rows[] = [
                    'name' => $procedureRow['name'],
                    'create_stmt' => $procedureRow['create_stmt'],
                    'created_at' => $procedureRow['created_at'],
                    'modified_at' => $procedureRow['modified_at'],
                    'sql_mode' => $procedureRow['sql_mode'],
                    'commit_hash' => $commit['commit_hash'],
                    'committer' => $commit['committer'],
                    'commit_date' => $commit['commit_date'],
                ];
            }
        }

        return $rows;
    }

    /**
     * Project the row shape returned by upstream `dolt_diff_dolt_procedures`.
     *
     * Commits are supplied oldest-to-newest. Each commit is compared to its
     * immediate parent, the first commit is compared to Dolt's `EMPTY` ref, and
     * optional working procedures are compared to the last commit with
     * `to_commit` set to `WORKING`.
     *
     * @param list<array{commit_hash:string, committer:string, commit_date:string|null, procedures:list<array{name:string, create_stmt:string, created_at:mixed, modified_at:mixed, sql_mode?:string|null}>}> $commits
     * @param list<array{name:string, create_stmt:string, created_at:mixed, modified_at:mixed, sql_mode?:string|null}>|null $workingProcedures
     * @return list<array{to_name:string|null, to_create_stmt:string|null, to_created_at:mixed, to_modified_at:mixed, to_sql_mode:string|null, to_commit:string, to_commit_date:string|null, from_name:string|null, from_create_stmt:string|null, from_created_at:mixed, from_modified_at:mixed, from_sql_mode:string|null, from_commit:string, from_commit_date:string|null, diff_type:string}>
     */
    public function diffRows(array $commits, ?array $workingProcedures = null): array
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
                    $commit['procedures'],
                    $fromCommit,
                    $fromDate,
                    $commit['commit_hash'],
                    $commit['commit_date']
                )
            );

            $fromRows = $commit['procedures'];
            $fromCommit = $commit['commit_hash'];
            $fromDate = $commit['commit_date'];
        }

        if ($workingProcedures !== null) {
            array_push(
                $rows,
                ...$this->diffPair(
                    $fromRows,
                    $this->normalizeProcedureRows($workingProcedures, 'working procedures'),
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
     * @param list<array{commit_hash:string, committer:string, commit_date:string|null, procedures:list<array{name:string, create_stmt:string, created_at:mixed, modified_at:mixed, sql_mode?:string|null}>}> $commits
     * @return list<array{commit_hash:non-empty-string, committer:non-empty-string, commit_date:string|null, procedures:array<string, array{name:non-empty-string, create_stmt:non-empty-string, created_at:mixed, modified_at:mixed, sql_mode:string|null}>}>
     */
    private function normalizeCommits(array $commits): array
    {
        $normalized = [];
        foreach ($commits as $i => $commit) {
            $hash = $commit['commit_hash'] ?? null;
            if (!is_string($hash) || $hash === '') {
                throw new \InvalidArgumentException("Dolt procedure history commit {$i} requires a non-empty commit_hash.");
            }
            $committer = $commit['committer'] ?? null;
            if (!is_string($committer) || $committer === '') {
                throw new \InvalidArgumentException("Dolt procedure history commit {$hash} requires a non-empty committer.");
            }
            $commitDate = $commit['commit_date'] ?? null;
            if ($commitDate !== null && (!is_string($commitDate) || $commitDate === '')) {
                throw new \InvalidArgumentException("Dolt procedure history commit {$hash} commit_date must be a non-empty string or null.");
            }
            $procedures = $commit['procedures'] ?? null;
            if (!is_array($procedures)) {
                throw new \InvalidArgumentException("Dolt procedure history commit {$hash} requires a procedures array.");
            }

            $normalized[] = [
                'commit_hash' => $hash,
                'committer' => $committer,
                'commit_date' => $commitDate,
                'procedures' => $this->normalizeProcedureRows($procedures, "commit {$hash} procedures"),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{name:string, create_stmt:string, created_at:mixed, modified_at:mixed, sql_mode?:string|null}> $rows
     * @return array<string, array{name:non-empty-string, create_stmt:non-empty-string, created_at:mixed, modified_at:mixed, sql_mode:string|null}>
     */
    private function normalizeProcedureRows(array $rows, string $label): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $name = $row['name'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException("Dolt {$label} rows require a non-empty name.");
            }
            $createStmt = $row['create_stmt'] ?? null;
            if (!is_string($createStmt) || $createStmt === '') {
                throw new \InvalidArgumentException("Dolt procedure row {$name} requires a non-empty create_stmt.");
            }
            $createdAt = $this->normalizeTimestamp($row['created_at'] ?? null, "created_at", $name);
            $modifiedAt = $this->normalizeTimestamp($row['modified_at'] ?? null, "modified_at", $name);
            $sqlMode = $row['sql_mode'] ?? null;
            if ($sqlMode !== null && !is_string($sqlMode)) {
                throw new \InvalidArgumentException("Dolt procedure row {$name} sql_mode must be a string or null.");
            }

            $procedureRow = [
                'name' => $name,
                'create_stmt' => $createStmt,
                'created_at' => $createdAt,
                'modified_at' => $modifiedAt,
                'sql_mode' => $sqlMode,
            ];
            $key = $this->procedureKey($procedureRow);
            if (isset($normalized[$key])) {
                throw new \InvalidArgumentException("Duplicate Dolt procedure row key in {$label}: {$name}");
            }
            $normalized[$key] = $procedureRow;
        }

        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<string, array{name:non-empty-string, create_stmt:non-empty-string, created_at:mixed, modified_at:mixed, sql_mode:string|null}> $fromRows
     * @param array<string, array{name:non-empty-string, create_stmt:non-empty-string, created_at:mixed, modified_at:mixed, sql_mode:string|null}> $toRows
     * @return list<array{to_name:string|null, to_create_stmt:string|null, to_created_at:mixed, to_modified_at:mixed, to_sql_mode:string|null, to_commit:string, to_commit_date:string|null, from_name:string|null, from_create_stmt:string|null, from_created_at:mixed, from_modified_at:mixed, from_sql_mode:string|null, from_commit:string, from_commit_date:string|null, diff_type:string}>
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
     * @param array{name:non-empty-string, create_stmt:non-empty-string, created_at:mixed, modified_at:mixed, sql_mode:string|null}|null $toRow
     * @param array{name:non-empty-string, create_stmt:non-empty-string, created_at:mixed, modified_at:mixed, sql_mode:string|null}|null $fromRow
     * @return array{to_name:string|null, to_create_stmt:string|null, to_created_at:mixed, to_modified_at:mixed, to_sql_mode:string|null, to_commit:string, to_commit_date:string|null, from_name:string|null, from_create_stmt:string|null, from_created_at:mixed, from_modified_at:mixed, from_sql_mode:string|null, from_commit:string, from_commit_date:string|null, diff_type:string}
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
     * @param array{name:non-empty-string, create_stmt:non-empty-string, created_at:mixed, modified_at:mixed, sql_mode:string|null} $row
     */
    private function procedureKey(array $row): string
    {
        return strtolower($row['name']);
    }

    /**
     * @param array{name:non-empty-string, create_stmt:non-empty-string, created_at:mixed, modified_at:mixed, sql_mode:string|null} $left
     * @param array{name:non-empty-string, create_stmt:non-empty-string, created_at:mixed, modified_at:mixed, sql_mode:string|null} $right
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

    private function normalizeTimestamp(mixed $value, string $field, string $name): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ((is_string($value) && $value !== '') || is_int($value) || is_float($value)) {
            return $value;
        }

        throw new \InvalidArgumentException("Dolt procedure row {$name} {$field} must be a non-empty string, number, or DateTimeInterface.");
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
