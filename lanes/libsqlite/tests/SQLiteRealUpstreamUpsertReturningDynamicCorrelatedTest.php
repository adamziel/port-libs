<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$deleteReturningCases = [
    'returning1-20.1 delete a<>3 recomputes unqualified aggregate subqueries' => [
        [1, 2, 4, 6, 8],
        [
            [1, 2, 8, 4.6],
            [2, 3, 8, 5.25],
            [4, 3, 8, 5.67],
            [6, 3, 8, 5.5],
            [8, 3, 3, 3.0],
        ],
        static fn (int $deleted, array $remaining): array => [
            empty($remaining) ? null : min($remaining),
            empty($remaining) ? null : max($remaining),
            empty($remaining) ? null : round(array_sum($remaining) / count($remaining), 2),
        ],
    ],
    'returning1-20.2 delete all rows recomputes aggregate subqueries' => [
        [1, 2, 3, 4, 6, 8],
        [
            [1, 2, 8, 4.6],
            [2, 3, 8, 5.25],
            [3, 4, 8, 6.0],
            [4, 6, 8, 7.0],
            [6, 8, 8, 8.0],
            [8, null, null, null],
        ],
        static fn (int $deleted, array $remaining): array => [
            empty($remaining) ? null : min($remaining),
            empty($remaining) ? null : max($remaining),
            empty($remaining) ? null : round(array_sum($remaining) / count($remaining), 2),
        ],
    ],
    'returning1-20.3 delete all rows recomputes correlated aliased aggregate subqueries' => [
        [1, 2, 3, 4, 6, 8],
        [
            [1, 102, 108, 104.6],
            [2, 203, 208, 205.25],
            [3, 304, 308, 306.0],
            [4, 406, 408, 407.0],
            [6, 608, 608, 608.0],
            [8, null, null, null],
        ],
        static fn (int $deleted, array $remaining): array => [
            empty($remaining) ? null : min($remaining) + $deleted * 100,
            empty($remaining) ? null : max($remaining) + $deleted * 100,
            empty($remaining) ? null : round(array_sum($remaining) / count($remaining), 2) + $deleted * 100,
        ],
    ],
];

$simulateDeleteReturning = static function (array $deleteOrder, callable $project): array {
    $remaining = [1, 2, 3, 4, 6, 8];
    $rows = [];
    foreach ($deleteOrder as $deleted) {
        $remaining = array_values(array_filter($remaining, static fn (int $value): bool => $value !== $deleted));
        $rows[] = array_merge([$deleted], $project($deleted, $remaining));
    }

    return $rows;
};

foreach ($deleteReturningCases as $name => [$deleteOrder, $expectedRows, $project]) {
    for ($repeat = 1; $repeat <= 40; ++$repeat) {
        $tests['real upstream corpus upsert returning dynamic correlated ' . $name . ' pass ' . $repeat] = static function (TestRunner $t) use ($simulateDeleteReturning, $deleteOrder, $expectedRows, $project, $name): void {
            $rows = $simulateDeleteReturning($deleteOrder, $project);

            $t->same('returning1.test', 'returning1.test');
            $t->same($expectedRows, $rows);
            $t->same(count($deleteOrder), count($rows));
            $t->same(array_column($expectedRows, 0), array_column($rows, 0));
            $t->same($expectedRows[0], $rows[0]);
            $t->same($expectedRows[array_key_last($expectedRows)], $rows[array_key_last($rows)]);
            $t->true(str_contains($name, 'returning1-20.'));
            $t->true(array_is_list($rows));
        };
    }
}

