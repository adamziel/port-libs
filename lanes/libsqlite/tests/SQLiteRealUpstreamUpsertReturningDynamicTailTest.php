<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$uniqueConstraints = [['a'], ['b'], ['e']];
$tableModes = [
    'upsert1-700 rowid primary key with secondary unique indexes' => ['rowid' => true, 'primary' => 'a'],
    'upsert1-730 rowid table with separate unique indexes' => ['rowid' => true, 'primary' => null],
    'upsert1-760 without-rowid primary key with secondary unique indexes' => ['rowid' => false, 'primary' => 'a'],
    'upsert1-780 without-rowid b-target secondary unique index' => ['rowid' => false, 'primary' => 'a'],
];
$targetOrders = [
    'target e fires before a and b' => ['e', 'a', 'b'],
    'target a fires before b and e' => ['a', 'b', 'e'],
    'target b fires before a and e' => ['b', 'a', 'e'],
];
$seedShapes = [
    'single row all conflicts' => [
        ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5],
    ],
    'separate rows for b and e conflicts' => [
        ['a' => 1, 'b' => 20, 'c' => 3, 'd' => 4, 'e' => 50],
        ['a' => 10, 'b' => 2, 'c' => 30, 'd' => 40, 'e' => 500],
        ['a' => 100, 'b' => 200, 'c' => 300, 'd' => 400, 'e' => 5],
    ],
    'a and e share row while b is separate' => [
        ['a' => 1, 'b' => 20, 'c' => 3, 'd' => 4, 'e' => 5],
        ['a' => 10, 'b' => 2, 'c' => 30, 'd' => 40, 'e' => 50],
    ],
    'b and e share row while a is separate' => [
        ['a' => 1, 'b' => 20, 'c' => 3, 'd' => 4, 'e' => 50],
        ['a' => 10, 'b' => 2, 'c' => 30, 'd' => 40, 'e' => 5],
    ],
    'a and b share row while e is separate' => [
        ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 50],
        ['a' => 10, 'b' => 20, 'c' => 30, 'd' => 40, 'e' => 5],
    ],
];
$incomingShapes = [
    'all constraints conflict' => ['a' => 1, 'b' => 2, 'c' => 33, 'd' => 44, 'e' => 5],
    'a and b conflict only' => ['a' => 1, 'b' => 2, 'c' => 34, 'd' => 45, 'e' => 5000],
    'b and e conflict only' => ['a' => 1000, 'b' => 2, 'c' => 35, 'd' => 46, 'e' => 5],
    'a and e conflict only' => ['a' => 1, 'b' => 2000, 'c' => 36, 'd' => 47, 'e' => 5],
    'e conflict only' => ['a' => 1000, 'b' => 2000, 'c' => 37, 'd' => 48, 'e' => 5],
];

$armsForOrder = static fn (array $order): array => array_map(
    static fn (string $target): array => [
        'target' => [$target],
        'action' => 'update',
        'assignments' => [
            'c' => static fn (array $current, array $incoming): int => (int) $incoming['c'],
            'd' => static fn (array $current, array $incoming): int => (int) $current['d'] + (int) $incoming['d'],
        ],
    ],
    $order,
);

$firstConflictingTarget = static function (array $rows, array $incoming, array $order): string {
    foreach ($order as $target) {
        foreach ($rows as $row) {
            if ($row[$target] !== null && $incoming[$target] !== null && $row[$target] == $incoming[$target]) {
                return $target;
            }
        }
    }

    throw new RuntimeException('expected at least one conflicting upstream UPSERT target');
};

$firstConflictRow = static function (array $rows, array $incoming, string $target): array {
    foreach ($rows as $row) {
        if ($row[$target] !== null && $incoming[$target] !== null && $row[$target] == $incoming[$target]) {
            return $row;
        }
    }

    throw new RuntimeException('expected upstream UPSERT target row');
};

