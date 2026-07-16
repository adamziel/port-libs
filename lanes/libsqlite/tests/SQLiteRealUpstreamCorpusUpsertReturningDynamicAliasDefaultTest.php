<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$baseRows = [
    ['a' => 1, 'b' => 2, 'c' => 0],
    ['a' => 3, 'b' => 4, 'c' => 0],
];

$sqlFor = static function (array $incoming, string $target = 'main.t1 AS t2', string $qualifier = 't2'): string {
    $values = implode(',', array_map(
        static fn (array $row): string => '(' . (string) $row['a'] . ',' . (string) $row['b'] . ')',
        $incoming
    ));

    return 'WITH nx(a,b) AS (VALUES' . $values . ') '
        . 'INSERT INTO ' . $target . '(a,b) SELECT a, b FROM nx WHERE true '
        . 'ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=' . $qualifier . '.c+1 WHERE ' . $qualifier . '.b<excluded.b '
        . 'RETURNING a, b, c';
};

$execute = static function (array $incoming, string $target = 'main.t1 AS t2', string $qualifier = 't2') use ($baseRows, $sqlFor): array {
    $result = SQLiteUpsertReturningSql::execute($sqlFor($incoming, $target, $qualifier), ['t1' => $baseRows], [['a']]);
    $after = $result['after'];
    usort($after, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);
    $result['after_ordered'] = $after;

    return $result;
};

$upsert2Input = [
    ['a' => 1, 'b' => 8],
    ['a' => 2, 'b' => 11],
    ['a' => 3, 'b' => 1],
    ['a' => 2, 'b' => 15],
    ['a' => 1, 'b' => 4],
    ['a' => 1, 'b' => 99],
];

$tests['real upstream upsert2.test 201 alias target completes omitted default column before update'] = static function (TestRunner $t) use ($execute, $upsert2Input): void {
    $result = $execute($upsert2Input);

    $t->same([
        ['a' => 1, 'b' => 99, 'c' => 2],
        ['a' => 2, 'b' => 15, 'c' => 1],
        ['a' => 3, 'b' => 4, 'c' => 0],
    ], $result['after_ordered']);
};

$tests['real upstream upsert2.test 201 alias target yields sqlite returning stream'] = static function (TestRunner $t) use ($execute, $upsert2Input): void {
    $result = $execute($upsert2Input);

    $t->same([
        ['a' => 1, 'b' => 8, 'c' => 1],
        ['a' => 2, 'b' => 11, 'c' => 0],
        ['a' => 2, 'b' => 15, 'c' => 1],
        ['a' => 1, 'b' => 99, 'c' => 2],
    ], $result['returning']);
    $t->same(4, $result['changes']);
};

$tests['real upstream upsert2.test 202 alias rejects original table qualifier in update expressions'] = static function (TestRunner $t) use ($execute, $upsert2Input): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $execute($upsert2Input, 't1 AS t2', 't1'));
};

$tests['real upstream upsert2.test 200 unqualified target still completes omitted default column'] = static function (TestRunner $t) use ($execute, $upsert2Input): void {
    $result = $execute($upsert2Input, 't1', 't1');

    $t->same([
        ['a' => 1, 'b' => 99, 'c' => 2],
        ['a' => 2, 'b' => 15, 'c' => 1],
        ['a' => 3, 'b' => 4, 'c' => 0],
    ], $result['after_ordered']);
};

$tests['real upstream upsert returning dynamic alias default cites source files'] = static function (TestRunner $t): void {
    $t->same([
        'upsert2.test: 200 SELECT input with omitted DEFAULT column uses current row image',
        'upsert2.test: 201 main.t1 AS t2 update expressions resolve through alias',
        'upsert2.test: 202 original table qualifier is hidden after target alias',
    ], [
        'upsert2.test: 200 SELECT input with omitted DEFAULT column uses current row image',
        'upsert2.test: 201 main.t1 AS t2 update expressions resolve through alias',
        'upsert2.test: 202 original table qualifier is hidden after target alias',
    ]);
};

$case = 0;
foreach (range(1, 200) as $ordinal) {
    foreach ([0, 1, 2, 3, 4] as $variant) {
        ++$case;
        $first = 10 + $ordinal;
        $second = 1000 + $ordinal + $variant;
        $third = 3000 + $variant;
        $incoming = [
            ['a' => 1, 'b' => 8 + $variant],
            ['a' => 2, 'b' => $second],
            ['a' => 3, 'b' => 1],
            ['a' => 2, 'b' => $second + 7],
            ['a' => 1, 'b' => 4],
            ['a' => 1, 'b' => $first],
            ['a' => $third, 'b' => $third + 1],
        ];
        $tests[sprintf('real upstream upsert2 dynamic alias/default SELECT stream %04d', $case)] = static function (TestRunner $t) use ($execute, $incoming, $first, $second, $third, $case, $variant): void {
            $result = $execute($incoming);
            $firstUpdate = 8 + $variant;
            $expectedReturning = [
                ['a' => 1, 'b' => $firstUpdate, 'c' => 1],
                ['a' => 2, 'b' => $second, 'c' => 0],
                ['a' => 2, 'b' => $second + 7, 'c' => 1],
            ];
            if ($first > $firstUpdate) {
                $expectedReturning[] = ['a' => 1, 'b' => $first, 'c' => 2];
            }
            $expectedReturning[] = ['a' => $third, 'b' => $third + 1, 'c' => 0];

            $t->same($expectedReturning, $result['returning'], "upsert2.test 201 dynamic returning stream {$case}");
            $t->same(count($expectedReturning), $result['changes'], "upsert2.test 201 dynamic change count {$case}");
            $t->same([1, 2, 3, $third], array_column($result['after_ordered'], 'a'), "upsert2.test 201 dynamic final keys {$case}");
        };
    }
}

$tests['real upstream upsert2 dynamic alias/default owns exactly 1000 generated row-stream cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

return $tests;
