<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class TableDeltaMatcher
{
    public const DIFF_ADDED = 'added';
    public const DIFF_MODIFIED = 'modified';
    public const DIFF_RENAMED = 'renamed';
    public const DIFF_DROPPED = 'dropped';

    /**
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $fromTables
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $toTables
     * @return list<array{table_name:string, from_table_name:string|null, to_table_name:string|null, diff_type:string, data_change:bool, schema_change:bool, primary_key_set_changed:bool}>
     */
    public function summaries(array $fromTables, array $toTables): array
    {
        $from = $this->indexTables($fromTables, 'from');
        $to = $this->indexTables($toTables, 'to');
        $deltas = [];

        foreach (array_keys($to) as $name) {
            if (!isset($from[$name])) {
                continue;
            }
            $summary = $this->summarize($from[$name], $to[$name]);
            if (!$this->isUnmodified($summary)) {
                $deltas[] = $summary;
            }
            unset($from[$name], $to[$name]);
        }

        foreach (array_keys($from) as $fromName) {
            foreach (array_keys($to) as $toName) {
                if (!TableSchema::schemasOverlap($from[$fromName]['schema'], $to[$toName]['schema'])) {
                    continue;
                }
                $summary = $this->summarize($from[$fromName], $to[$toName]);
                if (!$this->isUnmodified($summary)) {
                    $deltas[] = $summary;
                }
                unset($from[$fromName], $to[$toName]);
                break;
            }
        }

        foreach ($from as $table) {
            $deltas[] = $this->summarize($table, null);
        }
        foreach ($to as $table) {
            $deltas[] = $this->summarize(null, $table);
        }

        usort($deltas, static function (array $a, array $b): int {
            return [$a['from_table_name'] ?? '', $a['to_table_name'] ?? ''] <=> [$b['from_table_name'] ?? '', $b['to_table_name'] ?? ''];
        });

        return $deltas;
    }

    /**
     * Project table deltas into the row shape returned by upstream
     * `dolt_diff_summary()`.
     *
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $fromTables
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $toTables
     * @param list<array{pattern:string, ignore:bool}> $ignorePatterns Apply only when the compared refs include WORKING or STAGED.
     * @return list<array{from_table_name:string, to_table_name:string, diff_type:string, data_change:bool, schema_change:bool}>
     */
    public function summaryRows(
        array $fromTables,
        array $toTables,
        ?string $tableName = null,
        bool $errorOnPrimaryKeyChange = false,
        array $ignorePatterns = [],
        array &$warnings = [],
        string $fromCommit = 'FROM',
        string $toCommit = 'TO',
    ): array
    {
        $ignorePatterns = $this->normalizeIgnorePatterns($ignorePatterns);
        $rows = [];
        foreach ($this->summaries($fromTables, $toTables) as $summary) {
            if ($tableName !== null && !$this->summaryMatchesTable($summary, $tableName)) {
                continue;
            }
            if ($ignorePatterns !== [] && $this->shouldIgnoreSummary($summary, $ignorePatterns)) {
                continue;
            }
            if ($summary['primary_key_set_changed']) {
                if ($errorOnPrimaryKeyChange) {
                    throw new \RuntimeException("failed to compute diff summary for table {$summary['table_name']}: primary key set changed");
                }
                $warnings[] = [
                    'code' => TableSchema::WARNING_UNKNOWN,
                    'message' => sprintf(TableSchema::PRIMARY_KEY_CHANGE_WARNING, $fromCommit, $toCommit),
                ];
                continue;
            }

            $rows[] = [
                'from_table_name' => $summary['from_table_name'] ?? '',
                'to_table_name' => $summary['to_table_name'] ?? '',
                'diff_type' => $summary['diff_type'],
                'data_change' => $summary['data_change'],
                'schema_change' => $summary['schema_change'],
            ];
        }

        return $rows;
    }

    public static function changeTypeToDiffType(string $changeType): string
    {
        return match ($changeType) {
            'added' => self::DIFF_ADDED,
            'removed' => self::DIFF_DROPPED,
            'modified_old', 'modified_new' => self::DIFF_MODIFIED,
            default => '',
        };
    }

    /**
     * @param list<array{pattern:string, ignore:bool}> $ignorePatterns
     */
    public function ignoreResultForTable(
        string $tableName,
        array $ignorePatterns,
        bool $conflictingPatternsAreVisible = false,
    ): string {
        $ignorePatterns = $this->normalizeIgnorePatterns($ignorePatterns);

        try {
            return $this->ignoreResult($tableName, $ignorePatterns);
        } catch (\RuntimeException $e) {
            if ($conflictingPatternsAreVisible && str_contains($e->getMessage(), 'matches conflicting patterns in dolt_ignore')) {
                return 'dont_ignore';
            }

            throw $e;
        }
    }

    /**
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $tables
     * @return array<string, array{name:non-empty-string, schema:TableSchema, rowHash:string|null, rowCount:int|null}>
     */
    private function indexTables(array $tables, string $side): array
    {
        $indexed = [];
        foreach ($tables as $table) {
            $name = $table['name'] ?? null;
            $schema = $table['schema'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException("{$side} table names must be non-empty strings.");
            }
            if (!$schema instanceof TableSchema) {
                throw new \InvalidArgumentException("{$side} table {$name} must include a TableSchema.");
            }
            if (isset($indexed[$name])) {
                throw new \InvalidArgumentException("Duplicate {$side} table name: {$name}");
            }
            $rowHash = $table['rowHash'] ?? null;
            if ($rowHash !== null && (!is_string($rowHash) || $rowHash === '')) {
                throw new \InvalidArgumentException("{$side} table {$name} rowHash must be a non-empty string or null.");
            }
            $rowCount = $table['rowCount'] ?? null;
            if ($rowCount !== null && (!is_int($rowCount) || $rowCount < 0)) {
                throw new \InvalidArgumentException("{$side} table {$name} rowCount must be a non-negative integer or null.");
            }

            $indexed[$name] = [
                'name' => $name,
                'schema' => $schema,
                'rowHash' => $rowHash,
                'rowCount' => $rowCount,
            ];
        }

        ksort($indexed, SORT_STRING);

        return $indexed;
    }

    /**
     * @param array{name:non-empty-string, schema:TableSchema, rowHash:string|null, rowCount:int|null}|null $from
     * @param array{name:non-empty-string, schema:TableSchema, rowHash:string|null, rowCount:int|null}|null $to
     * @return array{table_name:string, from_table_name:string|null, to_table_name:string|null, diff_type:string, data_change:bool, schema_change:bool, primary_key_set_changed:bool}
     */
    private function summarize(?array $from, ?array $to): array
    {
        $fromName = $from['name'] ?? null;
        $toName = $to['name'] ?? null;
        $isAdd = $from === null && $to !== null;
        $isDrop = $from !== null && $to === null;
        $isRename = !$isAdd && !$isDrop && $fromName !== $toName;
        $primaryKeySetChanged = !$isAdd
            && !$isDrop
            && !TableSchema::primaryKeySetsDiffable($from['schema'], $to['schema']);

        if ($isAdd) {
            $diffType = self::DIFF_ADDED;
            $schemaChange = true;
        } elseif ($isDrop) {
            $diffType = self::DIFF_DROPPED;
            $schemaChange = true;
        } elseif ($isRename) {
            $diffType = self::DIFF_RENAMED;
            $schemaChange = true;
        } else {
            $diffType = self::DIFF_MODIFIED;
            $schemaChange = $primaryKeySetChanged || $this->hasColumnDiff($from['schema'], $to['schema']);
        }

        return [
            'table_name' => $toName ?? $fromName ?? '',
            'from_table_name' => $fromName,
            'to_table_name' => $toName,
            'diff_type' => $diffType,
            'data_change' => $this->hasDataChange($from, $to),
            'schema_change' => $schemaChange,
            'primary_key_set_changed' => $primaryKeySetChanged,
        ];
    }

    private function hasColumnDiff(TableSchema $from, TableSchema $to): bool
    {
        foreach (TableSchema::diffColumns($from, $to) as $diff) {
            if ($diff['diff_type'] !== TableSchema::DIFF_NONE) {
                return true;
            }
        }

        return !$from->hasSameSchemaMetadata($to);
    }

    /**
     * @param array{table_name:string, from_table_name:string|null, to_table_name:string|null, diff_type:string, data_change:bool, schema_change:bool, primary_key_set_changed:bool} $summary
     */
    private function isUnmodified(array $summary): bool
    {
        return $summary['diff_type'] === self::DIFF_MODIFIED
            && !$summary['data_change']
            && !$summary['schema_change']
            && !$summary['primary_key_set_changed'];
    }

    /**
     * @param array{name:non-empty-string, schema:TableSchema, rowHash:string|null, rowCount:int|null}|null $from
     * @param array{name:non-empty-string, schema:TableSchema, rowHash:string|null, rowCount:int|null}|null $to
     */
    private function hasDataChange(?array $from, ?array $to): bool
    {
        if ($from === null) {
            return ($to['rowCount'] ?? 0) > 0;
        }
        if ($to === null) {
            return ($from['rowCount'] ?? 0) > 0;
        }
        if ($from['rowHash'] === null || $to['rowHash'] === null) {
            return false;
        }

        return $from['rowHash'] !== $to['rowHash'];
    }

    /**
     * @param array{table_name:string, from_table_name:string|null, to_table_name:string|null, diff_type:string, data_change:bool, schema_change:bool, primary_key_set_changed:bool} $summary
     */
    private function summaryMatchesTable(array $summary, string $tableName): bool
    {
        foreach ([$summary['from_table_name'], $summary['to_table_name'], $summary['table_name']] as $name) {
            if (is_string($name) && strcasecmp($name, $tableName) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{table_name:string, from_table_name:string|null, to_table_name:string|null, diff_type:string, data_change:bool, schema_change:bool, primary_key_set_changed:bool} $summary
     * @param list<array{pattern:non-empty-string, ignore:bool}> $ignorePatterns
     */
    private function shouldIgnoreSummary(array $summary, array $ignorePatterns): bool
    {
        if ($summary['diff_type'] === self::DIFF_ADDED && is_string($summary['to_table_name'])) {
            return $this->ignoreResult($summary['to_table_name'], $ignorePatterns) === 'ignore';
        }
        if ($summary['diff_type'] === self::DIFF_DROPPED && is_string($summary['from_table_name'])) {
            return $this->ignoreResult($summary['from_table_name'], $ignorePatterns) === 'ignore';
        }

        return false;
    }

    /**
     * @param list<array{pattern:string, ignore:bool}> $patterns
     * @return list<array{pattern:non-empty-string, ignore:bool}>
     */
    private function normalizeIgnorePatterns(array $patterns): array
    {
        $normalized = [];
        foreach ($patterns as $pattern) {
            $name = $pattern['pattern'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('Dolt ignore patterns must include a non-empty pattern string.');
            }
            $ignore = $pattern['ignore'] ?? null;
            if (!is_bool($ignore)) {
                throw new \InvalidArgumentException("Dolt ignore pattern {$name} must include a boolean ignore flag.");
            }

            $normalized[] = ['pattern' => $name, 'ignore' => $ignore];
        }

        return $normalized;
    }

    /**
     * @param list<array{pattern:non-empty-string, ignore:bool}> $ignorePatterns
     */
    private function ignoreResult(string $tableName, array $ignorePatterns): string
    {
        if (strcasecmp($tableName, 'dolt_rebase') === 0) {
            return 'ignore';
        }

        $trueMatches = [];
        $falseMatches = [];
        foreach ($ignorePatterns as $pattern) {
            if (!$this->matchesIgnorePattern($pattern['pattern'], $tableName, false)) {
                continue;
            }
            if ($pattern['ignore']) {
                $trueMatches[] = $pattern['pattern'];
            } else {
                $falseMatches[] = $pattern['pattern'];
            }
        }

        if ($trueMatches === []) {
            return 'dont_ignore';
        }
        if ($falseMatches === []) {
            return 'ignore';
        }

        return $this->resolveConflictingIgnorePatterns($trueMatches, $falseMatches, $tableName);
    }

    /**
     * @param list<non-empty-string> $trueMatches
     * @param list<non-empty-string> $falseMatches
     */
    private function resolveConflictingIgnorePatterns(array $trueMatches, array $falseMatches, string $tableName): string
    {
        $trueMatchesToRemove = [];
        $falseMatchesToRemove = [];

        foreach ($trueMatches as $trueMatch) {
            foreach ($falseMatches as $falseMatch) {
                if ($this->normalizeIgnorePattern($trueMatch) === $this->normalizeIgnorePattern($falseMatch)) {
                    throw new \RuntimeException($this->ignoreConflictError($tableName, [$trueMatch], [$falseMatch]));
                }
                if ($this->matchesIgnorePattern($trueMatch, $falseMatch, true)) {
                    $trueMatchesToRemove[$trueMatch] = true;
                }
            }
        }

        foreach ($falseMatches as $falseMatch) {
            foreach ($trueMatches as $trueMatch) {
                if ($this->matchesIgnorePattern($falseMatch, $trueMatch, true)) {
                    $falseMatchesToRemove[$falseMatch] = true;
                }
            }
        }

        if (count($trueMatchesToRemove) === count($trueMatches)) {
            return 'dont_ignore';
        }
        if (count($falseMatchesToRemove) === count($falseMatches)) {
            return 'ignore';
        }

        $conflictingTrueMatches = [];
        foreach ($trueMatches as $trueMatch) {
            if (!isset($trueMatchesToRemove[$trueMatch])) {
                $conflictingTrueMatches[] = $trueMatch;
            }
        }

        $conflictingFalseMatches = [];
        foreach ($falseMatches as $falseMatch) {
            if (!isset($falseMatchesToRemove[$falseMatch])) {
                $conflictingFalseMatches[] = $falseMatch;
            }
        }

        throw new \RuntimeException($this->ignoreConflictError($tableName, $conflictingTrueMatches, $conflictingFalseMatches));
    }

    /**
     * @param list<non-empty-string> $trueMatches
     * @param list<non-empty-string> $falseMatches
     */
    private function ignoreConflictError(string $tableName, array $trueMatches, array $falseMatches): string
    {
        $message = "the table {$tableName} matches conflicting patterns in dolt_ignore:";
        foreach ($trueMatches as $pattern) {
            $message .= "\nignored:     {$pattern}";
        }
        foreach ($falseMatches as $pattern) {
            $message .= "\nnot ignored: {$pattern}";
        }

        return $message;
    }

    private function matchesIgnorePattern(string $pattern, string $tableName, bool $moreSpecific): bool
    {
        $quoted = preg_quote($pattern, '~');
        $quoted = str_replace('\\?', $moreSpecific ? '[^*%]' : '.', $quoted);
        $quoted = str_replace('\\*', '.*', $quoted);
        $quoted = str_replace('%', '.*', $quoted);
        $result = preg_match('~^' . $quoted . '$~', $tableName);
        if ($result === false) {
            throw new \RuntimeException("Failed to compile Dolt ignore pattern: {$pattern}");
        }

        return $result === 1;
    }

    private function normalizeIgnorePattern(string $pattern): string
    {
        $pattern = str_replace('*', '%', $pattern);
        do {
            $previous = $pattern;
            $pattern = str_replace('%%', '%', $pattern);
        } while ($pattern !== $previous);

        return $pattern;
    }
}
