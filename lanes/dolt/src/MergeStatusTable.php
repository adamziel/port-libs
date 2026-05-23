<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class MergeStatusTable
{
    public const UNMERGED_PATHS_HEADER = 'Unmerged paths:';
    public const UNMERGED_PATHS_HELP = '  (use "dolt add <table>..." to mark resolution)';
    public const ALL_MERGED_HEADER = "All conflicts and constraint violations fixed but you are still merging.\n  (use \"dolt commit\" to conclude merge)";

    /**
     * Project the single row returned by upstream `dolt_merge_status`.
     *
     * @param list<string> $dataConflictTables
     * @param list<string> $constraintViolationTables
     * @param list<string> $schemaConflictTables
     * @return array{is_merging:bool, source:string|null, source_commit:string|null, target:string|null, unmerged_tables:string|null}
     */
    public function statusRow(
        bool $isMerging,
        ?string $source = null,
        ?string $sourceCommit = null,
        ?string $target = null,
        array $dataConflictTables = [],
        array $constraintViolationTables = [],
        array $schemaConflictTables = [],
    ): array {
        if (!$isMerging) {
            return [
                'is_merging' => false,
                'source' => null,
                'source_commit' => null,
                'target' => null,
                'unmerged_tables' => null,
            ];
        }

        foreach ([
            'source' => $source,
            'source_commit' => $sourceCommit,
            'target' => $target,
        ] as $field => $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("Active Dolt merge status requires a non-empty {$field}.");
            }
        }

        return [
            'is_merging' => true,
            'source' => $source,
            'source_commit' => $sourceCommit,
            'target' => $target,
            'unmerged_tables' => $this->tableNamesAsString($this->uniqueTableNames(
                $dataConflictTables,
                $constraintViolationTables,
                $schemaConflictTables
            )),
        ];
    }

    /**
     * Project the row shape returned by upstream `dolt_conflicts`.
     *
     * Data and schema conflict table names share the same table-name set in
     * upstream before partition rows are produced. Root-object conflicts are
     * appended separately for schema objects such as views and procedures.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $rootObjectConflicts
     * @return list<array{table:string, num_conflicts:int}>
     */
    public function conflictRows(
        array $dataConflictTables,
        array $schemaConflictTables = [],
        array $rootObjectConflicts = [],
    ): array {
        $tableRows = [];
        foreach ([$dataConflictTables, $schemaConflictTables] as $items) {
            foreach ($items as $item) {
                $normalized = $this->normalizeConflictItem($item);
                if (isset($tableRows[$normalized['name']])) {
                    $tableRows[$normalized['name']] = max($tableRows[$normalized['name']], $normalized['numConflicts']);
                    continue;
                }
                $tableRows[$normalized['name']] = $normalized['numConflicts'];
            }
        }

        $rows = [];
        foreach ($tableRows as $name => $count) {
            $rows[] = ['table' => $name, 'num_conflicts' => $count];
        }

        foreach ($rootObjectConflicts as $item) {
            $normalized = $this->normalizeConflictItem($item);
            $rows[] = ['table' => $normalized['name'], 'num_conflicts' => $normalized['numConflicts']];
        }

        return $rows;
    }

    /**
     * Render the merge-state guidance printed by upstream `dolt status`.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<string> $constraintViolationTables
     */
    public function statusGuidance(
        bool $isMerging,
        array $dataConflictTables = [],
        array $schemaConflictTables = [],
        array $constraintViolationTables = [],
    ): ?string {
        if (!$isMerging) {
            return null;
        }

        $conflictRows = $this->conflictRows($dataConflictTables, $schemaConflictTables);
        $constraintTables = $this->uniqueTableNames($constraintViolationTables);

        if ($conflictRows === [] && $constraintTables === []) {
            return self::ALL_MERGED_HEADER;
        }

        $lines = [$this->unmergedTablesHeader($conflictRows !== [], $constraintTables !== []), ''];
        foreach ($this->unmergedPathLines($dataConflictTables, $schemaConflictTables, $constraintTables) as $line) {
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Render the unresolved-path block upstream `dolt commit` adds when a
     * commit is blocked by unresolved conflicts or constraint violations.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<string> $constraintViolationTables
     */
    public function commitUnmergedPaths(
        array $dataConflictTables = [],
        array $schemaConflictTables = [],
        array $constraintViolationTables = [],
    ): ?string {
        $constraintTables = $this->uniqueTableNames($constraintViolationTables);
        $lines = $this->unmergedPathLines($dataConflictTables, $schemaConflictTables, $constraintTables);
        if ($lines === []) {
            return null;
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> ...$groups
     * @return list<non-empty-string>
     */
    private function uniqueTableNames(array ...$groups): array
    {
        $names = [];
        $seen = [];
        foreach ($groups as $group) {
            foreach ($group as $tableName) {
                if (!is_string($tableName) || $tableName === '') {
                    throw new \InvalidArgumentException('Dolt merge status table names must be non-empty strings.');
                }
                if (isset($seen[$tableName])) {
                    continue;
                }
                $seen[$tableName] = true;
                $names[] = $tableName;
            }
        }

        return $names;
    }

    /**
     * @param list<non-empty-string> $names
     */
    private function tableNamesAsString(array $names): string
    {
        return implode(', ', $names);
    }

    /**
     * @param string|array{name:string, numConflicts?:int} $item
     * @return array{name:non-empty-string, numConflicts:int}
     */
    private function normalizeConflictItem(string|array $item): array
    {
        if (is_string($item)) {
            if ($item === '') {
                throw new \InvalidArgumentException('Dolt conflict table names must be non-empty strings.');
            }

            return ['name' => $item, 'numConflicts' => 0];
        }

        $name = $item['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('Dolt conflict rows must include a non-empty table name.');
        }

        $count = $item['numConflicts'] ?? 0;
        if (!is_int($count) || $count < 0) {
            throw new \InvalidArgumentException("Dolt conflict count for {$name} must be a non-negative integer.");
        }

        return ['name' => $name, 'numConflicts' => $count];
    }

    private function unmergedTablesHeader(bool $hasConflicts, bool $hasConstraintViolations): string
    {
        $fix = match (true) {
            $hasConflicts && $hasConstraintViolations => 'conflicts and constraint violations',
            $hasConflicts => 'conflicts',
            default => 'constraint violations',
        };

        return "You have unmerged tables.\n"
            . "  (fix {$fix} and run \"dolt commit\")\n"
            . "  (use \"dolt merge --abort\" to abort the merge)";
    }

    /**
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<non-empty-string> $constraintViolationTables
     * @return list<string>
     */
    private function unmergedPathLines(
        array $dataConflictTables,
        array $schemaConflictTables,
        array $constraintViolationTables,
    ): array {
        $schemaRows = $this->conflictRows([], $schemaConflictTables);
        $dataRows = $this->conflictRows($dataConflictTables);
        $hasConflictByTable = [];
        $lines = [];

        foreach ($schemaRows as $row) {
            $hasConflictByTable[$row['table']] = true;
            $lines[] = $this->statusLine('schema conflict:', $row['table']);
        }
        foreach ($dataRows as $row) {
            $hasConflictByTable[$row['table']] = true;
            $lines[] = $this->statusLine('both modified:', $row['table']);
        }

        $violationOnly = [];
        foreach ($constraintViolationTables as $tableName) {
            if (!isset($hasConflictByTable[$tableName])) {
                $violationOnly[] = $tableName;
            }
        }
        sort($violationOnly, SORT_STRING);

        foreach ($violationOnly as $tableName) {
            $lines[] = $this->statusLine('modified', $tableName);
        }

        if ($lines === []) {
            return [];
        }

        return array_merge([self::UNMERGED_PATHS_HEADER, self::UNMERGED_PATHS_HELP], $lines);
    }

    private function statusLine(string $label, string $tableName): string
    {
        return "\t" . str_pad($label, 18, ' ', STR_PAD_RIGHT) . $tableName;
    }
}
