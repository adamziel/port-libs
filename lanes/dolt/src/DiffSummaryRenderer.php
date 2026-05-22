<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class DiffSummaryRenderer
{
    private const COLUMNS = ['Table name', 'Diff type', 'Data change', 'Schema change'];

    /**
     * Render rows from `dolt_diff_summary()` in the same fixed-width table
     * shape used by `dolt diff --summary`.
     *
     * @param list<array<string, mixed>> $summaryRows
     * @param array{tableNames?:list<string>, filter?:string|null} $options
     */
    public function render(array $summaryRows, array $options = []): string
    {
        $rows = $this->sortedRows($this->filteredRows($summaryRows, $options));

        return $this->renderRows($rows);
    }

    /**
     * Render `dolt diff --summary [tables...]` output using upstream's CLI
     * table-argument behavior. Upstream stops at the first changed table outside
     * the requested table set instead of continuing to search later summaries.
     *
     * @param list<array<string, mixed>> $summaryRows
     * @param list<string> $tableNames
     * @param array{filter?:string|null} $options
     */
    public function renderForTableArgs(array $summaryRows, array $tableNames, array $options = []): string
    {
        $tableNames = $this->normalizeTableNames($tableNames);
        if ($tableNames === []) {
            return $this->render($summaryRows, $options);
        }

        $filter = $this->normalizeFilter($options['filter'] ?? null);
        $rows = [];
        foreach ($summaryRows as $row) {
            if (!$this->rowMatchesTableNames($row, $tableNames)) {
                break;
            }
            if ($filter !== null && $this->requiredDiffType($row['diff_type'] ?? null) !== $filter) {
                continue;
            }
            $rows[] = $row;
        }

        return $this->renderRows($rows);
    }

    /**
     * Render the table names used by `dolt diff --name-only`.
     *
     * @param list<array<string, mixed>> $summaryRows
     * @param array{tableNames?:list<string>, filter?:string|null} $options
     */
    public function renderNameOnly(array $summaryRows, array $options = []): string
    {
        $rows = $this->sortedRows($this->filteredRows($summaryRows, $options));
        if ($rows === []) {
            return '';
        }

        return implode("\n", array_map(fn (array $row): string => $this->displayTableName($row), $rows));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sortedRows(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            return $this->displayTableName($a) <=> $this->displayTableName($b);
        });

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array{tableNames?:list<string>, filter?:string|null} $options
     * @return list<array<string, mixed>>
     */
    private function filteredRows(array $rows, array $options): array
    {
        $tableNames = $options['tableNames'] ?? [];
        if (!is_array($tableNames)) {
            throw new \InvalidArgumentException('tableNames must be a list of table names.');
        }
        $tableNames = $this->normalizeTableNames($tableNames);
        if ($tableNames !== []) {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $this->rowMatchesTableNames($row, $tableNames)));
        }

        $filter = $this->normalizeFilter($options['filter'] ?? null);
        if ($filter === null) {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($filter): bool {
            return $this->requiredDiffType($row['diff_type'] ?? null) === $filter;
        }));
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function renderRows(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $table = [];
        foreach ($rows as $row) {
            $table[] = [
                $this->displayTableName($row),
                $this->requiredDiffType($row['diff_type'] ?? null),
                $this->boolString($row['data_change'] ?? null, 'data_change'),
                $this->boolString($row['schema_change'] ?? null, 'schema_change'),
            ];
        }

        $widths = array_map('strlen', self::COLUMNS);
        foreach ($table as $row) {
            foreach ($row as $index => $value) {
                $widths[$index] = max($widths[$index], strlen($value));
            }
        }

        $separator = $this->separator($widths);
        $lines = [$separator, $this->rowLine(self::COLUMNS, $widths), $separator];
        foreach ($table as $row) {
            $lines[] = $this->rowLine($row, $widths);
        }
        $lines[] = $separator;

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $tableNames
     */
    private function rowMatchesTableNames(array $row, array $tableNames): bool
    {
        foreach ($this->candidateNames($row) as $name) {
            if (in_array(strtolower($name), $tableNames, true)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeFilter(mixed $filter): ?string
    {
        if ($filter === null || $filter === '' || $filter === 'all') {
            return null;
        }
        if (!is_string($filter)) {
            throw new \InvalidArgumentException('Diff summary filter must be a string.');
        }
        if ($filter === 'removed') {
            return TableDeltaMatcher::DIFF_DROPPED;
        }
        if (!in_array($filter, [
            TableDeltaMatcher::DIFF_ADDED,
            TableDeltaMatcher::DIFF_MODIFIED,
            TableDeltaMatcher::DIFF_RENAMED,
            TableDeltaMatcher::DIFF_DROPPED,
        ], true)) {
            throw new \InvalidArgumentException(
                "invalid filter: {$filter}. Valid values are: added, modified, renamed, dropped (or removed)"
            );
        }

        return $filter;
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
     * @param array<string, mixed> $row
     */
    private function displayTableName(array $row): string
    {
        $diffType = $this->requiredDiffType($row['diff_type'] ?? null);
        $from = $this->optionalString($row['from_table_name'] ?? null, 'from_table_name');
        $to = $this->optionalString($row['to_table_name'] ?? null, 'to_table_name');
        $table = $this->optionalString($row['table_name'] ?? null, 'table_name');

        if ($diffType === TableDeltaMatcher::DIFF_RENAMED) {
            if ($from === '' || $to === '') {
                throw new \InvalidArgumentException('Renamed summary rows must include from_table_name and to_table_name.');
            }

            return "{$from} -> {$to}";
        }
        if ($diffType === TableDeltaMatcher::DIFF_DROPPED && $from !== '') {
            return $from;
        }
        if ($to !== '') {
            return $to;
        }
        if ($from !== '') {
            return $from;
        }
        if ($table !== '') {
            return $table;
        }

        throw new \InvalidArgumentException('Summary rows must include a table name.');
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    private function candidateNames(array $row): array
    {
        $names = [];
        foreach (['from_table_name', 'to_table_name', 'table_name'] as $field) {
            $value = $this->optionalString($row[$field] ?? null, $field);
            if ($value !== '' && !in_array($value, $names, true)) {
                $names[] = $value;
            }
        }

        return $names;
    }

    private function requiredDiffType(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('Summary rows must include a non-empty diff_type.');
        }
        if (!in_array($value, [
            TableDeltaMatcher::DIFF_ADDED,
            TableDeltaMatcher::DIFF_MODIFIED,
            TableDeltaMatcher::DIFF_RENAMED,
            TableDeltaMatcher::DIFF_DROPPED,
        ], true)) {
            throw new \InvalidArgumentException("Unsupported summary diff_type: {$value}");
        }

        return $value;
    }

    private function optionalString(mixed $value, string $field): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Summary field {$field} must be a string or null.");
        }

        return $value;
    }

    private function boolString(mixed $value, string $field): string
    {
        if (!is_bool($value)) {
            throw new \InvalidArgumentException("Summary field {$field} must be a boolean.");
        }

        return $value ? 'true' : 'false';
    }

    /**
     * @param list<int> $widths
     */
    private function separator(array $widths): string
    {
        $parts = array_map(static fn (int $width): string => str_repeat('-', $width + 2), $widths);

        return '+' . implode('+', $parts) . '+';
    }

    /**
     * @param list<string> $values
     * @param list<int> $widths
     */
    private function rowLine(array $values, array $widths): string
    {
        $cells = [];
        foreach ($values as $index => $value) {
            $cells[] = ' ' . str_pad($value, $widths[$index], ' ', STR_PAD_RIGHT) . ' ';
        }

        return '|' . implode('|', $cells) . '|';
    }
}
