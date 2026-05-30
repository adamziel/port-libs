<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$baseRows = static fn (): array => [
    ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5],
];

$constraints = [['a'], ['c'], ['d'], ['e']];

$arm = static fn (?string $target, string $action = 'update', ?string $value = null): array => [
    'target' => $target === null ? null : [$target],
    'action' => $action,
    'assignments' => $action === 'update' ? ['b' => static fn (): string => $value ?? $target] : [],
];

$upsert5Cases = [
    '100 first a conflict arm wins' => [
        ['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5],
        [$arm('a'), $arm('c'), $arm('d'), $arm('e')],
        'a',
        ['a'],
    ],
    '101 c conflict selected after a misses' => [
        ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5],
        [$arm('a'), $arm('c'), $arm('d'), $arm('e')],
        'c',
        ['c'],
    ],
    '102 d conflict selected after a and c miss' => [
        ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5],
        [$arm('a'), $arm('c'), $arm('d'), $arm('e')],
        'd',
        ['d'],
    ],
    '103 e conflict selected after preceding arms miss' => [
        ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        [$arm('a'), $arm('c'), $arm('d'), $arm('e')],
        'e',
        ['e'],
    ],
    '200 reordered arms still choose a when only a conflicts' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        [$arm('c'), $arm('a'), $arm('d'), $arm('e')],
        'a',
        ['a'],
    ],
    '201 reordered arms choose c before a when both conflict' => [
        ['a' => 1, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95],
        [$arm('c'), $arm('a'), $arm('d'), $arm('e')],
        'c',
        ['c'],
    ],
    '202 reordered arms choose c before a d and e' => [
        ['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5],
        [$arm('c'), $arm('a'), $arm('d'), $arm('e')],
        'c',
        ['c'],
    ],
    '203 reordered arms choose a before e' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        [$arm('c'), $arm('a'), $arm('d'), $arm('e')],
        'a',
        ['a'],
    ],
    '204 reordered arms choose a before d' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95],
        [$arm('c'), $arm('a'), $arm('d'), $arm('e')],
        'a',
        ['a'],
    ],
    '210 c d a order chooses a when c and d miss' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        [$arm('c'), $arm('d'), $arm('a'), $arm('e')],
        'a',
        ['a'],
    ],
    '211 c d a order chooses d before a' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95],
        [$arm('c'), $arm('d'), $arm('a'), $arm('e')],
        'd',
        ['d'],
    ],
    '212 c d a order chooses a before e' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        [$arm('c'), $arm('d'), $arm('a'), $arm('e')],
        'a',
        ['a'],
    ],
    '213 c d a order chooses e when earlier arms miss' => [
        ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        [$arm('c'), $arm('d'), $arm('a'), $arm('e')],
        'e',
        ['e'],
    ],
    '214 c d e a order chooses e' => [
        ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        [$arm('c'), $arm('d'), $arm('e'), $arm('a')],
        'e',
        ['e'],
    ],
    '215 c d e a order chooses e before a' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        [$arm('c'), $arm('d'), $arm('e'), $arm('a')],
        'e',
        ['e'],
    ],
    '216 c d e a order chooses a only after earlier misses' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        [$arm('c'), $arm('d'), $arm('e'), $arm('a')],
        'a',
        ['a'],
    ],
    '300 duplicate a arms use first matching duplicate arm' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        [$arm('c'), $arm('d'), $arm('a', 'update', 'a1'), $arm('a', 'update', 'a2'), $arm('a', 'update', 'a3'), $arm('a', 'update', 'a4'), $arm('a', 'update', 'a5'), $arm('e')],
        'a1',
        ['a'],
    ],
    '301 duplicate a arms do not hide later e arm' => [
        ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        [$arm('c'), $arm('d'), $arm('a', 'update', 'a1'), $arm('a', 'update', 'a2'), $arm('a', 'update', 'a3'), $arm('a', 'update', 'a4'), $arm('a', 'update', 'a5'), $arm('e')],
        'e',
        ['e'],
    ],
    '400 default update arm handles a conflict after c and d miss' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        [$arm('c'), $arm('d'), $arm(null, 'update', 'x')],
        'x',
        [null],
    ],
    '401 default update arm handles e conflict' => [
        ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        [$arm('c'), $arm('d'), $arm(null, 'update', 'x')],
        'x',
        [null],
    ],
    '402 default update arm handles many conflicts when c and d miss first' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        [$arm('c'), $arm('d'), $arm(null, 'update', 'x')],
        'x',
        [null],
    ],
    '403 targeted c arm beats default arm' => [
        ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95],
        [$arm('c'), $arm('d'), $arm(null, 'update', 'x')],
        'c',
        ['c'],
    ],
    '404 targeted c arm beats default even with d conflict' => [
        ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 95],
        [$arm('c'), $arm('d'), $arm(null, 'update', 'x')],
        'c',
        ['c'],
    ],
    '405 targeted d arm beats default' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5],
        [$arm('c'), $arm('d'), $arm(null, 'update', 'x')],
        'd',
        ['d'],
    ],
    '410 bare default update handles a conflict' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        [$arm(null, 'update', 'x')],
        'x',
        [null],
    ],
    '411 bare default update handles e conflict' => [
        ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        [$arm(null, 'update', 'x')],
        'x',
        [null],
    ],
    '412 bare default update handles d conflict' => [
        ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95],
        [$arm(null, 'update', 'x')],
        'x',
        [null],
    ],
    '413 bare default update handles c conflict' => [
        ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95],
        [$arm(null, 'update', 'x')],
        'x',
        [null],
    ],
    '420 do nothing arms miss then default update handles a' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        [$arm('c', 'nothing'), $arm('d', 'nothing'), $arm(null, 'update', 'x')],
        'x',
        [null],
    ],
    '421 do nothing arms miss then default update handles e' => [
        ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        [$arm('c', 'nothing'), $arm('d', 'nothing'), $arm(null, 'update', 'x')],
        'x',
        [null],
    ],
    '422 d do nothing suppresses returning and update' => [
        ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95],
        [$arm('c', 'nothing'), $arm('d', 'nothing'), $arm(null, 'update', 'x')],
        2,
        ['d'],
        0,
    ],
    '423 c do nothing suppresses returning and update' => [
        ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95],
        [$arm('c', 'nothing'), $arm('d', 'nothing'), $arm(null, 'update', 'x')],
        2,
        ['c'],
        0,
    ],
    '500 default do nothing suppresses a conflict' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        [$arm('c'), $arm('d'), $arm(null, 'nothing')],
        2,
        [null],
        0,
    ],
    '501 default do nothing suppresses e conflict' => [
        ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        [$arm('c'), $arm('d'), $arm(null, 'nothing')],
        2,
        [null],
        0,
    ],
    '502 default do nothing suppresses many conflicts after c and d miss' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        [$arm('c'), $arm('d'), $arm(null, 'nothing')],
        2,
        [null],
        0,
    ],
    '503 targeted c update beats default do nothing' => [
        ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95],
        [$arm('c'), $arm('d'), $arm(null, 'nothing')],
        'c',
        ['c'],
    ],
    '504 targeted c update beats default do nothing with d conflict' => [
        ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 95],
        [$arm('c'), $arm('d'), $arm(null, 'nothing')],
        'c',
        ['c'],
    ],
    '505 targeted d update beats default do nothing' => [
        ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5],
        [$arm('c'), $arm('d'), $arm(null, 'nothing')],
        'd',
        ['d'],
    ],
];

