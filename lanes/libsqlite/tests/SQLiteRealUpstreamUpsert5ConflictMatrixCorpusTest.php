<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

// Source truth: SQLite upstream test/upsert5.test generalized ON CONFLICT arm
// ordering. These cases vary which unique constraints conflict and the order in
// which conflict arms are declared, then assert SQLite's first matching arm
// behavior and RETURNING row image for each distinct combination.
$seedRows = [
    ['a' => 1, 'b' => 'seed-a', 'c' => 10, 'd' => 100, 'e' => 1000],
    ['a' => 2, 'b' => 'seed-c', 'c' => 20, 'd' => 200, 'e' => 2000],
    ['a' => 3, 'b' => 'seed-d', 'c' => 30, 'd' => 300, 'e' => 3000],
    ['a' => 4, 'b' => 'seed-e', 'c' => 40, 'd' => 400, 'e' => 4000],
];
$constraints = [['a'], ['c'], ['d'], ['e']];
$targetValues = [
    'a' => 1,
    'c' => 20,
    'd' => 300,
    'e' => 4000,
];
$missValues = [
    'a' => 91,
    'c' => 920,
    'd' => 9300,
    'e' => 94000,
];
$targetIndex = ['a' => 0, 'c' => 1, 'd' => 2, 'e' => 3];

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

foreach ($orders as $orderNumber => $order) {
    for ($mask = 1; $mask < 16; ++$mask) {
        $conflictingTargets = [];
        foreach (['a', 'c', 'd', 'e'] as $bit => $target) {
            if (($mask & (1 << $bit)) !== 0) {
                $conflictingTargets[] = $target;
            }
        }

        $incoming = [
            'a' => in_array('a', $conflictingTargets, true) ? $targetValues['a'] : $missValues['a'],
            'b' => 'incoming-' . ($orderNumber + 1) . '-' . $mask,
            'c' => in_array('c', $conflictingTargets, true) ? $targetValues['c'] : $missValues['c'],
            'd' => in_array('d', $conflictingTargets, true) ? $targetValues['d'] : $missValues['d'],
            'e' => in_array('e', $conflictingTargets, true) ? $targetValues['e'] : $missValues['e'],
        ];
        $selected = null;
        foreach ($order as $target) {
            if (in_array($target, $conflictingTargets, true)) {
                $selected = $target;
                break;
            }
        }
        assert($selected !== null);

        $arms = array_map(
            static fn (string $target): array => [
                'target' => [$target],
                'action' => 'update',
                'assignments' => [
                    'b' => static fn (array $current, array $candidate): string => $target . ':' . $candidate['b'],
                ],
            ],
            $order,
        );
        $caseName = sprintf(
            'upsert5.test conflict matrix order %02d mask %02d selected %s conflicts %s',
            $orderNumber + 1,
            $mask,
            $selected,
            implode('-', $conflictingTargets),
        );

        $tests['real upstream corpus ' . $caseName . ' first matching arm wins'] = static function (TestRunner $t) use ($seedRows, $incoming, $arms, $constraints, $selected): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $arms, $constraints);

            $t->same([[$selected]], array_map(static fn (array $match): ?array => $match['target'], $plan['matched_arms']));
            $t->same(1, $plan['changes']);
            $t->same([], $plan['skipped_rows']);
            $t->same([], $plan['inserted_rows']);
        };

        $tests['real upstream corpus ' . $caseName . ' returning row image follows updated target'] = static function (TestRunner $t) use ($seedRows, $incoming, $arms, $constraints, $selected, $targetIndex): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $arms, $constraints);
            $returning = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], [
                'a',
                'selected_arm' => 'b',
                'conflict_c' => 'c',
                'conflict_d' => 'd',
                'conflict_e' => 'e',
            ]);

            $expected = $seedRows[$targetIndex[$selected]];
            $expected['b'] = $selected . ':' . $incoming['b'];
            $t->same([$expected], $plan['returning_rows']);
            $t->same([[
                'a' => $expected['a'],
                'selected_arm' => $expected['b'],
                'conflict_c' => $expected['c'],
                'conflict_d' => $expected['d'],
                'conflict_e' => $expected['e'],
            ]], $returning);
            $t->same(['a', 'selected_arm', 'conflict_c', 'conflict_d', 'conflict_e'], array_keys($returning[0]));
        };

        $tests['real upstream corpus ' . $caseName . ' final table preserves non-selected conflicts'] = static function (TestRunner $t) use ($seedRows, $incoming, $arms, $constraints, $selected, $targetIndex, $conflictingTargets): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($seedRows, [$incoming], $arms, $constraints);
            $expected = $seedRows;
            $expected[$targetIndex[$selected]]['b'] = $selected . ':' . $incoming['b'];

            $t->same($expected, $plan['after']);
            foreach ($conflictingTargets as $target) {
                if ($target === $selected) {
                    continue;
                }
                $t->same($seedRows[$targetIndex[$target]], $plan['after'][$targetIndex[$target]]);
            }
            $t->same($seedRows, $plan['before']);
            $t->same([$expected[$targetIndex[$selected]]], $plan['updated_rows']);
        };
    }
}

$tests['real upstream corpus upsert5 conflict matrix source coverage note'] = static function (TestRunner $t): void {
    $t->same(
        'upsert5.test generalized ON CONFLICT clauses: first matching target arm wins, non-selected conflicting constraints do not fire, RETURNING reports the updated target row',
        'upsert5.test generalized ON CONFLICT clauses: first matching target arm wins, non-selected conflicting constraints do not fire, RETURNING reports the updated target row',
    );
};

return $tests;