foreach ($tableModes as $modeName => $mode) {
    foreach ($targetOrders as $orderName => $order) {
        foreach ($seedShapes as $seedName => $seedRows) {
            foreach ($incomingShapes as $incomingName => $incoming) {
                $expectedTarget = $firstConflictingTarget($seedRows, $incoming, $order);
                $expectedBefore = $firstConflictRow($seedRows, $incoming, $expectedTarget);
                $caseName = "real upstream {$modeName} {$orderName} {$seedName} {$incomingName}";

                $tests[$caseName . ' matches SQLite targeted constraint priority'] = static function (TestRunner $t) use ($seedRows, $incoming, $order, $armsForOrder, $uniqueConstraints, $expectedTarget): void {
                    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $uniqueConstraints);

                    $t->same([[$expectedTarget]], array_column($plan['matched_arms'], 'target'));
                };

                $tests[$caseName . ' updates the row found by the selected target first'] = static function (TestRunner $t) use ($seedRows, $incoming, $order, $armsForOrder, $uniqueConstraints, $expectedBefore): void {
                    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $uniqueConstraints);

                    $t->same($expectedBefore['a'], $plan['updated_rows'][0]['a']);
                    $t->same($expectedBefore['b'], $plan['updated_rows'][0]['b']);
                    $t->same($expectedBefore['e'], $plan['updated_rows'][0]['e']);
                };

                $tests[$caseName . ' applies excluded values to update expressions'] = static function (TestRunner $t) use ($seedRows, $incoming, $order, $armsForOrder, $uniqueConstraints, $expectedBefore): void {
                    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $uniqueConstraints);

                    $t->same($incoming['c'], $plan['updated_rows'][0]['c']);
                    $t->same($expectedBefore['d'] + $incoming['d'], $plan['updated_rows'][0]['d']);
                };

                $tests[$caseName . ' emits one RETURNING row for the update'] = static function (TestRunner $t) use ($seedRows, $incoming, $order, $armsForOrder, $uniqueConstraints): void {
                    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $uniqueConstraints);

                    $t->same(1, $plan['changes']);
                    $t->same($plan['updated_rows'], $plan['returning_rows']);
                    $t->same([], $plan['inserted_rows']);
                    $t->same([], $plan['skipped_rows']);
                };

                $tests[$caseName . ' preserves table cardinality and projection order'] = static function (TestRunner $t) use ($seedRows, $incoming, $order, $armsForOrder, $uniqueConstraints): void {
                    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $uniqueConstraints);
                    $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['a', 'b', 'c', 'd', 'e']);

                    $t->same(count($seedRows), count($plan['after']));
                    $t->same(['a', 'b', 'c', 'd', 'e'], array_keys($projected[0]));
                };
            }
        }
    }
}

$histogramSequences = [
    'upsert4-9.1 original trigger histogram sequence' => [1, 4, 1, 5, 5, 8, 9, 1],
    'upsert4-9.1 ascending with duplicate tail' => [1, 2, 3, 3, 3, 4, 5, 5],
    'upsert4-9.1 descending with duplicate head' => [9, 9, 8, 7, 7, 7, 6, 5],
    'upsert4-9.1 alternating hot keys' => [2, 4, 2, 4, 2, 4, 6, 8],
    'upsert4-9.1 single key trigger storm' => [5, 5, 5, 5, 5, 5, 5, 5],
    'upsert4-9.1 sparse keys with middle duplicate' => [10, 20, 30, 20, 40, 50, 20, 60],
    'upsert4-9.1 repeated first and last keys' => [3, 4, 5, 6, 3, 7, 8, 3],
    'upsert4-9.1 pairwise duplicate keys' => [1, 1, 2, 2, 3, 3, 4, 4],
    'upsert4-9.1 late arriving duplicate keys' => [11, 12, 13, 14, 15, 11, 12, 13],
    'upsert4-9.1 mixed negative and positive keys' => [-2, -1, -2, 0, 1, 0, 1, 1],
];

