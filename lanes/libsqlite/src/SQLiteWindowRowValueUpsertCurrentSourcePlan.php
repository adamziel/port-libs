<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWindowRowValueUpsertCurrentSourcePlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $conflictColumns
     * @param list<string> $rowValueColumns
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,window_rows:list<array<string,mixed>>,decisions:list<array<string,mixed>>,changes:int,status:string,detail:string}
     */
    public static function execute(
        array $rows,
        array $incomingRows,
        array $conflictColumns,
        array $rowValueColumns,
        string $operator = '>',
        int $preceding = 1,
        int $following = 1,
        string $exclude = 'NO OTHERS',
    ): array {
        self::validateRows($rows, 'current');
        self::validateRows($incomingRows, 'incoming');
        self::validateColumns($conflictColumns, 'conflict');
        self::validateColumns($rowValueColumns, 'row-value');
        if ($preceding < 0 || $following < 0) {
            throw new \InvalidArgumentException('SQLite UPSERT window frame offsets must be non-negative');
        }

        $operator = strtoupper(trim($operator));
        if (!in_array($operator, ['>', '>=', '<', '<=', '=', '<>', '!=', 'IS', 'IS NOT'], true)) {
            throw new \InvalidArgumentException('SQLite UPSERT row-value operator is unsupported');
        }

        $before = $rows;
        $after = $rows;
        $inserted = [];
        $updated = [];
        $skipped = [];
        $returning = [];
        $decisions = [];

        foreach ($incomingRows as $incomingIndex => $incoming) {
            self::ensureColumns($incoming, array_merge($conflictColumns, $rowValueColumns), 'incoming');
            $conflictIndex = self::findConflictIndex($after, $incoming, $conflictColumns);
            if ($conflictIndex === null) {
                $after[] = $incoming;
                $inserted[] = $incoming;
                $returning[] = self::withAction($incoming, 'insert', $incomingIndex + 1);
                $decisions[] = [
                    'incoming_index' => $incomingIndex,
                    'action' => 'insert',
                    'current_tuple' => null,
                    'excluded_tuple' => self::tuple($incoming, $rowValueColumns),
                    'predicate' => null,
                    'source' => 'current-source',
                ];
                continue;
            }

            $current = $after[$conflictIndex];
            self::ensureColumns($current, array_merge($conflictColumns, $rowValueColumns), 'current');
            $currentTuple = self::tuple($current, $rowValueColumns);
            $excludedTuple = self::tuple($incoming, $rowValueColumns);
            $predicate = self::rowValuePredicate($excludedTuple, $operator, $currentTuple);
            if ($predicate !== true) {
                $skipped[] = $incoming;
                $decisions[] = [
                    'incoming_index' => $incomingIndex,
                    'action' => 'skip',
                    'current_tuple' => $currentTuple,
                    'excluded_tuple' => $excludedTuple,
                    'predicate' => $predicate,
                    'source' => 'current-source',
                ];
                continue;
            }

            $updatedRow = $current;
            foreach ($incoming as $column => $value) {
                $updatedRow[$column] = $value;
            }
            $after[$conflictIndex] = $updatedRow;
            $updated[] = $updatedRow;
            $returning[] = self::withAction($updatedRow, 'update', $incomingIndex + 1);
            $decisions[] = [
                'incoming_index' => $incomingIndex,
                'action' => 'update',
                'current_tuple' => $currentTuple,
                'excluded_tuple' => $excludedTuple,
                'predicate' => $predicate,
                'source' => 'current-source',
            ];
        }

        return [
            'before' => $before,
            'after' => array_values($after),
            'inserted_rows' => $inserted,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'returning_rows' => $returning,
            'window_rows' => self::windowRows($returning, $preceding, $following, $exclude, $conflictColumns[0]),
            'decisions' => $decisions,
            'changes' => count($returning),
            'status' => 'window-rowvalue-upsert-current-source-ready',
            'detail' => 'UPSERT DO UPDATE WHERE row-value comparisons read the statement current source, and changed rows feed a bounded RETURNING window frame.',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function windowRows(array $rows, int $preceding, int $following, string $exclude, string $labelColumn): array
    {
        $priorities = array_map(static fn (array $row): int|float|bool => self::numericValue($row['priority'] ?? 0, 'priority'), $rows);
        $orderKeys = array_map(static fn (array $row): int|float|bool => self::numericValue($row['sequence'] ?? 0, 'sequence'), $rows);
        $frames = SQLiteWindowFunction::aggregateFrameRows($priorities, $orderKeys, 'ROWS', $preceding, $following, $exclude);
        $firstNames = SQLiteWindowFunction::valueFrameValues('first_value', array_column($rows, $labelColumn), $orderKeys, 'ROWS', $preceding, $following, $exclude);
        $lastNames = SQLiteWindowFunction::valueFrameValues('last_value', array_column($rows, $labelColumn), $orderKeys, 'ROWS', $preceding, $following, $exclude);

        $windowRows = [];
        foreach ($rows as $index => $row) {
            $windowRows[] = [
                'key_name' => $row[$labelColumn] ?? null,
                'action' => $row['_upsert_action'] ?? null,
                'sequence' => $row['sequence'] ?? null,
                'frame' => $frames[$index]['frame'],
                'frame_count' => $frames[$index]['count'],
                'frame_priority_sum' => $frames[$index]['sum'],
                'frame_priority_concat' => $frames[$index]['groupConcat'],
                'first_key_name' => $firstNames[$index],
                'last_key_name' => $lastNames[$index],
            ];
        }

        return $windowRows;
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function rowValuePredicate(array $left, string $operator, array $right): ?bool
    {
        if (count($left) !== count($right) || count($left) < 2) {
            throw new \InvalidArgumentException('SQLite UPSERT row-value comparisons need matching tuple widths of at least two');
        }

        if ($operator === 'IS' || $operator === 'IS NOT') {
            $same = true;
            foreach ($left as $index => $value) {
                if ($value !== $right[$index]) {
                    $same = false;
                    break;
                }
            }

            return $operator === 'IS' ? $same : !$same;
        }

        $unknown = false;
        foreach ($left as $index => $value) {
            $other = $right[$index];
            if ($value === null || $other === null) {
                $unknown = true;
                continue;
            }
            if ($value == $other) {
                continue;
            }
            $cmp = $value <=> $other;

            return match ($operator) {
                '>' => $cmp > 0,
                '>=' => $cmp >= 0,
                '<' => $cmp < 0,
                '<=' => $cmp <= 0,
                '=', '=='=> false,
                '<>', '!=' => true,
                default => throw new \InvalidArgumentException('SQLite UPSERT row-value operator is unsupported'),
            };
        }

        if ($unknown) {
            return null;
        }

        return match ($operator) {
            '=' => true,
            '<>', '!=' => false,
            '>=', '<=' => true,
            '>', '<' => false,
            default => throw new \InvalidArgumentException('SQLite UPSERT row-value operator is unsupported'),
        };
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     */
    private static function findConflictIndex(array $rows, array $incoming, array $columns): ?int
    {
        foreach ($rows as $index => $row) {
            $matches = true;
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new \InvalidArgumentException("SQLite UPSERT conflict column {$column} is missing");
                }
                if ($row[$column] === null || $incoming[$column] === null || $row[$column] != $incoming[$column]) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param list<string> $columns
     * @return list<mixed>
     */
    private static function tuple(array $row, array $columns): array
    {
        return array_map(static fn (string $column): mixed => $row[$column], $columns);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function validateRows(array $rows, string $label): void
    {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException("SQLite UPSERT {$label} rows must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite UPSERT {$label} row must be an array");
            }
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function validateColumns(array $columns, string $label): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException("SQLite UPSERT {$label} columns cannot be empty");
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException("SQLite UPSERT {$label} columns must be non-empty strings");
            }
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function ensureColumns(array $row, array $columns, string $label): void
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite UPSERT {$label} column {$column} is missing");
            }
        }
    }

    private static function withAction(array $row, string $action, int $sequence): array
    {
        $row['_upsert_action'] = $action;
        $row['sequence'] = $sequence;

        return $row;
    }

    private static function numericValue(mixed $value, string $label): int|float|bool
    {
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return $value + 0;
        }

        throw new \InvalidArgumentException("SQLite UPSERT window {$label} value must be numeric");
    }
}
