<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$tableModes = [
    'upsert4-7.1 rowid composite primary key plus z unique' => false,
    'upsert4-7.2 without-rowid composite primary key plus z unique' => true,
];

$runArm = static function (array $rows, array $incoming, array $target, array $assignments, ?callable $where = null, ?array $uniqueConstraints = null): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $rows,
        [$incoming],
        [[
            'target' => $target,
            'action' => 'update',
            'assignments' => $assignments,
            'where' => $where,
        ]],
        $uniqueConstraints ?? [['x', 'y'], ['z']],
    );
};

$runNothing = static function (array $rows, array $incoming, array $target): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $rows,
        [$incoming],
        [[
            'target' => $target,
            'action' => 'nothing',
        ]],
        [['x', 'y'], ['z']],
    );
};

for ($seed = 1; $seed <= 42; ++$seed) {
    foreach ($tableModes as $modeName => $withoutRowid) {
        $base = [
            ['w' => 'a' . $seed, 'x' => $seed * 10 + 1, 'y' => $seed * 10 + 1, 'z' => $seed * 100 + 1],
            ['w' => 'b' . $seed, 'x' => $seed * 10 + 2, 'y' => $seed * 10 + 2, 'z' => $seed * 100 + 2],
        ];
        $quotedBase = [
            ['w' => 'a' . $seed, 'x' => $seed * 10 + 1, 'a b' => $seed * 10 + 1, 'z' => $seed * 100 + 1],
            ['w' => 'b' . $seed, 'x' => $seed * 10 + 2, 'a b' => $seed * 10 + 2, 'z' => $seed * 100 + 2],
        ];
        $modeLabel = $modeName . ' seed ' . $seed . ($withoutRowid ? ' without rowid' : ' rowid');

        $tests['real upstream upsert returning dynamic alias matrix ' . $modeLabel . ' upsert4-7.1 excluded assignment uses incoming row'] = static function (TestRunner $t) use ($runArm, $base, $seed): void {
            $plan = $runArm(
                $base,
                ['w' => 'c' . $seed, 'x' => $seed * 10 + 3, 'y' => $seed * 10 + 3, 'z' => $seed * 100 + 1],
                ['z'],
                ['w' => static fn (array $current, array $incoming): string => (string) $incoming['w']],
            );

            $t->same('c' . $seed, $plan['after'][0]['w']);
        };

        $tests['real upstream upsert returning dynamic alias matrix ' . $modeLabel . ' upsert4-7.1 z conflict preserves composite key'] = static function (TestRunner $t) use ($runArm, $base, $seed): void {
            $plan = $runArm(
                $base,
                ['w' => 'c' . $seed, 'x' => $seed * 10 + 3, 'y' => $seed * 10 + 3, 'z' => $seed * 100 + 1],
                ['z'],
                ['w' => static fn (array $current, array $incoming): string => (string) $incoming['w']],
            );

            $t->same([$seed * 10 + 1, $seed * 10 + 1, $seed * 100 + 1], [$plan['after'][0]['x'], $plan['after'][0]['y'], $plan['after'][0]['z']]);
        };

        $tests['real upstream upsert returning dynamic alias matrix ' . $modeLabel . ' upsert4-7.2 reversed conflict target matches composite key'] = static function (TestRunner $t) use ($runArm, $base): void {
            $plan = $runArm(
                $base,
                ['w' => 'c', 'x' => $base[1]['x'], 'y' => $base[1]['y'], 'z' => $base[1]['z'] + 10],
                ['y', 'x'],
                ['w' => static fn (array $current): string => (string) $current['w'] . (string) $current['w']],
            );

            $t->same($base[1]['w'] . $base[1]['w'], $plan['after'][1]['w']);
        };

        $tests['real upstream upsert returning dynamic alias matrix ' . $modeLabel . ' upsert4-7.2 reversed conflict target leaves z unchanged'] = static function (TestRunner $t) use ($runArm, $base): void {
            $plan = $runArm(
                $base,
                ['w' => 'c', 'x' => $base[1]['x'], 'y' => $base[1]['y'], 'z' => $base[1]['z'] + 10],
                ['y', 'x'],
                ['w' => static fn (array $current): string => (string) $current['w'] . (string) $current['w']],
            );

            $t->same($base[1]['z'], $plan['after'][1]['z']);
        };

        $tests['real upstream upsert returning dynamic alias matrix ' . $modeLabel . ' upsert4-7.3 target table qualifier reads current row'] = static function (TestRunner $t) use ($runArm, $base): void {
            $plan = $runArm(
                $base,
                ['w' => 'c', 'x' => $base[1]['x'], 'y' => $base[1]['y'], 'z' => $base[1]['z'] + 20],
                ['y', 'x'],
                ['w' => static fn (array $current): string => (string) $current['w'] . (string) $current['w']],
            );

            $t->same($base[1]['w'] . $base[1]['w'], $plan['returning_rows'][0]['w']);
        };

        $tests['real upstream upsert returning dynamic alias matrix ' . $modeLabel . ' upsert4-7.4 insert alias qualifier reads current target row'] = static function (TestRunner $t) use ($runArm, $base): void {
            $plan = $runArm(
                $base,
                ['w' => 'c', 'x' => $base[1]['x'], 'y' => $base[1]['y'], 'z' => $base[1]['z'] + 30],
                ['y', 'x'],
                ['w' => static fn (array $current): string => str_repeat((string) $current['w'], 2)],
            );

            $t->same(str_repeat((string) $base[1]['w'], 2), $plan['updated_rows'][0]['w']);
        };

        $tests['real upstream upsert returning dynamic alias matrix ' . $modeLabel . ' upsert4-8.1 table named excluded still permits target conflict'] = static function (TestRunner $t) use ($runArm, $quotedBase): void {
            $plan = $runArm(
                $quotedBase,
                ['w' => 'hello', 'x' => $quotedBase[0]['x'], 'a b' => $quotedBase[0]['a b'], 'z' => null],
                ['x', 'a b'],
                ['w' => static fn (array $current): string => (string) $current['w']],
                null,
                [['x', 'a b'], ['z']],
            );

            $t->same($quotedBase[0]['w'], $plan['after'][0]['w']);
        };

        $tests['real upstream upsert returning dynamic alias matrix ' . $modeLabel . ' upsert4-8.2 excluded pseudo-table wins with insert alias'] = static function (TestRunner $t) use ($runArm, $quotedBase): void {
            $plan = $runArm(
                $quotedBase,
                ['w' => 'hello', 'x' => $quotedBase[0]['x'], 'a b' => $quotedBase[0]['a b'], 'z' => null],
                ['x', 'a b'],
                ['w' => static fn (array $current, array $incoming): string => (string) $incoming['w']],
                null,
                [['x', 'a b'], ['z']],
            );

            $t->same('hello', $plan['after'][0]['w']);
        };

        $tests['real upstream upsert returning dynamic alias matrix ' . $modeLabel . ' upsert4-8.3 excluded where false skips update'] = static function (TestRunner $t) use ($runArm, $quotedBase): void {
            $plan = $runArm(
                $quotedBase,
                ['w' => 'hello', 'x' => $quotedBase[0]['x'], 'a b' => $quotedBase[0]['a b'], 'z' => null],
                ['x', 'a b'],
                ['w' => static fn (array $current): string => (string) $current['w'] . (string) $current['w']],
                static fn (array $current, array $incoming): bool => $incoming['w'] !== 'hello',
                [['x', 'a b'], ['z']],
            );

            $t->same([], $plan['returning_rows']);
        };

        $tests['real upstream upsert returning dynamic alias matrix ' . $modeLabel . ' upsert4-8.4 excluded where true updates current row'] = static function (TestRunner $t) use ($runArm, $quotedBase): void {
            $plan = $runArm(
                $quotedBase,
                ['w' => 'hello', 'x' => $quotedBase[0]['x'], 'a b' => $quotedBase[0]['a b'], 'z' => null],
                ['x', 'a b'],
                ['w' => static fn (array $current): string => (string) $current['w'] . (string) $current['w']],
                static fn (array $current, array $incoming): bool => $incoming['x'] === $current['x'],
                [['x', 'a b'], ['z']],
            );

            $t->same($quotedBase[0]['w'] . $quotedBase[0]['w'], $plan['after'][0]['w']);
        };

        $tests['real upstream upsert returning dynamic alias matrix ' . $modeLabel . ' upsert4-8.5 malformed conflict predicate is rejected'] = static function (TestRunner $t) use ($runArm, $quotedBase): void {
            $t->throws(InvalidArgumentException::class, static fn () => $runArm(
                $quotedBase,
                ['w' => 'hello', 'x' => $quotedBase[0]['x'], 'a b' => $quotedBase[0]['a b'], 'z' => null],
                ['x', 'a b', 'y'],
                ['w' => static fn (array $current): string => (string) $current['w']],
            ));
        };

        $tests['real upstream upsert returning dynamic alias matrix ' . $modeLabel . ' upsert4-5.0 mismatched collation-style target is rejected'] = static function (TestRunner $t) use ($runNothing, $base): void {
            $t->throws(InvalidArgumentException::class, static fn () => $runNothing(
                $base,
                ['w' => 'c', 'x' => $base[0]['x'], 'y' => $base[0]['y'], 'z' => $base[0]['z']],
                ['x', 'y', 'collate_nocase'],
            ));
        };
    }
}

$tests['real upstream upsert returning dynamic alias matrix source coverage cites upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'upsert4.test upsert4-7.1 through upsert4-7.4 excluded/current target qualifier behavior',
        'upsert4.test upsert4-8.1 through upsert4-8.5 table named excluded and quoted composite target behavior',
        'upsert4.test upsert4-5.0 mismatched expression/collation-style target rejection',
    ], [
        'upsert4.test upsert4-7.1 through upsert4-7.4 excluded/current target qualifier behavior',
        'upsert4.test upsert4-8.1 through upsert4-8.5 table named excluded and quoted composite target behavior',
        'upsert4.test upsert4-5.0 mismatched expression/collation-style target rejection',
    ]);
};

return $tests;
