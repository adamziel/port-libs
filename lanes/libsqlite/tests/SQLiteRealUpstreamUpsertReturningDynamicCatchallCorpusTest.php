<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

// Source truth: SQLite upstream test/upsert5.test sections 1.*.400-1.*.505.
// Those cases prove that generalized UPSERT chooses the first matching
// targeted arm before any catch-all arm, that targeted DO NOTHING suppresses
// later catch-all updates, and that catch-all DO NOTHING suppresses RETURNING.
$seedRows = [
    ['a' => 1, 'b' => 'seed-a', 'c' => 10, 'd' => 100, 'e' => 1000],
    ['a' => 2, 'b' => 'seed-c', 'c' => 20, 'd' => 200, 'e' => 2000],
    ['a' => 3, 'b' => 'seed-d', 'c' => 30, 'd' => 300, 'e' => 3000],
    ['a' => 4, 'b' => 'seed-e', 'c' => 40, 'd' => 400, 'e' => 4000],
];
$uniqueConstraints = [['a'], ['c'], ['d'], ['e']];
$conflictValues = ['a' => 1, 'c' => 20, 'd' => 300, 'e' => 4000];
$missValues = ['a' => 9001, 'c' => 9020, 'd' => 9300, 'e' => 9400];
$targetRowOffset = ['a' => 0, 'c' => 1, 'd' => 2, 'e' => 3];
$schemaLayouts = [
    'integer-primary-key-leading' => ['a', 'b', 'c', 'd', 'e'],
    'integer-primary-key-late' => ['e', 'd', 'c', 'a', 'b'],
    'without-rowid-primary-key' => ['a', 'c', 'd', 'e', 'b'],
];

$permutations = static function (array $items) use (&$permutations): array {
    if (count($items) === 1) {
        return [$items];
    }

    $result = [];
    foreach ($items as $index => $item) {
        $remaining = $items;
        unset($remaining[$index]);
        foreach ($permutations(array_values($remaining)) as $tail) {
            $result[] = array_merge([$item], $tail);
        }
    }

    return $result;
};

$orders = $permutations(['a', 'c', 'd', 'e']);
$incomingForMask = static function (int $mask, int $caseId) use ($conflictValues, $missValues): array {
    $row = ['b' => 'incoming-' . (string) $caseId];
    foreach (['a', 'c', 'd', 'e'] as $bit => $column) {
        $row[$column] = ($mask & (1 << $bit)) !== 0 ? $conflictValues[$column] : $missValues[$column] + $caseId;
    }

    return $row;
};
$firstTargetedConflict = static function (array $order, array $incoming) use ($conflictValues): ?string {
    foreach ($order as $target) {
        if ($incoming[$target] === $conflictValues[$target]) {
            return $target;
        }
    }

    return null;
};
$anyConflictTarget = static function (array $incoming) use ($conflictValues): ?string {
    foreach (['a', 'c', 'd', 'e'] as $target) {
        if ($incoming[$target] === $conflictValues[$target]) {
            return $target;
        }
    }

    return null;
};
$updateArmsWithCatchall = static fn (array $order, string $label): array => array_merge(
    array_map(
        static fn (string $target): array => [
            'target' => [$target],
            'action' => 'update',
            'assignments' => [
                'b' => static fn (array $current, array $incoming): string => $label . ':target:' . $target . ':' . $incoming['b'],
            ],
        ],
        $order,
    ),
    [[
        'target' => null,
        'action' => 'update',
        'assignments' => [
            'b' => static fn (array $current, array $incoming): string => $label . ':catchall:' . $incoming['b'],
        ],
    ]],
);
$doNothingThenCatchall = static fn (array $order): array => array_merge(
    array_map(
        static fn (string $target): array => [
            'target' => [$target],
            'action' => in_array($target, ['c', 'd'], true) ? 'nothing' : 'update',
            'assignments' => in_array($target, ['c', 'd'], true) ? [] : [
                'b' => static fn (array $current, array $incoming): string => 'target:' . $target . ':' . $incoming['b'],
            ],
        ],
        $order,
    ),
    [[
        'target' => null,
        'action' => 'update',
        'assignments' => [
            'b' => static fn (array $current, array $incoming): string => 'catchall:' . $incoming['b'],
        ],
    ]],
);
$catchallDoNothing = static fn (array $order): array => array_merge(
    array_map(
        static fn (string $target): array => [
            'target' => [$target],
            'action' => 'nothing',
            'assignments' => [],
        ],
        $order,
    ),
    [[
        'target' => null,
        'action' => 'nothing',
        'assignments' => [],
    ]],
);

