<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class StatusTable
{
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_CONSTRAINT_VIOLATION = 'constraint violation';
    public const STATUS_DELETED = 'deleted';
    public const STATUS_MERGED = 'merged';
    public const STATUS_MODIFIED = 'modified';
    public const STATUS_NEW_TABLE = 'new table';
    public const STATUS_RENAMED = 'renamed';
    public const STATUS_SCHEMA_CONFLICT = 'schema conflict';

    private TableDeltaMatcher $matcher;

    public function __construct(?TableDeltaMatcher $matcher = null)
    {
        $this->matcher = $matcher ?? new TableDeltaMatcher();
    }

    /**
     * Project the row shape returned by upstream `dolt_status`.
     *
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $headTables
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $stagedTables
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $workingTables
     * @param list<string> $dataConflictTables
     * @param list<string> $schemaConflictTables
     * @param list<string> $constraintViolationTables
     * @param list<string> $mergedTables
     * @param list<array{pattern:string, ignore:bool}> $ignorePatterns
     * @return list<array{table_name:string, staged:int, status:string}>
     */
    public function rows(
        array $headTables,
        array $stagedTables,
        array $workingTables,
        array $dataConflictTables = [],
        array $schemaConflictTables = [],
        array $constraintViolationTables = [],
        array $mergedTables = [],
        array $ignorePatterns = [],
    ): array {
        $rows = [];
        foreach ($this->rowsWithIgnored(
            $headTables,
            $stagedTables,
            $workingTables,
            $dataConflictTables,
            $schemaConflictTables,
            $constraintViolationTables,
            $mergedTables,
            $ignorePatterns,
            false
        ) as $row) {
            if (!$row['ignored']) {
                unset($row['ignored']);
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Project the row shape returned by upstream `dolt_status_ignored`.
     *
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $headTables
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $stagedTables
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $workingTables
     * @param list<string> $dataConflictTables
     * @param list<string> $schemaConflictTables
     * @param list<string> $constraintViolationTables
     * @param list<string> $mergedTables
     * @param list<array{pattern:string, ignore:bool}> $ignorePatterns
     * @return list<array{table_name:string, staged:int, status:string, ignored:bool}>
     */
    public function rowsWithIgnored(
        array $headTables,
        array $stagedTables,
        array $workingTables,
        array $dataConflictTables = [],
        array $schemaConflictTables = [],
        array $constraintViolationTables = [],
        array $mergedTables = [],
        array $ignorePatterns = [],
        bool $conflictingIgnorePatternsAreVisible = true,
    ): array {
        $constraintViolations = $this->normalizeTableNames($constraintViolationTables, 'constraint violation');
        $rows = [];

        foreach ($constraintViolations as $tableName) {
            $rows[] = $this->row($tableName, false, self::STATUS_CONSTRAINT_VIOLATION);
        }
        foreach ($this->normalizeTableNames($schemaConflictTables, 'schema conflict') as $tableName) {
            $rows[] = $this->row($tableName, false, self::STATUS_SCHEMA_CONFLICT);
        }
        foreach ($this->normalizeTableNames($mergedTables, 'merged') as $tableName) {
            $rows[] = $this->row($tableName, true, self::STATUS_MERGED);
        }
        foreach ($this->normalizeTableNames($dataConflictTables, 'data conflict') as $tableName) {
            $rows[] = $this->row($tableName, false, self::STATUS_CONFLICT);
        }

        $skipChanged = array_fill_keys($constraintViolations, true);
        $stagedSummaries = $this->matcher->summaries($headTables, $stagedTables);
        $unstagedSummaries = $this->matcher->summaries($stagedTables, $workingTables);
        $unstagedNewTables = [];

        foreach ($stagedSummaries as $summary) {
            if ($this->shouldSkipChangedSummary($summary, $skipChanged)) {
                continue;
            }
            $rows[] = $this->rowFromSummary($summary, true);
        }
        foreach ($unstagedSummaries as $summary) {
            if ($this->shouldSkipChangedSummary($summary, $skipChanged)) {
                continue;
            }
            $row = $this->rowFromSummary($summary, false);
            if ($row['status'] === self::STATUS_NEW_TABLE) {
                $unstagedNewTables[$row['table_name']] = true;
            }
            $rows[] = $row;
        }

        $ignoredRows = [];
        foreach ($rows as $row) {
            $ignored = false;
            if (
                $row['staged'] === 0
                && $row['status'] === self::STATUS_NEW_TABLE
                && isset($unstagedNewTables[$row['table_name']])
            ) {
                $result = $this->matcher->ignoreResultForTable(
                    $row['table_name'],
                    $ignorePatterns,
                    $conflictingIgnorePatternsAreVisible
                );
                $ignored = $result === 'ignore';
            }

            $ignoredRows[] = [
                'table_name' => $row['table_name'],
                'staged' => $row['staged'],
                'status' => $row['status'],
                'ignored' => $ignored,
            ];
        }

        return $ignoredRows;
    }

    /**
     * @return array{table_name:string, staged:int, status:string}
     */
    private function row(string $tableName, bool $staged, string $status): array
    {
        if ($tableName === '') {
            throw new \InvalidArgumentException('Dolt status table names must be non-empty strings.');
        }

        return [
            'table_name' => $tableName,
            'staged' => $staged ? 1 : 0,
            'status' => $status,
        ];
    }

    /**
     * @param array{table_name:string, from_table_name:string|null, to_table_name:string|null, diff_type:string, data_change:bool, schema_change:bool, primary_key_set_changed:bool} $summary
     * @return array{table_name:string, staged:int, status:string}
     */
    private function rowFromSummary(array $summary, bool $staged): array
    {
        if ($summary['diff_type'] === TableDeltaMatcher::DIFF_ADDED) {
            return $this->row($summary['to_table_name'] ?? $summary['table_name'], $staged, self::STATUS_NEW_TABLE);
        }
        if ($summary['diff_type'] === TableDeltaMatcher::DIFF_DROPPED) {
            return $this->row($summary['from_table_name'] ?? $summary['table_name'], $staged, self::STATUS_DELETED);
        }
        if ($summary['diff_type'] === TableDeltaMatcher::DIFF_RENAMED) {
            return $this->row(
                ($summary['from_table_name'] ?? '') . ' -> ' . ($summary['to_table_name'] ?? ''),
                $staged,
                self::STATUS_RENAMED
            );
        }

        return $this->row($summary['table_name'], $staged, self::STATUS_MODIFIED);
    }

    /**
     * @param array{table_name:string, from_table_name:string|null, to_table_name:string|null, diff_type:string, data_change:bool, schema_change:bool, primary_key_set_changed:bool} $summary
     * @param array<string, true> $skipTables
     */
    private function shouldSkipChangedSummary(array $summary, array $skipTables): bool
    {
        foreach ([$summary['from_table_name'], $summary['to_table_name'], $summary['table_name']] as $name) {
            if (is_string($name) && isset($skipTables[$name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $tableNames
     * @return list<non-empty-string>
     */
    private function normalizeTableNames(array $tableNames, string $label): array
    {
        $normalized = [];
        $seen = [];
        foreach ($tableNames as $tableName) {
            if (!is_string($tableName) || $tableName === '') {
                throw new \InvalidArgumentException("Dolt {$label} table names must be non-empty strings.");
            }
            if (isset($seen[$tableName])) {
                continue;
            }
            $seen[$tableName] = true;
            $normalized[] = $tableName;
        }

        return $normalized;
    }
}
