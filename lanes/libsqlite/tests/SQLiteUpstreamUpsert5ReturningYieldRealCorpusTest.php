<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$schemaVariants = [
    1 => ['name' => 'rowid primary key first', 'unique' => [['a'], ['c'], ['d'], ['e']]],
    2 => ['name' => 'int primary key first', 'unique' => [['a'], ['c'], ['d'], ['e']]],
    3 => ['name' => 'without rowid primary key first', 'unique' => [['a'], ['c'], ['d'], ['e']]],
    4 => ['name' => 'rowid primary key after unique columns', 'unique' => [['e'], ['d'], ['c'], ['a']]],
    5 => ['name' => 'int primary key after unique columns', 'unique' => [['e'], ['d'], ['c'], ['a']]],
    6 => ['name' => 'without rowid primary key after unique columns', 'unique' => [['e'], ['d'], ['c'], ['a']]],
];

$setB = static fn (string $value): array => ['b' => static fn (array $current, array $incoming): string => $value];
$arm = static fn (?array $target, string $value): array => ['target' => $target, 'assignments' => $setB($value)];
$nothing = static fn (?array $target): array => ['target' => $target, 'action' => 'nothing'];

$scenarios = [
    '100' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5], 'arms' => [$arm(['a'], 'a'), $arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['e'], 'e')], 'b' => 'a', 'target' => ['a'], 'action' => 'update'],
    '101' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5], 'arms' => [$arm(['a'], 'a'), $arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['e'], 'e')], 'b' => 'c', 'target' => ['c'], 'action' => 'update'],
    '102' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5], 'arms' => [$arm(['a'], 'a'), $arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['e'], 'e')], 'b' => 'd', 'target' => ['d'], 'action' => 'update'],
    '103' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'arms' => [$arm(['a'], 'a'), $arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['e'], 'e')], 'b' => 'e', 'target' => ['e'], 'action' => 'update'],
    '200' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['a'], 'a'), $arm(['d'], 'd'), $arm(['e'], 'e')], 'b' => 'a', 'target' => ['a'], 'action' => 'update'],
    '201' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['a'], 'a'), $arm(['d'], 'd'), $arm(['e'], 'e')], 'b' => 'c', 'target' => ['c'], 'action' => 'update'],
    '202' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5], 'arms' => [$arm(['c'], 'c'), $arm(['a'], 'a'), $arm(['d'], 'd'), $arm(['e'], 'e')], 'b' => 'c', 'target' => ['c'], 'action' => 'update'],
    '203' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'arms' => [$arm(['c'], 'c'), $arm(['a'], 'a'), $arm(['d'], 'd'), $arm(['e'], 'e')], 'b' => 'a', 'target' => ['a'], 'action' => 'update'],
    '204' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['a'], 'a'), $arm(['d'], 'd'), $arm(['e'], 'e')], 'b' => 'a', 'target' => ['a'], 'action' => 'update'],
    '210' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['a'], 'a'), $arm(['e'], 'e')], 'b' => 'a', 'target' => ['a'], 'action' => 'update'],
    '211' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['a'], 'a'), $arm(['e'], 'e')], 'b' => 'd', 'target' => ['d'], 'action' => 'update'],
    '212' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['a'], 'a'), $arm(['e'], 'e')], 'b' => 'a', 'target' => ['a'], 'action' => 'update'],
    '213' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['a'], 'a'), $arm(['e'], 'e')], 'b' => 'e', 'target' => ['e'], 'action' => 'update'],
    '214' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['e'], 'e'), $arm(['a'], 'a')], 'b' => 'e', 'target' => ['e'], 'action' => 'update'],
    '215' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['e'], 'e'), $arm(['a'], 'a')], 'b' => 'e', 'target' => ['e'], 'action' => 'update'],
    '216' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['e'], 'e'), $arm(['a'], 'a')], 'b' => 'a', 'target' => ['a'], 'action' => 'update'],
    '300' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['a'], 'a1'), $arm(['a'], 'a2'), $arm(['a'], 'a3'), $arm(['a'], 'a4'), $arm(['a'], 'a5'), $arm(['e'], 'e')], 'b' => 'a1', 'target' => ['a'], 'action' => 'update'],
    '301' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(['a'], 'a1'), $arm(['a'], 'a2'), $arm(['a'], 'a3'), $arm(['a'], 'a4'), $arm(['a'], 'a5'), $arm(['e'], 'e')], 'b' => 'e', 'target' => ['e'], 'action' => 'update'],
    '400' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(null, 'x')], 'b' => 'x', 'target' => null, 'action' => 'update'],
    '401' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(null, 'x')], 'b' => 'x', 'target' => null, 'action' => 'update'],
    '402' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(null, 'x')], 'b' => 'x', 'target' => null, 'action' => 'update'],
    '403' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(null, 'x')], 'b' => 'c', 'target' => ['c'], 'action' => 'update'],
    '404' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(null, 'x')], 'b' => 'c', 'target' => ['c'], 'action' => 'update'],
    '405' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $arm(null, 'x')], 'b' => 'd', 'target' => ['d'], 'action' => 'update'],
    '410' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'arms' => [$arm(null, 'x')], 'b' => 'x', 'target' => null, 'action' => 'update'],
    '411' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'arms' => [$arm(null, 'x')], 'b' => 'x', 'target' => null, 'action' => 'update'],
    '412' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'arms' => [$arm(null, 'x')], 'b' => 'x', 'target' => null, 'action' => 'update'],
    '413' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'arms' => [$arm(null, 'x')], 'b' => 'x', 'target' => null, 'action' => 'update'],
    '420' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'arms' => [$nothing(['c']), $nothing(['d']), $arm(null, 'x')], 'b' => 'x', 'target' => null, 'action' => 'update'],
    '421' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'arms' => [$nothing(['c']), $nothing(['d']), $arm(null, 'x')], 'b' => 'x', 'target' => null, 'action' => 'update'],
    '422' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'arms' => [$nothing(['c']), $nothing(['d']), $arm(null, 'x')], 'b' => 2, 'target' => ['d'], 'action' => 'nothing'],
    '423' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'arms' => [$nothing(['c']), $nothing(['d']), $arm(null, 'x')], 'b' => 2, 'target' => ['c'], 'action' => 'nothing'],
    '500' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $nothing(null)], 'b' => 2, 'target' => null, 'action' => 'nothing'],
    '501' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $nothing(null)], 'b' => 2, 'target' => null, 'action' => 'nothing'],
    '502' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $nothing(null)], 'b' => 2, 'target' => null, 'action' => 'nothing'],
    '503' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $nothing(null)], 'b' => 'c', 'target' => ['c'], 'action' => 'update'],
    '504' => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 95], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $nothing(null)], 'b' => 'c', 'target' => ['c'], 'action' => 'update'],
    '505' => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5], 'arms' => [$arm(['c'], 'c'), $arm(['d'], 'd'), $nothing(null)], 'b' => 'd', 'target' => ['d'], 'action' => 'update'],
];

