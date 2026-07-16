<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteReturningCorrelatedDeletePlan
{
    /**
     * @param list<array{a:int,b:int|null}> $rows
     * @param callable(array{a:int,b:int|null}):bool $where
     * @return array{before:list<array{a:int,b:int|null}>,after:list<array{a:int,b:int|null}>,returning_rows:list<array<string,int|float|null>>,changes:int,dependencies:list<string>}
     */
    public static function deleteWithRecomputedAggregateReturning(array $rows, callable $where, bool $correlateOuterRow = false): array
    {
        self::validateRows($rows, 'input');

        $before = self::sortRows($rows);
        $remaining = $before;
        $returningRows = [];

        foreach ($before as $row) {
            if (!$where($row)) {
                continue;
            }

            $remaining = array_values(array_filter(
                $remaining,
                static fn (array $candidate): bool => $candidate['a'] !== $row['a'],
            ));
            $stats = self::aggregateStats($remaining);

            $returningRows[] = $correlateOuterRow
                ? [
                    'a' => $row['a'],
                    'min_plus_outer' => $stats['min'] === null ? null : $stats['min'] + ($row['a'] * 100),
                    'max_plus_outer' => $stats['max'] === null ? null : $stats['max'] + ($row['a'] * 100),
                    'avg_plus_outer' => $stats['avg'] === null ? null : $stats['avg'] + ($row['a'] * 100),
                ]
                : [
                    'a' => $row['a'],
                    'min_remaining' => $stats['min'],
                    'max_remaining' => $stats['max'],
                    'avg_remaining' => $stats['avg'],
                ];
        }

        return [
            'before' => $before,
            'after' => self::sortRows($remaining),
            'returning_rows' => $returningRows,
            'changes' => count($returningRows),
            'dependencies' => [
                'sqlite-returning-correlated-delete-subqueries',
                'returning1.test-20.1',
                'returning1.test-20.2',
                'returning1.test-20.3',
            ],
        ];
    }

    /**
     * @param list<array{a:int,b:int|null}> $rows
     * @return list<array{a:int,b:int|null}>
     */
    private static function sortRows(array $rows): array
    {
        usort($rows, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

        return array_values($rows);
    }

    /**
     * @param list<array{a:int,b:int|null}> $rows
     * @return array{min:int|null,max:int|null,avg:float|null}
     */
    private static function aggregateStats(array $rows): array
    {
        if ($rows === []) {
            return ['min' => null, 'max' => null, 'avg' => null];
        }

        $values = array_column($rows, 'a');
        $avg = round(array_sum($values) / count($values), 2);

        return [
            'min' => min($values),
            'max' => max($values),
            'avg' => $avg,
        ];
    }

    /**
     * @param list<array{a:int,b:int|null}> $rows
     */
    private static function validateRows(array $rows, string $label): void
    {
        if ($rows === [] || !array_is_list($rows)) {
            throw new \InvalidArgumentException("SQLite RETURNING correlated delete {$label} rows must be a non-empty list");
        }

        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists('a', $row) || !array_key_exists('b', $row)) {
                throw new \InvalidArgumentException("SQLite RETURNING correlated delete {$label} row is malformed");
            }
            if (!is_int($row['a'])) {
                throw new \InvalidArgumentException("SQLite RETURNING correlated delete {$label} primary key must be an integer");
            }
            if ($row['b'] !== null && !is_int($row['b'])) {
                throw new \InvalidArgumentException("SQLite RETURNING correlated delete {$label} payload must be an integer or null");
            }
            if (isset($seen[$row['a']])) {
                throw new \InvalidArgumentException("SQLite RETURNING correlated delete {$label} primary key must be unique");
            }
            $seen[$row['a']] = true;
        }
    }
}