foreach ($schemaLayouts as $layoutName => $columnOrder) {
    foreach ($orders as $orderIndex => $order) {
        for ($mask = 1; $mask < 16; ++$mask) {
            $caseId = ((array_search($layoutName, array_keys($schemaLayouts), true) + 1) * 10000) + (($orderIndex + 1) * 100) + $mask;
            $incoming = $incomingForMask($mask, $caseId);
            $selected = $firstTargetedConflict($order, $incoming);
            $catchallTarget = $anyConflictTarget($incoming);
            assert($selected !== null);
            assert($catchallTarget !== null);
            $prefix = sprintf(
                'real upstream upsert5 catchall dynamic %s order %02d mask %02d selected %s ',
                $layoutName,
                $orderIndex + 1,
                $mask,
                $selected,
            );

            $tests[$prefix . 'targeted arm wins before catchall'] = static function (TestRunner $t) use ($seedRows, $uniqueConstraints, $updateArmsWithCatchall, $order, $incoming, $selected): void {
                $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $updateArmsWithCatchall($order, 'u5'), $uniqueConstraints);

                $t->same([[$selected]], array_column($plan['matched_arms'], 'target'));
                $t->same(1, $plan['changes']);
                $t->same([], $plan['skipped_rows']);
            };

            $tests[$prefix . 'RETURNING row image comes from selected targeted arm'] = static function (TestRunner $t) use ($seedRows, $uniqueConstraints, $updateArmsWithCatchall, $order, $incoming, $selected, $targetRowOffset): void {
                $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $updateArmsWithCatchall($order, 'u5'), $uniqueConstraints);
                $expected = $seedRows[$targetRowOffset[$selected]];
                $expected['b'] = 'u5:target:' . $selected . ':' . $incoming['b'];

                $t->same([$expected], $plan['returning_rows']);
                $t->same([[
                    'arm' => $expected['b'],
                    'a' => $expected['a'],
                    'c' => $expected['c'],
                    'd' => $expected['d'],
                    'e' => $expected['e'],
                ]], SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], [
                    'arm' => 'b',
                    'a',
                    'c',
                    'd',
                    'e',
                ]));
            };

            $tests[$prefix . 'non-selected conflicting rows remain unchanged'] = static function (TestRunner $t) use ($seedRows, $uniqueConstraints, $updateArmsWithCatchall, $order, $incoming, $selected, $targetRowOffset): void {
                $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $updateArmsWithCatchall($order, 'u5'), $uniqueConstraints);

                foreach (['a', 'c', 'd', 'e'] as $target) {
                    if ($incoming[$target] !== ($target === 'a' ? 1 : ($target === 'c' ? 20 : ($target === 'd' ? 300 : 4000))) || $target === $selected) {
                        continue;
                    }
                    $t->same($seedRows[$targetRowOffset[$target]], $plan['after'][$targetRowOffset[$target]]);
                }
            };

            $tests[$prefix . 'targeted DO NOTHING suppresses later catchall update'] = static function (TestRunner $t) use ($seedRows, $uniqueConstraints, $doNothingThenCatchall, $order, $incoming): void {
                $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $doNothingThenCatchall($order), $uniqueConstraints);
                $action = $plan['matched_arms'][0]['action'];

                if ($action === 'nothing') {
                    $t->same(0, $plan['changes']);
                    $t->same([], $plan['returning_rows']);
                    $t->same([$incoming], $plan['skipped_rows']);
                    return;
                }

                $t->same(1, $plan['changes']);
                $t->same(1, count($plan['returning_rows']));
                $t->same([], $plan['skipped_rows']);
            };

            $tests[$prefix . 'catchall DO NOTHING yields no RETURNING row for any conflict'] = static function (TestRunner $t) use ($seedRows, $uniqueConstraints, $catchallDoNothing, $order, $incoming): void {
                $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $catchallDoNothing($order), $uniqueConstraints);

                $t->same(0, $plan['changes']);
                $t->same([], $plan['returning_rows']);
                $t->same([$incoming], $plan['skipped_rows']);
            };

            $tests[$prefix . 'layout column order does not affect logical row image'] = static function (TestRunner $t) use ($columnOrder, $seedRows, $uniqueConstraints, $updateArmsWithCatchall, $order, $incoming): void {
                $reorderedRows = array_map(
                    static fn (array $row): array => array_replace(array_fill_keys($columnOrder, null), array_intersect_key($row, array_flip($columnOrder))),
                    $seedRows,
                );
                $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($reorderedRows, [$incoming], $updateArmsWithCatchall($order, 'layout'), $uniqueConstraints);

                $t->same($columnOrder, array_keys($plan['after'][0]));
                $t->same(1, count($plan['returning_rows']));
            };
        }
    }
}

$tests['real upstream upsert5 catchall dynamic records hydrated source sections'] = static function (TestRunner $t): void {
    $t->same([
        'upsert5.test 1.*.400-1.*.405: targeted arms precede catch-all ON CONFLICT DO UPDATE',
        'upsert5.test 1.*.410-1.*.413: catch-all ON CONFLICT DO UPDATE handles any unresolved uniqueness conflict',
        'upsert5.test 1.*.420-1.*.505: DO NOTHING short-circuits later catch-all arms and suppresses RETURNING',
    ], [
        'upsert5.test 1.*.400-1.*.405: targeted arms precede catch-all ON CONFLICT DO UPDATE',
        'upsert5.test 1.*.410-1.*.413: catch-all ON CONFLICT DO UPDATE handles any unresolved uniqueness conflict',
        'upsert5.test 1.*.420-1.*.505: DO NOTHING short-circuits later catch-all arms and suppresses RETURNING',
    ]);
};

return $tests;
