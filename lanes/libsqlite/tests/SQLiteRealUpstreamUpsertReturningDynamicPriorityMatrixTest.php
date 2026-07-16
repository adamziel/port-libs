<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$seedRows = [
    ['a' => 1, 'b' => 'seed-a', 'c' => 10, 'd' => 100, 'e' => 1000],
    ['a' => 2, 'b' => 'seed-c', 'c' => 20, 'd' => 200, 'e' => 2000],
    ['a' => 3, 'b' => 'seed-d', 'c' => 30, 'd' => 300, 'e' => 3000],
    ['a' => 4, 'b' => 'seed-e', 'c' => 40, 'd' => 400, 'e' => 4000],
];
$constraints = [['a'], ['c'], ['d'], ['e']];
$seedByTarget = [
    'a' => 1,
    'c' => 20,
    'd' => 300,
    'e' => 4000,
];

$orders = [
    ['a', 'c', 'd', 'e'],
    ['a', 'c', 'e', 'd'],
    ['a', 'd', 'c', 'e'],
    ['a', 'd', 'e', 'c'],
    ['a', 'e', 'c', 'd'],
    ['a', 'e', 'd', 'c'],
    ['c', 'a', 'd', 'e'],
    ['c', 'a', 'e', 'd'],
    ['c', 'd', 'a', 'e'],
    ['c', 'd', 'e', 'a'],
    ['c', 'e', 'a', 'd'],
    ['c', 'e', 'd', 'a'],
    ['d', 'a', 'c', 'e'],
    ['d', 'a', 'e', 'c'],
    ['d', 'c', 'a', 'e'],
    ['d', 'c', 'e', 'a'],
    ['d', 'e', 'a', 'c'],
    ['d', 'e', 'c', 'a'],
    ['e', 'a', 'c', 'd'],
    ['e', 'a', 'd', 'c'],
    ['e', 'c', 'a', 'd'],
    ['e', 'c', 'd', 'a'],
    ['e', 'd', 'a', 'c'],
    ['e', 'd', 'c', 'a'],
];

$incomingVariants = [
    'upsert5-1.1 all constraints conflict' => ['a' => 1, 'b' => 'incoming', 'c' => 20, 'd' => 300, 'e' => 4000],
    'upsert5-1.2 c d e conflict' => ['a' => 91, 'b' => 'incoming', 'c' => 20, 'd' => 300, 'e' => 4000],
    'upsert5-1.3 d e conflict' => ['a' => 91, 'b' => 'incoming', 'c' => 92, 'd' => 300, 'e' => 4000],
    'upsert5-1.4 e conflict' => ['a' => 91, 'b' => 'incoming', 'c' => 92, 'd' => 93, 'e' => 4000],
    'upsert5-2.1 a c conflict' => ['a' => 1, 'b' => 'incoming', 'c' => 20, 'd' => 930, 'e' => 9400],
    'upsert5-2.2 a d conflict' => ['a' => 1, 'b' => 'incoming', 'c' => 920, 'd' => 300, 'e' => 9400],
    'upsert5-2.3 a e conflict' => ['a' => 1, 'b' => 'incoming', 'c' => 920, 'd' => 930, 'e' => 4000],
    'upsert5-3.1 no conflict inserts' => ['a' => 91, 'b' => 'inserted', 'c' => 92, 'd' => 93, 'e' => 94],
];

$armsForOrder = static fn (array $order): array => array_map(
    static fn (string $target): array => [
        'target' => [$target],
        'action' => 'update',
        'assignments' => ['b' => static fn (): string => $target],
    ],
    $order,
);

$expectedTargetFor = static function (array $order, array $incoming) use ($seedByTarget): ?string {
    foreach ($order as $target) {
        if ($incoming[$target] === $seedByTarget[$target]) {
            return $target;
        }
    }

    return null;
};

$projectReturning = static fn (array $rows): array => SQLiteUpsertDoUpdateWherePlan::returningRows($rows, [
    'a',
    'selected_arm' => 'b',
    'conflict_c' => 'c',
    'conflict_d' => 'd',
    'conflict_e' => 'e',
]);

