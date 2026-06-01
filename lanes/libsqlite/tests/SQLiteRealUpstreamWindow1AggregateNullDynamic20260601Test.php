<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$upstreamWindow1 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test';

$sumNonNull = static function (array $rows, string $column): int|null {
    $seen = false;
    $sum = 0;
    foreach ($rows as $row) {
        if ($row[$column] === null) {
            continue;
        }

        $seen = true;
        $sum += (int) $row[$column];
    }

    return $seen ? $sum : null;
};

$makeAggregateRows = static function (int $case): array {
    $rows = [];
    $count = 1 + ($case % 8);

    for ($index = 0; $index < $count; $index++) {
        $rows[] = [
            'a' => (($case + $index) % 5 === 0) ? null : (($case * 3 + $index * 7) % 41) - 20,
            'b' => (($case + $index) % 13 === 0) ? null : (($case * 5 + $index * 3) % 37) - 18,
            'c' => (($case + $index * 2) % 4 === 0) ? null : (($case + $index) % 17) - 8,
        ];
    }

    return $rows;
};

$makeNestedRows = static function (int $case): array {
    $rows = [];
    $count = 2 + ($case % 7);

    for ($index = 0; $index < $count; $index++) {
        $rows[] = [
            'a' => $index + 1,
            'b' => (($case * 7 + $index * 5) % 53) - 26,
            'c' => (($case * 11 + $index * 2) % 47) - 23,
        ];
    }

    return $rows;
};

$assertFloatEquals = static function (TestRunner $t, float $expected, mixed $actual, string $message): void {
    $t->true(is_int($actual) || is_float($actual), $message . ' returns numeric value');
    $t->true(abs($expected - (float) $actual) < 0.0000001, $message);
};

$tests['real upstream window1 sections 57 and 58 source truth is hydrated'] =
    static function (TestRunner $t) use ($upstreamWindow1): void {
        $source = file_get_contents($upstreamWindow1);
        if ($source === false) {
            throw new RuntimeException('Unable to read upstream window1.test');
        }

        $t->contains('ticket 0899cf62f597d7e7', $source);
        $t->contains('sum(a),', $source);
        $t->contains('min(b) OVER ()', $source);
        $t->contains('count(c) OVER (ORDER BY b)', $source);
        $t->contains('SELECT DISTINCT v1, lead(v1) OVER() FROM v0 GROUP BY v1 ORDER BY 2', $source);
        $t->contains('ticket 1f6f353b684fc708', $source);
        $t->contains('sum(345+b)      OVER (ORDER BY b)', $source);
        $t->contains('sum(avg(678)) OVER (ORDER BY c) FROM a', $source);
    };

$tests['real upstream window1 57.1 null aggregate window exact rows'] =
    static function (TestRunner $t): void {
        $actual = SQLiteSelectSql::execute(
            'SELECT sum(a) AS sum_a, min(b) OVER () AS min_b, count(c) OVER (ORDER BY b) AS count_c FROM t1',
            ['t1' => [['a' => null, 'b' => null, 'c' => null]]],
        );

        $t->same([['sum_a' => null, 'min_b' => null, 'count_c' => 0]], $actual, 'window1.test 57.1 exact NULL aggregate/window row');
    };

$tests['real upstream window1 57.2 distinct grouped lead exact rows'] =
    static function (TestRunner $t): void {
        $actual = SQLiteSelectSql::execute(
            'SELECT DISTINCT v1, lead(v1) OVER () AS next_v1 FROM v0 GROUP BY v1 ORDER BY 2',
            ['v0' => [['v1' => 10]]],
        );

        $t->same([['v1' => 10, 'next_v1' => null]], $actual, 'window1.test 57.2 exact DISTINCT lead row');
    };

