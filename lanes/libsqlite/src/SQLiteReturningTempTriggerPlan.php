<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteReturningTempTriggerPlan
{
    /**
     * @param list<array{a:mixed,b:mixed}> $firstRows
     * @param list<array{e:int}> $thirdRows
     * @return array{
     *   first_returning:list<array{a:mixed,b:mixed,sep:string}>,
     *   update_returning:list<array{a:mixed,b:mixed,tag:string}>,
     *   delete_returning:list<array{a:mixed,b:mixed,tag:string}>,
     *   first_log:list<array{op:string,x:mixed,y:mixed}>,
     *   second_returning:list<array{d:mixed,c:mixed,tag:string}>,
     *   second_log:list<array{op:string,x:mixed,y:mixed}>,
     *   third_returning:list<array{event:string,e:int,f?:int}>,
     *   third_log:list<array{op:string,x:int,y:int}>,
     *   after_first:list<array{a:mixed,b:mixed}>,
     *   after_second:list<array{c:mixed,d:mixed}>,
     *   after_third:list<array{e:int,f:int}>,
     *   dependencies:list<string>
     * }
     */
    public static function execute(array $firstRows, array $thirdRows, mixed $updateKey, mixed $updatedValue, array $secondRow): array
    {
        $first = self::rows($firstRows, ['a', 'b'], 'first temp table rows');
        $thirdInput = self::rows($thirdRows, ['e'], 'third temp table rows');
        $second = self::row($secondRow, ['c', 'd'], 'second temp table row');

        $firstReturning = [];
        $firstLog = [];
        foreach ($first as $row) {
            $firstReturning[] = ['a' => $row['a'], 'b' => $row['b'], 'sep' => '|'];
            $firstLog[] = ['op' => 'I1', 'x' => $row['a'], 'y' => $row['b']];
        }

        $updateReturning = [];
        foreach ($first as $index => $row) {
            if ($row['a'] !== $updateKey) {
                continue;
            }
            $first[$index]['b'] = $updatedValue;
            $updateReturning[] = ['a' => $first[$index]['a'], 'b' => $first[$index]['b'], 'tag' => 'x'];
            $firstLog[] = ['op' => 'U1', 'x' => $first[$index]['a'], 'y' => $first[$index]['b']];
        }

        $deleteReturning = [];
        foreach ($first as $row) {
            if ($row['a'] === 'xray') {
                continue;
            }
            $deleteReturning[] = ['a' => $row['a'], 'b' => $row['b'], 'tag' => '@'];
            $firstLog[] = ['op' => 'D1', 'x' => $row['a'], 'y' => $row['b']];
        }
        $first = array_values(array_filter($first, static fn (array $row): bool => $row['a'] === 'xray'));

        $secondReturning = [['d' => $second['d'], 'c' => $second['c'], 'tag' => 'z']];
        $secondLog = [['op' => 'I2', 'x' => $second['c'], 'y' => $second['d']]];

        $third = [];
        $thirdReturning = [];
        foreach ($thirdInput as $row) {
            $inserted = ['e' => self::intValue($row['e'], 'third e')];
            $third[] = $inserted;
            $thirdReturning[] = ['event' => 'I', 'e' => $inserted['e']];
        }

        $thirdLog = [];
        foreach ($third as $index => $row) {
            $third[$index]['f'] = $row['e'] + 100;
            $thirdReturning[] = ['event' => 'U', 'e' => $third[$index]['e'], 'f' => $third[$index]['f']];
            $thirdLog[] = ['op' => 'U3', 'x' => $third[$index]['e'], 'y' => $third[$index]['f']];
        }

        $remainingThird = [];
        foreach ($third as $row) {
            if ($row['f'] > 100) {
                $thirdReturning[] = ['event' => 'D', 'e' => $row['e'], 'f' => $row['f']];
                $thirdLog[] = ['op' => 'D3', 'x' => $row['e'], 'y' => $row['f']];
                continue;
            }
            $remainingThird[] = $row;
        }

        return [
            'first_returning' => $firstReturning,
            'update_returning' => $updateReturning,
            'delete_returning' => $deleteReturning,
            'first_log' => $firstLog,
            'second_returning' => $secondReturning,
            'second_log' => $secondLog,
            'third_returning' => $thirdReturning,
            'third_log' => $thirdLog,
            'after_first' => $first,
            'after_second' => [$second],
            'after_third' => $remainingThird,
            'dependencies' => ['returning1.test-11.1', 'returning1.test-11.2', 'returning1.test-11.3', 'returning1.test-11.5', 'returning1.test-11.7'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function rows(array $rows, array $columns, string $label): array
    {
        if ($rows === [] || !array_is_list($rows)) {
            throw new \InvalidArgumentException("SQLite {$label} must be a non-empty list");
        }

        return array_map(static fn (array $row): array => self::row($row, $columns, $label), $rows);
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function row(array $row, array $columns, string $label): array
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite {$label} must include {$column}");
            }
        }

        return array_intersect_key($row, array_fill_keys($columns, true));
    }

    private static function intValue(mixed $value, string $label): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException("SQLite {$label} must be an integer");
        }

        return $value;
    }
}
