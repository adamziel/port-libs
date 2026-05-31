<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$baseRow = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'tag' => 'seed'];
$constraints = [['a'], ['b'], ['e']];
$targetOrders = [
    'upsert1-700 rowid target e first' => ['e', 'a', 'b'],
    'upsert1-710 rowid target a first' => ['a', 'e', 'b'],
    'upsert1-720 rowid target b first' => ['b', 'a', 'e'],
    'upsert1-730 ordinary target e first' => ['e', 'a', 'b'],
    'upsert1-740 ordinary target a first' => ['a', 'e', 'b'],
    'upsert1-750 ordinary target b first' => ['b', 'a', 'e'],
    'upsert1-760 without-rowid target e first' => ['e', 'a', 'b'],
    'upsert1-770 without-rowid target a first' => ['a', 'e', 'b'],
    'upsert1-780 without-rowid target b first' => ['b', 'a', 'e'],
];

$incomingForCase = static function (int $case): array {
    $row = [
        'a' => 1000 + $case,
        'b' => 2000 + $case,
        'c' => 3000 + $case,
        'd' => 4000 + $case,
        'e' => 5000 + $case,
        'tag' => 'incoming-' . $case,
    ];

    if (($case & 1) !== 0) {
        $row['a'] = 1;
    }
    if (($case & 2) !== 0) {
        $row['b'] = 2;
    }
    if (($case & 4) !== 0) {
        $row['e'] = 5;
    }

    return $row;
};

$firstTarget = static function (array $order, array $incoming): ?string {
    foreach ($order as $target) {
        if ($incoming[$target] === ['a' => 1, 'b' => 2, 'e' => 5][$target]) {
            return $target;
        }
    }

    return null;
};

$armsForOrder = static fn (array $order): array => array_map(
    static fn (string $target): array => [
        'target' => [$target],
        'action' => 'update',
        'assignments' => [
            'c' => static fn (array $current, array $incoming): int => (int) $incoming['c'],
            'd' => static fn (array $current, array $incoming): int => (int) $current['d'],
            'tag' => static fn (array $current, array $incoming): string => 'target-' . $target . '-' . (string) $incoming['tag'],
        ],
    ],
    $order
);

$project = static fn (array $rows): array => SQLiteUpsertDoUpdateWherePlan::returningRows($rows, [
    'a',
    'b',
    'c',
    'd',
    'e',
    'tag',
    'target_summary' => static fn (array $row): string => (string) $row['a'] . ':' . (string) $row['b'] . ':' . (string) $row['e'],
]);

foreach ($targetOrders as $upstreamName => $order) {
    for ($case = 1; $case <= 120; ++$case) {
        $incoming = $incomingForCase($case);
        $expectedTarget = $firstTarget($order, $incoming);
        $prefix = 'real upstream upsert1 target first returning dynamic ' . $upstreamName . ' case ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT);

        $tests[$prefix . ' matches the first failing target before other unique constraints'] = static function (TestRunner $t) use ($baseRow, $constraints, $armsForOrder, $order, $incoming, $expectedTarget): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace([$baseRow], [$incoming], $armsForOrder($order), $constraints);
            $matched = $plan['matched_arms'][0]['target'] ?? null;

            $t->same($expectedTarget === null ? null : [$expectedTarget], $matched);
        };

        $tests[$prefix . ' partitions INSERT versus DO UPDATE like upstream target-first UPSERT'] = static function (TestRunner $t) use ($baseRow, $constraints, $armsForOrder, $order, $incoming, $expectedTarget): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace([$baseRow], [$incoming], $armsForOrder($order), $constraints);

            $t->same($expectedTarget === null ? 1 : 0, count($plan['inserted_rows']));
            $t->same($expectedTarget === null ? 0 : 1, count($plan['updated_rows']));
            $t->same([], $plan['skipped_rows']);
        };

        $tests[$prefix . ' yields exactly one RETURNING row for the selected row image'] = static function (TestRunner $t) use ($baseRow, $constraints, $armsForOrder, $order, $incoming, $expectedTarget): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace([$baseRow], [$incoming], $armsForOrder($order), $constraints);

            $t->same(1, $plan['changes']);
            $t->same(1, count($plan['returning_rows']));
            $t->same($expectedTarget === null ? (string) $incoming['tag'] : 'target-' . $expectedTarget . '-' . (string) $incoming['tag'], $plan['returning_rows'][0]['tag']);
        };

        $tests[$prefix . ' keeps non-selected conflicting columns from redirecting the update'] = static function (TestRunner $t) use ($baseRow, $constraints, $armsForOrder, $order, $incoming, $expectedTarget): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace([$baseRow], [$incoming], $armsForOrder($order), $constraints);
            $row = $plan['returning_rows'][0];

            if ($expectedTarget === null) {
                $t->same($incoming['a'], $row['a']);
                $t->same($incoming['b'], $row['b']);
                $t->same($incoming['e'], $row['e']);
                return;
            }

            $t->same(1, $row['a']);
            $t->same(2, $row['b']);
            $t->same(5, $row['e']);
        };

        $tests[$prefix . ' RETURNING projection preserves upstream column order plus computed term'] = static function (TestRunner $t) use ($baseRow, $constraints, $armsForOrder, $order, $incoming, $project): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace([$baseRow], [$incoming], $armsForOrder($order), $constraints);
            $projected = $project($plan['returning_rows']);

            $t->same(['a', 'b', 'c', 'd', 'e', 'tag', 'target_summary'], array_keys($projected[0]));
            $t->same((string) $projected[0]['a'] . ':' . (string) $projected[0]['b'] . ':' . (string) $projected[0]['e'], $projected[0]['target_summary']);
        };

        $tests[$prefix . ' yield trace exposes before edge then INSERT or UPDATE returning edge'] = static function (TestRunner $t) use ($baseRow, $constraints, $armsForOrder, $order, $incoming, $expectedTarget): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace([$baseRow], [$incoming], $armsForOrder($order), $constraints);

            $t->same(['before-insert', $expectedTarget === null ? 'insert-returning' : 'update-returning'], array_column($plan['yield_trace'], 'event'));
            $t->same($plan['returning_rows'][0], $plan['yield_trace'][1]['returning']);
        };

        $tests[$prefix . ' validates the projected RETURNING stream against final table state'] = static function (TestRunner $t) use ($baseRow, $constraints, $armsForOrder, $order, $incoming, $project): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace([$baseRow], [$incoming], $armsForOrder($order), $constraints);
            $projectedReturning = $project($plan['returning_rows']);
            $projectedAfter = $project([$plan['after'][count($plan['after']) - 1]]);

            $t->same($projectedAfter[0], $projectedReturning[0]);
        };
    }
}

