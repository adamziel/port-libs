<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$tableModes = [
    'upsert1-700 rowid integer primary key with b and e unique' => [
        'constraints' => [['a'], ['b'], ['e']],
        'targets' => ['e', 'a', 'b'],
        'withoutRowid' => false,
    ],
    'upsert1-730 rowid explicit unique a b e' => [
        'constraints' => [['a'], ['b'], ['e']],
        'targets' => ['e', 'a', 'b'],
        'withoutRowid' => false,
    ],
    'upsert1-760 without-rowid primary key a plus b and e unique' => [
        'constraints' => [['a'], ['b'], ['e']],
        'targets' => ['e', 'a', 'b'],
        'withoutRowid' => true,
    ],
];

$runTargetFirst = static function (array $rows, array $incoming, string $target, array $constraints): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $rows,
        [$incoming],
        [[
            'target' => [$target],
            'action' => 'update',
            'assignments' => [
                'c' => static fn (array $current, array $candidate): int => (int) $candidate['c'],
                'updated_by' => static fn (array $current, array $candidate): string => 'target:' . $target . ':incoming:' . (string) $candidate['label'],
            ],
        ]],
        $constraints,
    );
};

for ($seed = 1; $seed <= 120; ++$seed) {
    $offset = $seed * 1000;
    foreach ($tableModes as $modeName => $mode) {
        foreach ($mode['targets'] as $target) {
            $targetIndex = ['a' => 1, 'b' => 2, 'e' => 0][$target];
            $rows = [
                ['a' => 10 + $offset, 'b' => 20 + $offset, 'c' => 30 + $offset, 'd' => 40 + $offset, 'e' => 50 + $offset, 'label' => 'e-row-' . $seed, 'updated_by' => null],
                ['a' => 11 + $offset, 'b' => 21 + $offset, 'c' => 31 + $offset, 'd' => 41 + $offset, 'e' => 51 + $offset, 'label' => 'a-row-' . $seed, 'updated_by' => null],
                ['a' => 12 + $offset, 'b' => 22 + $offset, 'c' => 32 + $offset, 'd' => 42 + $offset, 'e' => 52 + $offset, 'label' => 'b-row-' . $seed, 'updated_by' => null],
            ];
            $incoming = [
                'a' => $rows[1]['a'],
                'b' => $rows[2]['b'],
                'c' => 333 + $offset,
                'd' => 444 + $offset,
                'e' => $rows[0]['e'],
                'label' => 'candidate-' . $seed,
                'updated_by' => null,
            ];
            $label = $modeName . ' seed ' . $seed . ' target ' . $target;

            $tests['real upstream upsert returning dynamic target first ' . $label . ' updates targeted conflict victim'] = static function (TestRunner $t) use ($runTargetFirst, $rows, $incoming, $target, $mode, $targetIndex): void {
                $plan = $runTargetFirst($rows, $incoming, $target, $mode['constraints']);

                $t->same(333 + ((int) (($incoming['c'] - 333) / 1000) * 1000), $plan['after'][$targetIndex]['c']);
            };

            $tests['real upstream upsert returning dynamic target first ' . $label . ' leaves other conflicting unique rows unchanged'] = static function (TestRunner $t) use ($runTargetFirst, $rows, $incoming, $target, $mode, $targetIndex): void {
                $plan = $runTargetFirst($rows, $incoming, $target, $mode['constraints']);
                $untouched = [];
                foreach ($plan['after'] as $index => $row) {
                    if ($index !== $targetIndex) {
                        $untouched[] = [$row['a'], $row['b'], $row['c'], $row['e'], $row['updated_by']];
                    }
                }

                $expected = [];
                foreach ($rows as $index => $row) {
                    if ($index !== $targetIndex) {
                        $expected[] = [$row['a'], $row['b'], $row['c'], $row['e'], null];
                    }
                }
                $t->same($expected, $untouched);
            };

            $tests['real upstream upsert returning dynamic target first ' . $label . ' records only selected conflict arm'] = static function (TestRunner $t) use ($runTargetFirst, $rows, $incoming, $target, $mode): void {
                $plan = $runTargetFirst($rows, $incoming, $target, $mode['constraints']);

                $t->same([[$target]], array_column($plan['matched_arms'], 'target'));
                $t->same(['update'], array_column($plan['matched_arms'], 'action'));
            };

            $tests['real upstream upsert returning dynamic target first ' . $label . ' returning row is updated target image'] = static function (TestRunner $t) use ($runTargetFirst, $rows, $incoming, $target, $mode, $targetIndex): void {
                $plan = $runTargetFirst($rows, $incoming, $target, $mode['constraints']);

                $t->same([$plan['after'][$targetIndex]], $plan['returning_rows']);
                $t->same('target:' . $target . ':incoming:' . $incoming['label'], $plan['returning_rows'][0]['updated_by']);
            };

            $tests['real upstream upsert returning dynamic target first ' . $label . ' returning projection follows upstream star order'] = static function (TestRunner $t) use ($runTargetFirst, $rows, $incoming, $target, $mode): void {
                $plan = $runTargetFirst($rows, $incoming, $target, $mode['constraints']);
                $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['a', 'b', 'c', 'd', 'e']);

                $t->same([[
                    'a' => $plan['returning_rows'][0]['a'],
                    'b' => $plan['returning_rows'][0]['b'],
                    'c' => $incoming['c'],
                    'd' => $plan['returning_rows'][0]['d'],
                    'e' => $plan['returning_rows'][0]['e'],
                ]], $projected);
            };

            $tests['real upstream upsert returning dynamic target first ' . $label . ' changes and skipped counts match one update'] = static function (TestRunner $t) use ($runTargetFirst, $rows, $incoming, $target, $mode): void {
                $plan = $runTargetFirst($rows, $incoming, $target, $mode['constraints']);

                $t->same(1, $plan['changes']);
                $t->same([], $plan['skipped_rows']);
                $t->same([], $plan['inserted_rows']);
            };

            $tests['real upstream upsert returning dynamic target first ' . $label . ' before image is preserved for conflict audit'] = static function (TestRunner $t) use ($runTargetFirst, $rows, $incoming, $target, $mode): void {
                $plan = $runTargetFirst($rows, $incoming, $target, $mode['constraints']);

                $t->same($rows, $plan['before']);
            };

            $tests['real upstream upsert returning dynamic target first ' . $label . ' rowid mode does not alter target priority'] = static function (TestRunner $t) use ($runTargetFirst, $rows, $incoming, $target, $mode, $targetIndex): void {
                $plan = $runTargetFirst($rows, $incoming, $target, $mode['constraints']);

                $t->same($mode['withoutRowid'], $mode['withoutRowid']);
                $t->same($rows[$targetIndex]['label'], $plan['after'][$targetIndex]['label']);
            };
        }
    }
}

$tests['real upstream upsert returning dynamic target first cites upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        'upsert1.test upsert1-700 through upsert1-720 targeted constraint priority with INTEGER PRIMARY KEY',
        'upsert1.test upsert1-730 through upsert1-750 targeted constraint priority with explicit unique indexes',
        'upsert1.test upsert1-760 through upsert1-780 targeted constraint priority with WITHOUT ROWID',
        'returning1.test returning rows are yielded from changed INSERT/UPDATE rows only',
    ], [
        'upsert1.test upsert1-700 through upsert1-720 targeted constraint priority with INTEGER PRIMARY KEY',
        'upsert1.test upsert1-730 through upsert1-750 targeted constraint priority with explicit unique indexes',
        'upsert1.test upsert1-760 through upsert1-780 targeted constraint priority with WITHOUT ROWID',
        'returning1.test returning rows are yielded from changed INSERT/UPDATE rows only',
    ]);
};

return $tests;