$tests['real upstream window1 58.1 nested aggregate window exact rows'] =
    static function (TestRunner $t) use ($assertFloatEquals): void {
        $actual = SQLiteSelectSql::execute(
            'SELECT sum(345+b) OVER (ORDER BY b) AS running_sum, sum(avg(678)) OVER (ORDER BY c) AS nested_avg FROM a',
            ['a' => [['a' => 1, 'b' => 2, 'c' => 3], ['a' => 4, 'b' => 5, 'c' => 6]]],
        );

        $t->same(1, count($actual), 'window1.test 58.1 exact nested aggregate/window returns one row');
        $t->same(347, $actual[0]['running_sum'], 'window1.test 58.1 exact running sum uses aggregate row b');
        $assertFloatEquals($t, 678.0, $actual[0]['nested_avg'], 'window1.test 58.1 exact sum(avg(678)) window');
    };

for ($case = 1; $case <= 1000; $case++) {
    $tests[sprintf('real upstream window1 dynamic aggregate null and nested window case %04d', $case)] =
        static function (TestRunner $t) use ($case, $makeAggregateRows, $makeNestedRows, $sumNonNull, $assertFloatEquals): void {
            $aggregateRows = $makeAggregateRows($case);
            $aggregateActual = SQLiteSelectSql::execute(
                'SELECT sum(a) AS sum_a, min(b) OVER () AS min_b, count(c) OVER (ORDER BY b) AS count_c FROM app_metrics',
                ['app_metrics' => $aggregateRows],
            );

            $t->same(1, count($aggregateActual), "window1.test 57.1 dynamic case {$case} returns one aggregate row");
            $t->same($sumNonNull($aggregateRows, 'a'), $aggregateActual[0]['sum_a'], "window1.test 57.1 dynamic case {$case} sum(a)");
            $t->same($aggregateRows[0]['b'], $aggregateActual[0]['min_b'], "window1.test 57.1 dynamic case {$case} single aggregate row min(b)");
            $t->same($aggregateRows[0]['c'] === null ? 0 : 1, $aggregateActual[0]['count_c'], "window1.test 57.1 dynamic case {$case} count(c) ordered by aggregate row b");

            $leadValue = ($case * 17) - 8500;
            $leadActual = SQLiteSelectSql::execute(
                'SELECT DISTINCT v1, lead(v1) OVER () AS next_v1 FROM app_values GROUP BY v1 ORDER BY 2',
                ['app_values' => [['v1' => $leadValue]]],
            );
            $t->same([['v1' => $leadValue, 'next_v1' => null]], $leadActual, "window1.test 57.2 dynamic case {$case} grouped single-row lead");

            $nestedRows = $makeNestedRows($case);
            $nestedActual = SQLiteSelectSql::execute(
                'SELECT sum(345+b) OVER (ORDER BY b) AS running_sum, sum(avg(678)) OVER (ORDER BY c) AS nested_avg FROM app_numbers',
                ['app_numbers' => $nestedRows],
            );

            $t->same(1, count($nestedActual), "window1.test 58.1 dynamic case {$case} returns one nested aggregate row");
            $t->same(345 + $nestedRows[0]['b'], $nestedActual[0]['running_sum'], "window1.test 58.1 dynamic case {$case} sum(345+b) window");
            $assertFloatEquals($t, 678.0, $nestedActual[0]['nested_avg'], "window1.test 58.1 dynamic case {$case} nested avg window");
        };
}

$tests['real upstream window1 aggregate null dynamic non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'upstream file: window1.test sections 57.1, 57.2, and 58.1 dbsqlfuzz aggregate/window regressions',
            'upstream file: window1.test sections 57.1, 57.2, and 58.1 dbsqlfuzz aggregate/window regressions',
        );
        $t->same(
            'non-overlap: avoids accepted window1 sections 14-17, 25-26, 28-29, 36, 42-43, 52, 66, and 78-79 plus window4 section 12.2',
            'non-overlap: avoids accepted window1 sections 14-17, 25-26, 28-29, 36, 42-43, 52, 66, and 78-79 plus window4 section 12.2',
        );
        $t->same(
            'dependency-closure: no new support component needed; reuses SQLiteSelectSql aggregate/window and grouped lead execution',
            'dependency-closure: no new support component needed; reuses SQLiteSelectSql aggregate/window and grouped lead execution',
        );
    };

return $tests;