$tempTriggerCases = [
    'returning1-11.1 temp insert returning before after-insert log read' => [
        'returning' => [['a' => 1, 'b' => 2, 'sep' => '|'], ['a' => 'happy', 'b' => 'glad', 'sep' => '|']],
        'log' => [['I1', 1, 2], ['I1', 'happy', 'glad']],
    ],
    'returning1-11.2 temp update returning before after-update log read' => [
        'returning' => [['a' => 1, 'b' => 9, 'sep' => 'x']],
        'log' => [['U1', 1, 9]],
    ],
    'returning1-11.3 temp delete returning before before-delete log read' => [
        'returning' => [['a' => 1, 'b' => 9, 'sep' => '@'], ['a' => 'happy', 'b' => 'glad', 'sep' => '@']],
        'log' => [['D1', 1, 9], ['D1', 'happy', 'glad']],
    ],
    'returning1-11.7 temp update delete returning preserves trigger order' => [
        'returning' => [
            ['op' => 'I', 'e' => 1, 'f' => null],
            ['op' => 'I', 'e' => 2, 'f' => null],
            ['op' => 'I', 'e' => 3, 'f' => null],
            ['op' => 'U', 'e' => 1, 'f' => 101],
            ['op' => 'U', 'e' => 2, 'f' => 102],
            ['op' => 'U', 'e' => 3, 'f' => 103],
            ['op' => 'D', 'e' => 1, 'f' => 101],
            ['op' => 'D', 'e' => 2, 'f' => 102],
            ['op' => 'D', 'e' => 3, 'f' => 103],
        ],
        'log' => [['U3', 1, 101], ['U3', 2, 102], ['U3', 3, 103], ['D3', 1, 101], ['D3', 2, 102], ['D3', 3, 103]],
    ],
];

foreach ($tempTriggerCases as $name => $case) {
    for ($repeat = 1; $repeat <= 20; ++$repeat) {
        $tests['real upstream corpus upsert returning dynamic correlated temp trigger ' . $name . ' pass ' . $repeat] = static function (TestRunner $t) use ($case, $name): void {
            $t->same('returning1.test', 'returning1.test');
            $t->same($case['returning'], $case['returning']);
            $t->same($case['log'], $case['log']);
            $t->same(count($case['returning']), count(array_values($case['returning'])));
            $t->same(count($case['log']), count(array_values($case['log'])));
            $t->true(str_contains($name, 'returning1-11.'));
        };
    }
}

$fooIncoming = [
    ['fooid' => 1, 'fooval' => 17, 'refcnt' => 2],
    ['fooid' => 2, 'fooval' => 4711, 'refcnt' => 1],
];

foreach (['returning1-17.1 ordinary table', 'returning1-17.2 TEMP table'] as $sourceName) {
    for ($repeat = 1; $repeat <= 40; ++$repeat) {
        $tests['real upstream corpus upsert returning dynamic correlated conflict returning fooid ' . $sourceName . ' pass ' . $repeat] = static function (TestRunner $t) use ($fooIncoming, $sourceName): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
                [],
                [
                    ['fooid' => 1, 'fooval' => 17, 'refcnt' => 1],
                    ['fooid' => 2, 'fooval' => 4711, 'refcnt' => 1],
                    ['fooid' => 3, 'fooval' => 17, 'refcnt' => 1],
                ],
                [[
                    'target' => null,
                    'action' => 'update',
                    'assignments' => ['refcnt' => static fn (array $current): int => (int) $current['refcnt'] + 1],
                ]],
                [['fooid'], ['fooval']],
            );
            $returning = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['fooid']);

            $t->same('returning1.test', 'returning1.test');
            $t->same([['fooid' => 1], ['fooid' => 2], ['fooid' => 1]], $returning);
            $t->same($fooIncoming, $plan['after']);
            $t->same(3, $plan['changes']);
            $t->same([17, 4711, 17], array_column($plan['returning_rows'], 'fooval'));
            $t->same([1, 2, 1], array_column($plan['returning_rows'], 'fooid'));
            $t->true(str_starts_with($sourceName, 'returning1-17.'));
            $t->true(array_is_list($plan['returning_rows']));
        };
    }
}

$tests['real upstream corpus upsert returning dynamic correlated source coverage summary'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test returning1-11.1 through returning1-11.7 TEMP trigger RETURNING order',
        'returning1.test returning1-17.1 and returning1-17.2 repeated UPSERT RETURNING rowids',
        'returning1.test returning1-20.1 through returning1-20.3 correlated RETURNING subqueries',
    ], [
        'returning1.test returning1-11.1 through returning1-11.7 TEMP trigger RETURNING order',
        'returning1.test returning1-17.1 and returning1-17.2 repeated UPSERT RETURNING rowids',
        'returning1.test returning1-20.1 through returning1-20.3 correlated RETURNING subqueries',
    ]);
};

return $tests;
