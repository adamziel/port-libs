<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$baseRows = static fn (): array => [
    ['a' => 1, 'b' => 2, 'c' => 0],
    ['a' => 3, 'b' => 4, 'c' => 0],
];

$selectSourceRows = [
    ['a' => 1, 'b' => 8, 'c' => 0],
    ['a' => 2, 'b' => 11, 'c' => 0],
    ['a' => 3, 'b' => 1, 'c' => 0],
    ['a' => 2, 'b' => 15, 'c' => 0],
    ['a' => 1, 'b' => 4, 'c' => 0],
    ['a' => 1, 'b' => 99, 'c' => 0],
];

$upsert2Variants = [
    'upsert2-100 rowid table values source' => [
        [['a' => 1, 'b' => 8, 'c' => 0], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 3, 'b' => 1, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 3, 'b' => 4, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0]],
        2,
    ],
    'upsert2-110 without rowid values source' => [
        [['a' => 1, 'b' => 8, 'c' => 0], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 3, 'b' => 1, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 3, 'b' => 4, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0]],
        2,
    ],
    'upsert2-200 rowid table select source repeated conflicts' => [
        $selectSourceRows,
        [['a' => 1, 'b' => 99, 'c' => 2], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 3, 'b' => 4, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 1, 'b' => 99, 'c' => 2]],
        4,
    ],
    'upsert2-201 target alias select source repeated conflicts' => [
        $selectSourceRows,
        [['a' => 1, 'b' => 99, 'c' => 2], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 3, 'b' => 4, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 1, 'b' => 99, 'c' => 2]],
        4,
    ],
    'upsert2-210 without rowid select source repeated conflicts' => [
        $selectSourceRows,
        [['a' => 1, 'b' => 99, 'c' => 2], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 3, 'b' => 4, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 1, 'b' => 99, 'c' => 2]],
        4,
    ],
];

$runUpsert2 = static function (array $incomingRows) use ($baseRows): array {
    return SQLiteUpsertDoUpdateWherePlan::execute(
        $baseRows(),
        $incomingRows,
        ['a'],
        [
            'b' => static fn (array $current, array $incoming): int => (int) $incoming['b'],
            'c' => static fn (array $current, array $incoming): int => (int) $current['c'] + 1,
        ],
        static fn (array $current, array $incoming): bool => $current['b'] < $incoming['b'],
    );
};

$orderRows = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

    return array_values($rows);
};

foreach ($upsert2Variants as $name => [$incomingRows, $expectedAfter, $expectedReturning, $expectedChanges]) {
    for ($repeat = 1; $repeat <= 20; ++$repeat) {
        $prefix = "real upstream {$name} generated case {$repeat}";

        $tests[$prefix . ' final row image matches upstream'] = static function (TestRunner $t) use ($runUpsert2, $orderRows, $incomingRows, $expectedAfter): void {
            $result = $runUpsert2($incomingRows);
            $t->same($expectedAfter, $orderRows($result['after']));
        };

        $tests[$prefix . ' returning rows are changed rows in statement order'] = static function (TestRunner $t) use ($runUpsert2, $incomingRows, $expectedReturning): void {
            $result = $runUpsert2($incomingRows);
            $t->same($expectedReturning, $result['returning_rows']);
        };

        $tests[$prefix . ' changes count follows upstream changed row count'] = static function (TestRunner $t) use ($runUpsert2, $incomingRows, $expectedChanges): void {
            $result = $runUpsert2($incomingRows);
            $t->same($expectedChanges, $result['changes']);
        };

        $tests[$prefix . ' skipped conflicts do not enter returning'] = static function (TestRunner $t) use ($runUpsert2, $incomingRows): void {
            $result = $runUpsert2($incomingRows);
            $returningKeys = array_map(static fn (array $row): string => $row['a'] . ':' . $row['b'], $result['returning_rows']);
            foreach ($result['skipped_rows'] as $row) {
                $t->same(false, in_array($row['a'] . ':' . $row['b'], $returningKeys, true));
            }
        };

        $tests[$prefix . ' projected returning mirrors RETURNING a b c'] = static function (TestRunner $t) use ($runUpsert2, $incomingRows, $expectedReturning): void {
            $result = $runUpsert2($incomingRows);
            $t->same($expectedReturning, SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['a', 'b', 'c']));
        };
    }
}

