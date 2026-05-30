<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$baseRow = static fn (): array => [
    ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5],
];

$uniqueConstraints = [['a'], ['c'], ['d'], ['e']];

$arm = static fn (?array $target, string $value, string $action = 'update'): array => [
    'target' => $target,
    'action' => $action,
    'assignments' => $action === 'update' ? ['b' => static fn (array $current, array $incoming): string => $value] : [],
];

$armsAFirst = [
    $arm(['a'], 'a'),
    $arm(['c'], 'c'),
    $arm(['d'], 'd'),
    $arm(['e'], 'e'),
];
$armsCFirst = [
    $arm(['c'], 'c'),
    $arm(['a'], 'a'),
    $arm(['d'], 'd'),
    $arm(['e'], 'e'),
];
$armsCDThenA = [
    $arm(['c'], 'c'),
    $arm(['d'], 'd'),
    $arm(['a'], 'a'),
    $arm(['e'], 'e'),
];
$armsCDEThenA = [
    $arm(['c'], 'c'),
    $arm(['d'], 'd'),
    $arm(['e'], 'e'),
    $arm(['a'], 'a'),
];
$armsRepeatedA = [
    $arm(['c'], 'c'),
    $arm(['d'], 'd'),
    $arm(['a'], 'a1'),
    $arm(['a'], 'a2'),
    $arm(['a'], 'a3'),
    $arm(['a'], 'a4'),
    $arm(['a'], 'a5'),
    $arm(['e'], 'e'),
];
$armsCatchAllUpdate = [
    $arm(['c'], 'c'),
    $arm(['d'], 'd'),
    $arm(null, 'x'),
];
$armsCatchAllOnly = [
    $arm(null, 'x'),
];
$armsNothingThenCatchAll = [
    $arm(['c'], '', 'nothing'),
    $arm(['d'], '', 'nothing'),
    $arm(null, 'x'),
];
$armsUpdateThenNothing = [
    $arm(['c'], 'c'),
    $arm(['d'], 'd'),
    $arm(null, '', 'nothing'),
];