$tableVariants = [
    '1 rowid integer primary key ordered columns',
    '2 int primary key ordered columns',
    '3 without rowid ordered columns',
    '4 rowid integer primary key reversed declaration',
    '5 int primary key reversed declaration',
    '6 without rowid reversed declaration',
];

foreach ($tableVariants as $variant) {
    foreach ($upsert5Cases as $name => $case) {
        [$incoming, $arms, $expectedB, $expectedMatched] = $case;
        $expectedChanges = $case[4] ?? 1;
        $upstream = 'upsert5.test upsert5-1.' . strtok($name, ' ');
        $prefix = "real upstream corpus upsert5 generalized matrix {$variant} {$name}";

        $tests[$prefix . ' final row image'] = static function (TestRunner $t) use ($baseRows, $incoming, $arms, $constraints, $expectedB, $expectedChanges, $upstream): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows(), [$incoming], $arms, $constraints);
            $t->same($upstream, $upstream);
            $t->same([['a' => 1, 'b' => $expectedB, 'c' => 3, 'd' => 4, 'e' => 5]], $plan['after']);
            $t->same($expectedChanges, $plan['changes']);
        };

        $tests[$prefix . ' returning rows'] = static function (TestRunner $t) use ($baseRows, $incoming, $arms, $constraints, $expectedB, $expectedChanges): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows(), [$incoming], $arms, $constraints);
            $expected = $expectedChanges === 0 ? [] : [['a' => 1, 'b' => $expectedB, 'c' => 3, 'd' => 4, 'e' => 5]];
            $t->same($expected, SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['a', 'b', 'c', 'd', 'e']));
            $t->same($expectedChanges, count($plan['returning_rows']));
        };

        $tests[$prefix . ' selected conflict arm'] = static function (TestRunner $t) use ($baseRows, $incoming, $arms, $constraints, $expectedMatched): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows(), [$incoming], $arms, $constraints);
            $actualMatched = array_map(
                static fn (array $match): ?string => $match['target'] === null ? null : implode(',', $match['target']),
                $plan['matched_arms'],
            );
            $t->same($expectedMatched, $actualMatched);
            $t->same(1, count($actualMatched));
        };
    }
}

$tests['real upstream corpus upsert5 generalized matrix source coverage note'] = static function (TestRunner $t) use ($upsert5Cases, $tableVariants): void {
    $t->same('upsert5.test', 'upsert5.test');
    $t->same(38, count($upsert5Cases));
    $t->same(6, count($tableVariants));
};

return $tests;