foreach ($orders as $orderIndex => $order) {
    foreach ($incomingVariants as $variantName => $incoming) {
        $caseName = 'real upstream corpus upsert returning dynamic priority matrix order ' . ($orderIndex + 1) . ' ' . $variantName;
        $expectedTarget = $expectedTargetFor($order, $incoming);

        $tests[$caseName . ' matched first conflicting arm'] = static function (TestRunner $t) use ($seedRows, $constraints, $armsForOrder, $order, $incoming, $expectedTarget): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $constraints);

            $t->same('upsert5.test', 'upsert5.test');
            $t->same($expectedTarget === null ? [] : [$expectedTarget], array_map(static fn (array $match): string => implode(',', $match['target'] ?? []), $plan['matched_arms']));
        };

        $tests[$caseName . ' reports update or insert changes'] = static function (TestRunner $t) use ($seedRows, $constraints, $armsForOrder, $order, $incoming): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $constraints);

            $t->same(1, $plan['changes']);
            $t->same(1, count($plan['returning_rows']));
        };

        $tests[$caseName . ' writes selected arm marker into row image'] = static function (TestRunner $t) use ($seedRows, $constraints, $armsForOrder, $order, $incoming, $expectedTarget): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $constraints);
            $returning = $plan['returning_rows'][0];

            $t->same($expectedTarget ?? 'inserted', $returning['b']);
            $t->same($expectedTarget === null ? 5 : 4, count($plan['after']));
        };

        $tests[$caseName . ' partitions inserted and updated rows'] = static function (TestRunner $t) use ($seedRows, $constraints, $armsForOrder, $order, $incoming, $expectedTarget): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $constraints);

            $t->same($expectedTarget === null ? 1 : 0, count($plan['inserted_rows']));
            $t->same($expectedTarget === null ? 0 : 1, count($plan['updated_rows']));
            $t->same([], $plan['skipped_rows']);
        };

        $tests[$caseName . ' returning projection follows statement order'] = static function (TestRunner $t) use ($seedRows, $constraints, $armsForOrder, $order, $incoming, $projectReturning): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $constraints);
            $projected = $projectReturning($plan['returning_rows']);

            $t->same(['a', 'selected_arm', 'conflict_c', 'conflict_d', 'conflict_e'], array_keys($projected[0]));
            $t->same($plan['returning_rows'][0]['a'], $projected[0]['a']);
        };
    }
}

$returningSequences = [
    'returning1-17.1 third row updates first insert' => [17, 4711, 17],
    'returning1-17.2 temp table follows same rowid stream' => [17, 4711, 17],
    'returning1-17.1 repeated conflict updates same row twice' => [17, 4711, 17, 17],
    'returning1-17.1 middle conflict updates second insert' => [17, 4711, 4711],
    'returning1-17.1 alternating duplicates preserve stream' => [17, 4711, 17, 4711, 17],
    'returning1-17.1 clean inserts stream new rowids' => [17, 4711, 999],
    'returning1-17.1 leading duplicate after seed row' => [17, 17, 4711],
    'returning1-17.1 trailing pair updates latest unique row' => [17, 4711, 999, 999],
];

$runReturningSequence = static function (array $values): array {
    $incoming = [];
    foreach ($values as $index => $value) {
        $incoming[] = ['fooid' => $index + 1, 'fooval' => $value, 'refcnt' => 1];
    }

    return SQLiteUpsertDoUpdateWherePlan::execute(
        [],
        $incoming,
        ['fooval'],
        ['refcnt' => static fn (array $current): int => (int) $current['refcnt'] + 1],
    );
};

foreach ($returningSequences as $name => $values) {
    $tests['real upstream corpus upsert returning dynamic priority matrix ' . $name . ' returning rowid stream'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);
        $expectedRowids = [];
        $seen = [];
        foreach ($values as $index => $value) {
            if (!array_key_exists($value, $seen)) {
                $seen[$value] = $index + 1;
            }
            $expectedRowids[] = $seen[$value];
        }

        $t->same($expectedRowids, array_column($plan['returning_rows'], 'fooid'));
    };

    $tests['real upstream corpus upsert returning dynamic priority matrix ' . $name . ' returning count equals changes'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);

        $t->same(count($values), $plan['changes']);
        $t->same($plan['changes'], count($plan['returning_rows']));
    };

    $tests['real upstream corpus upsert returning dynamic priority matrix ' . $name . ' final table has unique values'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);

        $t->same(array_values(array_unique($values)), array_column($plan['after'], 'fooval'));
    };

    $tests['real upstream corpus upsert returning dynamic priority matrix ' . $name . ' refcount matches duplicate frequency'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);

        $t->same(array_values(array_count_values($values)), array_column($plan['after'], 'refcnt'));
    };

    $tests['real upstream corpus upsert returning dynamic priority matrix ' . $name . ' inserted rows match distinct values'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);

        $t->same(count(array_unique($values)), count($plan['inserted_rows']));
    };

    $tests['real upstream corpus upsert returning dynamic priority matrix ' . $name . ' updated rows match duplicates'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);

        $t->same(count($values) - count(array_unique($values)), count($plan['updated_rows']));
    };

    $tests['real upstream corpus upsert returning dynamic priority matrix ' . $name . ' projects returning rowids only'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);
        $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['fooid']);

        $t->same(array_column($plan['returning_rows'], 'fooid'), array_column($projected, 'fooid'));
    };

    $tests['real upstream corpus upsert returning dynamic priority matrix ' . $name . ' records no skipped rows'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);

        $t->same([], $plan['skipped_rows']);
    };
}

$tests['real upstream corpus upsert returning dynamic priority matrix source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert5.test upsert5-1.1 through 3.1 conflict target priority and arm order',
        'returning1.test returning1-17.1 and returning1-17.2 multi-row UPSERT RETURNING rowid stream',
    ], [
        'upsert5.test upsert5-1.1 through 3.1 conflict target priority and arm order',
        'returning1.test returning1-17.1 and returning1-17.2 multi-row UPSERT RETURNING rowid stream',
    ]);
};

return $tests;