$dynamicCases = [
    'upsert5-1.x.100 first a-target arm wins when all constraints conflict' => [$armsAFirst, ['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5], 'a', ['a'], 'update', 1, 0],
    'upsert5-1.x.101 first c-target arm wins when a does not conflict' => [$armsAFirst, ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5], 'c', ['c'], 'update', 1, 0],
    'upsert5-1.x.102 first d-target arm wins after non-conflicting a and c' => [$armsAFirst, ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5], 'd', ['d'], 'update', 1, 0],
    'upsert5-1.x.103 first e-target arm wins after earlier targets miss' => [$armsAFirst, ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'e', ['e'], 'update', 1, 0],
    'upsert5-1.x.200 reordered c then a still selects a when c misses' => [$armsCFirst, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'a', ['a'], 'update', 1, 0],
    'upsert5-1.x.201 reordered c arm preempts a when both conflict' => [$armsCFirst, ['a' => 1, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'c', ['c'], 'update', 1, 0],
    'upsert5-1.x.202 reordered c arm preempts all later conflicts' => [$armsCFirst, ['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5], 'c', ['c'], 'update', 1, 0],
    'upsert5-1.x.203 reordered a arm wins before e when c misses' => [$armsCFirst, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'a', ['a'], 'update', 1, 0],
    'upsert5-1.x.204 reordered a arm wins before d when c misses' => [$armsCFirst, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'a', ['a'], 'update', 1, 0],
    'upsert5-1.x.210 c-d-a ordering selects a when c and d miss' => [$armsCDThenA, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'a', ['a'], 'update', 1, 0],
    'upsert5-1.x.211 c-d-a ordering selects d before a' => [$armsCDThenA, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'd', ['d'], 'update', 1, 0],
    'upsert5-1.x.212 c-d-a ordering selects a before e' => [$armsCDThenA, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'a', ['a'], 'update', 1, 0],
    'upsert5-1.x.213 c-d-a ordering selects e after misses' => [$armsCDThenA, ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'e', ['e'], 'update', 1, 0],
    'upsert5-1.x.214 c-d-e-a ordering selects e before a' => [$armsCDEThenA, ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'e', ['e'], 'update', 1, 0],
    'upsert5-1.x.215 c-d-e-a ordering selects e when a also conflicts' => [$armsCDEThenA, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'e', ['e'], 'update', 1, 0],
    'upsert5-1.x.216 c-d-e-a ordering falls through to a' => [$armsCDEThenA, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'a', ['a'], 'update', 1, 0],
    'upsert5-1.x.300 duplicate a arms use first matching duplicate' => [$armsRepeatedA, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'a1', ['a'], 'update', 1, 0],
    'upsert5-1.x.301 duplicate a arms do not preempt later e match' => [$armsRepeatedA, ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'e', ['e'], 'update', 1, 0],
    'upsert5-1.x.400 catch-all update handles a conflict' => [$armsCatchAllUpdate, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'x', null, 'update', 1, 0],
    'upsert5-1.x.401 catch-all update handles e conflict' => [$armsCatchAllUpdate, ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'x', null, 'update', 1, 0],
    'upsert5-1.x.402 catch-all update handles a conflict after misses' => [$armsCatchAllUpdate, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'x', null, 'update', 1, 0],
    'upsert5-1.x.403 targeted c preempts catch-all' => [$armsCatchAllUpdate, ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'c', ['c'], 'update', 1, 0],
    'upsert5-1.x.404 targeted c preempts d and catch-all' => [$armsCatchAllUpdate, ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 95], 'c', ['c'], 'update', 1, 0],
    'upsert5-1.x.405 targeted d preempts catch-all' => [$armsCatchAllUpdate, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5], 'd', ['d'], 'update', 1, 0],
    'upsert5-1.x.410 bare catch-all handles a conflict' => [$armsCatchAllOnly, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'x', null, 'update', 1, 0],
    'upsert5-1.x.411 bare catch-all handles e conflict' => [$armsCatchAllOnly, ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'x', null, 'update', 1, 0],
    'upsert5-1.x.412 bare catch-all handles d conflict' => [$armsCatchAllOnly, ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'x', null, 'update', 1, 0],
    'upsert5-1.x.413 bare catch-all handles c conflict' => [$armsCatchAllOnly, ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'x', null, 'update', 1, 0],
    'upsert5-1.x.420 do-nothing arms fall through to catch-all for a' => [$armsNothingThenCatchAll, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'x', null, 'update', 1, 0],
    'upsert5-1.x.421 do-nothing arms fall through to catch-all for e' => [$armsNothingThenCatchAll, ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'x', null, 'update', 1, 0],
    'upsert5-1.x.422 do-nothing d arm suppresses catch-all' => [$armsNothingThenCatchAll, ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 2, ['d'], 'nothing', 0, 1],
    'upsert5-1.x.423 do-nothing c arm suppresses catch-all' => [$armsNothingThenCatchAll, ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 2, ['c'], 'nothing', 0, 1],
    'upsert5-1.x.500 catch-all do-nothing handles a conflict' => [$armsUpdateThenNothing, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 2, null, 'nothing', 0, 1],
    'upsert5-1.x.501 catch-all do-nothing handles e conflict' => [$armsUpdateThenNothing, ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 2, null, 'nothing', 0, 1],
    'upsert5-1.x.502 catch-all do-nothing handles a conflict after misses' => [$armsUpdateThenNothing, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 2, null, 'nothing', 0, 1],
    'upsert5-1.x.503 targeted c update beats catch-all do-nothing' => [$armsUpdateThenNothing, ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'c', ['c'], 'update', 1, 0],
    'upsert5-1.x.504 targeted c update beats d and catch-all do-nothing' => [$armsUpdateThenNothing, ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 95], 'c', ['c'], 'update', 1, 0],
    'upsert5-1.x.505 targeted d update beats catch-all do-nothing' => [$armsUpdateThenNothing, ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5], 'd', ['d'], 'update', 1, 0],
];

$mixedReturning = static function (): array {
    $rows = [
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 4, 'b' => 5, 'c' => 6],
        ['a' => 7, 'b' => 8, 'c' => 9],
    ];
    $incoming = [
        ['a' => 2, 'b' => 3, 'c' => 4],
        ['a' => 4, 'b' => 5, 'c' => 6],
        ['a' => 5, 'b' => 6, 'c' => 7],
    ];

    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $rows,
        $incoming,
        [[
            'target' => ['a'],
            'action' => 'update',
            'assignments' => ['b' => static fn (array $current, array $candidate): int => 100],
        ]],
        [['a']],
    );
};

$tests = [];

