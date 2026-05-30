<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$project = static fn (array $rows): array => array_values(array_map('array_values', $rows));

$returningCases = [];
for ($i = 0; $i < 100; $i++) {
    $base = 1000 + $i * 10;
    $returningCases["returning1-4.5 parser mixed insert update variant {$i}"] = [
        [
            ['a' => $base + 1, 'b' => 2, 'c' => 3],
            ['a' => $base + 4, 'b' => 5, 'c' => 6],
            ['a' => $base + 7, 'b' => 8, 'c' => 9],
        ],
        [
            [$base + 2, 3, 4],
            [$base + 4, 5, 6],
            [$base + 5, 6, 7],
        ],
        [
            [$base + 2, 3, 4],
            [$base + 4, 100 + $i, 6],
            [$base + 5, 6, 7],
        ],
        [
            [$base + 1, 2, 3],
            [$base + 4, 100 + $i, 6],
            [$base + 7, 8, 9],
            [$base + 2, 3, 4],
            [$base + 5, 6, 7],
        ],
        100 + $i,
    ];
}

foreach ($returningCases as $name => [$beforeRows, $incomingValues, $expectedReturning, $expectedAfter, $updatedB]) {
    $tests['real upstream ' . $name] = static function (TestRunner $t) use ($beforeRows, $incomingValues, $expectedReturning, $expectedAfter, $updatedB, $project): void {
        $valuesSql = implode(',', array_map(
            static fn (array $row): string => '(' . implode(',', $row) . ')',
            $incomingValues,
        ));

        $result = SQLiteUpsertReturningSql::execute(
            "INSERT INTO app_settings(a,b,c) VALUES {$valuesSql} ON CONFLICT(a) DO UPDATE SET b={$updatedB} RETURNING a,b,c",
            ['app_settings' => $beforeRows],
            [['a']],
        );

        $t->same($expectedReturning, $project($result['returning']));
        $t->same($expectedAfter, $project($result['after']));
        $t->same([$expectedAfter[1]], $project($result['updated_rows']));
        $t->same([$expectedAfter[3], $expectedAfter[4]], $project($result['inserted_rows']));
        $t->same(3, $result['changes']);
    };
}

$fooCases = [];
for ($i = 0; $i < 30; $i++) {
    $first = 17 + $i;
    $second = 4711 + $i;
    $fooCases["returning1-17 parser duplicate input returns existing id variant {$i}"] = [$first, $second];
}

foreach ($fooCases as $name => [$firstValue, $secondValue]) {
    $tests['real upstream ' . $name] = static function (TestRunner $t) use ($firstValue, $secondValue, $project): void {
        $result = SQLiteUpsertReturningSql::execute(
            "INSERT INTO app_counters(fooid,fooval,refcnt) VALUES(1,{$firstValue},1),(2,{$secondValue},1),(3,{$firstValue},1) ON CONFLICT(fooval) DO UPDATE SET refcnt=refcnt+1 RETURNING fooid",
            ['app_counters' => []],
            [['fooval']],
        );

        $t->same([[1], [2], [1]], $project($result['returning']));
        $t->same([[1, $firstValue, 2], [2, $secondValue, 1]], $project($result['after']));
        $t->same([[1, $firstValue, 2]], $project($result['updated_rows']));
        $t->same([[1, $firstValue, 1], [2, $secondValue, 1]], $project($result['inserted_rows']));
        $t->same(3, $result['changes']);
    };
}

$tests['real upstream returning1-4.2 parser update returns final row image'] = static function (TestRunner $t): void {
    $result = SQLiteUpsertReturningSql::execute(
        'INSERT INTO app_settings(a,b,c) VALUES(1,22,33) ON CONFLICT(a) DO UPDATE SET b=44 RETURNING *',
        ['app_settings' => [['a' => 1, 'b' => 2, 'c' => 3]]],
        [['a']],
    );

    $t->same([['a' => 1, 'b' => 44, 'c' => 3]], $result['returning']);
    $t->same([['a' => 1, 'b' => 44, 'c' => 3]], $result['after']);
    $t->same(1, $result['changes']);
};

return $tests;
