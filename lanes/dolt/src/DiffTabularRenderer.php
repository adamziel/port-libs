<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class DiffTabularRenderer
{
    private const MODE_ROW = 'row';
    private const MODE_LINE = 'line';
    private const MODE_IN_PLACE = 'in-place';
    private const MODE_CONTEXT = 'context';

    /**
     * Render projected `DOLT_DIFF_*` rows as Dolt's row-mode tabular CLI diff.
     *
     * @param list<array<string, scalar|null>> $diffRows
     * @param array{filter?:string|null, fromTableName?:string|null, toTableName?:string|null, diffMode?:string|null} $options
     */
    public function render(string $tableName, TableSchema $schema, array $diffRows, array $options = []): string
    {
        if ($tableName === '') {
            throw new \InvalidArgumentException('Tabular diff table name must be a non-empty string.');
        }

        $filter = $this->normalizeFilter($options['filter'] ?? null);
        $diffMode = $this->normalizeDiffMode($options['diffMode'] ?? null);
        $rows = [];
        foreach ($diffRows as $row) {
            $diffType = $this->requiredDiffType($row['diff_type'] ?? null);
            if ($filter !== null && $diffType !== $filter) {
                continue;
            }

            foreach ($this->displayRows($schema, $row, $diffType, $diffMode) as $displayRow) {
                $rows[] = $displayRow;
            }
        }

        if ($rows === []) {
            return '';
        }

        $fromTableName = $this->tableName($options['fromTableName'] ?? null, $tableName, 'fromTableName');
        $toTableName = $this->tableName($options['toTableName'] ?? null, $tableName, 'toTableName');

        return implode("\n", array_merge(
            [
                "diff --dolt a/{$fromTableName} b/{$toTableName}",
                "--- a/{$fromTableName}",
                "+++ b/{$toTableName}",
            ],
            $this->fixedWidthTable($schema, $rows)
        ));
    }

    private function normalizeFilter(mixed $filter): ?string
    {
        if ($filter === null || $filter === '' || $filter === 'all') {
            return null;
        }
        if (!is_string($filter)) {
            throw new \InvalidArgumentException('Tabular diff filter must be a string.');
        }
        if ($filter === TableDiff::DIFF_ADDED || $filter === TableDiff::DIFF_MODIFIED) {
            return $filter;
        }
        if ($filter === TableDiff::DIFF_REMOVED || $filter === TableDeltaMatcher::DIFF_DROPPED) {
            return TableDiff::DIFF_REMOVED;
        }
        if ($filter === TableDeltaMatcher::DIFF_RENAMED) {
            return TableDeltaMatcher::DIFF_RENAMED;
        }

        throw new \InvalidArgumentException(
            "invalid filter: {$filter}. Valid values are: added, modified, renamed, dropped (or removed)"
        );
    }

    private function normalizeDiffMode(mixed $mode): string
    {
        if ($mode === null || $mode === '') {
            return self::MODE_CONTEXT;
        }
        if (!is_string($mode)) {
            throw new \InvalidArgumentException('Tabular diff mode must be a string.');
        }

        $normalized = strtolower($mode);
        if (in_array($normalized, [self::MODE_ROW, self::MODE_LINE, self::MODE_IN_PLACE, self::MODE_CONTEXT], true)) {
            return $normalized;
        }

        throw new \InvalidArgumentException(
            "invalid diff mode: {$mode}. Valid values are: row, line, in-place, context"
        );
    }

    private function requiredDiffType(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('Tabular diff rows must include a non-empty diff_type.');
        }
        if (!in_array($value, [TableDiff::DIFF_ADDED, TableDiff::DIFF_REMOVED, TableDiff::DIFF_MODIFIED], true)) {
            throw new \InvalidArgumentException("Unsupported row diff_type: {$value}");
        }

        return $value;
    }

    /**
     * @param array<string, scalar|null> $row
     * @return list<list<string>>
     */
    private function displayRows(TableSchema $schema, array $row, string $diffType, string $diffMode): array
    {
        if ($diffType === TableDiff::DIFF_ADDED) {
            return [$this->rowForPrefix($schema, $row, '+', 'to_')];
        }
        if ($diffType === TableDiff::DIFF_REMOVED) {
            return [$this->rowForPrefix($schema, $row, '-', 'from_')];
        }

        if ($diffMode === self::MODE_ROW || ($diffMode === self::MODE_CONTEXT && !$this->contextUsesCombinedRow($schema, $row))) {
            return [
                $this->rowForPrefix($schema, $row, '<', 'from_'),
                $this->rowForPrefix($schema, $row, '>', 'to_'),
            ];
        }

        return [$this->combinedModifiedRow($schema, $row, $diffMode)];
    }

    /**
     * @param array<string, scalar|null> $row
     * @return list<string>
     */
    private function rowForPrefix(TableSchema $schema, array $row, string $marker, string $prefix): array
    {
        $values = [$marker];
        foreach ($schema->columns() as $column) {
            $values[] = $this->valueString($row[$prefix . $column['name']] ?? null);
        }

        return $values;
    }

    /**
     * @param array<string, scalar|null> $row
     */
    private function contextUsesCombinedRow(TableSchema $schema, array $row): bool
    {
        foreach ($schema->columns() as $column) {
            $name = $column['name'];
            $old = $this->valueString($row['from_' . $name] ?? null);
            $new = $this->valueString($row['to_' . $name] ?? null);
            if ($old === $new) {
                if (count($this->lines($old)) > 1) {
                    return true;
                }
                continue;
            }

            if (count($this->lines($this->lineDiffText($old, $new))) > 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, scalar|null> $row
     * @return list<string>
     */
    private function combinedModifiedRow(TableSchema $schema, array $row, string $diffMode): array
    {
        $values = ['*'];
        foreach ($schema->columns() as $column) {
            $name = $column['name'];
            $old = $this->valueString($row['from_' . $name] ?? null);
            $new = $this->valueString($row['to_' . $name] ?? null);
            if ($old === $new) {
                $values[] = $old;
            } elseif ($diffMode === self::MODE_IN_PLACE) {
                $values[] = $this->inPlaceDiffText($old, $new);
            } else {
                $values[] = $this->lineDiffText($old, $new);
            }
        }

        return $values;
    }

    private function lineDiffText(string $old, string $new): string
    {
        $lines = [];
        foreach ($this->sequenceDiff($this->lines($old), $this->lines($new)) as $op) {
            $prefix = match ($op['type']) {
                'equal' => ' ',
                'delete' => '-',
                'insert' => '+',
            };
            $lines[] = $prefix . $op['value'];
        }

        return implode("\n", $lines);
    }

    private function inPlaceDiffText(string $old, string $new): string
    {
        if ($old === $new) {
            return $old;
        }
        if (!str_contains($old, "\n") && !str_contains($new, "\n")) {
            return $this->inPlaceLineDiff($old, $new);
        }

        $lines = [];
        $deleted = [];
        $inserted = [];
        foreach ($this->sequenceDiff($this->lines($old), $this->lines($new)) as $op) {
            if ($op['type'] === 'equal') {
                array_push($lines, ...$this->inPlaceChangedLines($deleted, $inserted));
                $deleted = [];
                $inserted = [];
                $lines[] = $op['value'];
            } elseif ($op['type'] === 'delete') {
                $deleted[] = $op['value'];
            } else {
                $inserted[] = $op['value'];
            }
        }
        array_push($lines, ...$this->inPlaceChangedLines($deleted, $inserted));

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $deleted
     * @param list<string> $inserted
     * @return list<string>
     */
    private function inPlaceChangedLines(array $deleted, array $inserted): array
    {
        $lines = [];
        while ($deleted !== [] && $inserted !== []) {
            $old = $deleted[0];
            $new = $inserted[0];
            $score = $this->similarityScore($old, $new);

            if ($score < 0.45 && count($deleted) > 1 && $this->similarityScore($deleted[1], $new) > $score) {
                $lines[] = array_shift($deleted);
                continue;
            }
            if ($score < 0.45 && count($inserted) > 1 && $this->similarityScore($old, $inserted[1]) > $score) {
                $lines[] = array_shift($inserted);
                continue;
            }

            $lines[] = $score >= 0.45 ? $this->inPlaceLineDiff($old, $new) : $old . $new;
            array_shift($deleted);
            array_shift($inserted);
        }

        return array_merge($lines, $deleted, $inserted);
    }

    private function inPlaceLineDiff(string $old, string $new): string
    {
        $prefixLength = $this->commonPrefixLength($old, $new);
        $suffixLength = $this->commonSuffixLength(
            substr($old, $prefixLength),
            substr($new, $prefixLength)
        );
        $oldMiddleLength = strlen($old) - $prefixLength - $suffixLength;
        $newMiddleLength = strlen($new) - $prefixLength - $suffixLength;
        $oldMiddle = substr($old, $prefixLength, $oldMiddleLength);
        $newMiddle = substr($new, $prefixLength, $newMiddleLength);

        if ($oldMiddle !== '' && $newMiddle !== '' && preg_match('/^[[:punct:]]+$/', $oldMiddle) === 1) {
            $oldMiddle = '';
        }

        return substr($old, 0, $prefixLength)
            . $oldMiddle
            . $newMiddle
            . ($suffixLength === 0 ? '' : substr($old, -$suffixLength));
    }

    private function commonPrefixLength(string $a, string $b): int
    {
        $max = min(strlen($a), strlen($b));
        for ($i = 0; $i < $max; $i++) {
            if ($a[$i] !== $b[$i]) {
                return $i;
            }
        }

        return $max;
    }

    private function commonSuffixLength(string $a, string $b): int
    {
        $max = min(strlen($a), strlen($b));
        for ($i = 0; $i < $max; $i++) {
            if ($a[strlen($a) - 1 - $i] !== $b[strlen($b) - 1 - $i]) {
                return $i;
            }
        }

        return $max;
    }

    private function similarityScore(string $a, string $b): float
    {
        $max = max(strlen($a), strlen($b));
        if ($max === 0) {
            return 1.0;
        }

        return $this->longestCommonSubsequenceLength(str_split($a), str_split($b)) / $max;
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     * @return list<array{type:'equal'|'delete'|'insert', value:string}>
     */
    private function sequenceDiff(array $old, array $new): array
    {
        $n = count($old);
        $m = count($new);
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                if ($old[$i] === $new[$j]) {
                    $dp[$i][$j] = $dp[$i + 1][$j + 1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i + 1][$j], $dp[$i][$j + 1]);
                }
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($old[$i] === $new[$j]) {
                $ops[] = ['type' => 'equal', 'value' => $old[$i]];
                $i++;
                $j++;
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $ops[] = ['type' => 'delete', 'value' => $old[$i]];
                $i++;
            } else {
                $ops[] = ['type' => 'insert', 'value' => $new[$j]];
                $j++;
            }
        }
        while ($i < $n) {
            $ops[] = ['type' => 'delete', 'value' => $old[$i]];
            $i++;
        }
        while ($j < $m) {
            $ops[] = ['type' => 'insert', 'value' => $new[$j]];
            $j++;
        }

        return $ops;
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     */
    private function longestCommonSubsequenceLength(array $a, array $b): int
    {
        $n = count($a);
        $m = count($b);
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = $a[$i] === $b[$j]
                    ? $dp[$i + 1][$j + 1] + 1
                    : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }

        return $dp[0][0];
    }

    /**
     * @param list<list<string>> $rows
     * @return list<string>
     */
    private function fixedWidthTable(TableSchema $schema, array $rows): array
    {
        $columns = [' '];
        foreach ($schema->columns() as $column) {
            $columns[] = $column['name'];
        }

        $widths = array_map([$this, 'displayWidth'], $columns);
        foreach ($rows as $row) {
            if (count($row) !== count($columns)) {
                throw new \InvalidArgumentException('Tabular diff rows do not match schema column count.');
            }
            foreach ($row as $index => $value) {
                $widths[$index] = max($widths[$index], $this->displayWidth($value));
            }
        }

        $separator = $this->separator($widths);
        $lines = [$separator, $this->rowLine($columns, $widths), $separator];
        foreach ($rows as $row) {
            array_push($lines, ...$this->bodyRowLines($row, $widths));
        }
        $lines[] = $separator;

        return $lines;
    }

    /**
     * @param list<string> $values
     * @param list<int> $widths
     * @return list<string>
     */
    private function bodyRowLines(array $values, array $widths): array
    {
        $split = array_map([$this, 'lines'], $values);
        $height = max(array_map('count', $split));
        $lines = [];

        for ($lineIndex = 0; $lineIndex < $height; $lineIndex++) {
            $lineValues = [];
            foreach ($split as $columnLines) {
                $lineValues[] = $columnLines[$lineIndex] ?? '';
            }
            $lines[] = $this->rowLine($lineValues, $widths);
        }

        return $lines;
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

    private function valueString(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException('Tabular diff values must be scalar or null.');
    }

    private function tableName(mixed $value, string $default, string $field): string
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Tabular diff {$field} must be a string or null.");
        }

        return $value;
    }

    private function displayWidth(string $value): int
    {
        $max = 0;
        foreach ($this->lines($value) as $line) {
            $max = max($max, strlen($line));
        }

        return $max;
    }

    /**
     * @return non-empty-list<string>
     */
    private function lines(string $value): array
    {
        return explode("\n", $value);
    }
}