foreach ($dynamicCases as $name => [$arms, $incoming, $expectedB, $expectedTarget, $expectedAction, $expectedChanges, $expectedSkipped]) {
    $tests['real upstream corpus dynamic UPSERT ' . $name . ' final row image'] = static function (TestRunner $t) use ($baseRow, $incoming, $arms, $uniqueConstraints, $expectedB): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRow(), [$incoming], $arms, $uniqueConstraints);
        $t->same($expectedB, $result['after'][0]['b']);
    };
    $tests['real upstream corpus dynamic UPSERT ' . $name . ' matched arm target'] = static function (TestRunner $t) use ($baseRow, $incoming, $arms, $uniqueConstraints, $expectedTarget): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRow(), [$incoming], $arms, $uniqueConstraints);
        $t->same($expectedTarget, $result['matched_arms'][0]['target']);
    };
    $tests['real upstream corpus dynamic UPSERT ' . $name . ' matched arm action'] = static function (TestRunner $t) use ($baseRow, $incoming, $arms, $uniqueConstraints, $expectedAction): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRow(), [$incoming], $arms, $uniqueConstraints);
        $t->same($expectedAction, $result['matched_arms'][0]['action']);
    };
    $tests['real upstream corpus dynamic UPSERT ' . $name . ' changes count'] = static function (TestRunner $t) use ($baseRow, $incoming, $arms, $uniqueConstraints, $expectedChanges): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRow(), [$incoming], $arms, $uniqueConstraints);
        $t->same($expectedChanges, $result['changes']);
    };
    $tests['real upstream corpus dynamic UPSERT ' . $name . ' skipped count'] = static function (TestRunner $t) use ($baseRow, $incoming, $arms, $uniqueConstraints, $expectedSkipped): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRow(), [$incoming], $arms, $uniqueConstraints);
        $t->same($expectedSkipped, count($result['skipped_rows']));
    };
}

$tests['real upstream corpus returning1-4.2 conflict update returns updated row'] = static function (TestRunner $t) use ($mixedReturning): void {
    $t->same([['a' => 2, 'b' => 3, 'c' => 4], ['a' => 4, 'b' => 100, 'c' => 6], ['a' => 5, 'b' => 6, 'c' => 7]], $mixedReturning()['returning_rows']);
};
$tests['real upstream corpus returning1-4.5 mixed insert update preserves RETURNING order'] = static function (TestRunner $t) use ($mixedReturning): void {
    $t->same([2, 4, 5], array_column($mixedReturning()['returning_rows'], 'a'));
};
$tests['real upstream corpus returning1-4.5 mixed insert update projects star rows'] = static function (TestRunner $t) use ($mixedReturning): void {
    $t->same($mixedReturning()['returning_rows'], SQLiteUpsertDoUpdateWherePlan::returningRows($mixedReturning()['returning_rows'], ['*']));
};
$tests['real upstream corpus returning1-4.5 mixed insert update projects literal separator'] = static function (TestRunner $t) use ($mixedReturning): void {
    $rows = SQLiteUpsertDoUpdateWherePlan::returningRows($mixedReturning()['returning_rows'], ['a', 'b', 'c', 'separator' => static fn (array $row): string => '|']);
    $t->same(['|', '|', '|'], array_column($rows, 'separator'));
};
$tests['real upstream corpus returning1-4.5 mixed insert update final table row order'] = static function (TestRunner $t) use ($mixedReturning): void {
    $t->same([1, 4, 7, 2, 5], array_column($mixedReturning()['after'], 'a'));
};
$tests['real upstream corpus returning1-4.5 mixed insert update changed count'] = static function (TestRunner $t) use ($mixedReturning): void {
    $t->same(3, $mixedReturning()['changes']);
};
$tests['real upstream corpus upsert5 invalid target reports unmatched constraint'] = static function (TestRunner $t) use ($baseRow, $arm, $uniqueConstraints): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRow(), [['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5]], [$arm(['missing'], 'bad')], $uniqueConstraints));
};
$tests['real upstream corpus upsert5 do-nothing arm rejects assignments'] = static function (TestRunner $t) use ($baseRow, $uniqueConstraints): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRow(), [['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5]], [['target' => ['a'], 'action' => 'nothing', 'assignments' => ['b' => static fn (): string => 'bad']]], $uniqueConstraints));
};

return $tests;