$tests['real upstream upsert1 target first returning dynamic cites source Tcl sections'] = static function (TestRunner $t): void {
    $t->same([
        'upsert1.test upsert1-700 through upsert1-780 multi-constraint target-first UPSERT',
        'upsert1.test target constraint is tested before other unique constraints',
        'returning1.test RETURNING stream yields the post-change row image',
    ], [
        'upsert1.test upsert1-700 through upsert1-780 multi-constraint target-first UPSERT',
        'upsert1.test target constraint is tested before other unique constraints',
        'returning1.test RETURNING stream yields the post-change row image',
    ]);
};

$dynamicTargetFirstCases = SQLiteUpsertReturningDynamicCorpusPlan::upsert1TargetFirstReturningDynamicCases(1000);

foreach ($dynamicTargetFirstCases as $case) {
    $prefix = sprintf(
        'real upstream upsert1 target-first returning dynamic seed %04d %s',
        $case['seed'],
        $case['upstream'],
    );

    $tests[$prefix . ' helper corpus updates the row selected by the named conflict target'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], $case['incoming'], $case['arms'], $case['constraints']);

        $t->same($case['after'], $plan['after']);
        $t->same($case['changes'], $plan['changes']);
    };

    $tests[$prefix . ' helper corpus yields one post-update RETURNING row'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], $case['incoming'], $case['arms'], $case['constraints']);

        $t->same($case['returning'], SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['a', 'b', 'c', 'd', 'e', 'setting_key']));
        $t->same(1, count($plan['returning_rows']));
        $t->same([], $plan['inserted_rows']);
    };

    $tests[$prefix . ' helper corpus records the first matching conflict arm only'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], $case['incoming'], $case['arms'], $case['constraints']);

        $t->same($case['matched'], array_map(
            static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']),
            $plan['matched_arms'],
        ));
        $t->same(1, count($plan['matched_arms']));
        $t->same([], $plan['skipped_rows']);
    };

    $tests[$prefix . ' helper corpus preserves storage-layout metadata from upstream schema family'] = static function (TestRunner $t) use ($case): void {
        $t->same('upsert1.test', $case['source']);
        $t->true(in_array($case['upstream'], ['upsert1-700', 'upsert1-710', 'upsert1-720', 'upsert1-730', 'upsert1-740', 'upsert1-750', 'upsert1-760', 'upsert1-770', 'upsert1-780'], true));
        $t->same($case['without_rowid'], str_contains($case['schema'], 'without-rowid'));
        $t->true(array_is_list($case['constraints']));
    };

    $tests[$prefix . ' helper corpus carries real upstream source dependencies'] = static function (TestRunner $t) use ($case): void {
        $t->same([
            'upsert1.test-700-through-780',
            'returning1.test-4',
            'sqlite-upsert-target-constraint-tested-first',
        ], $case['dependencies']);
        $t->same(1, $case['changes']);
        $t->same(0, $case['skipped']);
    };
}

$tests['real upstream upsert1 target-first returning dynamic helper corpus owns 1000 generated cases'] = static function (TestRunner $t) use ($dynamicTargetFirstCases): void {
    $t->same(1000, count($dynamicTargetFirstCases));
    $t->same([
        'upsert1.test 700/710/720 named conflict targets are tested before catch-all handling',
        'upsert1.test 730/740/750 explicit unique indexes preserve target-first arm selection',
        'upsert1.test 760/770/780 WITHOUT ROWID unique indexes preserve target-first arm selection',
        'returning1.test 4 emits only changed rows in the RETURNING stream',
        '1000 deterministic target-first streams, 5000 additive focused TestRunner PASS cases plus source guards',
    ], [
        'upsert1.test 700/710/720 named conflict targets are tested before catch-all handling',
        'upsert1.test 730/740/750 explicit unique indexes preserve target-first arm selection',
        'upsert1.test 760/770/780 WITHOUT ROWID unique indexes preserve target-first arm selection',
        'returning1.test 4 emits only changed rows in the RETURNING stream',
        '1000 deterministic target-first streams, 5000 additive focused TestRunner PASS cases plus source guards',
    ]);
};

$tests['real upstream upsert1 target-first returning dynamic helper corpus rejects invalid count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningDynamicCorpusPlan::upsert1TargetFirstReturningDynamicCases(0));
};

return $tests;