foreach ($schemaVariants as $variant => $schema) {
    foreach ($scenarios as $upstreamId => $scenario) {
        $label = sprintf(
            'upsert5.test 1.%d.%s generalized upsert returning yield %s',
            $variant,
            $upstreamId,
            $schema['name'],
        );
        $plan = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
            [['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]],
            [$scenario['incoming']],
            $scenario['arms'],
            $schema['unique'],
        );

        $tests[$label . ' keeps upstream final b value'] = static function (TestRunner $t) use ($plan, $scenario): void {
            $t->same($scenario['b'], $plan()['after'][0]['b']);
        };
        $tests[$label . ' records the first matching conflict arm'] = static function (TestRunner $t) use ($plan, $scenario): void {
            $t->same($scenario['target'], $plan()['matched_arms'][0]['target']);
        };
        $tests[$label . ' records upstream arm action'] = static function (TestRunner $t) use ($plan, $scenario): void {
            $t->same($scenario['action'], $plan()['matched_arms'][0]['action']);
        };
        $tests[$label . ' yields RETURNING only for changed rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
            $expected = $scenario['action'] === 'update' ? [$scenario['b']] : [];
            $t->same($expected, array_column($plan()['returning_rows'], 'b'));
        };
        $tests[$label . ' keeps change counter aligned with RETURNING rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
            $t->same($scenario['action'] === 'update' ? 1 : 0, $plan()['changes']);
        };
    }
}

return $tests;