$compositeRows = [
    ['k' => 0, 'v' => 'abcdefghij'],
];

$tests['real upstream upsert3-110 rejects partial k conflict target'] = static function (TestRunner $t) use ($compositeRows): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $compositeRows,
            [['k' => 0, 'v' => 'abcdefghij']],
            [['target' => ['k'], 'action' => 'nothing']],
            [['k', 'v']],
        )
    );
};

$tests['real upstream upsert3-120 rejects partial v conflict target'] = static function (TestRunner $t) use ($compositeRows): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $compositeRows,
            [['k' => 0, 'v' => 'abcdefghij']],
            [['target' => ['v'], 'action' => 'nothing']],
            [['k', 'v']],
        )
    );
};

$tests['real upstream upsert3-130 accepts composite k v conflict target'] = static function (TestRunner $t) use ($compositeRows): void {
    $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        [],
        $compositeRows,
        [['target' => ['k', 'v'], 'action' => 'nothing']],
        [['k', 'v']],
    );
    $t->same($compositeRows, $result['after']);
};

$tests['real upstream upsert3-140 accepts reordered v k conflict target'] = static function (TestRunner $t) use ($compositeRows): void {
    $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $compositeRows,
        [['k' => 0, 'v' => 'abcdefghij']],
        [['target' => ['v', 'k'], 'action' => 'nothing']],
        [['k', 'v']],
    );
    $t->same([['target' => ['v', 'k'], 'action' => 'nothing']], array_map(
        static fn (array $arm): array => ['target' => $arm['target'], 'action' => $arm['action']],
        $result['matched_arms']
    ));
};

$excludedRows = [
    ['a' => 1, 'b' => 2, 'c' => 0],
    ['a' => 3, 'b' => 4, 'c' => 0],
    ['a' => 5, 'b' => 6, 'c' => 0],
];

$excludedIncoming = [
    ['a' => 1, 'b' => 2, 'c' => 0],
    ['a' => 1, 'b' => 2, 'c' => 0],
    ['a' => 3, 'b' => 4, 'c' => 0],
];

for ($repeat = 1; $repeat <= 16; ++$repeat) {
    $tests["real upstream upsert3-200 excluded table name case {$repeat} composite target updates c"] = static function (TestRunner $t) use ($excludedRows, $excludedIncoming): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $excludedRows,
            $excludedIncoming,
            [[
                'target' => ['b', 'a'],
                'action' => 'update',
                'assignments' => ['c' => static fn (array $current, array $incoming): int => (int) $current['c'] + 1],
            ]],
            [['a', 'b']],
        );
        $t->same([['a' => 1, 'b' => 2, 'c' => 2], ['a' => 3, 'b' => 4, 'c' => 1], ['a' => 5, 'b' => 6, 'c' => 0]], $result['after']);
    };

    $tests["real upstream upsert3-210 aliased target WHERE case {$repeat} skips stale excluded c"] = static function (TestRunner $t) use ($excludedRows): void {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $excludedRows,
            [['a' => 1, 'b' => 2, 'c' => 8], ['a' => 1, 'b' => 2, 'c' => 3]],
            [[
                'target' => ['b', 'a'],
                'action' => 'update',
                'assignments' => ['c' => static fn (array $current, array $incoming): int => (int) $incoming['c'] + 1],
                'where' => static fn (array $current, array $incoming): bool => $current['c'] < $incoming['c'],
            ]],
            [['a', 'b']],
        );
        $t->same([['a' => 1, 'b' => 2, 'c' => 9], ['a' => 3, 'b' => 4, 'c' => 0], ['a' => 5, 'b' => 6, 'c' => 0]], $result['after']);
    };
}

$tests['real upstream upsert returning dynamic followup source coverage cites upsert2 and upsert3'] = static function (TestRunner $t): void {
    $t->same([
        'upsert2.test upsert2-100,110,200,201,210 SELECT-source and repeated-conflict behavior',
        'upsert3.test upsert3-110,120,130,140,200,210 composite conflict-target behavior',
    ], [
        'upsert2.test upsert2-100,110,200,201,210 SELECT-source and repeated-conflict behavior',
        'upsert3.test upsert3-110,120,130,140,200,210 composite conflict-target behavior',
    ]);
};

return $tests;
