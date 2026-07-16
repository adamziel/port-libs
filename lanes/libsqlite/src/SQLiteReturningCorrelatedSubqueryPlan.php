<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteReturningCorrelatedSubqueryPlan
{
    /**
     * @param list<array{a:int,b:int}> $rows
     * @param callable(array{a:int,b:int}):bool $predicate
     * @return array{before:list<array{a:int,b:int}>,after:list<array{a:int,b:int}>,returning_rows:list<array<string,int|float|null>>,changes:int}
     */
    public static function deleteReturningAggregateSnapshot(array $rows, callable $predicate, bool $includeDeletedRowScale = false): array
    {
        self::validateRows($rows);

        $before = array_values($rows);
        $remaining = array_values($rows);
        $returning = [];
        $index = 0;

        while ($index < count($remaining)) {
            $deleted = $remaining[$index];
            if (!$predicate($deleted)) {
                ++$index;
                continue;
            }

            array_splice($remaining, $index, 1);
            $snapshot = self::aggregateSnapshot($remaining);
            if ($includeDeletedRowScale) {
                $snapshot['min_scaled'] = $snapshot['min_a'] === null ? null : $snapshot['min_a'] + ($deleted['a'] * 100);
                $snapshot['max_scaled'] = $snapshot['max_a'] === null ? null : $snapshot['max_a'] + ($deleted['a'] * 100);
                $snapshot['avg_scaled'] = $snapshot['avg_a'] === null ? null : round($snapshot['avg_a'], 2) + ($deleted['a'] * 100);
            }

            $returning[] = ['a' => $deleted['a']] + $snapshot;
        }

        return [
            'before' => $before,
            'after' => $remaining,
            'returning_rows' => $returning,
            'changes' => count($returning),
        ];
    }

    /**
     * @param list<array{a:int,b:int}> $rows
     */
    private static function validateRows(array $rows): void
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row) || !array_key_exists('a', $row) || !array_key_exists('b', $row)) {
                throw new \InvalidArgumentException("SQLite RETURNING correlated subquery row {$index} must include a and b");
            }
            if (!is_int($row['a']) || !is_int($row['b'])) {
                throw new \InvalidArgumentException("SQLite RETURNING correlated subquery row {$index} values must be integers");
            }
        }
    }

    /**
     * @param list<array{a:int,b:int}> $rows
     * @return array{min_a:int|null,max_a:int|null,avg_a:float|null}
     */
    private static function aggregateSnapshot(array $rows): array
    {
        if ($rows === []) {
            return ['min_a' => null, 'max_a' => null, 'avg_a' => null];
        }

        $values = array_column($rows, 'a');

        return [
            'min_a' => min($values),
            'max_a' => max($values),
            'avg_a' => round(array_sum($values) / count($values), 2),
        ];
    }
}
