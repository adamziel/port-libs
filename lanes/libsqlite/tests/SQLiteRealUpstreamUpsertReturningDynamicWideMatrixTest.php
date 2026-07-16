<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$seedRows = [
    ['a' => 1, 'b' => 'row-a', 'c' => 10, 'd' => 100, 'e' => 1000],
    ['a' => 2, 'b' => 'row-c', 'c' => 20, 'd' => 200, 'e' => 2000],
    ['a' => 3, 'b' => 'row-d', 'c' => 30, 'd' => 300, 'e' => 3000],
    ['a' => 4, 'b' => 'row-e', 'c' => 40, 'd' => 400, 'e' => 4000],
];
$constraints = [['a'], ['c'], ['d'], ['e']];
$conflictingValues = ['a' => 1, 'c' => 20, 'd' => 300, 'e' => 4000];

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

$firstConflictingTarget = static function (array $order, array $incoming) use ($conflictingValues): ?string {
    foreach ($order as $target) {
        if ($incoming[$target] === $conflictingValues[$target]) {
            return $target;
        }
    }

    return null;
};

$armsForOrder = static fn (array $order): array => array_map(
    static fn (string $target): array => [
        'target' => [$target],
        'action' => 'update',
        'assignments' => ['b' => static fn (array $current, array $incoming): string => 'arm-' . $target . '-' . (string) $incoming['payload']],
    ],
    $order,
);

$mixedArmsForOrder = static function (array $order, int $caseIndex): array {
    $arms = [];
    foreach ($order as $position => $target) {
        $doNothing = (($caseIndex + $position) % 5) === 0;
        $arms[] = [
            'target' => [$target],
            'action' => $doNothing ? 'nothing' : 'update',
            'assignments' => $doNothing ? [] : ['b' => static fn (array $current, array $incoming): string => 'mixed-' . $target . '-' . (string) $incoming['payload']],
        ];
    }
    $arms[] = [
        'target' => null,
        'action' => ($caseIndex % 7) === 0 ? 'nothing' : 'update',
        'assignments' => ($caseIndex % 7) === 0 ? [] : ['b' => static fn (array $current, array $incoming): string => 'catchall-' . (string) $incoming['payload']],
    ];

    return $arms;
};

$incomingCases = [
    'upsert5-1.100 all four constraints conflict' => ['a' => 1, 'c' => 20, 'd' => 300, 'e' => 4000],
    'upsert5-1.101 c d e constraints conflict' => ['a' => 91, 'c' => 20, 'd' => 300, 'e' => 4000],
    'upsert5-1.102 d e constraints conflict' => ['a' => 91, 'c' => 92, 'd' => 300, 'e' => 4000],
    'upsert5-1.103 only e constraint conflicts' => ['a' => 91, 'c' => 92, 'd' => 93, 'e' => 4000],
    'upsert5-1.200 a c constraints conflict' => ['a' => 1, 'c' => 20, 'd' => 930, 'e' => 9400],
    'upsert5-1.211 a d constraints conflict' => ['a' => 1, 'c' => 920, 'd' => 300, 'e' => 9400],
    'upsert5-1.214 a e constraints conflict' => ['a' => 1, 'c' => 920, 'd' => 930, 'e' => 4000],
    'upsert5-3.1 no constraint conflicts inserts' => ['a' => 91, 'c' => 92, 'd' => 93, 'e' => 94],
];

$makeIncoming = static fn (array $template, int $payload): array => [
    'a' => $template['a'],
    'b' => 'incoming-' . (string) $payload,
    'c' => $template['c'],
    'd' => $template['d'],
    'e' => $template['e'],
    'payload' => $payload,
];

$project = static fn (array $rows): array => SQLiteUpsertDoUpdateWherePlan::returningRows($rows, [
    'a',
    'b',
    'c',
    'd',
    'e',
    'tag' => static fn (array $row): string => 'returning:' . (string) $row['b'],
]);