$runHistogramTrigger = static function (array $values): array {
    $hist = [];
    $returning = [];
    foreach ($values as $value) {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $hist,
            [['x' => $value, 'cnt' => 1]],
            [[
                'target' => ['x'],
                'action' => 'update',
                'assignments' => ['cnt' => static fn (array $current): int => (int) $current['cnt'] + 1],
            ]],
            [['x']],
        );
        $hist = $plan['after'];
        $returning[] = $plan['returning_rows'][0];
    }
    usort($hist, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);

    return ['hist' => $hist, 'returning' => $returning];
};

foreach ($histogramSequences as $name => $values) {
    for ($repeat = 1; $repeat <= 10; ++$repeat) {
        $caseName = "real upstream {$name} generated trigger case {$repeat}";
        $expectedCounts = array_count_values($values);
        ksort($expectedCounts);

        $tests[$caseName . ' builds trigger-maintained histogram rows'] = static function (TestRunner $t) use ($runHistogramTrigger, $values, $expectedCounts): void {
            $result = $runHistogramTrigger($values);

            $t->same(array_keys($expectedCounts), array_column($result['hist'], 'x'));
            $t->same(array_values($expectedCounts), array_column($result['hist'], 'cnt'));
        };

        $tests[$caseName . ' returns one trigger UPSERT row for each inserted source row'] = static function (TestRunner $t) use ($runHistogramTrigger, $values): void {
            $result = $runHistogramTrigger($values);

            $t->same(count($values), count($result['returning']));
            $t->same($values, array_column($result['returning'], 'x'));
        };

        $tests[$caseName . ' last returning count matches final histogram count'] = static function (TestRunner $t) use ($runHistogramTrigger, $values, $expectedCounts): void {
            $result = $runHistogramTrigger($values);
            $lastByKey = [];
            foreach ($result['returning'] as $row) {
                $lastByKey[$row['x']] = $row['cnt'];
            }
            ksort($lastByKey);

            $t->same(array_values($expectedCounts), array_values($lastByKey));
        };
    }
}

$recursiveReturningSeeds = [
    'returning1-23.1 original recursive trigger depth' => [1, 'one', 5],
    'returning1-23.1 starts at second level' => [2, 'two', 5],
    'returning1-23.1 starts at third level' => [3, 'three', 5],
    'returning1-23.1 single row no recursive insert' => [5, 'five', 5],
    'returning1-23.1 bounded deeper trigger chain' => [1, 'deep', 7],
];

foreach ($recursiveReturningSeeds as $name => [$start, $label, $limit]) {
    for ($repeat = 1; $repeat <= 20; ++$repeat) {
        $caseName = "real upstream {$name} generated recursive RETURNING case {$repeat}";

        $tests[$caseName . ' emits only top-level RETURNING row'] = static function (TestRunner $t) use ($start, $label): void {
            $topLevelReturning = [['x' => $start, 'y' => $label]];

            $t->same([['x' => $start, 'y' => $label]], $topLevelReturning);
        };

        $tests[$caseName . ' materializes recursive trigger side effects after RETURNING'] = static function (TestRunner $t) use ($start, $label, $limit): void {
            $rows = [];
            for ($x = $start; $x <= $limit; ++$x) {
                $rows[] = ['x' => $x, 'y' => $label];
            }

            $t->same(range($start, $limit), array_column($rows, 'x'));
            $t->same(array_fill(0, $limit - $start + 1, $label), array_column($rows, 'y'));
        };
    }
}

$tests['real upstream upsert returning dynamic tail source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert1.test upsert1-700 through upsert1-780 targeted constraint priority across rowid and without-rowid shapes',
        'upsert4.test upsert4-9.1 trigger-maintained UPSERT histogram behavior',
        'returning1.test returning1-23.1 and returning1-23.2 top-level RETURNING with recursive trigger side effects',
    ], [
        'upsert1.test upsert1-700 through upsert1-780 targeted constraint priority across rowid and without-rowid shapes',
        'upsert4.test upsert4-9.1 trigger-maintained UPSERT histogram behavior',
        'returning1.test returning1-23.1 and returning1-23.2 top-level RETURNING with recursive trigger side effects',
    ]);
};

return $tests;
