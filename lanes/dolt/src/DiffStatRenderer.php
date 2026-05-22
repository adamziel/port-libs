<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class DiffStatRenderer
{
    private const NO_DATA_CHANGES = 'No data changes. See schema changes by using -s or --schema.';
    private const UINT64_WRAP = '18446744073709551616';

    /**
     * Render Dolt CLI `dolt diff --stat` output for already-computed table stat rows.
     *
     * Unlike `dolt diff --summary [tables...]`, upstream `--stat [tables...]`
     * continues scanning changed tables until it finds requested table names.
     *
     * @param list<array<string, mixed>> $tableStats
     * @param array{tableNames?:list<string>} $options
     */
    public function render(array $tableStats, array $options = []): string
    {
        $blocks = [];
        foreach ($this->filteredTables($tableStats, $options) as [$table, $fromTableName, $toTableName]) {
            $blocks[] = $this->renderBlock($table, $fromTableName, $toTableName);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Render Dolt CLI `dolt diff --stat -r json` output.
     *
     * @param list<array<string, mixed>> $tableStats
     * @param array{tableNames?:list<string>} $options
     */
    public function renderJson(array $tableStats, array $options = []): string
    {
        $tables = [];
        foreach ($this->filteredTables($tableStats, $options) as [$table, $fromTableName, $toTableName]) {
            $tables[] = sprintf(
                '{"name":%s,"stats":%s}',
                json_encode($this->jsonTableName($fromTableName, $toTableName), JSON_THROW_ON_ERROR),
                $this->jsonStatsText($table)
            );
        }

        if ($tables === []) {
            return '';
        }

        return '{"tables":[' . implode(',', $tables) . ']}';
    }

    /**
     * @param list<array<string, mixed>> $tableStats
     * @param array{tableNames?:list<string>} $options
     * @return list<array{0:array<string, mixed>, 1:string, 2:string}>
     */
    private function filteredTables(array $tableStats, array $options): array
    {
        $tableNames = $options['tableNames'] ?? [];
        if (!is_array($tableNames)) {
            throw new \InvalidArgumentException('tableNames must be a list of table names.');
        }
        $tableNames = $this->normalizeTableNames($tableNames);

        $filtered = [];
        foreach ($tableStats as $table) {
            if (!is_array($table)) {
                throw new \InvalidArgumentException('Diff stat entries must be arrays.');
            }
            $fromTableName = $this->optionalTableName($table['from_table_name'] ?? null, 'from_table_name');
            $toTableName = $this->optionalTableName($table['to_table_name'] ?? null, 'to_table_name');
            if ($fromTableName === '' && $toTableName === '') {
                throw new \InvalidArgumentException('Diff stat entries must include from_table_name or to_table_name.');
            }
            if ($tableNames !== [] && !$this->matchesTableNames($fromTableName, $toTableName, $tableNames)) {
                continue;
            }

            $filtered[] = [$table, $fromTableName, $toTableName];
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $table
     */
    private function renderBlock(array $table, string $fromTableName, string $toTableName): string
    {
        $diffType = $this->diffType($table['diff_type'] ?? null, $fromTableName, $toTableName);
        $statRows = $table['statRows'] ?? [];
        if (!is_array($statRows)) {
            throw new \InvalidArgumentException('Diff stat entries must include statRows as a list.');
        }

        $oldColumnCount = $this->columnCount($table['old_column_count'] ?? null, $table['from_schema'] ?? null, 'old_column_count');
        $newColumnCount = $this->columnCount($table['new_column_count'] ?? null, $table['to_schema'] ?? null, 'new_column_count');
        $keyless = $this->keylessFlag($table);

        $lines = $this->headerLines($fromTableName, $toTableName, $diffType);
        $stats = $this->aggregate($statRows);
        array_push($lines, ...$this->statLines($stats, $oldColumnCount, $newColumnCount, $keyless));

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $table
     */
    private function jsonStatsText(array $table): string
    {
        if ($this->keylessFlag($table)) {
            return $this->keylessJsonStatsText($table);
        }

        return json_encode((object) $this->jsonStats($table), JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $table
     * @return array{rows_added:int, rows_deleted:int, rows_modified:int, rows_unmodified:int, cells_added:int, cells_deleted:int, cells_modified:int}|array{}
     */
    private function jsonStats(array $table): array
    {
        $statRows = $table['statRows'] ?? [];
        if (!is_array($statRows)) {
            throw new \InvalidArgumentException('Diff stat entries must include statRows as a list.');
        }

        $stats = $this->aggregate($statRows);
        if ($stats['adds'] + $stats['removes'] + $stats['changes'] === 0 && $stats['oldCells'] - $stats['newCells'] === 0) {
            return [];
        }
        if ($stats['oldRows'] < $stats['changes'] + $stats['removes']) {
            throw new \InvalidArgumentException('Diff stat row counts are inconsistent.');
        }

        $newColumnCount = $this->columnCount($table['new_column_count'] ?? null, $table['to_schema'] ?? null, 'new_column_count');
        [$cellsAdded, $cellsDeleted] = $this->cellsAddedAndDeleted(
            $stats['adds'],
            $stats['removes'],
            $stats['oldCells'],
            $stats['newCells'],
            $newColumnCount
        );

        return [
            'rows_added' => $stats['adds'],
            'rows_deleted' => $stats['removes'],
            'rows_modified' => $stats['changes'],
            'rows_unmodified' => $stats['oldRows'] - $stats['changes'] - $stats['removes'],
            'cells_added' => $cellsAdded,
            'cells_deleted' => $cellsDeleted,
            'cells_modified' => $stats['cellChanges'],
        ];
    }

    /**
     * @param array<string, mixed> $table
     */
    private function keylessJsonStatsText(array $table): string
    {
        $statRows = $table['statRows'] ?? [];
        if (!is_array($statRows)) {
            throw new \InvalidArgumentException('Diff stat entries must include statRows as a list.');
        }

        $stats = $this->aggregate($statRows);
        if ($stats['adds'] + $stats['removes'] + $stats['changes'] === 0 && $stats['oldCells'] - $stats['newCells'] === 0) {
            return '{}';
        }

        $newColumnCount = $this->newColumnCountForJson($table);
        $rowCellChanges = max($stats['adds'], $stats['removes']) * $newColumnCount;

        return sprintf(
            '{"rows_added":%d,"rows_deleted":%d,"rows_modified":0,"rows_unmodified":%s,"cells_added":%d,"cells_deleted":%d,"cells_modified":0}',
            $stats['adds'],
            $stats['removes'],
            $this->uint64DifferenceText($stats['oldRows'], $stats['changes'], $stats['removes']),
            $rowCellChanges,
            $rowCellChanges
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{adds:int, removes:int, changes:int, cellChanges:int, oldRows:int, newRows:int, oldCells:int, newCells:int}
     */
    private function aggregate(array $rows): array
    {
        $stats = [
            'adds' => 0,
            'removes' => 0,
            'changes' => 0,
            'cellChanges' => 0,
            'oldRows' => 0,
            'newRows' => 0,
            'oldCells' => 0,
            'newCells' => 0,
        ];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('Diff stat rows must be arrays.');
            }
            $stats['adds'] += $this->uintField($row['rows_added'] ?? 0, 'rows_added');
            $stats['removes'] += $this->uintField($row['rows_deleted'] ?? 0, 'rows_deleted');
            $stats['changes'] += $this->uintField($row['rows_modified'] ?? 0, 'rows_modified');
            $stats['cellChanges'] += $this->uintField($row['cells_modified'] ?? 0, 'cells_modified');
            $stats['oldRows'] += $this->uintField($row['old_row_count'] ?? 0, 'old_row_count');
            $stats['newRows'] += $this->uintField($row['new_row_count'] ?? 0, 'new_row_count');
            $stats['oldCells'] += $this->uintField($row['old_cell_count'] ?? 0, 'old_cell_count');
            $stats['newCells'] += $this->uintField($row['new_cell_count'] ?? 0, 'new_cell_count');
        }

        return $stats;
    }

    /**
     * @param array{adds:int, removes:int, changes:int, cellChanges:int, oldRows:int, newRows:int, oldCells:int, newCells:int} $stats
     * @return list<string>
     */
    private function statLines(array $stats, int $oldColumnCount, int $newColumnCount, bool $keyless): array
    {
        if ($stats['adds'] + $stats['removes'] + $stats['changes'] === 0 && $stats['oldCells'] - $stats['newCells'] === 0) {
            return [self::NO_DATA_CHANGES];
        }

        if ($keyless) {
            return [
                $this->pluralize('Row Added', 'Rows Added', $stats['adds']),
                $this->pluralize('Row Deleted', 'Rows Deleted', $stats['removes']),
            ];
        }

        if ($stats['oldRows'] < $stats['changes'] + $stats['removes']) {
            throw new \InvalidArgumentException('Diff stat row counts are inconsistent.');
        }

        [$cellsAdded, $cellsDeleted] = $this->cellsAddedAndDeleted(
            $stats['adds'],
            $stats['removes'],
            $stats['oldCells'],
            $stats['newCells'],
            $newColumnCount
        );
        $rowsUnmodified = $stats['oldRows'] - $stats['changes'] - $stats['removes'];

        return [
            sprintf(
                '%s (%s%%)',
                $this->pluralize('Row Unmodified', 'Rows Unmodified', $rowsUnmodified),
                $this->safePercent($rowsUnmodified, $stats['oldRows'])
            ),
            sprintf(
                '%s (%s%%)',
                $this->pluralize('Row Added', 'Rows Added', $stats['adds']),
                $this->safePercent($stats['adds'], $stats['oldRows'])
            ),
            sprintf(
                '%s (%s%%)',
                $this->pluralize('Row Deleted', 'Rows Deleted', $stats['removes']),
                $this->safePercent($stats['removes'], $stats['oldRows'])
            ),
            sprintf(
                '%s (%s%%)',
                $this->pluralize('Row Modified', 'Rows Modified', $stats['changes']),
                $this->safePercent($stats['changes'], $stats['oldRows'])
            ),
            sprintf(
                '%s (%s%%)',
                $this->pluralize('Cell Added', 'Cells Added', $cellsAdded),
                $this->safePercent($cellsAdded, $stats['oldCells'])
            ),
            sprintf(
                '%s (%s%%)',
                $this->pluralize('Cell Deleted', 'Cells Deleted', $cellsDeleted),
                $this->safePercent($cellsDeleted, $stats['oldCells'])
            ),
            sprintf(
                '%s (%s%%)',
                $this->pluralize('Cell Modified', 'Cells Modified', $stats['cellChanges']),
                $this->rawPercent($stats['cellChanges'], $stats['oldRows'] * $oldColumnCount)
            ),
            sprintf(
                '(%s vs %s)',
                $this->pluralize('Row Entry', 'Row Entries', $stats['oldRows']),
                $this->pluralize('Row Entry', 'Row Entries', $stats['newRows'])
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function headerLines(string $fromTableName, string $toTableName, string $diffType): array
    {
        if ($diffType === TableDeltaMatcher::DIFF_ADDED) {
            $name = $this->requiredName($toTableName, 'to_table_name');

            return ["diff --dolt a/{$name} b/{$name}", 'added table'];
        }
        if ($diffType === TableDeltaMatcher::DIFF_DROPPED) {
            $name = $this->requiredName($fromTableName, 'from_table_name');

            return ["diff --dolt a/{$name} b/{$name}", 'deleted table'];
        }

        $from = $this->requiredName($fromTableName, 'from_table_name');
        $to = $this->requiredName($toTableName, 'to_table_name');

        return ["diff --dolt a/{$from} b/{$to}", "--- a/{$from}", "+++ b/{$to}"];
    }

    private function jsonTableName(string $fromTableName, string $toTableName): string
    {
        return $fromTableName === '' ? $this->requiredName($toTableName, 'to_table_name') : $fromTableName;
    }

    private function diffType(mixed $value, string $fromTableName, string $toTableName): string
    {
        if ($value === null || $value === '') {
            if ($fromTableName === '') {
                return TableDeltaMatcher::DIFF_ADDED;
            }
            if ($toTableName === '') {
                return TableDeltaMatcher::DIFF_DROPPED;
            }

            return TableDeltaMatcher::DIFF_MODIFIED;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Diff stat diff_type must be a string.');
        }
        if (!in_array($value, [
            TableDeltaMatcher::DIFF_ADDED,
            TableDeltaMatcher::DIFF_MODIFIED,
            TableDeltaMatcher::DIFF_RENAMED,
            TableDeltaMatcher::DIFF_DROPPED,
        ], true)) {
            throw new \InvalidArgumentException("Unsupported diff stat diff_type: {$value}");
        }

        return $value;
    }

    private function columnCount(mixed $explicit, mixed $schema, string $field): int
    {
        if ($explicit === null && $schema instanceof TableSchema) {
            return count($schema->columns());
        }
        if (!is_int($explicit) || $explicit < 0) {
            throw new \InvalidArgumentException("Diff stat {$field} must be a non-negative integer.");
        }

        return $explicit;
    }

    /**
     * @param array<string, mixed> $table
     */
    private function newColumnCountForJson(array $table): int
    {
        $explicit = $table['new_column_count'] ?? null;
        if ($explicit !== null) {
            return $this->columnCount($explicit, null, 'new_column_count');
        }
        $schema = $table['to_schema'] ?? null;
        if ($schema instanceof TableSchema) {
            return count($schema->columns());
        }

        return 0;
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function cellsAddedAndDeleted(int $added, int $deleted, int $oldCellCount, int $newCellCount, int $newColumnCount): array
    {
        $rowToCellInserts = $added * $newColumnCount;
        $rowToCellDeletes = $deleted * $newColumnCount;
        $cellDiff = $newCellCount - $oldCellCount;

        if ($cellDiff > 0) {
            return [$cellDiff + $rowToCellDeletes, $rowToCellDeletes];
        }
        if ($cellDiff < 0) {
            return [$rowToCellInserts, abs($cellDiff) + $rowToCellInserts];
        }
        if ($rowToCellInserts !== $rowToCellDeletes) {
            $max = max($rowToCellDeletes, $rowToCellInserts);

            return [$max, $max];
        }

        return [$rowToCellInserts, $rowToCellDeletes];
    }

    private function pluralize(string $singular, string $plural, int $count): string
    {
        return number_format($count, 0, '.', ',') . ' ' . ($count === 1 ? $singular : $plural);
    }

    private function safePercent(int $num, int $den): string
    {
        if ($num === 0) {
            return '0.00';
        }

        return $this->formatFloat($den === 0 ? INF : (100 * $num) / $den);
    }

    private function rawPercent(int $num, int $den): string
    {
        if ($den === 0) {
            return $this->formatFloat($num === 0 ? NAN : INF);
        }

        return $this->formatFloat((100 * $num) / $den);
    }

    private function formatFloat(float $value): string
    {
        if (is_nan($value)) {
            return 'NaN';
        }
        if (is_infinite($value)) {
            return $value > 0 ? '+Inf' : '-Inf';
        }

        return sprintf('%.2f', $value);
    }

    private function uintField(mixed $value, string $field): int
    {
        if ($value === null) {
            return 0;
        }
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("Diff stat field {$field} must be a non-negative integer or null.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $table
     */
    private function keylessFlag(array $table): bool
    {
        $keyless = $table['keyless'] ?? false;
        if (!is_bool($keyless)) {
            throw new \InvalidArgumentException('Diff stat keyless flag must be boolean.');
        }

        return $keyless;
    }

    private function uint64DifferenceText(int $oldRows, int $changes, int $removes): string
    {
        $value = $oldRows - $changes - $removes;
        if ($value >= 0) {
            return (string) $value;
        }

        return $this->decimalSubtract(self::UINT64_WRAP, -$value);
    }

    private function decimalSubtract(string $value, int $subtract): string
    {
        $digits = array_map('intval', str_split($value));
        $subtractDigits = array_reverse(str_split((string) $subtract));
        $index = count($digits) - 1;

        foreach ($subtractDigits as $digit) {
            if ($index < 0) {
                throw new \InvalidArgumentException('Diff stat unsigned row count underflow is too large.');
            }

            $digits[$index] -= (int) $digit;
            while ($digits[$index] < 0) {
                $digits[$index] += 10;
                if ($index === 0) {
                    throw new \InvalidArgumentException('Diff stat unsigned row count underflow is too large.');
                }
                $digits[$index - 1]--;
            }
            $index--;
        }

        for ($i = count($digits) - 1; $i > 0; $i--) {
            if ($digits[$i] >= 0) {
                continue;
            }
            $digits[$i] += 10;
            $digits[$i - 1]--;
        }

        return ltrim(implode('', $digits), '0') ?: '0';
    }

    private function optionalTableName(mixed $value, string $field): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Diff stat field {$field} must be a string or null.");
        }

        return $value;
    }

    private function requiredName(string $value, string $field): string
    {
        if ($value === '') {
            throw new \InvalidArgumentException("Diff stat field {$field} must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param list<mixed> $tableNames
     * @return list<string>
     */
    private function normalizeTableNames(array $tableNames): array
    {
        $normalized = [];
        foreach ($tableNames as $name) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('tableNames must contain non-empty strings.');
            }
            $normalized[] = strtolower($name);
        }

        return $normalized;
    }

    /**
     * @param list<string> $tableNames
     */
    private function matchesTableNames(string $fromTableName, string $toTableName, array $tableNames): bool
    {
        foreach ([$fromTableName, $toTableName] as $name) {
            if ($name !== '' && in_array(strtolower($name), $tableNames, true)) {
                return true;
            }
        }

        return false;
    }
}