foreach ($orders as $orderIndex => $order) {
    foreach ($incomingCases as $caseName => $template) {
        $payload = ($orderIndex + 1) * 100 + count($tests);
        $incoming = $makeIncoming($template, $payload);
        $expectedTarget = $firstConflictingTarget($order, $incoming);
        $prefix = 'real upstream upsert5 returning wide matrix order ' . ($orderIndex + 1) . ' ' . $caseName;

        $tests[$prefix . ' chooses first matching conflict target'] = static function (TestRunner $t) use ($seedRows, $constraints, $armsForOrder, $order, $incoming, $expectedTarget): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $constraints);

            $actual = $plan['matched_arms'] === [] ? null : implode(',', $plan['matched_arms'][0]['target'] ?? []);
            $t->same($expectedTarget, $actual);
        };

        $tests[$prefix . ' partitions insert and update rowsets'] = static function (TestRunner $t) use ($seedRows, $constraints, $armsForOrder, $order, $incoming, $expectedTarget): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $constraints);

            $t->same($expectedTarget === null ? 1 : 0, count($plan['inserted_rows']));
            $t->same($expectedTarget === null ? 0 : 1, count($plan['updated_rows']));
            $t->same([], $plan['skipped_rows']);
        };

        $tests[$prefix . ' yields one RETURNING row for insert or update'] = static function (TestRunner $t) use ($seedRows, $constraints, $armsForOrder, $order, $incoming): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $constraints);

            $t->same(1, $plan['changes']);
            $t->same(1, count($plan['returning_rows']));
        };

        $tests[$prefix . ' writes selected arm marker into returned row'] = static function (TestRunner $t) use ($seedRows, $constraints, $armsForOrder, $order, $incoming, $expectedTarget): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $constraints);

            $expected = $expectedTarget === null ? (string) $incoming['b'] : 'arm-' . $expectedTarget . '-' . (string) $incoming['payload'];
            $t->same($expected, $plan['returning_rows'][0]['b']);
        };

        $tests[$prefix . ' projected RETURNING order is stable'] = static function (TestRunner $t) use ($seedRows, $constraints, $armsForOrder, $order, $incoming, $project): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $armsForOrder($order), $constraints);
            $projected = $project($plan['returning_rows']);

            $t->same(['a', 'b', 'c', 'd', 'e', 'tag'], array_keys($projected[0]));
            $t->same('returning:' . (string) $plan['returning_rows'][0]['b'], $projected[0]['tag']);
        };

        $tests[$prefix . ' mixed DO NOTHING arms suppress RETURNING only when selected'] = static function (TestRunner $t) use ($seedRows, $constraints, $mixedArmsForOrder, $order, $orderIndex, $incoming, $expectedTarget): void {
            $arms = $mixedArmsForOrder($order, $orderIndex);
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $arms, $constraints);
            $matched = $plan['matched_arms'][0]['action'] ?? 'insert';

            $t->same($matched === 'nothing' ? 0 : 1, count($plan['returning_rows']));
            $t->same($matched === 'nothing' ? 0 : 1, $plan['changes']);
            $t->same($expectedTarget === null && $matched !== 'nothing' ? 1 : 0, count($plan['inserted_rows']));
        };
    }
}

$sequenceCases = [
    'returning1-17 duplicate updates first row' => [17, 4711, 17, 17, 9001],
    'returning1-17 alternating duplicate updates' => [17, 4711, 17, 4711, 17],
    'returning1-17 adjacent duplicate updates' => [17, 17, 4711, 4711, 42],
    'returning1-17 late duplicate after clean inserts' => [17, 4711, 42, 99, 42],
    'returning1-17 all clean inserts' => [17, 4711, 42, 99, 100],
    'returning1-17 all duplicate one key' => [17, 17, 17, 17, 17],
    'returning1-17 three-way duplicate rotation' => [17, 4711, 42, 17, 4711, 42],
    'returning1-17 temp-table duplicate stream parity' => [4711, 17, 4711, 17, 99],
];

$runSequence = static function (array $values): array {
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

foreach ($sequenceCases as $name => $values) {
    $tests['real upstream returning1 wide matrix ' . $name . ' returning rowid stream'] = static function (TestRunner $t) use ($runSequence, $values): void {
        $plan = $runSequence($values);
        $seen = [];
        $expected = [];
        foreach ($values as $index => $value) {
            $seen[$value] ??= $index + 1;
            $expected[] = $seen[$value];
        }

        $t->same($expected, array_column($plan['returning_rows'], 'fooid'));
    };

    $tests['real upstream returning1 wide matrix ' . $name . ' change count matches yielded rows'] = static function (TestRunner $t) use ($runSequence, $values): void {
        $plan = $runSequence($values);

        $t->same(count($values), $plan['changes']);
        $t->same(count($values), count($plan['returning_rows']));
    };

    $tests['real upstream returning1 wide matrix ' . $name . ' final table keeps unique values'] = static function (TestRunner $t) use ($runSequence, $values): void {
        $plan = $runSequence($values);

        $t->same(array_values(array_unique($values)), array_column($plan['after'], 'fooval'));
    };

    $tests['real upstream returning1 wide matrix ' . $name . ' refcount records duplicate frequency'] = static function (TestRunner $t) use ($runSequence, $values): void {
        $plan = $runSequence($values);

        $t->same(array_values(array_count_values($values)), array_column($plan['after'], 'refcnt'));
    };

    $tests['real upstream returning1 wide matrix ' . $name . ' RETURNING projection can narrow rowid'] = static function (TestRunner $t) use ($runSequence, $values): void {
        $plan = $runSequence($values);
        $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['fooid']);

        $t->same(array_column($plan['returning_rows'], 'fooid'), array_column($projected, 'fooid'));
    };

    $tests['real upstream returning1 wide matrix ' . $name . ' insert update partitions match duplicate stream'] = static function (TestRunner $t) use ($runSequence, $values): void {
        $plan = $runSequence($values);

        $t->same(count(array_unique($values)), count($plan['inserted_rows']));
        $t->same(count($values) - count(array_unique($values)), count($plan['updated_rows']));
    };
}

$tests['real upstream upsert returning wide matrix cites source Tcl sections'] = static function (TestRunner $t): void {
    $t->same([
        'upsert5.test 1.* ON CONFLICT arm priority and catch-all ordering',
        'upsert5.test 3.* multi-row conflict stream behavior',
        'returning1.test 17.* INSERT ON CONFLICT DO UPDATE RETURNING rowid stream',
    ], [
        'upsert5.test 1.* ON CONFLICT arm priority and catch-all ordering',
        'upsert5.test 3.* multi-row conflict stream behavior',
        'returning1.test 17.* INSERT ON CONFLICT DO UPDATE RETURNING rowid stream',
    ]);
};

return $tests;
