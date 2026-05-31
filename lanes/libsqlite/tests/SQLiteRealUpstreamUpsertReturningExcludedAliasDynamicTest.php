<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicPlan;

$tests = [];

$columns = ['w', 'x', 'a_b', 'z'];
$baseRowsFor = static fn (int $case): array => [
    ['w' => 'a' . $case, 'x' => 1, 'a_b' => 1, 'z' => 1],
    ['w' => 'b' . $case, 'x' => 2, 'a_b' => 2, 'z' => 2],
];

for ($i = 0; $i < 250; ++$i) {
    $prefix = sprintf('real upstream upsert4 excluded alias dynamic %03d ', $i);

    $tests[$prefix . 'upsert4-7.1 table named excluded still resolves excluded pseudo-row'] = static function (TestRunner $t) use ($baseRowsFor, $columns, $i): void {
        $actual = SQLiteUpsertReturningDynamicPlan::execute(
            $baseRowsFor($i),
            [['w' => 'hello' . $i, 'x' => 1, 'a_b' => 1, 'z' => null]],
            $columns,
            ['x', 'a_b'],
            [],
            ['w' => 'excluded.w'],
            ['w', 'x', 'a_b', 'z'],
        );

        $t->same('update', $actual['decisions'][0]['action']);
        $t->same('hello' . $i, $actual['after'][0]['w']);
        $t->same([['w' => 'hello' . $i, 'x' => 1, 'a_b' => 1, 'z' => 1, '_upsert_action' => 'update', '_statement_sequence' => 1, '_old' => ['w' => 'a' . $i, 'x' => 1, 'a_b' => 1, 'z' => 1]]], $actual['returning_rows']);
    };

    $tests[$prefix . 'upsert4-7.2 target alias does not shadow excluded pseudo-row in WHERE false branch'] = static function (TestRunner $t) use ($baseRowsFor, $columns, $i): void {
        $actual = SQLiteUpsertReturningDynamicPlan::execute(
            $baseRowsFor($i),
            [['w' => 'hello', 'x' => 1, 'a_b' => 1, 'z' => null]],
            $columns,
            ['x', 'a_b'],
            [],
            ['w' => static fn (array $old): string => (string) $old['w'] . (string) $old['w']],
            ['w', 'x', 'a_b', 'z'],
            null,
            false,
            'x',
            [],
            static fn (array $old, array $candidate): bool => $candidate['w'] !== 'hello',
        );

        $t->same('skip', $actual['decisions'][0]['action']);
        $t->same('a' . $i, $actual['after'][0]['w']);
        $t->same([], $actual['returning_rows']);
    };

    $tests[$prefix . 'upsert4-7.3 target alias admits excluded column predicate true branch'] = static function (TestRunner $t) use ($baseRowsFor, $columns, $i): void {
        $actual = SQLiteUpsertReturningDynamicPlan::execute(
            $baseRowsFor($i),
            [['w' => 'hello' . $i, 'x' => 1, 'a_b' => 1, 'z' => null]],
            $columns,
            ['x', 'a_b'],
            [],
            ['w' => static fn (array $old): string => (string) $old['w'] . (string) $old['w']],
            ['w', 'x', 'a_b'],
            null,
            false,
            'x',
            [],
            static fn (array $old, array $candidate): bool => $candidate['x'] === 1,
        );

        $t->same('update', $actual['decisions'][0]['action']);
        $t->same('a' . $i . 'a' . $i, $actual['after'][0]['w']);
        $t->same([['w' => 'a' . $i . 'a' . $i, 'x' => 1, 'a_b' => 1, '_upsert_action' => 'update', '_statement_sequence' => 1, '_old' => ['w' => 'a' . $i, 'x' => 1, 'a_b' => 1, 'z' => 1]]], $actual['returning_rows']);
    };

    $tests[$prefix . 'upsert4-7.4 secondary unique conflict target uses incoming excluded row values'] = static function (TestRunner $t) use ($baseRowsFor, $columns, $i): void {
        $actual = SQLiteUpsertReturningDynamicPlan::execute(
            $baseRowsFor($i),
            [['w' => 'replacement' . $i, 'x' => 99, 'a_b' => 99, 'z' => 2]],
            $columns,
            ['z'],
            [],
            [
                'w' => 'excluded.w',
                'x' => 'excluded.x',
                'a_b' => 'excluded.a_b',
            ],
            ['w', 'x', 'a_b', 'z'],
        );

        $t->same('update', $actual['decisions'][0]['action']);
        $t->same(['w' => 'replacement' . $i, 'x' => 99, 'a_b' => 99, 'z' => 2], array_intersect_key($actual['after'][1], array_flip($columns)));
        $t->same('b' . $i, $actual['returning_rows'][0]['_old']['w']);
    };
}

return $tests;
